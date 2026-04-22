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
        return User::with(['profile', 'activityLogs', 'nutritionLogs', 'stressLogs', 'sleepLogs'])
            ->where('id', $userId)
            ->get()
            ->map(function($user) {
            return [
                'demographics' => [
                    'age' => $user->profile->age,
                    'gender' => $user->profile->sex,
                    'bmi' => $this->calculateBMI($user->profile->weight, $user->profile->height),
                ],
                'habits' => [
                    'avg_sleep' => $user->sleepLogs()->avg('sleep_hours'),
                    'avg_steps' => $user->activityLogs()->avg('daily_steps'),
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

    public function calculateBMI($weight, $height)
    {
        if ($height > 0) {
            return round($weight / (($height / 100) ** 2), 2);
        }
        return null;
    }

    
}