<?php

namespace App\Http\Controllers\NutritionLog;

use App\Http\Controllers\Controller;
use App\Models\AI\UserNutritionCalculate;
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
    try {

        $today = now()->format('Y-m-d');

        $logs = NutritionLog::where('user_id', auth()->id())
            ->whereDate('log_date', $today)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
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

        $endDate   = Carbon::today();
        $startDate = $this->getStartDate($type);

        // Same grouping idea as aggregateForDate() in store()/show() —
        // but grouped per day across the whole range instead of a single date
        $groupedLogs = UserNutritionCalculate::where('user_id', $userId)
            ->whereBetween('log_date', [$startDate, $endDate])
            ->get()
            ->groupBy(fn($item) => Carbon::parse($item->log_date)->format('Y-m-d'));

        $chartData = $this->generateNutritionChart($type, $startDate, $endDate, $groupedLogs);

        $daysWithData = $groupedLogs->count();
        $totalDays    = $startDate->diffInDays($endDate) + 1;

        $avgCalories = $this->getAverageCalories($groupedLogs);

        $bestStreak = $this->calculateBestStreak($groupedLogs, $startDate, $endDate);

        $currentTrend = $this->calculateTrend($groupedLogs, $startDate, $endDate);

        return response()->json([
            'status' => 'success',
            'data' => [
                'filter_applied' => $type,
                'chart_data' => $chartData,
                'statistics' => [
                    'average'       => $avgCalories . " kcal",
                    'consistency'   => round(($daysWithData / $totalDays) * 100) . "%",
                    'best_streak'   => $bestStreak . " DAYS",
                    'current_trend' => $currentTrend,
                ],
                'bio_insight' => "Your nutrition quality is balanced. Maintaining high protein servings helps in physical recovery markers."
            ]
        ]);
    }

    /**
     * Average daily calories — sum each day's calories (like aggregateForDate does
     * for a single date), then average across the days that actually have logs.
     */
    private function getAverageCalories($groupedLogs)
    {
        if ($groupedLogs->isEmpty()) {
            return 0;
        }

        $totalCalories = $groupedLogs->sum(function ($dayLogs) {
            return $dayLogs->sum('calories_value');
        });

        return round($totalCalories / $groupedLogs->count());
    }

    private function getStartDate($type)
    {
        return match($type) {
            'monthly'  => Carbon::today()->subDays(29),
            '3_months' => Carbon::today()->subMonths(3)->addDay(),
            default    => Carbon::today()->subDays(6),
        };
    }

    private function generateNutritionChart($type, $startDate, $endDate, $groupedLogs)
    {
        $data = [];

        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dateString = $date->format('Y-m-d');

            $label = match($type) {
                'weekly'               => $date->format('D'),
                'monthly', '3_months'  => $date->format('d M'),
                default                => $date->format('D'),
            };

            $dayNutritions = $groupedLogs->get($dateString);

            if ($dayNutritions && $dayNutritions->isNotEmpty()) {
                $proteinValue = $dayNutritions->sum('protein_value');
                $carbsValue   = $dayNutritions->sum('carbs_value');
                $fatValue     = $dayNutritions->sum('fat_value');

                $totalMacros = $proteinValue + $carbsValue + $fatValue;

                if ($totalMacros > 0) {
                    $proteinPct = round(($proteinValue / $totalMacros) * 100);
                    $carbsPct   = round(($carbsValue / $totalMacros) * 100);
                    $fatsPct    = 100 - ($proteinPct + $carbsPct);
                } else {
                    $proteinPct = $carbsPct = $fatsPct = 0;
                }

                $data[] = [
                    'label'   => $label,
                    'protein' => $proteinPct,
                    'carbs'   => $carbsPct,
                    'fats'    => $fatsPct
                ];
            } else {
                $data[] = [
                    'label'   => $label,
                    'protein' => 0,
                    'carbs'   => 0,
                    'fats'    => 0
                ];
            }
        }
        return $data;
    }

    private function calculateBestStreak($groupedLogs, $startDate, $endDate)
    {
        $currentStreak = 0;
        $bestStreak    = 0;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateString = $date->format('Y-m-d');

            if ($groupedLogs->has($dateString)) {
                $currentStreak++;
                if ($currentStreak > $bestStreak) {
                    $bestStreak = $currentStreak;
                }
            } else {
                $currentStreak = 0;
            }
        }

        return $bestStreak;
    }

    /**
     * Compare average daily calories in the first half of the range vs the
     * second half, to decide if the trend is Improving / Declining / Stable.
     */
    private function calculateTrend($groupedLogs, $startDate, $endDate)
    {
        $totalDays = $startDate->diffInDays($endDate) + 1;
        $midPoint  = $startDate->copy()->addDays(intdiv($totalDays, 2));

        $firstHalfTotal = 0; $firstHalfDays = 0;
        $secondHalfTotal = 0; $secondHalfDays = 0;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateString = $date->format('Y-m-d');
            $dayLogs = $groupedLogs->get($dateString);

            if ($dayLogs && $dayLogs->isNotEmpty()) {
                $dayCalories = $dayLogs->sum('calories_value');

                if ($date->lt($midPoint)) {
                    $firstHalfTotal += $dayCalories;
                    $firstHalfDays++;
                } else {
                    $secondHalfTotal += $dayCalories;
                    $secondHalfDays++;
                }
            }
        }

        if ($firstHalfDays === 0 && $secondHalfDays === 0) {
            return "No Data";
        }

        $firstHalfAvg  = $firstHalfDays > 0 ? $firstHalfTotal / $firstHalfDays : 0;
        $secondHalfAvg = $secondHalfDays > 0 ? $secondHalfTotal / $secondHalfDays : 0;

        if ($secondHalfAvg > $firstHalfAvg) {
            return "Improving";
        } elseif ($secondHalfAvg < $firstHalfAvg) {
            return "Declining";
        }

        return "Stable";
    }

    private function getAverageHydration($userId, $startDate, $endDate) 
    {
        $avg = DB::table('hydration_logs')
            ->where('user_id', $userId)
            ->whereBetween('log_date', [$startDate, $endDate])
            ->avg('water_glasses');

        return round($avg ?? 0);
    }

}