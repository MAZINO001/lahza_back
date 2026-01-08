<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
interface PaymentServiceInterface
{
    public function getPayment();
    public function createPaymentLink(Invoice $invoice, float $payment_percentage, string $payment_status, string $payment_type): array;
    public function handleStripeWebhook(string  $payload, string $sigHeader);
    public function updatePendingPayment(Payment $payment, float $percentage, string $payment_method);
    public function getRemaining(Invoice $invoice);
    public function getInvoicePayments(Invoice $invoice);
    public function createAdditionalPayment(Invoice $invoice, float $percentage);
    public function updateInvoiceStatus(Invoice $invoice);
    public function sendPaymentSuccessEmail(Payment $payment);
    public function autoGenerateRemainingPayment(Invoice $invoice, Payment $payment): void;
}
