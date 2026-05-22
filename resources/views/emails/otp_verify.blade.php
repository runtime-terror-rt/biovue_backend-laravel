@extends('emails.layouts.master')

@section('title', 'Email Verification')

@section('content')
    <h2 style="font-size: 20px; margin-bottom: 15px; color: #111;">Hi {{ $user->name }},</h2>
    
    <p>Thank you for registering with <strong>BioVue</strong>. Your email verification OTP code is:</p>
    
    <div style="text-align: center; margin: 25px 0;">
        <div style="display: inline-block; background-color: #f3f4f6; border-radius: 8px; padding: 15px 25px; font-size: 26px; font-weight: 700; letter-spacing: 6px; text-align: center; border: 1px solid #e5e7eb; color: #1b1b18;">
            {{ $otp }}
        </div>
    </div>

    <p>This OTP is valid for a limited time. Please use this code to complete your registration process.</p>

    <p>Thanks,<br><strong>{{ config('app.name') }} Team</strong></p>
@endsection