<?php

namespace App\Http\Controllers\HydrationLog;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HydrationReportController extends Controller
{
    public function getHydrationReport(Request $request, $userId = null)
    {
        try {
            $id = $userId ?: auth()->id();
            
            // ১. ফিল্টার দিন নির্ধারণ (ডিফল্ট ৭ দিন)
            $days = (int) $request->query('days', 7); 
            if (!in_array($days, [7, 15, 30, 90])) {
                $days = 7; 
            }

            $endDate = now()->format('Y-m-d');
            $startDate = now()->subDays($days - 1)->format('Y-m-d');

            // ২. ডাটাবেস থেকে হাইড্রেশন লগ নিয়ে আসা
            $hydrationData = DB::table('hydration_logs')
                ->where('user_id', $id)
                ->whereBetween('log_date', [$startDate, $endDate])
                ->get()
                ->keyBy(fn($item) => \Carbon\Carbon::parse($item->log_date)->format('Y-m-d'));

            // ৩. টার্গেট এবং প্রফেশনাল নোটস আনা
            $waterTarget = DB::table('target_goals')
                ->where('user_id', $id)
                ->value('water_target') ?? 0; // এটি গ্লাসে বা লিটারে হতে পারে

            $notes = DB::table('profession_notes')
                ->join('users as professionals', 'profession_notes.profession_id', '=', 'professionals.id')
                ->where('profession_notes.user_id', $id)
                ->select('profession_notes.id', 'profession_notes.note', 'profession_notes.created_at', 'professionals.name as provider_name')
                ->latest()
                ->take(5)
                ->get();

            $chartData = [];
            $totalGlasses = 0;
            $loggedDaysCount = 0;

            // ৪. চার্ট ডাটা লুপ (ডাইনামিক $days অনুযায়ী)
            for ($i = 0; $i < $days; $i++) {
                $currentDate = now()->subDays(($days - 1) - $i)->format('Y-m-d');
                $log = $hydrationData->get($currentDate);
                
                $glasses = $log ? (float) $log->water_glasses : 0;
                $totalGlasses += $glasses;

                $chartData[] = [
                    'label' => $days > 15 ? \Carbon\Carbon::parse($currentDate)->format('d M') : \Carbon\Carbon::parse($currentDate)->format('D'),
                    'glasses' => $glasses,
                    'target' => (float) $waterTarget,
                ];

                if ($glasses > 0) $loggedDaysCount++;
            }

            // ৫. স্ট্যাটিস্টিকস ক্যালকুলেশন (মোট দিন দিয়ে ভাগ)
            $avgWater = ($days > 0) ? ($totalGlasses / $days) : 0;
            $consistency = ($days > 0) ? round(($loggedDaysCount / $days) * 100) : 0;

            // ট্রেন্ড লজিক
            $currentTrend = ($avgWater >= $waterTarget && $waterTarget > 0) ? 'Improving' : 'Stable';

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => "Past $days Days",
                    'chart_data' => $chartData,
                    'statistics' => [
                        'average_water' => round($avgWater, 1) . ' Glasses',
                        'water_target' => round($waterTarget, 1) . ' Glasses',
                        'best_streak' => $this->calculateStreak($id, 'hydration_logs') . ' DAYS',
                        'consistency' => $consistency . '%',
                        'current_trend' => $currentTrend 
                    ],
                    'profession_notes' => $notes 
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function calculateStreak($userId, $tableName)
    {
        $dates = DB::table($tableName)
            ->where('user_id', $userId)
            ->orderBy('log_date', 'desc')
            ->pluck('log_date');

        if ($dates->isEmpty()) return 0;

        $streak = 0;
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        
        $lastLogDate = \Carbon\Carbon::parse($dates[0])->startOfDay();

        if (!$lastLogDate->equalTo($today) && !$lastLogDate->equalTo($yesterday)) {
            return 0;
        }

        $compareDate = $lastLogDate; 

        foreach ($dates as $date) {
            $logDate = \Carbon\Carbon::parse($date)->startOfDay();
            
            if ($logDate->equalTo($compareDate)) {
                $streak++;
                $compareDate->subDay();
            } elseif ($logDate->greaterThan($compareDate)) {
                continue;
            } else {
                break;
            }
        }
        return $streak;
    }
}
