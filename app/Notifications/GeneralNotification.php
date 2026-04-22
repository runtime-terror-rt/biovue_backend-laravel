<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class GeneralNotification extends Notification
{
    public $data;

    public function __construct($data) {
        $this->data = $data; 
    }

    public function via($notifiable): array {
        return ['database', 'broadcast']; 
    }

    public function toArray($notifiable): array {
        return $this->data;
    }

    public function toBroadcast($notifiable): BroadcastMessage {
        return new BroadcastMessage(['data' => $this->data]); 
    }
}