<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Models\AI\ProjectionFutureGoal;
use App\Models\AI\UserNutritionCalculate;
use App\Models\StressLog;
use App\Models\UserProfile;

class ProjectionFutureGoalController extends Controller
{
    /**
     * Save AI future goal projection response
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
        ]);

        try {

            $data = $request->all();

            $projection = ProjectionFutureGoal::create([

                'user_id' => $data['user_id'],

                'image' => $data['image'] ?? null,
                'duration' => $data['duration'] ?? '1 year',
                'resolution' => $data['resolution'] ?? '2K',
                'tier' => $data['tier'] ?? 'ultra',

                'use_default_goal' => $data['use_default_goal'] ?? true,
                'goal' => $data['goal'] ?? null,
                'goal_description' => $data['goal_description'] ?? null,

                'projection_id' => $data['projection_id'] ?? null,
                'projection_url' => $data['projection_url'] ?? null,
                'route' => $data['route'] ?? null,
                'timeframe' => $data['timeframe'] ?? null,

                'est_bmi' => $data['est_bmi'] ?? null,
                'est_weight' => $data['est_weight'] ?? null,

                'expected_changes' => isset($data['expected_changes'])
                    ? json_encode($data['expected_changes'])
                    : null,

                'confidence_score' => $data['confidence_score'] ?? null,
            ]);

            return response()->json([
                'message' => 'Future goal projection saved successfully',
                'data' => $projection
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Show latest future goal projection
     */
    public function showLatest($user_id)
    {
        try {

            $projection = ProjectionFutureGoal::where('user_id', $user_id)
                ->latest()
                ->first();

            if (!$projection) {
                return response()->json([
                    'message' => 'No projection found for this user'
                ], 404);
            }

            return response()->json([
                'message' => 'Projection retrieved successfully',
                'data' => $projection
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getUserProjectionData(Request $request, $userId)
    {
        $startDate = $request->query('start_date', now()->subDays(90)->format('Y-m-d'));
        $endDate = $request->query('end_date', now()->format('Y-m-d'));

        $activities = \App\Models\ActivityLog::where('user_id', $userId)
            ->whereBetween('log_date', [$startDate, $endDate])
            ->get()
            ->keyBy('log_date');

        $nutrition = UserNutritionCalculate::where('user_id', $userId)
            ->whereBetween('log_date', [$startDate, $endDate])
            ->get()
            ->keyBy('log_date');

        $stress = \App\Models\StressLog::where('user_id', $userId)
            ->whereBetween('log_date', [$startDate, $endDate])
            ->get()
            ->keyBy('log_date');

        $hydration = \App\Models\HydrationLog::where('user_id', $userId)
            ->whereBetween('log_date', [$startDate, $endDate])
            ->get()
            ->keyBy('log_date');

        $profile = \App\Models\UserProfile::where('user_id', $userId)->first();
        if (!$profile) return response()->json(['message' => 'Profile not found'], 404);

        $processedData = [];
        $period = \Carbon\CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $date) {
            $formattedDate = $date->format('Y-m-d');
            
            $processedData[] = [
                'date' => $formattedDate,
                'weight' => $activities[$formattedDate]->weight ?? $profile->weight,
                'height' => $profile->height,
                'body_fat' => $profile[$formattedDate]->body_fat ?? null,
                'steps' => $activities[$formattedDate]->daily_steps ?? 0,
                'sleep_hours' => $activities[$formattedDate]->sleep_hours ?? 0,
                'calories' => $nutrition[$formattedDate]->calories_value ?? 0,
                'stress_level' => $stress[$formattedDate]->stress_level ?? 5,
                'hydration_level' => $hydration[$formattedDate]->hydration_level ?? 0,
                'is_athletic' => (bool)($profile->is_athletic),
            ];
        }

        return response()->json([
            'success' => true,
            'user_id' => $userId,
            'range' => ['from' => $startDate, 'to' => $endDate],
            'dataset_count' => count($processedData),
            'data' => $processedData
        ]);
    }
}