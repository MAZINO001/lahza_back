<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class QuoteCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $quote;
    public $client;
    public $pdfPath;

    public function __construct($quote, $pdfPath)
    {
        $this->quote = $quote;
        $this->client = $quote->client;
        $this->pdfPath = $pdfPath;
    }

    public function build()
    {
        $quoteNumber = 'QUOTES-' . str_pad($this->quote->id, 6, '0', STR_PAD_LEFT);
        $subject = 'New Quote Created - ' . $quoteNumber;
        
        $html = view('emails.quote_created', [
            'quote' => $this->quote,
            'client' => $this->client,
        ])->render();

        $mail = $this
            ->subject($subject)
            ->html($html);

        // Add custom header for client_id
        if ($this->quote->client_id) {
            $mail->withSymfonyMessage(function (Email $email) {
                $email->getHeaders()->addTextHeader(
                    'X-Client-Id',
                    (string) $this->quote->client_id
                );
            });
        }

        // Attach PDF if path is provided
        if ($this->pdfPath && file_exists($this->pdfPath)) {
            $mail->attach($this->pdfPath, [
                'as' => 'quote-' . $this->quote->id . '.pdf',
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}

