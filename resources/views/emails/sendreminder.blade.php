@extends('emails.layouts.master')

@section('title', $title ?? 'New Reminder')

@section('content')
    <h2 style="font-size: 20px; margin-bottom: 15px; color: #111;">Hello, {{ $notifiable->name }}!</h2>
    
    <p>You have received a new reminder notification from BioVue.</p>
    
    <div class="info-box">
        <h3 style="margin-top: 0; font-size: 18px; color: #1b1b18;">{{ $title }}</h3>
        <p style="margin: 10px 0; color: #4a4a4a;">{{ $body }}</p>
        <p style="margin: 0; font-size: 14px;">Type: <strong>{{ ucfirst($type) }}</strong></p>
    </div>

    <div class="btn-container">
        <a href="https://biovuedigitalwellness.com" class="btn-primary">Check Dashboard</a>
    </div>

    <p>Thanks,<br><strong>{{ config('app.name') }} Team</strong></p>
@endsection