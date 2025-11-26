<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Quotes;
use App\Services\PaymentServiceInterface;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
class PaymentController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentServiceInterface $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function createPaymentLink(Request $request, $quoteId)
    {
        $quote = Quotes::with('client')->findOrFail($quoteId);
        $response = $this->paymentService->createPaymentLink($quote);
        return response()->json($response);
    }
    /**
     * Update a payment amount
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Payment  $payment
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePayment(Request $request, Payment $payment)
    {
        Log::info('PaymentController@updatePayment called', [
            'payment_id' => $payment->id ?? null,
            'request_data' => $request->all(),
        ]);

        $request->validate([
            'percentage' => 'required|numeric|min:0.01|max:100',
        ]);

        try {
            $result = $this->paymentService->updatePendingPayment($payment, $request->percentage);
            Log::info('PaymentController@updatePayment result', [
                'payment_id' => $payment->id ?? null,
                'result' => $result,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    public function handleWebhook(Request $request)
    {
        $payload = json_decode($request->getContent(), true);
        $sigHeader = $request->header('Stripe-Signature');

        $result = $this->paymentService->handleStripeWebhook($payload, $sigHeader);

        return response()->json($result);
    }
}
