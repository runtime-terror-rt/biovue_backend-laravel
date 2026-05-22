@extends('emails.layouts.master')

@section('title', 'Plan Updated')

@section('content')
    <h2 style="font-size: 20px; margin-bottom: 15px; color: #111;">Hello,</h2>
    
    <p>We wanted to inform you that your wellness plan has been updated.</p>
    
    <p>No immediate action is required on your part — your current plan pricing and auto-renewal will continue exactly as they are.</p>
    
    <p>If you’re curious about the new options, you’re welcome to explore them anytime in your account.</p>

    <div class="btn-container" style="margin-top: 30px;">
        <a href="{{ $url }}" class="btn-primary" style="margin-right: 10px;">Check My Plan</a>
        <a href="https://biovuedigitalwellness.com/pricing" class="btn-primary" style="background-color: #ffffff; color: #1b1b18 !important; border: 2px solid #1b1b18; padding: 12px 28px;">Check New Plan</a>
    </div>

    <p>Regards,<br><strong>The BioVue Team</strong></p>
@endsection