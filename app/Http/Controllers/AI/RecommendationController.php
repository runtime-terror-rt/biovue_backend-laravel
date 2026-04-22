<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RecommendationController extends Controller
{
    /**
     * Get professional suggestions for a specific user
     */
    public function index($user_id)
    {
        try {

            $response = Http::timeout(120)
                ->withOptions(['verify' => false])
                ->get("https://ai.biovuedigitalwellness.com/api/v1/recommend/professionals/{$user_id}");

            if (!$response->successful()) {
                return response()->json([
                    'message' => 'Professional recommendation API failed',
                    'error' => $response->body()
                ], 500);
            }

            $data = $response->json();

            return response()->json([
                'message' => 'Professional suggestions retrieved successfully',
                'data' => $data
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    /**
 * Get user suggestions for a trainer
 */
public function trainerUsers($trainer_id)
{
    try {

        $response = Http::timeout(120)
            ->withoutVerifying() // SSL verify ignore
            ->get("https://ai.biovuedigitalwellness.com/api/v1/recommend/users/trainer/{$trainer_id}");

        if ($response->failed()) {
            return response()->json([
                'message' => 'Trainer recommendation API failed',
                'error' => $response->body()
            ], 500);
        }

        return response()->json([
            'message' => 'Trainer user suggestions retrieved successfully',
            'data' => $response->json()
        ], 200);

    } catch (\Exception $e) {

        return response()->json([
            'message' => 'Something went wrong',
            'error' => $e->getMessage()
        ], 500);
    }
}



     /**
     * Get recommended users for a nutritionist
     */
    public function nutritionistUsers($nutritionist_id)
    {
        try {

            $response = Http::timeout(120)
                ->withOptions(['verify' => false])
                ->get("https://ai.biovuedigitalwellness.com/api/v1/recommend/users/nutritionist/{$nutritionist_id}");

            if (!$response->successful()) {
                return response()->json([
                    'message' => 'Nutritionist recommendation API failed',
                    'error' => $response->body()
                ], 500);
            }

            return response()->json([
                'message' => 'Recommended users fetched successfully',
                'data' => $response->json()
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);

        }
    }




public function supplierUsers($supplier_id)
{
    try {
        // Call external API
        $response = Http::timeout(120)
            ->withoutVerifying() // ignore SSL issues
            ->get("https://ai.biovuedigitalwellness.com/api/v1/recommend/users/supplier/{$supplier_id}");

        // Check if API call failed
        if (!$response->successful()) {
            return response()->json([
                'message' => 'Supplier recommendation API failed',
                'error' => $response->body()
            ], 500);
        }

        // Return formatted response
        return response()->json([
            'message' => 'Recommended users fetched successfully',
            'supplier_id' => $supplier_id,
            'suggestions' => $response->json()['suggestions'] ?? []
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Something went wrong',
            'error' => $e->getMessage()
        ], 500);
    }
}

}