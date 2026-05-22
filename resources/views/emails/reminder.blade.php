@extends('emails.layouts.master')

@section('title', 'Reminder Notification')

@section('content')
    <h2 style="font-size: 20px; margin-bottom: 15px; color: #111;">Hello!</h2>
    
    <p>{{ $bodyMessage }}</p>

    <div class="btn-container">
        <a href="{{ url('https://biovuedigitalwellness.com/pricing') }}" class="btn-primary">Check My Plan</a>
    </div>

    <p>Thanks,<br><strong>{{ config('app.name') }} Team</strong></p>
@endsection