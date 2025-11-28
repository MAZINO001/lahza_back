<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
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
public function getPayment(){
    return $this->paymentService->getPayment();
}
    public function createPaymentLink(Request $request, $invoiceId)
    {
        $invoice = Invoice::with('client')->findOrFail($invoiceId);
        $response = $this->paymentService->createPaymentLink($invoice);
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
        $request->validate([
            'percentage' => 'required|numeric|min:0.01|max:100',
        ]);

        try {
            $result = $this->paymentService->updatePendingPayment($payment, $request->percentage);
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
        $payload = $request->getContent(); // RAW JSON STRING
        $sigHeader = $request->header('Stripe-Signature');

        $result = $this->paymentService->handleStripeWebhook($payload, $sigHeader);

        return response()->json($result);
    }


public function getRemaining(Request $request, Invoice $invoice){
   $balance = $this->paymentService->getRemaining($invoice);

    return response()->json([
        'invoice_id' => $invoice->id,
        'balance_due' => $balance
    ]);
    }
    public function getInvoicePayments(Request $request, Invoice $invoice){
        $payments = $this->paymentService->getInvoicePayments($invoice);
        return response()->json($payments);
    }
 public function createAdditionalPayment(Request $request, Invoice $invoice, $percentage)
{
    // Validate the percentage from the URL parameter
    $validated = validator([
        'percentage' => $percentage
    ], [
        'percentage' => 'required|numeric|min:0.01|max:100'
    ])->validate();

    return $this->paymentService->createAdditionalPayment(
        $invoice,
        floatval($validated['percentage'])
    );
}

}
