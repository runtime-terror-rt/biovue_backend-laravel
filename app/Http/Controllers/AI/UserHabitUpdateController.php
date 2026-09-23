<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Models\AI\UserHabitUpdate;
use App\Models\AI\UserNutritionCalculate;
use App\Models\StressLog;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Http;

class UserHabitUpdateController extends Controller
{
    /**
     * Update user's habit analysis
     */
    public function update(Request $request)
    {
        // Validate user_id
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $userId = $request->user_id;

        try {
            // 1️⃣ Call the BioVue AI API
            // Timeout 120 sec + SSL bypass for dev
            $response = Http::timeout(120)
                            ->withoutVerifying()
                            ->get('https://ai.biovuedigitalwellness.com/api/v1/habits/update/', [
                                'user_id' => $userId
                            ]);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'Failed to fetch AI habits data',
                    'error' => $response->body()
                ], 500);
            }

            $data = $response->json();

            // 2️⃣ Save / Update into database
            $habit = UserHabitUpdate::updateOrCreate(
                ['user_id' => $userId],
                [
                    'focus_on_trends' => $data['focus_on_trends'] ?? null,

                    'sleep_status' => $data['habits']['sleep']['status'] ?? null,
                    'sleep_why_this_matters' => $data['habits']['sleep']['why_this_matters'] ?? null,
                    'sleep_biovue_insights' => $data['habits']['sleep']['biovue_insights'] ?? null,

                    'nutrition_status' => $data['habits']['nutrition']['status'] ?? null,
                    'nutrition_why_this_matters' => $data['habits']['nutrition']['why_this_matters'] ?? null,
                    'nutrition_biovue_insights' => $data['habits']['nutrition']['biovue_insights'] ?? null,

                    'activity_status' => $data['habits']['activity']['status'] ?? null,
                    'activity_why_this_matters' => $data['habits']['activity']['why_this_matters'] ?? null,
                    'activity_biovue_insights' => $data['habits']['activity']['biovue_insights'] ?? null,

                    'stress_status' => $data['habits']['stress']['status'] ?? null,
                    'stress_why_this_matters' => $data['habits']['stress']['why_this_matters'] ?? null,
                    'stress_biovue_insights' => $data['habits']['stress']['biovue_insights'] ?? null,

                    'hydration_status' => $data['habits']['hydration']['status'] ?? null,
                    'hydration_why_this_matters' => $data['habits']['hydration']['why_this_matters'] ?? null,
                    'hydration_biovue_insights' => $data['habits']['hydration']['biovue_insights'] ?? null,
                ]
            );

            return response()->json([
                'message' => 'User habits updated successfully',
                'data' => $habit
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Connection / Timeout error
            return response()->json([
                'message' => 'Connection error while fetching AI data',
                'error' => $e->getMessage()
            ], 500);

        } catch (\Exception $e) {
            // Other exceptions
            return response()->json([
                'message' => 'Something went wrong while fetching AI data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($userId)
    {
        $habit = UserHabitUpdate::where('user_id', $userId)->first();

        if (!$habit) {
            return response()->json([
                'message' => 'Habit data not found for this user'
            ], 404);
        }

        return response()->json([
            'focus_on_trends' => $habit->focus_on_trends,
            'habits' => [
                'sleep' => [
                    'status' => $habit->sleep_status,
                    'why_this_matters' => $habit->sleep_why_this_matters,
                    'biovue_insights' => $habit->sleep_biovue_insights,
                ],
                'nutrition' => [
                    'status' => $habit->nutrition_status,
                    'why_this_matters' => $habit->nutrition_why_this_matters,
                    'biovue_insights' => $habit->nutrition_biovue_insights,
                ],
                'activity' => [
                    'status' => $habit->activity_status,
                    'why_this_matters' => $habit->activity_why_this_matters,
                    'biovue_insights' => $habit->activity_biovue_insights,
                ],
                'stress' => [
                    'status' => $habit->stress_status,
                    'why_this_matters' => $habit->stress_why_this_matters,
                    'biovue_insights' => $habit->stress_biovue_insights,
                ],
                'hydration' => [
                    'status' => $habit->hydration_status,
                    'why_this_matters' => $habit->hydration_why_this_matters,
                    'biovue_insights' => $habit->hydration_biovue_insights,
                ],
            ]
        ]);
    }

    public function getAiInputData($userId)
    {
        return User::with(['profile', 'activityLogs', 'nutritionLogs', 'stressLogs', 'sleepLogs', 'hydrationLogs'])
            ->where('id', $userId)
            ->get()
            ->map(function($user) {
            $unit = $user->profile->unit ?? 'imperial';
            $avgGlasses = round($user->hydrationLogs()->avg('water_glasses') ?? 0, 1);
            $avgOz = round($user->hydrationLogs()->avg('water_oz') ?? ($avgGlasses * 8), 1);
            if ($avgGlasses > 30) {
                $avgOz = $avgGlasses;
                $avgGlasses = round($avgOz / 8, 1);
            }

            return [
                'demographics' => [
                    'age' => $user->profile->age,
                    'gender' => $user->profile->sex,
                    'unit' => $unit,
                    'bmi' => $this->calculateBMI($user->profile->weight, $user->profile->height, $unit),
                ],
                'habits' => [
                    'avg_sleep' => $user->sleepLogs()->avg('sleep_hours'),
                    'avg_steps' => $user->activityLogs()->avg('daily_steps'),
                    'avg_hydration_oz' => round($avgOz, 1),
                    'avg_hydration_glasses' => round($avgGlasses, 1),
                    'diet_quality' => $user->profile->overall_diet_quality,
                ],
                'risk_factors' => [
                    'smoking' => $user->profile->smoking_status,
                    'alcohol' => $user->profile->alcohol_consumption,
                    'avg_stress' => $user->stressLogs()->avg('stress_level'),
                ]
            ];
        });
    }

    public function calculateBMI($weight, $height, $unit = 'imperial')
    {
        $weight = (float)$weight;
        $height = (float)$height;

        if ($weight <= 0 || $height <= 0) {
            return null;
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
            return null;
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

    
}