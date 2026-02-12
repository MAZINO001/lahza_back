<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Invoice;
use App\Models\InvoiceSubscription;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionInvoiceService
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Create invoice for direct subscription purchase (Path 1)
     * Called when client buys a plan directly via /subscriptions endpoint
     */
    public function createInvoiceForSubscription(
        Client $client,
        Plan $plan,
        PlanPrice $planPrice,
        array $customFieldValues = []
    ): array {
        return DB::transaction(function () use ($client, $plan, $planPrice, $customFieldValues) {
            
            // 1. Create the invoice
            $invoice = Invoice::create([
                'client_id' => $client->id,
                'quote_id' => null,
                'invoice_date' => now(),
                'due_date' => now()->addDays(30),
                'status' => 'unpaid',
                'notes' => "Subscription: {$plan->name} ({$planPrice->interval})",
                'total_amount' => $planPrice->price,
                'balance_due' => $planPrice->price,
                'description' => "Subscription plan: {$plan->name}",
                'has_projects' => null,
            ]);

            // 2. Create invoice_subscription record
            $invoiceSubscription = InvoiceSubscription::create([
                'invoice_id' => $invoice->id,
                'plan_id' => $plan->id,
                'subscription_id' => null, // Will be set after payment
                'price_snapshot' => $planPrice->price,
                'billing_cycle' => $planPrice->interval,
            ]);

            // 3. Determine payment method
            $paymentMethod = strtolower($client->country) === 'maroc' ? 'bank' : 'stripe';

            // 4. Create payment (100% for subscription - pending)
            $paymentResponse = $this->paymentService->createPaymentLink(
                $invoice,
                100.0, // Full payment for subscription
                'pending',
                $paymentMethod
            );

            // 5. Get the created payment
            $payment = $invoice->payments()->latest()->first();

            // // 6. Create payment allocation for subscription (100%)
            // if ($payment) {
            //     $payment->allocations()->create([
            //         'invoice_subscription_id' => $invoiceSubscription->id,
            //         'allocatable_type' => 'subscription',
            //         'amount' => $planPrice->price,
            //     ]);
            // }

            Log::info('Subscription invoice created', [
                'invoice_id' => $invoice->id,
                'plan_id' => $plan->id,
                'client_id' => $client->id,
                'amount' => $planPrice->price,
            ]);

            return [
                'invoice' => $invoice->load('invoiceSubscriptions.plan'),
                'payment' => $paymentResponse,
                'custom_field_values' => $customFieldValues, // Store for later subscription creation
            ];
        });
    }

    /**
     * Create actual subscription after payment allocation is paid
     * Called from webhook handler
     */
    public function createSubscriptionFromPaidAllocation(
        InvoiceSubscription $invoiceSubscription,
        array $customFieldValues = []
    ): Subscription {
        // Verify it's fully paid
        if (!$invoiceSubscription->isFullyPaid()) {
            throw new \Exception("Cannot create subscription: Invoice subscription #{$invoiceSubscription->id} is not fully paid");
        }

        // Check if subscription already exists
        if ($invoiceSubscription->subscription_id) {
            return Subscription::find($invoiceSubscription->subscription_id);
        }

        $client = $invoiceSubscription->invoice->client;
        $plan = $invoiceSubscription->plan;
        $planPrice = $invoiceSubscription->getPlanPrice();

        if (!$planPrice) {
            throw new \Exception("Plan price not found for invoice subscription #{$invoiceSubscription->id}");
        }

        // Create the subscription
        $subscriptionService = app(SubscriptionService::class);
        
        $subscription = $subscriptionService->createSubscription(
            $client,
            $plan,
            $planPrice,
            $customFieldValues,
            'active' // Active immediately since it's paid
        );

        // Link subscription to invoice_subscription
        $invoiceSubscription->update([
            'subscription_id' => $subscription->id,
        ]);

        Log::info('Subscription created from paid allocation', [
            'subscription_id' => $subscription->id,
            'invoice_subscription_id' => $invoiceSubscription->id,
            'client_id' => $client->id,
        ]);

        return $subscription;
    }

    /**
     * Calculate totals for invoice with both services and subscriptions
     */
    public function calculateInvoiceTotals(array $services = [], array $subscriptions = []): array
    {
        $servicesTotal = 0;
        $subscriptionsTotal = 0;

        // Calculate services total
        foreach ($services as $service) {
            $servicesTotal += $service['individual_total'] ?? 0;
        }

        // Calculate subscriptions total
        foreach ($subscriptions as $subscription) {
            $subscriptionsTotal += $subscription['price_snapshot'];
        }

        $total = $servicesTotal + $subscriptionsTotal;

        return [
            'services_total' => round($servicesTotal, 2),
            'subscriptions_total' => round($subscriptionsTotal, 2),
            'total_amount' => round($total, 2),
        ];
    }

   /**
 * Create payment allocations for invoice with both services and subscriptions
 */
