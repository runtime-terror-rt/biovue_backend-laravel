@extends('emails.layouts.master')

@section('title', 'Welcome to BioVue')

@section('content')
    <h2 style="font-size: 22px; color: #333; font-weight: bold; margin-bottom: 15px;">Welcome to BioVue!</h2>
    
    <p>Hello <strong>{{ $clientName }}</strong>,</p>
    
    <p>Your walk-in client profile has been registered by <strong>{{ $supplierName }}</strong> at <strong>BioVue Digital Wellness</strong>.</p>

    <div class="info-box">
        <p style="margin: 0 0 10px 0; font-weight: bold; color: #1b1b18; font-size: 16px;">Your Login Credentials:</p>
        <p style="margin: 6px 0;"><strong>Email:</strong> <span style="color: #2563eb;">{{ $email }}</span></p>
        <p style="margin: 6px 0;"><strong>Temporary Password:</strong> <span style="background: #e2e8f0; padding: 3px 8px; border-radius: 4px; font-family: monospace; font-size: 15px; font-weight: bold;">{{ $plainPassword }}</span></p>
    </div>

    @if(!empty($recommendations) && is_array($recommendations))
        <div style="background-color: #f0f7ff; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: left; border: 1px solid #d0e3ff;">
            <h3 style="margin-top: 0; color: #0056b3; font-size: 16px;">Recommended Supplements</h3>
            <ul style="color: #444; padding-left: 20px; margin-top: 5px;">
                @foreach($recommendations as $item)
                    <li style="margin-bottom: 6px; font-size: 14px;">
                        @if(is_array($item))
                            <strong>{{ $item['name'] ?? ($item['supplement_name'] ?? 'Supplement') }}</strong>
                            @if(!empty($item['dosage'])) - Dosage: {{ $item['dosage'] }} @endif
                        @else
                            {{ $item }}
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="btn-container">
        <a href="{{ $loginUrl }}" class="btn-primary">Log In to Your Account</a>
    </div>

    <p style="font-size: 13px; color: #666; margin-top: 25px;">
        <em>Tip: For your security, please update your password after your first login in your account settings.</em>
    </p>

    <p>Thanks,<br><strong>{{ config('app.name') }} Team</strong></p>
@endsection
