@extends('emails.layouts.transactional')

@section('title', 'New Contact Message')

@section('content')
    <p class="heading">New contact message</p>
    <p><strong>From:</strong> {{ $contactMessage->name }} ({{ $contactMessage->email }})</p>
    <p><strong>Subject:</strong> {{ $contactMessage->subject }}</p>
    <p style="white-space: pre-line;">{{ $contactMessage->message }}</p>
    <p>Reply to this email to answer the customer, or see all messages in <a href="{{ route('admin.contact-messages.index') }}">Admin</a>.</p>
@endsection
