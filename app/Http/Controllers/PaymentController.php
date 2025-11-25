<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Quotes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Webhook;
use App\Models\Invoice;

class PaymentController extends Controller
{
    public function createPaymentLink(Request $request, $quoteId)
    {
        $quote = Quotes::findOrFail($quoteId);

        // Initialize Stripe
        Stripe::setApiKey(env('STRIPE_SECRET'));

        // Create Stripe Checkout Session
        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => $quote->total_amount * 100,
                    'product_data' => [
                        'name' => "Quote #{$quote->id}"
                    ]
                ],
                'quantity' => 1
            ]],
            'mode' => 'payment',
            'metadata' => [
                'quote_id' => $quote->id
            ],
            'success_url' => url('/payment-success'),
            'cancel_url' => url('/payment-cancel')
        ]);

        // Save payment in DB
        $payment = Payment::create([
            'quote_id' => $quote->id,
            'amount' => $quote->total_amount,
            'currency' => 'test',
            'stripe_session_id' => $session->id,
            'status' => 'pending'
        ]);

        return response()->json([
            'payment_url' => $session->url
        ]);
    }

     public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = config('stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $secret
            );
        } catch (\Exception $e) {
            Log::error('Stripe Webhook Error: '.$e->getMessage());
            return response()->json(['error' => 'invalid'], 400);
        }

        // Log event type so we SEE what Stripe is sending
        Log::info('Stripe Event Received: '.$event->type);

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;

            // Log session data so we can debug
            Log::info('Stripe Session Data', (array) $session);

            $quoteId = $session->metadata->quote_id ?? null;

            if (!$quoteId) {
                Log::error('No quote_id in metadata');
                return response()->json(['ok'], 200);
            }

            // Find payment
            $payment = Payment::where('stripe_session_id', $session->id)->first();

            if (!$payment) {
                Log::error("Payment not found for session ID: ".$session->id);
                return response()->json(['ok'], 200);
            }

            $payment->update([
                'status' => 'paid',
                'stripe_payment_intent_id' => $session->payment_intent
            ]);

            // Create new invoice
            Invoice::create([
                'client_id'      => $payment->quote->client_id,
                'quote_number'   => $payment->quote->quote_number,
                'quotation_date' => now(),
                'status'         => 'paid',
                'total_amount'   => $payment->amount,
            ]);

            Log::info("Invoice created for quote $quoteId");
        }

        return response()->json(['status' => 'ok'], 200);
    }
}
