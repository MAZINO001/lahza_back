<?php

namespace App\Repositories;

use App\Models\Invoice;
use App\Models\Payment;

class PaymentRepository
{
    public function getPayment()
    {
        // return Payment::latestOfMany('quote_id')->get();
        return Payment::with(['invoice', 'user'])->latest()->get();
    }
    public function create(array $data)
    {
        return Payment::create($data);
    }

    public function findByStripeSessionId(string $sessionId)
    {
        return Payment::where('stripe_session_id', $sessionId)->first();
    }

    public function update(Payment $payment, array $data)
    {
        return $payment->update($data);
    }
   public function getPaidAmount(Invoice $invoice)
{
    return $invoice->payments()
                   ->where('status', 'paid')
                   ->sum('amount');
}

public function getInvoicePayments(Invoice $invoice)
{
    return $invoice->payments()->get();
}
}
