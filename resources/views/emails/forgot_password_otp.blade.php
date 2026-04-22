<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password OTP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f4f6f8;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(90deg, #d2d1f3ff, #bfc0f3ff);
            color: #ffffff;
            padding: 25px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        .content {
            padding: 35px 25px;
            color: #333333;
            line-height: 1.6;
        }
        .content p {
            margin: 15px 0;
            font-size: 16px;
        }
        .otp-box {
            display: inline-block;
            background-color: #f3f4f6;
            border-radius: 8px;
            padding: 15px 25px;
            font-size: 26px;
            font-weight: 700;
            margin: 25px 0;
            letter-spacing: 6px;
            text-align: center;
        }
        a.button {
            display: inline-block;
            background-color: #6c63ff;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 30px;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 20px;
            transition: background 0.3s ease;
        }
        a.button:hover {
            background-color: #5952d4;
        }
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 13px;
            color: #888888;
            background-color: #f9fafb;
        }

        /* Responsive */
        @media only screen and (max-width: 600px) {
            .container {
                margin: 20px;
            }
            .header h1 {
                font-size: 24px;
            }
            .otp-box {
                font-size: 22px;
                padding: 12px 20px;
            }
            .content {
                padding: 25px 15px;
            }
            a.button {
                padding: 12px 25px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Forgot Password OTP</h1>
        </div>
        <div class="content">
            <p>Hi {{ $user->name }},</p>
            <p>You requested to reset your password. Your OTP code is:</p>

            <div class="otp-box">{{ $otp }}</div>

            <p>This OTP will expire in <strong>5 minutes</strong>. Please use it to reset your password.</p>

            <p>If you did not request this, please ignore this email.</p>

            <p>Thanks,<br><strong>BioVue</strong></p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} BioVue. All rights reserved.
        </div>
    </div>
</body>
</html>
