<?php

namespace App\Http\Controllers;

use App\Models\Quotes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Quotes_service;
use App\Models\Service;
use App\Models\Invoice;
use App\Services\PaymentServiceInterface;
use App\Services\ProjectCreationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
class QuotesController extends Controller
{
    protected $paymentService;
    protected $projectCreationService;
    public function __construct(PaymentServiceInterface $paymentService , ProjectCreationService $projectCreationService)
    {
        $this->paymentService = $paymentService;
        $this->projectCreationService = $projectCreationService;
    }
    // GET /api/Quotes
    public function index()
    {
        $quotes = Quotes::with(['quoteServices', 'client.user:id,name,email', 'files'])->get();
        $allServices = Service::all();

        // Add signature URLs to each quote
        $quotes->each(function ($quote) {
            $quote->admin_signature_url = asset('images/admin_signature.png');
            $clientSignature = $quote->clientSignature();
            $quote->client_signature_url = $clientSignature ? $clientSignature->url : null;
            $quote->has_client_signature = $clientSignature !== null;
            $quote->has_admin_signature = $quote->adminSignature() !== null;
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
            'has_projects'=>'nullable',
        ]);


        return DB::transaction(function () use ($validated) {
            $quote = Quotes::create([
                'client_id' => $validated['client_id'],
                'quotation_date' => $validated['quotation_date'],
                'status' => $validated['status'],
                'notes' => $validated['notes'],
                'total_amount' => $validated['total_amount'],
                'has_projects' => is_array($validated["has_projects"])
                    ? json_encode($validated["has_projects"])
                    : $validated["has_projects"],
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

            // Auto-sign with admin signature
            $this->autoSignAdminSignature($quote);

            $quote->load('quoteServices', 'files');
            // Add signature URLs to the response
            $quote->admin_signature_url = asset('images/admin_signature.png');
            $clientSignature = $quote->clientSignature();
            $quote->client_signature_url = $clientSignature ? $clientSignature->url : null;
            $quote->has_client_signature = $clientSignature !== null;
            $quote->has_admin_signature = $quote->adminSignature() !== null;

            
                        $this->projectCreationService->createDraftProject($quote);

            return response()->json([$quote, 'quote_id' => $quote->id], 201);
        });
    }

    // GET /api/quotes/{id}
    public function show($id)
    {
        $quote = Quotes::with(['quoteServices', 'files'])->findOrFail($id);

        // Add signature URLs to the response
        $quote->admin_signature_url = asset('images/admin_signature.png');
        $clientSignature = $quote->clientSignature();
        $quote->client_signature_url = $clientSignature ? $clientSignature->url : null;
        $quote->has_client_signature = $clientSignature !== null;
        $quote->has_admin_signature = $quote->adminSignature() !== null;

        return response()->json($quote);
    }

    // GET /api/quotes/{id}/services
    public function quoteServices(Quotes $quote)
    {
        $quote->load('quoteServices.service', 'files');
        // Add signature URLs to the response
        $quote->admin_signature_url = asset('images/admin_signature.png');
        $clientSignature = $quote->clientSignature();
        $quote->client_signature_url = $clientSignature ? $clientSignature->url : null;
        $quote->has_client_signature = $clientSignature !== null;
        $quote->has_admin_signature = $quote->adminSignature() !== null;

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
                'has_projects'=> $quote->has_projects,
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

            // Copy client signature from quote to invoice if it exists
            $clientSignature = $quote->clientSignature();
            if ($clientSignature) {
                // Create a new file record for the invoice
                $invoice->files()->create([
                    'path' => $clientSignature->path,
                    'type' => 'client_signature',
                    'user_id' => $clientSignature->user_id, // Copy the user_id from the original signature
                    // fileable_type and fileable_id will be set automatically by the relationship
                ]);
                
                // Log the signature copy for debugging
                Log::info('Copied client signature to invoice', [
                    'quote_id' => $quote->id,
                    'invoice_id' => $invoice->id,
                    'signature_path' => $clientSignature->path
                ]);
            } else {
                Log::warning('No client signature found when creating invoice from quote', [
                    'quote_id' => $quote->id
                ]);
            }

            // Copy admin signature from quote to invoice if it exists, otherwise auto-sign
            $adminSignature = $quote->adminSignature();
            if ($adminSignature) {
                $invoice->files()->create([
                    'path' => $adminSignature->path,
                    'type' => 'admin_signature',
                    'user_id' => $adminSignature->user_id,
                ]);
            } else {
                // Auto-sign with admin signature
                $this->autoSignAdminSignature($invoice);
            }


            $paymentMethod = strtolower($quote->client->country) === 'maroc'
    ? 'bank'
    : 'stripe';

            // Create payment link with default values when creating invoice from quote
            // Default: 50% advance payment, pending status, bank payment method
            $response = $this->paymentService->createPaymentLink(
                $invoice,
                50.0,           // payment_percentage: 50% advance payment
                'pending',      // payment_status: pending until paid
                $paymentMethod          // payment_type: bank transfer (default)
            );
        
            $email = 'mangaka.wir@gmail.com';
            $data = [
                'quote' => $quote,
                'invoice' => $invoice,
                'client' => $quote->client,
                'payment_url' => $response['payment_url'],
                'bank_info' => $response['bank_info'],
                'payment_method' => $response['payment_method'],       // string: 'stripe' or 'bank'
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
            'has_projects'=>'nullable',
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

        $quote->load('quoteServices', 'files');
        // Add signature URLs to the response
        $quote->admin_signature_url = asset('images/admin_signature.png');
        $clientSignature = $quote->clientSignature();
        $quote->client_signature_url = $clientSignature ? $clientSignature->url : null;
        $quote->has_client_signature = $clientSignature !== null;
        $quote->has_admin_signature = $quote->adminSignature() !== null;

        return response()->json($quote);
    }

    // DELETE /api/quotes/{id}
    public function destroy($id)
    {
        $quote = Quotes::findOrFail($id);
        $quote->delete(); // Pivot entries cascade if foreign key is set
        return response()->json(null, 204);
    }

    /**
     * Automatically sign with admin signature by copying default admin signature image
     */
    private function autoSignAdminSignature($instance)
    {
        // Check if admin signature already exists
        if ($instance->adminSignature()) {
            return;
        }

        try {
            $defaultPath = public_path('images/admin_signature.png');
            if (!file_exists($defaultPath)) {
                Log::warning('Default admin signature image not found at: ' . $defaultPath);
                return;
            }

            // Generate unique filename
            $filename = 'admin_signature_' . uniqid() . '.png';
            $storagePath = 'signatures/' . $filename;

            // Copy the default admin signature to storage
            $imageData = file_get_contents($defaultPath);
            Storage::disk('public')->put($storagePath, $imageData);

            // Create file record
            $instance->files()->create([
                'path' => $storagePath,
                'type' => 'admin_signature',
                'user_id' => Auth::id() ?? 1, // Use authenticated user or default admin user
            ]);

            Log::info('Auto-signed with admin signature', [
                'model' => get_class($instance),
                'id' => $instance->id,
                'signature_path' => $storagePath
            ]);
        } catch (\Exception $e) {
            Log::error('Error auto-signing with admin signature: ' . $e->getMessage(), [
                'model' => get_class($instance),
                'id' => $instance->id
            ]);
        }
    }
}
