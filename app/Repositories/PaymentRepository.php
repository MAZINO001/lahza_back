<?php

namespace App\Repositories;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;

class PaymentRepository
{
public function getPayment()
{
    /** @var \App\Models\User */
    $user =  Auth::user();
    $payments = Payment::with(['invoice', 'user'])
        ->when($user->role === 'client', function ($query) use ($user) {
            $clientId = $user->client()->first()->id ?? 0; 
            $query->whereHas('invoice', function ($q) use ($clientId) {
                $q->where('client_id', $clientId);
            });
        })
        ->latest()
        ->get();

    return $payments;
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
public function createAdditionalPayment(Invoice $invoice, $data)
{
    return $invoice->payments()->create($data);
}

}
