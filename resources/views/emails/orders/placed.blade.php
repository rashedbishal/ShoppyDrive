@extends('emails.layouts.transactional')

@section('title', 'Order Confirmed')

@section('content')
    <p class="heading">Thanks for your order, {{ $order->shipping_address['name'] ?? 'there' }}!</p>
    <p>We've received your order <strong>{{ $order->order_number }}</strong> and we're getting it ready.</p>

    <table class="items">
        <tr>
            <th>Item</th>
            <th>Qty</th>
            <th class="num">Total</th>
        </tr>
        @foreach ($order->items as $item)
            <tr>
                <td>{{ $item->product_name }}</td>
                <td>{{ $item->quantity }}</td>
                <td class="num">৳{{ number_format($item->subtotal, 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="2">Total</td>
            <td class="num">৳{{ number_format($order->total, 2) }}</td>
        </tr>
    </table>

    <p>Payment method: {{ strtoupper($order->payment_method->value) }}</p>
    <p>We'll email you again once it ships.</p>
@endsection
