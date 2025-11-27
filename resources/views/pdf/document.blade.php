<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        @if ($type === 'invoice')
            Facture {{ 'INVOICE-' . str_pad($invoice->id ?? 0, 5, '0', STR_PAD_LEFT) }}
        @else
            Facture {{ 'QUOTES-' . str_pad($quotes->id ?? 0, 5, '0', STR_PAD_LEFT) }}
        @endif
    </title>
    <link rel="stylesheet" href="{{ public_path('fonts/roboto.css') }}">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Roboto", sans-serif;
            /* padding: 40px; */
            background: #f5f5f5;
            letter-spacing: 1.5px;
            line-height: 1.8;
        }

        .invoice-container {
            /* max-width: 800px; */
            /* margin: 0 auto; */
            /* padding: 30px; */
            /* box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); */
            background: white;
            height: 100%;
        }

        .header {
            display: flex;
            justify-content: space-between;
        }

        /* .company-info {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            width: 100%;
        } */

        .company-info {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }

        .company-info>div {
            display: table-cell;
            vertical-align: top;
            padding: 5px;
        }


        .company-logo img {
            width: 170px;
            height: auto;
            color: #999;
            font-size: 12px;
            margin-bottom: 15px;
        }

        .company-info .data {
            text-align: right;
        }

        .company-info .data h1 {
            font-size: 15px;
            font-weight: bold;
            margin-bottom: 8px;

        }

        .company-info p {
            font-size: 11px;
            line-height: 1.6;
            color: #333;
        }

        .invoice-title {
            position: relative;
            text-align: center;
            margin: 15px 0px;
        }

        .invoice-title h2 {
            display: inline-block;
            position: relative;
            font-size: 16px;
            color: #000;
            background: #fff;
            padding: 0 10px;
            z-index: 1;
        }

        .invoice-title::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 0;
            width: 100%;
            height: 1px;
            background-color: #ebe6e6;
            z-index: 0;
        }


        .client-invoice-info {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            margin-bottom: 25px;
        }

        /* .client-info {
            display: flex;
            align-items: start;
            justify-content: space-between;
            width: 100%;
            margin-bottom: 25px;
        } */


        .client-info h3 {
            font-size: 15px;
            font-weight: 900;
            margin-bottom: 8px;
        }

        .client-info p {
            font-size: 13px;
            line-height: 1.6;
            color: #222;
            font-weight: 700;
        }

        .invoice-details {
            text-align: left;
        }

        .invoice-details table {
            width: 100%;
            font-size: 11px;
            text-align: left;
        }

        .invoice-details table {
            width: 100%;
            border-collapse: collapse;
        }


        .invoice-details table.one thead {
            background-color: #051630;
            color: white;
        }

        .invoice-details th {
            padding: 10px;
            text-align: left;
            font-size: 15px;
            font-weight: 300;
            border-bottom: 1px solid #ebe6e6;
        }



        .invoice-details table.one tr td {
            text-align: left;
            font-weight: normal;
        }

        .invoice-details th,
        .invoice-details td {
            border: 1px solid #ebe6e6;
            padding: 6px 12px;
        }

        .invoice-details th {
            text-align: left;
        }

        .invoice-details td:first-child {
            text-align: left;
            font-weight: bold;
        }

        .invoice-details td:last-child {
            text-align: right;
        }

        .items-table {
            border: 1px solid #ebe6e6;
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        .items-table thead {
            background-color: #051630;
            color: white;
        }

        .items-table th {
            padding: 6px 12px;
            text-align: left;
            font-size: 15px;
            font-weight: 300;
            border-bottom: 1px solid #ebe6e6;
        }

        .Sous-total span {
            text-align: right;
            font-size: 12px;
            font-weight: 700;
        }

        .items-table td:first-child:not(.Sous-total) {
            text-align: left;
        }


        .items-table td {
            padding: 15px 10px;
            font-size: 12px;
            border-bottom: 1px solid #ebe6e6;
            vertical-align: top;
            text-align: right;
            font-weight: 400;
        }

        .item-description {
            margin-top: 8px;
            color: #444;
            font-weight: 300;
            line-height: 1.6;
        }

        .item-description li {
            margin-left: 15px;
        }

        /* .totals {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
            margin-left: auto;
            text-align: right;
        } */

        .total_container {
            width: 30%;
            font-weight: 900;
            font-size: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ebe6e6;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .total_container span:nth-child(2) {
            margin-left: 15px
        }

        .payment-info {
            width: 100%;
            margin-bottom: 25px;
        }

        .payment-info p {
            font-size: 13px;
        }

        .payment-info strong:not(.mode) {
            font-weight: 400;
            width: 17%;
        }

        .payment-info .mode {
            width: 17%;
            font-weight: 800;
            color: #000;
        }


        .bank-details {
            margin-top: 10px;
            font-size: 11px;
            line-height: 1.6;
            border-radius: 6px;
        }

        .bank-details p {
            margin: 4px 0;
        }

        .bank-details strong {
            display: inline-block;
            width: 120px;
            color: #333;
        }

        .conditions {

            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .notes {
            font-size: 12px;
            font-weight: normal;
        }


        /*
        .signatures {
            display: flex;
            align-items: center;
            justify-content: space-between;
        } */


        * .signatures .admin_sign {
            /* border: 1px solid #051630; */
            padding: 20px 30px;
        }

        .signatures .client_sign {
            /* border: 1px solid #051630; */
            padding: 20px 30px;
        }


        /* testing this shit  */
        .signatures {
            width: 100%;
            margin-top: 20px;
            overflow: hidden;
        }

        .signatures>.admin_sign {
            float: left;
            width: 30%;
            padding: 30px 20px;
            text-align: center
        }



        .signatures>.client_sign {
            float: right;
            width: 30%;
            padding: 30px 20px;
            text-align: center
        }

        .client-info {
            width: 100%;
            margin-bottom: 25px;
            position: relative;
            height: auto;
        }

        .client-info>div:first-child {
            display: inline-block;
            vertical-align: top;
            padding: 5px;
            width: 200px;
        }

        .client-info>div:nth-child(2) {
            display: inline-block;
            vertical-align: top;
            padding: 5px;
            position: absolute;
            right: 0;
            top: 0;
            text-align: right;
            width: 200px;
        }

        .totals {
            width: 100%;
            margin-bottom: 25px;
            overflow: hidden;
            /* ensures floats stay inside */
        }

        .totals>div:first-child {
            display: inline-block;
            vertical-align: middle;
            padding: 5px;
            width: 250px;
        }

        .totals>div:last-child {
            display: inline-block;
            vertical-align: middle;
            padding: 5px;
            float: right;
            text-align: right;
        }

        .last {
            /* background-color: red; */
            /* position: absolute; */
            /* bottom: 0; */
            /* width: 100%; */
        }

        .status {
            border: 1px solid #8a7d7d;
            padding: 4px;
            border-radius: 5px;
            text-align: center;
            width: 100px;
            float: right;
            text-transform: capitalize;
        }
    </style>
</head>

<body>
    <div class="invoice-container">
        <div class="header">
            <div class="company-info">
                <div class="company-logo">
                    <img src="{{ public_path('logo.png') }}" alt="lahza logo">
                </div>

                <div class="data">
                    <h1>LAHZA HM SARL</h1>
                    <p>
                        Rue Sayed Kotb, Rés. Assedk,<br>
                        Etg 1 Bureau 12, 90000 Tanger<br>
                        Morocco<br>
                        +2126 27 34 08 75<br>
                        contact@lahza.ma<br>
                        www.lahza.ma
                    </p>
                </div>
            </div>
        </div>
        <div class="invoice-title">
            <h2>
                @if ($type === 'invoice')
                    FACTURE
                @else
                    DEVIS
                @endif
            </h2>
        </div>

        <div class="client-invoice-info">
            <div class="client-info">
                <div>
                    @php($client = $type === 'invoice' ? $invoice->client ?? null : $quote->client ?? null)
                    <h3>{{ $client?->company ?? ($client?->name ?? '') }}</h3>
                    <p>
                        {{ $client?->address ?? '' }}<br>
                        {{ $client?->city ?? '' }}<br>
                        {{ $client?->country ?? '' }}<br>
                        {{ empty($client?->ice) ? 'SIREN : ' . ($client?->siren ?? '') : 'ICE : ' . ($client?->ice ?? '') }}
                    </p>
                </div>
                <div>
                    @if ($type === 'invoice')
                        <p>N° de facture</p>
                        <h3>{{ sprintf('INV-%05d', $invoice->id ?? 0) }}</h3>
                        <h3>{{ $invoice->status }}</h3>
                    @else
                        <p>N° de devis</p>
                        <h3>{{ sprintf('Q-%05d', $quote->id ?? 0) }}</h3>
                        <h3 class="status">{{ $quote->status }}</h3>
                    @endif
                </div>
            </div>
            <div class="invoice-details">
                <table border="1" class="one">
                    <thead>
                        <tr>
                            @if ($type === 'invoice')
                                <th>Date de facture</th>
                                <th>Date d'échéance</th>
                            @else
                                <th>Date du devis</th>
                                <th>Date d'échéance</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            @if ($type === 'invoice')
                                <td>{{ optional($invoice->invoice_date ? \Carbon\Carbon::parse($invoice->invoice_date) : '-')->translatedFormat('d F Y') }}
                                </td>
                                <td>{{ optional($invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date) : null)->translatedFormat('d F Y') }}
                                </td>
                            @else
                                <td>{{ optional($quote->quotation_date ? \Carbon\Carbon::parse($quote->quotation_date) : null)->translatedFormat('d F Y') }}
                                </td>
                                <td>{{ optional($quote->due_date ?? null ? \Carbon\Carbon::parse($quote->due_date) : null)->translatedFormat('d F Y') }}
                                </td>
                            @endif
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <table class="items-table" border="1">
            <thead>
                <tr>
                    <th>Article & Description</th>
                    <th>Quantité</th>
                    <th>Prix HT</th>
                    <th>Montant TTC</th>
                </tr>
            </thead>
            <tbody>
                @if ($type === 'invoice')
                    @foreach ($invoice->invoiceServices ?? [] as $line)
                        @php($service = $line->service ?? null)
                        <tr>
                            <td>
                                <strong>{{ $service->name ?? '' }}</strong>
                                @if (!empty($service->description))
                                    <div class="item-description">{!! nl2br(e($service->description)) !!}</div>
                                @endif
                            </td>
                            <td>{{ number_format((float) ($line->quantity ?? 0), 2, '.', ' ') }}</td>
                            <td>{{ number_format((float) ($service->base_Price ?? 0), 2, '.', ' ') }}</td>
                            <td>{{ number_format((float) ($line->individual_total ?? 0), 2, '.', ' ') }}</td>
                        </tr>
                    @endforeach
                @else
                    @foreach ($quote->services ?? [] as $service)
                        <tr>
                            <td>
                                <strong>{{ $service->name ?? '' }}</strong>
                                @if (!empty($service->description))
                                    <div class="item-description">{!! nl2br(e($service->description)) !!}</div>
                                @endif
                            </td>
                            <td>{{ number_format((float) ($service->pivot->quantity ?? 0), 2, '.', ' ') }}</td>
                            <td>{{ number_format((float) ($service->base_Price ?? 0), 2, '.', ' ') }}</td>
                            <td>{{ number_format((float) ($service->pivot->individual_total ?? 0), 2, '.', ' ') }}</td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
            <tr>
                <td colspan="3" class="Sous-total">
                    <span>Sous-total</span>
                </td>
                <td>
                    @php($currency = $type === 'invoice' ? $invoice->client?->currency ?? 'MAD' : $quote->client?->currency ?? 'MAD')
                    @if ($type === 'invoice')
                        {{ number_format((float) ($invoice->total_amount ?? 0), 2, '.', ' ') }}
                    @else
                        {{ number_format((float) ($quote->total_amount ?? 0), 2, '.', ' ') }}
                    @endif
                </td>
            </tr>
        </table>
        <div class="totals">
            <div class="notes">
                @if ($type === 'invoice')
                    {{ $invoice->notes ?? 'Merci de votre confiance.' }}
                @else
                    {{ $quote->notes ?? 'Merci de votre confiance.' }}
                @endif
            </div>
            <div class="total_container">
                <span>Total TTC</span>
                @php($currency = $type === 'invoice' ? $invoice->client->currency ?? 'MAD' : $quote->client->currency ?? 'MAD')
                <span>
                    @if ($type === 'invoice')
                        {{ number_format((float) ($invoice->total_amount ?? 0), 2, '.', ' ') }}
                        {{ $currency }}
                    @else
                        {{ number_format((float) ($quote->total_amount ?? 0), 2, '.', ' ') }} {{ $currency }}
                    @endif
                </span>
            </div>
        </div>

        <div class="last">


            <div class="payment-info">
                <div class="bank-details">
                    <p> <b><strong class="mode">Mode de paiement :</strong></b> Par virement ou Chèque</p>
                    <div class="bank-info">
                        <p><strong>Banque :</strong> ATTIJARI WAFABANK</p>
                        <p><strong>Nom du compte :</strong> LAHZA HM</p>
                        <p><strong>R.I.B :</strong> 007640001433200000026029</p>
                        <p><strong>SWIFT :</strong> BCMAMAMC</p>
                        <p><strong>ICE :</strong> 002 056 959 000 039</p>
                        <p><strong>RC :</strong> 88049</p>
                    </div>
                </div>

            </div>

            <div class="footer">
                <div class="conditions">
                    <strong>Conditions d'utilisation</strong><br>
                    En signant la facture, le client accepte sans réserves nos conditions. Pour plus d'informations,
                    consultez les politiques de notre entreprise sur : https://lahza.ma/politique-de-confidentialite/
                </div>
            </div>
            <div class="signatures">
                <div class="admin_sign">
                    <img src="{{ public_path('images/admin_signature.png') }}" alt="Admin Signature"
                        style="width:200px;">

                </div>
                <div class="client_sign">
                    @if ($clientSignatureBase64)
                        <img src="{{ $clientSignatureBase64 }}" alt="Client Signature" style="width:200px;">
                    @endif
                </div>
            </div>
        </div>
    </div>
</body>

</html>
