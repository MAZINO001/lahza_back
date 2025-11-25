<?php

namespace App\Http\Controllers;

use App\Models\Quotes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\Quotes_service;
use App\Models\Service;
use App\Models\Invoice;
class QuotesController extends Controller
{
    // GET /api/Quotes
    public function index()
    {
        $quotes = Quotes::with(['quoteServices', 'client.user:id,name'])->get();
        $allServices = Service::all();

        return response()->json([
            'quotes' => $quotes,
            'services' => $allServices
        ]);
    }

    // POST /api/Quotes
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'quotation_date' => 'required|date',
            'status' => 'required|in:draft,sent,confirmed,signed,rejected',
            'notes' => 'nullable|string',
            'total_amount' => 'required|numeric',
            'services' => 'array',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.quantity' => 'required|integer|min:1',
            'services.*.tax' => 'nullable|numeric',
            'services.*.individual_total' => 'nullable|numeric',
        ]);


        return DB::transaction(function () use ($validated) {



            // Generate the next quote number
            $latestQuote = Quotes::latest('id')->first();
            $nextNumber = $latestQuote ? $latestQuote->id + 1 : 1;
            $quoteNumber = 'QUOTE-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            // Create the quote
            $quote = Quotes::create([
                'client_id' => $validated['client_id'],
                'quotation_date' => $validated['quotation_date'],
                'status' => $validated['status'],
                'notes' => $validated['notes'],
                'quote_number' => $quoteNumber,
                'total_amount' => $validated['total_amount'],
            ]);

            // Insert pivot records
            if (!empty($validated['services'])) {
                foreach ($validated['services'] as $service) {
                    Quotes_service::create([
                        'quote_id' => $quote->id,
                        'service_id' => $service['service_id'],
                        'quantity' => $service['quantity'],
                        'tax' => $service['tax'] ?? 0,
                        'individual_total' => $service['individual_total'] ?? 0,
                    ]);
                }
            }

            return response()->json([$quote->load('quoteServices'), 'quote_id' => $quote->id], 201);
        });
    }

    // GET /api/quotes/{id}
    public function show($id)
    {
        $quote = Quotes::with('quoteServices')->findOrFail($id);
        return response()->json($quote);
    }

    // GET /api/quotes/{id}/services
    public function quoteServices(Quotes $quote)
    {
        $quote->load('quoteServices.service');
        return response()->json($quote);
    }

    /**
     * Create an invoice from a signed quote
     * 
     * @param int $id Quote ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function createInvoiceFromQuote($id)
    {
        $quote = Quotes::with(['quoteServices', 'client'])->findOrFail($id);
          
        $quote->update([
                'status' => 'signed',
            ]);

        // Check if quote has both signatures
        if (!$quote->adminSignature() || !$quote->clientSignature()) {
            return response()->json([
                'message' => 'Cannot create invoice: Quote is not fully signed by both parties'
            ], 422);
        }

        // Check if an invoice already exists for this quote
        if ($quote->invoice) {
            return response()->json([
                'message' => 'An invoice already exists for this quote',
                'invoice_id' => $quote->invoice->id
            ], 409);
        }

        return DB::transaction(function () use ($quote) {
            // Generate the next invoice number
            $latestInvoice = Invoice::latest('id')->first();
            $nextNumber = $latestInvoice ? $latestInvoice->id + 1 : 1;
            $invoiceNumber = 'INVOICE-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            // Calculate due date (30 days from now)
            $invoiceDate = now();
            $dueDate = now()->addDays(30);

            // Generate a checksum for the invoice
            $checksumData = [
                'client_id' => $quote->client_id,
                'quote_id' => $quote->id,
                'invoice_number' => $invoiceNumber,
                'total_amount' => $quote->total_amount,
            ];
            
            $checksum = hash('sha256', json_encode($checksumData) . config('app.key'));

            // Create the invoice
            $invoice = Invoice::create([
                'client_id' => $quote->client_id,
                'quote_id' => $quote->id,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'status' => 'unpaid',
                'notes' => 'Created from quote #' . $quote->quote_number,
                'total_amount' => $quote->total_amount,
                'balance_due' => $quote->total_amount,
                'checksum' => $checksum,
            ]);

            // Add services to the invoice
            foreach ($quote->quoteServices as $quoteService) {
                $invoice->services()->attach($quoteService->service_id, [
                    'invoice_id' => $invoice->id,
                    'service_id' => $quoteService->service_id,
                    'quantity' => $quoteService->quantity,
                    'tax' => $quoteService->tax,
                    'individual_total' => $quoteService->individual_total,
                ]);
            }
            
            // Save the invoice
            $invoice->save();

            // Generate payment link
            $paymentController = app(\App\Http\Controllers\PaymentController::class);
            $paymentResponse = $paymentController->createPaymentLink(new \Illuminate\Http\Request(), $quote->id);
            $paymentData = json_decode($paymentResponse->getContent(), true);
            $paymentUrl = $paymentData['payment_url'] ?? null;
            $bankInfo = $paymentData['bank_info'] ?? null;
            
            // Get the payment record that was just created
            $payment = \App\Models\Payment::where('quote_id', $quote->id)->latest()->first();

            // Send email notification
            $email = 'mangaka.wir@gmail.com';
            $data = [
                'quote' => $quote,
                'invoice' => $invoice,
                'client' => $quote->client,
                'paymentUrl' => $paymentUrl,
                'bankInfo' => $bankInfo,
                'payment' => $payment
            ];
            
            Mail::send('emails.invoice_created', $data, function($message) use ($email, $invoice) {
                $message->to($email)
                        ->subject('New Invoice Created - ' . $invoice->invoice_number);
            });

            return response()->json([
                'message' => 'Invoice created successfully and notification email sent',
                'invoice' => $invoice
            ], 201);
        });
    }

    // PUT /api/quotes/{id}
    public function update(Request $request, $id)
    {
        $quote = Quotes::findOrFail($id);

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'quotation_date' => 'required|date',
            'status' => 'required|in:draft,sent,confirmed,signed,rejected',
            'notes' => 'nullable|string',
            'total_amount' => 'required|numeric',
            'services' => 'array',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.quantity' => 'required|integer|min:1',
            'services.*.tax' => 'nullable|numeric',
            'services.*.individual_total' => 'nullable|numeric',
        ]);


        $quote->update($validated);

        // Update pivot table if services provided
        if (!empty($validated['services'])) {
            // Delete old pivot entries
            $quote->quoteServices()->delete();

            // Insert new pivot entries
            foreach ($validated['services'] as $service) {
                Quotes_service::create([
                    'quote_id' => $quote->id,
                    'service_id' => $service['service_id'],
                    'quantity' => $service['quantity'],
                    'tax' => $service['tax'] ?? 0,
                    'individual_total' => $service['individual_total'] ?? 0,
                ]);
            }
        }

        return response()->json($quote->load('quoteServices'));
    }

    // DELETE /api/quotes/{id}
    public function destroy($id)
    {
        $quote = Quotes::findOrFail($id);
        $quote->delete(); // Pivot entries cascade if foreign key is set
        return response()->json(null, 204);
    }
}
