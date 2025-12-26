<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Quote #{{ $quote->id }} - {{ $client->name }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f6f9;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            color: #333333;
        }
        .email-wrapper {
            background-color: #f4f6f9;
            padding: 20px 0;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .header p {
            margin: 12px 0 0;
            font-size: 16px;
            opacity: 0.95;
        }
        .content {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 17px;
            margin-bottom: 24px;
        }
        .highlight-box {
            background-color: #f8fafc;
            border-left: 4px solid #667eea;
            padding: 20px;
            border-radius: 0 8px 8px 0;
            margin: 28px 0;
        }
        .quote-details {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }
        .quote-details td {
            padding: 12px 0;
            font-size: 16px;
        }
        .label {
            font-weight: 600;
            color: #555;
            width: 140px;
        }
        .value {
            color: #222222;
        }
        .amount {
            font-size: 24px !important;
            font-weight: 700;
            color: #2563eb !important;
        }
        .footer {
            background-color: #f8fafc;
            padding: 30px;
            text-align: center;
            color: #718096;
            font-size: 14px;
            border-top: 1px solid #e2e8f0;
        }
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        @media (max-width: 600px) {
            .content, .header {
                padding: 30px 20px !important;
            }
            .header h1 {
                font-size: 24px;
            }
            .amount {
                font-size: 20px !important;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">
            <!-- Header -->
            <div class="header">
                <h1>New Quote Created</h1>
                <p>Quote #{{ 'QUOTES-' . str_pad($quote->id, 6, '0', STR_PAD_LEFT) }}</p>
            </div>

            <!-- Main Content -->
            <div class="content">
                <p class="greeting">Hello {{ $client->name }},</p>

                <p>We've prepared a new quote for you. Please review the details below and find the attached PDF document.</p>

                <div class="highlight-box">
                    <table class="quote-details">
                        <tr>
                            <td class="label">Quote Number</td>
                            <td class="value">#{{ 'QUOTES-' . str_pad($quote->id, 6, '0', STR_PAD_LEFT) }}</td>
                        </tr>
                        <tr>
                            <td class="label">Date</td>
                            <td class="value">{{ \Carbon\Carbon::parse($quote->quotation_date)->format('F j, Y') }}</td>
                        </tr>
                        <tr>
                            <td class="label">Status</td>
                            <td class="value">{{ ucfirst($quote->status) }}</td>
                        </tr>
                        <tr>
                            <td class="label">Total Amount</td>
                            <td class="value amount">${{ number_format($quote->total_amount, 2) }}</td>
                        </tr>
                    </table>
                </div>

                <div style="margin: 30px 0; padding: 20px; background-color: #f8fafc; border-radius: 8px; border-left: 4px solid #48bb78;">
                    <h3 style="color: #2d3748; margin-top: 0;">Client Information</h3>
                    <p style="margin: 5px 0;"><strong>Client:</strong> {{ $client->name }}</p>
                    @if(isset($client->user) && $client->user->email)
                        <p style="margin: 5px 0;"><strong>Email:</strong> {{ $client->user->email }}</p>
                    @endif
                </div>

                <p style="text-align: center; margin-top: 30px;">
                    <a href="{{ url('/login') }}" style="color: #667eea; text-decoration: none; font-weight: 500;">
                        Log in to view full quote details and approve
                    </a>
                </p>

                <p style="margin-top: 30px;">
                    Please find the detailed quote attached as a PDF document. If you have any questions, feel free to contact us.
                </p>
            </div>

            <!-- Footer -->
            <div class="footer">
                <p>This is an automated notification from Lahza HM.<br>
                Please do not reply to this email.</p>
                <p>
                    <a href="{{ url('/') }}">{{ url('/') }}</a> • 
                    Need help? <a href="mailto:support@yoursite.com">Contact Support</a>
                </p>
                <p style="margin-top: 20px; color: #a0aec0; font-size: 12px;">
                    © {{ date('Y') }} LAHZA HM. All rights reserved.
                </p>
            </div>
        </div>
    </div>
</body>
</html>

