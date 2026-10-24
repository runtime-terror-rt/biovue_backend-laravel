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
        if ($request->has('weight') && ($request->weight === '' || $request->weight === null)) {
            $request->merge(['weight' => null]);
        }

        $validated = $request->validate([
            'id'               => 'nullable|exists:activity_logs,id',
            'log_date'         => 'required|date',
            'weight'           => 'nullable|numeric|min:0',
            'unit'             => 'nullable|string',
            'daily_steps'      => 'nullable|integer|min:0',
            'sleep_hours'      => 'nullable|numeric|min:0|max:24',
            'water_glasses'    => 'nullable|integer|min:0',
            'workout_type'     => 'nullable|string|max:255',
            'workout_time'     => 'nullable|numeric|min:0',
            'workout_calories' => 'nullable|numeric|min:0',
            'workouts'         => 'nullable|array',
        ]);

        // Auto-sync workouts array and single workout fields
        if (!empty($validated['workouts'])) {
            $firstWorkout = $validated['workouts'][0] ?? [];
            if (empty($validated['workout_type']) && !empty($firstWorkout['type'])) {
                $validated['workout_type'] = $firstWorkout['type'];
            }
            if (empty($validated['workout_time']) && isset($firstWorkout['time'])) {
                $validated['workout_time'] = $firstWorkout['time'];
            }
            if (empty($validated['workout_calories']) && isset($firstWorkout['calories'])) {
                $validated['workout_calories'] = $firstWorkout['calories'];
            }
        } elseif (!empty($validated['workout_type'])) {
            $validated['workouts'] = [[
                'type'     => $validated['workout_type'],
                'time'     => $validated['workout_time'] ?? null,
                'calories' => $validated['workout_calories'] ?? null,
            ]];
        }

        $matchConditions = ['user_id' => Auth::id(), 'log_date' => $validated['log_date']];
        if ($request->filled('id')) {
            $matchConditions = ['user_id' => Auth::id(), 'id' => $request->id];
        }

        $existing = ActivityLog::where($matchConditions)->first();
        if ($existing) {
            $dataToSave = $validated;
            // If request did not explicitly supply weight, preserve existing logged weight
            if (!$request->filled('weight')) {
                unset($dataToSave['weight']);
            }
            // If request did not explicitly supply daily_steps, preserve existing steps
            if (!$request->filled('daily_steps') && $existing->daily_steps > 0) {
                unset($dataToSave['daily_steps']);
            }
            // If request did not supply workout info, preserve existing workouts
            if (empty($validated['workout_type']) && empty($validated['workouts']) && (!empty($existing->workout_type) || !empty($existing->workouts))) {
                unset($dataToSave['workout_type'], $dataToSave['workout_time'], $dataToSave['workout_calories'], $dataToSave['workouts']);
            }

            $existing->update($dataToSave);
            $activity = $existing;
        } else {
            $activity = ActivityLog::create(array_merge($matchConditions, $validated));
        }

        // Keep user profile weight synced if provided
        if (!empty($validated['weight'])) {
            \App\Models\UserProfile::where('user_id', Auth::id())
                ->update(['weight' => $validated['weight']]);
        }

        $user = Auth::user();

        $status = $activity->wasRecentlyCreated ? 201 : 200;
        $message = $activity->wasRecentlyCreated ? 'Activity log created.' : 'Activity log updated.';

        // Notify connected trainers about client activity log
        \App\Services\NotificationService::notifyConnectedTrainers(
            $user,
            new \App\Notifications\ClientActivityNotification(
                "Activity Logged: {$user->name}",
                "{$user->name} has logged daily activity for {$validated['log_date']}.",
                'client_activity_log',
                ['client_id' => $user->id, 'log_date' => $validated['log_date'], 'type' => 'activity']
            ),
            'program_milestone_updates'
        );

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
            $logItem = $logs[$dateString] ?? null;
            $steps = $logItem ? (int)$logItem->daily_steps : 0;

            $chartData[] = [
                'label'            => $days > 15 ? $date->format('d M') : $date->format('D'),
                'date'             => $dateString,
                'steps'            => $steps,
                'target'           => (int)$stepsTarget,
                'weight'           => $logItem && !empty($logItem->weight) ? (float)$logItem->weight : null,
                'workout_type'     => $logItem?->workout_type ?? null,
                'workout_time'     => $logItem ? (int)$logItem->workout_time : 0,
                'workout_calories' => $logItem ? (int)$logItem->workout_calories : 0,
                'workouts'         => $logItem?->workouts ?? [],
            ];

            if ($steps > 0 || ($logItem && $logItem->workout_time > 0) || ($logItem && !empty($logItem->weight))) $daysWithData++;
        }

        $totalSteps = $logs->sum('daily_steps');
        $averageSteps = ($days > 0) ? ($totalSteps / $days) : 0;

        $consistencyScore = ($days > 0) ? ($daysWithData / $days) * 100 : 0;

        $totalWorkoutTime = $logs->sum('workout_time');
        $totalWorkoutCalories = $logs->sum('workout_calories');
        $workoutsCount = $logs->filter(fn($l) => !empty($l->workout_type) || !empty($l->workout_time))->count();

        $targetWeight = DB::table('target_goals')->where('user_id', $id)->value('target_weight') ?? 0;
        $latestWeight = $logs->whereNotNull('weight')->last()?->weight 
            ?? ActivityLog::where('user_id', $id)->whereNotNull('weight')->where('weight', '>', 0)->latest('log_date')->value('weight')
            ?? ($userProfile->weight ?? 0);
        $weightUnit = ($userProfile->unit ?? 'imperial') === 'imperial' ? 'lbs' : 'kg';

        // Weight specific metrics
        $weightLogs = $logs->whereNotNull('weight')->filter(fn($l) => (float)$l->weight > 0);
        $weightDaysCount = $weightLogs->count();
        $averageWeight = $weightDaysCount > 0 ? round($weightLogs->avg('weight'), 1) : ($latestWeight ? round((float)$latestWeight, 1) : 0);
        $weightConsistency = ($days > 0) ? round(($weightDaysCount / $days) * 100) : 0;

        return response()->json([
            'status' => 'success',
            'data' => [
                'period' => "Past $days Days",
                'chart_data' => $chartData,
                'statistics' => [
                    'average_steps'          => number_format(round($averageSteps)) . " Steps",
                    'steps_target'           => number_format($stepsTarget) . " Steps",
                    'current_weight'         => $latestWeight ? round((float)$latestWeight, 1) : 0,
                    'target_weight'          => $targetWeight ? round((float)$targetWeight, 1) : 0,
                    'average_weight'         => $averageWeight,
                    'weight_unit'            => $weightUnit,
                    'weight_consistency'     => $weightConsistency . "%",
                    'weight_streak'          => $this->calculateWeightStreak($id) . " DAYS",
                    'weight_logs_count'      => $weightDaysCount,
                    'consistency'            => round($consistencyScore) . "%",
                    'best_streak'            => $this->calculateStreak($id) . " DAYS",
                    'current_trend'          => $this->getTrendStatus($chartData),
                    'total_workout_minutes'  => round($totalWorkoutTime) . " Mins",
                    'total_workout_calories' => round($totalWorkoutCalories) . " kcal",
                    'total_workouts'         => $workoutsCount,
                ],
                'profession_notes' => $notes
            ]
        ]);
    }

    private function calculateWeightStreak($userId)
    {
        $logs = ActivityLog::where('user_id', $userId)
            ->whereNotNull('weight')
            ->where('weight', '>', 0)
            ->orderBy('log_date', 'desc')
            ->pluck('log_date');

        if ($logs->isEmpty()) return 0;

        $streak = 0;
        $expectedDate = Carbon::today();

        $latestLogDate = Carbon::parse($logs->first());
        if ($latestLogDate->isYesterday()) {
            $expectedDate = Carbon::yesterday();
        } elseif (!$latestLogDate->isToday()) {
            return 0;
        }

        foreach ($logs as $date) {
            $logDate = Carbon::parse($date);
            if ($logDate->isSameDay($expectedDate)) {
                $streak++;
                $expectedDate->subDay();
            } elseif ($logDate->isAfter($expectedDate)) {
                continue;
            } else {
                break;
            }
        }

        return $streak;
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
