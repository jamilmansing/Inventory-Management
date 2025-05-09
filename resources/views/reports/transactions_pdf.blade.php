<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Transactions Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .header p {
            font-size: 14px;
            color: #666;
        }
        .summary {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
        }
        .summary-box {
            border: 1px solid #ddd;
            padding: 10px;
            width: 30%;
            text-align: center;
        }
        .summary-box h3 {
            margin: 0 0 5px 0;
            font-size: 14px;
        }
        .summary-box p {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .purchase {
            color: #2f855a;
        }
        .sale {
            color: #3182ce;
        }
        .adjustment {
            color: #d69e2e;
        }
        .footer {
            text-align: center;
            font-size: 10px;
            color: #666;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Transactions Report</h1>
        <p>Generated on {{ now()->format('F d, Y H:i:s') }}</p>
    </div>

    <div class="summary">
        <div class="summary-box">
            <h3>Total Purchases</h3>
            <p>${{ number_format($totals['purchases'], 2) }}</p>
        </div>
        <div class="summary-box">
            <h3>Total Sales</h3>
            <p>${{ number_format($totals['sales'], 2) }}</p>
        </div>
        <div class="summary-box">
            <h3>Total Adjustments</h3>
            <p>${{ number_format($totals['adjustments'], 2) }}</p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Type</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Total</th>
                <th>Reference</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $transaction)
                <tr>
                    <td>{{ $transaction->product->name }}</td>
                    <td class="{{ $transaction->type }}">{{ ucfirst($transaction->type) }}</td>
                    <td>{{ $transaction->quantity }}</td>
                    <td>${{ number_format($transaction->unit_price, 2) }}</td>
                    <td>${{ number_format($transaction->total_price, 2) }}</td>
                    <td>{{ $transaction->reference }}</td>
                    <td>{{ $transaction->created_at->format('M d, Y H:i') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>This report is generated from the Inventory Management System. All data is accurate as of the generation date.</p>
    </div>
</body>
</html>