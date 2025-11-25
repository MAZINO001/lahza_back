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
        $quote = Quotes::with('client')->findOrFail($quoteId);
        $client = $quote->client;
        
        // Check if client is from Morocco
        $isMoroccanClient = strtolower($client->country) === 'morocco' || 
            strtolower($client->country) === 'maroc' || 
            strtolower($client->country) === 'ma' ||
            strtolower($client->country) === 'mar';
        
        // Calculate half of the total amount for advance payment
        $halfAmount = $quote->total_amount / 2;
        
        // Default payment method based on client's country
        $paymentMethod = $isMoroccanClient ? 'banc' : 'stripe';
        
        $paymentData = [
            'quote_id' => $quote->id,
            'client_id' => $client->id,
            'total' => $quote->total_amount, 
            'amount' => $halfAmount,
            'total_amount' => $quote->total_amount,
            'currency' => 'usd',
            'payment_method' => $paymentMethod,
            'status' => $isMoroccanClient ? 'pending' : 'pending_stripe'
        ];
        
        if (!$isMoroccanClient) {
            // Initialize Stripe for non-Moroccan clients
            Stripe::setApiKey(env('STRIPE_SECRET'));
            
            // Create Stripe Checkout Session
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
            
            // Add Stripe specific data
            $paymentData['stripe_session_id'] = $session->id;
            $paymentData['stripe_payment_intent_id'] = $session->payment_intent;
        }
        
        // Save payment in DB
        $payment = Payment::create($paymentData);
        
        $response = [
            'payment_method' => $paymentMethod,
            'amount' => $halfAmount,
            'total_amount' => $quote->total_amount,
            'is_advance_payment' => true
        ];
        
        if (!$isMoroccanClient) {
            $response['payment_url'] = $session->url;
            // Here you would typically send an email with Stripe payment details
            // Mail::to($client->email)->send(new StripePaymentLinkCreated($payment, $session->url));
        } else {
            // Here you would typically send an email with bank transfer instructions
            // Mail::to($client->email)->send(new BankTransferInstructions($payment));
              $response['bank_info'] = [
        'bank_name' => 'YOUR_BANK_NAME',
        'account_holder' => 'YOUR_COMPANY_NAME',
        'rib' => 'YOUR_RIB_NUMBER',
        'swift_code' => 'YOUR_SWIFT_CODE',
        'iban' => 'YOUR_IBAN',
        'reference' => 'PAY-' . $payment->id . '-' . $payment->quote_id
    ];
        }
        
        return response()->json($response);
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
