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
    public function index(Request $request)
    {
        $loggedInUser = auth()->user();
        $targetUserId = $request->query('user_id') 
            ?: ($request->query('client_id') ?: $loggedInUser->id);

        if ($targetUserId != $loggedInUser->id) {
            $isConnected = DB::table('connect_user_proffesions')
                ->where('profession_id', $loggedInUser->id)
                ->where('user_id', $targetUserId)
                ->exists();

            if (!$isConnected && $loggedInUser->user_type !== 'admin' && $loggedInUser->user_type !== 'professional') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have permission to view these projections.'
                ], 403);
            }
        }

        $user = \App\Models\User::find($targetUserId) ?: $loggedInUser;

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

        $formattedData = $projections->map(function($projection) {
            $pData = $projection->projections_data;

            $currentLifestyleUrl = $this->resolveProjectionImageUrl($pData['current_lifestyle'] ?? null);
            $futureGoalUrl       = $this->resolveProjectionImageUrl($pData['future_goal'] ?? null);

            return [
                'id' => $projection->id,
                'title'   => 'Projection Results',
                'timeframe' => $projection->timeframe,
                'input_image' => asset('storage/' . $projection->input_image), 

                'projections' => [
                    'current_lifestyle' => [
                        'label'            => "Current lifestyle trajectory for " . $projection->timeframe,
                        'image'            => $currentLifestyleUrl,
                        'timeframe'        => $projection->timeframe,
                        'est_bmi'          => $pData['current_lifestyle']['est_bmi'] ?? 'N/A',
                        'est_weight'       => $pData['current_lifestyle']['est_weight'] ?? 'N/A',
                        'expected_changes' => $pData['current_lifestyle']['expected_changes'] ?? [],
                    ],
                    'future_goal' => [
                        'label'            => "Target goal achievement in " . $projection->timeframe,
                        'image'            => $futureGoalUrl,
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
        if (!$userPlan) {
            $latestPaid = \App\Models\PlanPayment::where('user_id', $user->id)
                ->whereIn('status', ['paid', 'active'])
                ->latest()
                ->first();
            if ($latestPaid && $latestPaid->plan) {
                $userPlan = $latestPaid->plan;
            }
        }

        $planName = strtolower($userPlan?->name ?? 'free');
        $isPremium = str_contains($planName, 'premium');
        $isPlus    = str_contains($planName, 'plus');

        // Check if user is an invited client under a trainer
        $hasTrainerConnection = \Illuminate\Support\Facades\DB::table('connect_user_proffesions')
            ->where('user_id', $user->id)
            ->exists();
        $isInvited = (bool)$user->is_invited || $hasTrainerConnection;

        // If user has no personal paid plan but is an invited client, grant Plus-tier feature permissions (6mo/1yr, 1k/2k)
        if (!$isPremium && !$isPlus && $isInvited) {
            $isPlus = true;
        }

        $isFree = !$isPremium && !$isPlus;

        $timeframeRaw  = strtolower(trim($request->timeframe));
        $resolutionRaw = strtoupper(trim($request->resolution));

        // 4k resolution is hidden for future use
        if ($resolutionRaw === '4K') {
            return response()->json([
                'success' => false,
                'message' => '4K resolution is currently not supported. Please choose 1K or 2K resolution.'
            ], 422);
        }

        // Normalize resolution: default to '1K' or '2K'
        $normalizedResolution = in_array($resolutionRaw, ['1K', '2K']) ? $resolutionRaw : '1K';

        // Resolution limits: Free Trial only gets 1k; Plus and Premium get both 1k and 2k
        if ($isFree && $normalizedResolution !== '1K') {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription plan only supports 1K resolution. Upgrade to Plus or Premium for 2K resolution.'
            ], 403);
        }

        // Timeframe normalization & variants
        $sixMonthVariants = ['6_month', '6month', '6 month', '6-month', '6_months', '6 months', '6-months', '6 mo', '6mo', '6 mos', '6mos', '6m'];
        $oneYearVariants  = ['1_year', '1year', '1 year', '1-year', '1_years', '1 years', '1-years', '1 yr', '1yr', '1 yrs', '1yrs', '1y'];
        $fiveYearVariants = ['5_year', '5year', '5 year', '5', '5-year', '5_years', '5 years', '5-years', '5 yr', '5yr', '5 yrs', '5yrs', '5y'];

        $is6Month = in_array($timeframeRaw, $sixMonthVariants);
        $is5Year  = in_array($timeframeRaw, $fiveYearVariants);
        $is1Year  = in_array($timeframeRaw, $oneYearVariants);

        // Canonical duration for AI API (FastAPI expects strict enum: '6 months', '1 year', '5 years')
        if ($is6Month) {
            $aiDuration = '6 months';
            $canonicalTimeframe = '6_month';
        } elseif ($is5Year) {
            $aiDuration = '5 years';
            $canonicalTimeframe = '5_year';
        } else {
            $aiDuration = '1 year';
            $canonicalTimeframe = '1_year';
        }

        // Timeframe limits: Free trial only gets 1_year
        if ($isFree && ($is6Month || $is5Year)) {
            return response()->json([
                'success' => false,
                'message' => 'The Free Trial is only available for 1-Year projections. 6-Month and 5-Year options require a Plus or Premium subscription.'
            ], 403);
        }

        // Plus users cannot access 5_year projections
        if ($isPlus && $is5Year) {
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
                ->attach('duration', $aiDuration) 
                ->attach('resolution', $normalizedResolution) 
                ->attach('use_default_goal', 'true') 
                ->post('https://ai.biovuedigitalwellness.com/api/v1/projection/combined/');

            // 5. Handling Response
            if ($response->successful()) {
                $aiData = $response->json();
                $pData = $aiData['projections'] ?? [];

                // Automatically download & store AI generated projection images on Hostinger storage
                $localCurrentPath = $this->storeRemoteProjectionImage(
                    $pData['current_lifestyle']['projection_url'] ?? null, 
                    $user->id, 
                    'current'
                );
                if ($localCurrentPath) {
                    $pData['current_lifestyle']['local_image'] = $localCurrentPath;
                }

                $localGoalPath = $this->storeRemoteProjectionImage(
                    $pData['future_goal']['projection_url'] ?? null, 
                    $user->id, 
                    'goal'
                );
                if ($localGoalPath) {
                    $pData['future_goal']['local_image'] = $localGoalPath;
                }

                \Illuminate\Support\Facades\DB::beginTransaction();

                // Store in your local Database
                $projection = \App\Models\ProjectionData::create([
                    'projection_id'    => $aiData['projection_id'],
                    'user_id'          => $user->id,
                    'input_image'      => $imagePath,
                    'timeframe'        => $aiData['timeframe'] ?? $canonicalTimeframe,
                    'resolution'       => $aiData['resolution'] ?? $normalizedResolution,
                    'projections_data' => $pData, 
                    'summary_data'     => $aiData['summary'] ?? null, 
                ]);

                // Sync to separate projection tables for backward compatibility
                if (isset($pData['current_lifestyle'])) {
                    \App\Models\AI\ProjectionLifestyle::create([
                        'user_id'          => $user->id,
                        'image'            => $imagePath,
                        'projection_id'    => $projection->id,
                        'projection_url'   => $localCurrentPath ? asset('storage/' . $localCurrentPath) : ($pData['current_lifestyle']['projection_url'] ?? null),
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
                        'projection_url'   => $localGoalPath ? asset('storage/' . $localGoalPath) : ($pData['future_goal']['projection_url'] ?? null),
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

                $currentLifestyleUrl = $this->resolveProjectionImageUrl($pData['current_lifestyle'] ?? null);
                $futureGoalUrl       = $this->resolveProjectionImageUrl($pData['future_goal'] ?? null);

                $formattedProjection = [
                    'id'               => $projection->id,
                    'title'            => 'Projection Results',
                    'subtitle'         => 'Visualizing your trajectory over the next ' . $projection->timeframe,
                    'timeframe'        => $projection->timeframe,
                    'input_image'      => asset('storage/' . $projection->input_image),
                    'remaining_credit' => $remainingLimit,
                    'current_lifestyle' => [
                        'label'            => "Current lifestyle trajectory for " . $projection->timeframe,
                        'image'            => $currentLifestyleUrl,
                        'timeframe'        => $projection->timeframe,
                        'est_bmi'          => $pData['current_lifestyle']['est_bmi'] ?? 'N/A',
                        'est_weight'       => $pData['current_lifestyle']['est_weight'] ?? 'N/A',
                        'expected_changes' => $pData['current_lifestyle']['expected_changes'] ?? [],
                    ],
                    'future_goal' => [
                        'label'            => "Target goal achievement in " . $projection->timeframe,
                        'image'            => $futureGoalUrl,
                        'timeframe'        => $projection->timeframe,
                        'est_bmi'          => $pData['future_goal']['est_bmi'] ?? 'N/A',
                        'est_weight'       => $pData['future_goal']['est_weight'] ?? 'N/A',
                        'expected_changes' => $pData['future_goal']['expected_changes'] ?? [],
                    ],
                    'projections' => [
                        'current_lifestyle' => [
                            'label'            => "Current lifestyle trajectory for " . $projection->timeframe,
                            'image'            => $currentLifestyleUrl,
                            'timeframe'        => $projection->timeframe,
                            'est_bmi'          => $pData['current_lifestyle']['est_bmi'] ?? 'N/A',
                            'est_weight'       => $pData['current_lifestyle']['est_weight'] ?? 'N/A',
                            'expected_changes' => $pData['current_lifestyle']['expected_changes'] ?? [],
                        ],
                        'future_goal' => [
                            'label'            => "Target goal achievement in " . $projection->timeframe,
                            'image'            => $futureGoalUrl,
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
        $loggedInUser = auth()->user();

        $projection = ProjectionData::findOrFail($id);

        // Allow owner, admin, or connected professional
        if ($projection->user_id !== $loggedInUser->id && $loggedInUser->user_type !== 'admin') {
            $isConnected = DB::table('connect_user_proffesions')
                ->where('profession_id', $loggedInUser->id)
                ->where('user_id', $projection->user_id)
                ->exists();

            if (!$isConnected && $loggedInUser->user_type !== 'professional') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this projection.'
                ], 403);
            }
        }
        
        $pData = $projection->projections_data;

        $currentLifestyleUrl = $this->resolveProjectionImageUrl($pData['current_lifestyle'] ?? null);
        $futureGoalUrl       = $this->resolveProjectionImageUrl($pData['future_goal'] ?? null);

        $lifestyleData = [
            'label'            => "If you continue your current lifestyle without changes for " . $projection->timeframe,
            'image'            => $currentLifestyleUrl,
            'timeframe'        => $projection->timeframe,
            'est_bmi'          => $pData['current_lifestyle']['est_bmi'] ?? 'N/A',
            'est_weight'       => $pData['current_lifestyle']['est_weight'] ?? 'N/A',
            'expected_changes' => $pData['current_lifestyle']['expected_changes'] ?? [],
        ];

        $futureGoalData = [
            'label'            => "Achieving your goal in " . $projection->timeframe,
            'image'            => $futureGoalUrl,
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

    /**
     * Download AI-generated projection image from remote Python FastAPI service and store locally on Hostinger storage.
     *
     * @param string|null $urlPath
     * @param int $userId
     * @param string $type
     * @return string|null Relative storage path (e.g. 'projections/ai_generated/proj_1_current_1728888888_abc123.jpg')
     */
    protected function storeRemoteProjectionImage(?string $urlPath, int $userId, string $type): ?string
    {
        if (empty($urlPath)) {
            return null;
        }

        try {
            // Build full download URL
            $fullUrl = (str_starts_with($urlPath, 'http://') || str_starts_with($urlPath, 'https://'))
                ? $urlPath
                : 'https://ai.biovuedigitalwellness.com/' . ltrim($urlPath, '/');

            // Download with reasonable timeout
            $response = \Illuminate\Support\Facades\Http::timeout(60)->get($fullUrl);

            if ($response->successful()) {
                $dir = 'projections/ai_generated';
                if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($dir)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory($dir);
                }

                $filename = $dir . '/proj_' . $userId . '_' . $type . '_' . time() . '_' . \Illuminate\Support\Str::random(8) . '.jpg';
                \Illuminate\Support\Facades\Storage::disk('public')->put($filename, $response->body());

                return $filename;
            } else {
                \Illuminate\Support\Facades\Log::warning("Failed to download AI projection image from {$fullUrl}: HTTP " . $response->status());
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Error saving AI projection image locally: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Resolve public URL for projection image (local Hostinger asset if saved, else fallback to remote AI domain).
     *
     * @param array|null $item
     * @return string
     */
    protected function resolveProjectionImageUrl(?array $item): string
    {
        if (empty($item)) {
            return '';
        }

        // 1. If stored locally and file exists on public disk
        if (!empty($item['local_image'])) {
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($item['local_image'])) {
                return asset('storage/' . $item['local_image']);
            }
        }

        // 2. Fallback to remote AI server URL
        if (!empty($item['projection_url'])) {
            $url = $item['projection_url'];
            if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                return $url;
            }
            return 'https://ai.biovuedigitalwellness.com/' . ltrim($url, '/');
        }

        return '';
    }
}