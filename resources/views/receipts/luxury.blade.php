<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Serana Atelier Receipt #{{ $order->id }}</title>
    <style>
        @page { margin: 0; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            background-color: #050505;
            color: #e5e2e1;
            margin: 0;
            padding: 40px;
            font-size: 14px;
        }
        .header {
            text-align: center;
            border-bottom: 1px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .brand {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 5px;
            text-transform: uppercase;
            color: #b9c3ff;
        }
        .receipt-title {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 3px;
            color: #888;
            margin-top: 10px;
        }
        .grid {
            width: 100%;
            margin-bottom: 40px;
        }
        .col {
            vertical-align: top;
            width: 50%;
        }
        .label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #b9c3ff;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .value {
            font-size: 13px;
            font-weight: normal;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .table th {
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 2px;
            border-bottom: 1px solid #333;
            padding: 10px 0;
            color: #b9c3ff;
        }
        .table td {
            padding: 15px 0;
            border-bottom: 1px dotted #222;
        }
        .total-row {
            margin-top: 30px;
            text-align: right;
        }
        .total-amount {
            font-size: 28px;
            font-weight: bold;
            color: #b9c3ff;
            margin-top: 5px;
        }
        .mpesa-badge {
            background-color: #1d211d;
            border: 1px solid #016e00;
            color: #4fb33f;
            padding: 15px;
            margin-top: 40px;
            border-radius: 4px;
        }
        .footer {
            margin-top: 60px;
            text-align: center;
            font-size: 10px;
            color: #666;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">Serana Atelier</div>
        <div class="receipt-title">Official Transaction Record</div>
    </div>

    <table class="grid">
        <tr>
            <td class="col">
                <div class="label">Billed To</div>
                <div class="value">{{ $order->full_name }}</div>
                <div class="value">{{ $order->email }}</div>
                <div class="value">{{ $order->phone }}</div>
            </td>
            <td class="col" style="text-align: right;">
                <div class="label">Invoice Details</div>
                <div class="value">Reference: ORD-{{ strtoupper(substr($order->id, 0, 8)) }}</div>
                <div class="value">Issued: {{ $date }}</div>
                <div class="value">Status: PAID</div>
            </td>
        </tr>
    </table>

    <table class="table">
        <thead>
            <tr>
                <th>Piece Description</th>
                <th style="text-align: center;">Quantity</th>
                <th style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td>
                    <div style="font-weight: bold; color: #fff;">{{ $item['name'] ?? 'Custom Piece' }}</div>
                    <div style="font-size: 10px; color: #888;">Precision Crafted Atelier Selection</div>
                </td>
                <td style="text-align: center;">{{ $item['quantity'] ?? 1 }}</td>
                <td style="text-align: right;">Ksh {{ number_format(($item['price'] ?? 0) * ($item['quantity'] ?? 1), 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-row">
        <div class="label">Total Amount Paid</div>
        <div class="total-amount">Ksh {{ number_format($order->price_quoted, 2) }}</div>
    </div>

    <div class="mpesa-badge">
        <div class="label" style="color: #4fb33f; margin-bottom: 8px;">M-Pesa Verification</div>
        <table style="width: 100%;">
            <tr>
                <td>
                    <div style="font-size: 10px;">Transaction ID</div>
                    <div style="font-weight: bold; color: #fff;">{{ $order->mpesa_receipt_number ?? 'M-PESA-VERIFIED' }}</div>
                </td>
                <td style="text-align: right;">
                    <div style="font-size: 10px;">Network Status</div>
                    <div style="font-weight: bold; color: #fff;">SUCCESSFUL</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Serana Atelier &copy; {{ date('Y') }} &bull; Nairobi, Kenya &bull; Concierge: +254 712 345678
    </div>
</body>
</html>
