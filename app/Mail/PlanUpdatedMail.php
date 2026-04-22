<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PlanUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $planId;

    public function __construct($user, $planId)
    {
        $this->user = $user;
        $this->planId = $planId;
    }

    public function build()
    {
        $url = "https://biovuedigitalwellness.com/user-dashboard/my-current-plan";

        return $this->subject('Your Plan Options Have Been Updated')
                    ->view('emails.plan_updated')
                    ->with([
                        'url' => $url,
                        'userName' => $this->user->name
                    ]);
    }
}
