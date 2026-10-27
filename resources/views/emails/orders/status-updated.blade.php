@extends('emails.layouts.transactional')

@section('title', 'Order Status Updated')

@section('content')
    <p class="heading">Your order status has changed</p>
    <p>Order <strong>{{ $order->order_number }}</strong> is now <strong>{{ ucfirst($order->status->value) }}</strong>.</p>

    @if ($order->status->value === 'refunded')
        <p>Your refund has been processed. It may take a few business days to reflect, depending on your payment method.</p>
    @endif
@endsection
