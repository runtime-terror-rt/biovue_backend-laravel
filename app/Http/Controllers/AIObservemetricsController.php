<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AIObservemetricsController extends Controller
{
    /**
     * Show latest AI Observemetrics for logged-in user
     */
    public function show()
    {
        $user = auth()->user(); // logged-in user

        // Latest logs
        $latestActivity = $user->activityLogs()->latest('log_date')->first();
        $latestSleep = $user->sleepLogs()->latest('log_date')->first();
        $latestNutrition = $user->nutritionLogs()->latest('log_date')->first();
        $latestStress = $user->stressLogs()->latest('log_date')->first();
        $latestHydration = $user->hydrationLogs()->latest('log_date')->first();

        // Sleep hours: check sleep_logs first, fallback to activityLogs
        $sleepHours = (float)($latestSleep->sleep_hours ?? ($latestActivity->sleep_hours ?? null));

        // Hydration normalize: prevent 800 oz / input mixup
        $waterGlasses = (float)($latestHydration->water_glasses ?? ($latestActivity->water_glasses ?? 0));
        $waterOz = (float)($latestHydration->water_oz ?? ($waterGlasses * 8));
        if ($waterGlasses > 30) {
            $waterOz = $waterGlasses;
            $waterGlasses = round($waterOz / 8, 1);
        }
        if ($waterOz >= 700 && $waterGlasses >= 80) {
            $waterOz = round($waterOz / 8, 1);
            $waterGlasses = round($waterOz / 8, 1);
        }

        // Stress: 1-10 scale
        $stressVal = (float)($latestStress->stress_level ?? 0);
        $stressText = 'Low';
        if ($stressVal > 6) {
            $stressText = 'High';
        } elseif ($stressVal >= 4) {
            $stressText = 'Moderate';
        }

        // Nutrition adherence calculation
        $nutritionAdherence = null;
        if ($latestNutrition) {
            $totalServings = ($latestNutrition->protein_servings ?? 0) + ($latestNutrition->vegetable_servings ?? 0);
            $nutritionAdherence = round(($totalServings / 10) * 100, 0);
        }

        // JSON data
        $data = [
            'weight' => [
                'value' => $latestActivity->weight ?? ($user->profile->weight ?? null),
                'unit' => $user->profile->unit === 'metric' ? 'kg' : 'lbs',
                'updated_at' => $latestActivity?->updated_at?->diffForHumans(),
            ],
            'sleep_average' => [
                'value' => $sleepHours > 0 ? $sleepHours : null,
                'unit' => 'Hrs',
                'updated_at' => ($latestSleep ?? $latestActivity)?->updated_at?->diffForHumans(),
            ],
            'activity_level' => [
                'value' => ($latestActivity->daily_steps ?? 0) >= 10000 ? 'High' : 'Moderate',
                'updated_at' => $latestActivity?->updated_at?->diffForHumans(),
            ],
            'nutrition_adherence' => [
                'value' => $nutritionAdherence,
                'unit' => '%',
                'updated_at' => $latestNutrition?->updated_at?->diffForHumans(),
            ],
            'stress_level' => [
                'value' => $stressText,
                'updated_at' => $latestStress?->updated_at?->diffForHumans(),
            ],
            'water_intake' => [
                'value' => $waterGlasses,
                'unit' => 'glasses/day',
                'updated_at' => ($latestHydration ?? $latestActivity)?->updated_at?->diffForHumans(),
            ],
        ];

        return response()->json([
            'success' => true,
            'user_id' => $user->id,
            'data' => $data
        ]);
    }

    public function index($id)
    {
        try {
            $user = \App\Models\User::with(['profile', 'targetGoals'])->find($id);

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }

            // Fetching latest records across actual user tracking tables
            $activity = $user->activityLogs()->latest('log_date')->first();
            $sleep = $user->sleepLogs()->latest('log_date')->first();
            $nutrition = $user->nutritionLogs()->latest('log_date')->first();
            $stress = $user->stressLogs()->latest('log_date')->first();
            $hydration = $user->hydrationLogs()->latest('log_date')->first();

            // =========================
            // Calculations
            // =========================

            $nutritionQuality = 0;
            $targetGoal = (float)($user->targetGoals?->target_calories ?? 200); 

            if ($nutrition) {
                $protein = (float)($nutrition->protein_value ?? 0);
                $carbs   = (float)($nutrition->carbs_value ?? 0);
                $calories = (float)($nutrition->calories_value ?? 0);
                
                if ($calories > 0 && $user->targetGoals?->target_calories > 0) {
                    $nutritionQuality = min(100, round(($calories / $user->targetGoals->target_calories) * 100));
                } else {
                    $total = $protein + $carbs;
                    if ($targetGoal > 0) {
                        $nutritionQuality = round(($total / $targetGoal) * 100);
                    }
                }
            }

            // Sleep: Check sleep_logs table first, then fallback to activity_logs
            $sleepHours = (float)($sleep->sleep_hours ?? ($activity->sleep_hours ?? 0));
            if ($sleepHours <= 0) {
                // Fallback to recent sleep log
                $recentSleep = $user->sleepLogs()->whereNotNull('sleep_hours')->where('sleep_hours', '>', 0)->latest('log_date')->first();
                $sleepHours = (float)($recentSleep->sleep_hours ?? 0);
            }

            $sleepFormatted = null;
            if ($sleepHours > 0) {
                $hours = floor($sleepHours);
                $minutes = round(($sleepHours - $hours) * 60);
                $sleepFormatted = $hours . 'h ' . $minutes . 'm';
            } else {
                $sleepFormatted = '0h 0m';
            }

            // Stress: 1-10 scale (1-3 Low, 4-6 Moderate, 7-10 High)
            $stressVal = (float)($stress->stress_level ?? 0);
            if ($stressVal <= 0) {
                $recentStress = $user->stressLogs()->whereNotNull('stress_level')->where('stress_level', '>', 0)->latest('log_date')->first();
                $stressVal = (float)($recentStress->stress_level ?? 0);
            }

            $stressLabel = 'Normal';
            if ($stressVal > 6) {
                $stressLabel = 'High';
            } elseif ($stressVal >= 4) {
                $stressLabel = 'Moderate';
            } elseif ($stressVal > 0) {
                $stressLabel = 'Low';
            }

            // Hydration: Check hydration_logs, sanitize input (Jackie 800 oz fix), format daily intake
            $rawGlasses = (float)($hydration->water_glasses ?? ($activity->water_glasses ?? 0));
            $rawOz = (float)($hydration->water_oz ?? 0);

            if ($rawGlasses <= 0 && $rawOz <= 0) {
                $recentHydration = $user->hydrationLogs()->where(function($q) {
                    $q->where('water_glasses', '>', 0)->orWhere('water_oz', '>', 0);
                })->latest('log_date')->first();

                if ($recentHydration) {
                    $rawGlasses = (float)($recentHydration->water_glasses ?? 0);
                    $rawOz = (float)($recentHydration->water_oz ?? 0);
                }
            }

            // Fix corrupted values (e.g. 100 oz entered as glasses, or multiplied to 800)
            if ($rawGlasses > 30) {
                $rawOz = $rawGlasses;
                $rawGlasses = round($rawOz / 8, 1);
            } elseif ($rawOz > 0 && $rawGlasses <= 0) {
                $rawGlasses = round($rawOz / 8, 1);
            } elseif ($rawGlasses > 0 && $rawOz <= 0) {
                $rawOz = round($rawGlasses * 8, 1);
            }

            if ($rawOz >= 700 && $rawGlasses >= 80) {
                $rawOz = round($rawOz / 8, 1);
                $rawGlasses = round($rawOz / 8, 1);
            }

            $hydrationDisplay = null;
            if ($rawOz > 0) {
                $hydrationDisplay = round($rawOz, 1) . ' oz';
            } elseif ($rawGlasses > 0) {
                $hydrationDisplay = round($rawGlasses * 8, 1) . ' oz';
            }

            // Weight: fallback to user profile weight if activity log weight is missing
            $latestWeight = $activity->weight ?? ($user->profile->weight ?? null);

            return response()->json([
                'success' => true,
                'data' => [
                    'weight'            => $latestWeight,
                    'nutrition_quality' => $nutritionQuality . '%',
                    'steps'             => (int)($activity->daily_steps ?? 0),
                    'sleep'             => $sleepFormatted,
                    'stress'            => $stressLabel,
                    'hydration'         => $hydrationDisplay,
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error("Index Error: " . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Something went wrong',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}