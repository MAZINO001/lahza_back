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
            color: #222;
            line-height: 1.45;
            font-size: 13px;
        }

        .receipt-container {
            background: white;
            max-width: 820px;
            margin: 30px auto;
            padding: 40px 50px;
            border: 1px solid #eee;
        }

        .header {
            margin-bottom: 35px;
        }

        .logo {
            width: 48%;
            display: inline-block;
            vertical-align: top;
        }

        .logo img {
            max-width: 160px;
            height: auto;
        }

        .company-info {
            width: 48%;
            display: inline-block;
            vertical-align: top;
            text-align: right;
            font-size: 12.5px;
            line-height: 1.7;
        }

        .company-info strong {
            font-size: 14px;
        }

        h1 {
            text-align: center;
            font-size: 26px;
            font-weight: bold;
            margin: 0 0 8px 0;
            color: #111;
        }

        .date {
            text-align: center;
            color: #555;
            font-size: 14px;
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 13.5px;
            font-weight: bold;
            margin: 0 0 6px 0;
            color: #222;
        }

        .customer {
            margin-bottom: 28px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 28px;
        }

        .items-table th {
            background: #041a38;
            color: white;
            padding: 9px 10px;
            font-weight: normal;
            font-size: 12px;
            text-align: left;
        }

        .items-table th.center,
        .items-table td.center {
            text-align: center;
        }

        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }

        .items-table .desc {
            font-weight: 600;
        }

        .totals-wrapper {
            width: 320px;
            margin-left: auto;
            margin-bottom: 30px;
        }

        .totals-line {
            padding: 10px 0;
            margin-bottom: 10px;
        }

        .totals-line strong {
            font-weight: bold;
        }

        .totals-line .label {
            float: left;
        }

        .totals-line .value {
            float: right;
        }

        .grand-total {
            border-bottom: none;
            padding-top: 10px;
            margin-top: 6px;
            font-size: 15px;
        }

        .payment {
            margin-bottom: 25px;
            line-height: 1.7;
        }

        .thank-you {
            text-align: center;
            color: #444;
            font-size: 13px;
            line-height: 1.6;
        }

        .thank-you strong {
            color: #000;
        }
    </style>
</head>

<body>

    <div class="receipt-container">

        <div class="header">
            <div class="logo">
                <img src="{{ public_path('logo.png') }}" alt="Company logo">
            </div>

            <div class="company-info">
                <strong>{{ $receipt['companyInfo']->company_name ?? 'Lahza Agency' }}</strong><br>
                {{ $receipt['companyInfo']->email ?? 'contact@company.com' }}<br>
                {{ $receipt['companyInfo']->phone ?? '(+212) 222 555 777' }}
            </div>
        </div>

        <h1>Receipt</h1>

        <div class="date">Date: {{ $receipt['date'] }}</div>

        <div class="customer">
            <div class="section-title">Received From:</div>
            <strong>{{ $receipt['customer_name'] }}</strong><br>
            Email: {{ $receipt['customer_email'] }}
        </div>

        <div class="section-title">Description of Services or Products:</div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th class="center">Quantity</th>
                    <th class="center">Unit Price</th>
                    <th class="center">Tax (%)</th>
                    <th class="center">Total (HT)</th>
                    <th class="center">Total (TTC)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($receipt['items'] as $item)
                    <tr>
                        <td class="desc">{{ $item['description'] }}</td>
                        <td class="center">{{ $item['quantity'] }}</td>
                        <td class="center">{{ number_format($item['unit_price'], 2) }}</td>
                        <td class="center">{{ number_format($item['tax_rate'], 2) }}%</td>
                        <td class="center">{{ number_format($item['total_price_ht'], 2) }}</td>
                        <td class="center"><strong>{{ number_format($item['total_price_ttc'], 2) }}</strong></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;padding:20px;">No items</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="totals-wrapper">
            <div class="totals-line">
                <div class="label"><strong>Subtotal</strong></div>
                <div class="value">{{ number_format($receipt['subtotal'], 2) }}</div>
            </div>

            <div class="totals-line">
                <div class="label"><strong>Tax</strong></div>
                <div class="value">{{ number_format($receipt['tax'], 2) }}</div>
            </div>

            <div class="totals-line grand-total">
                <div class="label"><strong>Total Amount</strong></div>
                <div class="value"><strong>{{ number_format($receipt['total'], 2) }}</strong></div>
            </div>
        </div>

        <div class="payment">
            <strong>Payment Method:</strong> {{ $receipt['payment_method'] }}<br>
            <strong>Transaction ID:</strong> {{ str_pad($receipt['transaction_id'] ?? 0, 5, '0', STR_PAD_LEFT) }}<br>
            <strong>Invoice Number:</strong> {{ str_pad($receipt['invoice']->id ?? 0, 5, '0', STR_PAD_LEFT) }}
        </div>

        <div class="thank-you">
            Thank you for your purchase!<br>
            If you have any questions or need assistance,<br>
            feel free to contact us at
            <strong>{{ config('app.contact_email') ?? ($receipt['companyInfo']->email ?? 'contact@company.com') }}</strong>.
        </div>

    </div>

</body>

</html>
