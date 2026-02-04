<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use App\Services\SubscriptionInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    protected SubscriptionService $subscriptionService;
    protected SubscriptionInvoiceService $subscriptionInvoiceService;

    public function __construct(
        SubscriptionService $subscriptionService,
        SubscriptionInvoiceService $subscriptionInvoiceService
    ) {
        $this->subscriptionService = $subscriptionService;
        $this->subscriptionInvoiceService = $subscriptionInvoiceService;
    }

    /**
     * Display a listing of subscriptions.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Subscription::with(['client', 'plan', 'planPrice', 'customFieldValues.customField']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by client
        if ($request->has('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        // Filter by plan
        if ($request->has('plan_id')) {
            $query->where('plan_id', $request->plan_id);
        }

        $subscriptions = $query->paginate($request->get('per_page', 15));

        return response()->json($subscriptions);
    }

    /**
     * Store a newly created subscription - NOW creates invoice + payment
     * This is for direct subscription purchase (Path 1)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|exists:clients,id',
            'plan_id' => 'required|exists:plans,id',
            'plan_price_id' => 'required|exists:plan_prices,id',
            'custom_field_values' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $client = Client::findOrFail($request->client_id);
            $plan = Plan::findOrFail($request->plan_id);
            $planPrice = PlanPrice::findOrFail($request->plan_price_id);

            // Verify that the plan price belongs to the plan
            if ($planPrice->plan_id !== $plan->id) {
                return response()->json(['error' => 'Plan price does not belong to the selected plan'], 422);
            }

            // Create invoice + payment for subscription
            $result = $this->subscriptionInvoiceService->createInvoiceForSubscription(
                $client,
                $plan,
                $planPrice,
                $request->get('custom_field_values', [])
            );

            return response()->json([
                'message' => 'Subscription invoice created successfully. Subscription will be activated after payment.',
                'invoice' => $result['invoice'],
                'payment' => $result['payment'],
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create subscription invoice: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified subscription.
     */
    public function show(Subscription $subscription): JsonResponse
    {
        $subscription->load(['client', 'plan', 'planPrice', 'customFieldValues.customField', 'invoices']);

        $customFieldValues = $this->subscriptionService->getCustomFieldValues($subscription);

        return response()->json([
            'subscription' => $subscription,
            'custom_values' => $customFieldValues
        ]);
    }

    /**
     * Update the specified subscription.
     */
    public function update(Request $request, Subscription $subscription): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:trial,active,past_due,cancelled,expired',
            'custom_field_values' => 'nullable|array',
            'stripe_subscription_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            if ($request->has('status')) {
                $this->subscriptionService->updateStatus($subscription, $request->status);
            }

            if ($request->has('custom_field_values')) {
                $this->subscriptionService->setCustomFieldValues($subscription, $request->custom_field_values);
            }

            if ($request->has('stripe_subscription_id')) {
                $subscription->update(['stripe_subscription_id' => $request->stripe_subscription_id]);
            }

            return response()->json([
                'message' => 'Subscription updated successfully',
                'subscription' => $subscription->fresh(['client', 'plan', 'planPrice', 'customFieldValues.customField'])
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update subscription: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Cancel a subscription.
     */
    public function cancel(Request $request, Subscription $subscription): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'immediate' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $immediate = $request->get('immediate', false);
            $subscription = $this->subscriptionService->cancelSubscription($subscription, $immediate);

            return response()->json([
                'message' => 'Subscription cancelled successfully',
                'subscription' => $subscription
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to cancel subscription: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Renew a subscription.
     */
    public function renew(Subscription $subscription): JsonResponse
    {
        try {
            $subscription = $this->subscriptionService->renewSubscription($subscription);

            return response()->json([
                'message' => 'Subscription renewed successfully',
                'subscription' => $subscription
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to renew subscription: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Change subscription plan.
     */
    public function changePlan(Request $request, Subscription $subscription): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:plans,id',
            'plan_price_id' => 'required|exists:plan_prices,id',
            'immediate' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $newPlan = Plan::findOrFail($request->plan_id);
            $newPlanPrice = PlanPrice::findOrFail($request->plan_price_id);

            // Verify that the plan price belongs to the plan
            if ($newPlanPrice->plan_id !== $newPlan->id) {
                return response()->json(['error' => 'Plan price does not belong to the selected plan'], 422);
            }

            $immediate = $request->get('immediate', true);
            $subscription = $this->subscriptionService->changePlan($subscription, $newPlan, $newPlanPrice, $immediate);

            return response()->json([
                'message' => 'Subscription plan changed successfully',
                'subscription' => $subscription
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to change plan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get subscription statistics.
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->subscriptionService->getSubscriptionStats();

            return response()->json(['stats' => $stats]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve stats: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get active subscription for a client.
     */
    public function getActiveSubscription(Client $client): JsonResponse
    {
        try {
            $subscription = $this->subscriptionService->getActiveSubscription($client);

            if (!$subscription) {
                return response()->json(['message' => 'No active subscription found'], 404);
            }

            $customFieldValues = $this->subscriptionService->getCustomFieldValues($subscription);

            return response()->json([
                'subscription' => $subscription,
                'custom_values' => $customFieldValues
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve subscription: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Check if subscription has reached a limit.
     */
    public function checkLimit(Request $request, Subscription $subscription): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit_key' => 'required|string',
            'current_usage' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $hasReachedLimit = $this->subscriptionService->hasReachedLimit(
                $subscription,
                $request->limit_key,
                $request->current_usage
            );

            $limit = $subscription->getCustomFieldValue($request->limit_key);

            return response()->json([
                'limit_key' => $request->limit_key,
                'limit_value' => $limit,
                'current_usage' => $request->current_usage,
                'has_reached_limit' => $hasReachedLimit,
                'remaining' => $limit !== null ? max(0, $limit - $request->current_usage) : null
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to check limit: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified subscription.
     */
    public function destroy(Subscription $subscription): JsonResponse
    {
        try {
            $subscription->delete();

            return response()->json(['message' => 'Subscription deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete subscription: ' . $e->getMessage()], 500);
        }
    }
}