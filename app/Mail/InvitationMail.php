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

    /**
     * Create a new message instance.
     */
    public function __construct($trainer, $token)
    {
        $this->trainer = $trainer;
        $this->token = $token;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Invitation from ' . $this->trainer->name)
            ->view('emails.invitation') 
            ->with([
                'trainerName' => $this->trainer->name,
                'url' => url('/api/v1/invitation/accept/' . $this->token), 
            ]);
    }
}