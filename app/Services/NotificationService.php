<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotificationSetting;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Determine default settings based on user role and profession type.
     */
    public static function getDefaultsForUser(User $user): array
    {
        // 1. Supplement Supplier
        if ($user->user_type === 'professional' && $user->profession_type === 'supplement_supplier') {
            return [
                'coach_messages'              => false,
                'client_messages'             => true,
                'goal_updates'                => false,
                'ai_insights'                 => false,
                'missed_checkin_alerts'       => false,
                'program_milestone_updates'   => false,
                'weekly_summary_email'        => true,
                'auto_remind_missed_checkins' => false,
                'default_reminder_time'       => '09:00 AM',
                'check_in_reminder_alerts'    => false,
                'subscription_updates'        => true,
            ];
        }

        // 2. Trainer / Coach
        if ($user->user_type === 'professional') {
            return [
                'coach_messages'              => false,
                'client_messages'             => true,
                'goal_updates'                => true,
                'ai_insights'                 => false, // Trainers do not need AI insight notifications
                'missed_checkin_alerts'       => true,
                'program_milestone_updates'   => true,
                'weekly_summary_email'        => false,
                'auto_remind_missed_checkins' => true,
                'default_reminder_time'       => '09:00 AM',
                'check_in_reminder_alerts'    => true,
                'subscription_updates'        => true,
            ];
        }

        // 3. Individual User (Client)
        return [
            'coach_messages'              => true,
            'client_messages'             => false,
            'goal_updates'                => true,
            'ai_insights'                 => true,
            'missed_checkin_alerts'       => true,
            'program_milestone_updates'   => true,
            'weekly_summary_email'        => false,
            'auto_remind_missed_checkins' => true,
            'default_reminder_time'       => '09:00 AM',
            'check_in_reminder_alerts'    => true,
            'subscription_updates'        => true,
        ];
    }

    /**
     * Check if a user can receive a notification of a given setting key.
     */
    public static function canReceive(User $user, string $settingKey): bool
    {
        // 1. Admin users receive all system alerts (never client habit or AI personal insights)
        if ($user->hasRole('admin') || $user->user_type === 'admin' || $user->id === 1) {
            return !in_array($settingKey, ['ai_insights']);
        }

        // 2. AI insights: strictly for individual clients only
        if ($settingKey === 'ai_insights') {
            if ($user->user_type !== 'individual') {
                return false;
            }
        }

        // 3. Suppliers: only allowed client_messages, subscription_updates, weekly_summary_email
        if ($user->user_type === 'professional' && $user->profession_type === 'supplement_supplier') {
            $allowedSupplierSettings = ['client_messages', 'subscription_updates', 'weekly_summary_email'];
            if (!in_array($settingKey, $allowedSupplierSettings)) {
                return false;
            }
        }

        // Fetch or create notification settings with role-based defaults
        $settings = $user->notificationSettings;
        if (!$settings) {
            $defaults = self::getDefaultsForUser($user);
            $defaults['user_id'] = $user->id;
            $settings = UserNotificationSetting::create($defaults);
        }

        if (isset($settings->$settingKey)) {
            return (bool) $settings->$settingKey;
        }

        return true;
    }

    /**
     * Send notification to a specific user if their settings permit it.
     */
    public static function notifyUser(User $user, Notification $notification, ?string $settingKey = null): void
    {
        try {
            if ($settingKey && !self::canReceive($user, $settingKey)) {
                Log::info("Notification [{$settingKey}] skipped for User #{$user->id} based on role/preferences.");
                return;
            }

            $user->notify($notification);
        } catch (\Throwable $e) {
            Log::error("Failed to notify User #{$user->id}: " . $e->getMessage());
        }
    }

    /**
     * Notify connected trainers/coaches when an individual client performs an action.
     * ONLY finds trainers connected to this client in `connect_user_proffesions`.
     */
    public static function notifyConnectedTrainers(User $client, Notification $notification, string $settingKey = 'program_milestone_updates'): void
    {
        try {
            if ($client->user_type !== 'individual') {
                return;
            }

            // Load connected professionals (excluding supplement suppliers)
            $trainers = $client->myProfessionals()
                ->where('user_type', 'professional')
                ->where(function ($q) {
                    $q->whereNull('profession_type')
                      ->orWhere('profession_type', '!=', 'supplement_supplier');
                })
                ->get();

            foreach ($trainers as $trainer) {
                if (self::canReceive($trainer, $settingKey)) {
                    $trainer->notify($notification);
                    Log::info("Notified connected Trainer #{$trainer->id} for client #{$client->id} action.");
                }
            }
        } catch (\Throwable $e) {
            Log::error("Failed to notify connected trainers for client #{$client->id}: " . $e->getMessage());
        }
    }
}
