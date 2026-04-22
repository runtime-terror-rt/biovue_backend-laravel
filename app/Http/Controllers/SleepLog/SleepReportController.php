<?php

namespace App\Http\Controllers\SleepLog;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SleepReportController extends Controller
{
    public function getSleepReport(Request $request)
{
    try {
        $user = $request->user();
        $days = collect(range(6, 0))->map(fn($i) => now()->subDays($i)->format('D'));
        $startDate = now()->subDays(6)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $sleepData = DB::table('hydration_logs')
            ->where('user_id', $user->id)
            ->whereBetween('log_date', [$startDate, $endDate])
            ->get()->keyBy(fn($item) => \Carbon\Carbon::parse($item->log_date)->format('D'));

        $sleepChart = $days->mapWithKeys(fn($day) => [
            $day => (float)($sleepData->get($day)->sleep_hours ?? 0)
        ]);

        $avgSleep = DB::table('hydration_logs')->where('user_id', $user->id)->avg('sleep_hours') ?? 0;
        
        $totalLogs = DB::table('hydration_logs')
            ->where('user_id', $user->id)
            ->whereBetween('log_date', [$startDate, $endDate])
            ->count();
        
        $consistency = round(($totalLogs / 7) * 100);

        $currentTrend = ($avgSleep >= 7) ? 'Improving' : 'Stable';

        return response()->json([
            'success' => true,
            'sleep_progress' => [
                'chart' => $sleepChart,
                'average' => round($avgSleep, 1) . ' Hrs',
                'best_streak' => $this->calculateStreak($user->id, 'hydration_logs') . ' DAYS',
                'consistency' => $consistency . '%',
                'current_trend' => $currentTrend
            ],
            'bio_insight' => "Your sleep consistency improved this week after Tuesday, maintaining regular timing will significantly increase your physical recovery markers."
        ], 200);

    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}
}
