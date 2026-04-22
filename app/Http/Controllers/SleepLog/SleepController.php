<?php

namespace App\Http\Controllers\SleepLog;

use App\Http\Controllers\Controller;
use App\Models\SleepLog;
use App\Notifications\ReminderNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SleepController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'log_date' => 'required|date',
            'weight' => 'nullable|integer',
            'daily_steps' => 'nullable|integer',
            'sleep_hours' => 'nullable|numeric',
            'water_glasses' => 'nullable|integer',
        ]);

        $activity = SleepLog::create($validated);

        $user = Auth::user();

        $user->notify(new ReminderNotification('New Sleep Logs', 'Sleep Logs Added on '.$request->log_date .' ','reminder_message'));

        return response()->json($activity, 201);

    }

    public function index()
    {
        return SleepLog::where('user_id', Auth::id())->get();
    }

    public function show($id)
    {
        return SleepLog::where('user_id', Auth::id())
            ->where('id', $id)
            ->firstOrFail();
    }



    public function getSleepReport(Request $request, $userId = null)
    {
        $id = $userId ?: auth()->id();

        $days = (int) $request->query('days', 7);

        if (!in_array($days, [7, 15, 30, 90])) {
            $days = 7;
        }

        $endDate = Carbon::now()->endOfDay();
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();

        $sleepLogs = DB::table('sleep_logs')
            ->where('user_id', $id)
            ->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('log_date', 'asc')
            ->get();

        $sleepTarget = DB::table('target_goals')
            ->where('user_id', $id)
            ->value('sleep_target') ?? 0;

        $notes = DB::table('profession_notes')
            ->join('users as professionals', 'profession_notes.profession_id', '=', 'professionals.id')
            ->where('profession_notes.user_id', $id)
            ->select('profession_notes.id', 'profession_notes.note', 'profession_notes.created_at', 'professionals.name as provider_name')
            ->latest()
            ->take(5)
            ->get();

        $chartData = [];
        $totalSleepHours = 0;
        $loggedDaysCount = 0;

        for ($i = 0; $i < $days; $i++) {
            $currentDate = Carbon::now()->subDays(($days - 1) - $i)->toDateString();
            $log = $sleepLogs->where('log_date', $currentDate)->first();

            $hours = $log ? (float) $log->sleep_hours : 0;
            $totalSleepHours += $hours;

            $chartData[] = [
                'date' => Carbon::parse($currentDate)->format('d M'),
                'sleep_hours' => $hours,
                'target_hours' => (float) $sleepTarget,
                'label' => $days > 15 ? Carbon::parse($currentDate)->format('d M') : Carbon::parse($currentDate)->format('D'),
            ];

            if ($hours > 0) $loggedDaysCount++;
        }

        $avgSleep = ($days > 0) ? ($totalSleepHours / $days) : 0;
        $consistency = ($days > 0) ? round(($loggedDaysCount / $days) * 100) : 0;

        return response()->json([
            'success' => true,
            'period' => "Past $days Days",
            'statistics' => [
                'average_sleep' => number_format($avgSleep, 1) . " Hrs",
                'sleep_target' => number_format($sleepTarget, 1) . " Hrs",
                'consistency' => $consistency . "%",
                'total_logged' => "$loggedDaysCount / $days Days",
            ],
            'chart_data' => $chartData,
            'profession_notes' => $notes
        ]);
    }

    private function getStartDate($type) {
        return match($type) {
            'weekly' => Carbon::now()->subDays(6),
            'monthly' => Carbon::now()->subDays(30),
            '3_months' => Carbon::now()->subMonths(3),
            '6_months' => Carbon::now()->subMonths(6),
            default => Carbon::now()->subDays(6),
        };
    }

    private function generateSleepChartData($type, $startDate, $endDate, $logs, $userId) {
        $data = [];
        if ($type === '3_months') {
            $period = \Carbon\CarbonPeriod::create($startDate, '1 week', $endDate);
            foreach ($period as $date) {
                $weekEnd = $date->copy()->addDays(6);
                $avg = SleepLog::where('user_id', $userId)
                        ->whereBetween('log_date', [$date, $weekEnd])
                        ->avg('sleep_hours') ?? 0;
                $data[] = ['label' => $date->format('M d'), 'hours' => (float)number_format($avg, 1)];
            }
        } else {
            for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
                $dateString = $date->format('Y-m-d');
                $data[] = [
                    'label' => $type === 'monthly' ? $date->format('d M') : $date->format('D'),
                    'hours' => $logs->has($dateString) ? (float)$logs[$dateString]->sleep_hours : 0
                ];
            }
        }
        return $data;
    }

    private function calculateStreak($userId) {
        return SleepLog::where('user_id', $userId)->where('sleep_hours', '>=', 1)->count();
    }

    private function getTrendStatus($chartData) {
        $count = count($chartData);
        if ($count < 2) return "Stable";
        return $chartData[$count-1]['hours'] >= $chartData[$count-2]['hours'] ? "Improving" : "Declining";
    }

    private function generateBioInsight($consistency, $avg) {
        if ($consistency > 80 && $avg >= 7) return "Excellent! Your sleep cycle is very stable.";
        return "Your sleep consistency improved this weekly after Tuesday, maintaining regular timing will significantly increase your physical recovery markers.";
    }

}
