<?php

namespace App\Notifications;

use App\Traits\SendsFcmPush;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class GeneralNotification extends Notification
{
    use SendsFcmPush;

    public $data;
    public $title;
    public $message;
    public $type;
    public $url;

    public function __construct($data) {
        $this->data = $data;
        $this->title = $data['title'] ?? 'Notification';
        $this->message = $data['message'] ?? '';
        $this->type = $data['type'] ?? 'general';
        $this->url = $data['url'] ?? '';
    }

    public function via($notifiable): array {
        return ['database', 'broadcast', 'fcm']; 
    }

    public function toArray($notifiable): array {
        return $this->data;
    }

    public function toBroadcast($notifiable): BroadcastMessage {
        return new BroadcastMessage(['data' => $this->data]); 
    }
}