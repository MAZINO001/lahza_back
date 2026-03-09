<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceService;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\PaymentServiceInterface;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\ProjectCreationService;
use App\Services\ActivityLoggerService;
use App\Mail\InvoiceCreatedMail;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use App\Models\InvoiceSubscription;
use App\Models\File;
use App\Jobs\SendInvoiceEmailJob;
use App\Jobs\CreatePaymentLinkJob;
use App\Jobs\LogActivityJob;
use App\Jobs\CreateDraftProjectFromInvoiceJob;
class InvoicesController extends Controller
{
    protected $paymentService;
    protected $projectCreationService;
    protected $activityLogger;

    public function __construct(
        PaymentServiceInterface $paymentService,
        ProjectCreationService $projectCreationService,
        ActivityLoggerService $activityLogger
    ) {
        $this->paymentService = $paymentService;
        $this->projectCreationService = $projectCreationService;
        $this->activityLogger = $activityLogger;
    }

    public function index()
    {
        /** @var \App\Models\User */

        $user = Auth::user();
        $invoices = Invoice::with(
            ['invoiceServices', 'client.user:id,name', 'files', 'projects'])->when($user->role === 'client', function ($query) use ($user) {
            $clientId = $user->client()->first()->id ?? 0;
            $query->where('client_id', $clientId);
        })->latest()->get();
        $allServices = Service::all();

        // Add signature URLs to each invoice
        $invoices->each(function ($invoice) {
            $invoice->admin_signature_url = asset('images/admin_signature.png');
            $clientSignature = $invoice->clientSignature();
            $invoice->client_signature_url = $clientSignature ? $clientSignature->url : null;
            $invoice->has_client_signature = $clientSignature !== null;
            $invoice->has_admin_signature = $invoice->adminSignature() !== null;
        });

        return response()->json([
            "invoices"  => $invoices,
            "services"  => $allServices
        ]);
    }

public function store(Request $request)
{
    $this->authorize('create', Invoice::class);

    $validate = $request->validate([
        'client_id' => 'required|exists:clients,id',
        'quote_id' => 'nullable|exists:quotes,id',
        'invoice_date' => 'required|date',
        'due_date' => 'required|date',
        'status' => ['required', Rule::in(['draft', 'sent', 'unpaid', 'partially_paid', 'paid', 'overdue'])],
        'notes' => 'nullable|string',
        'total_amount' => 'required|numeric',
        'balance_due' => 'required|numeric',
        'currency' => 'nullable|string|max:10|in:MAD,EUR,USD',
        'payment_percentage' => 'nullable|numeric',
        'payment_status' => 'string',
        'payment_type' => 'string',
        'services' => 'nullable|array',
        'services.*.service_id' => 'required|exists:services,id',
        'services.*.quantity' => 'required|integer|min:1',
        'services.*.tax' => 'nullable|numeric',
        'services.*.individual_total' => 'nullable|numeric',
        'subscriptions' => 'nullable|array',
        'subscriptions.*.plan_id' => 'required|exists:plans,id',
        'subscriptions.*.billing_cycle' => 'required|in:monthly,yearly,quarterly',
        'subscriptions.*.price_snapshot' => 'required|numeric',
        'has_projects' => 'nullable',
        'old_projects' => 'nullable|array',
        'old_projects.*' => 'exists:projects,id',
        'description' => 'nullable|string',
        'attachment_file_ids' => 'nullable|array',
        'attachment_file_ids.*' => 'integer|exists:files,id',
    ]);

    return DB::transaction(function () use ($validate) {
        // Load client to determine currency if not provided
        $client = \App\Models\Client::findOrFail($validate['client_id']);
        
        // Currency priority: 
        // 1. Admin-provided currency (EUR/USD/MAD) - takes precedence even if client is Moroccan
        // 2. Quote currency (if quote_id exists)
        // 3. Auto-determined from client country (Morocco = MAD, else = EUR)
        $currency = $validate['currency'] ?? null;
        
        if (!$currency && !empty($validate['quote_id'])) {
            $quote = \App\Models\Quotes::find($validate['quote_id']);
            if ($quote && $quote->currency) {
                $currency = $quote->currency;
            }
        }
        
        if (!$currency) {
            $currency = Invoice::determineCurrency($client);
        }

        // Recalculate totals from services + subscriptions to avoid mismatches
        $subscriptionInvoiceService = app(\App\Services\SubscriptionInvoiceService::class);
        $totals = $subscriptionInvoiceService->calculateInvoiceTotals(
            $validate['services'] ?? [],
            $validate['subscriptions'] ?? []
        );

        $calculatedTotal = $totals['total_amount'];

        $invoice = Invoice::create([
            'client_id' => $validate["client_id"],
            'quote_id' => $validate["quote_id"] ?? null,
            'invoice_date' => $validate["invoice_date"],
            'due_date' => $validate["due_date"],
            'status' => $validate["status"],
            'notes' => $validate["notes"],
            'total_amount' => $calculatedTotal,
            'balance_due' => $calculatedTotal,
            'description' => $validate["description"] ?? null,
            'currency' => $currency,
            'has_projects' => is_array($validate["has_projects"])
                ? json_encode($validate["has_projects"])
                : $validate["has_projects"],
        ]);

        // Add services
        if (!empty($validate["services"])) {
            foreach ($validate["services"] as $service) {
                InvoiceService::create([
                    'invoice_id' => $invoice->id,
                    'service_id' => $service['service_id'],
                    'quantity' => $service['quantity'],
                    'tax' => $service['tax'] ?? 0,
                    'individual_total' => $service['individual_total'] ?? 0,
                ]);
            }
        }

        // Add subscriptions
        if (!empty($validate['subscriptions'])) {
            foreach ($validate['subscriptions'] as $subscription) {
                InvoiceSubscription::create([
                    'invoice_id' => $invoice->id,
                    'plan_id' => $subscription['plan_id'],
                    'subscription_id' => null, // Will be set after payment
                    'price_snapshot' => $subscription['price_snapshot'],
                    'billing_cycle' => $subscription['billing_cycle'],
                ]);
            }
        }

        if (!empty($validate['old_projects'])) {
            $invoice->projects()->sync($validate['old_projects']);
        }

        // Auto-sign with admin signature
        $this->autoSignAdminSignature($invoice);

        // Load relationships
        $invoice->load('client', 'files', 'invoiceServices', 'invoiceSubscriptions.plan');

        // Add signature URLs to the response
        $invoice->admin_signature_url = asset('images/admin_signature.png');
        $clientSignature = $invoice->clientSignature();
        $invoice->client_signature_url = $clientSignature ? $clientSignature->url : null;
        $invoice->has_client_signature = $clientSignature !== null;
        $invoice->has_admin_signature = $invoice->adminSignature() !== null;

        // ONLY create payment and send email if status is NOT 'draft'
        if ($validate["status"] !== 'draft') {
            // Determine composition
            $hasSubscriptions = $invoice->hasSubscriptions();
            $hasServices = $invoice->invoiceServices()->exists();

            // Interpret payment_percentage as "services percentage":
            // - services-only     → default 50%
            // - subscriptions-only→ default 100% (kept for backwards compat, though it only affects services)
            // - mixed             → default 50% for services, subscriptions always 100%
            if ($hasServices && $hasSubscriptions) {
                $paymentPercentage = $validate['payment_percentage'] ?? 50;
            } elseif ($hasSubscriptions && !$hasServices) {
                $paymentPercentage = $validate['payment_percentage'] ?? 100;
            } else {
                // services only
                $paymentPercentage = $validate['payment_percentage'] ?? 50;
            }
            
            $paymentStatus = $validate['payment_status'] ?? 'pending';
            $paymentType = $validate['payment_type'] ?? 'bank';

            // Dispatch job to create payment link and send email
            // This runs asynchronously to speed up invoice creation
            CreatePaymentLinkJob::dispatch(
                $invoice,
                $paymentPercentage,
                $paymentStatus,
                $paymentType,
                $validate['attachment_file_ids'] ?? []
            );
        }

        // Create draft project(s) for direct invoice path (PATH 2) — dispatched for instant response
        if (!$invoice->quote_id) {
            CreateDraftProjectFromInvoiceJob::dispatch($invoice);
        }

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
                'client_id' => $invoice->client_id,
                'total_amount' => $invoice->total_amount,
                'status' => $invoice->status,
                'has_subscriptions' => $invoice->hasSubscriptions(),
                'url' => request()->fullUrl()
            ],
            "Invoice #{$invoice->id} created for client #{$invoice->client_id} with total: {$invoice->total_amount}"
        );

        return response()->json([
            $invoice->load("invoiceServices", "invoiceSubscriptions.plan", "projects"),
            'invoice_id' => $invoice->id
        ], 201);
    });
}

