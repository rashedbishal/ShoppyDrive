@extends('emails.layouts.transactional')

@section('title', 'Return Approved')

@section('content')
    <p class="heading">Your return request has been approved</p>
    <p>Your return request for order <strong>{{ $returnRequest->order->order_number }}</strong> has been approved.</p>

    @if ($returnRequest->admin_note)
        <p>Note from our team: {{ $returnRequest->admin_note }}</p>
    @endif

    <p>We'll be in touch with next steps on returning the item(s) and processing your refund.</p>
@endsection
