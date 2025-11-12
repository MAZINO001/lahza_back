<!DOCTYPE html>
<html>
<head>
    <title>Quotation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
        }
        header {
            text-align: center;
            margin-bottom: 30px;
        }
        h1 {
            margin: 0;
            font-size: 24px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid #999;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        tfoot td {
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #777;
        }
    </style>
</head>
<body>
    <header>
        <h1>Quotation from Agence LAHZA</h1>
        <p>Date: {{ date('d/m/Y') }}</p>
    </header>

    <p>From Agence LAHZA Team,</p>
    <p>We are pleased to provide you with the following quotation:</p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item/Service</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>SEO Optimization</td>
                <td>1</td>
                <td>USD 1,000.00</td>
                <td>USD 1,000.00</td>
            </tr>
            <tr>
                <td>2</td>
                <td>Graphic Design Services</td>
                <td>1</td>
                <td>USD 1,000.00</td>
                <td>USD 1,000.00</td>
            </tr>
            <tr>
                <td>3</td>
                <td>Web Development</td>
                <td>1</td>
                <td>USD 1,000.00</td>
                <td>USD 1,000.00</td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4">Grand Total</td>
                <td>USD 3,000.00</td>
            </tr>
        </tfoot>
    </table>

    <p>Please contact us if you have any questions or require further clarification.</p>

    <div class="footer">
        Thank you for considering our services.
    </div>
</body>
</html>
