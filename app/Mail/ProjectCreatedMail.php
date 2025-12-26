<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class ProjectCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {
        $subject = $this->data['subject'] ?? 'New Project Created';
        
        $mail = $this->subject($subject)
            ->view('emails.project_created');
        
        // Pass all data variables directly to the view
        foreach ($this->data as $key => $value) {
            $mail->with($key, $value);
        }

        if (isset($this->data['client_id'])) {
            $mail->withSymfonyMessage(function (Email $email) {
                $email->getHeaders()->addTextHeader(
                    'X-Client-Id',
                    (string) $this->data['client_id']
                );
            });
        }

        return $mail;
    }
}
