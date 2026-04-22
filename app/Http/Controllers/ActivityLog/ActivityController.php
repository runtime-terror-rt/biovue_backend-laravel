<?php

namespace App\Http\Controllers\ActivityLog;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Notifications\ReminderNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ActivityController extends Controller
{
    public function index()
    {
        return ActivityLog::where('user_id', Auth::id())
            ->orderBy('id', 'desc')
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id'            => 'nullable|exists:activity_logs,id',
            'log_date'      => 'required|date',
            'weight'        => 'nullable|numeric|min:0',
            'daily_steps'   => 'nullable|integer|min:0',
            'sleep_hours'   => 'nullable|numeric|min:0|max:24',
            'water_glasses' => 'nullable|integer|min:0',
        ]);

        $activity = ActivityLog::updateOrCreate(
            [
                'user_id'  => Auth::id(),
                'id' => $request->id ?? null,
                'log_date' => $validated['log_date']
            ],
            $validated
        );

        $user = Auth::user();

        $status = $activity->wasRecentlyCreated ? 201 : 200;
        $message = $activity->wasRecentlyCreated ? 'Activity log created.' : 'Activity log updated.';

        $user->notify(new ReminderNotification('New Logs', 'Logs Added on '.$request->log_date .' ','reminder_message'));

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $activity
        ], $status);
    }

    public function show($id)
    {
        $log = ActivityLog::where('user_id', Auth::id())
            ->where('id', $id)
            ->first();

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Activity not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $log
        ], 200);
    }

    public function destroy($id)
    {
        $log = ActivityLog::where('user_id', Auth::id())
            ->where('id', $id)
            ->first();

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Activity not found'
            ], 404);
        }

        $log->delete();

        return response()->json([
            'success' => true,
            'message' => 'Log successfully deleted'
        ], 200);
    }

    public function getActivityReport(Request $request, $userId = null)
    {
        $id = $userId ?: auth()->id();
        $days = (int) $request->query('days', 7);

        if (!in_array($days, [7, 15, 30, 90])) {
            $days = 7;
        }

        $endDate = Carbon::today();
        $startDate = Carbon::today()->subDays($days - 1);

        $logs = ActivityLog::where('user_id', $id)
            ->whereBetween('log_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderBy('log_date', 'asc')
            ->get()
            ->keyBy(fn($item) => Carbon::parse($item->log_date)->format('Y-m-d'));

        $stepsTarget = DB::table('target_goals')
            ->where('user_id', $id)
            ->value('daily_step_goal') ?? 0;

        $notes = DB::table('profession_notes')
            ->join('users as professionals', 'profession_notes.profession_id', '=', 'professionals.id')
            ->where('profession_notes.user_id', $id)
            ->select('profession_notes.id', 'profession_notes.note', 'profession_notes.created_at', 'professionals.name as provider_name')
            ->latest()
            ->take(5)
            ->get();

        $chartData = [];
        $daysWithData = 0;

        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dateString = $date->format('Y-m-d');
            $steps = isset($logs[$dateString]) ? (int)$logs[$dateString]->daily_steps : 0;

            $chartData[] = [
                'label' => $days > 15 ? $date->format('d M') : $date->format('D'),
                'steps' => $steps,
                'target' => (int)$stepsTarget
            ];

            if($steps > 0) $daysWithData++;
        }

        $totalSteps = $logs->sum('daily_steps');
        $averageSteps = ($days > 0) ? ($totalSteps / $days) : 0;

        $consistencyScore = ($days > 0) ? ($daysWithData / $days) * 100 : 0;

        return response()->json([
            'status' => 'success',
            'data' => [
                'period' => "Past $days Days",
                'chart_data' => $chartData,
                'statistics' => [
                    'average_steps' => number_format(round($averageSteps)) . " Steps",
                    'steps_target' => number_format($stepsTarget) . " Steps",
                    'consistency' => round($consistencyScore) . "%",
                    'best_streak' => $this->calculateStreak($id) . " DAYS",
                    'current_trend' => $this->getTrendStatus($chartData),
                ],
                'profession_notes' => $notes
            ]
        ]);
    }

    private function calculateStreak($userId)
    {
        $logs = ActivityLog::where('user_id', $userId)
            ->where('daily_steps', '>', 0)
            ->orderBy('log_date', 'desc')
            ->pluck('log_date');

        if ($logs->isEmpty()) return 0;

        $streak = 0;
        $currentDate = Carbon::today();

        foreach ($logs as $logDate) {
            $date = Carbon::parse($logDate);
            if ($date->isSameDay($currentDate) || $date->isSameDay($currentDate->copy()->subDay())) {
                $streak++;
                $currentDate = $date->copy()->subDay();
            } else {
                break;
            }
        }
        return $streak;
    }

    private function getTrendStatus($chartData)
    {
        $count = count($chartData);
        if ($count < 2) return "Stable";

        $lastValue = $chartData[$count - 1]['steps'];
        $previousValue = $chartData[$count - 2]['steps'];

        if ($lastValue > $previousValue) return "Improving";
        if ($lastValue < $previousValue) return "Declining";
        return "Stable";
    }
}
