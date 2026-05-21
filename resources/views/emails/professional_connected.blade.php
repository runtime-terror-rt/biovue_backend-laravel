@component('mail::message')
<div style="text-align: center; margin-bottom: 20px;">
    <img src="https://biovuedigitalwellness.com/images/logo.png" alt="BioVue Logo" style="width: 150px; height: auto;">
</div>

# Hello {{ $professional->name }},

A new user has successfully connected with you on BioVue.

**User Details:**
- **Name:** {{ $connectedUser->name }}
- **Email:** {{ $connectedUser->email }}

You can now review their wellness reports and configure goals from your professional dashboard.

@component('mail::button', ['url' => config('app.url') . '/trainer-dashboard/overview'])
View Dashboard
@endcomponent

Thanks,<br>
{{ config('app.name') }} Team
@endcomponent