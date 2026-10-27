@extends('emails.layouts.transactional')

@section('title', 'Welcome')

@section('content')
    <p class="heading">Welcome, {{ $user->name }}!</p>
    <p>Your email is verified and your account is ready to go. Happy shopping!</p>

    <a href="{{ route('home') }}" class="btn">Start browsing</a>
@endsection
