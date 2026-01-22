<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\Quotes;
use App\Models\CompanyInfo;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
class PdfController extends Controller
{
    public function invoice($id)
    {
        $user = Auth::user();

        $invoice = Invoice::with(['client', 'invoiceServices.service', 'files'])->findOrFail($id);
     // 🔐 AUTHORIZATION CHECK
        if ($user->role !== 'admin') {
            // must be client AND owner of this invoice
            if (
                $user->role !== 'client' ||
                !$user->client ||
                $invoice->client_id !== $user->client->id
            ) {
                abort(403, 'You are not allowed to view this invoice.');
            }
        }
        $type = 'invoice';

        // totals
        $totalHT = 0;
        $totalTVA = 0;
        $totalTTC = 0;

       foreach ($invoice->invoiceServices ?? [] as $line) {
            $quantity = (float) ($line->quantity ?? 1);
            $ttcPerUnit = (float) ($line->individual_total ?? 0);
            $rate = ((float) ($line->tax ?? 0)) / 100;

            // Calculate HT per unit
            $htPerUnit = $rate > 0 ? $ttcPerUnit / (1 + $rate) : $ttcPerUnit;
            $tvaPerUnit = $ttcPerUnit - $htPerUnit;

            // Multiply by quantity
            $ht = $htPerUnit * $quantity;
            $tva = $tvaPerUnit * $quantity;
            $ttc = $ttcPerUnit * $quantity;

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

     $companyInfo = CompanyInfo::first();

    $pdf = PDF::loadView(
        'pdf.document',
        compact(
            'invoice',
            'type',
            'totalHT',
            'totalTVA',
            'totalTTC',
            'currency',
            'clientSignatureBase64',
            'companyInfo'  // ← ADD THIS
        )
    );

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="invoice-' . $invoice->id . '.pdf"');
    }


    public function quote($id)
    {
        $user = Auth::user();
        $quote = Quotes::with(['client', 'files', 'services'])->findOrFail($id);
            $quote = Quotes::with(['client', 'files', 'services'])
        ->findOrFail($id);

    // 🔐 AUTHORIZATION CHECK
    if ($user->role !== 'admin') {
        if (
            $user->role !== 'client' ||
            !$user->client ||
            $quote->client_id !== $user->client->id
        ) {
            abort(403, 'You are not allowed to view this quote.');
        }
    }
        $type = 'quote';

        $totalHT = 0;
        $totalTVA = 0;
        $totalTTC = 0;

        foreach ($quote->quoteServices ?? [] as $line) {
        $quantity = (float) ($line->quantity ?? 1);
        $pricePerUnit = (float) (($line->individual_total ?? 0) / $quantity);
        $rate = ((float) ($line->tax ?? 0)) / 100;

        $ht = $pricePerUnit * $quantity;
        $tva = $ht * $rate;
        $ttc = $ht + $tva;

        $totalHT += $ht;
        $totalTVA += $tva;
        $totalTTC += $ttc;
    }

        $currency = $quote->client->currency ?? 'MAD';

        $clientSignatureBase64 = null;
        if ($quote->clientSignature()) {
            $clientSignatureBase64 = $this->getImageBase64($quote->clientSignature()->path);
        }

$companyInfo = CompanyInfo::first();

    $pdf = PDF::loadView(
        'pdf.document',
        compact(
            'quote',
            'type',
            'totalHT',
            'totalTVA',
            'totalTTC',
            'currency',
            'clientSignatureBase64',
            'companyInfo'
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
