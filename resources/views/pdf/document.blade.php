<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture INV-000634</title>
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
            font-size: 14px;
            font-weight: 900;
            margin-bottom: 8px;
        }

        .client-info p {
            font-size: 13px;
            line-height: 1.6;
            color: #222;
            font-weight: 900;
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

        .total-div {
            width: 50%;
            font-weight: 900;
            font-size: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ebe6e6;
            display: flex;
            align-items: center;
            justify-content: space-between;
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

        .thank-you {
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
            border: 1px solid #051630;
            padding: 20px 30px;
        }

        .signatures .client_sign {
            border: 1px solid #051630;
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
        }

        .client-info>div:nth-child(2) {
            display: inline-block;
            vertical-align: top;
            padding: 5px;
            position: absolute;
            right: 0;
            top: 0;
            text-align: right;
            width: 100px;
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
    </style>
</head>

<body>
    <div class="invoice-container">
        <div class="header">
            <div class="company-info">
                <div class="company-logo">
                    <img src="{{ asset('logo.png') }}" alt="lahza logo">
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
            <h2>FACTURE</h2>
        </div>

        <div class="client-invoice-info">
            <div class="client-info">
                <div>
                    <h3>NEWERA</h3>
                    <p>
                        7 Angle Rue de Fès & Rue de Uruguay, 5ème Ét. N°21,<br>
                        Tanger<br>
                        90000, Morocco<br>
                        SIREN (ou ICE) :
                    </p>
                </div>
                <div>
                    <p>N° de facture</p>
                    <h3>INV-000634</h3>
                </div>
            </div>
            <div class="invoice-details">
                <table border="1" class="one">
                    <thead>
                        <tr>
                            <th>Date de facture</th>
                            <th>Date d'échéance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>06 août 2025</td>
                            <td>06 août 2025</td>
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
                <tr>
                    <td>
                        <strong>Serveur d'hébergement WEB - Full Pack</strong>
                        <div class="item-description">
                            - Offre de 100 Dh / mois<br>
                            - Nom de domaine Gratuit<br>
                            - Certificat de sécurité SSL<br>
                            - Espace du serveur 80GB<br>
                            - Sous domaine illimités<br>
                            - Databases illimité<br>
                            - Web mail Active<br>
                            - Adresses mail professionnels<br>
                            - Système LiteSpeed<br>
                            - Sauvegarde automatique 24h / 24<br>
                            - Sécurité du Serveur 24/h<br>
                            - FTP & SFTP Access
                        </div>
                    </td>
                    <td>1.00</td>
                    <td>1,200.00</td>
                    <td>1,440.00</td>
                </tr>
            </tbody>
            <tr>
                <td colspan="3" class="Sous-total">
                    <span>Sous-total</span>
                </td>
                <td>1,440.00</td>
            </tr>
        </table>

        <div class="totals">
            <div class="thank-you">
                Merci de votre confiance.
            </div>
            <div class="total-div">
                <span>Total TTC</span>
                <span>1,440.00 MAD</span>
            </div>
        </div>



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
            <div class="admin_sign">admin signature</div>
            <div class="client_sign">client signature </div>
        </div>
    </div>
</body>

</html>
