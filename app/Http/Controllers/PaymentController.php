<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Services\PaymentServiceInterface;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use App\Services\ProjectCreationService; 
use Illuminate\Support\Facades\DB;
 
class PaymentController extends Controller
{
    protected $paymentService;
    protected $projectCreationService;

    public function __construct(PaymentServiceInterface $paymentService ,ProjectCreationService $projectCreationService)
    {
        $this->paymentService = $paymentService;
        $this->projectCreationService = $projectCreationService;
    }
public function getPayment(){
    return $this->paymentService->getPayment();
}
    public function createPaymentLink(Request $request, $invoiceId)
    {
        $validated = $request->validate([
            'payment_percentage' => 'required|numeric|min:0.01|max:100',
            'payment_status' => 'required|string|in:pending,paid',
            'payment_type' => 'required|string|in:stripe,bank,cash,cheque',
        ]);

        $invoice = Invoice::with('client')->findOrFail($invoiceId);
        $response = $this->paymentService->createPaymentLink(
            $invoice,
            floatval($validated['payment_percentage']),
            $validated['payment_status'],
            $validated['payment_type']
        );
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
        'percentage' => $percentage,
        'payment_type' => $request->input('payment_type', 'stripe'),
        'payment_status' => $request->input('payment_status', 'pending')
    ], [
        'percentage' => 'required|numeric|min:0.01|max:100',
        'payment_type' => 'required|string|in:stripe,bank,cash,cheque',
        'payment_status' => 'required|string|in:pending,paid'
    ])->validate();

    return $this->paymentService->createAdditionalPayment(
        $invoice,
        floatval($validated['percentage']),
        $validated['payment_type'],
        $validated['payment_status']
    );
}
public function handlManuelPayment ( Payment $payment){
    $invoice = $payment->invoice;
    if($payment->payment_method == 'stripe'){
        return response()->json([
            'error' => true,
            'message' => "stripe method is not allowed "
        ]);
    }
    if($payment->status !=='pending'){
        return response()->json([
            'error' => true,
            'message' => "payment is already paid"
        ]);
    }
    if($payment->invoice->status == 'paid'){
        return response()->json([
            'error' => true,
            'message' => "invoice is already paid"
        ]);
    }
    DB::transaction(function () use ($payment, $invoice) {
        $payment->update([
            'status' => 'paid',
        ]);
        $this->paymentService->updateInvoiceStatus($invoice);
        return response()->json([
            'success' => true,
            'message' => "payment is paid"
        ]);
    });
    try {
                $this->projectCreationService->createProjectForInvoice($invoice);
                logger('test payment controller line 145');
            } catch (\Exception $e) {
                Log::error('Failed to create project for invoice #'.$invoice->id.': '.$e->getMessage());
            }
}

}
