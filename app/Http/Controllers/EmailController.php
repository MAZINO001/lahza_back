<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use App\Mail\SendReportMail;
use App\Models\Invoice;
use App\Models\Quotes;

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
            $invoice = Invoice::with(['client', 'invoiceServices.service'])->findOrFail($id);
            $pdf = PDF::loadView('pdf.document', [
                'invoice' => $invoice,
                'type' => 'invoice',
            ]);
            $recipientName = optional($invoice->client)->name ?? 'Customer';
            $attachmentName = 'invoice-' . $invoice->id . '.pdf';
        } else {
            $quote = Quotes::with(['client', 'services'])->findOrFail($id);
            $pdf = PDF::loadView('pdf.document', [
                'quote' => $quote,
                'type' => 'quote',
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
}
