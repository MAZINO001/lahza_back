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
            Stripe::setApiKey(env('STRIPE_SECRET'));

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
                'success_url' => env('FRONTEND_URL') . '/client/projects/',
                'cancel_url' => env('FRONTEND_URL') . '/client/invoices',
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
        }
        
        $response['payment_url'] = $response['payment_url'] ?? null;
        $response['bank_info'] = $response['bank_info'] ?? null;

        return $response;
    }

    public function updatePendingPayment(Payment $payment, float $percentage)
    {
        // Ensure payment is editable
        if ($payment->status !== 'pending') {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'status' => 'error',
                    'message' => "Cannot update payment: Only pending payments can be updated. Payment ID {$payment->id} has status '{$payment->status}'."
                ], 400)
            );
        }

        // Load invoice relationship
        $invoice = $payment->invoice;
        if (!$invoice) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'status' => 'error',
                    'message' => "Cannot update payment: Invoice not found for payment ID {$payment->id}."
                ], 400)
            );
        }

        // Validate invoice status
        $this->validateInvoiceForPayment($invoice);

        // Get current balance due from invoice (excluding this pending payment)
        $currentBalanceDue = $invoice->balance_due;
        
        // Calculate new amount based on percentage of total
        $newAmount = ($payment->total * $percentage) / 100;
        
        // Validate new amount is positive
        if ($newAmount <= 0) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'status' => 'error',
                    'message' => "Cannot update payment: The calculated payment amount ($" . number_format($newAmount, 2) . ") must be greater than zero."
                ], 400)
            );
        }

        // Get current balance due (excluding this pending payment)
        $currentBalanceDue = $invoice->balance_due;
        
        // Validate that new amount doesn't exceed balance due
        if ($newAmount > $currentBalanceDue) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'status' => 'error',
                    'message' => "Cannot update payment: The requested payment amount ($" . number_format($newAmount, 2) . ") exceeds the remaining balance due ($" . number_format($currentBalanceDue, 2) . ") for invoice #{$invoice->id}."
                ], 400)
            );
        }

        $paymentUrl = null;

        if ($payment->payment_method === 'stripe') {
            // Delete old Stripe session data
            $payment->stripe_session_id = null;
            $payment->stripe_payment_intent_id = null;

            // Create new Stripe session
            Stripe::setApiKey(env('STRIPE_SECRET'));

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => $newAmount * 100,
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
                'success_url' => env('FRONTEND_URL') . '/client/projects',
                'cancel_url' => env('FRONTEND_URL') .'/client/invoices',
            ]);

            $payment->stripe_session_id = $session->id;
            $payment->stripe_payment_intent_id = $session->payment_intent;
            $payment->payment_url = $session->url;
            $paymentUrl = $session->url;
        }
        // For non-stripe payments, paymentUrl remains null

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

            // Update invoice status
            $this->updateInvoiceStatus($invoice);
            
            // Refresh invoice to get updated values
            $invoice->refresh();

            // // Create a project for the invoice
            // try {
            //     $this->projectCreationService->createProjectForInvoice($invoice);
            // } catch (\Exception $e) {
            //     Log::error('Failed to create project for invoice #'.$invoice->id.': '.$e->getMessage());
            // }

            // Calculate total paid for logging and checking 50% threshold
            $totalPaid = $invoice->payments()->where('status', 'paid')->sum('amount');
            $balanceDue = $invoice->balance_due;

            // Generate a new payment link if exactly 50% was paid and there's still balance due
            if (abs($totalPaid - $totalAmount * 0.5) < 0.01 && $balanceDue > 0) {
                try {
                    // Use the same payment method as the current payment
                    $newPaymentLink = $this->createPaymentLink($invoice, 50,  // payment percentage (50% for the remaining amount)
                            'pending',  // payment status
                            $payment->payment_method  // payment type (e.g., 'stripe')
                            );
                            
                    Log::info("50% of invoice #{$invoice->id} paid. New payment link created.", $newPaymentLink);
                } catch (\Exception $e) {
                    Log::warning("Failed to create new payment link after 50% payment for invoice #{$invoice->id}: " . $e->getMessage());
                }
            } else {
                Log::info("Payment received for invoice #{$invoice->id}. Total paid: {$totalPaid}, balance due: {$balanceDue}, status: {$invoice->status}");
            }
            $paymentData = $invoice->payments()->latest()->first()?->toArray() ?? [];
            $this->projectCreationService->updateProjectAfterPayment($invoice ,$paymentData);
        }

        return ['status' => 'ok'];
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
            Stripe::setApiKey(env('STRIPE_SECRET'));

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
                'success_url' => env('FRONTEND_URL') . '/client/projects',
                'cancel_url' => env('FRONTEND_URL') .'/client/invoices',
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
}