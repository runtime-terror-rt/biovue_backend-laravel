<?php

namespace App\Http\Controllers\StressLog;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StressReportController extends Controller
{
    public function getStressReport(Request $request)
{
    try {
        $user = $request->user();
        
        $daysCount = (int) $request->query('days', 7); 
        $startDate = now()->subDays($daysCount - 1)->startOfDay();
        $endDate = now()->endOfDay();

        $stressLogs = DB::table('stress_logs')
            ->where('user_id', $user->id)
            ->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->keyBy('log_date');

        $chartData = [];

        for ($i = 0; $i < $daysCount; $i++) {
            $currentDate = now()->subDays(($daysCount - 1) - $i)->toDateString();
            $log = $stressLogs->get($currentDate);

            $chartData[] = [
                'day' => Carbon::parse($currentDate)->format('D'),
                'date' => Carbon::parse($currentDate)->format('d M'),
                'stress_level' => $log ? (int) $log->stress_level : 0,
                'mood' => $log ? $log->mood : 'neutral'
            ];
        }

        $avgStress = $stressLogs->avg('stress_level') ?? 0;
        $logCount = $stressLogs->count();
        $consistency = round(($logCount / $daysCount) * 100);

        return response()->json([
            'success' => true,
            'period' => "Past $daysCount Days",
            'chart_data' => $chartData, 
            'stats' => [
                'average' => round($avgStress, 1) . '/10 PTS',
                'consistency' => $consistency . '%',
                'best_streak' => $this->calculateStreak($user->id) . ' DAYS',
                'current_trend' => $avgStress < 4 ? 'Improving' : 'Stable',
            ],
        ], 200);

    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

    private function calculateStreak($userId) 
    {
        $logs = DB::table('stress_logs')
            ->where('user_id', $userId)
            ->orderBy('log_date', 'desc')
            ->pluck('log_date');

        $streak = 0;
        $currentDate = now()->startOfDay();

        foreach ($logs as $logDate) {
            $date = Carbon::parse($logDate)->startOfDay();
            if ($date->equalTo($currentDate)) {
                $streak++;
                $currentDate->subDay();
            } elseif ($date->lessThan($currentDate)) {
                break;
            }
        }
        return $streak;
    }
}
