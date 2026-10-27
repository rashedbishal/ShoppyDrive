@extends('emails.layouts.transactional')

@section('title', 'Review Published')

@section('content')
    <p class="heading">Thanks for your review!</p>
    <p>Your {{ $review->rating }}-star review of <strong>{{ $review->product->name }}</strong> is now live on the product page.</p>

    @if ($review->points_awarded_at)
        <p>We've also credited loyalty points to your account for sharing your feedback.</p>
    @endif
@endsection
