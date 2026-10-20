<?php

namespace App\Notifications;

use App\Traits\SendsFcmPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClientActivityNotification extends Notification
{
    use Queueable, SendsFcmPush;

    public $title;
    public $message;
    public $type;
    public $extraData;

    public function __construct(string $title, string $message, string $type = 'client_activity_log', array $extraData = [])
    {
        $this->title = $title;
        $this->message = $message;
        $this->type = $type;
        $this->extraData = $extraData;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'fcm'];
    }

    public function getTargetUrl(object $notifiable): string
    {
        return ($notifiable->user_type === 'professional') ? '/trainer-dashboard/clients' : '/user-dashboard';
    }

    public function toDatabase(object $notifiable): array
    {
        $url = $this->getTargetUrl($notifiable);

        return [
            'title'      => $this->title,
            'message'    => $this->message,
            'type'       => $this->type,
            'extra_data' => $this->extraData,
            'url'        => $url,
            'action_url' => $url,
            'link'       => $url,
        ];
    }
}
