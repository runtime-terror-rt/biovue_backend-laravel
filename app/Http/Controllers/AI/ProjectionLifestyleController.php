<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AI\ProjectionLifestyle;

class ProjectionLifestyleController extends Controller
{
    /**
     * Save AI projection response
     */
    public function store(Request $request)
    {
        // Optional: validate user_id
        $request->validate([
            'user_id' => 'required|integer',
        ]);

        try {
            $data = $request->all();

            $projection = ProjectionLifestyle::create([
                'user_id' => $data['user_id'],
                'image' => $data['image'] ?? null,
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
                'message' => 'Projection saved successfully',
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
     * Show latest projection by user
     */
    public function showLatest($user_id)
    {
        try {
            $projection = ProjectionLifestyle::where('user_id', $user_id)
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
}