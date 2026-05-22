<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; 
use Illuminate\Contracts\Broadcasting\ShouldBroadcast; 
use Illuminate\Notifications\Messages\BroadcastMessage; 
use Illuminate\Notifications\Notification;
use App\Channels\FcmChannel;
use Illuminate\Support\Facades\Log;
class CoachMessageNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public $title;
    public $message;
    public $type;
    public $additionalData;

    public function __construct($title, $message, $type, $additionalData = [])
    {
        $this->title = $title;
        $this->message = $message;
        $this->type = $type;
        $this->additionalData = $additionalData;
    }

    public function via(object $notifiable): array
    {
        // 'database' -> saved in database for in-app notifications
        // 'broadcast' -> real-time notifications using Laravel Echo
        // 'fcm' ->  Firebase Cloud Messaging for push notifications
        return ['database', 'broadcast', 'fcm'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title'   => $this->title,
            'message' => $this->message,
            'type'    => $this->type,
            'additional_data' => $this->additionalData,
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'title'   => $this->title,
            'message' => $this->message,
            'type'    => $this->type,
            'additional_data' => $this->additionalData,
        ]);
    }

    public function toFcm($notifiable)
    {
        Log::info('toFcm method called for user: ' . $notifiable->id); 

        $device = $notifiable->devices()->latest()->first();
        
        if (!$device) {
            Log::info('No device found for user: ' . $notifiable->id);
            return;
        }

        return \App\Services\FcmService::send(
            $device->device_token, 
            $this->title, 
            $this->message
        );
    }
}