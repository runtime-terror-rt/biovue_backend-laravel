<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\AI\MealPlan;
use App\Models\AI\MealItem;

class MealPlanController extends Controller
{
    /**
     * Generate meal plan using external API & save in DB
     */
    public function generateMealPlan(Request $request)
    {
        $request->validate([
            'target_calorie' => 'required|integer',
            'target_protein' => 'required|integer',
            'target_carbs'   => 'required|integer',
            'target_fat'     => 'required|integer',
        ]);

        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Prepare API body
            $body = [
                'user_id'        => (string) $user->id,
                'target_calorie' => $request->target_calorie,
                'target_protein' => $request->target_protein,
                'target_carbs'   => $request->target_carbs,
                'target_fat'     => $request->target_fat,
            ];

            // Call external API
            $response = Http::withOptions([
                'verify' => false,  // local dev: ignore SSL issues
                'timeout' => 120,
            ])->retry(3, 2000)
              ->post('https://biovue-ai.onrender.com/api/v1/nutritions/meal-generate', $body);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'External API call failed',
                    'status'  => $response->status(),
                    'body'    => $response->body()
                ], 500);
            }

            // API response
            $data  = $response->json();
            $meals = $data['meals'] ?? [];

            // Delete old meal plan for this user
            MealPlan::where('user_id', $user->id)->delete();

            // Save new meal plan
            $mealPlan = MealPlan::create([
                'user_id'        => $user->id,
                'target_calorie' => $request->target_calorie,
                'target_protein' => $request->target_protein,
                'target_carbs'   => $request->target_carbs,
                'target_fat'     => $request->target_fat,
            ]);

            // Save meal items
            foreach ($meals as $meal) {
                foreach ($meal['items'] as $item) {
                    MealItem::create([
                        'meal_plan_id' => $mealPlan->id,
                        'meal_type'    => $meal['type'] ?? null,
                        'food'         => $item['food'] ?? null,
                        'quantity'     => $item['quantity'] ?? 0,
                        'unit'         => $item['unit'] ?? null,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Meal plan generated & saved successfully',
                'data'    => [
                    'meal_plan' => $mealPlan,
                    'items'     => $mealPlan->items,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show current user meal plan
     */
    public function showUserMealPlan(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $mealPlan = MealPlan::where('user_id', $user->id)->first();
            $items    = $mealPlan ? $mealPlan->items : [];

            return response()->json([
                'success' => true,
                'meal_plan' => $mealPlan,
                'items'     => $items,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ], 500);
        }
    }
}