<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\SubscriptionCustomField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PlanController extends Controller
{
    /**
     * Display a listing of plans.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Plan::with(['pack', 'prices', 'customFields']);

        // Filter by pack
        if ($request->has('pack_id')) {
            $query->where('pack_id', $request->pack_id);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Only active plans
        if ($request->get('active_only', false)) {
            $query->active();
        }

        $plans = $query->paginate($request->get('per_page', 15));

        return response()->json($plans);
    }

    /**
     * Store a newly created plan.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pack_id' => 'required|exists:packs,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'prices' => 'nullable|array',
            'prices.*.interval' => 'required|in:monthly,yearly',
            'prices.*.price' => 'required|numeric|min:0',
            'prices.*.currency' => 'nullable|string|max:10',
            'prices.*.stripe_price_id' => 'nullable|string',
            'custom_fields' => 'nullable|array',
            'custom_fields.*.key' => 'required|string',
            'custom_fields.*.label' => 'nullable|string',
            'custom_fields.*.type' => 'required|in:number,boolean,text,json',
            'custom_fields.*.default_value' => 'nullable|string',
            'custom_fields.*.required' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $plan = Plan::create([
                'pack_id' => $request->pack_id,
                'name' => $request->name,
                'description' => $request->description,
                'is_active' => $request->get('is_active', true),
            ]);

            // Create prices
            if ($request->has('prices')) {
                foreach ($request->prices as $priceData) {
                    PlanPrice::create([
                        'plan_id' => $plan->id,
                        'interval' => $priceData['interval'],
                        'price' => $priceData['price'],
                        'currency' => $priceData['currency'] ?? 'USD',
                        'stripe_price_id' => $priceData['stripe_price_id'] ?? null,
                    ]);
                }
            }

            // Create custom fields
            if ($request->has('custom_fields')) {
                foreach ($request->custom_fields as $fieldData) {
                    SubscriptionCustomField::create([
                        'plan_id' => $plan->id,
                        'key' => $fieldData['key'],
                        'label' => $fieldData['label'] ?? null,
                        'type' => $fieldData['type'],
                        'default_value' => $fieldData['default_value'] ?? null,
                        'required' => $fieldData['required'] ?? false,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Plan created successfully',
                'plan' => $plan->load(['prices', 'customFields'])
            ], 201);
        }
        catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create plan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified plan.
     */
    public function show(Plan $plan): JsonResponse
    {
        $plan->load(['pack', 'prices', 'customFields', 'subscriptions']);

        return response()->json(['plan' => $plan]);
    }

    /**
     * Update the specified plan.
     */
    public function update(Request $request, Plan $plan): JsonResponse
    {
        $validator = Validator::make($request->all(), 
        [
            'pack_id' => 'nullable|exists:packs,id',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]
        );

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try 
        {
            $plan->update($request->all());

            return response()->json([
                'message' => 'Plan updated successfully',
                'plan' => $plan->fresh(['pack', 'prices', 'customFields'])
            ]);
        }
        catch (\Exception $e) 
        {
            return response()->json(['error' => 'Failed to update plan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified plan.
     */
    public function destroy(Plan $plan): JsonResponse
    {
        try {
            $plan->delete();
            return response()->json(['message' => 'Plan deleted successfully']);
        }
        catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete plan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Add a price to a plan.
     */
    public function addPrice(Request $request, Plan $plan): JsonResponse
    {
        $validator = Validator::make($request->all(), 
        [
            'interval' => 'required|in:monthly,yearly,quarterly',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'stripe_price_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Check if price already exists for this interval
            $existingPrice = $plan->prices()->where('interval', $request->interval)->first();
            if ($existingPrice) {
                return response()->json(['error' => 'Price for this interval already exists'], 422);
            }

            $price = PlanPrice::create([
                'plan_id' => $plan->id,
                'interval' => $request->interval,
                'price' => $request->price,
                'currency' => $request->get('currency', 'USD'),
                'stripe_price_id' => $request->stripe_price_id,
            ]);

            return response()->json([
                'message' => 'Price added successfully',
                'price' => $price
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to add price: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update a plan price.
     */
    public function updatePrice(Request $request, Plan $plan, PlanPrice $price): JsonResponse
    {
        // Verify the price belongs to the plan
        if ($price->plan_id !== $plan->id) {
            return response()->json(['error' => 'Price does not belong to this plan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'stripe_price_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $price->update($request->all());

            return response()->json([
                'message' => 'Price updated successfully',
                'price' => $price->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update price: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Add a custom field to a plan.
     */
    public function addCustomField(Request $request, Plan $plan): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string',
            'label' => 'nullable|string',
            'type' => 'required|in:number,boolean,text,json',
            'default_value' => 'nullable|string',
            'required' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Check if custom field with this key already exists
            $existingField = $plan->customFields()->where('key', $request->key)->first();
            if ($existingField) {
                return response()->json(['error' => 'Custom field with this key already exists'], 422);
            }

            $customField = SubscriptionCustomField::create([
                'plan_id' => $plan->id,
                'key' => $request->key,
                'label' => $request->label,
                'type' => $request->type,
                'default_value' => $request->default_value,
                'required' => $request->get('required', false),
            ]);

            return response()->json([
                'message' => 'Custom field added successfully',
                'custom_field' => $customField
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to add custom field: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update a custom field.
     */
    public function updateCustomField(Request $request, Plan $plan, SubscriptionCustomField $customField): JsonResponse
    {
        // Verify the custom field belongs to the plan
        if ($customField->plan_id !== $plan->id) {
            return response()->json(['error' => 'Custom field does not belong to this plan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'label' => 'nullable|string',
            'type' => 'nullable|in:number,boolean,text,json',
            'default_value' => 'nullable|string',
            'required' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $customField->update($request->all());

            return response()->json([
                'message' => 'Custom field updated successfully',
                'custom_field' => $customField->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update custom field: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete a custom field.
     */
    public function deleteCustomField(Plan $plan, SubscriptionCustomField $customField): JsonResponse
    {
        // Verify the custom field belongs to the plan
        if ($customField->plan_id !== $plan->id) {
            return response()->json(['error' => 'Custom field does not belong to this plan'], 404);
        }

        try {
            $customField->delete();

            return response()->json(['message' => 'Custom field deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete custom field: ' . $e->getMessage()], 500);
        }
    }
}
