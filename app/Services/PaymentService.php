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

    public function createPaymentLink(Invoice $invoice): array
    {
        $client = $invoice->client;
        $country = trim(strtolower(preg_replace('/[^a-z]/i', '', $client->country)));
        $isMoroccanClient = in_array($country, ['morocco', 'maroc', 'ma', 'mar']);
        $halfAmount = $invoice->total_amount / 2;
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
                'bank_name' => 'YOUR_BANK_NAME',
                'account_holder' => 'YOUR_COMPANY_NAME',
                'rib' => 'YOUR_RIB_NUMBER',
                'swift_code' => 'YOUR_SWIFT_CODE',
                'iban' => 'YOUR_IBAN',
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
            throw new \Exception("Only pending payments can be updated.");
        }

        // 2. Calculate new amount based on percentage of total
        $newAmount = ($payment->total * $percentage) / 100;
        

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
    $secret = config('services.stripe.webhook_secret');

    try {
        $event = Webhook::constructEvent($payload, $sigHeader, $secret);
    } catch (\Exception $e) {
        Log::error('Invalid Stripe webhook: '.$e->getMessage());
        return ['status' => 'invalid'];
    }

    if ($event->type === 'checkout.session.completed') {
        $session = $event->data->object;

        $payment = $this->paymentRepository->findByStripeSessionId($session->id);
        if (!$payment) {
            Log::warning('Payment not found for Stripe session: '.$session->id);
            return ['status' => 'ok'];
        }

        // 1️⃣ Mark this payment as paid
        $this->paymentRepository->update($payment, [
            'status' => 'paid',
            'stripe_payment_intent_id' => $session->payment_intent,
            'payment_url' => null
        ]);

        $invoice = $payment->invoice;
        $totalAmount = $invoice->total_amount;

        // 2️⃣ Calculate total paid so far
        $totalPaid = $invoice->payments()->where('status', 'paid')->sum('amount');
        $balanceDue = $totalAmount - $totalPaid;

        // 3️⃣ Update invoice
        $invoiceStatus = $balanceDue <= 0 ? 'paid' : 'partially_paid';
        $invoice->update([
            'status' => $invoiceStatus,
            'balance_due' => $balanceDue
        ]);

        // 4️⃣ Generate a new payment link if exactly 50% was paid
        if (abs($totalPaid - $totalAmount * 0.5) < 0.01) { // tolerance for floats
            $newPaymentLink = $this->createPaymentLink($invoice);
            Log::info("50% of invoice #{$invoice->id} paid. New payment link created.", $newPaymentLink);
        } else {
            Log::info("Payment received for invoice #{$invoice->id}. Total paid: {$totalPaid}, balance due: {$balanceDue}");
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
public function getAllPayments(Invoice $invoice)
{
    return $this->paymentRepository->getAllPayments($invoice);
}
}
