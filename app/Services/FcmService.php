<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Illuminate\Support\Facades\Log;

class FcmService
{
    public static function send($token, $title, $body)
    {
        if (!$token) {
            Log::error("FCM Service: Attempted to send, but no device token provided.");
            return;
        }

        try {
            Log::info("FCM Service: Sending push notification to token: {$token}");

            $factory = (new Factory)->withServiceAccount(storage_path('app/firebase/firebase_credentials.json'));
            $messaging = $factory->createMessaging();

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(FcmNotification::create()
                    ->withTitle($title)
                    ->withBody($body));

            $response = $messaging->send($message);
            
            Log::info("FCM Service: Push notification sent successfully! Response: " . print_r($response, true));
            return $response;

        } catch (\Exception $e) {
            Log::error("FCM Service: Failed to send notification. Error: " . $e->getMessage());
        }
    }
}