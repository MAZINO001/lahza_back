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
use App\Services\ActivityLoggerService;
use App\Mail\InvoiceCreatedMail;
use App\Mail\SendReportMail;
use App\Mail\QuoteCreatedMail;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

class QuotesController extends Controller
{
    protected $paymentService;
    protected $projectCreationService;
    protected $activityLogger;

    public function __construct(
        PaymentServiceInterface $paymentService,
        ProjectCreationService $projectCreationService,
        ActivityLoggerService $activityLogger
        ) 
    {
        $this->paymentService = $paymentService;
        $this->projectCreationService = $projectCreationService;
        $this->activityLogger = $activityLogger;
    }

    public function index()
    {
        $user = Auth::user();
        $quotes = Quotes::with(['quoteServices', 'client.user:id,name,email', 'files', 'projects'])->when($user->role ==='client',function($query) use ($user){
            $query->where('client_id', $user->client->id);
        })->get();
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

    public function store(Request $request)
    {
        $this->authorize('create',Quotes::class);
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
            'has_projects' => 'nullable',
            'description' => 'nullable'
        ]);

        return DB::transaction(function () use ($validated) {
            $quoteData = [
                'client_id' => $validated['client_id'],
                'quotation_date' => $validated['quotation_date'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'total_amount' => $validated['total_amount'],
                'has_projects' => is_array($validated['has_projects'] ?? null) 
                    ? json_encode($validated['has_projects']) 
                    : ($validated['has_projects'] ?? null),
                'description' => $validated['description'] ?? null
            ];
            
            $quote = Quotes::create($quoteData);

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

            // Create draft project(s) for quote path (PATH 1)
            $this->projectCreationService->createDraftProjectFromQuote($quote);

            // Send quote created email with PDF attachment
            // Only send email if status is NOT 'draft'
            if ($validated['status'] !== 'draft') {
                $this->sendQuoteCreatedEmail($quote);
            }
            // Log activity
            $this->activityLogger->log(
                'clients_details',
                'quotes',
                $quote->client->id,
                request()->ip(),
                request()->userAgent(),
                [
                    'quote_id' => $quote->id,
                    'client_id' => $quote->client_id,
                    'total_amount' => $quote->total_amount,
                    'status' => $quote->status,
                    'url' => request()->fullUrl()
                ],
                "Quote #{$quote->id} created for client #{$quote->client_id} with status: {$quote->status}"
            );

            return response()->json([
                $quote->load('projects'),
                'quote_id' => $quote->id
            ], 201);
        });
    }

    public function show($id)
    {
        $quote = Quotes::with(['quoteServices', 'files'])->findOrFail($id);
        
        $this->authorize('view', $quote); 

        // Add signature URLs to the response
        $quote->admin_signature_url = asset('images/admin_signature.png');
        $clientSignature = $quote->clientSignature();
        $quote->client_signature_url = $clientSignature ? $clientSignature->url : null;
        $quote->has_client_signature = $clientSignature !== null;
        $quote->has_admin_signature = $quote->adminSignature() !== null;

        return response()->json($quote);
    }

    public function quoteServices(Quotes $quote)
    {
        $quote->load('quoteServices.service', 'files', 'projects');

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
     * This is the transition point from PATH 1 (Quote → Invoice)
     */
    public function createInvoiceFromQuote($id)
    {
        $quote = Quotes::with(['quoteServices', 'client', 'projects'])->findOrFail($id);

        // Check if quote has client signature
        if (!$quote->clientSignature()) {
            return response()->json([
                'message' => 'Cannot create invoice: Quote is not fully signed by client'
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
                'description' => $quote->description,
                'has_projects' => $quote->has_projects,
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

            // Copy client signature from quote to invoice
            $this->copySignatureToInvoice($quote, $invoice, 'client_signature');

            // Copy admin signature from quote to invoice or auto-sign
            if ($quote->adminSignature()) {
                $this->copySignatureToInvoice($quote, $invoice, 'admin_signature');
            } else {
                $this->autoSignAdminSignature($invoice);
            }

            // Update quote-based projects: draft → pending + link to invoice
            $this->projectCreationService->updateProjectsOnQuoteSigned($quote, $invoice);

            // If the invoice ended up with no projects, notify client/admin
            $invoice->load('projects');  

            // Determine payment method based on client country
            $paymentMethod = strtolower($quote->client->country) === 'maroc'
                ? 'bank'
                : 'stripe';

            // Create payment link (50% advance payment by default)
            $response = $this->paymentService->createPaymentLink(
                $invoice,
                50.0,           // payment_percentage: 50% advance payment
                'pending',      // payment_status: pending until paid
                $paymentMethod  // payment_type: bank or stripe
            );
            $quote->update(['status' => 'billed']);
            // Send invoice email
            $this->sendInvoiceCreatedEmail($quote, $invoice, $response);

            // Log activity
            $this->activityLogger->log(
                'clients_details',
                'invoices',
                $invoice->client->id,
                request()->ip(),
                request()->userAgent(),
                [
                    'invoice_id' => $invoice->id,
                    'quote_id' => $quote->id,
                    'client_id' => $invoice->client_id,
                    'total_amount' => $invoice->total_amount,
                    'status' => $invoice->status,
                    'url' => request()->fullUrl()
                ],
                "Invoice #{$invoice->id} created from quote #{$quote->id} for client #{$invoice->client_id}"
            );

            return response()->json([
                'message' => 'Invoice created successfully',
                'invoice' => $invoice->load('services', 'projects'),
                'payment' => $response['payment_method']
            ], 201);
        });
    }

    public function update(Request $request, $id)
    {
        $quote = Quotes::findOrFail($id);

        $this->authorize('update',$quote);

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
            'has_projects' => 'nullable',
            'description' => 'nullable'
        ]);

        return DB::transaction(function () use ($quote, $validated) {
            $quote->update([
                'client_id' => $validated['client_id'],
                'quotation_date' => $validated['quotation_date'],
                'status' => $validated['status'],
                'notes' => $validated['notes'],
                'total_amount' => $validated['total_amount'],
                'has_projects' => is_array($validated["has_projects"])
                    ? json_encode($validated["has_projects"])
                    : $validated["has_projects"],
                'description' => $validated['description']
            ]);

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

            $quote->load('quoteServices', 'files', 'projects');

            // Add signature URLs to the response
            $quote->admin_signature_url = asset('images/admin_signature.png');
            $clientSignature = $quote->clientSignature();
            $quote->client_signature_url = $clientSignature ? $clientSignature->url : null;
            $quote->has_client_signature = $clientSignature !== null;
            $quote->has_admin_signature = $quote->adminSignature() !== null;

            return response()->json($quote);
        });
    }

