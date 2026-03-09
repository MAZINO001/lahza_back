<?php

namespace App\Jobs;

use App\Models\Quotes;
use App\Models\File;
use App\Mail\QuoteCreatedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

class SendQuoteEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $quote;
    public $attachmentFileIds;

    /**
     * Create a new job instance.
     */
    public function __construct(Quotes $quote, array $attachmentFileIds = [])
    {
        $this->quote = $quote;
        $this->attachmentFileIds = $attachmentFileIds;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->quote->load('client.user', 'quoteServices', 'files');

            $clientEmail = $this->quote->client->user->email ?? null;

            if (!$clientEmail || !$this->quote->client->user->allowsMail('quotes')) {
                Log::info('Quote email not sent: no email or notifications disabled', [
                    'quote_id' => $this->quote->id,
                    'client_id' => $this->quote->client_id,
                ]);
                return;
            }

            // --- PDF GENERATION ---
            $adminSignatureBase64 = $this->quote->adminSignature() 
                ? $this->getImageBase64($this->quote->adminSignature()->path) 
                : $this->getDefaultAdminSignatureBase64();
                
            $clientSignatureBase64 = $this->quote->clientSignature() 
                ? $this->getImageBase64($this->quote->clientSignature()->path) 
                : null;

            // Totals calculation
            $totalHT = 0; 
            $totalTVA = 0; 
            $totalTTC = 0;
            foreach ($this->quote->quoteServices ?? [] as $line) {
                $ttc = (float) ($line->individual_total ?? 0);
                $rate = ((float) ($line->tax ?? 0)) / 100;
                $ht = $rate > 0 ? $ttc / (1 + $rate) : $ttc;
                $totalHT += $ht; 
                $totalTVA += ($ttc - $ht); 
                $totalTTC += $ttc;
            }

            // Detect language from URL parameter (lang=eng or default to fr)
            $lang = request()->get('lang', 'fr');
            $locale = ($lang === 'eng' || $lang === 'en') ? 'en' : 'fr';
            app()->setLocale($locale);

            $pdf = PDF::loadView('pdf.document', [
                'quote' => $this->quote,
                'type' => 'quote',
                'totalHT' => $totalHT,
                'totalTVA' => $totalTVA,
                'totalTTC' => $totalTTC,
                'currency' => $this->quote->currency ?? $this->quote->client->currency ?? 'MAD',
                'adminSignatureBase64' => $adminSignatureBase64,
                'clientSignatureBase64' => $clientSignatureBase64,
            ]);

            // --- STORAGE LOGIC ---
            // Define a relative path (relative to storage/app)
            $relativePdfPath = 'reports/quote_' . $this->quote->id . '_' . uniqid() . '.pdf';

            // Save the PDF using Storage instead of $pdf->save()
            Storage::disk('local')->put($relativePdfPath, $pdf->output());

            // Initialize the array with our absolute path
            $attachmentPaths = [Storage::disk('local')->path($relativePdfPath)];

            // Add additional attachments
            if (!empty($this->attachmentFileIds)) {
                $attachmentFiles = File::whereIn('id', $this->attachmentFileIds)->get();
                
                foreach ($attachmentFiles as $file) {
                    $attachmentPaths[] = Storage::disk($file->disk)->path($file->path);
                }
            }

            // Send the mail
            Mail::to($clientEmail)->send(
                new QuoteCreatedMail($this->quote, $attachmentPaths)
            );

            Log::info('Quote email sent successfully', [
                'quote_id' => $this->quote->id,
                'client_id' => $this->quote->client_id,
                'email' => $clientEmail,
                'attachment_count' => count($attachmentPaths)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send quote email', [
                'quote_id' => $this->quote->id,
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
