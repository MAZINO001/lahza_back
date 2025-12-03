<?php

namespace App\Services;

use App\Models\Invoice;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Webhook;
use App\Repositories\PaymentRepository;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;
class PaymentService implements PaymentServiceInterface
{
    protected $paymentRepository;

    public function __construct(PaymentRepository $paymentRepository)
    {
        $this->paymentRepository = $paymentRepository;
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
            throw new \Exception("Cannot create payment: Invoice #{$invoice->id} is already fully paid. Balance due: $" . number_format($invoice->balance_due, 2));
        }

        // Check if invoice status allows payments
        // 'sent', 'unpaid', 'partially_paid', and 'overdue' are valid statuses for receiving payments
        // 'draft' invoices should not receive payments (not finalized)
        // 'paid' invoices are already handled above
        if (!in_array($invoice->status, ['sent', 'unpaid', 'partially_paid', 'overdue'])) {
            throw new \Exception("Cannot create payment: Invoice #{$invoice->id} has status '{$invoice->status}' which does not allow payments. Only 'sent', 'unpaid', 'partially_paid', or 'overdue' invoices can receive payments.");
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
     * Calculate and get the current balance due for an invoice
     */
    protected function getCurrentBalanceDue(Invoice $invoice): float
    {
        $totalPaid = $invoice->payments()
            ->where('status', 'paid')
            ->sum('amount');
        
        return max(0, $invoice->total_amount - $totalPaid);
    }

    /**
     * Update invoice status based on balance due
     */
    protected function updateInvoiceStatus(Invoice $invoice): void
    {
        $balanceDue = $this->getCurrentBalanceDue($invoice);
        
        $invoice->update([
            'balance_due' => $balanceDue,
            'status' => $balanceDue <= 0 ? 'paid' : ($balanceDue < $invoice->total_amount ? 'partially_paid' : 'unpaid')
        ]);
    }

    public function createPaymentLink(Invoice $invoice): array
    {
        // Validate invoice status
        $this->validateInvoiceForPayment($invoice);

        // Check for existing pending payment
        $existingPendingPayment = $this->checkPendingPayment($invoice);
        if ($existingPendingPayment) {
            throw new \Exception("Cannot create payment: There is already a pending payment (ID: {$existingPendingPayment->id}) for invoice #{$invoice->id}. Please complete or cancel the existing payment before creating a new one.");
        }

        // Get current balance due
        $currentBalanceDue = $this->getCurrentBalanceDue($invoice);
        
        $client = $invoice->client;
        $country = trim(strtolower(preg_replace('/[^a-z]/i', '', $client->country)));
        $isMoroccanClient = in_array($country, ['morocco', 'maroc', 'ma', 'mar']);
        $halfAmount = $invoice->total_amount / 2;
        
        // Validate that half amount doesn't exceed balance due
        if ($halfAmount > $currentBalanceDue) {
            throw new \Exception("Cannot create payment: The requested payment amount ($" . number_format($halfAmount, 2) . ") exceeds the remaining balance due ($" . number_format($currentBalanceDue, 2) . ") for invoice #{$invoice->id}.");
        }

        $paymentMethod = $isMoroccanClient ? 'banc' : 'stripe';

        $paymentData = [
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
            'total' => $invoice->total_amount,
            'amount' => $halfAmount,
            'currency' => 'usd',
            'payment_method' => $paymentMethod,
            'status' => 'pending',
            'payment_url' => null,
            'percentage' => 50,
        ];

        $response = [
            'payment_method' => $paymentMethod,
            'amount' => $halfAmount,
            'total_amount' => $invoice->total_amount,
            'is_advance_payment' => true
        ];

        if (!$isMoroccanClient) {
            Stripe::setApiKey(env('STRIPE_SECRET'));

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => $halfAmount * 100,
                        'product_data' => [
                            'name' => "Advance Payment for invoice #{$invoice->id}",
                            'description' => '50% advance payment (remaining 50% due upon completion)'
                        ]
                    ],
                    'quantity' => 1
                ]],
                'mode' => 'payment',
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'client_id' => $client->id,
                    'total_amount' => $invoice->total_amount,
                    'advance_amount' => $halfAmount, 
                    'is_advance_payment' => true
                ],
                'success_url' => env('FRONTEND_URL') . '/client/projects?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => env('FRONTEND_URL') . '/payment-cancel',
            ]);

            $paymentData['stripe_session_id'] = $session->id;
            $paymentData['stripe_payment_intent_id'] = $session->payment_intent;
            $response['payment_url'] = $session->url;
            $paymentData['payment_url'] =$response['payment_url'];
        } else {
            $response['bank_info'] = [
                'account_holder' => 'LAHZA HM',
                'bank_name' => 'ATTIJARI WAFABANK',
                'rib' => '007640001433200000026029',
                'swift_code' => 'BCMAMAMC',
                'ICE' => '002 056 959 000 039',
                'reference' => 'PAY-' . $invoice->id
            ];
        }

        $this->paymentRepository->create($paymentData);
            $response['payment_url'] = $response['payment_url'] ?? null;
            $response['bank_info'] = $response['bank_info'] ?? null;

        return $response;
    }


    public function updatePendingPayment(Payment $payment, float $percentage)
    {
        // 1. Ensure payment is editable
        if ($payment->status !== 'pending') {
            throw new \Exception("Cannot update payment: Only pending payments can be updated. Payment ID {$payment->id} has status '{$payment->status}'.");
        }

        // Load invoice relationship
        $invoice = $payment->invoice;
        if (!$invoice) {
            throw new \Exception("Cannot update payment: Invoice not found for payment ID {$payment->id}.");
        }

        // Validate invoice status
        $this->validateInvoiceForPayment($invoice);

        // 2. Calculate new amount based on percentage of total
        $newAmount = ($payment->total * $percentage) / 100;
        
        // Validate new amount is positive
        if ($newAmount <= 0) {
            throw new \Exception("Cannot update payment: The calculated payment amount ($" . number_format($newAmount, 2) . ") must be greater than zero.");
        }

        // Get current balance due (excluding this pending payment)
        $currentBalanceDue = $this->getCurrentBalanceDue($invoice);
        
        // Validate that new amount doesn't exceed balance due
        if ($newAmount > $currentBalanceDue) {
            throw new \Exception("Cannot update payment: The requested payment amount ($" . number_format($newAmount, 2) . ") exceeds the remaining balance due ($" . number_format($currentBalanceDue, 2) . ") for invoice #{$invoice->id}.");
        }
        

        // 3. Delete old Stripe session if exist
        if ($payment->payment_method === 'stripe' && $payment->stripe_session_id) {
            $payment->stripe_session_id = null;
            $payment->stripe_payment_intent_id = null;
        }

        // 4. Create new Stripe session if stripe payment
        if ($payment->payment_method === 'stripe') {
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
                'success_url' => env('FRONTEND_URL') . '/client/projects?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => env('FRONTEND_URL') . '/payment-cancel',
            ]);

            $payment->stripe_session_id = $session->id;
            $payment->stripe_payment_intent_id = $session->payment_intent;
            $paymentUrl = $session->url;
            $payment->payment_url = $session->url;
        } else {
            // Bank transfer
            $paymentUrl = null;
        }

        $payment->amount = $newAmount;
        $payment->percentage= $percentage;
        $payment->save();

        // 5. Return response
        return [
            'status' => 'updated',
            'payment_method' => $payment->payment_method,
            'new_amount' => $payment->amount,
            'payment_url' => $paymentUrl,
        ];
    }



    public function handleStripeWebhook(string $payload, string $sigHeader)
{
    logger('Stripe webhook received');
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

        // 1️⃣ Mark this payment as paid
        $this->paymentRepository->update($payment, [
            'status' => 'paid',
            'stripe_payment_intent_id' => $session->payment_intent,
            'payment_url' => null
        ]);

        $invoice = $payment->invoice;
        $totalAmount = $invoice->total_amount;

        // 2️⃣ Update invoice status (this will calculate balance and set status to 'paid' if fully paid)
        $this->updateInvoiceStatus($invoice);
        
        // Refresh invoice to get updated values
        $invoice->refresh();

        // Calculate total paid for logging and checking 50% threshold
        $totalPaid = $invoice->payments()->where('status', 'paid')->sum('amount');
        $balanceDue = $invoice->balance_due;

        // 4️⃣ Generate a new payment link if exactly 50% was paid and there's still balance due
        if (abs($totalPaid - $totalAmount * 0.5) < 0.01 && $balanceDue > 0) { // tolerance for floats
            try {
                $newPaymentLink = $this->createPaymentLink($invoice);
                Log::info("50% of invoice #{$invoice->id} paid. New payment link created.", $newPaymentLink);
            } catch (\Exception $e) {
                // Log the error but don't fail the webhook (payment was already processed)
                Log::warning("Failed to create new payment link after 50% payment for invoice #{$invoice->id}: " . $e->getMessage());
            }
        } else {
            Log::info("Payment received for invoice #{$invoice->id}. Total paid: {$totalPaid}, balance due: {$balanceDue}, status: {$invoice->status}");
        }
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
public function createAdditionalPayment(Invoice $invoice, float $percentage)
{
    // Validate invoice status
    $this->validateInvoiceForPayment($invoice);

    // Check for existing pending payment
    $existingPendingPayment = $this->checkPendingPayment($invoice);
    if ($existingPendingPayment) {
        throw new \Exception("Cannot create additional payment: There is already a pending payment (ID: {$existingPendingPayment->id}) for invoice #{$invoice->id}. Please complete or cancel the existing payment before creating a new one.");
    }

    // 1) Convert percentage -> amount
    $amountToPay = round(($invoice->total_amount * $percentage) / 100, 2);

    // Validate amount is positive
    if ($amountToPay <= 0) {
        throw new \Exception("Cannot create payment: The payment amount must be greater than zero.");
    }

    // 2) Get current balance due
    $currentBalanceDue = $this->getCurrentBalanceDue($invoice);
    
    // Check remaining balance
    if ($amountToPay > $currentBalanceDue) {
        throw new \Exception("Cannot create payment: The requested payment amount ($" . number_format($amountToPay, 2) . ") exceeds the remaining balance due ($" . number_format($currentBalanceDue, 2) . ") for invoice #{$invoice->id}.");
    }

    // 3) Determine payment method based on client country (same logic as createPaymentLink)
    $client = $invoice->client;
    $country = trim(strtolower(preg_replace('/[^a-z]/i', '', $client->country)));
    $isMoroccanClient = in_array($country, ['morocco', 'maroc', 'ma', 'mar']);
    $paymentMethod = $isMoroccanClient ? 'banc' : 'stripe';

    // 4) Create the new payment row (pending)
    $paymentData = [
        'invoice_id' => $invoice->id,
        'client_id' => $invoice->client_id,
        'amount' => $amountToPay,
        'total' => $amountToPay,
        'currency' => 'usd',
        'status' => 'pending',
        'payment_method' => $paymentMethod,
        'payment_url' => null,
    ];

    $response = [
        'payment_method' => $paymentMethod,
        'amount' => $amountToPay,
        'total_amount' => $invoice->total_amount,
        'percentage' => $percentage,
    ];

    // 5) Generate payment link based on payment method
    if (!$isMoroccanClient) {
        // Stripe payment for non-Moroccan clients
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
            'success_url' => env('FRONTEND_URL') . '/client/projects?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => env('FRONTEND_URL') . '/payment-cancel',
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
        // Bank transfer for Moroccan clients
        $response['bank_info'] = [
        'account_holder' => 'LAHZA HM',
                'bank_name' => 'ATTIJARI WAFABANK',
                'rib' => '007640001433200000026029',
                'swift_code' => 'BCMAMAMC',
                'ICE' => '002 056 959 000 039',
                'reference' => 'PAY-' . $invoice->id . '-' . $percentage . '%'
        ];
    }

    // 6) Create payment record
    $payment = $this->paymentRepository->create($paymentData);
    
    // 7) Set response values
    $response['payment_url'] = $response['payment_url'] ?? null;
    $response['bank_info'] = $response['bank_info'] ?? null;

    return $response;
}

}
