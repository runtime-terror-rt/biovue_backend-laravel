@extends('emails.layouts.master')

@section('title', 'Session Scheduled')

@section('content')
    <h3 style="font-size: 18px; color: #333;">Hello {{ $notifiable->name }},</h3>
    <p>Your trainer has <strong>{{ $status_type }}</strong> a check-in session for you. Here are the details:</p>
    
    <div class="info-box">
        <p style="margin: 5px 0;"><strong>📅 Date:</strong> {{ $schedule->schedule_date }}</p>
        <p style="margin: 5px 0;"><strong>⏰ Time:</strong> {{ $schedule->schedule_time }}</p>
        <p style="margin: 5px 0;"><strong>📋 Type:</strong> {{ ucfirst($schedule->check_in_type) }}</p>
    </div>

    <div class="btn-container">
        <a href="https://biovuedigitalwellness.com/user-dashboard" class="btn-primary">View Dashboard</a>
    </div>

    <p>Regards,<br><strong>{{ config('app.name') }} Team</strong></p>
@endsection