<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #{{ $order->order_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;
            color: #333;
            font-size: 14px;
            line-height: 1.5;
            margin: 0;
            padding: 20px;
        }
        .header {
            width: 100%;
            margin-bottom: 40px;
            border-bottom: 2px solid #f3f4f6;
            padding-bottom: 20px;
        }
        .header table {
            width: 100%;
        }
        .header td {
            vertical-align: top;
        }
        .logo {
            max-width: 150px;
            height: auto;
        }
        .company-info {
            text-align: right;
            font-size: 13px;
            color: #6b7280;
        }
        .company-info h2 {
            margin: 0 0 5px 0;
            color: #111827;
            font-size: 24px;
        }
        .invoice-details {
            width: 100%;
            margin-bottom: 40px;
        }
        .invoice-details table {
            width: 100%;
        }
        .invoice-details td {
            vertical-align: top;
            width: 50%;
        }
        .section-title {
            font-size: 12px;
            text-transform: uppercase;
            font-weight: bold;
            color: #9ca3af;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        .info-box {
            background-color: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            font-size: 13px;
        }
        .info-box p {
            margin: 0 0 5px 0;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        .table th {
            background-color: #f9fafb;
            font-size: 11px;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: 0.5px;
        }
        .text-right {
            text-align: right !important;
        }
        .text-center {
            text-align: center !important;
        }
        .totals-container {
            width: 100%;
        }
        .totals-table {
            width: 300px;
            float: right;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #f3f4f6;
        }
        .totals-table .total-row td {
            font-weight: bold;
            font-size: 18px;
            border-bottom: none;
            color: #111827;
            padding-top: 15px;
        }
        .footer {
            clear: both;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            background-color: #d1fae5;
            color: #065f46;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

    <div class="header">
        <table>
            <tr>
                <td>
                    @if(file_exists(public_path('Shoppydrive.jpg')))
                        <img src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(public_path('Shoppydrive.jpg'))) }}" alt="Logo" class="logo">
                    @else
                        <h2>ShoppyDrive</h2>
                    @endif
                </td>
                <td class="company-info">
                    <h2>INVOICE</h2>
                    <p>
                        ShoppyDrive Ltd.<br>
                        123 Commerce Avenue, Suite 100<br>
                        Dhaka, Bangladesh<br>
                        support@shoppydrive.com
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <div class="invoice-details">
        <table>
            <tr>
                <td style="padding-right: 15px;">
                    <div class="section-title">Billed To</div>
                    <div class="info-box">
                        <p><strong>{{ $order->billing_address['name'] ?? $order->shipping_address['name'] ?? $order->user->name }}</strong></p>
                        @if(isset($order->billing_address['phone']) || isset($order->shipping_address['phone']))
                            <p>{{ $order->billing_address['phone'] ?? $order->shipping_address['phone'] }}</p>
                        @endif
                        @if(isset($order->billing_address['address_line1']) || isset($order->shipping_address['address_line1']))
                            @php $addr = $order->billing_address ?? $order->shipping_address; @endphp
                            <p>
                                {{ $addr['address_line1'] ?? '' }}<br>
                                @if(!empty($addr['address_line2'])) {{ $addr['address_line2'] }}<br> @endif
                                {{ $addr['city'] ?? '' }}, {{ $addr['state'] ?? '' }} {{ $addr['postal_code'] ?? '' }}<br>
                                {{ $addr['country'] ?? '' }}
                            </p>
                        @endif
                        <p>{{ $order->user->email }}</p>
                    </div>
                </td>
                <td style="padding-left: 15px;">
                    <div class="section-title">Invoice Details</div>
                    <div class="info-box" style="background-color: #fff; border: 1px solid #e5e7eb;">
                        <table style="width: 100%;">
                            <tr>
                                <td style="padding: 5px 0; color: #6b7280; width: 40%;">Order Number:</td>
                                <td style="padding: 5px 0; font-weight: bold; text-align: right;">{{ $order->order_number }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 5px 0; color: #6b7280;">Date Issued:</td>
                                <td style="padding: 5px 0; font-weight: bold; text-align: right;">{{ $order->created_at->format('M d, Y') }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 5px 0; color: #6b7280;">Payment Method:</td>
                                <td style="padding: 5px 0; font-weight: bold; text-align: right; text-transform: uppercase;">{{ str_replace('_', ' ', $order->payment_method->value ?? $order->payment_method) }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 5px 0; color: #6b7280;">Payment Status:</td>
                                <td style="padding: 5px 0; text-align: right;">
                                    <span class="badge">{{ $order->payment_status->value ?? $order->payment_status }}</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 45%;">Item Description</th>
                <th class="text-center" style="width: 15%;">Unit Price</th>
                <th class="text-center" style="width: 10%;">Qty</th>
                <th class="text-right" style="width: 25%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $index => $item)
                <tr>
                    <td style="color: #6b7280;">{{ $index + 1 }}</td>
                    <td>
                        <strong style="color: #111827;">{{ $item->product_name ?? $item->product->name }}</strong>
                        @if($item->variant)
                            <div style="font-size: 11px; color: #6b7280; margin-top: 4px;">
                                @foreach($item->variant['attributes'] ?? [] as $key => $value)
                                    {{ $key }}: {{ $value }}@if(!$loop->last) | @endif
                                @endforeach
                            </div>
                        @endif
                        @if($item->sku)
                            <div style="font-size: 11px; color: #9ca3af; margin-top: 2px;">SKU: {{ $item->sku }}</div>
                        @endif
                    </td>
                    <td class="text-center">৳{{ number_format($item->price, 2) }}</td>
                    <td class="text-center">{{ $item->quantity }}</td>
                    <td class="text-right font-bold">৳{{ number_format($item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals-container">
        <table class="totals-table">
            <tr>
                <td style="color: #6b7280;">Subtotal:</td>
                <td class="text-right">৳{{ number_format($order->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td style="color: #6b7280;">Shipping:</td>
                <td class="text-right">৳{{ number_format($order->shipping_cost, 2) }}</td>
            </tr>
            <tr>
                <td style="color: #6b7280;">Tax:</td>
                <td class="text-right">৳{{ number_format($order->tax, 2) }}</td>
            </tr>
            @if($order->discount > 0)
            <tr>
                <td style="color: #6b7280;">Discount @if($order->coupon_code) ({{ $order->coupon_code }}) @endif:</td>
                <td class="text-right" style="color: #059669;">-৳{{ number_format($order->discount, 2) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td>Grand Total:</td>
                <td class="text-right">৳{{ number_format($order->total, 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Thank you for your business!</p>
        <p>If you have any questions about this invoice, please contact support@shoppydrive.com</p>
    </div>

</body>
</html>
