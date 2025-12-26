<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ClientRegisteredMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $client;

    public function __construct($user, $client)
    {
        $this->user = $user;
        $this->client = $client;
    }

    public function build()
    {
        return $this
            ->subject('Welcome! Your Account Has Been Created')
            ->view('emails.client_registered')
            ->with([
                'user' => $this->user,
                'client' => $this->client,
            ]);
    }
}
