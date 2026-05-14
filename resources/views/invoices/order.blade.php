<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #{{ $order->order_number }}</title>
    <style>
        @page {
            margin: 0;
            size: A4;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            color: #1f2937;
            margin: 0;
            padding: 0;
            line-height: 1.45;
        }

        .top-bar {
            background-color: #ffffff;
            padding: 18px 36px;
            border-bottom: 2px solid #e2e8f0;
        }
        .top-bar-table {
            width: 100%;
        }
        .top-bar-table td {
            vertical-align: middle;
        }
        .brand {
            font-size: 20pt;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 1px;
        }
        .invoice-word {
            font-size: 18pt;
            font-weight: bold;
            color: #0f172a;
            text-align: right;
            letter-spacing: 4px;
        }

        .page-body {
            padding: 22px 36px 20px 36px;
        }

        .meta-outer {
            width: 100%;
            margin-bottom: 18px;
        }
        .meta-outer td {
            vertical-align: top;
            width: 50%;
        }
        .col-label {
            font-size: 6.5pt;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #94a3b8;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .info-panel {
            background-color: #f8fafc;
            border-left: 3px solid #0f172a;
            padding: 10px 13px;
            font-size: 8.5pt;
        }
        .info-panel .cust-name {
            font-weight: bold;
            font-size: 10pt;
            color: #0f172a;
            margin-bottom: 3px;
        }
        .info-panel p {
            margin: 2px 0;
            color: #4b5563;
        }
        .detail-row td {
            padding: 3px 0;
            font-size: 8.5pt;
            vertical-align: top;
        }
        .dl {
            color: #6b7280;
            width: 52%;
        }
        .dv {
            font-weight: bold;
            text-align: right;
        }

        .rule {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 0 0 14px 0;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .items thead tr {
            background-color: #0f172a;
        }
        .items thead th {
            padding: 7px 9px;
            font-size: 7pt;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #cbd5e1;
            font-weight: bold;
        }
        .items tbody td {
            padding: 7px 9px;
            font-size: 8.5pt;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }
        .items tbody tr.even td {
            background-color: #f8fafc;
        }
        .prod-name {
            font-weight: bold;
            color: #0f172a;
            font-size: 8.5pt;
        }
        .prod-meta {
            font-size: 7pt;
            color: #6b7280;
            margin-top: 2px;
        }
        .items tbody tr {
            page-break-inside: avoid;
        }

        .tl {
            color: #6b7280;
            text-align: right;
            padding-right: 6px;
        }
        .tv {
            text-align: right;
            font-weight: bold;
            width: 22%;
        }
        .disc {
            color: #059669;
        }
        .items tfoot td {
            padding: 6px 9px;
            font-size: 8.5pt;
            border-bottom: 1px solid #f1f5f9;
        }
        .items tfoot tr.grand-row td {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: bold;
            font-size: 9.5pt;
            border-bottom: none;
            padding: 8px 9px;
        }
        .items tfoot tr.grand-row td.tl {
            color: #94a3b8;
        }

        .badge {
            display: inline-block;
            padding: 2px 7px;
            background-color: #d1fae5;
            color: #065f46;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            border-radius: 3px;
        }

        .footer {
            margin-top: 24px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 7.5pt;
            color: #94a3b8;
        }
        .footer p {
            margin: 2px 0;
        }

        .tr { text-align: right; }
        .tc { text-align: center; }
    </style>
</head>
<body>

<div class="top-bar">
    <table class="top-bar-table">
        <tr>
            <td>
                @if(file_exists(public_path('Shoppydrive.jpg')))
                    <img src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(public_path('Shoppydrive.jpg'))) }}" alt="ShoppyDrive" style="max-height: 80px; width: auto;">
                @else
                    <div class="brand">ShoppyDrive</div>
                @endif
            </td>
            <td class="invoice-word">INVOICE</td>
        </tr>
    </table>
</div>

