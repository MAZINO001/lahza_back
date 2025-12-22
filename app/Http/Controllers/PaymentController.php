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
        $this->authorize('update', $payment);
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
    // $this->authorize('update', $invoice);


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
public function handleManualPayment(Payment $payment, bool $cancel = false)
{
    $this->authorize('update', $payment);

    if ($payment->payment_method === 'stripe') {
        return response()->json([
            'error' => true,
            'message' => 'Stripe method is not allowed',
        ]);
    }

    // ---- PAY FLOW ----
    if ($payment->status !== 'pending') {
        return response()->json([
            'error' => true,
            'message' => 'Only pending payments can be marked as paid',
        ]);
    }

    DB::transaction(function () use ($payment) {
        $payment->update(['status' => 'paid']);
        $this->paymentService->updateInvoiceStatus($payment->invoice);

        $paymentData = $payment->invoice
            ->payments()
            ->latest()
            ->first()?->toArray() ?? [];

        $this->projectCreationService->updateProjectAfterPayment($payment->invoice, $paymentData);
    });

    return response()->json([
        'success' => true,
        'message' => 'Payment processed successfully',
    ]);
}
public function cancelPayment(Payment $payment) {
    $this->authorize('update', $payment);


if ($payment->payment_method === 'stripe') {
        return response()->json([
            'error' => true,
            'message' => 'Stripe method is not allowed',
        ]);
    }
if ($payment->status !== 'paid') {
        return response()->json([
            'error' => true,
            'message' => "Only paid payments can be cancelled"
        ]);
    }

    DB::transaction(function() use ($payment) {
        $payment->update(['status' => 'pending']);
        $this->paymentService->updateInvoiceStatus($payment->invoice);
        $paymentData = $payment->invoice->payments()->latest()->first()?->toArray() ?? [];
        $this->projectCreationService->cancelProjectUpdate($payment->invoice);
    });

    return response()->json([
        'success' => true,
        'message' => "Payment cancelled successfully"
    ]);
}
public function updatePaymentDate(Request $request ,Payment $payment){
    $this->authorize('update', $payment);

    
    $request->validate([
        'updated_at' => 'required|date',
    ]);
$payment->update(
    ['updated_at' => $request->updated_at]
);
return response()->json([
    'success' => true,
    'message' => 'Payment date updated successfully',
],205);
}
}
