<?php

namespace App\Services;

use App\Models\Invoice;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Webhook;
use App\Repositories\PaymentRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Payment; 
use App\Services\ProjectCreationService;
use App\Services\ActivityLoggerService;
use Illuminate\Support\Facades\Mail;
use App\Mail\PaymentSuccessfulMail;
use App\Models\Subscription;
use App\Models\PaymentAllocation;
class PaymentService implements PaymentServiceInterface
{
    protected $paymentRepository;
    protected $projectCreationService;
    protected $activityLogger;

    public function __construct(PaymentRepository $paymentRepository, ProjectCreationService $projectCreationService, ActivityLoggerService $activityLogger)
    {
        $this->paymentRepository = $paymentRepository;
        $this->projectCreationService = $projectCreationService;
        $this->activityLogger = $activityLogger;

    }
    
    public function getPayment()
    {
         return $this->paymentRepository->getPayment();
    }

    /**
     * Validate invoice for payment creation
     */
    protected function validateInvoiceForPayment(Invoice $invoice): void
    {
        // Check if invoice is already fully paid
        if ($invoice->status === 'paid' || $invoice->balance_due <= 0) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'status' => 'error',
                    'message' => "Cannot create payment: Invoice #{$invoice->id} is already fully paid. Balance due: $" . number_format($invoice->balance_due, 2)
                ], 400)
            );
        }

        // Check if invoice status allows payments
        if (!in_array($invoice->status, ['sent', 'unpaid', 'partially_paid', 'overdue'])) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'status' => 'error',
                    'message' => "Cannot create payment: Invoice #{$invoice->id} has status '{$invoice->status}' which does not allow payments. Only 'sent', 'unpaid', 'partially_paid', or 'overdue' invoices can receive payments."
                ], 400)
            );
        }
    }

    /**
     * Check if there's an existing pending payment for the invoice
     */
    protected function checkPendingPayment(Invoice $invoice): ?Payment
    {
        return $invoice->payments()
            ->where('status', 'pending')
            ->first();
    }

    /**
     * Update invoice status and balance based on payments
     */
    public function updateInvoiceStatus(Invoice $invoice): void
    {
        $totalPaid = $invoice->payments()
            ->where('status', 'paid')
            ->sum('amount');
        
        $balanceDue = max(0, $invoice->total_amount - $totalPaid);
        
        $status = $balanceDue <= 0 ? 'paid' : ($balanceDue < $invoice->total_amount ? 'partially_paid' : 'unpaid');
        
        if($invoice->quote && $status === 'paid'){
            $invoice->quote->update(['status' => 'paid']);
        }elseif($invoice->quote && $status === 'partially_paid'){
            $invoice->quote->update(['status' => 'billed']);
        }
        // Use query builder to update directly, bypassing model attributes
        // This ensures non-database attributes like admin_signature_url are not included
        DB::table('invoices')
            ->where('id', $invoice->id)
            ->update([
                'balance_due' => $balanceDue,
                'status' => $status
            ]);
        
        // Refresh the model to reflect the changes
        $invoice->refresh();
    }

    /**
     * Create payment link - payment method, percentage, and status should be passed as parameters
     */
    public function createPaymentLink(Invoice $invoice, float $payment_percentage, string $payment_status, string $payment_type): array
    {
        // Validate payment_method matches ENUM values
        $allowedPaymentMethods = ['stripe', 'bank', 'cash', 'cheque'];
        if (!in_array($payment_type, $allowedPaymentMethods)) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'status' => 'error',
                    'message' => "Invalid payment method: '{$payment_type}'. Allowed values are: " . implode(', ', $allowedPaymentMethods)
                ], 400)
            );
        }

        // Validate invoice status
        $this->validateInvoiceForPayment($invoice);

        // Only check for pending payments if we're creating a new pending payment
        if ($payment_status === 'pending') {
            $existingPendingPayment = $this->checkPendingPayment($invoice);
            if ($existingPendingPayment) {
                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                    response()->json([
                        'status' => 'error',
                        'message' => "Cannot create payment: There is already a pending payment (ID: {$existingPendingPayment->id}) for invoice #{$invoice->id}. Please complete or cancel the existing payment before creating a new one."
                    ], 400)
                );
            }
        }

        // Get current balance due from invoice
        $currentBalanceDue = $invoice->balance_due;
        
        // Calculate amount based on invoice composition:
        // - Subscriptions: use ONLY remaining amount per subscription
        //   (price_snapshot - totalPaid), never exceeding balance_due.
        // - Services use the given percentage (acts as "services percentage"),
        //   but we NEVER exceed the current balance_due.
        $invoiceSubscriptions = $invoice->invoiceSubscriptions;
        $subscriptionsTotal = $invoiceSubscriptions->sum(function ($sub) {
            /** @var \App\Models\InvoiceSubscription $sub */
            $alreadyPaid = $sub->getTotalPaid();
            return max(0, $sub->price_snapshot - $alreadyPaid);
        });
        $servicesTotal = $invoice->invoiceServices->sum('individual_total');

        // Budget left for services part given that subscriptions are 100%
        $maxServicesBudget = max(0, $currentBalanceDue - $subscriptionsTotal);

        $rawServicesPart = ($servicesTotal * $payment_percentage) / 100;
        $servicesPart = min($rawServicesPart, $maxServicesBudget);

        $amount = round($subscriptionsTotal + $servicesPart, 2);
        
        // Validate that amount doesn't exceed balance due (only for pending/unpaid)
        if ($payment_status !== 'paid' && $amount > $currentBalanceDue) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'status' => 'error',
                    'message' => "Cannot create payment: The requested payment amount ($" . number_format($amount, 2) . ") exceeds the remaining balance due ($" . number_format($currentBalanceDue, 2) . ") for invoice #{$invoice->id}."
                ], 400)
            );
        }

        // Validate percentage
        if ($payment_percentage <= 0 || $payment_percentage > 100) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'status' => 'error',
                    'message' => "Cannot create payment: Percentage must be between 0 and 100."
                ], 400)
            );
        }

        $client = $invoice->client;

        // Effective services percentage actually used (after clamping to balance_due)
        $effectiveServicesPercentage = $servicesTotal > 0
            ? round(($servicesPart / $servicesTotal) * 100, 2)
            : 0;

        $paymentData = [
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
            'total' => $invoice->total_amount,
            'amount' => $amount,
            'currency' => 'eur',
            'payment_method' => $payment_type,
            'status' => $payment_status,
            'payment_url' => null,
            // Store the effective services percentage used for this payment
            'percentage' => $effectiveServicesPercentage,
        ];

        $response = [
            'payment_method' => $payment_type,
            'amount' => $amount,
            'total_amount' => $invoice->total_amount,
            'payment_percentage' => $payment_percentage,
            'status' => $payment_status,
            'is_advance_payment' => $payment_percentage < 100
        ];

        if ($payment_type === 'stripe') {
            // Handle Stripe payment
        Stripe::setApiKey(config('services.stripe.secret'));

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => $amount * 100,
                        'product_data' => [
                            'name' => "Payment for invoice #{$invoice->id} ({$payment_percentage}%)",
                            'description' => "{$payment_percentage}% payment for invoice #{$invoice->id}"
                        ]
                    ],
                    'quantity' => 1
                ]],
                'mode' => 'payment',
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'client_id' => $client->id,
                    'total_amount' => $invoice->total_amount,
                    'payment_amount' => $amount,
                    'percentage' => $payment_percentage,
                ],
                'success_url' => config('app.frontend_url') . '/client/projects',
                'cancel_url' => config('app.frontend_url') . '/client/invoices',
            ]);

            $paymentData['stripe_session_id'] = $session->id;
            $paymentData['stripe_payment_intent_id'] = $session->payment_intent;
            $paymentData['payment_url'] = $session->url;
            $response['payment_url'] = $session->url;
        } else {
            // Handle bank transfer or other payment methods
            $response['bank_info'] = [
                'account_holder' => 'LAHZA HM',
                'bank_name' => 'ATTIJARI WAFABANK',
                'rib' => '007640001433200000026029',
                'swift_code' => 'BCMAMAMC',
                'ICE' => '002 056 959 000 039',
                'reference' => 'PAY-' . $invoice->id . '-' . $payment_percentage . '%'
            ];
        }

        $payment = $this->paymentRepository->create($paymentData);
        // CREATE PAYMENT ALLOCATIONS
        // Use the EFFECTIVE services percentage so allocations match the payment amount
        $subscriptionInvoiceService = app(\App\Services\SubscriptionInvoiceService::class);
        $subscriptionInvoiceService->createPaymentAllocations($payment, $invoice, $effectiveServicesPercentage);

         $this->activityLogger->log(
                'clients_details',
                'payments',
                $invoice->client->id,
                request()->ip(),
                request()->userAgent(),
                [
                    'invoice_id' => $invoice->id,
                    'quote_id' => $quote->id ?? null,
                    'client_id' => $invoice->client_id,
                    'total_amount' => $invoice->total_amount,
                    'url' => request()->fullUrl()
                ],
                "Payment created for invoice #{$invoice->id} with amount: {$paymentData['amount']}"
            );
        // If status is 'paid', update the invoice immediately
        if ($payment_status === 'paid') {
            $this->updateInvoiceStatus($invoice);
            $this->autoGenerateRemainingPayment($invoice, $payment);

        }
        
        $response['payment_url'] = $response['payment_url'] ?? null;
        $response['bank_info'] = $response['bank_info'] ?? null;

        return $response;
    }

