<!DOCTYPE html>
<html>
<head>
    <style>
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 40px;
            background-color: #f8f9fa;
        }
        .email-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .logo {
            width: 150px;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            background-color: #000000;
            color: #ffffff !important;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px 0;
        }
        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #888;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-card">
            <img src="https://biovuedigitalwellness.com/images/logo.png" alt="BioVue Logo" class="logo">
            <h3 style="color: #333;">Hello {{ $notifiable->name }},</h3>
            <p>Your trainer has <strong>{{ $status_type }}</strong> a check-in session for you. Here are the details:</p>
            
            <div class="details-box">
                <p><strong>📅 **Date:**</strong> {{ $schedule->schedule_date }}</p>
                <p><strong>⏰ **Time:**</strong> {{ $schedule->schedule_time }}</p>
                <p><strong>📋 **Type:**</strong> {{ ucfirst($schedule->check_in_type) }}</p>
            </div>

            <p style="text-align: center;">
                <a href="https://biovuedigitalwellness.com/user-dashboard" class="btn">View Dashboard</a>
            </p>

            <p style="margin-top: 30px; color: #333;">
                Regards,<br>
                <strong>BioVue Team</strong>
            </p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} BioVue Digital Wellness. All rights reserved.
        </div>
    </div>
</body>
</html>

