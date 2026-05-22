@extends('emails.layouts.master')

@section('title', 'Password Reset Successful')

@section('content')
    <h2 style="font-size: 20px; margin-bottom: 15px; color: #111;">Hi {{ $user->fullname ?? $user->name }},</h2>
    
    <p>We wanted to let you know that your password has been successfully reset.</p>

    <div class="info-box" style="border-left-color: #10b981; background-color: #f0fdf4;">
        <p style="margin: 0; color: #14532d; font-weight: 500;">
            ✅ Your account is now secured with the new password.
        </p>
    </div>

    <p>If you did not perform this action, please secure your account or contact our support team immediately.</p>

    <div class="btn-container">
        <a href="{{ config('app.url') . '/login' }}" class="btn-primary">Login to My Account</a>
    </div>

    <p>Thanks,<br><strong>{{ config('app.name') }} Team</strong></p>
@endsection