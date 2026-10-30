<?php

namespace App\Notifications;

use App\Traits\SendsFcmPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MilestoneNotification extends Notification
{
    use Queueable, SendsFcmPush;

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
        return ['database', 'fcm'];
    }

    public function getTargetUrl(object $notifiable): string
    {
        return ($notifiable->user_type === 'professional') ? '/trainer-dashboard/clients' : '/user-dashboard/goals';
    }

    public function toDatabase($notifiable)
    {
        $url = $this->getTargetUrl($notifiable);

        return [
            'title'      => $this->title,
            'message'    => $this->message,
            'type'       => $this->type,
            'url'        => $url,
            'action_url' => $url,
            'link'       => $url,
        ];
    }
}
