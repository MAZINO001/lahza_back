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
    $dashboardUrl = url('/login'); // change if needed

    return $this
        ->subject('Bienvenue sur votre espace - LAHZA votre partenaire Marketing Digital')
        ->html("
            <table width='100%' cellpadding='0' cellspacing='0' style='padding:40px 0;'>
                <tr>
                    <td align='center'>

                        <table width='600' cellpadding='0' cellspacing='0' style='background:#ffffff; border-radius:12px; padding:40px; box-shadow:0 4px 12px rgba(0,0,0,0.05);'>
                            
                            <!-- Logo / Title -->
                            <tr>
                                <td style='text-align:center; padding-bottom:20px;'>
                                    <h2 style='margin:0; color:#111827;'>Bienvenue sur LAHZA</h2>
                                </td>
                            </tr>

                            <!-- Message -->
                            <tr>
                                <td style='color:#374151; font-size:15px; line-height:1.6;'>
                                    Bonjour <strong>{$this->user->name}</strong>,<br/><br/>
                                    
                                    Votre compte client <strong>LAHZA</strong> est actif.<br/><br/>
                                    
                                    Accédez à votre tableau de bord pour suivre vos projets, 
                                    valider vos devis et gérer vos factures.
                                </td>
                            </tr>

                            <!-- Credentials -->
                            <tr>
                                <td style='padding:25px 0;'>
                                    <div style='background:#f9fafb; border-radius:8px; padding:15px; font-size:14px; color:#111827;'>
                                        <strong>Email :</strong> {$this->user->email}<br/>
                                        <strong>Mot de passe :</strong> {$this->password}
                                    </div>
                                </td>
                            </tr>

                            <!-- Button -->
                            <tr>
                                <td align='center' style='padding:20px 0;'>
                                    <a href='{$dashboardUrl}'
                                       style='display:inline-block; background-color:#111827; color:#ffffff; 
                                              text-decoration:none; padding:12px 24px; border-radius:8px; 
                                              font-size:14px; font-weight:500;'>
                                        Accéder à mon espace
                                    </a>
                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style='padding-top:30px; font-size:12px; color:#9ca3af; text-align:center;'>
                                    © ".date('Y')." LAHZA. Tous droits réservés.<br/>
                                    Merci de nous faire confiance.
                                </td>
                            </tr>

                        </table>

                    </td>
                </tr>
            </table>
        ");
}
}
