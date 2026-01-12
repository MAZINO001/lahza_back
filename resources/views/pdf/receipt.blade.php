<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'plusJakartaSans', sans-serif;
            background: #f8f9fa;
            color: #1a1a1a;
            line-height: 1.45;
        }

        .receipt-container {
            background: white;
            max-width: 800px;
            padding: 20px;
            border-radius: 8px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            width: 100%;
            margin-bottom: 16px;
        }

        .header-top .logo-square {
            flex: 0 0 auto;
            min-width: 170px;
        }

        .header-top .logo-square img {
            width: 170px;
            height: auto;
            display: block;
        }

        .header-top .contact-block {
            flex: 1;
            font-size: 15px;
            color: #051630;
            line-height: 1.6;
            text-align: right;
        }

        .header-top .contact-block a {
            color: #051630;
            text-decoration: none;
        }

        .header-top .contact-block h3 {
            margin: 0 0 5px 0;
            font-size: 15px;
            font-weight: normal;
        }

        .main-title {
            text-align: center;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: -0.3px;
        }

        .receipt-date {
            text-align: center;
            color: #051630;
            font-size: 14.5px;
            margin-bottom: 40px;
        }

        .from-block {
            margin-bottom: 40px;
        }

        .from-title {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #222;
        }

        .from-name {
            font-weight: 600;
            font-size: 15px;
            margin-bottom: 4px;
        }

        .from-email {
            color: #051630;
            font-size: 14.5px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 32px;
        }

        .items-table thead {
            background: #f1f3f5;
        }

        .items-table th,
        .items-table td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
            font-size: 14px;
        }

        .items-table th {
            font-weight: 600;
            color: #333;
            text-transform: uppercase;
            font-size: 12.5px;
            letter-spacing: 0.4px;
        }

        .items-table .qty,
        .items-table .price,
        .items-table .total {
            text-align: right;
        }

        .items-table .description {
            font-weight: 500;
        }

        .totals-block {
            width: 320px;
            margin-bottom: 40px;
        }

        .total-line {
            display: flex;
            justify-content: space-between;
            padding: 9px 0;
            font-size: 14.5px;
        }

        .total-line strong {
            font-weight: 600;
        }

        .grand-total {
            border-top: 1px solid #333;
            padding-top: 14px;
            margin-top: 10px;
            font-size: 17px;
            font-weight: 700;
        }

        .payment-info {
            font-size: 14px;
            color: #333;
            margin-bottom: 40px;
        }

        .payment-info strong {
            font-weight: 600;
            min-width: 140px;
            display: inline-block;
        }

        .thank-you {
            text-align: center;
            color: #051630;
            font-size: 14px;
            line-height: 1.6;
        }

        .thank-you strong {
            color: #222;
        }
    </style>
</head>

<body>

    <div class="receipt-container">

        <div class="header-top">
            <div class="logo-square">
                <img src="{{ public_path('logo.png') }}" alt="Logo" />
            </div>
            <div class="contact-block">
                <h3>{{ $receipt['companyInfo']->company_name ?? 'Lahza Agency' }}</h3>
                <h3>{{ $receipt['companyInfo']->email ?? 'contact@company.com' }}</h3>
                <h3>{{ $receipt['companyInfo']->phone ?? 'Téléphone' }}</h3>
            </div>
        </div>

        <h1 class="main-title">Reçu</h1>

        <div class="receipt-date">
            Date : {{ $receipt['date'] }}
        </div>

        <div class="from-block">
            <div class="from-title">Reçu de : {{ $receipt['customer_name'] }}</div>
            <div class="from-email">E-mail : {{ $receipt['customer_email'] }}</div>
        </div>

        <div style="margin-bottom: 16px;">
            <div class="from-title">Description des services ou produits :</div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="qty">Quantité</th>
                    <th class="price">Prix unitaire</th>
                    <th class="price">Taxe (%)</th>
                    <th class="total">Montant HT</th>
                    <th class="total">Montant TTC</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($receipt['items'] as $item)
                    <tr>
                        <td class="description">{{ $item['description'] }}</td>
                        <td class="qty">{{ $item['quantity'] }}</td>
                        <td class="price">{{ number_format($item['unit_price'], 2) }} <span class="currency"></span>
                        </td>
                        <td class="tax">{{ number_format($item['tax_rate'], 2) }}%</td>
                        <td class="total">{{ number_format($item['total_price_ht'], 2) }} <span
                                class="currency"></span></td>
                        <td class="total">{{ number_format($item['total_price_ttc'], 2) }} <span
                                class="currency"></span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty-state">Aucun article trouvé</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="totals-block">
            <div class="total-line">
                <div>Sous-total (HT) :</div>
                <div>{{ number_format($receipt['subtotal'], 2) }} </div>
            </div>
            <div class="total-line">
                <div>Total Taxe :</div>
                <div>{{ number_format($receipt['tax'], 2) }} </div>
            </div>
            <div class="total-line grand-total">
                <strong>Montant total TTC :</strong>
                <strong>{{ number_format($receipt['total'], 2) }} </strong>
            </div>
        </div>

        <div class="payment-info">
            <div><strong>Mode de paiement :</strong> {{ $receipt['payment_method'] }}</div>
            <div><strong>ID de transaction :</strong> {{ $receipt['transaction_id'] }}</div>
            <div><strong>Numéro de facture :</strong> #{{ $receipt['invoice']->id ?? 'N/A' }}</div>
        </div>

        <div class="thank-you">
            Merci pour votre achat !<br>
            Si vous avez des questions ou besoin d'aide,<br>
            contactez-nous à
            <strong>{{ config('app.contact_email') ?? ($receipt['companyInfo']->email ?? 'votre@email.com') }}</strong>.
        </div>

    </div>

</body>

</html>
