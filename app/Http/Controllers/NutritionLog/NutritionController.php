<?php

namespace App\Http\Controllers\NutritionLog;

use App\Http\Controllers\Controller;
use App\Models\NutritionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NutritionController extends Controller
{
    // List all logs for the authenticated user
    public function index()
    {
        return response()->json(
            Auth::user()
            ->nutritionLogs()
            ->orderBy('id', 'desc')
            ->get());
    }

    // Store or Update a log for a specific date
    public function store(Request $request)
    {
        $validated = $request->validate([
            'log_date'           => 'required|date',
            'meal_balance'       => 'nullable|in:balanced,high_carb,high_protein,keto',
            'protein_servings'   => 'integer|min:0|max:20',
            'vegetable_servings' => 'integer|min:0|max:20',
            'carb_quality'       => 'nullable|string|max:255',
            'fat_sources'        => 'nullable|string',
        ]);

        $log = NutritionLog::updateOrCreate(
            ['user_id' => Auth::id(), 'log_date' => $validated['log_date']],
            $validated
        );

        $status = $log->wasRecentlyCreated ? 201 : 200;
        $message = $log->wasRecentlyCreated ? 'Log saved successfully' : 'Log Updated successfully';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $log
        ], $status);;
    }

    // Show a single log entry
    public function show($id)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $log = NutritionLog::where('user_id', Auth::id())
            ->where('id', $id)
            ->first();

        if (!$log) {
            return response()->json([
                'message' => "Log with ID {$id} not found for this user.",
                'debug_user_id' => Auth::id()
            ], 404);
        }

        return response()->json($log);
    }

    // Delete a log entry
    public function destroy($id)
    {

        $log = NutritionLog::where('user_id', Auth::id())->find($id);

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Nutrition log not found'
            ], 404);
        }

        $log->delete();

        return response()->json([
            'success' => true,
            'message' => 'Log deleted successfully'
        ]);
    }

    public function getNutritionReport(Request $request)
    {
        $userId = auth()->id();
        $type = $request->query('type', 'weekly'); 
        
        $endDate = Carbon::today();
        $startDate = $this->getStartDate($type);

        $logs = NutritionLog::where('user_id', $userId)
            ->whereBetween('log_date', [$startDate, $endDate])
            ->get()
            ->keyBy(fn($item) => Carbon::parse($item->log_date)->format('Y-m-d'));

        $chartData = $this->generateNutritionChart($type, $startDate, $endDate, $logs);

        $avgWater = $this->getAverageHydration($userId, $startDate, $endDate); 
        $daysWithData = $logs->count();
        $totalDays = $startDate->diffInDays($endDate) + 1;

        return response()->json([
            'status' => 'success',
            'data' => [
                'chart_data' => $chartData,
                'statistics' => [
                    'average' => $avgWater . " Glasses GLS",
                    'consistency' => round(($daysWithData / $totalDays) * 100) . "%",
                    'best_streak' => "7 DAYS",
                    'current_trend' => "Improving",
                ],
                'bio_insight' => "Your nutrition quality is balanced. Maintaining high protein servings helps in physical recovery markers."
            ]
        ]);
    }


    private function getAverageHydration($userId, $startDate, $endDate) 
    {
        $avg = DB::table('sleep_logs')
            ->where('user_id', $userId)
            ->whereBetween('log_date', [$startDate, $endDate])
            ->avg('water_glasses');

        return round($avg ?? 0);
    }

    private function getStartDate($type) 
    {
        return match($type) {
            'monthly' => Carbon::today()->subDays(29),
            '3_months' => Carbon::today()->subMonths(3),
            default => Carbon::today()->subDays(6),
        };
    }

    private function generateNutritionChart($type, $startDate, $endDate, $logs) 
    {
        $data = [];
        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dateString = $date->format('Y-m-d');
            $log = $logs->get($dateString);

            if ($log) {
                $pWeight = $log->protein_servings * 10; 
                $cWeight = 0;
                $fWeight = 0;

                switch ($log->meal_balance) {
                    case 'balanced': $cWeight = 35; $fWeight = 35; break;
                    case 'high_carb': $cWeight = 50; $fWeight = 20; break;
                    case 'high_protein': $pWeight += 20; $cWeight = 25; $fWeight = 25; break;
                    case 'keto': $cWeight = 10; $fWeight = 60; break;
                    default: $cWeight = 30; $fWeight = 30;
                }

                $total = $pWeight + $cWeight + $fWeight;
                
                $proteinPct = round(($pWeight / $total) * 100);
                $carbsPct = round(($cWeight / $total) * 100);
                $fatsPct = 100 - ($proteinPct + $carbsPct); 

                $data[] = [
                    'label' => $date->format('D'), 
                    'protein' => $proteinPct, 
                    'carbs' => $carbsPct,   
                    'fats' => $fatsPct      
                ];
            } else {
                $data[] = [
                    'label' => $date->format('D'), 
                    'protein' => 0, 
                    'carbs' => 0,   
                    'fats' => 0      
                ];
            }
        }
        return $data;
    }
}