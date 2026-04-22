<?php

namespace App\Http\Controllers\HydrationLog;

use App\Http\Controllers\Controller;
use App\Models\HydrationLog;
use App\Notifications\ReminderNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HydrationController extends Controller
{
    public function index()
    {
        $logs = HydrationLog::where('user_id', Auth::id())
            ->orderBy('log_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'log_date'      => 'required|date',
            'weight'        => 'nullable|numeric|min:0',
            'daily_steps'   => 'nullable|integer|min:0',
            'sleep_hours'   => 'nullable|numeric|min:0|max:24',
            'water_glasses' => 'nullable|integer|min:0',
        ]);

        $activity = HydrationLog::updateOrCreate(
            [
                'user_id'  => Auth::id(),
                'log_date' => $validated['log_date']
            ],
            $validated
        );

        $user = Auth::user();

        $status = $activity->wasRecentlyCreated ? 201 : 200;
        $message = $activity->wasRecentlyCreated ? 'Hydration log created' : 'Hydration log updated';

        $user->notify(new ReminderNotification('New Hydration Logs', 'Hydration Logs Added on '.$request->log_date .' ','reminder_message'));



        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $activity
        ], $status);
    }

    public function show($id)
    {
        $log = HydrationLog::where('user_id', Auth::id())->find($id);

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Hydration log not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $log
        ]);
    }

    public function destroy($id)
    {
        $log = HydrationLog::where('user_id', Auth::id())->find($id);

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Hydration log not found'
            ], 404);
        }

        $log->delete();

        return response()->json([
            'success' => true,
            'message' => 'Log deleted successfully'
        ]);
    }
}
