<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProfessionalConnectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $connectedUser;
    public $professional;

    public function __construct(User $connectedUser, User $professional)
    {
        $this->connectedUser = $connectedUser;
        $this->professional  = $professional;
    }

    public function build()
    {
        return $this->subject('New User Connected to Your Profile!')
                    ->markdown('emails.professional_connected');
    }
}