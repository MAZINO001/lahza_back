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
        
        // Calculate amount based on percentage
        $amount = round(($invoice->total_amount * $payment_percentage) / 100, 2);
        
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

        $paymentData = [
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
            'total' => $invoice->total_amount,
            'amount' => $amount,
            'currency' => 'eur',
            'payment_method' => $payment_type,
            'status' => $payment_status,
            'payment_url' => null,
            'percentage' => $payment_percentage,
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

    // 3. Calculate and Validate Amount
    // Assuming $payment->total refers to the invoice total or the base amount for percentage
    $newAmount = ($payment->total * $percentage) / 100;
    
    if ($newAmount <= 0) {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'status' => 'error',
                'message' => "Cannot update payment: The calculated amount ($" . number_format($newAmount, 2) . ") must be greater than zero."
            ], 400)
        );
    }

    $currentBalanceDue = $invoice->balance_due;
    if ($newAmount > $currentBalanceDue) {
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
    $payment->amount = $newAmount;
    $payment->percentage = $percentage;
    $payment->save();

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

            $invoice = $payment->invoice;
            $totalAmount = $invoice->total_amount;
            $this->updateInvoiceStatus($invoice);
            $this->autoGenerateRemainingPayment($invoice, $payment);

            // Update invoice status
            // Refresh invoice to get updated values
            $invoice->refresh();


            // Calculate total paid for logging and checking 50% threshold
            $totalPaid = $invoice->payments()->where('status', 'paid')->sum('amount');
            $balanceDue = $invoice->balance_due;

            // Generate a new payment link if exactly 50% was paid and there's still balance due
           
            $paymentData = $invoice->payments()->latest()->first()?->toArray() ?? [];
            $this->projectCreationService->updateProjectAfterPayment($invoice ,$paymentData);

            // Send payment success email
            $this->sendPaymentSuccessEmail($payment);
        }

        return ['status' => 'ok'];
    }

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

    // Calculate remaining percentage based on ACTUAL balance, not just the paid payment percentage
    $remainingPercentage = ($invoice->balance_due / $invoice->total_amount) * 100;
    
    // Round to 2 decimal places to avoid floating point issues
    $remainingPercentage = round($remainingPercentage, 2);

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
}