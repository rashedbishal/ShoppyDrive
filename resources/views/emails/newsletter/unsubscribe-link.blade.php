@extends('emails.layouts.transactional')

@section('title', 'Manage your newsletter subscription')

@section('content')
    <p class="heading">Manage your subscription</p>
    <p>You asked for a link to manage your newsletter subscription for <strong>{{ $subscriberEmail }}</strong>.</p>
    <p>
        <a href="{{ $unsubscribeUrl }}" class="btn">Unsubscribe</a>
    </p>
    <p>If you didn't request this, you can safely ignore this email — your subscription won't change unless you use the link above.</p>
@endsection