public function updatePendingPayment(Payment $payment, float $percentage, string $payment_method)
{
    // 1. Ensure payment is editable
    if ($payment->status !== 'pending') {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'status' => 'error',
                'message' => "Cannot update payment: Only pending payments can be updated. Payment ID {$payment->id} has status '{$payment->status}'."
            ], 400)
        );
    }

    // 2. Load invoice and validate
    $invoice = $payment->invoice;
    if (!$invoice) {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'status' => 'error',
                'message' => "Cannot update payment: Invoice not found for payment ID {$payment->id}."
            ], 400)
        );
    }
    $this->validateInvoiceForPayment($invoice);

    // 3. Calculate new payment amount based on invoice composition,
    //    mirroring createPaymentLink logic:
    //    - Subscriptions use remaining amount (snapshot - totalPaid)
    //    - Services controlled by the given percentage, clamped to balance_due.
    $currentBalanceDue = $invoice->balance_due;

    $invoiceSubscriptions = $invoice->invoiceSubscriptions;
    $subscriptionsTotal = $invoiceSubscriptions->sum(function ($sub) {
        /** @var \App\Models\InvoiceSubscription $sub */
        $alreadyPaid = $sub->getTotalPaid();
        return max(0, $sub->price_snapshot - $alreadyPaid);
    });

    $servicesTotal = $invoice->invoiceServices->sum('individual_total');

    // Budget left for services part given that subscriptions are 100%
    $maxServicesBudget = max(0, $currentBalanceDue - $subscriptionsTotal);

    $rawServicesPart = ($servicesTotal * $percentage) / 100;
    $servicesPart = min($rawServicesPart, $maxServicesBudget);

    $newAmount = $subscriptionsTotal + $servicesPart;

