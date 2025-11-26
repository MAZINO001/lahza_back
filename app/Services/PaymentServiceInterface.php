<?php

namespace App\Services;

use App\Models\Quotes;
use App\Models\Payment;
interface PaymentServiceInterface
{
    public function createPaymentLink(Quotes $quote): array;
    public function handleStripeWebhook(array $payload, string $sigHeader);
    public function updatePendingPayment(Payment $payment, float $percentage);
}
