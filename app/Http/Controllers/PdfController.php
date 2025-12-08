<?php

// namespace App\Http\Controllers;

// use Illuminate\Http\Request;
// use App\Models\Invoice;
// use App\Models\Quotes;
// use Barryvdh\Snappy\Facades\SnappyPdf as PDF;


// class PdfController extends Controller
// {
//     public function invoice($id)
//     {
//         $invoice = Invoice::with(['client', 'invoiceServices.service'])->findOrFail($id);
//         $type = 'invoice';

//         $pdf = PDF::loadView('pdf.document', compact('invoice', 'type'));


//         return response($pdf->output(), 200)
//             ->header('Content-Type', 'application/pdf')
//             ->header('Content-Disposition', 'inline; filename="invoice-' . $invoice->id . '.pdf"');
//     }

//     public function quote($id)
//     {
//         $quote = Quotes::with(['client', 'files', 'quoteServices.service'])->findOrFail($id);
//         $type = 'quote';
//         $pdf = PDF::loadView('pdf.document', compact('quote', 'type'));

//         return response($pdf->output(), 200)
//             ->header('Content-Type', 'application/pdf')
//             ->header('Content-Disposition', 'inline; filename="quote-' . $quote->id . '.pdf"');
//     }
//        private function getImageBase64($path)
//     {
//         try {
//             if (Storage::exists($path)) {
//                 $imageData = Storage::get($path);
//                 $mimeType = Storage::mimeType($path);
//                 return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
//             }
//         } catch (\Exception $e) {
//             \Log::error('Error converting image to base64: ' . $e->getMessage());
//         }

//         return null;
//     }
// }

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

        // Convert signatures to base64
        $adminSignatureBase64 = null;
        $clientSignatureBase64 = null;

        if ($invoice->adminSignature()) {
            $adminSignatureBase64 = $this->getImageBase64($invoice->adminSignature()->path);
        } else {
            // Use default admin signature if no admin signature exists
            $adminSignatureBase64 = $this->getDefaultAdminSignatureBase64();
        }

        if ($invoice->clientSignature()) {
            $clientSignatureBase64 = $this->getImageBase64($invoice->clientSignature()->path);
        }

        $pdf = PDF::loadView('pdf.document', compact('invoice', 'type', 'adminSignatureBase64', 'clientSignatureBase64'));

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="invoice-' . $invoice->id . '.pdf"');
    }

    public function quote($id)
    {
        $quote = Quotes::with(['client', 'files', 'services'])->findOrFail($id);
        $type = 'quote';

        // Convert signatures to base64
        $adminSignatureBase64 = null;
        $clientSignatureBase64 = null;

        if ($quote->adminSignature()) {
            $adminSignatureBase64 = $this->getImageBase64($quote->adminSignature()->path);
        } else {
            // Use default admin signature if no admin signature exists
            $adminSignatureBase64 = $this->getDefaultAdminSignatureBase64();
        }

        if ($quote->clientSignature()) {
            $clientSignatureBase64 = $this->getImageBase64($quote->clientSignature()->path);
        }

        $pdf = PDF::loadView('pdf.document', compact('quote', 'type', 'adminSignatureBase64', 'clientSignatureBase64'));

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="quote-' . $quote->id . '.pdf"');
    }

    /**
     * Convert image to base64 data URI
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
