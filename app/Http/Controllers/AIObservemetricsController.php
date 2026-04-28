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
        $latestNutrition = $user->nutritionLogs()->latest('log_date')->first();
        $latestStress = $user->stressLogs()->latest('log_date')->first();

        // Nutrition adherence calculation
        $nutritionAdherence = null;
        if ($latestNutrition) {
            $totalServings = $latestNutrition->protein_servings + $latestNutrition->vegetable_servings;
            $nutritionAdherence = round(($totalServings / 10) * 100, 0);
        }

        // JSON data
        $data = [
            'weight' => [
                'value' => $latestActivity->weight ?? null,
                'unit' => 'lbs',
                'updated_at' => $latestActivity?->updated_at?->diffForHumans(),
            ],
            'sleep_average' => [
                'value' => $latestActivity->sleep_hours ?? null,
                'unit' => 'Hrs',
                'updated_at' => $latestActivity?->updated_at?->diffForHumans(),
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
                'value' => $latestStress->stress_level ?? 'Low',
                'updated_at' => $latestStress?->updated_at?->diffForHumans(),
            ],
            'water_intake' => [
                'value' => $latestActivity->water_glasses ?? 0,
                'unit' => 'L/day',
                'updated_at' => $latestActivity?->updated_at?->diffForHumans(),
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
            $user = \App\Models\User::find($id);

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }

            // Fetching latest records
            $activity = $user->activityLogs()->latest('log_date')->first();
            $nutrition = $user->nutritionLogs()->latest('log_date')->first();
            $stress = $user->stressLogs()->latest('log_date')->first();
            $hydration = $user->hydrationLogs()->latest('log_date')->first();

            // =========================
            // Calculations
            // =========================

            $nutritionQuality = 0;
            $targetGoal = 200; 

            if ($nutrition) {
                $protein = (float)($nutrition->protein_value ?? 0);
                $carbs   = (float)($nutrition->carbs_value ?? 0);
                
                $total = $protein + $carbs;
                
                if ($targetGoal > 0) {
                    $nutritionQuality = round(($total / $targetGoal) * 100);
                }
            }

            $sleepFormatted = null;
            if ($activity && $activity->sleep_hours) {
                $hours = floor($activity->sleep_hours);
                $minutes = round(($activity->sleep_hours - $hours) * 60);
                $sleepFormatted = $hours . 'h ' . $minutes . 'm';
            }

            $stressLabel = $stress->stress_level ?? null;
            if ($stressLabel) {
                $stressLabel = ($stressLabel >= 4) ? 'High' : (($stressLabel >= 2) ? 'Moderate' : 'Low');
            }

            $hydrationOz = ($hydration && isset($hydration->water_glasses)) ? ($hydration->water_glasses * 8) . ' oz' : null;

            return response()->json([
                'success' => true,
                'data' => [
                    'weight'            => $activity->weight ?? null,
                    'nutrition_quality' => $nutritionQuality . '%',
                    'steps'             => $activity->daily_steps ?? 0,
                    'sleep'             => $sleepFormatted,
                    'stress'            => $stressLabel,
                    'hydration'         => $hydrationOz,
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