if ($newAmount <= 0) {
    throw new \Illuminate\Http\Exceptions\HttpResponseException(
        response()->json([
            'status' => 'error',
            'message' => "Cannot update payment: The calculated amount ($" . number_format($newAmount, 2) . ") must be greater than zero."
        ], 400)
    );
}

// Safety check (should not normally trigger due to clamping above)
if ($newAmount > $currentBalanceDue + 0.0001) {
    throw new \Illuminate\Http\Exceptions\HttpResponseException(
        response()->json([
            'status' => 'error',
            'message' => "Cannot update payment: The requested amount ($" . number_format($newAmount, 2) . ") exceeds the remaining balance ($" . number_format($currentBalanceDue, 2) . ")."
        ], 400)
    );
}

    // 4. Update the payment method on the model BEFORE logic check
    $payment->payment_method = $payment_method;
    $paymentUrl = null;

    // 5. Handle Stripe specific logic
    if ($payment->payment_method === 'stripe') {
    // Reset old session data
        $payment->stripe_session_id = null;
        $payment->stripe_payment_intent_id = null;

        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => (int)($newAmount * 100), // Ensure it is an integer for Stripe
                    'product_data' => [
                        'name' => "Updated payment for invoice #{$payment->invoice_id}"
                    ]
                ],
                'quantity' => 1
            ]],
            'mode' => 'payment',
            'metadata' => [
                'invoice_id' => $payment->invoice_id,
                'client_id' => $payment->client_id,
                'updated_payment' => true
            ],
            'success_url' => config('app.frontend_url') . '/client/projects',
            'cancel_url' => config('app.frontend_url') . '/client/invoices',
        ]);

        $payment->stripe_session_id = $session->id;
        $payment->stripe_payment_intent_id = $session->payment_intent;
        $payment->payment_url = $session->url;
        $paymentUrl = $session->url;
    } else {
        // If switching away from stripe, clear the URL
        $payment->payment_url = null;
    }

    // 6. Finalize Save
    // Effective services percentage after clamping
    $effectiveServicesPercentage = $servicesTotal > 0
        ? round(($servicesPart / $servicesTotal) * 100, 2)
        : 0;

    $payment->amount = round($newAmount, 2);
    $payment->percentage = $effectiveServicesPercentage;
    $payment->save();
    // RECALCULATE ALLOCATIONS BASED ON NEW PAYMENT AMOUNT
    $this->recalculatePaymentAllocations($payment);

    return [
        'status' => 'updated',
        'payment_method' => $payment->payment_method,
        'new_amount' => $payment->amount,
        'payment_url' => $paymentUrl,
    ];
}

public function handleStripeWebhook(string $payload, string $sigHeader)
{
    $secret = config('services.stripe.webhook_secret');
    try {
        $event = Webhook::constructEvent($payload, $sigHeader, $secret);
    } catch (\Exception $e) {
        Log::error('Invalid Stripe webhook: '.$e->getMessage());
        return ['status' => 'invalid'];
    }
    
    Log::info('Stripe webhook received', [
        'type' => $event->type,
        'event_id' => $event->id
    ]);

    if ($event->type === 'checkout.session.completed') {
        $session = $event->data->object;

        $payment = $this->paymentRepository->findByStripeSessionId($session->id);
        if (!$payment) {
            Log::warning('Payment not found for Stripe session: '.$session->id);
            return ['status' => 'ok'];
        }
        
        Log::info('Payment found', [
            'payment_id' => $payment->id,
            'current_status' => $payment->status,
            'amount' => $payment->amount
        ]);

        // Mark this payment as paid
        $this->paymentRepository->update($payment, [
            'status' => 'paid',
            'stripe_payment_intent_id' => $session->payment_intent,
            'payment_url' => null
        ]);

        $this->markAllocationsAsPaid($payment);

        $invoice = $payment->invoice;
        
        // Update invoice status
        $this->updateInvoiceStatus($invoice);
        
        // Handle subscription allocations - Create subscriptions for paid allocations
        $this->handleSubscriptionAllocations($payment);
        
        // Auto-generate remaining payment if needed
        $this->autoGenerateRemainingPayment($invoice, $payment);

        // Refresh invoice to get updated values
        $invoice->refresh();

        // Update projects after payment
        $paymentData = $invoice->payments()->latest()->first()?->toArray() ?? [];
        $this->projectCreationService->updateProjectAfterPayment($invoice, $paymentData);

        // Send payment success email
        $this->sendPaymentSuccessEmail($payment);
    }

    return ['status' => 'ok'];
}
/**
 * Mark all allocations of a payment with their paid_percentage
 */
