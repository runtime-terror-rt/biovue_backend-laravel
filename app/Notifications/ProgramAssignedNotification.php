<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class ProgramAssignedNotification extends Notification
{
    protected $program;

    public function __construct($program)
    {
        $this->program = $program;
    }

    public function via($notifiable)
    {
        return ['database']; // database notification
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'New Program Assigned',
            'type' => 'program_assigned',
            'message' => "A new program '{$this->program->name}' has been assigned to you.",
            'program_id' => $this->program->id,
            'program_name' => $this->program->name,
            'url' => '/user-programs?program_id=' . $this->program->id,
            'action_url' => '/user-programs?program_id=' . $this->program->id,
            'link' => '/user-programs?program_id=' . $this->program->id,
        ];
    }
}