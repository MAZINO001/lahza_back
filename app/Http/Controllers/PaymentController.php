<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Services\PaymentServiceInterface;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use App\Services\ProjectCreationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\Project;

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
            'payment_method' => 'required|string|in:stripe,bank,cash,cheque',
        ]);

        try {
            $result = $this->paymentService->updatePendingPayment($payment, $request->percentage, $request->payment_method);
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
public function handleManualPayment(Payment $payment)
{
    // $this->authorize('update', $payment);

    try {
        $result = $this->paymentService->handleManualPayment($payment);
        
        return response()->json($result);
    } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
        throw $e;
    } catch (\Exception $e) {
        return response()->json([
            'error' => true,
            'message' => 'Failed to process payment: ' . $e->getMessage(),
        ], 500);
    }
}

public function cancelPayment(Payment $payment)
{
    $this->authorize('update', $payment);

    try {
        $result = $this->paymentService->cancelManualPayment($payment);
        
        return response()->json($result);
    } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
        throw $e;
    } catch (\Exception $e) {
        return response()->json([
            'error' => true,
            'message' => 'Failed to cancel payment: ' . $e->getMessage(),
        ], 500);
    }
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

    public function getProjectPayments(Project $project)
{
    $payments = Payment::whereHas('invoice', function ($q) use ($project) {
        $q->whereHas('projects', function ($q2) use ($project) {
            $q2->where('projects.id', $project->id);
        });
    })->get();

    return response()->json($payments);
}

public function show(Payment $payment)
{
    $this->authorize('view', $payment);
    return response()->json($payment->load('invoice', 'user'));
}

}
