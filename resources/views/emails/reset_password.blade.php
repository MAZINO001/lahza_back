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
            background-color: #3490dc;
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
        <h2>Reset Your Password</h2>
        
        <p>Hello,</p>
        
        <p>You are receiving this email because we received a password reset request for your account.</p>
        
        <p>
            <a href="{{ $resetUrl }}" class="button">Reset Password</a>
        </p>
        
        <p>This password reset link will expire in {{ config('auth.passwords.users.expire', 60) }} minutes.</p>
        
        <p>If you did not request a password reset, no further action is required.</p>
        
        <div class="footer">
            <p>Thanks,<br>{{ config('app.name') }}</p>
        </div>
    </div>
</body>
</html>
