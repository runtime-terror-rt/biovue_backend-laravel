@extends('emails.layouts.master')

@section('title', 'Password Reset OTP')

@section('content')
    <h2 style="font-size: 20px; margin-bottom: 15px; color: #111;">Hi {{ $user->name }},</h2>
    
    <p>You requested to reset your password for <strong>BioVue</strong>. Your OTP code is:</p>
    
    <div style="text-align: center; margin: 25px 0;">
        <div style="display: inline-block; background-color: #f3f4f6; border-radius: 8px; padding: 15px 25px; font-size: 26px; font-weight: 700; letter-spacing: 6px; text-align: center; border: 1px solid #e5e7eb;">
            {{ $otp }}
        </div>
    </div>

    <p>This OTP will expire in <strong>5 minutes</strong>. Use this code to complete your password reset process.</p>
    <p>If you did not request a password reset, please ignore this email.</p>

    <p>Thanks,<br><strong>{{ config('app.name') }} Team</strong></p>
@endsection