protected function markAllocationsAsPaid(Payment $payment): void
{
    $invoice = $payment->invoice;
    
    foreach ($payment->allocations as $allocation) {
        if ($allocation->allocatable_type === 'subscription') {
            // Calculate what percentage of the subscription this allocation represents
            $invoiceSubscription = $allocation->invoiceSubscription;
            $allocationPercentage = ($allocation->amount / $invoiceSubscription->price_snapshot) * 100;
            
            $allocation->update(['paid_percentage' => round($allocationPercentage, 2)]);
        } else {
            // Services allocation uses the payment percentage
            $servicesTotal = $invoice->invoiceServices->sum('individual_total');
            $servicesPercentage = ($allocation->amount / $servicesTotal) * 100;
            
            $allocation->update(['paid_percentage' => round($servicesPercentage, 2)]);
        }
    }
    
    Log::info('Payment allocations marked as paid', [
        'payment_id' => $payment->id,
        'allocations_count' => $payment->allocations->count(),
    ]);
}

/**
 * Reset paid_percentage for all allocations when payment is cancelled
 */
protected function resetAllocationsPaidPercentage(Payment $payment): void
{
    foreach ($payment->allocations as $allocation) {
        $allocation->update(['paid_percentage' => 0]);
    }
    
    Log::info('Payment allocations paid_percentage reset', [
        'payment_id' => $payment->id,
        'allocations_count' => $payment->allocations->count(),
    ]);
}
/**
 * Handle subscription allocations after payment is completed
 * Creates actual subscriptions for paid subscription allocations
 */

    /**
     * Send payment success email
     */
    public function sendPaymentSuccessEmail($payment)
    {
        try {
            $invoice = $payment->invoice;
            $clientEmail = $invoice->client->user->email ?? null;

            if (!$clientEmail) {
                Log::warning('Cannot send payment success email: client has no email address', [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                ]);
                return;
            }

            // Check email notifications preference
            if (!$invoice->client->user->allowsMail('payments')) {

                Log::info('Payment success email not sent: email notifications disabled', [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                ]);
                return;
            }

            // Refresh payment to get latest data
            $payment->refresh();

            // Queue payment success email
            Mail::to($clientEmail)->queue(new PaymentSuccessfulMail($payment));

            Log::info('Payment success email queued successfully', [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'email' => $clientEmail,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue payment success email', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function getRemaining(Invoice $invoice)
    {
        $totalAmount = $invoice->total_amount;
        $totalPaid = $this->paymentRepository->getPaidAmount($invoice);

        return $totalAmount - $totalPaid;
    }

    public function getInvoicePayments(Invoice $invoice)
    {
        return $this->paymentRepository->getInvoicePayments($invoice);
    }

    public function createAdditionalPayment(Invoice $invoice, float $percentage, string $payment_type = 'stripe', string $status = 'pending')
    {
        // Validate payment_method matches ENUM values
        $allowedPaymentMethods = ['stripe', 'bank', 'cash', 'cheque'];
        if (!in_array($payment_type, $allowedPaymentMethods)) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'status' => 'error',
                    'message' => "Invalid payment method: '{$payment_type}'. Allowed values are: " . implode(', ', $allowedPaymentMethods)
                ], 400)
            );
        }

        // Validate invoice status
        $this->validateInvoiceForPayment($invoice);

        // Only check for pending payments if we're creating a new pending payment
        if ($status === 'pending') {
            $existingPendingPayment = $this->checkPendingPayment($invoice);
            if ($existingPendingPayment) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Cannot create additional payment: There is already a pending payment (ID: {$existingPendingPayment->id}) for invoice #{$invoice->id}. Please complete or cancel the existing payment before creating a new one.",
                ]);
            }
        }

        // Convert percentage to amount
        $amountToPay = round(($invoice->total_amount * $percentage) / 100, 2);

        // Validate amount is positive
        if ($amountToPay <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => "Cannot create payment: The payment amount must be greater than zero.",
            ]);
        }

        // Validate percentage
        if ($percentage <= 0 || $percentage > 100) {
            return response()->json([
                'status' => 'error',
                'message' => "Cannot create payment: Percentage must be between 0 and 100.",
            ]);
        }

        // Get current balance due from invoice
        $currentBalanceDue = $invoice->balance_due;
        
        // Check remaining balance (only for pending/unpaid)
        if ($status !== 'paid' && $amountToPay > $currentBalanceDue) {
            throw new \Exception("Cannot create payment: The requested payment amount ($" . number_format($amountToPay, 2) . ") exceeds the remaining balance due ($" . number_format($currentBalanceDue, 2) . ") for invoice #{$invoice->id}.");
        }

        // Create the new payment row
        $paymentData = [
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'amount' => $amountToPay,
            'total' => $amountToPay,
            'currency' => 'usd',
            'status' => $status,
            'payment_method' => $payment_type,
            'payment_url' => null,
            'percentage' => $percentage,
        ];

        $response = [
            'payment_method' => $payment_type,
            'amount' => $amountToPay,
            'total_amount' => $invoice->total_amount,
            'percentage' => $percentage,
            'status' => $status,
        ];

        if ($payment_type === 'stripe') {
            // Stripe payment
Stripe::setApiKey(config('services.stripe.secret'));

            $session = Session::create([
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => intval($amountToPay * 100),
                        'product_data' => [
                            'name' => "Additional Payment for Invoice #{$invoice->id}",
                            'description' => "Payment of {$percentage}% for invoice #{$invoice->id}"
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'success_url' => config('app.frontend_url') . '/client/projects',
                'cancel_url' => config('app.frontend_url') . '/client/invoices',
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'type' => 'additional_payment',
                    'percentage' => $percentage,
                ],
            ]);

            $paymentData['stripe_session_id'] = $session->id;
            $paymentData['stripe_payment_intent_id'] = $session->payment_intent ?? null;
            $paymentData['payment_url'] = $session->url;
            $response['payment_url'] = $session->url;
        } else {
            // Bank transfer or other payment methods
            $response['bank_info'] = [
                'account_holder' => 'LAHZA HM',
                'bank_name' => 'ATTIJARI WAFABANK',
                'rib' => '007 640 0014332000000260 29',
                'swift_code' => 'BCMAMAMC',
                'ICE' => '002 056 959 000 039',
                'reference' => 'PAY-' . $invoice->id . '-' . $percentage . '%'
            ];
        }

        // Create payment record
        $payment = $this->paymentRepository->create($paymentData);
        
        // If status is 'paid', update the invoice immediately
        if ($status === 'paid') {
            $this->updateInvoiceStatus($invoice);
        }
        
        // Set response values
        $response['payment_url'] = $response['payment_url'] ?? null;
        $response['bank_info'] = $response['bank_info'] ?? null;

        return $response;
    }
   public function autoGenerateRemainingPayment(Invoice $invoice, Payment $paidPayment): void
{
    // Refresh invoice state AFTER the payment status was updated
    // But BEFORE calling this method, ensure updateInvoiceStatus was called first
    $invoice->refresh();

    // If invoice is fully paid → nothing to do
    if ($invoice->balance_due <= 0) {
        return;
    }

    // Prevent duplicates (VERY IMPORTANT)
    $existingPending = $invoice->payments()
        ->where('status', 'pending')
        ->first();

    if ($existingPending) {
        return;
    }

    // Determine what kind of invoice we have
    $hasSubscriptions = $invoice->hasSubscriptions();
    $hasServices = $invoice->hasServices();

    if ($hasServices && $hasSubscriptions) {
        /**
         * MIXED INVOICE (services + subscriptions)
         *
         * By the time we are here:
         *  - Subscriptions are typically covered 100% in the first payment.
         *  - We want the auto-generated remaining payment to cover ONLY the
         *    remaining services part, and keep percentages clean.
         *
         * We therefore:
         *  - Look at how much of services has already been paid (via allocations)
         *  - Use the exact remaining services percentage as the new payment "percentage".
         */

        $servicesTotal = $invoice->getServicesTotal();

        if ($servicesTotal <= 0) {
            return;
        }

        // Sum of paid_percentage for services allocations on PAID payments
        $servicesPaidPercentage = DB::table('payment_allocations')
            ->whereNull('invoice_subscription_id')
            ->where('allocatable_type', 'invoice')
            ->whereIn('payment_id', function ($query) use ($invoice) {
                $query->select('id')
                    ->from('payments')
                    ->where('invoice_id', $invoice->id)
                    ->where('status', 'paid');
            })
            ->sum('paid_percentage');

        $remainingServicesPercentage = max(0, 100 - $servicesPaidPercentage);
        $remainingPercentage = round($remainingServicesPercentage, 2);
    } else {
        /**
         * SERVICES-ONLY or SUBSCRIPTIONS-ONLY
         *
         * For these simpler cases, using the invoice-level ratio is OK:
         *  remaining% = balance_due / total_amount.
         */
        $remainingPercentage = ($invoice->balance_due / $invoice->total_amount) * 100;
        $remainingPercentage = round($remainingPercentage, 2);
    }

    // Only create if there's actually something remaining
    if ($remainingPercentage <= 0) {
        return;
    }

    // Create remaining payment
    $this->createPaymentLink(
        $invoice,
        $remainingPercentage,
        'pending',
        $paidPayment->payment_method
    );
}
protected function handleSubscriptionAllocations($payment): void
{
    // Get all subscription allocations for this payment
    $subscriptionAllocations = $payment->allocations()
        ->where('allocatable_type', 'subscription')
        ->whereNotNull('invoice_subscription_id')
        ->with('invoiceSubscription.plan')
        ->get();

    if ($subscriptionAllocations->isEmpty()) {
        return; // No subscription allocations
    }

    $subscriptionInvoiceService = app(\App\Services\SubscriptionInvoiceService::class);
    $subscriptionService = app(\App\Services\SubscriptionService::class);

    foreach ($subscriptionAllocations as $allocation) {
        try {
            $invoiceSubscription = $allocation->invoiceSubscription;
            
            // Check if this specific subscription allocation is fully paid
            if (!$invoiceSubscription->isFullyPaid()) {
                Log::info('Subscription not fully paid yet', [
                    'invoice_subscription_id' => $invoiceSubscription->id,
                    'paid_amount' => $invoiceSubscription->getTotalPaid(),
                    'required_amount' => $invoiceSubscription->price_snapshot,
                ]);
                continue;
            }

            // If subscription already exists, reactivate it
            if ($invoiceSubscription->subscription_id) {
                $subscription = Subscription::find($invoiceSubscription->subscription_id);
                
                if ($subscription) {
                    // Reactivate if cancelled
                    if ($subscription->isCancelled() || $subscription->isExpired()) {
                        $subscriptionService->updateStatus($subscription, 'active');
                        
                        Log::info('Subscription reactivated from payment', [
                            'payment_id' => $payment->id,
                            'allocation_id' => $allocation->id,
                            'subscription_id' => $subscription->id,
                        ]);
                    } else {
                        Log::info('Subscription already active', [
                            'allocation_id' => $allocation->id,
                            'subscription_id' => $subscription->id,
                        ]);
                    }
                    continue;
                }
            }

            // Get custom field values if stored (you may need to adjust this)
            $customFieldValues = [];
            
            // Create NEW subscription only if it doesn't exist
            $subscription = $subscriptionInvoiceService->createSubscriptionFromPaidAllocation(
                $invoiceSubscription,
                $customFieldValues
            );

            Log::info('NEW subscription created from payment allocation', [
                'payment_id' => $payment->id,
                'allocation_id' => $allocation->id,
                'subscription_id' => $subscription->id,
                'plan_id' => $invoiceSubscription->plan_id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create/reactivate subscription from allocation', [
                'allocation_id' => $allocation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
/**
 * Handle manual payment (non-Stripe) - mark as paid
 */
public function handleManualPayment(Payment $payment): array
{
    // Validate payment method
    if ($payment->payment_method === 'stripe') {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'error' => true,
                'message' => 'Stripe payments cannot be manually processed',
            ], 400)
        );
    }

    // Validate payment status
    if ($payment->status !== 'pending') {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'error' => true,
                'message' => 'Only pending payments can be marked as paid',
            ], 400)
        );
    }

    
    return DB::transaction(function () use ($payment) {
        // Mark payment as paid
        $payment->update(['status' => 'paid']);
        // Mark allocations as paid with their percentages
        $this->markAllocationsAsPaid($payment);

        $invoice = $payment->invoice;
        
        // Update invoice status
        $this->updateInvoiceStatus($invoice);
        
        // Handle subscription allocations - NOW reactivates if exists
        $this->handleSubscriptionAllocations($payment);
        
        // Auto-generate remaining payment if needed
        $this->autoGenerateRemainingPayment($invoice, $payment);

        // Refresh invoice
        $invoice->refresh();

        // Update projects
        $paymentData = $invoice->payments()->latest()->first()?->toArray() ?? [];
        $this->projectCreationService->updateProjectAfterPayment($invoice, $paymentData);
        
        // Send payment success email
        $this->sendPaymentSuccessEmail($payment);

        return [
            'success' => true,
            'message' => 'Payment processed successfully',
            'payment' => $payment->fresh(),
        ];
    });
}

