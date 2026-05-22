@extends('emails.layouts.master')

@section('title', 'BioVue Invitation')

@section('content')
    <h2 style="font-size: 22px; color: #333; font-weight: bold; margin-bottom: 15px;">Welcome to BioVue!</h2>
    
    <p>You have been invited to join <strong>BioVue Digital Wellness</strong>.</p>

    @if(!empty($email) && !empty($plainPassword))
        <div class="info-box">
            <p style="margin: 0 0 8px 0; font-weight: bold;">Your Temporary Login Credentials:</p>
            <p style="margin: 5px 0;"><strong>Email:</strong> {{ $email }}</p>
            <p style="margin: 5px 0;"><strong>Password:</strong> {{ $plainPassword }}</p>
        </div>
    @endif

    @if(!empty($details['match_reason']) || !empty($details['recommended_actions']))
        <div style="background-color: #f0f7ff; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: left; border: 1px solid #d0e3ff;">
            @if(!empty($details['match_reason']))
                <h3 style="margin-top: 0; color: #0056b3; font-size: 16px;">AI Analysis & Plan</h3>
                <p style="font-size: 15px; color: #444; line-height: 1.5; margin-bottom: 15px;">
                    {{ $details['match_reason'] }}
                </p>
            @endif

            @if(!empty($details['recommended_actions']) && is_array($details['recommended_actions']))
                <h4 style="color: #333; font-size: 15px; margin-bottom: 5px;">Recommended Actions:</h4>
                <ul style="color: #555; padding-left: 20px; margin-top: 5px;">
                    @foreach($details['recommended_actions'] as $action)
                        <li style="margin-bottom: 5px; font-size: 14px;">{{ $action }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <div class="btn-container">
        <a href="{{ $url }}" class="btn-primary">Accept Invitation</a>
    </div>

    <p>Thanks,<br><strong>{{ config('app.name') }} Team</strong></p>
@endsection