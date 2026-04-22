<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Reminder;
use App\Models\User;
use App\Notifications\GoalUpdateNotification;
use Illuminate\Http\Request;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Google\Auth\Credentials\ServiceAccountCredentials;
use App\Notifications\ReminderNotification;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->query('date', Carbon::today()->toDateString());

        $schedules = Schedule::with(['client' => function($query) {
            $query->select('id', 'name')->with(['profile' => function($q) {
                $q->select('user_id', 'image');
            }]);
        }])
        // ->whereBetween('schedule_date', [
        //     Carbon::parse($date)->startOfWeek(),
        //     Carbon::parse($date)->endOfWeek()
        // ])
        ->get();

        return response()->json([
            'status' => 'success',
            'data' => $schedules
        ]);
    }
    // public function index(Request $request)
    // {
    //     try {
    //         $date = $request->query('date', Carbon::today()->toDateString());

    //         $schedules = Schedule::with([
    //             'client' => function ($query) {
    //                 $query->select('id', 'name')
    //                       ->with(['profile' => function ($q) {
    //                           $q->select('user_id', 'image');
    //                       }]);
    //             }
    //         ])
    //         ->where('trainer_id', auth()->id()) // recommended for security
    //         ->whereBetween('schedule_date', [
    //             Carbon::parse($date)->startOfWeek(),
    //             Carbon::parse($date)->endOfWeek()
    //         ])
    //         ->get();

    //         if ($schedules->isEmpty()) {
    //             return response()->json([
    //                 'status' => 'success',
    //                 'message' => 'No schedules found for this week.',
    //                 'data' => []
    //             ], 200);
    //         }

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Schedules fetched successfully.',
    //             'data' => $schedules
    //         ], 200);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Failed to fetch schedules.'
    //         ], 500);
    //     }
    // }

    public function storeOrUpdateSchedule(Request $request)
    {
        $validated = $request->validate([
            'id'            => 'nullable|exists:schedules,id',
            'client_id'     => 'required|exists:users,id',
            'date'          => 'required|date',
            'time'          => 'required',
            'check_in_type' => 'required|string',
            'private_note'  => 'nullable|string',
            'status'        => 'nullable|in:scheduled,completed,missed'
        ]);

        try {
            $schedule = Schedule::updateOrCreate(
                ['id' => $request->id], 
                [
                    'trainer_id'    => auth()->id(),
                    'client_id'     => $validated['client_id'],
                    'schedule_date' => $validated['date'],
                    'schedule_time' => $validated['time'],
                    'check_in_type' => $validated['check_in_type'],
                    'private_note'  => $validated['private_note'],
                    'status'        => $validated['status'] ?? 'scheduled'
                ]
            );

            $message = $request->id ? 'Schedule updated successfully' : 'Check-in scheduled successfully';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $schedule
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong!',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function sendReminder(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:users,id',
            'reminder_type' => 'required|in:motivation,habit,missed',
            'message' => 'required|string'
        ]);

        $reminder = Reminder::create([
            'sender_id' => auth()->id(),
            'client_id' => $request->client_id,
            'reminder_type' => $request->reminder_type,
            'message' => $request->message,
            'push_notification' => $request->push_notification ?? false
        ]);

        $client = User::find($request->client_id);
//        $client->notify(new ReminderNotification($reminder));
        $client->notify(new ReminderNotification('new Reminder',$request->message ?? 'Doing Great','reminder_message'));

        if ($request->push_notification) {
            $this->sendPushNotification($request->client_id, $request->message);
        }

        return response()->json(['message' => 'Reminder sent successfully']);
    }

    private function sendPushNotification($clientId, $message)
    {
        $user = User::find($clientId);
        $fcmToken = $user->fcm_token;

        if (!$fcmToken) return;

        $credentials = new ServiceAccountCredentials(
            'https://www.googleapis.com/auth/cloud-platform',
            storage_path('app/service-account.json')
        );

        $accessToken = $credentials->fetchAuthToken()['access_token'];
        $client = new Client();
        $projectId = 'your-firebase-project-id';

        try {
            $client->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'message' => [
                        'token' => $fcmToken,
                        'notification' => [
                            'title' => 'New Reminder!',
                            'body'  => $message,
                        ],
                        'data' => [
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            'type' => 'reminder'
                        ],
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error("FCM Error: " . $e->getMessage());
        }
    }

    public function getMyReminders()
{
    try {
        $reminders = Reminder::where('client_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        if ($reminders->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No reminders found.',
                'data' => []
            ], 200);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Reminders fetched successfully.',
            'data' => $reminders
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to fetch reminders. Error: ' . $e->getMessage()
        ], 500);
    }
}
}
