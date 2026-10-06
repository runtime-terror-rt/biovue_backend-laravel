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
        $url = $this->resolveTargetUrl($notifiable);
        return [
            'title'   => $this->title,
            'message' => $this->message,
            'type'    => $this->type,
            'additional_data' => $this->additionalData,
            'url'     => $url,
            'action_url' => $url,
            'link'    => $url,
        ];
    }

    public function toBroadcast($notifiable)
    {
        $url = $this->resolveTargetUrl($notifiable);
        return new BroadcastMessage([
            'title'   => $this->title,
            'message' => $this->message,
            'type'    => $this->type,
            'additional_data' => $this->additionalData,
            'url'     => $url,
            'action_url' => $url,
            'link'    => $url,
        ]);
    }

    private function resolveTargetUrl($notifiable): string
    {
        if ($this->type === 'connection_cancelled' || $this->type === 'connection_request') {
            return ($notifiable->user_type === 'professional') ? '/clients' : '/connected-professions';
        }
        return ($notifiable->user_type === 'professional') ? '/admin/messages' : '/messages';
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