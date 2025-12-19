<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\Quotes;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;


class PdfController extends Controller
{
    public function invoice($id)
    {
        $invoice = Invoice::with(['client', 'invoiceServices.service'])->findOrFail($id);
        $type = 'invoice';

        $pdf = PDF::loadView('pdf.document', compact('invoice', 'type'));

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="invoice-' . $invoice->id . '.pdf"');
    }

    public function quote($id)
    {
        $quote = Quotes::with(['client', 'services'])->findOrFail($id);
        $type = 'quote';

        $pdf = PDF::loadView('pdf.document', compact('quote', 'type'));

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="quote-' . $quote->id . '.pdf"');
    }
}
