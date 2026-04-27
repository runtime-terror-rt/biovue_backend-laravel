<!DOCTYPE html>
<html>
<head>
    <style>
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            text-align: center;
            padding: 40px;
            background-color: #f8f9fa;
        }
        .email-card {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .logo {
            width: 160px;
            margin-bottom: 25px;
        }
        .welcome-text {
            color: #333;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .description {
            color: #555;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        .btn {
            display: inline-block;
            background-color: #000000;
            color: #ffffff !important;
            padding: 14px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            margin: 20px 0;
            transition: background 0.3s ease;
        }
        .ignore-text {
            font-size: 13px;
            color: #999;
            margin-top: 20px;
        }
        .footer {
            margin-top: 25px;
            font-size: 12px;
            color: #888;
        }
        hr {
            border: 0;
            border-top: 1px solid #eee;
            margin: 30px 0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-card">
            <img src="https://biovuedigitalwellness.com/images/logo.png" alt="BioVue Logo" class="logo">
            
            <h2 class="welcome-text">Hello!</h2>
            
            <p class="description">
                <strong>{{ $trainerName }}</strong> has invited you to join their wellness program on <strong>BioVue</strong>. 
                Connect with your trainer to start tracking your journey and achieving your goals.
            </p>
            @if(empty($details['match_reason']) && empty($details['recommended_actions']))
                <p>Your account has been created. Here is your temporary login credentials:</p>
                <p><strong>Email:</strong> {{ $email }}</p>
                <p><strong>Password:</strong> {{ $plainPassword }}</p>
            @endif

            @if(!empty($details['match_reason']) || !empty($details['recommended_actions']))
                <div style="background-color: #f0f7ff; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: left;">
                    
                    @if(!empty($details['match_reason']))
                        <h3 style="margin-top: 0; color: #0056b3;">AI Analysis & Plan</h3>
                        <p style="font-size: 15px; color: #444; line-height: 1.5;">
                            {{ $details['match_reason'] }}
                        </p>
                    @endif

                    @if(!empty($details['recommended_actions']) && is_array($details['recommended_actions']))
                        <h4 style="color: #333;">Recommended Actions:</h4>
                        <ul style="color: #555; padding-left: 20px;">
                            @foreach($details['recommended_actions'] as $action)
                                <li style="margin-bottom: 5px;">{{ $action }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            <a href="{{ $url }}" class="btn">Accept Invitation</a>

        </div>
    </div>
</body>
</html>