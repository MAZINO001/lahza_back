<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;
use Illuminate\Support\Facades\Log;

class SendReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;
    public $attachmentPaths;

    public function __construct($data, $attachmentPaths = [])
    {
        $this->data = $data;
        $this->attachmentPaths = is_array($attachmentPaths) ? $attachmentPaths : [$attachmentPaths];
    }

    public function build()
    {
        Log::info('Building email with attachments', [
            'attachment_count' => count($this->attachmentPaths)
        ]);

        $subject = $this->data['subject'] ?? 'Your Document';
        $message = $this->data['message'] ?? 'Please find the attached document.';
        $name    = $this->data['name'] ?? 'Customer';
        $clientId = $this->data['client_id'] ?? null;

        $html = "<p>Hi {$name},</p><p>{$message}</p><p>Regards,<br/>LAHZA HM</p>";

        $mail = $this
            ->subject($subject)
            ->html($html);

        if ($clientId) {
            $mail->withSymfonyMessage(function (Email $email) use ($clientId) {
                $email->getHeaders()->addTextHeader(
                    'X-Client-Id',
                    (string) $clientId
                );
            });
        }

        // Attach all files from the array
        foreach ($this->attachmentPaths as $index => $filePath) {
            // Normalize path for Windows/Linux compatibility
            $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath);
            
            Log::info('Processing attachment', [
                'index' => $index,
                'original_path' => $filePath,
                'normalized_path' => $normalizedPath,
                'exists' => file_exists($normalizedPath)
            ]);
            
            if (file_exists($normalizedPath)) {
                $filename = basename($normalizedPath);
                
                // First file is the main PDF (invoice/quote)
                if ($index === 0 && pathinfo($normalizedPath, PATHINFO_EXTENSION) === 'pdf') {
                    $filename = 'invoice.pdf';
                }
                
                $mail->attach($normalizedPath, [
                    'as' => $filename,
                    'mime' => mime_content_type($normalizedPath) ?: 'application/pdf'
                ]);
                
                Log::info('Successfully attached file', [
                    'filename' => $filename,
                    'size' => filesize($normalizedPath)
                ]);
            } else {
                Log::error('Attachment file not found', [
                    'path' => $normalizedPath,
                    'index' => $index
                ]);
            }
        }

        return $mail;
    }
}