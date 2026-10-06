<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function updateSettings(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'coach_messages' => 'boolean',
            'goal_updates' => 'boolean',
            'ai_insights' => 'boolean',
            'missed_checkin_alerts' => 'boolean',
            'program_milestone_updates' => 'boolean',
            'weekly_summary_email' => 'boolean',
            'auto_remind_missed_checkins' => 'boolean',
            'default_reminder_time' => 'string',
            'check_in_reminder_alerts' => 'boolean',
            'subscription_updates' => 'boolean',
        ]);

        $user->notificationSettings()->updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Settings updated successfully!',
            'data' => $user->notificationSettings->refresh()
        ]);
    }

    public function getSettings(Request $request)
    {
        $user = Auth::user();

        $settings = $user->notificationSettings;

        return response()->json([
            'status' => 'success',
            'data' => $settings
        ]);
    }

    public function notificationListByUser()
    {
        try {
            $user = auth()->user();
            $notifications = $user->notifications->map(function ($notification) use ($user) {
                $data = $notification->data ?? [];
                $type = $data['type'] ?? $notification->type ?? null;
                $url = $this->resolveNotificationUrl($notification, $user);

                $title = $data['title'] 
                    ?? (!empty($data['program_name']) ? 'Program Assigned: ' . $data['program_name'] : null)
                    ?? (!empty($data['schedule_id']) ? 'Check-in Scheduled' : 'Notification');

                $message = $data['message'] 
                    ?? ($data['reminder_content'] ?? null);

                return [
                    'id' => $notification->id,
                    'title' => $title,
                    'type' => $type,
                    'message' => $message,
                    'url' => $url,
                    'action_url' => $url,
                    'link' => $url,
                    'data' => $data,
                    'created_at' => $notification->created_at->format('Y-m-d H:i:s'),
                    'created_at_formatted' => $notification->created_at->diffForHumans(),
                    'read_at' => $notification->read_at,
                    'is_read' => $notification->read_at !== null,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'fetched Notification Successfully.',
                'data' => $notifications
            ]);
        } catch (\Exception $e) {

            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Something went wrong, please try again.'
            ]);
        }
    }

    /**
     * Resolve the target website URL for a notification
     */
    public function resolveNotificationUrl($notification, $user = null): string
    {
        $data = $notification->data ?? [];

        // 1. If explicit URL is already present in notification payload
        if (!empty($data['url']) && $data['url'] !== '#') {
            return $data['url'];
        }
        if (!empty($data['action_url']) && $data['action_url'] !== '#') {
            return $data['action_url'];
        }
        if (!empty($data['link']) && $data['link'] !== '#') {
            return $data['link'];
        }

        $type = $data['type'] ?? null;
        $userType = $user ? $user->user_type : (auth()->user()?->user_type ?? 'individual');

        // 2. Resolve by known notification type
        return match ($type) {
            'coach_message' => ($userType === 'professional') ? '/admin/messages' : '/messages',
            'client_message' => '/admin/messages',
            'program_assigned' => !empty($data['program_id']) ? '/user-programs?program_id=' . $data['program_id'] : '/user-programs',
            'goal_updates', 'goal_message' => '/goals',
            'milestone_message' => '/goals',
            'insight_msg' => '/insights',
            'schedule_created', 'schedule_updated', 'schedule_reminder', 'reminder_message' => '/calendar',
            'connection_cancelled', 'connection_request' => ($userType === 'professional') ? '/clients' : '/connected-professions',
            'subscription_message', 'subscription_updates' => '/pricing',
            'registration_message' => '/admin/users',
            default => $this->fallbackUrlByTypeOrClass($notification, $data, $userType),
        };
    }

    private function fallbackUrlByTypeOrClass($notification, array $data, string $userType): string
    {
        if (!empty($data['program_id'])) {
            return '/user-programs?program_id=' . $data['program_id'];
        }

        if (!empty($data['schedule_id'])) {
            return '/calendar';
        }

        $className = class_basename($notification->type ?? '');
        return match ($className) {
            'ProgramAssignedNotification' => '/user-programs',
            'ScheduleNotification' => '/calendar',
            'CoachMessageNotification' => ($userType === 'professional') ? '/admin/messages' : '/messages',
            'ClientMessageNotification' => '/admin/messages',
            'GoalUpdateNotification', 'MilestoneNotification' => '/goals',
            'InsightNotification' => '/insights',
            'ReminderNotification' => '/calendar',
            'SubscriptionNotification' => '/pricing',
            'AdminNotification' => '/admin/overview',
            default => ($userType === 'professional') ? '/trainer-overview' : '/user-dashboard',
        };
    }

    public function markAsRead(Request $request)
    {
        try {
            $user = $request->user();

            $user->unreadNotifications->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read',
            ]);
        } catch (\Exception $e) {
            Log::error('Notifications mark as read:' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
            ]);
        }
    }

    public function markSingleAsRead(Request $request)
    {
        try {
            $user = $request->user();

            $request->validate([
                'notification_id' => 'required|exists:notifications,id',
            ]);

            $notification = $user->notifications()->where('id', $request->notification_id)->first();

            if ($notification) {
                $notification->markAsRead();
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
            ]);
        } catch (\Exception $e) {
            Log::error('Notification mark as read: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
            ]);
        }
    }

    public function deleteAllNotifications(Request $request)
    {
        try {
            $user = $request->user();

            $user->notifications()->delete();

            return response()->json([
                'success' => true,
                'message' => 'All notifications deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Delete all notifications: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
            ]);
        }
    }

    public function deleteSingleNotification(Request $request)
    {
        try {
            $user = $request->user();

            $request->validate([
                'notification_id' => 'required|exists:notifications,id',
            ]);

            $notification = $user->notifications()->where('id', $request->notification_id)->first();

            if ($notification) {
                $notification->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Notification delete: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
            ]);
        }
    }

}
