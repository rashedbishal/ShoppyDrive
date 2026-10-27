@extends('emails.layouts.transactional')

@section('title', 'Order Shipped')

@php
    $tracking = $order->tracking_number ?: $order->steadfast_tracking_code ?: $order->pathao_consignment_id;
@endphp

@section('content')
    <p class="heading">Your order is on its way!</p>
    <p>Order <strong>{{ $order->order_number }}</strong> has shipped.</p>

    @if ($tracking)
        <p>Tracking number: <strong>{{ $tracking }}</strong></p>
    @endif

    <a href="{{ route('order.track') }}" class="btn">Track your order</a>
@endsection
