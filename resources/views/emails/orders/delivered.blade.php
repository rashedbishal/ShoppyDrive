@extends('emails.layouts.transactional')

@section('title', 'Order Delivered')

@section('content')
    <p class="heading">Delivered!</p>
    <p>Order <strong>{{ $order->order_number }}</strong> has been delivered. We hope you love it.</p>
    @if (\App\Support\OrderInvoice::isAvailable($order))
        <p>Your updated invoice is attached to this email as a PDF.</p>
    @endif
    <p>If anything's not right, just reply to this email and we'll sort it out.</p>
@endsection
