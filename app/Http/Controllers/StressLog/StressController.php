<?php

namespace App\Http\Controllers\StressLog;

use App\Http\Controllers\Controller;
use App\Models\StressLog;
use App\Notifications\ReminderNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class StressController extends Controller
{
    // List of logs for the logged-in user
    public function index()
    {
        $logs = StressLog::with('user:id,name,email')
            ->where('user_id', Auth::id())
            ->orderBy('log_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }

    // Single Logic for Store and Update
    public function store(Request $request)
    {
        $validated = $request->validate([
            'log_date'     => 'required|date',
            'stress_level' => 'nullable|integer|min:1|max:10',
            'mood'         => 'nullable|in:motivated,normal,low,happy,sad,neutral,angry,anxious',
            'description'  => 'nullable|string',
        ]);

        // Strict Date format to prevent Duplicate Key error (Y-m-d)
        $cleanDate = Carbon::parse($validated['log_date'])->format('Y-m-d');

        $log = StressLog::updateOrCreate(
            [
                'user_id'  => Auth::id(),
                'log_date' => $cleanDate
            ],
            $validated
        );

        $status = $log->wasRecentlyCreated ? 201 : 200;

        $user = Auth::user();

        $user->notify(new ReminderNotification('New Stress Logs', 'Stress Logs Added on '.$request->log_date .' ','reminder_message'));

        return response()->json([
            'success' => true,
            'message' => $log->wasRecentlyCreated ? 'Stress log created' : 'Stress log updated',
            'data'    => $log
        ], $status);
    }

    public function show($id)
    {
        $log = StressLog::with('user:id,name,email')
            ->where('user_id', Auth::id())
            ->find($id);

        if (!$log) {
            return response()->json(['success' => false, 'message' => 'Log not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $log
        ]);
    }

    public function destroy($id)
    {
        $log = StressLog::where('user_id', Auth::id())->find($id);

        if (!$log) {
            return response()->json(['success' => false, 'message' => 'Log not found'], 404);
        }

        $log->delete();
        return response()->json(['success' => true, 'message' => 'Log deleted successfully']);
    }
}
