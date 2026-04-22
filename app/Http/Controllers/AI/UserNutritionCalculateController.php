<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\AI\UserNutritionCalculate;

class UserNutritionCalculateController extends Controller
{
    /**
     * Store nutrition calculation for a user
     */
    public function store(Request $request)
    {
        // Validate request
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'foods' => 'required|array|min:1'
        ]);

        try {
            $userId = $request->user_id;
            $foods = $request->foods;
            $log_date = $request->log_date ?? now()->toDateString();

            // Call AI Nutrition API
            $response = Http::withoutVerifying()
                ->timeout(120)
                ->post('https://ai.biovuedigitalwellness.com/api/v1/habits/nutritions/calculate', [
                    'foods' => $foods,
                    'user_id' => (string) $userId,
                ]);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'Nutrition API failed',
                    'error' => $response->json()
                ], 500);
            }

            $data = $response->json();

            // Extract nutrition values
            $caloriesValue = $data['nutrition']['calories']['value'] ?? 0;
            $caloriesUnit  = $data['nutrition']['calories']['unit'] ?? 'kcal';

            $proteinValue = $data['nutrition']['macros']['protein']['value'] ?? 0;
            $proteinUnit  = $data['nutrition']['macros']['protein']['unit'] ?? 'g';

            $carbsValue = $data['nutrition']['macros']['carbs']['value'] ?? 0;
            $carbsUnit  = $data['nutrition']['macros']['carbs']['unit'] ?? 'g';

            $fatValue = $data['nutrition']['macros']['fat']['value'] ?? 0;
            $fatUnit  = $data['nutrition']['macros']['fat']['unit'] ?? 'g';

            // Total nutrition
            $total = $caloriesValue + $proteinValue + $carbsValue + $fatValue;

            // Save into database
            $nutrition = UserNutritionCalculate::create([
                'user_id' => $userId,
                'foods' => $foods,
                'calories_value' => $caloriesValue,
                'calories_unit' => $caloriesUnit,
                'protein_value' => $proteinValue,
                'protein_unit' => $proteinUnit,
                'carbs_value' => $carbsValue,
                'carbs_unit' => $carbsUnit,
                'fat_value' => $fatValue,
                'fat_unit' => $fatUnit,
                'total' => $total,
                'log_date' => $log_date,
            ]);

            // Return AI-style response
            return response()->json([
                'log_date' => $log_date,
                'nutrition' => [
                    'calories' => [
                        'value' => $caloriesValue,
                        'unit' => $caloriesUnit
                    ],
                    'macros' => [
                        'protein' => [
                            'value' => $proteinValue,
                            'unit' => $proteinUnit
                        ],
                        'carbs' => [
                            'value' => $carbsValue,
                            'unit' => $carbsUnit
                        ],
                        'fat' => [
                            'value' => $fatValue,
                            'unit' => $fatUnit
                        ]
                    ],
                    'total' => $total,
                    'foods' => $foods
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Show authenticated user's latest nutrition calculation
     */
    public function show(Request $request)
    {
        $user = $request->user(); // Logged-in user

        // Fetch latest record for this user
        $nutrition = UserNutritionCalculate::where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$nutrition) {
            return response()->json([
                'message' => 'No nutrition data found for this user'
            ], 404);
        }

        // Return AI-style response
        return response()->json([
            'log_date' => $nutrition->log_date,
            'nutrition' => [
                'calories' => [
                    'value' => $nutrition->calories_value,
                    'unit' => $nutrition->calories_unit
                ],
                'macros' => [
                    'protein' => [
                        'value' => $nutrition->protein_value,
                        'unit' => $nutrition->protein_unit
                    ],
                    'carbs' => [
                        'value' => $nutrition->carbs_value,
                        'unit' => $nutrition->carbs_unit
                    ],
                    'fat' => [
                        'value' => $nutrition->fat_value,
                        'unit' => $nutrition->fat_unit
                    ]
                ],
                'total' => $nutrition->total,
                'foods' => $nutrition->foods
            ]
        ], 200);
    }
}