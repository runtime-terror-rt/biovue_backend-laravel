<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\AI\Insight;
use Carbon\Carbon;

class InsightController extends Controller
{
    /**
     * Fetch insights from external API and save to database.
     * Overwrites old insights if 7 days passed since last update.
     */
    public function fetchInsights(Request $request)
    {
        // Validate request
        $request->validate([
            'user_id' => 'required|integer|exists:users,id'
        ]);

        $userId = $request->user_id;

        // Check last insight date
        $lastInsight = Insight::where('user_id', $userId)->latest()->first();

        if ($lastInsight && Carbon::parse($lastInsight->created_at)->diffInDays(now()) < 7) {
            return response()->json([
                'success' => true,
                'message' => 'Insights already generated within 7 days',
                'data' => Insight::where('user_id', $userId)->get()
            ]);
        }

        // Determine SSL verification based on environment
        $verifySSL = env('APP_ENV') === 'production';

        try {
            // Call external API with SSL, timeout 120s, retry 3 times
            $response = Http::withOptions([
                'verify' => $verifySSL,
                'timeout' => 120,  // 120 seconds
            ])->retry(3, 2000)  // 3 retries, 2 sec interval
            ->post('https://ai.biovuedigitalwellness.com/api/v1/insights/current', [
                'user_id' => (string) $userId
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'External API call failed',
                    'status' => $response->status(),
                    'body' => $response->body()
                ], 500);
            }

            $insightsData = $response->json()['insights'] ?? [];

            // Overwrite old insights
            Insight::where('user_id', $userId)->delete();

            // Save new insights
            foreach ($insightsData as $item) {
                Insight::create([
                    'user_id' => $userId,
                    'priority' => $item['priority'] ?? null,
                    'category' => $item['category'] ?? null,
                    'insight' => $item['insight'] ?? null,
                    'why_this_matters' => $item['why_this_matters'] ?? null,
                    'expected_impact' => $item['expected_impact'] ?? null,
                    'trainers_note' => $item['trainers_note'] ?? null,
                    'action_steps' => $item['action_steps'] ?? []
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Insights fetched and stored successfully',
                'data' => Insight::where('user_id', $userId)->get()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exception occurred: ' . $e->getMessage()
            ], 500);
        }
    }




    /**
 * Show all insights for the currently logged-in user
 */
public function showUserInsights(Request $request)
{
    try {
        // Ensure user is logged in
        $user = $request->user(); 

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        // Fetch all insights for this user
        $insights = Insight::where('user_id', $user->id)->get();

        return response()->json([
            'success' => true,
            'data' => $insights
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Exception occurred: ' . $e->getMessage()
        ], 500);
    }
}
}