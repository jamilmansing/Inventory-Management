<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Inventory Report</title>
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
        .low-stock {
            color: #e53e3e;
            font-weight: bold;
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
        <h1>Inventory Report</h1>
        <p>Generated on {{ now()->format('F d, Y H:i:s') }}</p>
    </div>

    <div class="summary">
        <div class="summary-box">
            <h3>Total Products</h3>
            <p>{{ $products->count() }}</p>
        </div>
        <div class="summary-box">
            <h3>Total Stock Value</h3>
            <p>${{ number_format($products->sum(function($product) { return $product->price * $product->quantity; }), 2) }}</p>
        </div>
        <div class="summary-box">
            <h3>Low Stock Items</h3>
            <p>{{ $products->where('quantity', '<', 10)->count() }}</p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>SKU</th>
                <th>Category</th>
                <th>Price</th>
                <th>Cost</th>
                <th>Quantity</th>
                <th>Stock Value</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($products as $product)
                <tr>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->sku }}</td>
                    <td>{{ $product->category->name }}</td>
                    <td>${{ number_format($product->price, 2) }}</td>
                    <td>${{ number_format($product->cost, 2) }}</td>
                    <td class="{{ $product->quantity < 10 ? 'low-stock' : '' }}">{{ $product->quantity }}</td>
                    <td>${{ number_format($product->price * $product->quantity, 2) }}</td>
                    <td>{{ ucfirst($product->status) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>This report is generated from the Inventory Management System. All data is accurate as of the generation date.</p>
    </div>
</body>
</html>