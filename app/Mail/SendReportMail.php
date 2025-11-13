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
//         return $this->subject('Your Report')
//                     ->view('emails.report')
//                     ->with('data', $this->data)
//                     ->attach($this->pdfPath, [
//                         'as' => 'report.pdf',
//                         'mime' => 'application/pdf',
//                     ]);
//     }
// }


namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

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
        return $this->subject('Your Invoice')
            ->view('emails.invoice') // Simple email body
            ->attach($this->pdfPath, [
                'as' => 'invoice.pdf',
                'mime' => 'application/pdf',
            ]);
    }
}