/**
 * Cancel a paid manual payment - revert to pending
 */
public function cancelManualPayment(Payment $payment): array
{
    // Validate payment method
    if ($payment->payment_method === 'stripe') {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'error' => true,
                'message' => 'Stripe payments cannot be manually cancelled',
            ], 400)
        );
    }

    // Validate payment status
    if ($payment->status !== 'paid') {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'error' => true,
                'message' => 'Only paid payments can be cancelled',
            ], 400)
        );
    }

    return DB::transaction(function () use ($payment) {
        $invoice = $payment->invoice;

        // Delete any pending payment that was auto-generated after this payment
        $invoice->payments()
            ->where('status', 'pending')
            ->where('created_at', '>=', $payment->updated_at)
            ->delete();

        // Mark the payment as pending
        $payment->update(['status' => 'pending']);
        // Reset allocation paid percentages to 0
        $this->resetAllocationsPaidPercentage($payment);
        // Update invoice status
        $this->updateInvoiceStatus($invoice);

        // Cancel project update
        $this->projectCreationService->cancelProjectUpdate($invoice);

        // Handle subscription cancellation if needed
        $this->cancelSubscriptionAllocations($payment);

        return [
            'success' => true,
            'message' => 'Payment cancelled successfully',
            'payment' => $payment->fresh(),
        ];
    });
}

