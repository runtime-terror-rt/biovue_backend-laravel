<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Reset OTP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f8; margin:0; padding:0; }
        .container { max-width:600px; margin:40px auto; background-color:#fff; border-radius:12px; box-shadow:0 6px 20px rgba(0,0,0,0.08); overflow:hidden; }
        .header { background: linear-gradient(90deg,#d2d1f3,#bfc0f3); color:#fff; padding:25px 20px; text-align:center; }
        .header h1 { margin:0; font-size:28px; }
        .content { padding:35px 25px; color:#333; line-height:1.6; }
        .otp-box { display:inline-block; background-color:#f3f4f6; border-radius:8px; padding:15px 25px; font-size:26px; font-weight:700; margin:25px 0; letter-spacing:6px; text-align:center; }
        .footer { text-align:center; padding:20px; font-size:13px; color:#888; background-color:#f9fafb; }
        @media only screen and (max-width:600px) {
            .container { margin:20px; }
            .header h1 { font-size:24px; }
            .otp-box { font-size:22px; padding:12px 20px; }
            .content { padding:25px 15px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Password Reset OTP</h1>
        </div>
        <div class="content">
            <p>Hi {{ $user->name }},</p>
            <p>You requested to reset your password for <strong>BioVue</strong>. Your OTP code is:</p>
            
            <div class="otp-box">{{ $otp }}</div>

            <p>This OTP will expire in <strong>5 minutes</strong>. Use this code to complete your password reset process.</p>

            <p>If you did not request a password reset, please ignore this email.</p>

            <p>Thanks,<br><strong>BioVue Team</strong></p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} BioVue. All rights reserved.
        </div>
    </div>
</body>
</html>
