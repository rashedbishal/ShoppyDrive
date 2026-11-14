<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #{{ $order->order_number }}</title>
    <style>
        /* DISC-40: A4 comes from OrderInvoice's mPDF config; mPDF mis-paginates
           a `size: A4` here (one page becomes ~160). */
        @page {
            margin: 0;
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
        /* DISC-40: mPDF draws a <div>'s background/border line by line, so
           the panels are single-cell tables and the columns get their own
           class (a descendant `td` rule would also size the inner tables). */
        .meta-col {
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
            width: 100%;
            border-collapse: collapse;
        }
        .info-panel-cell {
            background-color: #f8fafc;
            border-left: 3px solid #0f172a;
            padding: 10px 13px;
            font-size: 8.5pt;
            vertical-align: top;
        }
        .cust-name {
            font-weight: bold;
            font-size: 10pt;
            color: #0f172a;
            margin-bottom: 3px;
        }
        .info-panel-cell p {
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

        .badge-unpaid {
            background-color: #fef3c7;
            color: #92400e;
        }
        .copy-tag {
            display: block;
            margin-top: 3px;
            font-size: 7.5pt;
            letter-spacing: 2px;
            color: #64748b;
        }
        .items tfoot tr.amount-row td {
            font-weight: bold;
            font-size: 9.5pt;
            border-bottom: none;
            padding: 8px 9px;
        }
        .items tfoot tr.amount-row.paid td {
            background-color: #d1fae5;
            color: #065f46;
        }
        .items tfoot tr.amount-row.due td {
            background-color: #fef3c7;
            color: #92400e;
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
@php
    // DISC-38: normally set by App\Support\OrderInvoice; defaults keep a
    // direct render of this view (tests) working.
    $parcel ??= null;
    $amountLabel ??= \App\Support\OrderInvoice::amountLabel($order);
    $isPaid = \App\Support\OrderInvoice::isPaid($order);
@endphp

<div class="top-bar">
    <table class="top-bar-table">
        <tr>
            <td style="width: 60%;">
                @php
                    // The logo uploaded in Admin › Settings when dompdf can read it (PNG/JPG), else the bundled copy.
                    $invoiceLogo = null;
                    $cmsLogo = \App\Models\Setting::where('key', 'logo')->value('value');
                    if (is_string($cmsLogo) && str_starts_with($cmsLogo, '/storage/')) {
                        $cmsLogoFile = storage_path('app/public/'.substr($cmsLogo, 9));
                        if (is_file($cmsLogoFile) && in_array(strtolower(pathinfo($cmsLogoFile, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg'], true)) {
                            $invoiceLogo = $cmsLogoFile;
                        }
                    }
                    $invoiceLogo ??= public_path('images/brand/invoice-logo.png');
                    $invoiceLogoMime = strtolower(pathinfo($invoiceLogo, PATHINFO_EXTENSION)) === 'png' ? 'png' : 'jpeg';
                @endphp
                @if(is_file($invoiceLogo))
                    <img src="data:image/{{ $invoiceLogoMime }};base64,{{ base64_encode(file_get_contents($invoiceLogo)) }}" alt="ShoppyDrive" style="height: 56px;">
                @else
                    <div class="brand">ShoppyDrive</div>
                @endif
            </td>
            <td class="invoice-word" style="width: 40%;">
                INVOICE
                @if($parcel)
                    <div class="copy-tag">PARCEL COPY</div>
                @endif
            </td>
        </tr>
    </table>
</div>

<div class="page-body">

    @php
        $addr = $order->billing_address ?: ($order->shipping_address ?: []);
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
            <td class="meta-col" style="padding-right: 13px;">
                <div class="col-label">Billed To</div>
                <table class="info-panel"><tr><td class="info-panel-cell">
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
                </td></tr></table>
            </td>
            <td class="meta-col" style="padding-left: 13px;">
                <div class="col-label">Invoice Details</div>
                <table class="info-panel"><tr><td class="info-panel-cell">
                    <table style="width: 100%;">
                        <tr class="detail-row">
                            <td class="dl">Order Number</td>
                            <td class="dv">{{ $order->order_number }}</td>
                        </tr>
                        <tr class="detail-row">
                            <td class="dl">Date Issued</td>
                            <td class="dv">{{ $order->created_at->copy()->timezone('Asia/Dhaka')->format('d M Y') }}</td>
                        </tr>
                        <tr class="detail-row">
                            <td class="dl">Payment Method</td>
                            <td class="dv">{{ $paymentMethod }}</td>
                        </tr>
                        <tr class="detail-row">
                            <td class="dl">Payment Status</td>
                            <td class="dv">
                                <span class="{{ $isPaid ? 'badge' : 'badge badge-unpaid' }}">{{ $paymentStatus }}</span>
                            </td>
                        </tr>
                        @if($parcel)
                            <tr class="detail-row">
                                <td class="dl">Courier</td>
                                <td class="dv">{{ $parcel['courier'] }}</td>
                            </tr>
                            <tr class="detail-row">
                                <td class="dl">Consignment ID</td>
                                <td class="dv">{{ $parcel['consignment_id'] }}</td>
                            </tr>
                            @if($parcel['tracking_code'])
                                <tr class="detail-row">
                                    <td class="dl">Tracking Code</td>
                                    <td class="dv">{{ $parcel['tracking_code'] }}</td>
                                </tr>
                            @endif
                        @endif
                    </table>
                </td></tr></table>
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
                        @if($item->variantLabel())
                            <div class="prod-meta">{{ $item->variantLabel() }}</div>
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
            <tr class="amount-row {{ $isPaid ? 'paid' : 'due' }}">
                <td colspan="4" class="tl">{{ $amountLabel }} (BDT)</td>
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
