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
    public function index(Request $request)
    {
        try {
            $userId = $request->query('user_id') ?: ($request->query('client_id') ?: auth()->id());
            $today = $request->query('log_date') ?: ($request->query('date') ?: now()->format('Y-m-d'));

            $logs = NutritionLog::where('user_id', $userId)
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

        // Notify connected trainers about nutrition update
        if ($user = Auth::user()) {
            \App\Services\NotificationService::notifyConnectedTrainers(
                $user,
                new \App\Notifications\ClientActivityNotification(
                    "Nutrition Logged: {$user->name}",
                    "{$user->name} has logged nutrition for {$validated['log_date']}.",
                    'client_activity_log',
                    ['client_id' => $user->id, 'log_date' => $validated['log_date'], 'type' => 'nutrition']
                ),
                'program_milestone_updates'
            );
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $log
        ], $status);
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
        $userId = $request->query('user_id') ?: ($request->query('client_id') ?: auth()->id());
        $type = $request->query('type', 'weekly');

        $endDate   = Carbon::today();
        $startDate = $this->getStartDate($type);

        $groupedLogs = UserNutritionCalculate::where('user_id', $userId)
            ->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])
                  ->orWhereBetween('created_at', [$startDate->toDateString() . ' 00:00:00', $endDate->toDateString() . ' 23:59:59']);
            })
            ->get()
            ->groupBy(function($item) {
                return !empty($item->log_date) 
                    ? Carbon::parse($item->log_date)->format('Y-m-d') 
                    : Carbon::parse($item->created_at)->format('Y-m-d');
            });

        $adjustProgram = \App\Models\AdjustProgram::where('user_id', $userId)->first();
        $targetGoal    = \App\Models\TargetGoal::where('user_id', $userId)->where('is_active', true)->first();

        $targetCalories = (float)(
            $adjustProgram->calories 
            ?? $targetGoal->target_calories 
            ?? 2000
        );
        $targetProtein = (float)($adjustProgram->protein ?? ($targetGoal->protein_target ?? 0));
        $targetCarbs   = (float)($adjustProgram->carbs ?? ($targetGoal->carbs_target ?? 0));
        $targetFat     = (float)($adjustProgram->fat ?? ($targetGoal->fat_target ?? 0));

        $chartData = $this->generateNutritionChart($type, $startDate, $endDate, $groupedLogs, $targetCalories);

        $daysWithData = $groupedLogs->count();
        $totalDays    = $startDate->diffInDays($endDate) + 1;
        $totalEntries = $groupedLogs->flatten()->count();

        $avgCalories = $this->getAverageCalories($groupedLogs);

        $bestStreak = $this->calculateStreak($userId);

        $currentTrend = $this->calculateTrend($groupedLogs, $startDate, $endDate);

        $totalProtein = round($groupedLogs->flatten()->sum('protein_value'), 1);
        $totalCarbs   = round($groupedLogs->flatten()->sum('carbs_value'), 1);
        $totalFat     = round($groupedLogs->flatten()->sum('fat_value'), 1);

        // Current day calorie intake total box (over / under calculation)
        $todayDateStr  = Carbon::today()->format('Y-m-d');
        $todayLogs     = $groupedLogs->get($todayDateStr);
        $todayCalories = $todayLogs ? (float)$todayLogs->sum('calories_value') : 0.0;
        $diff          = $todayCalories - $targetCalories;
        $absDiff       = abs($diff);
        $diffPct       = $targetCalories > 0 ? round(($absDiff / $targetCalories) * 100) : 0;
        $intakePct     = $targetCalories > 0 ? round(($todayCalories / $targetCalories) * 100) : 0;

        $status = ($todayCalories == 0)
            ? 'no_data'
            : ($diff > 0 ? 'over' : ($diff < 0 ? 'under' : 'on_track'));

        $label = match($status) {
            'over'     => '+' . round($absDiff) . ' kcal over target',
            'under'    => '-' . round($absDiff) . ' kcal under target',
            'on_track' => 'On target',
            default    => '0 kcal logged today',
        };

        $currentDayCalorieBox = [
            'date'            => $todayDateStr,
            'intake_calories' => round($todayCalories),
            'target_calories' => round($targetCalories),
            'difference'      => round($diff),
            'abs_difference'  => round($absDiff),
            'status'          => $status,
            'is_over'         => $diff > 0,
            'is_under'        => $diff < 0,
            'percentage'      => $intakePct,
            'diff_percentage' => $diffPct,
            'label'           => $label,
            'badge'           => ($diff >= 0 ? '+' : '-') . $diffPct . '% ' . ($diff >= 0 ? 'over' : 'under'),
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'filter_applied'          => $type,
                'chart_data'              => $chartData,
                'current_day_calorie_box' => $currentDayCalorieBox,
                'target_macros'           => [
                    'calories'       => round($targetCalories),
                    'protein'        => round($targetProtein),
                    'carbs'          => round($targetCarbs),
                    'fat'            => round($targetFat),
                    'protein_target' => $targetProtein > 0 ? ($targetProtein . "g") : null,
                    'carbs_target'   => $targetCarbs > 0 ? ($targetCarbs . "g") : null,
                    'fat_target'     => $targetFat > 0 ? ($targetFat . "g") : null,
                ],
                'macro_totals' => [
                    'protein'       => $totalProtein . "g",
                    'carbs'         => $totalCarbs . "g",
                    'fat'           => $totalFat . "g",
                    'protein_value' => $totalProtein,
                    'carbs_value'   => $totalCarbs,
                    'fat_value'     => $totalFat,
                ],
                'statistics' => [
                    'average'           => $avgCalories . " kcal",
                    'target_calories'   => $targetCalories . " kcal",
                    'target_protein'    => $targetProtein > 0 ? ($targetProtein . "g") : null,
                    'target_carbs'      => $targetCarbs > 0 ? ($targetCarbs . "g") : null,
                    'target_fat'        => $targetFat > 0 ? ($targetFat . "g") : null,
                    'consistency'       => round(($daysWithData / $totalDays) * 100) . "%",
                    'best_streak'       => $bestStreak . " DAYS",
                    'current_trend'     => $currentTrend,
                    'total_entries'     => $totalEntries,
                    'total_protein'     => $totalProtein . "g",
                    'total_carbs'       => $totalCarbs . "g",
                    'total_fat'         => $totalFat . "g",
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

    private function generateNutritionChart($type, $startDate, $endDate, $groupedLogs, $targetCalories = 2000)
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
                $dayCalories  = (float)$dayNutritions->sum('calories_value');
                $proteinValue = (float)$dayNutritions->sum('protein_value');
                $carbsValue   = (float)$dayNutritions->sum('carbs_value');
                $fatValue     = (float)$dayNutritions->sum('fat_value');

                $totalMacros = $proteinValue + $carbsValue + $fatValue;

                if ($totalMacros > 0) {
                    $proteinPct = round(($proteinValue / $totalMacros) * 100, 1);
                    $carbsPct   = round(($carbsValue / $totalMacros) * 100, 1);
                    $fatsPct    = round(($fatValue / $totalMacros) * 100, 1);
                } else {
                    $proteinPct = $carbsPct = $fatsPct = 0;
                }

                $adherencePct = $targetCalories > 0 ? round(($dayCalories / $targetCalories) * 100) : 0;

                $data[] = [
                    'label'              => $label,
                    'date'               => $dateString,
                    'calories'           => round($dayCalories),
                    'calorie_percentage' => $adherencePct,
                    'adherence_pct'      => $adherencePct,
                    // Values in grams
                    'protein'            => round($proteinValue, 2),
                    'carbs'              => round($carbsValue, 2),
                    'fats'               => round($fatValue, 2),
                    'fat'                => round($fatValue, 2),
                    'protein_g'          => round($proteinValue, 2),
                    'carbs_g'            => round($carbsValue, 2),
                    'fats_g'             => round($fatValue, 2),
                    // Percentages
                    'protein_pct'        => $proteinPct,
                    'carbs_pct'          => $carbsPct,
                    'fats_pct'           => $fatsPct,
                    'protein_percentage' => $proteinPct,
                    'carbs_percentage'   => $carbsPct,
                    'fats_percentage'    => $fatsPct,
                ];
            } else {
                $data[] = [
                    'label'              => $label,
                    'date'               => $dateString,
                    'calories'           => 0,
                    'calorie_percentage' => 0,
                    'adherence_pct'      => 0,
                    'protein'            => 0,
                    'carbs'              => 0,
                    'fats'               => 0,
                    'fat'                => 0,
                    'protein_g'          => 0,
                    'carbs_g'            => 0,
                    'fats_g'             => 0,
                    'protein_pct'        => 0,
                    'carbs_pct'          => 0,
                    'fats_pct'           => 0,
                    'protein_percentage' => 0,
                    'carbs_percentage'   => 0,
                    'fats_percentage'    => 0,
                ];
            }
        }
        return $data;
    }

    private function calculateStreak($userId)
    {
        $dates = UserNutritionCalculate::where('user_id', $userId)
            ->whereNotNull('log_date')
            ->orderBy('log_date', 'desc')
            ->pluck('log_date')
            ->map(fn($d) => Carbon::parse($d)->toDateString())
            ->unique();

        if ($dates->isEmpty()) return 0;

        $streak = 0;
        $currentDate = Carbon::today();

        foreach ($dates as $logDate) {
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

        $diffPct = $firstHalfAvg > 0 ? (($secondHalfAvg - $firstHalfAvg) / $firstHalfAvg) * 100 : 0;

        if (abs($diffPct) <= 5) {
            return "Stable";
        }

        return $secondHalfAvg > $firstHalfAvg ? "Improving" : "Declining";
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