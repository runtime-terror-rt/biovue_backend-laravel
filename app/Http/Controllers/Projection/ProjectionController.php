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
        $hasPaidPlan = ($user->plan && !str_contains(strtolower($user->plan->name), 'free'))
            || $user->planPayments()->whereIn('status', ['paid', 'active'])->where('is_trial', false)->exists();
        
        $isTrialActive = ($user->trial_ends_at && $user->trial_ends_at->isFuture()) || $daysSinceJoined <= 7;

        $projections = ProjectionData::where('user_id', $user->id)
            ->latest()
            ->get();

        if ($projections->isEmpty()) {
            if (!$hasPaidPlan && !$isTrialActive) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your trial access period has expired. Please subscribe to a plan to view your projections.',
                    'locked'  => true
                ], 403);
            }

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
            'image' => [
                'required',
                'max:15360',
                function ($attribute, $value, $fail) {
                    $allowedMimes = [
                        'image/jpeg', 'image/png', 'image/gif',
                        'image/webp', 'image/avif', 'image/bmp',
                        'image/svg+xml', 'image/heic', 'image/heif',
                    ];
                    
                    if (!in_array($value->getMimeType(), $allowedMimes)) {
                        $fail('The ' . $attribute . ' must be a valid image format.');
                    }
                },
            ],
            'timeframe'  => 'required|string',
            'resolution' => 'required|string',
        ]);

        $user = auth()->user();

        // Plan and feature permission check:
        $userPlan = $user->plan;
        $planName = strtolower($userPlan?->name ?? 'free');
        $isPremium = str_contains($planName, 'premium');
        $isPlus    = str_contains($planName, 'plus');
        $isFree    = !$isPremium && !$isPlus;

        $timeframe  = strtolower(trim($request->timeframe));
        $resolution = strtolower(trim($request->resolution));

        // 4k resolution is hidden for future use
        if ($resolution === '4k') {
            return response()->json([
                'success' => false,
                'message' => '4K resolution is currently not supported. Please choose 1K or 2K resolution.'
            ], 422);
        }

        // Resolution limits: Free and Plus only get 1k; Premium gets 1k and 2k
        if (($isFree || $isPlus) && $resolution !== '1k') {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription plan only supports 1K resolution. Upgrade to Premium for 2K resolution.'
            ], 403);
        }

        // Timeframe limits: Free trial only gets 1_year
        $restrictedFreeVariants = ['6_month', '6month', '6 month', '6-month', '5_year', '5year', '5 year', '5', '5-year', '5_years', '5 years'];
        if ($isFree && in_array($timeframe, $restrictedFreeVariants)) {
            return response()->json([
                'success' => false,
                'message' => 'The Free Trial is only available for 1-Year projections. 6-Month and 5-Year options require a Plus or Premium subscription.'
            ], 403);
        }

        // Plus users cannot access 5_year projections
        $fiveYearVariants = ['5_year', '5year', '5 year', '5', '5-year', '5_years', '5 years', '5-years'];
        if ($isPlus && in_array($timeframe, $fiveYearVariants)) {
            return response()->json([
                'success' => false,
                'message' => '5-Year projections and health insights are exclusively available on the Premium plan. Please upgrade to Premium.'
            ], 403);
        }

        // 2. Credit limit check
        $credits = ProjectionCredit::firstOrCreate(
            ['user_id' => $user->id],
            [
                'projection_limit' => $user->plan?->projection_limit ?? 1,
                'member_limit'     => 0,
                'expiry_date'      => $user->trial_ends_at ?: now()->addDays(7)
            ]
        );
        if ($credits->projection_limit <= 0) {
            return response()->json([
                'success' => false, 
                'message' => 'No projection credits remaining. Please upgrade your plan to generate new projections.',
                'remaining_credit' => 0
            ], 403);
        }

        try {
            // 3. Store the input image locally (optimized, preserving aspect ratio and high fidelity)
            $imagePath = \App\Services\ImageOptimizerService::storeOptimized($request->file('image'), 'projections/inputs');
            $optimizedFullPath = storage_path('app/public/' . $imagePath);
            $imageContent = file_exists($optimizedFullPath) 
                ? file_get_contents($optimizedFullPath) 
                : file_get_contents($request->file('image')->getRealPath());

            // 4. Hit the AI API (Matching the FastAPI structure you provided)
            $response = \Illuminate\Support\Facades\Http::timeout(300)
                ->asMultipart()
                ->attach(
                    'image', 
                    $imageContent, 
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

                // Sync to separate projection tables for backward compatibility
                $aiDomain = "https://ai.biovuedigitalwellness.com";
                $pData = $projection->projections_data;

                if (isset($pData['current_lifestyle'])) {
                    \App\Models\AI\ProjectionLifestyle::create([
                        'user_id'          => $user->id,
                        'image'            => $imagePath,
                        'projection_id'    => $projection->id,
                        'projection_url'   => $pData['current_lifestyle']['projection_url'] ?? null,
                        'timeframe'        => $projection->timeframe,
                        'est_bmi'          => $pData['current_lifestyle']['est_bmi'] ?? null,
                        'est_weight'       => $pData['current_lifestyle']['est_weight'] ?? null,
                        'expected_changes' => isset($pData['current_lifestyle']['expected_changes']) ? json_encode($pData['current_lifestyle']['expected_changes']) : null,
                    ]);
                }

                if (isset($pData['future_goal'])) {
                    \App\Models\AI\ProjectionFutureGoal::create([
                        'user_id'          => $user->id,
                        'image'            => $imagePath,
                        'projection_id'    => $projection->id,
                        'projection_url'   => $pData['future_goal']['projection_url'] ?? null,
                        'timeframe'        => $projection->timeframe,
                        'est_bmi'          => $pData['future_goal']['est_bmi'] ?? null,
                        'est_weight'       => $pData['future_goal']['est_weight'] ?? null,
                        'expected_changes' => isset($pData['future_goal']['expected_changes']) ? json_encode($pData['future_goal']['expected_changes']) : null,
                    ]);
                }

                // Decrement User Credit
                $credits->decrement('projection_limit');
                $remainingLimit = max(0, (int)$credits->fresh()->projection_limit);
                
                \Illuminate\Support\Facades\DB::commit();

                $aiDomain = "https://ai.biovuedigitalwellness.com";
                $pData = $projection->projections_data;

                $formattedProjection = [
                    'id'               => $projection->id,
                    'title'            => 'Projection Results',
                    'subtitle'         => 'Visualizing your trajectory over the next ' . $projection->timeframe,
                    'timeframe'        => $projection->timeframe,
                    'input_image'      => asset('storage/' . $projection->input_image),
                    'remaining_credit' => $remainingLimit,
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
                    ],
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
                    'summary'          => $projection->summary_data,
                    'created_at'       => $projection->created_at->format('Y-m-d')
                ];

                return response()->json([
                    'success'          => true,
                    'message'          => 'Projection generated successfully.',
                    'remaining_credit' => $remainingLimit,
                    'data'             => $formattedProjection,
                ], 201);
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

        $lifestyleData = [
            'label'            => "If you continue your current lifestyle without changes for " . $projection->timeframe,
            'image'            => $aiDomain . ($pData['current_lifestyle']['projection_url'] ?? ''),
            'timeframe'        => $projection->timeframe,
            'est_bmi'          => $pData['current_lifestyle']['est_bmi'] ?? 'N/A',
            'est_weight'       => $pData['current_lifestyle']['est_weight'] ?? 'N/A',
            'expected_changes' => $pData['current_lifestyle']['expected_changes'] ?? [],
        ];

        $futureGoalData = [
            'label'            => "Achieving your goal in " . $projection->timeframe,
            'image'            => $aiDomain . ($pData['future_goal']['projection_url'] ?? ''),
            'timeframe'        => $projection->timeframe,
            'est_bmi'          => $pData['future_goal']['est_bmi'] ?? 'N/A',
            'est_weight'       => $pData['future_goal']['est_weight'] ?? 'N/A',
            'expected_changes' => $pData['future_goal']['expected_changes'] ?? [],
        ];

        return response()->json([
            'success'     => true,
            'title'       => 'Projection Results',
            'subtitle'    => 'Visualizing your trajectory over the next ' . $projection->timeframe,
            'timeframe'   => $projection->timeframe,
            'input_image' => asset('storage/' . $projection->input_image), 
            'summary'     => $projection->summary_data,
            'projections' => [
                'current_lifestyle' => $lifestyleData,
                'future_goal'       => $futureGoalData,
            ],
            'data' => [
                'current_lifestyle' => $lifestyleData,
                'future_goal'       => $futureGoalData,
            ]
        ]);
    }
}