public function createPaymentAllocations(
    $payment,
    Invoice $invoice,
    float $paymentPercentage = 100
): void {
    $invoiceSubscriptions = $invoice->invoiceSubscriptions;
    $hasServices = $invoice->invoiceServices()->count() > 0;

    if ($invoiceSubscriptions->isEmpty() && !$hasServices) {
        return;
    }

    $allocations = [];

    // 1. Create allocations for subscriptions
    //    Use InvoiceSubscription as source of truth and allocate ONLY the remaining amount.
    foreach ($invoiceSubscriptions as $invoiceSubscription) {
        // Money already paid for this subscription across PAID payments
        $alreadyPaidAmount = $invoiceSubscription->getTotalPaid();

        // Remaining amount for this subscription
        $remainingAmount = max(0, $invoiceSubscription->price_snapshot - $alreadyPaidAmount);

        // Only create allocation if there is something left to pay
        if ($remainingAmount > 0) {
            $allocations[] = [
                'payment_id' => $payment->id,
                'invoice_subscription_id' => $invoiceSubscription->id,
                'allocatable_type' => 'subscription',
                'amount' => round($remainingAmount, 2),
                'paid_percentage' => 0, // Will be set when payment is marked as paid
            ];
        }
    }

    // 2. Create allocation for services - uses payment percentage, check what's already paid
    if ($hasServices) {
        $servicesTotal = $invoice->invoiceServices->sum('individual_total');
        
        // Check what % of services is already paid
        $alreadyPaidPercentage = DB::table('payment_allocations')
            ->whereNull('invoice_subscription_id')
            ->where('allocatable_type', 'invoice')
            ->whereIn('payment_id', function($query) use ($invoice) {
                $query->select('id')
                    ->from('payments')
                    ->where('invoice_id', $invoice->id)
                    ->where('status', 'paid'); // Only count paid payments
            })
            ->sum('paid_percentage');
        
        $remainingPercentage = 100 - $alreadyPaidPercentage;
        
        // Only create allocation if there's remaining percentage
        if ($remainingPercentage > 0) {
            // Services use the payment percentage (up to remaining)
            $allocationPercentage = min($paymentPercentage, $remainingPercentage);
            $servicesAllocationAmount = ($servicesTotal * $allocationPercentage) / 100;
            
            $allocations[] = [
                'payment_id' => $payment->id,
                'invoice_subscription_id' => null,
                'allocatable_type' => 'invoice',
                'amount' => round($servicesAllocationAmount, 2),
                'paid_percentage' => 0, // Will be set when payment is paid
            ];
        }
    }

    // Bulk insert allocations
    if (!empty($allocations)) {
        DB::table('payment_allocations')->insert($allocations);
        
        Log::info('Payment allocations created', [
            'payment_id' => $payment->id,
            'allocations_count' => count($allocations),
        ]);
    }
}
}