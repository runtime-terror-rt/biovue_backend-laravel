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
            
            <h2 style="color: #333;">Hello!</h2>
            <p style="color: #555; font-size: 16px;">
                {{ $bodyMessage }}
            </p>

            <a href="{{ url('https://biovuedigitalwellness.com/pricing') }}" class="btn">Check My Plan</a>

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