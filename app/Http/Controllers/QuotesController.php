<?php

namespace App\Http\Controllers;

use App\Models\Quotes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Quotes_service;
use App\Models\Service;
use App\Models\Invoice;
use App\Services\PaymentServiceInterface;
use Illuminate\Support\Facades\Mail;

class QuotesController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentServiceInterface $paymentService)
    {
        $this->paymentService = $paymentService;
    }
    // GET /api/Quotes
    public function index()
    {
        $quotes = Quotes::with(['quoteServices', 'client.user:id,name,email'])->get();
        $allServices = Service::all();

        // Add admin signature URL to each quote
        $quotes->each(function ($quote) {
            $quote->admin_signature_url = asset('images/admin_signature.png');
        });

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
            $quote = Quotes::create([
                'client_id' => $validated['client_id'],
                'quotation_date' => $validated['quotation_date'],
                'status' => $validated['status'],
                'notes' => $validated['notes'],
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
            $quote->load('quoteServices');
            // Add admin signature URL to the response
            $quote->admin_signature_url = asset('images/admin_signature.png');

            return response()->json([$quote, 'quote_id' => $quote->id], 201);
        });
    }

    // GET /api/quotes/{id}
    public function show($id)
    {
        $quote = Quotes::with('quoteServices')->findOrFail($id);

        // Add admin signature URL to the response
        $quote->admin_signature_url = asset('images/admin_signature.png');

        return response()->json($quote);
    }

    // GET /api/quotes/{id}/services
    public function quoteServices(Quotes $quote)
    {
        $quote->load('quoteServices.service');
        // Add admin signature URL to the response
        $quote->admin_signature_url = asset('images/admin_signature.png');

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

        // Check if quote has both signatures
        if (!$quote->clientSignature()) {
            return response()->json([
                'message' => 'Cannot create invoice: Quote is not fully signed by client partie'
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
            // Calculate due date (30 days from now)
            $invoiceDate = now();
            $dueDate = now()->addDays(30);

            // Generate a checksum for the invoice
            $checksumData = [
                'client_id' => $quote->client_id,
                'quote_id' => $quote->id,
                'total_amount' => $quote->total_amount,
                'due_date' => $dueDate,
                'invoice_date' => $invoiceDate,
                'balance_due' => $quote->total_amount,

            ];
            $checksum = md5(json_encode($checksumData));

            // Create the invoice
            $invoice = Invoice::create([
                'client_id' => $quote->client_id,
                'quote_id' => $quote->id,
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'status' => 'unpaid',
                'notes' => 'Created from quote #' . $quote->id,
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


            $response = $this->paymentService->createPaymentLink($quote);

            $email = 'mangaka.wir@gmail.com';
            $data = [
                'quote' => $quote,
                'invoice' => $invoice,
                'client' => $quote->client,
                'payment_url' => $response['payment_url'],
                'bank_info' => $response['bank_info'],
                'payment_method' => $response['payment_method'],       // string: 'stripe' or 'banc'
            ];




            Mail::send('emails.invoice_created', $data, function ($message) use ($email, $invoice) {
                $latestInvoice = Invoice::latest('id')->first();
                $nextNumber = $latestInvoice ? $latestInvoice->id + 1 : 1;
                $invoiceNumber = 'INVOICE-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
                $message->to($email)
                    ->subject('New Invoice Created - ' . $invoiceNumber);
            });

            return response()->json([
                'message' => 'Invoice created successfully',
                'invoice' => $invoice->load('services'),
                'payment' => $response['payment_method']
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
