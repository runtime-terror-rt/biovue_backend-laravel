<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ForgotPasswordOtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;
    public $otp;

    public function __construct($user, $otp)
    {
        $this->user = $user;
        $this->otp = $otp;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Forgot Password OTP'
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.forgot_password_otp',
            with: [
                'user' => $this->user,
                'otp' => $this->otp,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
