<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\AI\FutureInsight;
use Carbon\Carbon;

class FutureInsightController extends Controller
{
    /**
     * Fetch future insights from Biovue API and store in DB
     * Overwrites only if 7 days passed since last insight
     */
    public function fetchFutureInsights(Request $request)
    {
        $request->validate([
            'user_id'   => 'required|integer|exists:users,id',
            'timeframe' => 'required|string'
        ]);

        $userId = $request->user_id;
        $timeframe = $request->timeframe;

        try {
            // Check last insight
            $lastInsight = FutureInsight::where('user_id', $userId)->latest()->first();
            if ($lastInsight && Carbon::parse($lastInsight->created_at)->diffInDays(now()) < 7) {
                return response()->json([
                    'success' => true,
                    'message' => 'Future insights already generated within 7 days',
                    'data'    => FutureInsight::where('user_id', $userId)->get()
                ]);
            }

            // HTTP POST to external API
            $response = Http::timeout(120)
                ->retry(3, 2000)
                ->post('https://ai.biovuedigitalwellness.com/api/v1/insights/future', [
                    'user_id'   => (string)$userId,
                    'timeframe' => $timeframe
                ]);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'External API call failed',
                    'status'  => $response->status(),
                    'body'    => $response->body()
                ], 500);
            }

            $insightsData = $response->json()['insights'] ?? [];

            // Overwrite old insights
            FutureInsight::where('user_id', $userId)->delete();

            // Save new insights
            foreach ($insightsData as $item) {
                FutureInsight::create([
                    'user_id'          => $userId,
                    'priority'         => $item['priority'] ?? null,
                    'category'         => $item['category'] ?? null,
                    'insight'          => $item['insight'] ?? null,
                    'timeline'         => $item['timeline'] ?? null,
                    'expected_changes' => $item['expected_changes'] ?? []
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Future insights fetched and stored successfully',
                'data'    => FutureInsight::where('user_id', $userId)->get()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exception occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show all future insights for logged-in user
     */
    public function showUserFutureInsights(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $insights = FutureInsight::where('user_id', $user->id)->get();

            return response()->json([
                'success' => true,
                'data'    => $insights
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exception occurred: ' . $e->getMessage()
            ], 500);
        }
    }
}