<?php

namespace App\Http\Controllers\Projection;

use App\Http\Controllers\Controller;
use App\Models\ProjectionData;
use App\Models\ProjectionCredit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectionController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $daysSinceJoined = now()->diffInDays($user->created_at);
        $hasPlan = $user->adjustProgram()->exists();

        if ($daysSinceJoined > 7 && !$hasPlan) {
            return response()->json([
                'success' => false,
                'message' => 'Your access period has expired. Please subscribe to a plan to view your projections.',
                'locked' => true
            ], 403);
        }

        $projections = ProjectionData::where('user_id', $user->id)
            ->latest()
            ->get();

        if ($projections->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No projections found.',
                'data' => []
            ]);
        }

        $aiDomain = "https://ai.biovuedigitalwellness.com";

        $formattedData = $projections->map(function($projection) use ($aiDomain) {
            $pData = $projection->projections_data;

            return [
                'id' => $projection->id,
                'title'   => 'Projection Results',
                'timeframe' => $projection->timeframe,
                'input_image' => asset('storage/' . $projection->input_image), 

                'projections' => [
                    'current_lifestyle' => [
                        'label'            => "Current lifestyle trajectory for " . $projection->timeframe,
                        'image'            => $aiDomain . ($pData['current_lifestyle']['projection_url'] ?? ''),
                        'timeframe'        => $projection->timeframe,
                        'est_bmi'          => $pData['current_lifestyle']['est_bmi'] ?? 'N/A',
                        'est_weight'       => $pData['current_lifestyle']['est_weight'] ?? 'N/A',
                        'expected_changes' => $pData['current_lifestyle']['expected_changes'] ?? [],
                    ],
                    'future_goal' => [
                        'label'            => "Target goal achievement in " . $projection->timeframe,
                        'image'            => $aiDomain . ($pData['future_goal']['projection_url'] ?? ''),
                        'timeframe'        => $projection->timeframe,
                        'est_bmi'          => $pData['future_goal']['est_bmi'] ?? 'N/A',
                        'est_weight'       => $pData['future_goal']['est_weight'] ?? 'N/A',
                        'expected_changes' => $pData['future_goal']['expected_changes'] ?? [],
                    ]
                ],
                'created_at' => $projection->created_at->format('Y-m-d')
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedData
        ]);
    }


    public function generateProjection(Request $request)
    {
        // 1. Validating incoming request from your frontend/mobile
        $request->validate([
            'image'      => 'required|image|mimes:jpeg,png,jpg,webp',
            'timeframe'  => 'required|string',
            'resolution' => 'required|string',
        ]);

        $user = auth()->user();

        // 2. Credit limit check
        $credits = ProjectionCredit::where('user_id', $user->id)->first();
        if (!$credits || $credits->projection_limit <= 0) {
            return response()->json(['success' => false, 'message' => 'Insufficient credits'], 403);
        }

        try {
            // 3. Store the input image locally (optional but good for history)
            $imagePath = $request->file('image')->store('projections/inputs', 'public');

            // 4. Hit the AI API (Matching the FastAPI structure you provided)
            $response = \Illuminate\Support\Facades\Http::timeout(300)
                ->asMultipart()
                ->attach(
                    'image', 
                    file_get_contents($request->file('image')->getRealPath()), 
                    $request->file('image')->getClientOriginalName()
                )
                // FastAPI Form parameters
                ->attach('user_id', (string) $user->id) 
                ->attach('duration', (string) $request->timeframe) 
                ->attach('resolution', (string) $request->resolution)
                ->attach('use_default_goal', 'true') 
                ->post('https://ai.biovuedigitalwellness.com/api/v1/projection/combined/');

            // 5. Handling Response
            if ($response->successful()) {
                $aiData = $response->json();

                \Illuminate\Support\Facades\DB::beginTransaction();

                // Store in your local Database
                $projection = \App\Models\ProjectionData::create([
                    'projection_id'    => $aiData['projection_id'],
                    'user_id'          => $user->id,
                    'input_image'      => $imagePath,
                    'timeframe'        => $aiData['timeframe'] ?? $request->timeframe,
                    'resolution'       => $aiData['resolution'] ?? $request->resolution,
                    'projections_data' => $aiData['projections'], 
                    'summary_data'     => $aiData['summary'] ?? null, 
                ]);

                // Decrement User Credit
                $credits->decrement('projection_limit');
                
                \Illuminate\Support\Facades\DB::commit();

                return response()->json(['success' => true, 'data' => $projection], 201);
            }

            // 6. Error Handling (Handles 404 No Profile, 500 Server Error etc.)
            return response()->json([
                'success' => false, 
                'message' => 'AI API Error', 
                'error_detail' => $response->json() ?: $response->body()
            ], $response->status());

        } catch (\Exception $e) {
            if (\Illuminate\Support\Facades\DB::transactionLevel() > 0) {
                \Illuminate\Support\Facades\DB::rollBack();
            }
            \Illuminate\Support\Facades\Log::error("Projection Error: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // public function generateProjection(Request $request)
    // {
    //     $request->validate([
    //         'image'      => 'required|image|mimes:jpeg,png,jpg,webp',
    //         'timeframe'  => 'required|string',
    //         'resolution' => 'required|string',
    //     ]);

    //     $user = auth()->user();

    //     $credits = \App\Models\ProjectionCredit::where('user_id', $user->id)->first();
        
    //     if (!$credits || $credits->projection_limit <= 0) {
    //         return response()->json(['success' => false, 'message' => 'Insufficient credits'], 403);
    //     }

    //     try {
    //         $imagePath = $request->file('image')->store('projections/inputs', 'public');

    //         $response = Http::timeout(300)->attach(
    //             'image', 
    //             fopen($request->file('image')->getRealPath(), 'r'), 
    //             $request->file('image')->getClientOriginalName()
    //         )->post('https://ai.biovuedigitalwellness.com/api/v1/projection/combined', [
    //             'user_id'    => $user->id,
    //             'timeframe'  => $request->timeframe,
    //             'resolution' => $request->resolution,
    //         ]);

    //         if ($response->successful()) {
    //             $aiData = $response->json();

    //             \Illuminate\Support\Facades\DB::beginTransaction();

    //             $projection = ProjectionData::create([
    //                 'projection_id'    => $aiData['projection_id'],
    //                 'user_id'          => $user->id,
    //                 'input_image'      => $imagePath,
    //                 'timeframe'        => $aiData['timeframe'],
    //                 'resolution'       => $aiData['resolution'],
    //                 'projections_data' => $aiData['projections'], 
    //                 'summary_data'     => $aiData['summary'] ?? null, 
    //             ]);

    //             $credits->decrement('projection_limit');
                
    //             \Illuminate\Support\Facades\DB::commit();

    //             return response()->json(['success' => true, 'data' => $projection], 201);
    //         }

    //         return response()->json([
    //             'success' => false, 
    //             'message' => 'AI API Error', 
    //             'error_detail' => $response->body() 
    //         ], 502);

    //     } catch (\Exception $e) {
    //         if (\Illuminate\Support\Facades\DB::transactionLevel() > 0) {
    //             \Illuminate\Support\Facades\DB::rollBack();
    //         }
    //         \Illuminate\Support\Facades\Log::error("Projection Error: " . $e->getMessage());
    //         return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    //     }
    // }

    public function show($id)
    {
        $projection = ProjectionData::where('user_id', auth()->id())->findOrFail($id);
        
        $aiDomain = "https://ai.biovuedigitalwellness.com";
        $pData = $projection->projections_data;

        return response()->json([
            'success' => true,
            'title'   => 'Projection Results',
            'subtitle' => 'Visualizing your trajectory over the next ' . $projection->timeframe,
            'input_image' => asset('storage/' . $projection->input_image), 

            'data' => [
                'current_lifestyle' => [
                    'label'            => "If you continue your current lifestyle without changes for " . $projection->timeframe,
                    'image'            => $aiDomain . ($pData['current_lifestyle']['projection_url'] ?? ''),
                    'timeframe'        => $projection->timeframe,
                    'est_bmi'          => $pData['current_lifestyle']['est_bmi'] ?? 'N/A',
                    'est_weight'       => $pData['current_lifestyle']['est_weight'] ?? 'N/A',
                    'expected_changes' => $pData['current_lifestyle']['expected_changes'] ?? [],
                ],
                'future_goal' => [
                    'label'            => "Achieving your goal in " . $projection->timeframe,
                    'image'            => $aiDomain . ($pData['future_goal']['projection_url'] ?? ''),
                    'timeframe'        => $projection->timeframe,
                    'est_bmi'          => $pData['future_goal']['est_bmi'] ?? 'N/A',
                    'est_weight'       => $pData['future_goal']['est_weight'] ?? 'N/A',
                    'expected_changes' => $pData['future_goal']['expected_changes'] ?? [],
                ]
            ]
        ]);
    }
}