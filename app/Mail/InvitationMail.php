<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $trainer;
    public $token;
    public $details;
    public $email;
    public $plainPassword;

    public function __construct($trainer, $token, $details, $email, $plainPassword)
    {
        $this->trainer = $trainer;
        $this->token = $token;
        $this->details = $details;
        $this->email = $email;
        $this->plainPassword = $plainPassword;
    }

    public function build()
    {
        return $this->subject('Invitation to BioVue')
                    ->view('emails.invitation')
                    ->with([
                        'url' => 'https://api.biovuedigitalwellness.com/api/v1/accept/invitation/' . $this->token,
                        'trainerName' => $this->trainer->name,
                    ]);
    }
}