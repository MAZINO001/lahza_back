<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationNotification extends Notification
{
    use Queueable;

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $verificationUrl = config('app.frontend_url') . '/auth/verify-email?token=' . $notifiable->email_verification_token;

        return (new MailMessage)
            ->subject('Verify Your Email Address')
            ->view('emails.verify_email', [
                'verificationUrl' => $verificationUrl,
            ]);
    }
}