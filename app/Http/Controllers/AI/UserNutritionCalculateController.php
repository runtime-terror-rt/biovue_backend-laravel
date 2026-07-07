<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\AI\UserNutritionCalculate;

class UserNutritionCalculateController extends Controller
{
    /**
     * Store nutrition calculation for a user.
     * Always inserts a new row, then returns the AGGREGATED total
     * for that user + log_date (i.e. all rows of that day combined).
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'foods' => 'required|array|min:1'
        ]);

        try {
            $userId   = $request->user_id;
            $newFoods = $request->foods;
            $logDate  = $request->log_date ?? now()->toDateString();

            // Call AI Nutrition API (only for the newly submitted foods)
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

            $caloriesValue = (float) ($data['nutrition']['calories']['value'] ?? 0);
            $caloriesUnit  = $data['nutrition']['calories']['unit'] ?? 'kcal';

            $proteinValue = (float) ($data['nutrition']['macros']['protein']['value'] ?? 0);
            $proteinUnit  = $data['nutrition']['macros']['protein']['unit'] ?? 'g';

            $carbsValue = (float) ($data['nutrition']['macros']['carbs']['value'] ?? 0);
            $carbsUnit  = $data['nutrition']['macros']['carbs']['unit'] ?? 'g';

            $fatValue = (float) ($data['nutrition']['macros']['fat']['value'] ?? 0);
            $fatUnit  = $data['nutrition']['macros']['fat']['unit'] ?? 'g';

            $total = $caloriesValue + $proteinValue + $carbsValue + $fatValue;

            // Always insert — this call's foods as its own row
            UserNutritionCalculate::create([
                'user_id'        => $userId,
                'foods'          => $newFoods,
                'calories_value' => $caloriesValue,
                'calories_unit'  => $caloriesUnit,
                'protein_value'  => $proteinValue,
                'protein_unit'   => $proteinUnit,
                'carbs_value'    => $carbsValue,
                'carbs_unit'     => $carbsUnit,
                'fat_value'      => $fatValue,
                'fat_unit'       => $fatUnit,
                'total'          => $total,
                'log_date'       => $logDate,
            ]);

            // Return the combined total of ALL rows for this user + date
            $aggregated = $this->aggregateForDate($userId, $logDate);

            return response()->json($aggregated, 200);

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

        $aggregated = $this->aggregateForDate($user->id, $logDate);

        if ($aggregated === null) {
            return response()->json([
                'message' => 'No nutrition data found for this user on ' . $logDate
            ], 404);
        }

        return response()->json($aggregated, 200);
    }

    /**
     * Combine (sum) all rows for a given user + log_date into one response.
     */
    private function aggregateForDate($userId, $logDate)
    {
        $nutritions = UserNutritionCalculate::where('user_id', $userId)
            ->whereDate('log_date', $logDate)
            ->get();

        if ($nutritions->isEmpty()) {
            return null;
        }

        $caloriesValue = $nutritions->sum('calories_value');
        $proteinValue  = $nutritions->sum('protein_value');
        $carbsValue    = $nutritions->sum('carbs_value');
        $fatValue      = $nutritions->sum('fat_value');
        $total         = $nutritions->sum('total');

        $allFoods = $nutritions->flatMap(function ($item) {
            return is_array($item->foods)
                ? $item->foods
                : (json_decode($item->foods, true) ?? []);
        })->values();

        return [
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
        ];
    }
}