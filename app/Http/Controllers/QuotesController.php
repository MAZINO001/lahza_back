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
use App\Models\QuoteSubscription;
use App\Models\InvoiceSubscription;
use App\Jobs\SendQuoteEmailJob;
use App\Jobs\SendInvoiceCreatedFromQuoteJob;
use App\Jobs\CreatePaymentLinkJob;
use App\Jobs\LogActivityJob;
use App\Jobs\CreateDraftProjectFromQuoteJob;
use App\Jobs\UpdateProjectsOnQuoteSignedJob;
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
    $this->authorize('create', Quotes::class);
    
    $validated = $request->validate([
        'client_id' => 'required|exists:clients,id',
        'quotation_date' => 'required|date',
        'status' => 'required|in:draft,sent,confirmed,signed,rejected',
        'notes' => 'nullable|string',
        'total_amount' => 'required|numeric',
        'currency' => 'nullable|string|max:10|in:MAD,EUR,USD',
        'services' => 'nullable|array',
        'services.*.service_id' => 'required|exists:services,id',
        'services.*.quantity' => 'required|integer|min:1',
        'services.*.tax' => 'nullable|numeric',
        'services.*.individual_total' => 'nullable|numeric',
        'subscriptions' => 'nullable|array',
        'subscriptions.*.plan_id' => 'required|exists:plans,id',
        'subscriptions.*.billing_cycle' => 'required|in:monthly,yearly',
        'subscriptions.*.price_snapshot' => 'required|numeric',
        'has_projects' => 'nullable',
        'description' => 'nullable',
        'attachment_file_ids' => 'nullable|array',
        'attachment_file_ids.*' => 'integer|exists:files,id',
    ]);

    return DB::transaction(function () use ($validated) {
        // Load client to determine currency if not provided
        $client = \App\Models\Client::findOrFail($validated['client_id']);
        
        // Currency priority:
        // 1. Admin-provided currency (EUR/USD/MAD) - takes precedence even if client is Moroccan
        // 2. Auto-determined from client country (Morocco = MAD, else = EUR)
        $currency = $validated['currency'] ?? Quotes::determineCurrency($client);
        
        // Recalculate totals from services + subscriptions so quote total is always correct
        $subscriptionInvoiceService = app(\App\Services\SubscriptionInvoiceService::class);
        $totals = $subscriptionInvoiceService->calculateInvoiceTotals(
            $validated['services'] ?? [],
            $validated['subscriptions'] ?? []
        );

        $calculatedTotal = $totals['total_amount'];

        $quoteData = [
            'client_id' => $validated['client_id'],
            'quotation_date' => $validated['quotation_date'],
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'total_amount' => $calculatedTotal,
            'currency' => $currency,
            'has_projects' => is_array($validated['has_projects'] ?? null) 
                ? json_encode($validated['has_projects']) 
                : ($validated['has_projects'] ?? null),
            'description' => $validated['description'] ?? null
        ];
        
        $quote = Quotes::create($quoteData);

        // Insert service records
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

        // Insert subscription records
        if (!empty($validated['subscriptions'])) {
            foreach ($validated['subscriptions'] as $subscription) {
                QuoteSubscription::create([
                    'quote_id' => $quote->id,
                    'plan_id' => $subscription['plan_id'],
                    'price_snapshot' => $subscription['price_snapshot'],
                    'billing_cycle' => $subscription['billing_cycle'],
                ]);
            }
        }

        // Auto-sign with admin signature
        $this->autoSignAdminSignature($quote);

        $quote->load('quoteServices', 'quoteSubscriptions.plan', 'files');

        // Add signature URLs to the response
        $quote->admin_signature_url = asset('images/admin_signature.png');
        $clientSignature = $quote->clientSignature();
        $quote->client_signature_url = $clientSignature ? $clientSignature->url : null;
        $quote->has_client_signature = $clientSignature !== null;
        $quote->has_admin_signature = $quote->adminSignature() !== null;

        // Create draft project(s) for quote path (PATH 1) — dispatched for instant response
        CreateDraftProjectFromQuoteJob::dispatch($quote);

        // Dispatch job to send quote created email with PDF attachment
        // Only send email if status is NOT 'draft'
        if ($validated['status'] !== 'draft') {
            SendQuoteEmailJob::dispatch($quote, $validated['attachment_file_ids'] ?? []);
        }

        // Log activity asynchronously
        LogActivityJob::dispatch(
            Auth::id(),
            Auth::user()->role ?? null,
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
                'has_subscriptions' => !empty($validated['subscriptions']),
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
    $quote = Quotes::with(['quoteServices', 'quoteSubscriptions.plan', 'client', 'projects'])->findOrFail($id);

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

        // Recalculate totals from quote services + subscriptions to ensure correctness
        // using the shared SubscriptionInvoiceService so that logic is centralised.
        $subscriptionInvoiceService = app(\App\Services\SubscriptionInvoiceService::class);

        $servicesArray = $quote->quoteServices->map(function ($service) {
            return [
                'individual_total' => $service->individual_total,
            ];
        })->toArray();

        $subscriptionsArray = $quote->quoteSubscriptions->map(function ($subscription) {
            return [
                'price_snapshot' => $subscription->price_snapshot,
            ];
        })->toArray();

        $totals = $subscriptionInvoiceService->calculateInvoiceTotals(
            $servicesArray,
            $subscriptionsArray
        );

        $totalAmount = $totals['total_amount'];

        // Keep quote total in sync as well
        $quote->update(['total_amount' => $totalAmount]);

        // Generate a checksum for the invoice
        $checksumData = [
            'client_id' => $quote->client_id,
            'quote_id' => $quote->id,
            'total_amount' => $totalAmount,
            'due_date' => $dueDate,
            'invoice_date' => $invoiceDate,
            'balance_due' => $quote->total_amount,
        ];
        $checksum = md5(json_encode($checksumData));

        // Use quote currency, fallback to determining from client if quote doesn't have currency
        $currency = $quote->currency ?? Invoice::determineCurrency($quote->client);

        // Create the invoice
        $invoice = Invoice::create([
            'client_id' => $quote->client_id,
            'quote_id' => $quote->id,
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'status' => 'unpaid',
            'notes' => 'Created from quote #' . $quote->id,
            'total_amount' => $totalAmount,
            'balance_due' => $totalAmount,
            'checksum' => $checksum,
            'description' => $quote->description,
            'has_projects' => $quote->has_projects,
            'currency' => $currency,
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

        // Add subscriptions to the invoice
        foreach ($quote->quoteSubscriptions as $quoteSubscription) {
            InvoiceSubscription::create([
                'invoice_id' => $invoice->id,
                'plan_id' => $quoteSubscription->plan_id,
                'subscription_id' => null, // Will be set after payment
                'price_snapshot' => $quoteSubscription->price_snapshot,
                'billing_cycle' => $quoteSubscription->billing_cycle,
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

        // Update quote-based projects: draft → pending + link to invoice — dispatched for instant response
        UpdateProjectsOnQuoteSignedJob::dispatch($quote, $invoice);

        // Load invoice relationships
        $invoice->load('projects', 'invoiceSubscriptions.plan');

        // Determine payment method based on client country
        $paymentMethod = strtolower($quote->client->country) === 'maroc'
            ? 'bank'
            : 'stripe';

        // Determine payment percentage, interpreted as "services percentage"
        $hasSubscriptions = $invoice->hasSubscriptions();
        $hasServices = $invoice->invoiceServices()->exists();

        if ($hasServices && $hasSubscriptions) {
            // Mixed: 50% on services, 100% on subscriptions by default
            $paymentPercentage = 50.0;
        } elseif ($hasSubscriptions && !$hasServices) {
            // Only subscriptions: 100% (full subscription amount)
            $paymentPercentage = 100.0;
        } else {
            // Only services: legacy 50% advance
            $paymentPercentage = 50.0;
        }

        $quote->update(['status' => 'billed']);
        
        // Dispatch job to create payment link and send invoice created email
        // This runs asynchronously to speed up invoice creation
        CreatePaymentLinkJob::dispatch(
            $invoice,
            $paymentPercentage,
            'pending',
            $paymentMethod,
            [], // No attachment file IDs
            true, // Send invoice created email
            $quote // Pass quote for invoice created email
        );

        // Log activity asynchronously
        LogActivityJob::dispatch(
            Auth::id(),
            Auth::user()->role ?? null,
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
                'has_subscriptions' => $invoice->hasSubscriptions(),
                'url' => request()->fullUrl()
            ],
            "Invoice #{$invoice->id} created from quote #{$quote->id} for client #{$invoice->client_id}"
        );

        return response()->json([
            'message' => 'Invoice created successfully. Payment link is being created and will be sent via email.',
            'invoice' => $invoice->load('services', 'projects', 'invoiceSubscriptions.plan'),
            'payment' => [
                'status' => 'processing',
                'message' => 'Payment link is being created and will be sent via email shortly.'
            ]
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
            'currency' => 'nullable|string|max:10|in:MAD,EUR,USD',
            'services' => 'array',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.quantity' => 'required|integer|min:1',
            'services.*.tax' => 'nullable|numeric',
            'services.*.individual_total' => 'nullable|numeric',
            'subscriptions' => 'nullable|array',
            'subscriptions.*.plan_id' => 'required|exists:plans,id',
            'subscriptions.*.billing_cycle' => 'required|in:monthly,yearly',
            'subscriptions.*.price_snapshot' => 'required|numeric',
            'has_projects' => 'nullable',
            'description' => 'nullable'
        ]);

        return DB::transaction(function () use ($quote, $validated) {
            // Load client to determine currency if not provided
            $client = \App\Models\Client::findOrFail($validated['client_id']);
            
            // Currency priority:
            // 1. Admin-provided currency (EUR/USD/MAD) - takes precedence even if client is Moroccan
            // 2. Auto-determined from client country (Morocco = MAD, else = EUR)
            // 3. Keep existing quote currency
            $currency = $validated['currency'] ?? null;
            
            if (!$currency) {
                $currency = Quotes::determineCurrency($client) ?? $quote->currency;
            }
            
            /*
            |--------------------------------------------------------------------------
            | TOTALS CALCULATION (SERVICES + SUBSCRIPTIONS)
            |--------------------------------------------------------------------------
            |
            | Use SubscriptionInvoiceService so that totals are always calculated
            | in a single place for both store() and update().
            */
            $subscriptionInvoiceService = app(\App\Services\SubscriptionInvoiceService::class);
            $totals = $subscriptionInvoiceService->calculateInvoiceTotals(
                $validated['services'] ?? [],
                $validated['subscriptions'] ?? []
            );

            $calculatedTotal = $totals['total_amount'];

            $quote->update([
                'client_id' => $validated['client_id'],
                'quotation_date' => $validated['quotation_date'],
                'status' => $validated['status'],
                'notes' => $validated['notes'],
                'total_amount' => $calculatedTotal,
                'currency' => $currency,
                'has_projects' => is_array($validated["has_projects"])
                    ? json_encode($validated["has_projects"])
                    : $validated["has_projects"],
                'description' => $validated['description']
            ]);

            // Update services if provided
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

            /*
            |--------------------------------------------------------------------------
            | SUBSCRIPTIONS
            |--------------------------------------------------------------------------
            */
            if (isset($validated['subscriptions'])) {
                // Remove existing subscriptions then recreate from payload
                $quote->quoteSubscriptions()->delete();

                foreach ($validated['subscriptions'] as $subscription) {
                    QuoteSubscription::create([
                        'quote_id' => $quote->id,
                        'plan_id' => $subscription['plan_id'],
                        'price_snapshot' => $subscription['price_snapshot'],
                        'billing_cycle' => $subscription['billing_cycle'],
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
    $request->validate([
        'attachment_file_ids' => 'nullable|array',
        'attachment_file_ids.*' => 'integer|exists:files,id',
    ]);

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
            // Dispatch job to send email with PDF
            SendQuoteEmailJob::dispatch($quote, $request->attachment_file_ids ?? []);

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

        // Log activity asynchronously
        LogActivityJob::dispatch(
            Auth::id(),
            Auth::user()->role ?? null,
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
