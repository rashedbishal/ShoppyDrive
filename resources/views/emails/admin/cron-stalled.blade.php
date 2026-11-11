@extends('emails.layouts.transactional')

@section('title', 'Background tasks stopped')

@section('content')
    <p class="heading">Background tasks have stopped</p>
    <p>The scheduled task that runs every minute (cron) has not run for about <strong>{{ $minutesSilent }} minutes</strong>.</p>
    <p>Until it runs again, customer order emails and courier status updates are <strong>not being sent</strong>. Orders themselves are still being placed normally.</p>
    <p>Please check the Cron Jobs page in your Hostinger hPanel and make sure the Laravel scheduler job is still there and enabled.</p>

    <a href="{{ route('admin.monitor.index') }}" class="btn">Open System Monitor</a>
@endsection
