<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Mail\SendReportMail;

class EmailController extends Controller
{
    public function sendEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'name'  => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $reportsDir = storage_path('app/reports');
        if (!file_exists($reportsDir)) {
            mkdir($reportsDir, 0777, true);
        }

        $name = $validated['name'];
        $items = $validated['items'];

        // Calculate grand total
        $grandTotal = collect($items)->sum(function ($item) {
            return $item['quantity'] * $item['unit_price'];
        });

        // Generate PDF
        
        $pdf = Pdf::loadView('pdf.report', [
            'name' => $name,
            'items' => $items,
            'grandTotal' => $grandTotal
        ]);




        $fullPdfPath = $reportsDir . '/' . uniqid() . '.pdf';
        $pdf->save($fullPdfPath);

        if (!file_exists($fullPdfPath)) {
            return response()->json(['error' => 'PDF generation failed'], 500);
        }

        Mail::to($validated['email'])->send(new SendReportMail($validated, $fullPdfPath));

        if (file_exists($fullPdfPath)) {
            unlink($fullPdfPath);
        }

        return response()->json([

            'message' => 'Email sent successfully to ' . $validated['email']
        ], 200);
    }
}
