<?php

namespace App\Jobs;

use App\Models\User;
use App\Mail\OtpVerifyMail;
use App\Mail\ForgotPasswordOtpMail;
use App\Mail\ResetPasswordOtpMail;
use App\Mail\PasswordResetSuccessMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendOtpEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $userId;
    public string $type; // verify, forgot, reset, reset_success
    public ?int $otp;

    /**
     * Create a new job instance.
     */
    public function __construct(int $userId, string $type = 'verify', ?int $otp = null)
    {
        $this->userId = $userId;
        $this->type = $type;
        $this->otp = $otp;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            return;
        }

        try {

            if ($this->type === 'verify') {

                Mail::to($user->email)->send(new OtpVerifyMail($user, $this->otp));

            } elseif ($this->type === 'forgot') {

                Mail::to($user->email)->send(new ForgotPasswordOtpMail($user, $this->otp));

            } elseif ($this->type === 'reset') {

                Mail::to($user->email)->send(new ResetPasswordOtpMail($user, $this->otp));

            } elseif ($this->type === 'reset_success') {

                Mail::to($user->email)->send(new PasswordResetSuccessMail($user));

            }

        } catch (\Exception $e) {

            \Log::error("SendOtpEmail failed for {$user->email}: " . $e->getMessage());

        }
    }
}
