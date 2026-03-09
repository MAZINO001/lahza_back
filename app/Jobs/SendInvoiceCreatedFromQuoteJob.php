<?php

namespace App\Jobs;

use App\Models\Quotes;
use App\Models\Invoice;
use App\Mail\InvoiceCreatedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendInvoiceCreatedFromQuoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $quote;
    public $invoice;
    public $paymentResponse;

    /**
     * Create a new job instance.
     */
    public function __construct(Quotes $quote, Invoice $invoice, $paymentResponse = null)
    {
        $this->quote = $quote;
        $this->invoice = $invoice;
        $this->paymentResponse = $paymentResponse;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->quote->load('client.user');
            $this->invoice->load('client.user');

            $email = 'mangaka.wir@gmail.com';
            
            $invoiceNumber = 'INVOICE-' . str_pad($this->quote->id, 6, '0', STR_PAD_LEFT);
            
            $data = [
                'quote' => $this->quote,
                'invoice' => $this->invoice,
                'client' => $this->quote->client,
                'client_id' => $this->quote->client_id,
                'payment_url' => $this->paymentResponse['payment_url'] ?? null,
                'bank_info' => $this->paymentResponse['bank_info'] ?? null,
                'payment_method' => $this->paymentResponse['payment_method'] ?? 'bank',
                'subject' => 'New Invoice Created - ' . $invoiceNumber,
            ];

            if ($this->quote->client->user->allowsMail('quotes') || $this->quote->client->user->allowsMail('invoices')) {
                Mail::to($email)->send(new InvoiceCreatedMail($data));
                
                Log::info('Invoice created email sent successfully', [
                    'invoice_id' => $this->invoice->id,
                    'quote_id' => $this->quote->id,
                    'email' => $email
                ]);
            } else {
                Log::info('Invoice created email not sent: email notifications disabled', [
                    'invoice_id' => $this->invoice->id,
                    'quote_id' => $this->quote->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send invoice created email', [
                'invoice_id' => $this->invoice->id,
                'quote_id' => $this->quote->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