    public function destroy($id)
    {
        $quote = Quotes::findOrFail($id);
        $this->authorize('delete');

        // Note: Projects linked to quotes should be handled carefully
        // If quote has an invoice, the invoice owns the project relationship
        // If quote doesn't have an invoice, we can delete the draft projects
        if (!$quote->invoice) {
            // Delete draft projects that were created from this quote
            $quote->projects()->each(function ($project) {
                $this->projectCreationService->deleteProject($project->id);
            });
        }

        $quote->delete();

        return response()->json(null, 204);
    }

    /**
     * Copy signature from quote to invoice
     */
    private function copySignatureToInvoice($quote, $invoice, $signatureType)
    {
        $signature = $signatureType === 'client_signature'
            ? $quote->clientSignature()
            : $quote->adminSignature();

        if ($signature) {
            $invoice->files()->create([
                'path' => $signature->path,
                'type' => $signatureType,
                'user_id' => $signature->user_id,
            ]);

            Log::info("Copied {$signatureType} to invoice", [
                'quote_id' => $quote->id,
                'invoice_id' => $invoice->id,
                'signature_path' => $signature->path
            ]);
        } else {
            Log::warning("No {$signatureType} found when creating invoice from quote", [
                'quote_id' => $quote->id
            ]);
        }
    }

