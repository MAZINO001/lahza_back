<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\Quotes;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PdfController extends Controller
{
    public function invoice($id)
    {
        $invoice = Invoice::with(['client', 'invoiceServices.service', 'files'])->findOrFail($id);
        $type = 'invoice';

        // totals
        $totalHT = 0;
        $totalTVA = 0;
        $totalTTC = 0;

        foreach ($invoice->invoiceServices ?? [] as $line) {
            $ttc = (float) ($line->individual_total ?? 0);
            $rate = ((float) ($line->tax ?? 0)) / 100;

            $ht = $rate > 0 ? $ttc / (1 + $rate) : $ttc;
            $tva = $ttc - $ht;

            $totalHT += $ht;
            $totalTVA += $tva;
            $totalTTC += $ttc;
        }

        $currency = $invoice->client->currency ?? 'MAD';

        // client signature
        $clientSignatureBase64 = null;
        if ($invoice->clientSignature()) {
            $clientSignatureBase64 = $this->getImageBase64($invoice->clientSignature()->path);
        }

        $pdf = PDF::loadView(
            'pdf.document',
            compact(
                'invoice',
                'type',
                'totalHT',
                'totalTVA',
                'totalTTC',
                'currency',
                'clientSignatureBase64'
            )
        );

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="invoice-' . $invoice->id . '.pdf"');
    }


    public function quote($id)
    {
        $quote = Quotes::with(['client', 'files', 'services'])->findOrFail($id);
        $type = 'quote';

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

        $clientSignatureBase64 = null;
        if ($quote->clientSignature()) {
            $clientSignatureBase64 = $this->getImageBase64($quote->clientSignature()->path);
        }

        $pdf = PDF::loadView(
            'pdf.document',
            compact(
                'quote',
                'type',
                'totalHT',
                'totalTVA',
                'totalTTC',
                'currency',
                'clientSignatureBase64'
            )
        );

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="quote-' . $quote->id . '.pdf"');
    }


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
