<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\File;
use App\Mail\SendReportMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

class SendInvoiceEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoice;
    public $paymentResponse;
    public $attachmentFileIds;

    /**
     * Create a new job instance.
     */
    public function __construct(Invoice $invoice, $paymentResponse = null, array $attachmentFileIds = [])
    {
        $this->invoice = $invoice;
        $this->paymentResponse = $paymentResponse;
        $this->attachmentFileIds = $attachmentFileIds;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $clientEmail = $this->invoice->client->user->email ?? null;

            if (!$clientEmail) {
                Log::warning('Cannot send invoice email: client has no email address', [
                    'invoice_id' => $this->invoice->id,
                    'client_id' => $this->invoice->client_id,
                ]);
                return;
            }

            // Check email notifications preference
            if (!$this->invoice->client->user->allowsMail('invoices')) {
                Log::info('Invoice email not sent: email notifications disabled', [
                    'invoice_id' => $this->invoice->id,
                    'client_id' => $this->invoice->client_id,
                ]);
                return;
            }

            // Load relationships
            $this->invoice->load('invoiceServices', 'files', 'client.user');

            // Generate PDF for the invoice
            $reportsDir = storage_path('app/reports');
            if (!file_exists($reportsDir)) {
                mkdir($reportsDir, 0777, true);
            }

            $adminSignatureBase64 = null;
            $clientSignatureBase64 = null;
            if ($this->invoice->adminSignature()) {
                $adminSignatureBase64 = $this->getImageBase64($this->invoice->adminSignature()->path);
            } else {
                $adminSignatureBase64 = $this->getDefaultAdminSignatureBase64();
            }
            if ($this->invoice->clientSignature()) {
                $clientSignatureBase64 = $this->getImageBase64($this->invoice->clientSignature()->path);
            }

            // Calculate totals for PDF
            $totalHT = 0;
            $totalTVA = 0;
            $totalTTC = 0;

            foreach ($this->invoice->invoiceServices ?? [] as $line) {
                $ttc = (float) ($line->individual_total ?? 0);
                $rate = ((float) ($line->tax ?? 0)) / 100;

                $ht = $rate > 0 ? $ttc / (1 + $rate) : $ttc;
                $tva = $ttc - $ht;

                $totalHT += $ht;
                $totalTVA += $tva;
                $totalTTC += $ttc;
            }

            $currency = $this->invoice->currency ?? $this->invoice->client->currency ?? 'MAD';

            // Detect language from URL parameter (lang=eng or default to fr)
            $lang = request()->get('lang', 'fr');
            $locale = ($lang === 'eng' || $lang === 'en') ? 'en' : 'fr';
            app()->setLocale($locale);

            $pdf = PDF::loadView('pdf.document', [
                'invoice' => $this->invoice,
                'type' => 'invoice',
                'totalHT' => $totalHT,
                'totalTVA' => $totalTVA,
                'totalTTC' => $totalTTC,
                'currency' => $currency,
                'adminSignatureBase64' => $adminSignatureBase64,
                'clientSignatureBase64' => $clientSignatureBase64,
            ]);

            $fullPdfPath = $reportsDir . DIRECTORY_SEPARATOR . uniqid() . '_invoice_' . $this->invoice->id . '.pdf';
            $pdf->save($fullPdfPath);

            if (!file_exists($fullPdfPath)) {
                Log::error('PDF generation failed for invoice email', [
                    'invoice_id' => $this->invoice->id,
                    'expected_path' => $fullPdfPath
                ]);
                return;
            }

            Log::info('Invoice PDF generated successfully', [
                'invoice_id' => $this->invoice->id,
                'pdf_path' => $fullPdfPath,
                'file_exists' => file_exists($fullPdfPath),
                'file_size' => filesize($fullPdfPath)
            ]);

            $invoiceNumber = 'INVOICE-' . str_pad($this->invoice->id, 6, '0', STR_PAD_LEFT);
            
            $data = [
                'quote' => $this->invoice->quote ?? null,
                'invoice' => $this->invoice,
                'client' => $this->invoice->client,
                'client_id' => $this->invoice->client_id,
                'payment_url' => $this->paymentResponse['payment_url'] ?? null,
                'bank_info' => $this->paymentResponse['bank_info'] ?? null,
                'payment_method' => $this->paymentResponse['payment_method'] ?? 'bank',
                'subject' => 'New Invoice Created - ' . $invoiceNumber,
            ];

            // Start with the invoice PDF (THIS MUST ALWAYS BE FIRST AND PRESENT)
            $attachmentPaths = [$fullPdfPath];

            // Add additional attachments if provided
            if (!empty($this->attachmentFileIds)) {
                $attachmentFiles = File::whereIn('id', $this->attachmentFileIds)->get();
                
                Log::info('Processing additional attachments', [
                    'requested_ids' => $this->attachmentFileIds,
                    'found_count' => $attachmentFiles->count()
                ]);
                
                foreach ($attachmentFiles as $file) {
                    // Build the absolute path correctly based on disk type
                    $filePath = Storage::disk($file->disk)->path($file->path);
                    
                    if (file_exists($filePath)) {
                        $attachmentPaths[] = $filePath;
                        Log::info('Additional attachment added', [
                            'file_id' => $file->id,
                            'path' => $filePath,
                            'exists' => true
                        ]);
                    } else {
                        Log::warning('Additional attachment file not found', [
                            'file_id' => $file->id,
                            'path' => $filePath,
                            'disk' => $file->disk
                        ]);
                    }
                }
            }

            Log::info('Final attachment list for email', [
                'invoice_id' => $this->invoice->id,
                'total_attachments' => count($attachmentPaths),
                'paths' => $attachmentPaths
            ]);

            // Send email with all attachments
            Mail::to($clientEmail)->send(new SendReportMail([
                'subject' => $data['subject'],
                'message' => 'Please find your invoice attached.',
                'name' => $this->invoice->client->name ?? 'Customer',
                'client_id' => $this->invoice->client_id,
            ], $attachmentPaths));

            Log::info('Invoice email sent successfully', [
                'invoice_id' => $this->invoice->id,
                'client_id' => $this->invoice->client_id,
                'email' => $clientEmail,
                'attachment_count' => count($attachmentPaths)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send invoice email', [
                'invoice_id' => $this->invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
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
}
