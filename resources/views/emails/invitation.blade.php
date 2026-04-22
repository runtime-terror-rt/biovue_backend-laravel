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

            <a href="{{ $url }}" class="btn">Accept Invitation</a>

            <p class="ignore-text">
                If you did not expect this invitation, you can safely ignore this email.
            </p>

            <hr>

            <p style="color: #333; font-size: 15px;">
                Regards,<br>
                <strong>BioVue Digital Wellness Team</strong>
            </p>
        </div>
        
        <div class="footer">
            &copy; {{ date('Y') }} BioVue Digital Wellness. All rights reserved.
        </div>
    </div>
</body>
</html>