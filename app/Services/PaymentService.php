<?php

namespace App\Services;

use App\Models\Quotes;
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

    public function createPaymentLink(Quotes $quote): array
    {
        $client = $quote->client;
        $country = trim(strtolower(preg_replace('/[^a-z]/i', '', $client->country)));
        $isMoroccanClient = in_array($country, ['morocco', 'maroc', 'ma', 'mar']);        $halfAmount = $quote->total_amount / 2;
        $paymentMethod = $isMoroccanClient ? 'banc' : 'stripe';

        $paymentData = [
            'quote_id' => $quote->id,
            'client_id' => $client->id,
            'total' => $quote->total_amount,
            'amount' => $halfAmount,
            'currency' => 'usd',
            'payment_method' => $paymentMethod,
            'status' => 'pending' 
        ];

        $response = [
            'payment_method' => $paymentMethod,
            'amount' => $halfAmount,
            'total_amount' => $quote->total_amount,
            'is_advance_payment' => true
        ];

        if (!$isMoroccanClient) {
            Stripe::setApiKey(env('STRIPE_SECRET'));

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => $quote->total_amount * 100,
                        'product_data' => [
                            'name' => "Advance Payment for Quote #{$quote->id}",
                            'description' => '50% advance payment (remaining 50% due upon completion)'
                        ]
                    ],
                    'quantity' => 1
                ]],
                'mode' => 'payment',
                'metadata' => [
                    'quote_id' => $quote->id,
                    'client_id' => $client->id,
                    'total_amount' => $quote->total_amount,
                    'is_advance_payment' => true
                ],
                'success_url' => url('/payment-success?session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url' => url('/payment-cancel')
            ]);

            $paymentData['stripe_session_id'] = $session->id;
            $paymentData['stripe_payment_intent_id'] = $session->payment_intent;
            $response['payment_url'] = $session->url;
        } else {
            $response['bank_info'] = [
                'bank_name' => 'YOUR_BANK_NAME',
                'account_holder' => 'YOUR_COMPANY_NAME',
                'rib' => 'YOUR_RIB_NUMBER',
                'swift_code' => 'YOUR_SWIFT_CODE',
                'iban' => 'YOUR_IBAN',
                'reference' => 'PAY-' . $quote->id
            ];
        }

        $this->paymentRepository->create($paymentData);
            $response['payment_url'] = $response['payment_url'] ?? null;
            $response['bank_info'] = $response['bank_info'] ?? null;

        return $response;
    }


    public function updatePendingPayment(Payment $payment, float $percentage)
    {
        Log::info('Starting updatePendingPayment', [
            'payment_id' => $payment->id,
            'current_amount' => $payment->amount,
            'status' => $payment->status,
            'total' => $payment->total,
            'percentage' => $percentage,
        ]);

        // 1. Ensure payment is editable
        if ($payment->status !== 'pending') {
            throw new \Exception("Only pending payments can be updated.");
        }

        // 2. Calculate new amount based on percentage of total
        $newAmount = ($payment->total * $percentage) / 100;
        Log::info('Calculated new amount from percentage', [
            'payment_id' => $payment->id,
            'total' => $payment->total,
            'percentage' => $percentage,
            'new_amount' => $newAmount,
        ]);

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
                            'name' => "Updated payment for Quote #{$payment->quote_id}"
                        ]
                    ],
                    'quantity' => 1
                ]],
                'mode' => 'payment',
                'metadata' => [
                    'quote_id' => $payment->quote_id,
                    'client_id' => $payment->client_id,
                    'updated_payment' => true
                ],
                'success_url' => url('/payment-success?session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url' => url('/payment-cancel')
            ]);

            $payment->stripe_session_id = $session->id;
            $payment->stripe_payment_intent_id = $session->payment_intent;
            $paymentUrl = $session->url;
        } else {
            // Bank transfer
            $paymentUrl = null;
        }

        // 5. Update amount + save
        $payment->amount = $newAmount;
        $saved = $payment->save();

        Log::info('After save', [
            'payment_id' => $payment->id,
            'saved' => $saved,
            'new_amount_in_object' => $payment->amount,
            'amount_in_db' => Payment::find($payment->id)->amount ?? null,
        ]);

        // 5. Return response
        return [
            'status' => 'updated',
            'payment_method' => $payment->payment_method,
            'new_amount' => $payment->amount,
            'payment_url' => $paymentUrl,
        ];
    }



    public function handleStripeWebhook(array $payload, string $sigHeader)
    {
        $secret = config('stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\Exception $e) {
            Log::error('Stripe Webhook Error: ' . $e->getMessage());
            return ['status' => 'invalid'];
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;

            $payment = $this->paymentRepository->findByStripeSessionId($session->id);
            if ($payment) {
                $this->paymentRepository->update($payment, [
                    'status' => 'paid',
                    'stripe_payment_intent_id' => $session->payment_intent
                ]);

                Invoice::create([
                    'client_id' => $payment->quote->client_id,
                    'quote_number' => $payment->quote->quote_number,
                    'quotation_date' => now(),
                    'status' => 'paid',
                    'total_amount' => $payment->amount,
                ]);

                Log::info("Invoice created for quote {$payment->quote_id}");
            }
        }

        return ['status' => 'ok'];
    }
}
