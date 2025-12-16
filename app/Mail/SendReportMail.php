<?php
// namespace App\Mail;

// use Illuminate\Bus\Queueable;
// use Illuminate\Mail\Mailable;
// use Illuminate\Queue\SerializesModels;

// class SendReportMail extends Mailable
// {
//     use Queueable, SerializesModels;

//     public $data;
//     public $pdfPath;

//     public function __construct($data, $pdfPath)
//     {
//         $this->data = $data;
//         $this->pdfPath = $pdfPath;
//     }

//     public function build()
//     {
//         $subject = $this->data['subject'] ?? 'Your Document';
//         $message = $this->data['message'] ?? 'Please find the attached document.';
//         $name = $this->data['name'] ?? 'Customer';

//         $html = "<p>Hi {$name},</p><p>{$message}</p><p>Regards,<br/>LAHZA HM</p>";

//         return $this->subject($subject)
//             ->html($html)
//             ->attach($this->pdfPath, [
//                 'as' => 'document.pdf',
//                 'mime' => 'application/pdf',
//             ]);
//     }
// }<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class SendReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;
    public $pdfPath;

    public function __construct($data, $pdfPath)
    {
        $this->data = $data;
        $this->pdfPath = $pdfPath;
    }

    public function build()
    {
        $subject = $this->data['subject'] ?? 'Your Document';
        $message = $this->data['message'] ?? 'Please find the attached document.';
        $name    = $this->data['name'] ?? 'Customer';
        $clientId = $this->data['client_id'] ?? null;

        $html = "<p>Hi {$name},</p><p>{$message}</p><p>Regards,<br/>LAHZA HM</p>";

        return $this
            ->subject($subject)
            ->html($html)
            ->withSymfonyMessage(function (Email $email) use ($clientId) {
                if ($clientId) {
                    $email->getHeaders()->addTextHeader(
                        'X-Client-Id',
                        (string) $clientId
                    );
                }
            })
            ->attach($this->pdfPath, [
                'as'   => 'document.pdf',
                'mime' => 'application/pdf',
            ]);
    }
}

