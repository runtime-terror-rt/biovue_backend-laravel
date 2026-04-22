<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ProjectionCredit;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class UserController extends Controller
{
    public function index()
    {
        $projectionCredits = ProjectionCredit::all()->keyBy('user_id');
        $users = User::with('profile', 'medicalHistory')->get();
        foreach ($users as $user) {
            $user->projection_limit = $projectionCredits->get($user->id)->projection_limit ?? 0;
        }
        return response()->json($users);
    }

    public function show($id)
    {
        $user = User::with('profile', 'medicalHistory')->find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        return response()->json($user);
    }

   public function individualUsers(Request $request)
    {
        try {
            $query = User::role('individual'); // Spatie scope

            if ($request->has('email')) {
                $email = $request->email;
                $query->where('email', 'like', "%{$email}%"); // partial match
            }

            $users = $query->select('id', 'name', 'email')->get(); // id added

            if ($users->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No individual users found.'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $users
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch users. Error: '.$e->getMessage()
            ], 500);
        }
    }


    public function getUserReport(Request $request)
    {
        try {
            $user = $request->user();

            $profile = DB::table('user_profiles')
                ->where('user_id', $user->id)
                ->first();

            if (!$profile) {
                return response()->json(['success' => false, 'message' => 'Profile not found'], 404);
            }

            $unit = $profile->unit ?? 'imperial'; 
            $weight = $profile->weight ?? 0;
            $height = $profile->height ?? 0; 

            $weightInKg = ($unit === 'imperial') ? $weight * 0.453592 : $weight;
            $heightInMeters = $height / 100;
            $bmi = $heightInMeters > 0 ? round($weightInKg / ($heightInMeters * $heightInMeters), 1) : 0;

            if ($unit === 'imperial') {
                $displayWeight = round($weight, 1) . ' lbs';
                
                $totalInches = $height / 2.54;
                $feet = floor($totalInches / 12);
                $inches = round($totalInches % 12);
                $displayHeight = "{$feet}'{$inches}\"";
            } else {
                $displayWeight = round($weight, 1) . ' kg';
                $displayHeight = $height . ' cm';
            }

            return response()->json([
                'success' => true,
                'unit_system' => $unit,
                'user_info' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'wellness_stats' => [
                    'wellness_score' => $profile->stress_level ?? 0,
                    'days_active' => $profile->workout_week ?? '0/7',
                    'data_logged' => 12,
                ],
                'health_overview' => [
                    'current_weight' => $displayWeight,
                    'current_height' => $displayHeight,
                    'bmi' => $bmi,
                    'nutrition_quality' => $profile->overall_diet_quality ?? 'N/A',
                    'weekly_workouts' => $profile->workout_week ?? '0 session',
                    'daily_steps' => number_format($profile->daily_step ?? 0),
                    'sleep_hours' => ($profile->sleep_hour ?? 0) . ' hrs',
                ],
                'fitness_goals' => [
                    'is_athletic' => (bool)$profile->is_athletic,
                    'toned' => (bool)$profile->toned,
                    'lean' => (bool)$profile->lean,
                    'muscular' => (bool)$profile->muscular,
                    'curvy_fit' => (bool)$profile->curvy_fit,
                ],
                'today_focus' => [
                    'diet' => 'Improve ' . ($profile->overall_diet_quality ?? 'Diet') . ' Quality',
                    'sleep' => 'Maintain ' . ($profile->sleep_hour ?? 0) . ' hours sleep'
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }


    public function getHealthReport($userId = null)
    {
        try {
            $id = $userId ?: auth()->id();
            
            $user = User::with(['profile', 'targetGoals', 'adjustProgram'])->find($id);
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }

            $unit = $user->profile->unit ?? 'imperial'; // 'metric' or 'imperial'
            $startOfWeek = now()->startOfWeek()->toDateString();
            $endOfWeek = now()->today()->toDateString();

            $activityLogs = DB::table('activity_logs')
                ->where('user_id', $id)
                ->whereBetween('log_date', [$startOfWeek, $endOfWeek])
                ->get();
                
            $nutritionLogs = DB::table('nutrition_logs')
                ->where('user_id', $id)
                ->whereBetween('log_date', [$startOfWeek, $endOfWeek])
                ->get();
                
            $stressLogs = DB::table('stress_logs')
                ->where('user_id', $id)
                ->whereBetween('log_date', [$startOfWeek, $endOfWeek])
                ->get();

            $rawWeight = $activityLogs->whereNotNull('weight')->last()->weight ?? ($user->profile->weight ?? 0);
            $rawTarget = $user->targetGoals->target_weight ?? 0;
            $height = $user->profile->height ?? 0; // In CM

            if ($unit === 'imperial') {
                $displayWeight = round($rawWeight * 2.20462, 1); // KG to LBS
                $displayTarget = round($rawTarget * 2.20462, 1); // KG to LBS
                $unitLabel = 'lbs';
            } else {
                $displayWeight = $rawWeight;
                $displayTarget = $rawTarget;
                $unitLabel = 'kg';
            }

            $weightDiff = round($displayWeight - $displayTarget, 1);

            //  BMI Calculation (Standard Metric Internal)
            $bmi = 0;
            if ($height > 0 && $rawWeight > 0) {
                $heightInMeters = $height / 100;
                $bmi = round($rawWeight / ($heightInMeters * $heightInMeters), 1);
            }

            //  Final JSON Response
            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'wellness_score' => method_exists($this, 'calculateWellnessScore') ? $this->calculateWellnessScore($id) : 0, 
                        'days_active' => $activityLogs->unique('log_date')->count() . '/7',
                        'data_logged_entries' => $activityLogs->count() + $nutritionLogs->count() + $stressLogs->count(),
                        'unit_system' => $unit
                    ],

                    'health_overview' => [
                        'weight' => [
                            'current' => $displayWeight . ' ' . $unitLabel,
                            'status' => $weightDiff > 0 ? "+{$weightDiff} {$unitLabel} above target" : "On track",
                            'coach_target' => $displayTarget . ' ' . $unitLabel 
                        ],
                        'bmi' => [
                            'current' => $bmi,
                            'status' => method_exists($this, 'getBmiStatus') ? $this->getBmiStatus($bmi) : 'N/A',
                            'ideal_range' => '18.5 - 24.9'
                        ],
                        'nutrition' => [
                            'last_meal_balance' => $nutritionLogs->last()->meal_balance ?? 'N/A',
                            'protein_servings' => $nutritionLogs->sum('protein_servings'),
                            'note' => $user->adjustProgram->note ?? 'Follow coach plan'
                        ],
                        'daily_steps' => [
                            'current' => number_format($activityLogs->sum('daily_steps')),
                            'coach_plan' => number_format($user->targetGoals->daily_step_goal ?? 0) . ' steps'
                        ],
                        'sleep_hours' => [
                            'current' => round($activityLogs->avg('sleep_hours') ?? 0, 1) . ' Hrs',
                            'coach_plan' => ($user->targetGoals->sleep_target ?? 'N/A') . ' Hrs'
                        ],
                        'hydration' => [
                            'current_glasses' => $activityLogs->sum('water_glasses'),
                            'target' => ($user->targetGoals->water_target ?? 'N/A') . ' glasses'
                        ],
                        'stress_and_mood' => [
                            'latest_mood' => ucfirst($stressLogs->last()->mood ?? 'stable'),
                            'avg_stress_level' => round($stressLogs->avg('stress_level') ?? 0, 1)
                        ]
                    ],

                    'settings' => [
                        'show_graphs' => (bool) ($user->adjustProgram->show_progress_graphs ?? true),
                        'show_ai' => (bool) ($user->adjustProgram->show_ai_insights ?? true)
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate health report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Helper to determine BMI Status
     */
    private function getBmiStatus($bmi) {
        if ($bmi < 18.5) return 'Underweight';
        if ($bmi <= 24.9) return 'Healthy weight';
        if ($bmi <= 29.9) return 'Overweight';
        return 'Obese range';
    }

    private function calculateWellnessScore($userId)
    {
        $startOfWeek = now()->startOfWeek()->toDateString();
        $endOfWeek = now()->endOfWeek()->toDateString();

        $activityCount = DB::table('activity_logs')->where('user_id', $userId)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->count();
        $avgStress = DB::table('stress_logs')->where('user_id', $userId)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->avg('stress_level') ?? 3;
        $nutritionCount = DB::table('nutrition_logs')->where('user_id', $userId)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->count();

        $activityScore = min(($activityCount / 7) * 50, 50);
        
        $nutritionScore = min(($nutritionCount / 7) * 30, 30);
        
        $stressScore = (5 - $avgStress) * 4;

        return round($activityScore + $nutritionScore + $stressScore);
    }

    public function getLogReport(Request $request, $userId = null)
    {
        $id = $userId ?: auth()->id();
        $days = (int) $request->query('days', 7); 
        $startDate = now()->subDays($days - 1)->toDateString();
        $endDate = now()->toDateString();

        $user = User::with(['profile', 'medicalHistory', 'targetGoals', 'adjustProgram'])->find($id);
        if (!$user) return response()->json(['message' => 'User not found'], 404);

        $unit = $user->profile->unit ?? 'imperial';
        $unitLabel = ($unit === 'imperial') ? 'lbs' : 'kg';

        $activityLogs = DB::table('activity_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $nutritionLogs = DB::table('nutrition_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $stressLogs = DB::table('stress_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();

        // Weight Logic
        $latestWeight = $activityLogs->whereNotNull('weight')->last()->weight ?? ($user->profile->weight ?? 0);
        $targetWeight = $user->targetGoals->target_weight ?? 0;

        // BMI Calculation
        $bmiScore = 0;
        $height = $user->profile->height ?? 0;
        if ($height > 0 && $latestWeight > 0) {
            $weightInKg = ($unit === 'imperial') ? $latestWeight * 0.453592 : $latestWeight;
            $heightInMeters = $height / 100;
            $bmiScore = round($weightInKg / ($heightInMeters * $heightInMeters), 1);
        }

        $nutritionEntriesCount = $nutritionLogs->count();
        $nutritionScore = min(($nutritionEntriesCount / $days) * 100, 100); 

        return response()->json([
            'success' => true,
            'health_overview' => [
                'weight' => [
                    'current' => $latestWeight . " " . $unitLabel,
                    'coach_target' => $targetWeight . " " . $unitLabel,
                    'insight' => $user->targetGoals->notes ?? "Stay consistent with your plan."
                ],
                'bmi' => [
                    'score' => $bmiScore,
                    'coach_target' => $user->targetGoals->target_bmi ?? 26.0, 
                    'status_label' => $this->getBmiStatus($bmiScore)
                ],
                'nutrition_quality' => [
                    'score' => round($nutritionScore), 
                    'status' => $nutritionLogs->last()->meal_balance ?? 'Balanced',
                    'coach_note' => $user->adjustProgram->note ?? "Improve consistency on weekends"
                ],
                'daily_steps' => [
                    'current' => (int) ($activityLogs->avg('daily_steps') ?? 0),
                    'coach_plan' => number_format($user->targetGoals->daily_step_goal ?? 0) . " steps"
                ],
                'sleep_hours' => [
                    'current' => round($activityLogs->avg('sleep_hours') ?? 0, 1) . " Hrs",
                    'coach_plan' => $user->adjustProgram->sleep_target_range ?? '7-8 Hrs'
                ]
            ]
        ]);
    }

    public function getDashboardData(Request $request, $userId = null)
    {
        $id = $userId ?: auth()->id();
        $days = (int) $request->query('days', 7); 
        $startDate = now()->subDays($days - 1)->toDateString();
        $endDate = now()->toDateString();

        $user = User::with(['profile', 'targetGoals', 'adjustProgram'])->find($id);
        if (!$user) return response()->json(['message' => 'User not found'], 404);

        $unit = $user->profile->unit ?? 'imperial';
        $unitLabel = ($unit === 'imperial') ? 'lbs' : 'kg';

        // Fetching All Logs
        $activityLogs = DB::table('activity_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $hydrationLogs = DB::table('hydration_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $sleepLogs = DB::table('sleep_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $stressLogs = DB::table('stress_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $nutritionLogs = DB::table('nutrition_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();

        $latestWeight = $activityLogs->whereNotNull('weight')->last()->weight ?? ($user->profile->weight ?? 0);
        $targetWeight = $user->targetGoals->target_weight ?? 0;
        
        // BMI Logic
        $bmiScore = 0;
        $height = $user->profile->height ?? 0;
        if ($height > 0 && $latestWeight > 0) {
            $weightInKg = ($unit === 'imperial') ? $latestWeight * 0.453592 : $latestWeight;
            $heightInMeters = $height / 100;
            $bmiScore = round($weightInKg / ($heightInMeters * $heightInMeters), 1);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'wellness_score' => [
                        'value' => $this->calculateWellnessScore($id), 
                        'max' => 100,
                        'trend' => "Updated recently",
                        'label' => "Coach-tracked"
                    ],
                    'days_active' => [
                        'current' => $activityLogs->unique('log_date')->count(),
                        'total' => $days,
                        'status' => "Activity log consistency"
                    ],
                    'data_logged' => [
                        'count' => $activityLogs->count() + $nutritionLogs->count() + $stressLogs->count(),
                        'label' => "Entries in selected period"
                    ]
                ],

                'health_overview' => [
                    'weight' => [
                        'current' => $latestWeight,
                        'unit' => $unitLabel,
                        'diff_label' => ($latestWeight - $targetWeight) > 0 ? "+" . round($latestWeight - $targetWeight, 1) . " $unitLabel above target" : "On track",
                        'insight' => "Based on your target goal"
                    ],
                    'bmi' => [
                        'score' => $bmiScore,
                        'range' => "18.5 - 24.9",
                        'status' => $this->getBmiStatus($bmiScore)
                    ],
                    'nutrition' => [
                        'score' => min(($nutritionLogs->count() / $days) * 100, 100), 
                        'status' => $nutritionLogs->last()->meal_balance ?? 'Balanced',
                        'message' => "Your meals are fueling you well"
                    ],
                    'workouts' => [
                        'completed' => $activityLogs->where('daily_steps', '>', 5000)->count(),
                        'goal' => $user->targetGoals->workout_days_goal ?? "4-5 sessions",
                        'insight' => "Workouts logged via steps/activity"
                    ],
                    'steps' => [
                        'current' => (int) ($activityLogs->avg('daily_steps') ?? 0),
                        'goal' => ($user->targetGoals->daily_step_goal ?? 8000) . " steps"
                    ],
                    'sleep' => [
                        'avg' => round($sleepLogs->avg('sleep_hours') ?? 0, 1),
                        'goal' => "7-9 hours"
                    ]
                ],

                'consistency_metrics' => [
                    $this->formatMetric("Sleep", $sleepLogs->avg('sleep_hours'), "hrs avg", $sleepLogs->count(), $days),
                    $this->formatMetric("Activity", $activityLogs->avg('daily_steps'), "steps avg", $activityLogs->count(), $days, true),
                    $this->formatMetric("Hydration", $hydrationLogs->avg('water_glasses'), "glasses avg", $hydrationLogs->count(), $days),
                    $this->formatMetric("Nutrition", $nutritionLogs->count(), "entries total", $nutritionLogs->count(), $days),
                    $this->formatMetric("Stress", $stressLogs->avg('stress_level'), "/10 level", $stressLogs->count(), $days)
                ]
            ]
        ]);
    }

    /** * Helper for Consistency Metrics formatting 
     */
    private function formatMetric($title, $avg, $suffix, $count, $days, $isSteps = false) {
        $val = $isSteps ? number_format($avg ?? 0) : round($avg ?? 0, 1);
        return [
            'title' => $title,
            'avg' => "$val $suffix",
            'status' => $count >= ($days * 0.6) ? "ON TRACK" : "Need Attention",
            'ratio' => "$count/$days Days"
        ];
    }

    public function toggleActiveUser($userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => "User has been " . ($user->is_active ? "activated" : "deactivated") . "."
        ]);
    }


    public function userOverviewData(Request $request, $userId = null)
    {
        $id = $userId ?: auth()->id();
        $days = (int) $request->query('days', 7); 
        $startDate = now()->subDays($days - 1)->toDateString();
        $endDate = now()->toDateString();

        $user = User::with(['profile', 'targetGoals' => function($q) {
            $q->where('is_active', true);
        }, 'adjustProgram'])->find($id);

        if (!$user) return response()->json(['message' => 'User not found'], 404);

        $unit = $user->profile->unit ?? 'imperial';
        $unitLabel = ($unit === 'imperial') ? 'lbs' : 'kg';

        $activityLogs = DB::table('activity_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $sleepLogs = DB::table('sleep_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        
        // Fetching using Model to leverage $casts
        $nutritionLogs = \App\Models\AI\UserNutritionCalculate::where('user_id', $id)
                            ->whereBetween('log_date', [$startDate, $endDate])
                            ->get();
        
        $target = $user->targetGoals;
        $latestWeight = $activityLogs->last()->weight ?? ($user->profile->weight ?? 0);
        $targetWeight = $target->target_weight ?? 0;
        
        // BMI Calculation
        $bmiScore = 0;
        $height = $user->profile->height ?? 0;
        if ($height > 0 && $latestWeight > 0) {
            $weightInKg = ($unit === 'imperial') ? $latestWeight * 0.453592 : $latestWeight;
            $heightInMeters = $height / 100;
            $bmiScore = round($weightInKg / ($heightInMeters * $heightInMeters), 1);
        }

        $actualLogsCount = $activityLogs->count() + $nutritionLogs->count() + $sleepLogs->count();
        $totalPossibleLogs = $days * 3; 
        $wellnessScore = $totalPossibleLogs > 0 ? min(round(($actualLogsCount / $totalPossibleLogs) * 100), 100) : 0;

        $nutritionQuality = $nutritionLogs->count() > 0 ? min(round(($nutritionLogs->count() / $days) * 100), 100) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'wellness_score' => [
                        'score' => $wellnessScore,
                        'max' => 100,
                        'trend' => $wellnessScore >= 70 ? "+4% vs last week" : "Needs attention", 
                        'footer' => "Based on logging consistency"
                    ],
                    'days_active' => [
                        'current' => $activityLogs->unique('log_date')->count(),
                        'total' => $days,
                        'status' => $activityLogs->unique('log_date')->count() >= ($target->weekly_workout_goal ?? 5) ? "On track" : "Behind schedule"
                    ],
                    'data_logged' => [
                        'count' => $actualLogsCount,
                        'label' => "AI-Analyzed Entries"
                    ]
                ],

                'health_overview' => [
                    'weight' => [
                        'current' => $latestWeight . " " . $unitLabel,
                        'diff' => ($latestWeight - $targetWeight > 0 ? "+" : "") . round($latestWeight - $targetWeight, 1) . " $unitLabel from target",
                        'coach_target' => ($targetWeight ?: 'N/A') . " " . $unitLabel
                    ],
                    'bmi' => [
                        'score' => $bmiScore,
                        'status' => method_exists($this, 'getBmiStatus') ? $this->getBmiStatus($bmiScore) : 'N/A'
                    ],
                    'nutrition' => [
                        'quality' => "$nutritionQuality/100",
                        'status' => $nutritionQuality >= 70 ? "Consistent" : "Inconsistent logging",
                        'last_meal' => $nutritionLogs->last() ? (function($foods) {
                                $decoded = is_string($foods) ? json_decode($foods, true) : $foods;
                                
                                $flattened = collect($decoded)->flatten()->toArray();
                                
                                if (empty($flattened)) return "Meal tracked";

                                return implode(', ', $flattened);
                            })($nutritionLogs->last()->foods) : "No meals logged today",
                        'avg_calories' => round($nutritionLogs->avg('calories_value') ?? 0) . " kcal/day"
                    ],
                    'steps' => [
                        'avg' => (int) ($activityLogs->avg('daily_steps') ?? 0),
                        'coach_plan' => number_format($target->daily_step_goal ?? 0) . " steps"
                    ],
                    'sleep' => [
                        'avg' => round($sleepLogs->avg('sleep_hours') ?? 0, 1) . " Hrs",
                        'coach_plan' => ($target->sleep_target ?? '7-8') . " Hrs"
                    ]
                ]
            ]
        ]);
    }

/**
 * Helper to safely format foods array/json to string
 */
private function formatFoods($foods)
{
    if (is_string($foods)) {
        $foods = json_decode($foods, true);
    }
    return is_array($foods) ? implode(', ', $foods) : "Meal tracked";
}

    private function formatConsistency($title, $logs, $totalDays, $target, $column = 'sleep_hours', $isStress = false)
    {
        $count = $logs->count();
        $avg = round($logs->avg($column) ?? 0, 1);
        
        $isOnTrack = $isStress ? ($avg <= $target) : ($count >= ($totalDays * 0.7));

        return [
            'title' => $title,
            'avg_text' => $avg . " avg this week",
            'status' => $isOnTrack ? "ON TRACK" : "Need Attention",
            'ratio' => "$count/$totalDays Days",
            'percentage' => round(($count / $totalDays) * 100)
        ];
    }

    public function getProgressReport(Request $request, $userId = null)
    {
        try {
            $id = $userId ?: auth()->id();
            
            $filter = $request->query('filter', 'weekly'); // default weekly
            
            switch ($filter) {
                case 'monthly':
                    $days = 30;
                    $groupBy = 'week';
                    break;
                case '3_months':
                    $days = 90;
                    $groupBy = 'month';
                    break;
                default: // weekly
                    $days = 7;
                    $groupBy = 'day';
                    break;
            }

            $startDate = now()->subDays($days - 1)->startOfDay();
            $endDate = now()->endOfDay();

            $activityLogs = \DB::table('activity_logs')
                ->where('user_id', $id)
                ->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->orderBy('log_date', 'asc')
                ->get();

            $nutritionLogs = \DB::table('user_nutrition_calculates')
                ->where('user_id', $id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'asc')
                ->get();

            $chartData = $this->processChartData($days, $activityLogs, $nutritionLogs);

            return response()->json([
                'success' => true,
                'filter_used' => $filter,
                'charts' => $chartData
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function processChartData(Request $request, $userId = null)
    {
        try {
            $id = $userId ?: auth()->id();
            $filter = $request->query('filter', 'weekly'); 

            if ($filter === 'monthly') {
                $days = 30;
            } elseif ($filter === '3_months') {
                $days = 90;
            } else {
                $days = 7;
            }

            $startDate = now()->subDays($days - 1)->toDateString();
            $endDate = now()->toDateString();

            $activityLogs = \DB::table('activity_logs')
                ->where('user_id', $id)
                ->whereBetween('log_date', [$startDate, $endDate])
                ->get();

            $nutritionLogs = \DB::table('user_nutrition_calculates')
                ->where('user_id', $id)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->get();

            $data = $this->calculateChartDetails($days, $activityLogs, $nutritionLogs);

            return response()->json([
                'success' => true,
                'filter' => $filter,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    protected function calculateChartDetails($days, $activityLogs, $nutritionLogs)
    {
        $labels = [];
        $weightData = [];
        $stepData = [];
        $sleepData = [];
        $protein = []; $carbs = []; $fats = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            
            $labels[] = ($days <= 7) ? now()->subDays($i)->format('D') : now()->subDays($i)->format('M d');

            $log = $activityLogs->where('log_date', $date)->first();
            $weightData[] = $log ? (float)$log->weight : null;
            $stepData[] = $log ? (int)$log->daily_steps : 0;
            $sleepData[] = $log ? (float)$log->sleep_hours : 0;

            $dayNutri = $nutritionLogs->filter(fn($n) => date('Y-m-d', strtotime($n->created_at)) == $date);
            $p = $dayNutri->sum('protein_value');
            $c = $dayNutri->sum('carbs_value');
            $f = $dayNutri->sum('fat_value');
            $total = $p + $c + $f;

            $protein[] = $total > 0 ? round(($p / $total) * 100) : 0;
            $carbs[] = $total > 0 ? round(($c / $total) * 100) : 0;
            $fats[] = $total > 0 ? round(($f / $total) * 100) : 0;
        }

        return [
            'weight' => ['labels' => $labels, 'data' => $weightData, 'total_progress' => $this->getWeightDiff($activityLogs)],
            'activity' => ['labels' => $labels, 'data' => $stepData],
            'sleep' => ['labels' => $labels, 'data' => $sleepData],
            'nutrition' => [
                'labels' => $labels,
                'datasets' => [
                    ['label' => 'Protein', 'data' => $protein, 'color' => '#34A853'],
                    ['label' => 'Carbs', 'data' => $carbs, 'color' => '#4285F4'],
                    ['label' => 'Fats', 'data' => $fats, 'color' => '#FBBC05']
                ]
            ]
        ];
    }

    protected function getWeightDiff($logs) {
        if ($logs->count() < 2) return "0.0 lbs";
        $diff = $logs->last()->weight - $logs->first()->weight;
        return ($diff > 0 ? "+" : "") . round($diff, 1) . " lbs";
    }

    // public function userOverviewChart(Request $request)
    // {
    //     $user = $request->user();
    //     $id = $user->id;
        
    //     $daysCount = (int) $request->query('days', 7); 
    //     $startDate = now()->subDays($daysCount - 1)->startOfDay();
    //     $endDate = now()->endOfDay();

    //     $userData = \App\Models\User::with(['profile', 'targetGoals', 'adjustProgram'])->find($id);

    //     $activity = DB::table('activity_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])->get();
    //     $hydration = DB::table('hydration_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])->get();
    //     $sleep = DB::table('sleep_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])->get();
    //     $stress = DB::table('stress_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])->get();
    //     $nutrition = DB::table('nutrition_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])->get();

    //     $chartData = [];
    //     $period = \Carbon\CarbonPeriod::create($startDate, $endDate);

    //     foreach ($period as $date) {
    //         $formattedDate = $date->toDateString();
    //         $actLog = $activity->where('log_date', $formattedDate)->first();
    //         $slpLog = $sleep->where('log_date', $formattedDate)->first();
    //         $nutLog = $nutrition->where('log_date', $formattedDate)->first();

    //         $chartData[] = [
    //             'label' => $daysCount <= 7 ? $date->format('D') : $date->format('d M'),
    //             'weight' => $actLog ? (float)$actLog->weight : null,
    //             'steps' => $actLog ? (int)$actLog->daily_steps : 0,
    //             'sleep_hours' => $slpLog ? (float)$slpLog->sleep_hours : 0,
    //             'nutrition' => [
    //                 'protein' => $nutLog ? (int)$nutLog->protein_servings : 0,
    //                 'carbs' => $nutLog ? (int)$nutLog->carb_quality : 0,
    //                 'fats' => $nutLog ? (int)$nutLog->	fat_sources : 0,
    //             ]
    //         ];
    //     }

    //     $latestWeight = $activity->last()->weight ?? ($userData->profile->weight ?? 0);
    //     $bmi = 0;
    //     if ($userData->profile->height > 0 && $latestWeight > 0) {
    //         $heightM = $userData->profile->height / 100;
    //         $weightK = $latestWeight * 0.453592; 
    //         $bmi = round($weightK / ($heightM * $heightM), 1);
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'summary' => [
    //             'wellness_score' => 72,
    //             'days_active' => $activity->unique('log_date')->count() . "/$daysCount",
    //             'entries_count' => $activity->count() + $nutrition->count() + $sleep->count()
    //         ],
    //         'health_overview' => [
    //             'weight' => [
    //                 'current' => $latestWeight,
    //                 'target' => $userData->targetGoals->target_weight ?? 0,
    //                 'note' => $userData->targetGoals->notes ?? "Target updated by coach"
    //             ],
    //             'bmi' => [
    //                 'score' => $bmi,
    //                 'target' => $userData->targetGoals->target_bmi ?? 26.0,
    //                 'status' => $bmi > 24.9 ? "Higher than range" : "Healthy"
    //             ],
    //             'steps' => [
    //                 'avg' => (int)$activity->avg('daily_steps'),
    //                 'target' => $userData->targetGoals->daily_step_goal ?? 6500
    //             ]
    //         ],
    //         'charts' => $chartData, 
    //         'consistency' => [
    //             'sleep' => $this->calcConsist('Sleep', $sleep, $daysCount, 7),
    //             'activity' => $this->calcConsist('Activity', $activity, $daysCount, 6500, 'daily_steps'),
    //             'hydration' => $this->calcConsist('Hydration', $hydration, $daysCount, 8, 'water_glasses'),
    //             'nutrition' => $this->calcConsist('Nutrition', $nutrition, $daysCount, 3, 'protein_servings')
    //         ]
    //     ]);
    // }

    public function userOverviewChart(Request $request, $userId = null)
    {
        try {
            $loggedInUser = auth()->user();
            $targetId = $userId ?: $loggedInUser->id;

            if ($targetId != $loggedInUser->id) {
                $isConnected = DB::table('connect_user_proffesions')
                    ->where('profession_id', $loggedInUser->id)
                    ->where('user_id', $targetId)
                    ->exists();

                if (!$isConnected) {
                    return response()->json(['success' => false, 'message' => 'Unauthorized client access'], 403);
                }
            }

            $daysCount = (int) $request->query('days', 7); 
            $startDate = now()->subDays($daysCount - 1)->startOfDay();
            $endDate = now()->endOfDay();

            $userData = \App\Models\User::with(['profile', 'targetGoals', 'adjustProgram'])->find($targetId);
            if (!$userData) return response()->json(['success' => false, 'message' => 'User not found'], 404);

            $activity = DB::table('activity_logs')
                ->where('user_id', $targetId)
                ->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->get();

            $sleep = DB::table('sleep_logs')
                ->where('user_id', $targetId)
                ->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->get();

            $nutrition = DB::table('user_nutrition_calculates')
                ->where('user_id', $targetId)
                ->whereBetween('log_date', [$startDate->toDateTimeString(), $endDate->toDateTimeString()])
                ->get();

            $chartData = [];
            $period = \Carbon\CarbonPeriod::create($startDate, $endDate);

            foreach ($period as $date) {
                $formattedDate = $date->toDateString(); 
                
                $actLog = $activity->where('log_date', $formattedDate)->first();
                $slpLog = $sleep->where('log_date', $formattedDate)->first();
                
                $nutLog = $nutrition->filter(function($item) use ($formattedDate) {
                    return date('Y-m-d', strtotime($item->log_date)) == $formattedDate;
                })->first();

                $chartData[] = [
                    'label' => $date->format('D'), 
                    'weight' => $actLog ? (float)$actLog->weight : null,
                    'steps' => $actLog ? (int)$actLog->daily_steps : 0,
                    'sleep_hours' => $slpLog ? (float)$slpLog->sleep_hours : 0,
                    'nutrition' => [
                        'protein' => $nutLog ? (float)$nutLog->protein_value : 0,
                        'carbs'   => $nutLog ? (float)$nutLog->carbs_value : 0,
                        'fats'    => $nutLog ? (float)$nutLog->fat_value : 0,
                    ]
                ];
            }

            $latestWeight = $activity->last()->weight ?? ($userData->profile->weight ?? 0);
            $targetGoal = $userData->targetGoals?->first();

            return response()->json([
                'success' => true,
                'client_name' => $userData->name,
                'charts' => $chartData, 
                'health_overview' => [
                    'weight' => [
                        'current' => $latestWeight,
                        'target' => $targetGoal->target_weight ?? 0
                    ],
                    'steps' => [
                        'avg' => (int)$activity->avg('daily_steps'),
                        'target' => $targetGoal->daily_step_goal ?? 6500
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    private function calcConsist($title, $logs, $days, $target, $col = 'sleep_hours')
    {
        $count = $logs->count();
        $avg = round($logs->avg($col) ?? 0, 1);
        return [
            'title' => $title,
            'avg' => "$avg avg this week",
            'percentage' => round(($count / $days) * 100),
            'status' => ($count >= ($days * 0.7)) ? "ON TRACK" : "Need Attention"
        ];
    }

    public function trainerOverview(Request $request)
    {
        try {
            $coach = auth()->user(); 
            $coachId = $coach->id;

            $todaysCheckinsCount = Schedule::where('trainer_id', $coachId)
                ->whereDate('schedule_date', now()->today())
                ->count();

            $activeCount = $coach->myClients()->count();

            $clientsQuery = $coach->myClients();

            $attentionCount = (clone $clientsQuery)->whereDoesntHave('activityLogs', function($q) {
                $q->where('log_date', '>=', now()->subDays(2)->toDateString());
            })->count();

            $clientsTable = $clientsQuery->with([
                    'targetGoals', 
                    'activityLogs' => fn($q) => $q->latest('log_date'),
                    'projectionCredits' 
                ])
                ->withCount('projections') 
                ->get()
                ->map(function($user) {
                    $latestLog = $user->activityLogs->first();
                    $lastLogDate = $latestLog ? \Carbon\Carbon::parse($latestLog->log_date) : null;
                    
                    $diff = $lastLogDate ? now()->startOfDay()->diffInDays($lastLogDate->startOfDay()) : null;

                    $goalData = $user->targetGoals?->first();
                    $used = $user->projections_count ?? 0;
                    $limit = $user->projectionCredits->projection_limit ?? 0;
                    $projectionText = "{$used}/{$limit}";

                    return [
                        'user_id'         => $user->id,
                        'user_name'       => $user->name,
                        'goal'            => $goalData ? ($goalData->target_weight . " lbs Target") : 'General wellness',
                        'projection_used' => $projectionText, 
                        'status'          => ($diff === null || $diff >= 3) ? 'Need attention' : 'On track',
                        'activity'        => $this->resolveActivityText($diff, $lastLogDate),
                    ];
                });

            return response()->json([
                'success' => true,
                'stats' => [
                    'active_clients'    => [
                        'value' => $activeCount, 
                        'label' => 'Currently coached'
                    ],
                    'needing_attention' => [
                        'value' => $attentionCount, 
                        'label' => 'Clients needing attention'
                    ],
                    'pending_messages'  => [
                        'value' => 0, 
                        'label' => 'Unread client messages'
                    ], 
                    'todays_checkins'   => [
                        'value' => $todaysCheckinsCount, 
                        'label' => 'Scheduled Today'
                    ] 
                ],
                'client_table' => $clientsTable,
                'today_actions' => [
                    ['title' => 'Review progress', 'desc' => 'Check recent updates', 'link' => '/admin/clients'],
                    ['title' => 'Send motivation', 'desc' => 'Send encouragement or reminders', 'link' => '/admin/messages'],
                    ['title' => 'Review check-ins', 'desc' => 'View scheduled check-ins', 'link' => '/admin/calendar']
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    private function resolveActivityText($diff, $date)
    {
        if ($diff === null) return "Missed check-in";
        if ($diff === 0) return "Logged today";
        if ($diff < 3) return "Logged " . $date->diffForHumans();
        return "No log in $diff days";
    }
    

    public function connectToProfession(Request $request)
    {
        $request->validate([
            'profession_id' => 'required|exists:users,id',
        ]);

        try {
            $user = auth()->user();
            $professionId = $request->profession_id;

            if ($user->id == $professionId) {
                return response()->json(['success' => false, 'message' => "You can't connect to yourself"], 400);
            }

            $user->myProfessionals()->syncWithoutDetaching([$professionId]);

            $connectedUser = User::with('profile')->find($professionId);

            return response()->json([
                'success' => true,
                'message' => 'Successfully connected to the professional',
                'data' => [
                    'connection_details' => [
                        'connected_at' => now()->format('Y-m-d H:i:s'),
                        'status'       => 'active'
                    ],
                    'professional_info' => [
                        'id'            => $connectedUser->id,
                        'name'          => $connectedUser->name,
                        'email'         => $connectedUser->email,
                        'user_type'     => $connectedUser->user_type,
                        'bio'           => $connectedUser->profile?->bio ?? null,
                        'profile_image' => $connectedUser->profile?->image 
                                        ? asset('storage/' . $connectedUser->profile->image) 
                                        : null,
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }   

    public function getMyConnections(Request $request)
    {
        try {
            $targetUserId = auth()->id(); 
            
            $targetUser = User::find($targetUserId);

            if (!$targetUser) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }

            if (in_array($targetUser->user_type, ['professional', 'trainer', 'coach'])) {
                $connections = $targetUser->belongsToMany(User::class, 'connect_user_proffesions', 'profession_id', 'user_id')
                                        ->get(['users.id', 'users.name', 'users.email']); 
                $message = "Connected clients for " . $targetUser->name;
            } 
            else {
                $connections = $targetUser->belongsToMany(User::class, 'connect_user_proffesions', 'user_id', 'profession_id')
                                        ->get(['users.id', 'users.name', 'users.email']); 
                $message = "Professionals followed by " . $targetUser->name;
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'queried_user' => [
                    'id' => $targetUser->id,
                    'type' => $targetUser->user_type,
                    'name' => $targetUser->name
                ],
                'count' => $connections->count(),
                'data' => $connections
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}

