<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Illuminate\Support\Facades\Log;

class FcmService
{
    public static function send($token, $title, $body, array $data = [])
    {
        if (!$token) {
            Log::error("FCM Service: Attempted to send, but no device token provided.");
            return;
        }

        try {
            Log::info("FCM Service: Sending push notification to token: {$token}");

            $credentialsPath = storage_path('app/firebase/firebase_credentials.json');
            if (!file_exists($credentialsPath)) {
                Log::warning("FCM Service: Firebase credentials file not found at {$credentialsPath}");
                return;
            }

            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $messaging = $factory->createMessaging();

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(FcmNotification::create()
                    ->withTitle((string)$title)
                    ->withBody((string)$body));

            if (!empty($data)) {
                $stringData = [];
                foreach ($data as $k => $v) {
                    $stringData[(string)$k] = is_scalar($v) ? (string)$v : json_encode($v);
                }
                $message = $message->withData($stringData);
            }

            $response = $messaging->send($message);
            
            Log::info("FCM Service: Push notification sent successfully!");
            return $response;

        } catch (\Exception $e) {
            Log::error("FCM Service: Failed to send notification. Error: " . $e->getMessage());
        }
    }
}