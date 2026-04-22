<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class FcmChannel
{
    public function send($notifiable, Notification $notification)
    {
        Log::info("FCM Channel: Notification process started for User ID: {$notifiable->id}");

        if (method_exists($notification, 'toFcm')) {
            $notification->toFcm($notifiable);
        } else {
            Log::warning("FCM Channel: 'toFcm' method missing in notification class for User ID: {$notifiable->id}");
        }
    }
}