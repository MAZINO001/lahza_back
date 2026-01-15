<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\CompanyInfo;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptController extends Controller
{
    /**
     * Download receipt as PDF
     */
    public function receipt($id)
    {
        $user = Auth::user();
        $payment = Payment::with(['user', 'invoice', 'files'])
            ->findOrFail($id);

        // 🔐 AUTHORIZATION CHECK
        $this->authorizeReceiptAccess($user, $payment);

        // The $payment model instance already contains the payment data
        if (!$payment) {
            abort(404, 'No payment found for this invoice.');
        }

        $companyInfo = CompanyInfo::first();

        // Get invoice with its services
        $invoice = $payment->invoice;
        if (!$invoice) {
            abort(404, 'No invoice found for this payment.');
        }

        $receipt = [
            'invoice' => $invoice,
            'payment' => $payment,
            'client' => $invoice->client ?? $payment->user, // ✅ FIXED: Use correct client relationship
            'date' => $payment->updated_at?->format('F d, Y') ?? now()->format('F d, Y'),
            'customer_name' => $payment->user->name ?? 'unknown',
            'customer_email' => $payment->user->email ?? 'unknown',
            'items' => $this->formatInvoiceItems($invoice),
            'subtotal' => $this->calculateSubtotal($invoice),
            'tax' => $this->calculateTax($invoice),
            'tax_rate' => $this->getAverageTaxRate($invoice),
            'total' => $payment->total ?? $this->calculateTotal($invoice),
            'payment_method' => $this->formatPaymentMethod($payment),
            'transaction_id' => $payment->stripe_payment_intent_id ?? $payment->id ?? 'N/A',
            'currency' => $payment->currency ?? $invoice->currency ?? 'MAD',
            'companyInfo' => $companyInfo,
        ];

        $pdf = PDF::loadView('pdf.receipt', compact('receipt'));

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="receipt-' . $payment->id . '.pdf"');
    }

    /**
     * Authorize user access to receipt
     */
    protected function authorizeReceiptAccess($user, $payment): void
    {
        if ($user->role === 'admin') {
            return;
        }

        if ($user->role !== 'client' ||  $payment->client_id != $user->id) {
            abort(403, 'You are not allowed to download this receipt.');
        }
    }

    /**
     * Calculate subtotal (HT - Before Tax)
     */
    protected function calculateSubtotal($invoice): float
    {
        return collect($invoice->invoiceServices ?? [])->reduce(function ($total, $line) {
            $ttc = (float) ($line->individual_total ?? 0);
            $rate = ((float) ($line->tax ?? 0)) / 100;
            $ht = $rate > 0 ? $ttc / (1 + $rate) : $ttc;
            return $total + $ht;
        }, 0);
    }

    /**
     * Calculate total tax amount (TVA) - Total money that goes to taxes
     */
    protected function calculateTax($invoice): float
    {
        $subtotal = $this->calculateSubtotal($invoice);
        $total = $this->calculateTotal($invoice);

        // Tax amount = Total TTC - Total HT (before tax)
        return round($total - $subtotal, 2);
    }

    /**
     * Calculate total amount (TTC - After Tax)
     */
    protected function calculateTotal($invoice): float
    {
        return collect($invoice->invoiceServices ?? [])->sum(function ($line) {
            return (float) ($line->individual_total ?? 0);
        });
    }

    /**
     * Format invoice items for display
     * ✅ FIXED: Use individual_total directly instead of calculating from unit_price
     */
    protected function formatInvoiceItems($invoice): array
    {
        return collect($invoice->invoiceServices ?? [])->map(function ($line) {
            $quantity = (int) ($line->quantity ?? 1);
            $totalTTC = (float) ($line->individual_total ?? 0);
            $taxRate = (float) ($line->tax ?? 0);

            // Calculate HT from TTC and tax rate
            $totalHT = $taxRate > 0 ? $totalTTC / (1 + $taxRate / 100) : $totalTTC;
            $unitPrice = $quantity > 0 ? $totalHT / $quantity : 0;

            return [
                'description' => $line->service->name ?? 'Service',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'total_price_ht' => $totalHT,
                'total_price_ttc' => $totalTTC,
            ];
        })->toArray();
    }

    /**
     * Get average tax rate across all items
     */
    protected function getAverageTaxRate($invoice): float
    {
        $items = $invoice->invoiceServices ?? [];
        if (empty($items)) {
            return 0;
        }

        $totalTax = collect($items)->sum(function ($line) {
            return (float) ($line->tax ?? 0);
        });

        return round($totalTax / count($items), 2);
    }

    /**
     * Format payment method for display
     */
    protected function formatPaymentMethod($payment): string
    {
        $methods = [
            'credit_card' => 'Credit Card',
            'bank_transfer' => 'Bank Transfer',
            'cash' => 'Cash',
            'check' => 'Check',
            'stripe' => 'Credit Card (Stripe)',
        ];

        return $methods[$payment->payment_method] ?? ucfirst(str_replace('_', ' ', $payment->payment_method));
    }
}
