@extends('emails.layouts.transactional')

@section('title', 'Thanks for subscribing')

@section('content')
    <p class="heading">Thanks for subscribing!</p>
    <p>You're on the list. From now on, <strong>{{ $subscriberEmail }}</strong> will be among the first to hear about new arrivals, sales and exclusive deals.</p>

    <a href="{{ route('home') }}" class="btn">Start shopping</a>

    <p>We only send useful updates — no spam.</p>
    <p style="font-size:13px;color:#6b7280;">
        Changed your mind, or didn't sign up yourself?
        <a href="{{ $unsubscribeUrl }}" style="color:#6b7280;">Unsubscribe here</a>
        — it takes one click to confirm.
    </p>
@endsection
