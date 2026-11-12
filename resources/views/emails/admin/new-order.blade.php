@extends('emails.layouts.transactional')

@section('title', 'New Order')

@section('content')
    <p class="heading">New order received</p>
    <p>Order <strong>{{ $order->order_number }}</strong> was just placed for ৳{{ number_format($order->total, 2) }} via {{ strtoupper($order->payment_method->value) }}.</p>

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
