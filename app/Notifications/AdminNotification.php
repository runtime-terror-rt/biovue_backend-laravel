<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminNotification extends Notification
{
    use Queueable;

    public $title;
    public $message;
    public $type;
    /**
     * Create a new notification instance.
     */
    public function __construct($title, $message, $type)
    {
        $this->title = $title;
        $this->message = $message;
        $this->type = $type;
    }


    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $url = $this->type === 'registration_message' ? '/admin-dashboard/users' : '/admin-dashboard';
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'url' => $url,
            'action_url' => $url,
            'link' => $url,
        ];
    }
}
