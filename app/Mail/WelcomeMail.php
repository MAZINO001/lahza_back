<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $password;

    public function __construct($user, $password = 'lahzaapp2025')
    {
        $this->user = $user;
        $this->password = $password;
    }

    public function build()
    {
        return $this
            ->subject('Welcome to Lahza')
            ->html(
                "Hello {$this->user->name},<br/><br/>" .
                "Your account has been created on Lahza.<br/>" .
                "Email: {$this->user->email}<br/>" .
                "Password: {$this->password}<br/><br/>" .
                "Please log in and change your password.<br/><br/>" .
                "Regards,<br/>LAHZA HM"
            );
    }
}
