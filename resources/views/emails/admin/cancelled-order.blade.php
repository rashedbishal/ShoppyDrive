@extends('emails.layouts.transactional')

@section('title', 'Order Cancelled')

@section('content')
    <p class="heading">Order cancelled</p>
    <p>Order <strong>{{ $order->order_number }}</strong> (৳{{ number_format($order->total, 2) }}, {{ strtoupper($order->payment_method->value) }}) has been cancelled.</p>

    @if ($order->cancellation_reason)
        <p>Reason: {{ $order->cancellation_reason }}</p>
    @endif

    <table class="items">
        <tr>
            <th>Item</th>
            <th>Qty</th>
            <th class="num">Total</th>
        </tr>
        @foreach ($order->items as $item)
            <tr>
                <td>
                    {{ $item->product_name }}
                    @if ($item->variantLabel())
                        <br><span style="color: #64748b; font-size: 12px;">{{ $item->variantLabel() }}</span>
                    @endif
                </td>
                <td>{{ $item->quantity }}</td>
                <td class="num">৳{{ number_format($item->subtotal, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <p>Customer: {{ $order->shipping_address['name'] ?? 'N/A' }} ({{ $order->shipping_address['phone'] ?? 'N/A' }})</p>

    <a href="{{ route('admin.orders.show', $order) }}" class="btn">View order</a>
@endsection
