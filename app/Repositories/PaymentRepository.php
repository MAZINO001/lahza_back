<?php

namespace App\Repositories;

use App\Models\Payment;

class PaymentRepository
{
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
}
