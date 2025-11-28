<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
interface PaymentServiceInterface
{
    public function getPayment();
    public function createPaymentLink(Invoice $invoice): array;
    public function handleStripeWebhook(string  $payload, string $sigHeader);
    public function updatePendingPayment(Payment $payment, float $percentage);
    public function getRemaining(Invoice $invoice);
    public function getInvoicePayments(Invoice $invoice);
}
