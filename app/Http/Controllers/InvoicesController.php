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
        $invoices = Invoice::with(['invoiceServices', 'client.user:id,name', 'files'])->get();
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
        $validate = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'quote_id' => 'nullable|exists:quotes,id',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date',
            'status' => ['required', Rule::in(['draft', 'sent', 'unpaid', 'partially_paid', 'paid', 'overdue'])],
            'notes' => 'nullable|string',
            'total_amount' => 'required|numeric',
            'balance_due' => 'required|numeric',
            'payment_percentage'=>'numeric',
            'payment_status'=>'string',
            'payment_type'=> 'string',
            'services' => 'array',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.quantity' => 'required|integer|min:1',
            'services.*.tax' => 'nullable|numeric',
            'services.*.individual_total' => 'nullable|numeric',
            'has_projects'=>'nullable',
        ]);

        return  DB::transaction(function () use ($validate) {

            $invoice = Invoice::create([
                'client_id' => $validate["client_id"],
                'quote_id' => $validate["quote_id"] ?? null,
                'invoice_date' => $validate["invoice_date"],
                'due_date' => $validate["due_date"],
                'status' => $validate["status"],
                'notes' => $validate["notes"],
                'total_amount' => $validate["total_amount"],
                'balance_due' => $validate["total_amount"],
                'has_projects' => is_array($validate["has_projects"])
                    ? json_encode($validate["has_projects"])
                    : $validate["has_projects"],
            ]);
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

            // Auto-sign with admin signature
            $this->autoSignAdminSignature($invoice);

            // Add signature URLs to the response
            $invoice->load('client', 'files');
            $invoice->admin_signature_url = asset('images/admin_signature.png');
            $clientSignature = $invoice->clientSignature();
            $invoice->client_signature_url = $clientSignature ? $clientSignature->url : null;
            $invoice->has_client_signature = $clientSignature !== null;
            $invoice->has_admin_signature = $invoice->adminSignature() !== null;
            logger("teeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeessssssssssssssssssssstttttt" );
     
            $paymentPercentage = $validate['payment_percentage'] ?? 0;
            $paymentStatus = $validate['payment_status'] ?? 'unpaid';
            $paymentType = $validate['payment_type'] ?? 'bank';
            
            $response = $this->paymentService->createPaymentLink($invoice, $paymentPercentage, $paymentStatus, $paymentType);
        
            $email = 'mangaka.wir@gmail.com';
            
            // Generate PDF for the invoice
            $reportsDir = storage_path('app/reports');
            if (!file_exists($reportsDir)) {
                mkdir($reportsDir, 0777, true);
            }
            
            $pdf = app('snappy.pdf');
            $html = view('emails.invoice_created', [
                'quote' => $invoice->quote ?? null,
                'invoice' => $invoice,
                'client' => $invoice->client,
                'payment_url' => $response['payment_url'],
                'bank_info' => $response['bank_info'],
                'payment_method' => $response['payment_method']
            ])->render();
            
            $pdfPath = $reportsDir . '/' . uniqid() . '.pdf';
            $pdf->generateFromHtml($html, $pdfPath);
            
            // Prepare mail data with client_id
            $mailData = [
                'subject' => 'New Invoice Created - ' . $invoice->id,
                'message' => 'Please find your invoice attached.',
                'name' => $invoice->client->name ?? 'Customer',
                'client_id' => $invoice->client_id,
            ];
            
            // Send email using SendReportMail
            Mail::to($email)->send(new \App\Mail\SendReportMail($mailData, $pdfPath));
            
            // Clean up the temporary PDF
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }
            // app(ProjectCreationService::class)->createProjectForInvoice($invoice);
                        $this->projectCreationService->createDraftProject($invoice);
            // Log the activity with all relevant details
            $this->activityLogger->log(
                'invoice_created_from_quote',
                'invoices',
                $invoice->id, // Using invoice ID as record_id for direct reference
                request()->ip(),
                request()->userAgent()
            );

            // Store additional details in the activity log
            $this->activityLogger->log(
                'invoice_details',
                'invoices',
                // $invoice->client->id,
                1111,
                request()->ip(),
                request()->userAgent(),
                [
                    'invoice_id' => $invoice->id,
                    'quote_id' => $quote->id ?? null,
                    'client_id' => $invoice->client_id,
                    'total_amount' => $invoice->total_amount,
                    'url' => request()->fullUrl()
                ]
            );

    return response()->json([$invoice->load("invoiceServices"), 'invoice_id' => $invoice->id], 201);
        });
    }

    public function show($id)
    {
        $invoice = Invoice::with(['invoiceServices', 'client.user:id,name', 'files'])->findOrFail($id);
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
        $validate = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'quote_id' => 'nullable|exists:quotes,id',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date',
            'status' => ['required', Rule::in(['draft', 'sent', 'unpaid', 'partially_paid', 'paid', 'overdue'])],
            'notes' => 'nullable|string',
            'total_amount' => 'required|numeric',
            'balance_due' => 'required|numeric',
            'services' => 'array',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.quantity' => 'required|integer|min:1',
            'services.*.tax' => 'nullable|numeric',
            'services.*.individual_total' => 'nullable|numeric',
            'has_projects'=>'nullable',
        ]);

        return DB::transaction(function () use ($invoice, $validate) {
            $invoice->update([
                'client_id' => $validate['client_id'],
                'quote_id' => $validate["quote_id"] ?? null,
                'invoice_date' => $validate['invoice_date'],
                'due_date' => $validate['due_date'],
                'status' => $validate['status'],
                'notes' => $validate['notes'] ?? null,
                'total_amount' => $validate['total_amount'],
                'balance_due' => $validate['balance_due'],
                'has_projects' => is_array($validate["has_projects"])
                    ? json_encode($validate["has_projects"])
                    : $validate["has_projects"],
            ]);

            if (!empty($validate['services'])) {
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
            
            // $this->projectCreationService->createDraftProject($invoice);
            return response()->json($invoice->load('invoiceServices'));
        });
    }


    public function destroy($id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->delete();
        return response()->json(null, 204);
    }

    /**
     * Automatically sign with admin signature by copying default admin signature image
     */
    /**
     * Determines the client device from the request user-agent for logging.
     *
     * @param string $userAgent The User-Agent string from the request
     * @return string The detected device type ('Mobile', 'Tablet', or 'Desktop')
     */
    // Device detection has been moved to ActivityLoggerService

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
