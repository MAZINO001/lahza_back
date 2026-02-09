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
    public $attachmentPaths; // These should be ABSOLUTE system paths

    /**
     * @param $quote
     * @param array $attachmentPaths Full system paths (e.g., /var/www/storage/app/...)
     */
    public function __construct($quote, $attachmentPaths = [])
    {
        $this->quote = $quote;
        $this->client = $quote->client;
        $this->attachmentPaths = is_array($attachmentPaths) ? $attachmentPaths : [$attachmentPaths];
    }

    public function build()
    {
        $quoteNumber = 'QUOTES-' . str_pad($this->quote->id, 6, '0', STR_PAD_LEFT);
        $subject = 'New Quote Created - ' . $quoteNumber;
        
        $mail = $this->subject($subject)
                     ->view('emails.quote_created', [
                        'quote' => $this->quote,
                        'client' => $this->client,
                     ]);

        // Custom Header
        if ($this->quote->client_id) {
            $mail->withSymfonyMessage(function (Email $email) {
                $email->getHeaders()->addTextHeader('X-Client-Id', (string) $this->quote->client_id);
            });
        }

        // Attachments
        foreach ($this->attachmentPaths as $fullPath) {
            if (file_exists($fullPath)) {
                $mail->attach($fullPath, [
                    'as' => basename($fullPath),
                ]);
            }
        }

        return $mail;
    }
}