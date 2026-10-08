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

        $type = $data['type'] ?? null;
        $currentUser = $user ?: auth()->user();
        $userType = $currentUser?->user_type ?? 'individual';
        $professionType = $currentUser?->profession_type ?? null;
        $isAdmin = $currentUser && ($currentUser->hasRole('admin') || $currentUser->id === 1);
        $isSupplier = ($professionType === 'supplement_supplier');

        // Check explicit URL in payload, but sanitize legacy / broken routes
        $rawUrl = $data['url'] ?? $data['action_url'] ?? $data['link'] ?? null;
        if (!empty($rawUrl) && $rawUrl !== '#') {
            // Fix legacy '/admin/messages' or '/messages'
            if (str_contains($rawUrl, 'messages')) {
                return match(true) {
                    $isSupplier => '/supplier-dashboard/messages',
                    $userType === 'professional' => '/trainer-dashboard/messages',
                    default => '/user-dashboard/messages',
                };
            }

            // Fix legacy '/admin/clients' or '/clients'
            if (str_contains($rawUrl, 'clients')) {
                return match(true) {
                    $isSupplier => '/supplier-dashboard',
                    $userType === 'professional' => '/trainer-dashboard/clients',
                    default => '/connected-professions',
                };
            }

            // Fix legacy '/admin/calendar' or '/calendar'
            if (str_contains($rawUrl, 'calendar')) {
                return ($userType === 'professional') ? '/trainer-dashboard/calendar' : '/calendar';
            }

            // Fix legacy '/admin/overview'
            if (str_contains($rawUrl, 'overview')) {
                return match(true) {
                    $isAdmin => '/admin-dashboard',
                    $isSupplier => '/supplier-dashboard',
                    $userType === 'professional' => '/trainer-dashboard/overview',
                    default => '/user-dashboard',
                };
            }

            // Fix legacy '/admin/users'
            if (str_contains($rawUrl, '/admin/users')) {
                return '/admin-dashboard/users';
            }

            // If it's already a valid specific URL (e.g. program query, etc.)
            return $rawUrl;
        }

        // 2. Resolve by known notification type
        return match ($type) {
            'coach_message', 'client_message', 'supplier_message' => match(true) {
                $isSupplier => '/supplier-dashboard/messages',
                $userType === 'professional' => '/trainer-dashboard/messages',
                default => '/user-dashboard/messages',
            },
            'program_assigned' => !empty($data['program_id']) ? '/user-programs?program_id=' . $data['program_id'] : '/user-programs',
            'goal_updates', 'goal_message' => '/goals',
            'milestone_message' => '/goals',
            'insight_msg' => '/insights',
            'schedule_created', 'schedule_updated', 'schedule_reminder', 'reminder_message' => ($userType === 'professional') ? '/trainer-dashboard/calendar' : '/calendar',
            'connection_cancelled', 'connection_request' => match(true) {
                $isSupplier => '/supplier-dashboard',
                $userType === 'professional' => '/trainer-dashboard/clients',
                default => '/connected-professions'
            },
            'supplement_match', 'find_match' => '/supplier-dashboard',
            'product_order', 'product_update' => '/products/supplier',
            'subscription_message', 'subscription_updates' => $isAdmin ? '/admin-dashboard' : '/pricing',
            'registration_message' => '/admin-dashboard/users',
            default => $this->fallbackUrlByTypeOrClass($notification, $data, $currentUser),
        };
    }

    private function fallbackUrlByTypeOrClass($notification, array $data, $user): string
    {
        $userType = $user?->user_type ?? 'individual';
        $professionType = $user?->profession_type ?? null;
        $isAdmin = $user && ($user->hasRole('admin') || $user->id === 1);
        $isSupplier = ($professionType === 'supplement_supplier');

        if (!empty($data['program_id'])) {
            return '/user-programs?program_id=' . $data['program_id'];
        }

        if (!empty($data['schedule_id'])) {
            return ($userType === 'professional') ? '/trainer-dashboard/calendar' : '/calendar';
        }

        $className = class_basename($notification->type ?? '');
        return match ($className) {
            'ProgramAssignedNotification' => '/user-programs',
            'ScheduleNotification' => ($userType === 'professional') ? '/trainer-dashboard/calendar' : '/calendar',
            'CoachMessageNotification', 'ClientMessageNotification' => match(true) {
                $isSupplier => '/supplier-dashboard/messages',
                $userType === 'professional' => '/trainer-dashboard/messages',
                default => '/user-dashboard/messages',
            },
            'GoalUpdateNotification', 'MilestoneNotification' => '/goals',
            'InsightNotification' => '/insights',
            'ReminderNotification' => ($userType === 'professional') ? '/trainer-dashboard/calendar' : '/calendar',
            'SubscriptionNotification' => $isAdmin ? '/admin-dashboard' : '/pricing',
            'AdminNotification' => $isAdmin ? '/admin-dashboard' : '/user-dashboard',
            default => match(true) {
                $isAdmin => '/admin-dashboard',
                $isSupplier => '/supplier-dashboard',
                $userType === 'professional' => '/trainer-dashboard/overview',
                default => '/user-dashboard',
            },
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