/**
 * Cancel subscriptions created from this payment's allocations
 */
/**
 * Cancel subscriptions created from this payment's allocations
 */
protected function cancelSubscriptionAllocations(Payment $payment): void
{
    // Get subscription allocations
    $subscriptionAllocations = $payment->allocations()
        ->where('allocatable_type', 'subscription')
        ->whereNotNull('invoice_subscription_id')
        ->with('invoiceSubscription.subscription')
        ->get();

    if ($subscriptionAllocations->isEmpty()) {
        return;
    }

    foreach ($subscriptionAllocations as $allocation) {
        $invoiceSubscription = $allocation->invoiceSubscription;
        
        // If subscription was created, cancel it but keep the link
        if ($invoiceSubscription->subscription_id) {
            $subscription = $invoiceSubscription->subscription;
            
            if ($subscription && !$subscription->isCancelled()) {
                // Cancel subscription immediately
                $subscriptionService = app(\App\Services\SubscriptionService::class);
                $subscriptionService->cancelSubscription($subscription, immediate: true);
                
                // DO NOT unlink - keep subscription_id so we can reactivate it
                // $invoiceSubscription->update(['subscription_id' => null]); // REMOVED
                
                Log::info('Subscription cancelled due to payment cancellation', [
                    'payment_id' => $payment->id,
                    'subscription_id' => $subscription->id,
                    'invoice_subscription_id' => $invoiceSubscription->id,
                ]);
            }
        }
    }
}
    /**
     * Recalculate payment allocations proportionally based on new payment amount.
     *
     * NOTE: This is still used by the old global percentage flow.
     * For the new behaviour (editing a single allocation), see updatePaymentAllocation().
     */
    protected function recalculatePaymentAllocations(Payment $payment): void
    {
        $allocations = $payment->allocations;
        
        if ($allocations->isEmpty()) {
            return; // No allocations to recalculate
        }

        $invoice = $payment->invoice;
        $servicesTotal = $invoice->invoiceServices->sum('individual_total');
        
        // Get the services percentage from payment
        $servicesPercentage = $payment->percentage;

        foreach ($allocations as $allocation) {
            if ($allocation->allocatable_type === 'subscription') {
                // For subscriptions, keep the allocation amount as-is.
                // Subscription allocations are now driven by remaining amount logic
                // in SubscriptionInvoiceService::createPaymentAllocations and
                // updatePaymentAllocation, so we do NOT override them here.
                continue;
            } else {
                // Invoice/services allocation: uses the payment percentage
                $newAmount = ($servicesTotal * $servicesPercentage) / 100;
                $allocation->update(['amount' => round($newAmount, 2)]);
            }
        }

        Log::info('Payment allocations recalculated', [
            'payment_id' => $payment->id,
            'payment_percentage' => $servicesPercentage,
            'allocations_updated' => $allocations->count(),
        ]);
    }

    /**
     * Update a single payment allocation (services or subscription) and
     * recalculate the parent payment amount + Stripe session.
     *
     * Example:
     *  - 2 subscription allocations both at 100% → payment = 100% + 100%
     *  - Change one allocation to 50% → payment becomes 50% + 100%.
     */
    public function updatePaymentAllocation(PaymentAllocation $allocation, float $percentage): array
    {
        // 1. Basic validations
        if ($percentage <= 0 || $percentage > 100) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'status' => 'error',
                    'message' => 'Percentage must be between 0 and 100.',
                ], 400)
            );
        }

        $payment = $allocation->payment;

        if (!$payment) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'status' => 'error',
                    'message' => 'Payment not found for this allocation.',
                ], 400)
            );
        }

        if ($payment->status !== 'pending') {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'status' => 'error',
                    'message' => "Only pending payments can be updated. Payment ID {$payment->id} has status '{$payment->status}'.",
                ], 400)
            );
        }

        $invoice = $payment->invoice;
        if (!$invoice) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'status' => 'error',
                    'message' => "Invoice not found for payment ID {$payment->id}.",
                ], 400)
            );
        }

        // Ensure invoice itself is still payable
        $this->validateInvoiceForPayment($invoice);

        // 2. Compute new allocation amount based on type
        if ($allocation->isSubscriptionAllocation()) {
            $invoiceSubscription = $allocation->invoiceSubscription;
            if (!$invoiceSubscription) {
                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                    response()->json([
                        'status' => 'error',
                        'message' => 'Invoice subscription not found for this allocation.',
                    ], 400)
                );
            }

            // Amount already paid for this subscription across PAID payments
            $alreadyPaidAmount = $invoiceSubscription->getTotalPaid();

            // Maximum remaining amount we can allocate for this subscription
            $maxRemainingAmount = max(0, $invoiceSubscription->price_snapshot - $alreadyPaidAmount);

            // Requested amount based on percentage
            $requestedAmount = ($invoiceSubscription->price_snapshot * $percentage) / 100;

            if ($requestedAmount - 0.0001 > $maxRemainingAmount) {
                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                    response()->json([
                        'status' => 'error',
                        'message' => "Cannot update allocation: requested {$percentage}% exceeds remaining amount for this subscription.",
                    ], 400)
                );
            }

            $newAllocationAmount = $requestedAmount;
        } else {
            // Services (invoice) allocation
            $servicesTotal = $invoice->invoiceServices->sum('individual_total');

            if ($servicesTotal <= 0) {
                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                    response()->json([
                        'status' => 'error',
                        'message' => 'Cannot update services allocation: invoice has no services total.',
                    ], 400)
                );
            }

            // Already‑paid percentage for services from PAID payments only
            $alreadyPaidPercentage = DB::table('payment_allocations')
                ->whereNull('invoice_subscription_id')
                ->where('allocatable_type', 'invoice')
                ->whereIn('payment_id', function ($query) use ($invoice) {
                    $query->select('id')
                        ->from('payments')
                        ->where('invoice_id', $invoice->id)
                        ->where('status', 'paid');
                })
                ->sum('paid_percentage');

            if ($alreadyPaidPercentage + $percentage > 100.0001) {
                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                    response()->json([
                        'status' => 'error',
                        'message' => "Cannot update allocation: requested {$percentage}% would exceed 100% for services (already paid {$alreadyPaidPercentage}%).",
                    ], 400)
                );
            }

            $baseAmount = $servicesTotal;
            $newAllocationAmount = ($baseAmount * $percentage) / 100;
        }

        $newAllocationAmount = round($newAllocationAmount, 2);

        if ($newAllocationAmount <= 0) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'status' => 'error',
                    'message' => 'Allocation amount must be greater than zero.',
                ], 400)
            );
        }

        // 3. Save new allocation amount
        $allocation->amount = $newAllocationAmount;
        // We do NOT touch paid_percentage here; it is updated only when payment is actually marked as paid
        $allocation->save();

        // Re‑sum all allocations for this payment as the new payment amount
        $newPaymentAmount = $payment->allocations()->sum('amount');

        // 4. Validate against invoice total and already‑paid payments
        $totalPaidOtherPayments = $invoice->payments()
            ->where('status', 'paid')
            ->where('id', '<>', $payment->id)
            ->sum('amount');

        if ($totalPaidOtherPayments + $newPaymentAmount - 0.0001 > $invoice->total_amount) {
            // Roll back allocation change by refreshing model from DB
            $allocation->refresh();

            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'status' => 'error',
                    'message' => 'Cannot update allocation: resulting payment would exceed the invoice total.',
                ], 400)
            );
        }

        // 5. Update payment record + Stripe session if needed
        $payment->amount = $newPaymentAmount;
        $payment->total = $newPaymentAmount;

        // Optionally keep payment->percentage in sync for services allocation
        if ($allocation->isInvoiceAllocation()) {
            $servicesTotal = $invoice->invoiceServices->sum('individual_total');
            $payment->percentage = $servicesTotal > 0
                ? round(($allocation->amount / $servicesTotal) * 100, 2)
                : $payment->percentage;
        }

        $paymentUrl = null;

        if ($payment->payment_method === 'stripe') {
            // Reset old session data
            $payment->stripe_session_id = null;
            $payment->stripe_payment_intent_id = null;

            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => (int)($newPaymentAmount * 100),
                        'product_data' => [
                            'name' => "Updated payment for invoice #{$payment->invoice_id}",
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'metadata' => [
                    'invoice_id' => $payment->invoice_id,
                    'client_id' => $payment->client_id,
                    'updated_payment' => true,
                ],
                'success_url' => config('app.frontend_url') . '/client/projects',
                'cancel_url' => config('app.frontend_url') . '/client/invoices',
            ]);

            $payment->stripe_session_id = $session->id;
            $payment->stripe_payment_intent_id = $session->payment_intent;
            $payment->payment_url = $session->url;
            $paymentUrl = $session->url;
        } else {
            // Non‑Stripe methods do not have a checkout URL
            $payment->payment_url = null;
        }

        $payment->save();

        Log::info('Payment allocation updated', [
            'payment_id' => $payment->id,
            'allocation_id' => $allocation->id,
            'new_allocation_amount' => $allocation->amount,
            'new_payment_amount' => $payment->amount,
        ]);

        return [
            'status' => 'updated',
            'payment_id' => $payment->id,
            'allocation_id' => $allocation->id,
            'allocation_amount' => $allocation->amount,
            'payment_amount' => $payment->amount,
            'payment_url' => $paymentUrl,
        ];
    }
}