    /**
     * Send quote created email with PDF attachment
     */
    public function sendQuote(Request $request, Quotes $quote)
{
    $this->authorize('update', $quote);

    // Ensure quote is actually a draft
    if ($quote->status !== 'draft') {
        return response()->json([
            'status' => 'error',
            'message' => "Cannot send quote: Quote #{$quote->id} is not a draft (current status: {$quote->status})"
        ], 400);
    }

    return DB::transaction(function () use ($quote) {
        
        // Update quote status from 'draft' to 'sent'
        $quote->update([
            'status' => 'sent',
        ]);

        // Refresh quote to ensure status is updated
        $quote->refresh();

        // Load all necessary relationships for email
        $quote->load('client.user', 'quoteServices', 'files');

        try {
            // Send email with PDF
            $this->sendQuoteCreatedEmail($quote);

        } catch (\Exception $e) {
            // Rollback status if email fails
            $quote->update(['status' => 'draft']);
            
            Log::error('Failed to send quote', [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => "Failed to send quote: {$e->getMessage()}"
            ], 500);
        }

        // Log activity
        $this->activityLogger->log(
            'clients_details',
            'quotes',
            $quote->client->id,
            request()->ip(),
            request()->userAgent(),
            [
                'quote_id' => $quote->id,
                'client_id' => $quote->client_id,
                'total_amount' => $quote->total_amount,
                'status' => $quote->status,
                'url' => request()->fullUrl()
            ],
            "Quote #{$quote->id} sent to client #{$quote->client_id}"
        );

        return response()->json([
            'message' => 'Quote sent successfully',
            'quote' => $quote->load("quoteServices", "projects", "client"),
        ], 200);
    });
}
    private function sendQuoteCreatedEmail($quote)
    {
        try {
            // Get client's email from user relationship
            $clientEmail = $quote->client->user->email ?? null;

            if (!$clientEmail) {
                Log::warning('Cannot send quote email: client has no email address', [
                    'quote_id' => $quote->id,
                    'client_id' => $quote->client_id,
                ]);
                return;
            }

            // Check email notifications preference
            if (!$quote->client->user->allowsMail('quotes')) {

                Log::info('Quote email not sent: email notifications disabled', [
                    'quote_id' => $quote->id,
                    'client_id' => $quote->client_id,
                ]);
                return;
            }

            // Generate PDF for the quote
            $reportsDir = storage_path('app/reports');
            if (!file_exists($reportsDir)) {
                mkdir($reportsDir, 0777, true);
            }

            $adminSignatureBase64 = null;
            $clientSignatureBase64 = null;
            if ($quote->adminSignature()) {
                $adminSignatureBase64 = $this->getImageBase64($quote->adminSignature()->path);
            } else {
                $adminSignatureBase64 = $this->getDefaultAdminSignatureBase64();
            }
            if ($quote->clientSignature()) {
                $clientSignatureBase64 = $this->getImageBase64($quote->clientSignature()->path);
            }

            $quote->load('quoteServices', 'services', 'files');

            // Calculate totals for PDF
            $totalHT = 0;
            $totalTVA = 0;
            $totalTTC = 0;

            foreach ($quote->quoteServices ?? [] as $line) {
                $ttc = (float) ($line->individual_total ?? 0);
                $rate = ((float) ($line->tax ?? 0)) / 100;

                $ht = $rate > 0 ? $ttc / (1 + $rate) : $ttc;
                $tva = $ttc - $ht;

                $totalHT += $ht;
                $totalTVA += $tva;
                $totalTTC += $ttc;
            }

            $currency = $quote->client->currency ?? 'MAD';

            $pdf = PDF::loadView('pdf.document', [
                'quote' => $quote,
                'type' => 'quote',
                'totalHT' => $totalHT,
                'totalTVA' => $totalTVA,
                'totalTTC' => $totalTTC,
                'currency' => $currency,
                'adminSignatureBase64' => $adminSignatureBase64,
                'clientSignatureBase64' => $clientSignatureBase64,
            ]);

            $fullPdfPath = $reportsDir . '/' . uniqid() . '_quote_' . $quote->id . '.pdf';
            $pdf->save($fullPdfPath);

            if (!file_exists($fullPdfPath)) {
                Log::error('PDF generation failed for quote email', [
                    'quote_id' => $quote->id,
                ]);
                return;
            }

            // Queue email with PDF attachment
            Mail::to($clientEmail)->queue(new QuoteCreatedMail($quote, $fullPdfPath));

            Log::info('Quote created email queued successfully', [
                'quote_id' => $quote->id,
                'client_id' => $quote->client_id,
                'email' => $clientEmail,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue quote created email', [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Get image as base64 data URI
     */
    private function getImageBase64($path)
    {
        try {
            $fullPath = Storage::disk('public')->path($path);
            if (file_exists($fullPath)) {
                $imageData = Storage::disk('public')->get($path);
                $mimeType = mime_content_type($fullPath) ?: 'image/png';
                return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
            }
        } catch (\Exception $e) {
            Log::error('Error converting image to base64: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Get default admin signature as base64
     */
    private function getDefaultAdminSignatureBase64()
    {
        try {
            $defaultPath = public_path('images/admin_signature.png');
            if (file_exists($defaultPath)) {
                $imageData = file_get_contents($defaultPath);
                $mimeType = mime_content_type($defaultPath);
                return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
            }
        } catch (\Exception $e) {
            Log::error('Error getting default admin signature base64: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Send invoice created email
     */
    private function sendInvoiceCreatedEmail($quote, $invoice, $paymentResponse)
    {
        try {
            $email = 'mangaka.wir@gmail.com';
            
            $invoiceNumber = 'INVOICE-' . str_pad($quote->id, 6, '0', STR_PAD_LEFT);
            
            $data = [
                'quote' => $quote,
                'invoice' => $invoice,
                'client' => $quote->client,
                'client_id' => $quote->client_id,
                'payment_url' => $paymentResponse['payment_url']??null,
                'bank_info' => $paymentResponse['bank_info']??null,
                'payment_method' => $paymentResponse['payment_method'],
                'subject' => 'New Invoice Created - ' . $invoiceNumber,
            ];
        if($quote->client->user->allowsMail('quotes') || $quote->client->allowsMail('invoices')){
            Mail::to($email)->send(new InvoiceCreatedMail($data));
        }
        } 
        catch (\Exception $e) {
            Log::error('Failed to send invoice email', [
                'invoice_id' => $invoice->id,
                'quote_id' => $quote->id,
                'error' => $e->getMessage()
            ]);   
    }
    }

    /**
     * Automatically sign with admin signature
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
                'user_id' => Auth::id() ?? 1,
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
