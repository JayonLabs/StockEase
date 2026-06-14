<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: #2563eb;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }

        .header h2 {
            margin: 0;
        }

        .content {
            background: #fff;
            padding: 20px;
            border: 1px solid #e5e7eb;
        }

        .info {
            background: #f9fafb;
            padding: 12px;
            border-radius: 6px;
            margin: 16px 0;
        }

        .info p {
            margin: 4px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
        }

        th {
            background: #f3f4f6;
            text-align: left;
            padding: 8px 12px;
            border-bottom: 2px solid #e5e7eb;
        }

        td {
            padding: 8px 12px;
            border-bottom: 1px solid #e5e7eb;
        }

        .total {
            background: #f0fdf4;
            border: 1px solid #86efac;
            border-radius: 6px;
            padding: 12px;
            margin: 16px 0;
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            color: #166534;
        }

        .cash-info {
            background: #f9fafb;
            padding: 12px;
            border-radius: 6px;
            margin: 8px 0;
        }

        .footer {
            text-align: center;
            color: #9ca3af;
            font-size: 12px;
            margin-top: 24px;
        }

        .greeting {
            margin-bottom: 16px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h2>Invoice #{{ $sale->id }}</h2>
    </div>
    <div class="content">
        <p class="greeting">Halo{{ $sale->customer_name ? ' ' . $sale->customer_name : '' }},</p>

        <p>Terima kasih telah berbelanja di <strong>{{ config('app.name') }}</strong>. Berikut adalah detail pembelian
            Anda:</p>

        <div class="info">
            <p><strong>Tanggal:</strong> {{ $date }}</p>
            <p><strong>Metode Pembayaran:</strong> {{ $sale->payment_method === 'cash' ? 'Tunai' : 'QRIS' }}</p>
            <p><strong>Status:</strong> {{ $sale->status === 'completed' ? 'Selesai' : 'Menunggu Pembayaran' }}</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Qty</th>
                    <th>Harga</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->saleItems as $item)
                    <tr>
                        <td>{{ $item->product?->name ?? 'Produk' }}</td>
                        <td>{{ $item->qty }}</td>
                        <td>Rp {{ number_format((float) $item->price, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format((float) $item->qty * (float) $item->price, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="total">
            Total Pembayaran: Rp {{ number_format($total, 0, ',', '.') }}
        </div>

        @if ($sale->payment_method === 'cash')
            <div class="cash-info">
                <p><strong>Uang Dibayar:</strong> Rp {{ number_format((float) $sale->paid, 0, ',', '.') }}</p>
                <p><strong>Kembalian:</strong> Rp {{ number_format((float) $sale->change, 0, ',', '.') }}</p>
            </div>
        @endif

        <p>Terima kasih atas kunjungan Anda.</p>

        <p>Salam hangat,<br>{{ config('app.name') }}</p>
    </div>
    <div class="footer">
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</body>

</html>
