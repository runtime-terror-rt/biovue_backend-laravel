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
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'foods' => 'required|array|min:1'
        ]);

        try {
            $userId = $request->user_id;
            $newFoods = $request->foods;
            $logDate = $request->log_date ?? now()->toDateString();

            $response = Http::withoutVerifying()
                ->timeout(120)
                ->post('https://ai.biovuedigitalwellness.com/api/v1/habits/nutritions/calculate', [
                    'foods' => $newFoods,
                    'user_id' => (string) $userId,
                ]);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'Nutrition API failed',
                    'error' => $response->json()
                ], 500);
            }

            $data = $response->json();

            $caloriesValue = $data['nutrition']['calories']['value'] ?? 0;
            $caloriesUnit  = $data['nutrition']['calories']['unit'] ?? 'kcal';

            $proteinValue = $data['nutrition']['macros']['protein']['value'] ?? 0;
            $proteinUnit  = $data['nutrition']['macros']['protein']['unit'] ?? 'g';

            $carbsValue = $data['nutrition']['macros']['carbs']['value'] ?? 0;
            $carbsUnit  = $data['nutrition']['macros']['carbs']['unit'] ?? 'g';

            $fatValue = $data['nutrition']['macros']['fat']['value'] ?? 0;
            $fatUnit  = $data['nutrition']['macros']['fat']['unit'] ?? 'g';

            $existing = UserNutritionCalculate::where('user_id', $userId)
                ->whereDate('log_date', $logDate)
                ->first();

            if ($existing) {
                $existingFoods = is_array($existing->foods) ? $existing->foods : json_decode($existing->foods, true);
                $mergedFoods = array_merge($existingFoods, $newFoods);

                $caloriesValue = $existing->calories_value + $caloriesValue;
                $proteinValue  = $existing->protein_value + $proteinValue;
                $carbsValue    = $existing->carbs_value + $carbsValue;
                $fatValue      = $existing->fat_value + $fatValue;
                $total         = $caloriesValue + $proteinValue + $carbsValue + $fatValue;

                $existing->update([
                    'foods' => $mergedFoods,
                    'calories_value' => $caloriesValue,
                    'protein_value' => $proteinValue,
                    'carbs_value' => $carbsValue,
                    'fat_value' => $fatValue,
                    'total' => $total,
                ]);

                $nutrition = $existing;
                $foods = $mergedFoods;
            } else {
                $total = $caloriesValue + $proteinValue + $carbsValue + $fatValue;

                $nutrition = UserNutritionCalculate::create([
                    'user_id' => $userId,
                    'foods' => $newFoods,
                    'calories_value' => $caloriesValue,
                    'calories_unit' => $caloriesUnit,
                    'protein_value' => $proteinValue,
                    'protein_unit' => $proteinUnit,
                    'carbs_value' => $carbsValue,
                    'carbs_unit' => $carbsUnit,
                    'fat_value' => $fatValue,
                    'fat_unit' => $fatUnit,
                    'total' => $total,
                    'log_date' => $logDate,
                ]);

                $foods = $newFoods;
            }

            return response()->json([
                'log_date' => $logDate,
                'nutrition' => [
                    'calories' => ['value' => $caloriesValue, 'unit' => $caloriesUnit],
                    'macros' => [
                        'protein' => ['value' => $proteinValue, 'unit' => $proteinUnit],
                        'carbs' => ['value' => $carbsValue, 'unit' => $carbsUnit],
                        'fat' => ['value' => $fatValue, 'unit' => $fatUnit],
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
     * Show authenticated user's nutrition calculation for a given date (default: today)
     */
    public function show(Request $request)
    {
        $user = $request->user(); // Logged-in user

        $logDate = $request->log_date ?? now()->toDateString();

        $nutritions = UserNutritionCalculate::where('user_id', $user->id)
            ->whereDate('log_date', $logDate)
            ->get();

        if ($nutritions->isEmpty()) {
            return response()->json([
                'message' => 'No nutrition data found for this user on ' . $logDate
            ], 404);
        }

        $caloriesValue = $nutritions->sum('calories_value');
        $proteinValue  = $nutritions->sum('protein_value');
        $carbsValue    = $nutritions->sum('carbs_value');
        $fatValue      = $nutritions->sum('fat_value');
        $total         = $nutritions->sum('total');

        $allFoods = $nutritions->flatMap(function ($item) {
            return is_array($item->foods) ? $item->foods : json_decode($item->foods, true);
        })->values();

        return response()->json([
            'log_date' => $logDate,
            'nutrition' => [
                'calories' => [
                    'value' => $caloriesValue,
                    'unit' => $nutritions->first()->calories_unit
                ],
                'macros' => [
                    'protein' => [
                        'value' => $proteinValue,
                        'unit' => $nutritions->first()->protein_unit
                    ],
                    'carbs' => [
                        'value' => $carbsValue,
                        'unit' => $nutritions->first()->carbs_unit
                    ],
                    'fat' => [
                        'value' => $fatValue,
                        'unit' => $nutritions->first()->fat_unit
                    ]
                ],
                'total' => $total,
                'foods' => $allFoods
            ]
        ], 200);
    }
}