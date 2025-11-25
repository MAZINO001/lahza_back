<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Invoice #{{ $invoice->invoice_number }} - {{ $client->name }}</title>
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
        .invoice-details {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }
        .invoice-details td {
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
        .btn {
            display: inline-block;
            background-color: #667eea;
            color: white !important;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            padding: 14px 32px;
            border-radius: 8px;
            margin: 28px 0;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
        }
        .btn:hover {
            background-color: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
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
                <h1>New Invoice Created</h1>
                <p>Invoice #{{ $invoice->invoice_number }}</p>
            </div>

            <!-- Main Content -->
            <div class="content">
                <p class="greeting">Hello {{ $client->name }},</p>

                <p>We've generated your invoice <strong>#{{ $invoice->invoice_number }}</strong> from your approved quote <strong>#{{ $quote->id }}</strong>.</p>

                <div class="highlight-box">
                    <table class="invoice-details">
                        <tr>
                            <td class="label">Invoice Number</td>
                            <td class="value">#{{ $invoice->invoice_number }}</td>
                        </tr>
                        <tr>
                            <td class="label">Issue Date</td>
                            <td class="value">{{ $invoice->created_at->format('F j, Y') }}</td>
                        </tr>
                        <tr>
                            <td class="label">Total Amount</td>
                            <td class="value amount">${{ number_format($invoice->total_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="label">From Quote</td>
                            <td class="value">#{{ $quote->id }}</td>
                        </tr>
                    </table>
                </div>
                <div style="margin: 30px 0; text-align: center;">
            @if($payment->payment_method === 'stripe')
                    <h3 style="color: #4a5568; margin-bottom: 15px;">Payment Options</h3>
                    <a href="{{ $paymentUrl }}" class="btn" style="margin: 10px auto;">
                        Pay Now with Credit Card
                    </a>
                    <p style="margin-top: 10px; color: #718096; font-size: 14px;">
                        Secure payment processed by Stripe
                    </p>
                    <p style="margin-top: 10px; color: #718096; font-size: 12px;">
                        Or copy this link: <span style="word-break: break-all; color: #4a5568;">{{ $paymentUrl }}</span>
                    </p>
            @else
                    <h3 style="color: #4a5568; margin-bottom: 15px;">Our Bank Info</h3>
                    <p style="margin-top: 10px; color: #718096; font-size: 14px;">
                        Rib: xxxxxxxxxxxx
                    </p>

            @endif
                </div>

                <div style="margin: 30px 0; padding: 20px; background-color: #f8fafc; border-radius: 8px; border-left: 4px solid #48bb78;">
                    <h3 style="color: #2d3748; margin-top: 0;">Billing Information</h3>
                    <p style="margin: 5px 0;"><strong>Client:</strong> {{ $client->name }}</p>
                    @if($client->email)
                        <p style="margin: 5px 0;"><strong>Email:</strong> {{ $client->email }}</p>
                    @endif
                    @if($client->phone)
                        <p style="margin: 5px 0;"><strong>Phone:</strong> {{ $client->phone }}</p>
                    @endif
                    @if($client->address || $client->city || $client->postal_code)
                        <p style="margin: 5px 0 0;"><strong>Address:</strong></p>
                        <p style="margin: 5px 0;">
                            {{ $client->address ?? '' }}
                            {{ $client->city ? ($client->address ? ', ' : '') . $client->city : '' }}
                            {{ $client->postal_code ? ($client->city ? ', ' : '') . $client->postal_code : '' }}
                        </p>
                    @endif
                </div>

                <p style="text-align: center; margin-top: 30px;">
                    <a href="{{ url('/login') }}" style="color: #667eea; text-decoration: none; font-weight: 500;">
                        Or log in to view full invoice details and payment history
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