<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Verification Code</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2563eb;
            margin: 0;
            font-size: 24px;
        }
        .otp-code {
            background: #f8fafc;
            border: 2px dashed #2563eb;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 30px 0;
        }
        .otp-code .code {
            font-size: 36px;
            font-weight: bold;
            letter-spacing: 8px;
            color: #2563eb;
            font-family: 'Courier New', monospace;
        }
        .content {
            color: #555;
            font-size: 16px;
        }
        .warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Security Verification</h1>
        </div>
        
        <div class="content">
            <p>Hello {{ $userName }},</p>
            
            <p>As part of our security measures, we require periodic verification to ensure your account's safety. Your verification code is:</p>
        </div>
        
        <div class="otp-code">
            <div class="code">{{ $otp }}</div>
            <p style="margin: 10px 0 0 0; color: #6b7280; font-size: 14px;">Valid for 15 minutes</p>
        </div>
        
        <div class="content">
            <p>Please enter this code in the application to continue using your account.</p>
        </div>
        
        <div class="warning">
            <strong>⚠️ Security Notice:</strong> Never share this code with anyone. Our team will never ask for your verification code.
        </div>
        
        <div class="content">
            <p>If you didn't request this code, please ignore this email or contact support if you have concerns.</p>
        </div>
        
        <div class="footer">
            <p>This is an automated message, please do not reply to this email.</p>
            <p>&copy; {{ date('Y') }} Your CRM App. All rights reserved.</p>
        </div>
    </div>
</body>
</html>