<div class="page-body">

    @php
        $addr = $order->billing_address ?? $order->shipping_address ?? [];
        $addrLine = ($addr['address_line1'] ?? '');
        if (!empty($addr['address_line2'])) {
            $addrLine .= ', ' . $addr['address_line2'];
        }
        $cityLine = ($addr['city'] ?? '');
        if (!empty($addr['state'])) {
            $cityLine .= ', ' . $addr['state'];
        }
        if (!empty($addr['postal_code'])) {
            $cityLine .= ' ' . $addr['postal_code'];
        }
        $paymentMethod = strtoupper(str_replace('_', ' ', $order->payment_method->value ?? $order->payment_method));
        $paymentStatus = $order->payment_status->value ?? $order->payment_status;
    @endphp

    <table class="meta-outer">
        <tr>
            <td style="padding-right: 13px;">
                <div class="col-label">Billed To</div>
                <div class="info-panel">
                    <div class="cust-name">{{ $addr['name'] ?? $order->user?->name ?? '' }}</div>
                    @if(!empty($addr['phone']))
                        <p>{{ $addr['phone'] }}</p>
                    @endif
                    @if($addrLine)
                        <p>
                            {{ $addrLine }}<br>
                            {{ $cityLine }}<br>
                            {{ $addr['country'] ?? '' }}
                        </p>
                    @endif
                    @php $email = !empty($addr['email']) ? $addr['email'] : $order->user?->email; @endphp
                    @if($email)
                        <p>{{ $email }}</p>
                    @endif
                </div>
            </td>
            <td style="padding-left: 13px;">
                <div class="col-label">Invoice Details</div>
                <div class="info-panel">
                    <table style="width: 100%;">
                        <tr class="detail-row">
                            <td class="dl">Order Number</td>
                            <td class="dv">{{ $order->order_number }}</td>
                        </tr>
                        <tr class="detail-row">
                            <td class="dl">Date Issued</td>
                            <td class="dv">{{ $order->created_at->format('d M Y') }}</td>
                        </tr>
                        <tr class="detail-row">
                            <td class="dl">Payment Method</td>
                            <td class="dv">{{ $paymentMethod }}</td>
                        </tr>
                        <tr class="detail-row">
                            <td class="dl">Payment Status</td>
                            <td class="dv">
                                <span class="badge">{{ $paymentStatus }}</span>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <hr class="rule">

    <table class="items">
        <thead>
            <tr>
                <th style="width: 4%; text-align: left;">#</th>
                <th style="width: 46%; text-align: left;">Item Description</th>
                <th style="width: 18%; text-align: right;">Unit Price</th>
                <th style="width: 10%;" class="tc">Qty</th>
                <th style="width: 22%; text-align: right;">Amount (BDT)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $index => $item)
                <tr class="{{ $index % 2 === 1 ? 'even' : '' }}">
                    <td style="color: #94a3b8;">{{ $index + 1 }}</td>
                    <td>
                        <div class="prod-name">{{ $item->product_name ?? $item->product->name }}</div>
                        @if(!empty($item->variant['attributes']))
                            <div class="prod-meta">
                                @foreach($item->variant['attributes'] as $key => $value)
                                    {{ $key }}: {{ $value }}
                                    @if(!$loop->last)
                                        &nbsp;|&nbsp;
                                    @endif
                                @endforeach
                            </div>
                        @endif
                        @if($item->sku)
                            <div class="prod-meta">SKU: {{ $item->sku }}</div>
                        @endif
                    </td>
                    <td class="tr">{{ number_format($item->price, 2) }}</td>
                    <td class="tc">{{ $item->quantity }}</td>
                    <td class="tr" style="font-weight: bold;">{{ number_format($item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="tl">Subtotal</td>
                <td class="tv">{{ number_format($order->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td colspan="4" class="tl">Shipping</td>
                <td class="tv">{{ number_format($order->shipping_cost, 2) }}</td>
            </tr>
            @if($order->tax > 0)
                <tr>
                    <td colspan="4" class="tl">Tax</td>
                    <td class="tv">{{ number_format($order->tax, 2) }}</td>
                </tr>
            @endif
            @if($order->discount > 0)
                <tr>
                    <td colspan="4" class="tl">
                        Discount
                        @if($order->coupon_code)
                            ({{ $order->coupon_code }})
                        @endif
                    </td>
                    <td class="tv disc">- {{ number_format($order->discount, 2) }}</td>
                </tr>
            @endif
            <tr class="grand-row">
                <td colspan="4" class="tl">Grand Total (BDT)</td>
                <td class="tv">{{ number_format($order->total, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p>Thank you for shopping with ShoppyDrive!</p>
        <p>Questions? &nbsp; support@shoppydrive.com</p>
    </div>

</div>
</body>
</html>
