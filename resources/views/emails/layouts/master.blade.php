<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>@yield('title', config('app.name'))</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f4f6f8;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .email-container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.06);
            overflow: hidden;
            border: 1px solid #eef2f5;
        }
        .email-header {
            background: linear-gradient(90deg, #d2d1f3, #bfc0f3);
            padding: 30px 20px;
            text-align: center;
        }
        .logo {
            width: 150px;
            height: auto;
        }
        .email-body {
            padding: 35px 30px;
            color: #333333;
            line-height: 1.6;
        }
        .email-body p {
            font-size: 16px;
            margin: 15px 0;
        }
        .info-box {
            background-color: #f8fafc;
            border-left: 4px solid #bfc0f3;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        .btn-container {
            text-align: center;
            margin: 30px 0 20px 0;
        }
        .btn-primary {
            display: inline-block;
            background-color: #1b1b18;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            transition: background 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #333330;
        }
        .email-footer {
            text-align: center;
            padding: 25px 20px;
            font-size: 13px;
            color: #8c8c88;
            background-color: #f9fafb;
            border-top: 1px solid #f1f5f9;
        }
        hr {
            border: 0;
            border-top: 1px solid #edf2f7;
            margin: 30px 0;
        }
        /* Responsive */
        @media only screen and (max-width: 600px) {
            .email-container { margin: 20px auto; }
            .email-body { padding: 25px 20px; }
        }
    </style>
</head>
<body>

    <div class="email-container">
        <div class="email-header">
            <img src="https://biovuedigitalwellness.com/images/logo.png" alt="BioVue Logo" class="logo">
        </div>

        <div class="email-body">
            @yield('content')
        </div>

        <div class="email-footer">
            &copy; {{ date('Y') }} BioVue Digital Wellness. All rights reserved.
        </div>
    </div>

</body>
</html>