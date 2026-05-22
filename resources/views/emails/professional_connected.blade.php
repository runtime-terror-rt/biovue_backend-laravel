@extends('emails.layouts.master')

@section('title', 'New User Connected')

@section('content')
    <h2 style="font-size: 20px; margin-bottom: 15px; color: #111;">Hello {{ $professional->name }},</h2>

    <p>A new user has successfully connected with you on BioVue.</p>

    <div class="info-box">
        <p style="margin: 0 0 10px 0;"><strong>User Details:</strong></p>
        <ul style="margin: 0; padding-left: 20px; color: #4a4a4a;">
            <li><strong>Name:</strong> {{ $connectedUser->name }}</li>
            <li><strong>Email:</strong> {{ $connectedUser->email }}</li>
        </ul>
    </div>

    <p>You can now review their wellness reports and configure goals from your professional dashboard.</p>

    <div class="btn-container">
        <a href="{{ config('app.url') . '/trainer-dashboard/overview' }}" class="btn-primary">
            View Dashboard
        </a>
    </div>

    <p>Thanks,<br><strong>{{ config('app.name') }} Team</strong></p>
@endsection