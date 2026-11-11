@extends('emails.layouts.transactional')

@section('title', 'Background tasks running again')

@section('content')
    <p class="heading">Background tasks are running again</p>
    <p>The scheduled task (cron) is running again. Queued customer emails and courier updates will now be sent.</p>

    <a href="{{ route('admin.monitor.index') }}" class="btn">Open System Monitor</a>
@endsection
