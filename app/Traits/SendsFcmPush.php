<?php

namespace App\Traits;

use App\Services\FcmService;
use Illuminate\Support\Facades\Log;

trait SendsFcmPush
{
    /**
     * Send push notification via FCM to all registered devices of the notifiable user.
     */
    public function toFcm($notifiable): void
    {
        try {
            $title = $this->title ?? 'New Notification';
            $body  = $this->message ?? ($this->reminder_content ?? '');

            // Collect target tokens from user_devices table
            $tokens = [];
            if (method_exists($notifiable, 'devices')) {
                $tokens = $notifiable->devices()
                    ->whereNotNull('device_token')
                    ->where('device_token', '!=', '')
                    ->pluck('device_token')
                    ->all();
            }

            // Fallback to fcm_token attribute on user model if devices relation returned empty
            if (empty($tokens) && !empty($notifiable->fcm_token)) {
                $tokens[] = $notifiable->fcm_token;
            }

            if (empty($tokens)) {
                Log::info("FCM Push: No active device tokens found for User ID: {$notifiable->id}");
                return;
            }

            $actionUrl = method_exists($this, 'getTargetUrl') 
                ? $this->getTargetUrl($notifiable) 
                : ($this->url ?? '');

            $payloadData = [
                'type'       => (string) ($this->type ?? 'general'),
                'url'        => (string) $actionUrl,
                'action_url' => (string) $actionUrl,
                'title'      => (string) $title,
                'message'    => (string) $body,
            ];

            foreach (array_unique($tokens) as $token) {
                FcmService::send($token, $title, $body, $payloadData);
            }
        } catch (\Throwable $e) {
            Log::error("FCM dispatch error for User ID {$notifiable->id}: " . $e->getMessage());
        }
    }
}
