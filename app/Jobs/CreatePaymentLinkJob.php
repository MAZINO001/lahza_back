<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Quotes;
use App\Services\PaymentServiceInterface;
use App\Jobs\SendInvoiceEmailJob;
use App\Jobs\SendInvoiceCreatedFromQuoteJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreatePaymentLinkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoice;
    public $paymentPercentage;
    public $paymentStatus;
    public $paymentType;
    public $attachmentFileIds;
    public $sendInvoiceCreatedEmail;
    public $quote;

    /**
     * Create a new job instance.
     */
    public function __construct(
        Invoice $invoice,
        float $paymentPercentage,
        string $paymentStatus,
        string $paymentType,
        array $attachmentFileIds = [],
        bool $sendInvoiceCreatedEmail = false,
        ?Quotes $quote = null
    ) {
        $this->invoice = $invoice;
        $this->paymentPercentage = $paymentPercentage;
        $this->paymentStatus = $paymentStatus;
        $this->paymentType = $paymentType;
        $this->attachmentFileIds = $attachmentFileIds;
        $this->sendInvoiceCreatedEmail = $sendInvoiceCreatedEmail;
        $this->quote = $quote;
    }

    /**
     * Execute the job.
     */
    public function handle(PaymentServiceInterface $paymentService): void
    {
        try {
            // Reload invoice to ensure we have the latest data
            $this->invoice->refresh();
            $this->invoice->load('client.user', 'invoiceServices', 'invoiceSubscriptions');

            // Create payment link
            $response = $paymentService->createPaymentLink(
                $this->invoice,
                $this->paymentPercentage,
                $this->paymentStatus,
                $this->paymentType
            );

            Log::info('Payment link created successfully via job', [
                'invoice_id' => $this->invoice->id,
                'payment_method' => $this->paymentType,
                'amount' => $response['amount'] ?? null,
            ]);

            // Dispatch appropriate email job after payment is created
            if ($this->sendInvoiceCreatedEmail && $this->quote) {
                // Send invoice created email (for invoices created from quotes)
                SendInvoiceCreatedFromQuoteJob::dispatch(
                    $this->quote,
                    $this->invoice,
                    $response
                );

                Log::info('Invoice created email job dispatched after payment creation', [
                    'invoice_id' => $this->invoice->id,
                    'quote_id' => $this->quote->id,
                ]);
            } else {
                // Send regular invoice email (for regular invoices)
                // Note: attachmentFileIds can be empty - the invoice PDF will still be generated and attached
                SendInvoiceEmailJob::dispatch(
                    $this->invoice,
                    $response,
                    $this->attachmentFileIds ?? []
                );

                Log::info('Invoice email job dispatched after payment creation', [
                    'invoice_id' => $this->invoice->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to create payment link via job', [
                'invoice_id' => $this->invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
