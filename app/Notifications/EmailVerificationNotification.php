<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class EmailVerificationNotification extends Notification implements ShouldQueue{
    use Queueable;

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $verificationUrl = config('app.frontend_url') . '/auth/verify-email?token=' . $notifiable->email_verification_token;

        return (new MailMessage)
            ->subject('Bienvenue sur votre espace - LAHZA votre partenaire Marketing Digital')
            ->view('emails.verify_email', [
                'verificationUrl' => $verificationUrl,
            ]);
    }
}