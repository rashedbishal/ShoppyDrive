@extends('emails.layouts.transactional')

@section('title', 'Return Request Update')

@section('content')
    <p class="heading">Your return request could not be approved</p>
    <p>Your return request for order <strong>{{ $returnRequest->order->order_number }}</strong> could not be approved.</p>

    @if ($returnRequest->admin_note)
        <p>Reason: {{ $returnRequest->admin_note }}</p>
    @endif

    <p>If you have any questions, just reply to this email and we'll help.</p>
@endsection
