<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "PlusJakartaSans", sans-serif;
            background: #f5f5f5;
            letter-spacing: 1.5px;
            line-height: 1.5;
        }


        .receipt-container {
            background: white;
            max-width: 800px;
            padding: 40px;
            margin: 0 auto;
        }

        .header-top {
            padding-bottom: 20px;
            margin-bottom: 30px;
            position: relative;
        }

        .logo-section {
            width: 50%;
            display: inline-block;
            vertical-align: top;
        }

        .logo-section img {
            width: 150px;
            height: auto;
        }

        .contact-section {
            width: 50%;
            display: inline-block;
            vertical-align: top;
            text-align: right;
            font-size: 12px;
            line-height: 1.8;
            position: absolute;
            right: 0;
            top: 0;
        }

        .contact-section p {
            margin: 0;
        }

        .main-title {
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
            margin-top: 20px;
        }

        .receipt-date {
            text-align: center;
            font-size: 15px;
            margin-bottom: 20px;
            color: #555;
        }

        .from-section {
            margin-bottom: 20px;
        }

        .section-label {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .section-content {
            font-size: 13px;
            line-height: 1.6;
            color: #333;
        }



        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            font-size: 13px;
        }

        .items-table th {
            background-color: #051630;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 12px;
            /* font-weight: 300; */
            border-bottom: 1px solid #ebe6e6;
        }

        .items-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
        }

        .items-table .qty,
        .items-table .price,
        .items-table .total {
            text-align: center;
        }

        .items-table .description {
            font-weight: bold;
        }

        .totals-section {
            width: 50%;
            margin-bottom: 20px;
        }

        .total-line {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 13px;
            border-bottom: 1px solid #eee;
        }

        .total-line.label {
            border-bottom: none;
        }

        .grand-total {
            border-top: 1px solid #333;
            border-bottom: none;
            padding-top: 10px;
            font-weight: bold;
            font-size: 15px;
            margin-top: 5px;
        }

        .payment-section {
            margin-bottom: 20px;
            font-size: 13px;
        }

        .payment-line {
            margin-bottom: 5px;
        }

        .thank-you {
            text-align: center;
            font-size: 13px;
            line-height: 1.8;
            color: #555;
        }

        .thank-you strong {
            color: #333;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="receipt-container">
        <div class="header-top">
            <div class="logo-section">
                <img src="{{ public_path('logo.png') }}" alt="lahza logo">
            </div>
            <div class="contact-section">
                <p><strong>{{ $receipt['companyInfo']->company_name ?? 'Lahza Agency' }}</strong></p>
                <p>{{ $receipt['companyInfo']->email ?? 'contact@company.com' }}</p>
                <p>{{ $receipt['companyInfo']->phone ?? 'Phone' }}</p>
            </div>
        </div>


        <!-- Title -->
        <h1 class="main-title">Receipt</h1>


        <!-- Date -->
        <div class="receipt-date">
            Date: {{ $receipt['date'] }}
        </div>


        <!-- From Section -->
        <div class="from-section">
            <div class="section-label">Received From:</div>
            <div class="section-content">
                <strong>{{ $receipt['customer_name'] }}</strong><br>
                Email: {{ $receipt['customer_email'] }}
            </div>
        </div>


        <!-- Description Section -->
        <div class="from-section">
            <div class="section-label">Description of Services or Products:</div>
        </div>


        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th class="qty">Quantity</th>
                    <th class="price">Unit Price</th>
                    <th class="price">Tax (%)</th>
                    <th class="total">Total (HT)</th>
                    <th class="total">Total (TTC)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($receipt['items'] as $item)
                    <tr>
                        <td class="description">{{ $item['description'] }}</td>
                        <td class="qty">{{ $item['quantity'] }}</td>
                        <td class="price">{{ number_format($item['unit_price'], 2) }}</td>
                        <td class="price">{{ number_format($item['tax_rate'], 2) }}%</td>
                        <td class="total">{{ number_format($item['total_price_ht'], 2) }}</td>
                        <td class="total"><strong>{{ number_format($item['total_price_ttc'], 2) }}</strong></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No items found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>


        <!-- Totals -->
        <div class="totals-section">
            <div class="total-line label">
                <div>
                    <strong>
                        Subtotal:
                    </strong>
                </div>
                <div>{{ number_format($receipt['subtotal'], 2) }}</div>
            </div>
            <div class="total-line label">
                <div>
                    <strong>
                        Tax :
                    </strong>
                </div>
                <div>{{ number_format($receipt['tax'], 2) }}</div>
            </div>
            <div class="total-line grand-total">
                <div><strong>Total Amount:</strong></div>
                <div><strong>{{ number_format($receipt['total'], 2) }}</strong></div>
            </div>
        </div>

        <!-- Payment Info -->
        <div class="payment-section">
            <div class="payment-line">
                <strong>Payment Method:</strong> {{ $receipt['payment_method'] }}
            </div>
            <div class="payment-line">
                <strong>Transaction ID:</strong> {{ str_pad($receipt['transaction_id'] ?? 0, 5, '0', STR_PAD_LEFT) }}
            </div>
            <div class="payment-line">
                <strong>Invoice Number:</strong> {{ str_pad($receipt['invoice']->id ?? 0, 5, '0', STR_PAD_LEFT) }}
            </div>
        </div>


        <!-- Thank You -->
        <div class="thank-you">
            Thank you for your purchase!<br>
            If you have any questions or need assistance,<br>
            feel free to contact us at
            <strong>{{ config('app.contact_email') ?? ($receipt['companyInfo']->email ?? 'your@email.com') }}</strong>.
        </div>

    </div>
</body>

</html>
