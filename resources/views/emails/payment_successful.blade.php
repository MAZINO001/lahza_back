<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Received - Invoice #{{ $invoice->id }}</title>
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
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
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
            background-color: #f0fff4;
            border-left: 4px solid #48bb78;
            padding: 20px;
            border-radius: 0 8px 8px 0;
            margin: 28px 0;
        }
        .payment-details {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }
        .payment-details td {
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
            color: #38a169 !important;
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
            color: #48bb78;
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
                <h1>Payment Received</h1>
                <p>Thank you for your payment!</p>
            </div>

            <!-- Main Content -->
            <div class="content">
                <p class="greeting">Hello {{ $client->name }},</p>

                <p>We've successfully received your payment. Thank you for your business!</p>

                <div class="highlight-box">
                    <table class="payment-details">
                        <tr>
                            <td class="label">Invoice Number</td>
                            <td class="value">#{{ 'INVOICE-' . str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</td>
                        </tr>
                        <tr>
                            <td class="label">Payment Method</td>
                            <td class="value">{{ ucfirst($payment->payment_method) }}</td>
                        </tr>
                        <tr>
                            <td class="label">Payment Amount</td>
                            <td class="value amount">${{ number_format($payment->amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="label">Payment Date</td>
                            <td class="value">{{ $payment->created_at->format('F j, Y g:i A') }}</td>
                        </tr>
                        <tr>
                            <td class="label">Invoice Total</td>
                            <td class="value">${{ number_format($invoice->total_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="label">Balance Due</td>
                            <td class="value">${{ number_format($invoice->balance_due ?? 0, 2) }}</td>
                        </tr>
                    </table>
                </div>

                <div style="margin: 30px 0; padding: 20px; background-color: #f8fafc; border-radius: 8px; border-left: 4px solid #48bb78;">
                    <h3 style="color: #2d3748; margin-top: 0;">Payment Confirmation</h3>
                    <p style="margin: 5px 0;">Your payment of <strong>${{ number_format($payment->amount, 2) }}</strong> has been successfully processed.</p>
                    @if(isset($payment->stripe_payment_intent_id))
                        <p style="margin: 5px 0; font-size: 12px; color: #718096;">Transaction ID: {{ $payment->stripe_payment_intent_id }}</p>
                    @endif
                </div>

                <p style="text-align: center; margin-top: 30px;">
                    <a href="{{ url('/login') }}" style="color: #48bb78; text-decoration: none; font-weight: 500;">
                        Log in to view invoice details and payment history
                    </a>
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

