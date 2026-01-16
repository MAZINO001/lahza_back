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
     * Download receipt as PDF for a specific payment
     */
    public function receipt($id)
    {
        $user = Auth::user();
        $payment = Payment::with(['user', 'invoice', 'invoice.invoiceServices', 'invoice.client', 'files'])
            ->findOrFail($id);

        // 🔐 AUTHORIZATION CHECK
        $this->authorizeReceiptAccess($user, $payment);

        $companyInfo = CompanyInfo::first();
        $invoice = $payment->invoice;

        if (!$invoice) {
            abort(404, 'No invoice found for this payment.');
        }

        // Calculate items based on the PAYMENT PERCENTAGE, not the full invoice
        $paymentPercentage = $payment->percentage ?? 100;

        $receipt = [
            'invoice' => $invoice,
            'payment' => $payment,
            'client' => $invoice->client ?? $payment->user,
            'date' => $payment->updated_at?->format('F d, Y') ?? now()->format('F d, Y'),
            'customer_name' => $payment->user->name ?? 'unknown',
            'customer_email' => $payment->user->email ?? 'unknown',
            'items' => $this->formatInvoiceItemsForPayment($invoice, $paymentPercentage),
            'subtotal' => $this->calculatePaymentSubtotal($invoice, $paymentPercentage),
            'tax' => $this->calculatePaymentTax($invoice, $paymentPercentage),
            'tax_rate' => $this->getAverageTaxRate($invoice),
            'total' => $payment->amount ?? $payment->total, // Use the actual payment amount
            'payment_method' => $this->formatPaymentMethod($payment),
            'transaction_id' => $payment->stripe_payment_intent_id ?? $payment->id ?? 'N/A',
            'currency' => $payment->currency ?? $invoice->currency ?? 'MAD',
            'companyInfo' => $companyInfo,
            'payment_percentage' => $paymentPercentage, // Pass percentage to view
        ];

        $pdf = PDF::loadView('pdf.receipt', compact('receipt'));

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="receipt-payment-' . $payment->id . '.pdf"');
    }

    /**
     * Authorize user access to receipt
     */
    protected function authorizeReceiptAccess($user, $payment): void
    {
        if ($user->role === 'admin') {
            return;
        }

        if ($user->role !== 'client' || $payment->client_id != $user->id) {
            abort(403, 'You are not allowed to download this receipt.');
        }
    }

    /**
     * Calculate subtotal for the payment portion (HT - Before Tax)
     */
    protected function calculatePaymentSubtotal($invoice, $percentage): float
    {
        $fullSubtotal = collect($invoice->invoiceServices ?? [])->reduce(function ($total, $line) {
            $ttc = (float) ($line->individual_total ?? 0);
            $rate = ((float) ($line->tax ?? 0)) / 100;
            $ht = $rate > 0 ? $ttc / (1 + $rate) : $ttc;
            return $total + $ht;
        }, 0);

        return round($fullSubtotal * ($percentage / 100), 2);
    }

    /**
     * Calculate tax for the payment portion (TVA)
     */
    protected function calculatePaymentTax($invoice, $percentage): float
    {
        $subtotal = $this->calculatePaymentSubtotal($invoice, $percentage);
        $total = $this->calculatePaymentTotal($invoice, $percentage);

        return round($total - $subtotal, 2);
    }

    /**
     * Calculate total for the payment portion (TTC - After Tax)
     */
    protected function calculatePaymentTotal($invoice, $percentage): float
    {
        $fullTotal = collect($invoice->invoiceServices ?? [])->sum(function ($line) {
            return (float) ($line->individual_total ?? 0);
        });

        return round($fullTotal * ($percentage / 100), 2);
    }

    /**
     * Format invoice items for the payment percentage
     */
    protected function formatInvoiceItemsForPayment($invoice, $percentage): array
    {
        return collect($invoice->invoiceServices ?? [])->map(function ($line) use ($percentage) {
            $quantity = (int) ($line->quantity ?? 1);
            $totalTTC = (float) ($line->individual_total ?? 0);
            $taxRate = (float) ($line->tax ?? 0);

            // Apply percentage to the TTC amount
            $paymentTTC = $totalTTC * ($percentage / 100);
            $totalHT = $taxRate > 0 ? $paymentTTC / (1 + $taxRate / 100) : $paymentTTC;
            $unitPrice = $quantity > 0 ? $totalHT / $quantity : 0;

            return [
                'description' => $line->service->name ?? 'Service',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'total_price_ht' => round($totalHT, 2),
                'total_price_ttc' => round($paymentTTC, 2),
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
