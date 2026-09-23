<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ConnectUserProffesion;
use App\Models\ProjectionCredit;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Mail\ProfessionalConnectedMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Notifications\CoachMessageNotification;
use Carbon\Carbon;
class UserController extends Controller
{
    public function index()
    {
        $projectionCredits = ProjectionCredit::select('user_id', 'projection_limit')->get()->keyBy('user_id');
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

    public function professionalUsers(Request $request)
    {
        try {
            $query = User::role('professional');

            if ($request->has('email')) {
                $email = $request->email;
                $query->where('email', 'like', "%{$email}%");
            }

            $users = $query->select('id', 'name', 'email', 'profession_type')->get();

            if ($users->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No professional users found.'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $users
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch professional users. Error: '.$e->getMessage()
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

            $rawUnit = strtolower($profile->unit ?? 'imperial'); 
            $isImperial = in_array($rawUnit, ['imperial', 'lbs', 'lb', 'pound', 'pounds']);
            $unit = $isImperial ? 'imperial' : 'metric';
            $weight = (float)($profile->weight ?? 0);
            $height = (float)($profile->height ?? 0); 

            if ($height > 0 && $weight > 0) {
                $heightInches = $height > 100 ? ($height / 2.54) : $height;
                $heightMeters = $height > 100 ? ($height / 100) : (($height * 2.54) / 100);
                if ($isImperial) {
                    $bmi = round(($weight / ($heightInches * $heightInches)) * 703, 1);
                } else {
                    $bmi = round($weight / ($heightMeters * $heightMeters), 1);
                }
            } else {
                $bmi = 0;
            }

            if ($isImperial) {
                $displayWeight = round($weight, 1) . ' lbs';
                $heightInches = $height > 100 ? ($height / 2.54) : $height;
                $feet = floor($heightInches / 12);
                $inches = round(fmod($heightInches, 12));
                $displayHeight = "{$feet}'{$inches}\"";
            } else {
                $displayWeight = round($weight, 1) . ' kg';
                $displayHeight = ($height > 100 ? round($height, 1) : round($height * 2.54, 1)) . ' cm';
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
            $user = User::with(['profile', 'targetGoals'])->find($id);

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }

            $startOfWeek = now()->startOfWeek()->toDateString();
            $endOfWeek   = now()->today()->toDateString();

            $activityLogs  = DB::table('activity_logs')->where('user_id', $id)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->orderBy('log_date', 'desc')->get();
            $nutritionLogs = DB::table('user_nutrition_calculates')->where('user_id', $id)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->get();
            $hydrationLogs = DB::table('hydration_logs')->where('user_id', $id)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->get();
            $sleepLogs     = DB::table('sleep_logs')->where('user_id', $id)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->get();
            $stressLogs    = DB::table('stress_logs')->where('user_id', $id)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->get();

            $latestLog    = $activityLogs->first();
            $activityUnit = ($latestLog && !empty($latestLog->unit))
                                ? $latestLog->unit
                                : ($user->profile->unit ?? 'imperial');

            $profileUnit  = $user->profile->unit ?? 'imperial';
            $storedHeight = (float)($user->profile->height ?? 0);

            $latestMatchedLog = $activityLogs
                ->first(fn($log) => !empty($log->unit) && $log->unit === $activityUnit);

            $rawWeight = ($latestMatchedLog && isset($latestMatchedLog->weight) && $latestMatchedLog->weight > 0)
                            ? (float)$latestMatchedLog->weight
                            : (float)($user->profile->weight ?? 0);

            $bmi = 0;
            if ($storedHeight > 0 && $rawWeight > 0) {
                $heightInches = $storedHeight > 100 ? ($storedHeight / 2.54) : $storedHeight;
                $heightMeters = $storedHeight > 100 ? ($storedHeight / 100) : (($storedHeight * 2.54) / 100);

                if ($activityUnit === 'imperial') {
                    $bmi = ($heightInches > 0) ? round(($rawWeight / ($heightInches * $heightInches)) * 703, 1) : 0;
                } else {
                    $bmi = ($heightMeters > 0) ? round($rawWeight / ($heightMeters * $heightMeters), 1) : 0;
                }
            }

            $bmiStatus = match(true) {
                $bmi <= 0   => 'No data',
                $bmi < 18.5 => 'Underweight',
                $bmi < 25.0 => 'Healthy weight',
                $bmi < 30.0 => 'Overweight',
                default     => 'Obese',
            };

            $targetWeightRaw = (float)($user->targetGoals->target_weight ?? 0);
            $targetUnit      = !empty($user->targetGoals->unit)
                                ? $user->targetGoals->unit
                                : $activityUnit;

            if ($targetUnit === $activityUnit) {
                $targetWeight = $targetWeightRaw;
            } elseif ($activityUnit === 'imperial') {
                $targetWeight = round($targetWeightRaw * 2.20462, 1); 
            } else {
                $targetWeight = round($targetWeightRaw / 2.20462, 1); 
            }

            $weightLabel = $activityUnit === 'imperial' ? ' lbs' : ' kg';
            $activeDates = collect()
                ->merge($activityLogs->pluck('log_date'))
                ->merge($nutritionLogs->pluck('log_date'))
                ->merge($hydrationLogs->pluck('log_date'))
                ->merge($sleepLogs->pluck('log_date'))
                ->merge($stressLogs->pluck('log_date'))
                ->filter()
                ->map(fn($d) => \Carbon\Carbon::parse($d)->toDateString())
                ->unique()
                ->values();

            $daysActive = $activeDates->count();

            $avgSteps = (float)($activityLogs->avg('daily_steps') ?? 0);

            $matchedSleepLogs = $sleepLogs->filter(
                fn($log) => empty($log->unit) || $log->unit === $activityUnit
            );
            $avgSleep = $matchedSleepLogs->count() > 0
                            ? (float)$matchedSleepLogs->avg('sleep_hours')
                            : (float)($activityLogs->avg('sleep_hours') ?? 0);

            // Calculate daily average hydration
            $daysCount = max(1, \Carbon\Carbon::parse($startOfWeek)->diffInDays(\Carbon\Carbon::parse($endOfWeek)) + 1);
            
            $totalWaterGlasses = $hydrationLogs->sum('water_glasses');
            $totalWaterOz = $hydrationLogs->sum(function($log) {
                return isset($log->water_oz) && (float)$log->water_oz > 0 
                    ? (float)$log->water_oz 
                    : ((float)($log->water_glasses ?? 0) * 8);
            });

            // Daily averages
            $avgDailyGlasses = round($totalWaterGlasses / $daysCount, 1);
            $avgDailyOz = round($totalWaterOz / $daysCount, 1);

            // Recommended daily water intake based on profile
            $profileWeight = (float)($user->profile->weight ?? 0);
            if ($profileWeight > 0) {
                if ($activityUnit === 'imperial') {
                    $recommendedWaterOz = round($profileWeight * 0.5, 0);
                } else {
                    $recommendedWaterOz = round(($profileWeight * 35) / 29.5735, 0);
                }
            } else {
                $recommendedWaterOz = 64; // Default 64 oz (8 glasses)
            }

            $recommendedGlasses = round($recommendedWaterOz / 8, 0);
            $waterTarget = (float)($user->targetGoals->water_target ?? 0);
            if ($waterTarget <= 0) {
                $waterTarget = $recommendedGlasses;
            }

            $stepGoal    = (int)($user->targetGoals->daily_step_goal   ?? 10000);
            $sleepTarget = (float)($user->targetGoals->sleep_target    ?? 8);

            // Wellness Score (max 100)
            $wellnessScore  = 0;
            $wellnessScore += min(40, ($daysActive   / 7)                    * 40);
            $wellnessScore += min(25, ($avgSteps     / max($stepGoal,    1)) * 25);
            $wellnessScore += min(20, ($avgSleep     / max($sleepTarget, 1)) * 20);
            $wellnessScore += min(15, ($avgDailyGlasses / max($waterTarget, 1)) * 15);
            $wellnessScore  = min(100, (int)round($wellnessScore));

            return response()->json([
                'success' => true,
                'data'    => [
                    'summary' => [
                        'wellness_score'      => $wellnessScore,
                        'logs_summary' => [
                            'activity_days'   => $daysActive . '/7',
                        ],
                        'data_logged_entries' => $activityLogs->count()
                                            + $nutritionLogs->count()
                                            + $hydrationLogs->count()
                                            + $sleepLogs->count()
                                            + $stressLogs->count(),
                    ],
                    'health_overview' => [
                        'weight' => [
                            'current'      => round($rawWeight, 1) . $weightLabel,
                            'status'       => ($targetWeight > 0 && $rawWeight > $targetWeight)
                                                ? 'Above target' : 'On track',
                            'coach_target' => $targetWeight . $weightLabel,
                        ],
                        'bmi' => [
                            'current'     => round($bmi, 1),
                            'status'      => $bmiStatus,
                            'ideal_range' => '18.5 - 24.9',
                        ],
                        'nutrition' => [
                            'total_calories' => round($nutritionLogs->sum('calories_value'), 2),
                            'total_protein'  => round($nutritionLogs->sum('protein_value'),  2),
                            'total_carbs'    => round($nutritionLogs->sum('carbs_value'),    2),
                            'total_fat'      => round($nutritionLogs->sum('fat_value'),      2),
                        ],
                        'daily_steps' => [
                            'current'    => number_format((int)round($avgSteps)),
                            'coach_plan' => number_format($stepGoal) . ' steps',
                        ],
                        'sleep_hours' => [
                            'current'    => round($avgSleep, 1) . ' Hrs',
                            'coach_plan' => $sleepTarget . ' Hrs',
                        ],
                        'hydration' => [
                            'current'         => $avgDailyGlasses,
                            'current_glasses' => $avgDailyGlasses . ' glasses',
                            'current_oz'      => $avgDailyOz . ' oz',
                            'target'          => $waterTarget . ' glasses',
                            'target_glasses'  => $waterTarget . ' glasses',
                            'target_oz'       => ($waterTarget * 8) . ' oz',
                            'recommended_oz'  => $recommendedWaterOz . ' oz',
                        ],
                        'stress_and_mood' => [
                            'latest_mood'      => ucfirst($stressLogs->last()->mood ?? 'stable'),
                            'avg_stress_level' => round($stressLogs->avg('stress_level') ?? 0, 1),
                        ],
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating report: ' . $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Helper to determine BMI Status
     */
    public function getBmiStatus($bmi) {
        if ($bmi < 18.5) return 'Underweight';
        if ($bmi <= 24.9) return 'Healthy weight';
        if ($bmi <= 29.9) return 'Overweight';
        return 'Obese range';
    }

    /**
     * Smart BMI calculation that automatically handles inches, feet, cm, lbs, kg
     * regardless of whether unit is imperial or metric or mixed dirty data.
     */
    public function calculateSmartBMI($weight, $height, $unit = 'imperial')
    {
        $weight = (float) $weight;
        $height = (float) $height;

        if ($weight <= 0 || $height <= 0) {
            return 0;
        }

        // Smart height normalization to meters
        if ($height > 100) {
            // cm (e.g. 150-220 cm)
            $heightInMeters = $height / 100;
        } elseif ($height <= 10) {
            // feet (e.g. 5.5, 5.8, 6.0 ft)
            $heightInMeters = $height * 0.3048;
        } else {
            // inches (e.g. 50-90 inches)
            $heightInMeters = $height * 0.0254;
        }

        if ($heightInMeters <= 0.4) {
            return 0;
        }

        // Smart weight normalization to kg
        if ($unit === 'imperial') {
            $weightInKg = $weight * 0.453592;
        } else {
            // If metric, but user likely entered lbs (> 140 lbs)
            if ($weight > 140 && ($weight / ($heightInMeters * $heightInMeters)) > 55) {
                $weightInKg = $weight * 0.453592;
            } else {
                $weightInKg = $weight;
            }
        }

        $bmi = round($weightInKg / ($heightInMeters * $heightInMeters), 1);

        // Safeguard check against unrealistic numbers
        if ($bmi > 80 && $height > 10 && $height <= 100) {
            $altHeightMeters = $height * 0.0254;
            $altBmi = round($weightInKg / ($altHeightMeters * $altHeightMeters), 1);
            if ($altBmi >= 15 && $altBmi <= 60) {
                return $altBmi;
            }
        }

        return $bmi;
    }

    /**
     * Helper to get accurate nutrition logs from both user_nutrition_calculates and nutrition_logs
     */
    private function getNutritionLogsCollection($userId, $startDate, $endDate)
    {
        $calculates = \App\Models\AI\UserNutritionCalculate::where('user_id', $userId)
            ->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('log_date', [$startDate, $endDate])
                  ->orWhereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            })
            ->get();

        if ($calculates->isNotEmpty()) {
            return $calculates;
        }

        return DB::table('nutrition_logs')
            ->where('user_id', $userId)
            ->whereBetween('log_date', [$startDate, $endDate])
            ->get();
    }

    private function calculateWellnessScore($userId)
    {
        $startOfWeek = now()->startOfWeek()->toDateString();
        $endOfWeek = now()->endOfWeek()->toDateString();

        $activityCount = DB::table('activity_logs')->where('user_id', $userId)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->count();
        $avgStress = DB::table('stress_logs')->where('user_id', $userId)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->avg('stress_level') ?? 3;
        $nutritionLogs = $this->getNutritionLogsCollection($userId, $startOfWeek, $endOfWeek);
        $nutritionCount = $nutritionLogs->count();

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
        $hydrationLogs = DB::table('hydration_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $sleepLogs = DB::table('sleep_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $stressLogs = DB::table('stress_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $nutritionLogs = $this->getNutritionLogsCollection($id, $startDate, $endDate);

        // Weight Logic
        $latestWeight = $activityLogs->whereNotNull('weight')->last()->weight ?? ($user->profile->weight ?? 0);
        $targetWeight = $user->targetGoals->target_weight ?? 0;

        // Smart BMI Calculation
        $height = $user->profile->height ?? 0;
        $bmiScore = $this->calculateSmartBMI($latestWeight, $height, $unit);

        $nutritionEntriesCount = $nutritionLogs->count();
        $nutritionScore = min(($nutritionEntriesCount / $days) * 100, 100); 

        // Sleep hours: check sleep_logs first, fallback to activityLogs
        $avgSleep = $sleepLogs->isNotEmpty() ? round($sleepLogs->avg('sleep_hours'), 1) : round($activityLogs->avg('sleep_hours') ?? 0, 1);

        // Hydration average
        $avgGlasses = round($hydrationLogs->avg('water_glasses') ?? 0, 1);
        $avgOz = round($hydrationLogs->avg('water_oz') ?? ($avgGlasses * 8), 1);
        if ($avgGlasses > 30) {
            $avgOz = $avgGlasses;
            $avgGlasses = round($avgOz / 8, 1);
        }

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
                    'entries_count' => $nutritionEntriesCount,
                    'status' => $nutritionLogs->last()->meal_balance ?? ($nutritionEntriesCount > 0 ? 'Logged' : 'No entries'),
                    'coach_note' => $user->adjustProgram->note ?? "Improve consistency on weekends"
                ],
                'daily_steps' => [
                    'current' => (int) ($activityLogs->avg('daily_steps') ?? 0),
                    'coach_plan' => number_format($user->targetGoals->daily_step_goal ?? 0) . " steps"
                ],
                'sleep_hours' => [
                    'current' => $avgSleep . " Hrs",
                    'coach_plan' => $user->adjustProgram->sleep_target_range ?? '7-8 Hrs'
                ],
                'hydration' => [
                    'current' => $avgGlasses . " Glasses (" . $avgOz . " oz)",
                    'coach_target' => ($user->targetGoals->water_target ?? 8) . " Glasses"
                ],
                'stress' => [
                    'current' => round($stressLogs->avg('stress_level') ?? 0, 1) . "/10",
                    'status' => (round($stressLogs->avg('stress_level') ?? 0, 1) <= 4) ? "Low Stress" : "Need Attention"
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
        $nutritionLogs = $this->getNutritionLogsCollection($id, $startDate, $endDate);

        $latestWeight = $activityLogs->whereNotNull('weight')->last()->weight ?? ($user->profile->weight ?? 0);
        $targetWeight = $user->targetGoals->target_weight ?? 0;
        
        // Smart BMI Logic
        $height = $user->profile->height ?? 0;
        $bmiScore = $this->calculateSmartBMI($latestWeight, $height, $unit);

        // Sleep hours: check sleep_logs first, fallback to activityLogs
        $avgSleep = $sleepLogs->isNotEmpty() ? round($sleepLogs->avg('sleep_hours'), 1) : round($activityLogs->avg('sleep_hours') ?? 0, 1);

        // Hydration average: sanitize corrupted entries
        $avgHydrationGlasses = round($hydrationLogs->avg('water_glasses') ?? 0, 1);
        $avgHydrationOz = round($hydrationLogs->avg('water_oz') ?? ($avgHydrationGlasses * 8), 1);
        if ($avgHydrationGlasses > 30) {
            $avgHydrationOz = $avgHydrationGlasses;
            $avgHydrationGlasses = round($avgHydrationOz / 8, 1);
        }

        $nutritionCount = $nutritionLogs->count();
        $nutritionScore = min(($nutritionCount / $days) * 100, 100);

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
                        'count' => $activityLogs->count() + $nutritionCount + $stressLogs->count() + $sleepLogs->count() + $hydrationLogs->count(),
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
                        'score' => round($nutritionScore), 
                        'entries_count' => $nutritionCount,
                        'status' => $nutritionLogs->last()->meal_balance ?? ($nutritionCount > 0 ? 'Consistent' : 'No entries'),
                        'message' => $nutritionCount > 0 ? "Meals successfully tracked" : "Log meals to track fuel"
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
                        'avg' => $avgSleep,
                        'goal' => "7-9 hours"
                    ],
                    'hydration' => [
                        'current' => $avgHydrationGlasses . " Glasses (" . $avgHydrationOz . " oz)",
                        'goal' => ($user->targetGoals->water_target ?? 8) . " Glasses"
                    ],
                    'stress' => [
                        'current' => round($stressLogs->avg('stress_level') ?? 0, 1) . "/10",
                        'status' => (round($stressLogs->avg('stress_level') ?? 0, 1) <= 4) ? "Low Stress" : "Need Attention"
                    ]
                ],

                'consistency_metrics' => [
                    $this->formatMetric("Sleep", $avgSleep, "hrs avg", $sleepLogs->isNotEmpty() ? $sleepLogs->count() : $activityLogs->whereNotNull('sleep_hours')->count(), $days),
                    $this->formatMetric("Activity", $activityLogs->avg('daily_steps'), "steps avg", $activityLogs->count(), $days, true),
                    $this->formatMetric("Hydration", $avgHydrationGlasses, "glasses avg", $hydrationLogs->count(), $days),
                    $this->formatMetric("Nutrition", $nutritionCount, "entries total", $nutritionCount, $days),
                    $this->formatMetric("Stress", $stressLogs->avg('stress_level'), "/10 level", $stressLogs->count(), $days)
                ]
            ]
        ]);
    }

    /** * Helper for Consistency Metrics formatting 
     */
    private function formatMetric($title, $avg, $suffix, $count, $days, $isSteps = false) 
    {
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

            $sleepLogs = \DB::table('sleep_logs')
                ->where('user_id', $id)
                ->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->orderBy('log_date', 'asc')
                ->get();

            $hydrationLogs = \DB::table('hydration_logs')
                ->where('user_id', $id)
                ->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->orderBy('log_date', 'asc')
                ->get();

            $stressLogs = \DB::table('stress_logs')
                ->where('user_id', $id)
                ->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->orderBy('log_date', 'asc')
                ->get();

            $nutritionLogs = $this->getNutritionLogsCollection($id, $startDate->toDateString(), $endDate->toDateString());

            $chartData = $this->calculateChartDetails($days, $activityLogs, $nutritionLogs, $sleepLogs, $hydrationLogs, $stressLogs);

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

            $sleepLogs = \DB::table('sleep_logs')
                ->where('user_id', $id)
                ->whereBetween('log_date', [$startDate, $endDate])
                ->get();

            $hydrationLogs = \DB::table('hydration_logs')
                ->where('user_id', $id)
                ->whereBetween('log_date', [$startDate, $endDate])
                ->get();

            $stressLogs = \DB::table('stress_logs')
                ->where('user_id', $id)
                ->whereBetween('log_date', [$startDate, $endDate])
                ->get();

            $nutritionLogs = $this->getNutritionLogsCollection($id, $startDate, $endDate);

            $data = $this->calculateChartDetails($days, $activityLogs, $nutritionLogs, $sleepLogs, $hydrationLogs, $stressLogs);

            return response()->json([
                'success' => true,
                'filter' => $filter,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    protected function calculateChartDetails($days, $activityLogs, $nutritionLogs, $sleepLogs = null, $hydrationLogs = null, $stressLogs = null)
    {
        $sleepLogs = $sleepLogs ?? collect();
        $hydrationLogs = $hydrationLogs ?? collect();
        $stressLogs = $stressLogs ?? collect();

        $labels = [];
        $weightData = [];
        $stepData = [];
        $sleepData = [];
        $hydrationData = [];
        $stressData = [];
        $protein = []; $carbs = []; $fats = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            
            $labels[] = ($days <= 7) ? now()->subDays($i)->format('D') : now()->subDays($i)->format('M d');

            $log = $activityLogs->where('log_date', $date)->first();
            $sleepLog = $sleepLogs->where('log_date', $date)->first();
            $hydrationLog = $hydrationLogs->where('log_date', $date)->first();
            $stressLog = $stressLogs->where('log_date', $date)->first();

            $weightData[] = $log ? (float)$log->weight : null;
            $stepData[] = $log ? (int)$log->daily_steps : 0;
            
            // Sleep: preference to sleep_logs, fallback to activity_logs
            if ($sleepLog && isset($sleepLog->sleep_hours)) {
                $sleepData[] = (float)$sleepLog->sleep_hours;
            } elseif ($log && isset($log->sleep_hours)) {
                $sleepData[] = (float)$log->sleep_hours;
            } else {
                $sleepData[] = 0;
            }

            // Hydration
            if ($hydrationLog) {
                $rawGlasses = (float)$hydrationLog->water_glasses;
                if ($rawGlasses > 30) {
                    $hydrationData[] = round($rawGlasses / 8, 1);
                } else {
                    $hydrationData[] = $rawGlasses;
                }
            } else {
                $hydrationData[] = 0;
            }

            // Stress
            $stressData[] = $stressLog ? (float)$stressLog->stress_level : 0;

            $dayNutri = $nutritionLogs->filter(function($n) use ($date) {
                $createdDate = isset($n->log_date) ? date('Y-m-d', strtotime($n->log_date)) : date('Y-m-d', strtotime($n->created_at));
                return $createdDate == $date;
            });
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
            'hydration' => ['labels' => $labels, 'data' => $hydrationData],
            'stress' => ['labels' => $labels, 'data' => $stressData],
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

    protected function getWeightDiff($logs) 
    {
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

            $hydration = DB::table('hydration_logs')
                ->where('user_id', $targetId)
                ->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->get();

            $stress = DB::table('stress_logs')
                ->where('user_id', $targetId)
                ->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->get();

            $chartData = [];
            $period = \Carbon\CarbonPeriod::create($startDate, $endDate);

            foreach ($period as $date) {
                $formattedDate = $date->toDateString(); 
                
                $actLog = $activity->where('log_date', $formattedDate)->first();
                $slpLog = $sleep->where('log_date', $formattedDate)->first();
                $hydLog = $hydration->where('log_date', $formattedDate)->first();
                $strLog = $stress->where('log_date', $formattedDate)->first();
                
                $nutLog = $nutrition->filter(function($item) use ($formattedDate) {
                    $itemDate = isset($item->log_date) ? date('Y-m-d', strtotime($item->log_date)) : date('Y-m-d', strtotime($item->created_at));
                    return $itemDate == $formattedDate;
                })->first();

                $rawHydrationGlasses = $hydLog ? (float)$hydLog->water_glasses : 0;
                if ($rawHydrationGlasses > 30) {
                    $rawHydrationGlasses = round($rawHydrationGlasses / 8, 1);
                }

                $chartData[] = [
                    'label' => $date->format('D'), 
                    'weight' => $actLog ? (float)$actLog->weight : null,
                    'steps' => $actLog ? (int)$actLog->daily_steps : 0,
                    'sleep_hours' => $slpLog ? (float)$slpLog->sleep_hours : ($actLog ? (float)$actLog->sleep_hours : 0),
                    'hydration' => $rawHydrationGlasses,
                    'stress' => $strLog ? (float)$strLog->stress_level : 0,
                    'nutrition' => [
                        'protein' => $nutLog ? (float)$nutLog->protein_value : 0,
                        'carbs'   => $nutLog ? (float)$nutLog->carbs_value : 0,
                        'fats'    => $nutLog ? (float)$nutLog->fat_value : 0,
                    ]
                ];
            }

            $latestWeight = $activity->last()->weight ?? ($userData->profile->weight ?? 0);
            $targetGoal = $userData->targetGoals;

            $avgDailyGlasses = round($hydration->avg('water_glasses') ?? 0, 1);
            $avgDailyOz = round($hydration->avg('water_oz') ?? ($avgDailyGlasses * 8), 1);
            if ($avgDailyGlasses > 30) {
                $avgDailyOz = $avgDailyGlasses;
                $avgDailyGlasses = round($avgDailyOz / 8, 1);
            }
            $projectionsCount = DB::table('projection_data')->where('user_id', $targetId)->count();

            return response()->json([
                'success' => true,
                'client_name' => $userData->name,
                'charts' => $chartData, 
                'health_overview' => [
                    'weight' => [
                        'current' => $latestWeight,
                        'target'  => $targetGoal->target_weight ?? 0,
                        'unit'    => $userData->profile->unit ?? 'imperial'
                    ],
                    'steps' => [
                        'avg'    => (int)$activity->avg('daily_steps'),
                        'target' => $targetGoal->daily_step_goal ?? 6500
                    ],
                    'sleep' => [
                        'avg'    => round($sleep->avg('sleep_hours') ?? 0, 1),
                        'target' => $targetGoal->sleep_target ?? 8
                    ],
                    'hydration' => [
                        'avg_daily_glasses' => $avgDailyGlasses,
                        'avg_daily_oz'      => $avgDailyOz,
                        'target_glasses'    => $targetGoal->water_target ?? 8
                    ],
                    'nutrition' => [
                        'avg_calories' => round($nutrition->avg('calories_value') ?? 0),
                        'total_logs'   => $nutrition->count()
                    ],
                    'projections_used' => [
                        'count' => $projectionsCount
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

            $clientsTable = $coach->myClients()
                ->with([
                    'targetGoals',
                    'activityLogs' => fn($q) => $q->latest('log_date'),
                    'projectionCredits'
                ])
                ->withPivot('created_at as connected_at') 
                ->withCount('projectionDatas')
                ->get()
                ->map(function($user) {
                    $latestLog = $user->activityLogs->first();
                    $lastLogDate = $latestLog ? \Carbon\Carbon::parse($latestLog->log_date) : null;
                    
                    $diff = $lastLogDate ? now()->startOfDay()->diffInDays($lastLogDate->startOfDay()) : null;

                    $goalData = $user->targetGoals; 
                    $used = $user->projection_datas_count ?? 0;
                    $limit = $user->projectionCredits->projection_limit ?? 0;
                    
                    return [
                        'user_id'         => $user->id,
                        'user_name'       => $user->name,
                        'goal'            => $goalData ? ($goalData->target_weight . " lbs Target") : 'General wellness',
                        'projection_used' => "{$used}/{$limit}", 
                        'status'          => ($diff === null || $diff >= 3) ? 'Need attention' : 'On track',
                        'activity'        => $this->resolveActivityText($diff, $lastLogDate),
                        'connected_at' => ($user->pivot && $user->pivot->created_at) 
                            ? \Carbon\Carbon::parse($user->pivot->created_at)->format('M d, Y') 
                            : 'N/A',
                    ];
                });

            $attentionCount = $clientsTable->where('status', 'Need attention')->count();

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

            $creditRecord = ProjectionCredit::where('user_id', $professionId)->first();

            if (!$creditRecord) {
                return response()->json(['success' => false, 'message' => 'Professional configuration not found.'], 404);
            }

            if ($user->is_invited != 0 && $creditRecord->member_limit <= 0) {
                return response()->json(['success' => false, 'message' => 'This professional has reached their maximum member limit.'], 400);
            }

            $connectedUser = User::with('profile')->find($professionId);

            if (!$connectedUser) {
                return response()->json(['success' => false, 'message' => 'Professional not found.'], 404);
            }

            DB::beginTransaction();

            $user->myProfessionals()->syncWithoutDetaching([$professionId]);

            if ($user->is_invited != 0) {
                $creditRecord->decrement('member_limit');
            }

            $title = 'New Connection Request';
            $message = "{$user->name} has successfully connected with you.";
            $type = 'new_connection'; 
            $additionalData = [
                'connected_user_id' => $user->id,
                'connected_at'      => now()->format('Y-m-d H:i:s'),
            ];

            $connectedUser->notify(new CoachMessageNotification($title, $message, $type, $additionalData));

            DB::commit();

            try {
                Mail::to($connectedUser->email)->send(new ProfessionalConnectedMail($user, $connectedUser));
            } catch (\Exception $mailException) {
                Log::error('Notification Email Failed: ' . $mailException->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Successfully connected to the professional',
                'data' => [
                    'connection_details' => ['connected_at' => now()->format('Y-m-d H:i:s'), 'status' => 'active'],
                    'professional_info' => [
                        'id'        => $connectedUser->id,
                        'name'      => $connectedUser->name,
                        'user_type' => $connectedUser->user_type,
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Connect to Profession Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Something went wrong.'], 500);
        }
    }

    public function cancelConnectedUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $authUser = auth()->user();
        $authUserId = $authUser->id;
        $authUserName = $authUser->name;
        $targetUserId = $request->input('user_id');

        $connection = DB::table('connect_user_proffesions')
            ->where(function ($query) use ($authUserId, $targetUserId) {
                $query->where('profession_id', $authUserId)
                    ->where('user_id', $targetUserId);
            })
            ->orWhere(function ($query) use ($authUserId, $targetUserId) {
                $query->where('user_id', $authUserId)
                    ->where('profession_id', $targetUserId);
            })
            ->first();

        $programConnection = DB::table('connect_to_professions')
            ->where(function ($query) use ($authUserId, $targetUserId) {
                $query->where('profession_id', $authUserId)
                    ->where('user_id', $targetUserId);
            })
            ->orWhere(function ($query) use ($authUserId, $targetUserId) {
                $query->where('user_id', $authUserId)
                    ->where('profession_id', $targetUserId);
            })
            ->first();

        if (!$connection && !$programConnection) {
            return response()->json([
                'success' => false,
                'message' => 'No active connection found between you and this user.',
            ], 404);
        }

        $notificationTargetId = $targetUserId;
        $connectionId = $connection ? $connection->id : ($programConnection ? $programConnection->id : null);

        DB::beginTransaction();

        try {
            if ($connection) {
                DB::table('connect_user_proffesions')
                    ->where('id', $connection->id)
                    ->delete();
            }

            DB::table('connect_to_professions')
                ->where(function ($query) use ($authUserId, $targetUserId) {
                    $query->where('profession_id', $authUserId)
                        ->where('user_id', $targetUserId);
                })
                ->orWhere(function ($query) use ($authUserId, $targetUserId) {
                    $query->where('user_id', $authUserId)
                        ->where('profession_id', $targetUserId);
                })
                ->delete();

            $professionIdForCredit = $connection ? $connection->profession_id : $authUserId;
            $creditRecord = ProjectionCredit::where('user_id', $professionIdForCredit)->first();

            if ($creditRecord) {
                $creditRecord->increment('member_limit');
            } else {
                ProjectionCredit::create([
                    'user_id' => $professionIdForCredit,
                    'member_limit' => 1 
                ]);
            }

            $targetUser = User::find($notificationTargetId);
            if ($targetUser) {
                try {
                    $title = 'Connection Cancelled';
                    $message = "{$authUserName} has cancelled the connection with you.";
                    $type = 'connection_cancelled';
                    $additionalData = [
                        'cancelled_by'  => $authUserId,
                        'connection_id' => $connectionId
                    ];

                    $targetUser->notify(new CoachMessageNotification($title, $message, $type, $additionalData));
                } catch (\Exception $notifyEx) {
                    Log::warning('Cancel connection notify error: ' . $notifyEx->getMessage());
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User connection has been successfully cancelled, credit refunded, and user notified.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Cancel Connection Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while cancelling the connection: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getConnectedUsersList()
    {
       $lists = ConnectUserProffesion::with('profession:id,name,profession_type', 'user:id,name')->get();
       return $lists;
    }

    private function isCapacityReached($professionalId)
    {
        $limit = ProjectionCredit::where('user_id', $professionalId)->value('member_limit') ?? 0;

        $currentConnections = DB::table('connect_user_proffesions')
                                ->where('profession_id', $professionalId)
                                ->count();

        return $currentConnections >= $limit;
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

