<!DOCTYPE html>
<html>
<head>
    <style>
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            padding: 40px 20px;
            background-color: #f4f7f9;
        }
        .email-card {
            background: #ffffff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            text-align: left; /* লেখা বাম দিক থেকে শুরু হবে */
        }
        .logo-container {
            text-align: center; /* শুধু লোগোটি মাঝখানে থাকবে */
            margin-bottom: 30px;
        }
        .logo {
            width: 140px;
        }
        .greeting {
            color: #333333;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .content-text {
            color: #555555;
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        .button-wrapper {
            text-align: center; /* বাটনটি মাঝখানে রাখার জন্য */
            margin: 30px 0;
        }
        .btn {
            display: inline-block;
            background-color: #000000;
            color: #ffffff !important;
            padding: 14px 30px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            font-size: 15px;
        }
        .footer-section {
            margin-top: 35px;
            padding-top: 20px;
            border-top: 1px solid #eeeeee;
        }
        .signature {
            color: #333333;
            font-size: 15px;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="logo-container">
            <img src="https://biovuedigitalwellness.com/images/logo.png" alt="BioVue Logo" class="logo">
        </div>

        <div class="email-card">
            <h3> <strong>Your Plan Options Have Been Updated</strong> </h3>
            <div class="greeting">Hello, {{ $userName }}!</div>

            <p class="content-text">
                We’ve recently updated our plan options to bring you even more value and flexibility.
            </p>
            
            <p class="content-text">
                There’s nothing you need to do — your current plan pricing and auto-renewal will continue exactly as they are.
            </p>
            
            <p class="content-text">
                If you’re curious about the new options, you’re welcome to explore them anytime in your account.
            </p>

            <div class="button-row" style="text-align: center; margin: 30px 0;">
                <a href="{{ $url }}" class="btn btn-primary" style="display: inline-block; background-color: #000000; color: #ffffff !important; padding: 12px 20px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px; margin: 5px;">
                    Check My Plan
                </a>

                <a href="https://biovuedigitalwellness.com/pricing" class="btn btn-outline" style="display: inline-block; background-color: #ffffff; color: #000000 !important; border: 2px solid #000000; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px; margin: 5px;">
                    Check New Plan
                </a>
            </div>

            <div class="footer-section">
                <p class="signature">
                    Regards,<br>
                    <strong>The BioVue Team</strong>
                </p>
            </div>
        </div>

        <div style="text-align: center; font-size: 12px; color: #999; margin-top: 20px;">
            &copy; {{ date('Y') }} BioVue Digital Wellness. All rights reserved.
        </div>
    </div>
</body>
</html>