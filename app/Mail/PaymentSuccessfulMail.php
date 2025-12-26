<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class PaymentSuccessfulMail extends Mailable
{
    use Queueable, SerializesModels;

    public $payment;
    public $invoice;
    public $client;

    public function __construct($payment)
    {
        $this->payment = $payment;
        $this->invoice = $payment->invoice;
        $this->client = $payment->invoice->client;
    }

    public function build()
    {
        $invoiceNumber = 'INVOICE-' . str_pad($this->invoice->id, 6, '0', STR_PAD_LEFT);
        $subject = 'Payment Received - ' . $invoiceNumber;
        
        $html = view('emails.payment_successful', [
            'payment' => $this->payment,
            'invoice' => $this->invoice,
            'client' => $this->client,
        ])->render();

        $mail = $this
            ->subject($subject)
            ->html($html);

        // Add custom header for client_id
        if ($this->invoice->client_id) {
            $mail->withSymfonyMessage(function (Email $email) {
                $email->getHeaders()->addTextHeader(
                    'X-Client-Id',
                    (string) $this->invoice->client_id
                );
            });
        }

        return $mail;
    }
}

