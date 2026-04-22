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
            $notifications = auth()->user()->notifications->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->data['title'] ?? null,
                    'type' => $notification->data['type'] ?? null,
                    'message' => $notification->data['message'] ?? null,
                    'created_at' => $notification->created_at->format('Y-m-d H:i:s'),
                    'created_at_formatted' => $notification->created_at->diffForHumans(),
                    'read_at' => $notification->read_at,
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