public function sendInvoice(Request $request, Invoice $invoice)
{
    $this->authorize('update', $invoice);

    // Ensure invoice is actually a draft
    if ($invoice->status !== 'draft') {
        return response()->json([
            'status' => 'error',
            'message' => "Cannot send invoice: Invoice #{$invoice->id} is not a draft (current status: {$invoice->status})"
        ], 400);
    }

    $validate = $request->validate([
        'payment_percentage' => 'required|numeric|min:0|max:100',
        'payment_status' => 'nullable|string|in:unpaid,partially_paid,paid,pending',
        'payment_type' => 'required|string|in:stripe,bank,cash,cheque',
        'attachment_file_ids' => 'nullable|array',
        'attachment_file_ids.*' => 'integer|exists:files,id',
    ]);

    return DB::transaction(function () use ($invoice, $validate) {
        
        // Update invoice status from 'draft' to 'sent'
        $invoice->update([
            'status' => 'sent',
        ]);

        // Create payment link
        $paymentPercentage = $validate['payment_percentage'];
        $paymentStatus = $validate['payment_status'] ?? 'pending';
        $paymentType = $validate['payment_type'];

        // Dispatch job to create payment link and send email
        // This runs asynchronously to speed up invoice sending
        CreatePaymentLinkJob::dispatch(
            $invoice,
            $paymentPercentage,
            $paymentStatus,
            $paymentType,
            $validate['attachment_file_ids'] ?? []
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
                'client_id' => $invoice->client_id,
                'total_amount' => $invoice->total_amount,
                'status' => $invoice->status,
                'payment_percentage' => $paymentPercentage,
                'payment_type' => $paymentType,
                'url' => request()->fullUrl()
            ],
            "Invoice #{$invoice->id} sent to client #{$invoice->client_id}"
        );

        return response()->json([
            'message' => 'Invoice sent successfully. Payment link is being created and will be sent via email.',
            'invoice' => $invoice->load("invoiceServices", "projects", "client", "payments"),
            'payment_info' => [
                'status' => 'processing',
                'message' => 'Payment link is being created and will be sent via email shortly.'
            ]
        ], 200);
    });
}

    public function show($id)
    {
        $invoice = Invoice::with(['invoiceServices', 'client.user:id,name', 'files', 'projects'])->findOrFail($id);

        // Add signature URLs to the response
        $invoice->admin_signature_url = asset('images/admin_signature.png');
        $clientSignature = $invoice->clientSignature();
        $invoice->client_signature_url = $clientSignature ? $clientSignature->url : null;
        $invoice->has_client_signature = $clientSignature !== null;
        $invoice->has_admin_signature = $invoice->adminSignature() !== null;

        return response()->json($invoice);
    }

 public function update(Request $request, $id)
{
    $invoice = Invoice::findOrFail($id);
    $this->authorize('update', $invoice);

    $oldStatus = $invoice->status;

    $validate = $request->validate([
        'client_id' => 'required|exists:clients,id',
        'quote_id' => 'nullable|exists:quotes,id',
        'invoice_date' => 'required|date',
        'due_date' => 'required|date',
        'status' => ['required', Rule::in(['draft','sent','unpaid','partially_paid','paid','overdue'])],
        'notes' => 'nullable|string',
        'total_amount' => 'required|numeric',
        'balance_due' => 'required|numeric',
        'currency' => 'nullable|string|max:10|in:MAD,EUR,USD',
        'services' => 'array',
        'services.*.service_id' => 'required|exists:services,id',
        'services.*.quantity' => 'required|integer|min:1',
        'services.*.tax' => 'nullable|numeric',
        'services.*.individual_total' => 'nullable|numeric',
        'has_projects' => 'nullable',

        'subscriptions' => 'nullable|array',
        'subscriptions.*.plan_id' => 'required|exists:plans,id',
        'subscriptions.*.billing_cycle' => 'required|in:monthly,yearly,quarterly',
        'subscriptions.*.price_snapshot' => 'required|numeric',
        'old_projects' => 'nullable|array',
        'old_projects.*' => 'exists:projects,id',
        'description' => 'nullable|string',
        'attachment_file_ids' => 'nullable|array',
        'attachment_file_ids.*' => 'integer|exists:files,id',

    ]);

    return DB::transaction(function () use ($invoice, $validate, $oldStatus) {
        // Load client to determine currency if not provided
        $client = \App\Models\Client::findOrFail($validate['client_id']);
        
        // Currency priority:
        // 1. Admin-provided currency (EUR/USD/MAD) - takes precedence even if client is Moroccan
        // 2. Auto-determined from client country (Morocco = MAD, else = EUR)
        // 3. Keep existing invoice currency
        $currency = $validate['currency'] ?? null;
        
        if (!$currency) {
            $currency = Invoice::determineCurrency($client) ?? $invoice->currency;
        }

        /*
        |--------------------------------------------------------------------------
        | TOTALS CALCULATION (SERVICES + SUBSCRIPTIONS)
        |--------------------------------------------------------------------------
        |
        | Keep the calculation logic in the SubscriptionInvoiceService so that
        | both store() and update() share the same source of truth.
        | Frontend-provided totals are ignored in favour of backend calculation.
        */
        $subscriptionInvoiceService = app(\App\Services\SubscriptionInvoiceService::class);
        $totals = $subscriptionInvoiceService->calculateInvoiceTotals(
            $validate['services'] ?? [],
            $validate['subscriptions'] ?? []
        );

        $calculatedTotal = $totals['total_amount'];

        $invoice->update([
            'client_id' => $validate['client_id'],
            'quote_id' => $validate['quote_id'] ?? null,
            'invoice_date' => $validate['invoice_date'],
            'due_date' => $validate['due_date'],
            'status' => $validate['status'],
            'notes' => $validate['notes'] ?? null,
            'total_amount' => $calculatedTotal,
            // Keep balance_due behaviour as-is (driven by request / existing flows)
            'balance_due' => $validate['balance_due'],
            'description' => $validate['description'] ?? null,
            'currency' => $currency,
        ]);

        /*
        |--------------------------------------------------------------------------
        | SERVICES
        |--------------------------------------------------------------------------
        */
        if (isset($validate['services'])) {
            $invoice->invoiceServices()->delete();

            foreach ($validate['services'] as $service) {
                InvoiceService::create([
                    'invoice_id' => $invoice->id,
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
        if (isset($validate['subscriptions'])) {

            $invoice->invoiceSubscriptions()->delete();

            foreach ($validate['subscriptions'] as $subscription) {
                InvoiceSubscription::create([
                    'invoice_id' => $invoice->id,
                    'plan_id' => $subscription['plan_id'],
                    'price_snapshot' => $subscription['price_snapshot'],
                    'billing_cycle' => $subscription['billing_cycle'],
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | PROJECT SYNC
        |--------------------------------------------------------------------------
        */
        if (isset($validate['old_projects'])) {
            $invoice->projects()->sync($validate['old_projects']);
        }

        /*
        |--------------------------------------------------------------------------
        | STATUS TRANSITION LOGIC
        |--------------------------------------------------------------------------
        */
        if ($oldStatus === 'draft' && $invoice->status === 'sent') {
            // Dispatch job to send email with PDF
            SendInvoiceEmailJob::dispatch($invoice, null);
        }

        return response()->json(
            $invoice->load('invoiceServices','invoiceSubscriptions.plan','projects')
        );
    });
}
    public function destroy($id)
    {
        $this->authorize('delete', Invoice::class);
        
        $invoice = Invoice::findOrFail($id);

        // Delete associated projects
        $this->projectCreationService->deleteProjectsByInvoice($id);

        // Delete the invoice
        $invoice->delete();

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
    public function getInvoiceProjects(){
        return Invoice::doesnthave('projects')->get()->toArray();
    }
}
