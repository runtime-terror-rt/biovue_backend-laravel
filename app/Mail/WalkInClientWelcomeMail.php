<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WalkInClientWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $clientName;
    public $email;
    public $plainPassword;
    public $supplierName;
    public $recommendations;
    public $loginUrl;

    public function __construct($clientName, $email, $plainPassword, $supplierName, $recommendations = [])
    {
        $this->clientName = $clientName;
        $this->email = $email;
        $this->plainPassword = $plainPassword;
        $this->supplierName = $supplierName;
        $this->recommendations = $recommendations;
        $this->loginUrl = 'https://biovuedigitalwellness.com/login';
    }

    public function build()
    {
        return $this->subject('Welcome to BioVue - Your Login Credentials')
                    ->view('emails.walkin_client_welcome');
    }
}
