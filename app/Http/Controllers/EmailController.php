<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use App\Mail\SendReportMail;
use App\Models\Invoice;
use App\Models\Quotes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EmailController extends Controller
{
    public function sendEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'type' => 'required|in:invoice,quote',
            'id' => 'required|integer',
            'subject' => 'nullable|string|max:255',
            'message' => 'nullable|string',
        ]);

        $reportsDir = storage_path('app/reports');
        if (!file_exists($reportsDir)) {
            mkdir($reportsDir, 0777, true);
        }

        $type = $validated['type'];
        $id = $validated['id'];

        if ($type === 'invoice') {
            $invoice = Invoice::with(['client', 'invoiceServices.service', 'files'])->findOrFail($id);

            $adminSignatureBase64 = null;
            $clientSignatureBase64 = null;
            if ($invoice->adminSignature()) {
                $adminSignatureBase64 = $this->getImageBase64($invoice->adminSignature()->path);
            }
            if ($invoice->clientSignature()) {
                $clientSignatureBase64 = $this->getImageBase64($invoice->clientSignature()->path);
            }

            $pdf = PDF::loadView('pdf.document', [
                'invoice' => $invoice,
                'type' => 'invoice',
                'adminSignatureBase64' => $adminSignatureBase64,
                'clientSignatureBase64' => $clientSignatureBase64,
            ]);
            $recipientName = optional($invoice->client)->name ?? 'Customer';
            $attachmentName = 'invoice-' . $invoice->id . '.pdf';
        } else {
            $quote = Quotes::with(['client', 'files', 'quoteServices.service'])->findOrFail($id);

            $adminSignatureBase64 = null;
            $clientSignatureBase64 = null;
            if ($quote->adminSignature()) {
                $adminSignatureBase64 = $this->getImageBase64($quote->adminSignature()->path);
            }
            if ($quote->clientSignature()) {
                $clientSignatureBase64 = $this->getImageBase64($quote->clientSignature()->path);
            }

            $pdf = PDF::loadView('pdf.document', [
                'quote' => $quote,
                'type' => 'quote',
                'adminSignatureBase64' => $adminSignatureBase64,
                'clientSignatureBase64' => $clientSignatureBase64,
            ]);
            $recipientName = optional($quote->client)->name ?? 'Customer';
            $attachmentName = 'quote-' . $quote->id . '.pdf';
        }

        $fullPdfPath = $reportsDir . '/' . uniqid() . '.pdf';
        $pdf->save($fullPdfPath);

        if (!file_exists($fullPdfPath)) {
            return response()->json(['error' => 'PDF generation failed'], 500);
        }

        $mailData = [
            'subject' => $validated['subject'] ?? ($type === 'invoice' ? 'Your Invoice' : 'Your Quote'),
            'message' => $validated['message'] ?? 'Please find the attached document.',
            'name' => $recipientName,
        ];

        // Send email with attachment
        Mail::to($validated['email'])->send(new SendReportMail($mailData, $fullPdfPath));

        // Clean up temp file
        if (file_exists($fullPdfPath)) {
            unlink($fullPdfPath);
        }

        return response()->json([
            'message' => 'Email sent successfully to ' . $validated['email'],
            'type' => $type,
            'id' => $id,
            'attachment' => $attachmentName,
        ], 200);
    }

    /**
     * Convert image to base64 data URI
     */
    private function getImageBase64($path)
    {
        try {
            if (Storage::disk('public')->exists($path)) {
                $imageData = Storage::disk('public')->get($path);
                $mimeType = Storage::disk('public')->mimeType($path);
                return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
            }
        } catch (\Exception $e) {
            Log::error('Error converting image to base64: ' . $e->getMessage());
        }

        return null;
    }
}
