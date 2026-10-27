@extends('emails.layouts.transactional')

@section('title', 'Order Cancelled')

@section('content')
    <p class="heading">Your order has been cancelled</p>
    <p>Order <strong>{{ $order->order_number }}</strong> has been cancelled.</p>

    @if ($order->cancellation_reason)
        <p>Reason: {{ $order->cancellation_reason }}</p>
    @endif

    <p>If you didn't request this or have any questions, just reply to this email.</p>
@endsection
