<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #28a745;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 40px;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Vérifiez votre adresse e-mail</h2>
        
        <p>Bonjour,</p>
        
        <p>
           Bonjour, Votre compte client LAHZA est actif. Accédez à votre tableau
            de bord pour suivre vos projets, valider vos devis et gérer vos factures.
        </p>
        
        <p>
            <a href="{{ $verificationUrl }}" class="button">Vérifier votre e-mail</a>
        </p>
        
        <p>Ce lien expirera dans 24 heures.</p>
        
        <p>Si vous n’avez pas créé de compte, aucune autre action n’est requise.</p>
        
        <div class="footer">
            <p>Merci,<br>{{ config('app.name') }}</p>
        </div>
    </div>
</body>
</html>