<?php

namespace App\Notifications;

use App\Traits\SendsFcmPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class ProgramAssignedNotification extends Notification
{
    use Queueable, SendsFcmPush;

    public $program;
    public $title;
    public $message;
    public $type = 'program_assigned';

    public function __construct($program)
    {
        $this->program = $program;
        $this->title = 'New Program Assigned';
        $this->message = "A new program '{$this->program->name}' has been assigned to you.";
    }

    public function via($notifiable)
    {
        return ['database', 'fcm'];
    }

    public function getTargetUrl($notifiable): string
    {
        return '/user-programs?program_id=' . $this->program->id;
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