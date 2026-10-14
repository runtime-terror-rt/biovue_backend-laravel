<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ExternalApi;
use App\Models\PlanPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class ExternalApiController extends Controller
{
    /**
     * Show API configuration and credentials for the authenticated business.
     */
    public function show(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $externalApi = ExternalApi::where('user_id', $user->id)->first();

        if (!$externalApi) {
            if ($this->userHasApiAccess($user)) {
                $externalApi = $this->createOrUpdateApiKeyForUser($user);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'API access is exclusively available on Tier 2 Professional, Tier 3 Professional, Enterprise, or Supplier plans. Please upgrade your plan to connect to the BioVue External API.',
                ], 403);
            }
        }

        $isExpired = $externalApi->end_date ? now()->gt($externalApi->end_date) : false;

        return response()->json([
            'success' => true,
            'data'    => [
                'api_key'          => $externalApi->api_key,
                'projection_limit' => (int) $externalApi->projection_limit,
                'insite_limit'     => (int) $externalApi->insite_limit,
                'start_date'       => $externalApi->start_date ? $externalApi->start_date->toIso8601String() : null,
                'end_date'         => $externalApi->end_date ? $externalApi->end_date->toIso8601String() : null,
                'is_active'        => !$isExpired,
                'expires_in_days'  => ($isExpired || !$externalApi->end_date) ? 0 : (int) now()->diffInDays($externalApi->end_date),
            ],
        ]);
    }

    /**
     * Generate or rotate/regenerate an API key for eligible business users.
     */
    public function generateOrRegenerateKey(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if (!$this->userHasApiAccess($user)) {
            return response()->json([
                'success' => false,
                'message' => 'API access is exclusively available on Tier 2 Professional, Tier 3 Professional, Enterprise, or Supplier plans. Please upgrade your subscription to generate an API key.',
            ], 403);
        }

        $externalApi = $this->createOrUpdateApiKeyForUser($user, true);
        $isExpired = $externalApi->end_date ? now()->gt($externalApi->end_date) : false;

        return response()->json([
            'success' => true,
            'message' => 'API key generated successfully.',
            'data' => [
                'api_key'          => $externalApi->api_key,
                'projection_limit' => (int) $externalApi->projection_limit,
                'insite_limit'     => (int) $externalApi->insite_limit,
                'start_date'       => $externalApi->start_date ? $externalApi->start_date->toIso8601String() : null,
                'end_date'         => $externalApi->end_date ? $externalApi->end_date->toIso8601String() : null,
                'is_active'        => !$isExpired,
                'expires_in_days'  => ($isExpired || !$externalApi->end_date) ? 0 : (int) now()->diffInDays($externalApi->end_date),
            ]
        ]);
    }

    /**
     * Admin view: List all external API keys with pagination.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 10);

            $apis = ExternalApi::with('user')
                ->latest()
                ->paginate($perPage);

            $result = $apis->map(function ($api) {
                $isExpired = $api->end_date ? now()->gt($api->end_date) : false;
                return [
                    'id'               => $api->id,
                    'user'             => [
                        'id'    => $api->user?->id,
                        'name'  => $api->user?->name,
                        'email' => $api->user?->email,
                    ],
                    'api_key'          => $api->api_key,
                    'projection_limit' => (int) $api->projection_limit,
                    'insite_limit'     => (int) $api->insite_limit,
                    'start_date'       => $api->start_date,
                    'end_date'         => $api->end_date,
                    'is_active'        => !$isExpired,
                    'expires_in_days'  => ($isExpired || !$api->end_date) ? 0 : (int) now()->diffInDays($api->end_date),
                    'created_at'       => $api->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'meta'    => [
                    'current_page' => $apis->currentPage(),
                    'last_page'    => $apis->lastPage(),
                    'per_page'     => $apis->perPage(),
                    'total'        => $apis->total(),
                ],
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('ExternalApi Index Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Validate API key, check active status, and see projection/insight limits.
     */
    public function validateApiKey(Request $request)
    {
        $request->validate([
            'api_key' => 'required|string',
        ]);

        $externalApi = ExternalApi::where('api_key', $request->api_key)->first();

        if (!$externalApi) {
            return response()->json([
                'success' => false,
                'valid'   => false,
                'message' => 'Invalid API key.',
            ], 401);
        }

        $isExpired = $externalApi->end_date ? now()->gt($externalApi->end_date) : false;

        if ($isExpired) {
            return response()->json([
                'success' => false,
                'valid'   => false,
                'message' => 'API key has expired. Please renew your subscription.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'valid'   => true,
            'data'    => [
                'projection_limit' => (int) $externalApi->projection_limit,
                'insite_limit'     => (int) $externalApi->insite_limit,
                'end_date'         => $externalApi->end_date ? $externalApi->end_date->toIso8601String() : null,
                'expires_in_days'  => (int) now()->diffInDays($externalApi->end_date),
            ],
        ]);
    }

    /**
     * Reveal unmasked API key for the logged-in user.
     */
    public function revealApiKey()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $apiKey = DB::table('external_apis')->where('user_id', $user->id)->value('api_key');

        if (!$apiKey) {
            if ($this->userHasApiAccess($user)) {
                $externalApi = $this->createOrUpdateApiKeyForUser($user);
                $apiKey = $externalApi->api_key;
            } else {
                return response()->json(['success' => false, 'message' => 'No API Key found.'], 404);
            }
        }

        return response()->json([
            'success' => true,
            'api_key' => $apiKey 
        ]);
    }

    /**
     * Developer profile view with masked key, current plan, and balance.
     */
    public function getDeveloperProfile()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }
        
        $apiData = ExternalApi::where('user_id', $user->id)->first();
        if (!$apiData && $this->userHasApiAccess($user)) {
            $apiData = $this->createOrUpdateApiKeyForUser($user);
        }

        $isExpired = $apiData && $apiData->end_date ? now()->gt($apiData->end_date) : false;

        return response()->json([
            'success' => true,
            'data' => [
                'plan_name'        => $user->plan ? $user->plan->name : 'Free Plan',
                'api_key'          => $apiData ? 'bv_' . substr($apiData->api_key, 3, 6) . '...' . substr($apiData->api_key, -4) : null, 
                'projection_limit' => $apiData ? (int) $apiData->projection_limit : 0,
                'insite_limit'     => $apiData ? (int) $apiData->insite_limit : 0,
                'is_active'        => $apiData ? !$isExpired : false,
                'expires_at'       => $apiData?->end_date ? $apiData->end_date->toIso8601String() : null,
                'usage_count'      => 0,
            ]
        ]);
    }

    /**
     * Get API key balance and remaining quota (syncs local database with AI Fast-API service).
     */
    public function getBalance(Request $request)
    {
        $apiKey = $request->input('api_key') 
            ?: $request->header('X-API-KEY') 
            ?: $request->header('X-Api-Key');

        if (!$apiKey && auth()->check()) {
            $apiKey = ExternalApi::where('user_id', auth()->id())->value('api_key');
        }

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API Key is required. Send via X-API-KEY header or api_key parameter.'
            ], 401);
        }

        $externalApi = ExternalApi::where('api_key', $apiKey)->first();
        if (!$externalApi) {
            return response()->json(['success' => false, 'message' => 'Invalid API key.'], 401);
        }

        $isExpired = $externalApi->end_date ? now()->gt($externalApi->end_date) : false;

        $aiBalance = null;
        try {
            $aiRes = Http::timeout(5)->get('https://ai.biovuedigitalwellness.com/api/v1/api_service/balance/', [
                'api_key' => $apiKey
            ]);
            if ($aiRes->successful()) {
                $aiBalance = $aiRes->json();
            }
        } catch (\Throwable $e) {
            // Silently use database record
        }

        return response()->json([
            'success' => true,
            'data' => [
                'api_key'            => 'bv_' . substr($externalApi->api_key, 3, 6) . '...' . substr($externalApi->api_key, -4),
                'projection_limit'   => (int) $externalApi->projection_limit,
                'insite_limit'       => (int) $externalApi->insite_limit,
                'is_active'          => !$isExpired,
                'expires_at'         => $externalApi->end_date ? $externalApi->end_date->toIso8601String() : null,
                'expires_in_days'    => ($isExpired || !$externalApi->end_date) ? 0 : (int) now()->diffInDays($externalApi->end_date),
                'ai_service_balance' => $aiBalance,
            ]
        ]);
    }

    /**
     * External Gateway: Generate combined projections for external businesses.
     * Matches FastAPI endpoint POST /api/v1/api_service/projection/combined/
     */
    public function projectCombined(Request $request)
    {
        $externalApi = $this->resolveExternalApi($request);
        if (!$externalApi) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid, expired, or missing API key. Provide via X-API-KEY header or api_key parameter.'
            ], 401);
        }

        if ($externalApi->projection_limit <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Projection limit exhausted for this API key. Please upgrade your subscription.'
            ], 403);
        }

        $request->validate([
            'image'                      => 'required|file|max:15360',
            'full_name'                  => 'required|string',
            'email'                      => 'required|email',
            'age'                        => 'required|integer',
            'gender'                     => 'required|string',
            'height_cm'                  => 'required|numeric',
            'weight_lbs'                 => 'required|numeric',
            'weekly_workout_sessions'    => 'required|integer',
            'weekly_avg_daily_steps'     => 'required|integer',
            'weekly_avg_sleep_hours'     => 'required|numeric',
            'weekly_avg_water_litres'    => 'required|numeric',
            'nutrition_quality'          => 'required|integer',
            'alcohol_consumption'        => 'required|string',
            'fast_food_frequency'        => 'required|string',
            'stress_level'               => 'required|string',
            'smoking'                    => 'required|string',
            'goal_description'           => 'required|string',
            'goal_timeframe'             => 'required|string',
        ]);

        try {
            $imageFile = $request->file('image');
            $http = Http::timeout(300)->asMultipart();

            // Attach image
            $http->attach('image', file_get_contents($imageFile->getRealPath()), $imageFile->getClientOriginalName());

            // Always forward the verified api_key
            $http->attach('api_key', $externalApi->api_key);

            // Forward all other request fields
            $fields = $request->except(['image', 'api_key']);
            foreach ($fields as $key => $val) {
                if (is_bool($val)) {
                    $http->attach($key, $val ? 'true' : 'false');
                } elseif (is_scalar($val)) {
                    $http->attach($key, (string) $val);
                }
            }

            $aiResponse = $http->post('https://ai.biovuedigitalwellness.com/api/v1/api_service/projection/combined/');

            if ($aiResponse->successful()) {
                // Decrement projection limit
                $externalApi->decrement('projection_limit');

                return response()->json($aiResponse->json(), 200);
            }

            return response()->json([
                'success'      => false,
                'message'      => 'AI Projection Service Error',
                'error_detail' => $aiResponse->json() ?: $aiResponse->body()
            ], $aiResponse->status() ?: 502);

        } catch (\Throwable $e) {
            Log::error('External Project Combined Gateway Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error while communicating with BioVue AI',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * External Gateway: Get current health insights.
     * Matches FastAPI endpoint POST /api/v1/api_service/insights/current/
     */
    public function currentInsights(Request $request)
    {
        $externalApi = $this->resolveExternalApi($request);
        if (!$externalApi) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid, expired, or missing API key. Provide via X-API-KEY header or api_key parameter.'
            ], 401);
        }

        if ($externalApi->insite_limit <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Insight limit exhausted for this API key. Please upgrade your subscription.'
            ], 403);
        }

        $params = $request->all();
        $params['api_key'] = $externalApi->api_key;

        try {
            $aiResponse = Http::asForm()
                ->timeout(60)
                ->post('https://ai.biovuedigitalwellness.com/api/v1/api_service/insights/current/', $params);

            if ($aiResponse->successful()) {
                $externalApi->decrement('insite_limit');
                return response()->json($aiResponse->json(), 200);
            }

            return response()->json([
                'success'      => false,
                'message'      => 'AI Insights Service Error',
                'error_detail' => $aiResponse->json() ?: $aiResponse->body()
            ], $aiResponse->status() ?: 502);

        } catch (\Throwable $e) {
            Log::error('External Current Insights Gateway Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * External Gateway: Get future health insights.
     * Matches FastAPI endpoint POST /api/v1/api_service/insights/future/
     */
    public function futureInsights(Request $request)
    {
        $externalApi = $this->resolveExternalApi($request);
        if (!$externalApi) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid, expired, or missing API key. Provide via X-API-KEY header or api_key parameter.'
            ], 401);
        }

        if ($externalApi->insite_limit <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Insight limit exhausted for this API key. Please upgrade your subscription.'
            ], 403);
        }

        $params = $request->all();
        $params['api_key'] = $externalApi->api_key;

        try {
            $aiResponse = Http::asForm()
                ->timeout(60)
                ->post('https://ai.biovuedigitalwellness.com/api/v1/api_service/insights/future/', $params);

            if ($aiResponse->successful()) {
                $externalApi->decrement('insite_limit');
                return response()->json($aiResponse->json(), 200);
            }

            return response()->json([
                'success'      => false,
                'message'      => 'AI Future Insights Service Error',
                'error_detail' => $aiResponse->json() ?: $aiResponse->body()
            ], $aiResponse->status() ?: 502);

        } catch (\Throwable $e) {
            Log::error('External Future Insights Gateway Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sandbox proxy for developers to test local or internal routes.
     */
    public function sandboxProxy(Request $request)
    {
        $request->validate([
            'target_endpoint' => 'required|string', 
            'payload'         => 'nullable|array',
        ]);

        $target = $request->input('target_endpoint');
        $payload = $request->input('payload', []);

        $subRequest = Request::create($target, 'POST', $payload);
        $subRequest->headers->set('X-BioVue-Sandbox', 'true');
        $subRequest->headers->set('Accept', 'application/json');

        if (auth()->check()) {
            $subRequest->setUserResolver(fn() => auth()->user());
        }

        $response = Route::dispatch($subRequest);

        return response()->json([
            'success'        => true,
            'sandbox_mode'   => true,
            'status_code'    => $response->getStatusCode(),
            'response_data'  => json_decode($response->getContent(), true)
        ], $response->getStatusCode());
    }

    /**
     * Comprehensive Developer Documentation for external businesses integrating BioVue API.
     */
    public function getDocumentation()
    {
        return response()->json([
            'success'     => true,
            'title'       => 'BioVue External Business API Documentation',
            'version'     => '2.0',
            'description' => 'Complete B2B connectivity documentation for external wellness platforms, gym chains, clinics, and supplement suppliers connecting to BioVue.',
            'base_urls'   => [
                'laravel_gateway' => url('/api/v1'),
                'direct_ai_microservice' => 'https://ai.biovuedigitalwellness.com/api/v1',
            ],
            'authentication' => [
                'type'        => 'API Key',
                'header'      => 'X-API-KEY: <your_api_key>',
                'bearer'      => 'Authorization: Bearer <your_api_key>',
                'body_param'  => 'api_key: <your_api_key>',
                'description' => 'Include your API Key in the X-API-KEY HTTP header or as the api_key form field with every request.'
            ],
            'plan_permissions' => [
                'Tier 2 Professional' => 'Includes 50 Projections/month, 1K/2K resolution, and External API Access',
                'Tier 3 Professional' => 'Includes 600 Projections/month, dedicated account manager, and high-volume API access',
                'Enterprise'          => 'Custom volume (10,000+ Projections), custom SLAs, and priority endpoints',
                'Supplier Business'   => 'Includes Catalog Match API, Walk-in Client Registration, and Supplier Notifications',
            ],
            'endpoints' => [
                [
                    'name'        => 'Combined AI Body Projection',
                    'method'      => 'POST',
                    'gateway_url' => '/api/v1/external/projection/combined',
                    'direct_url'  => 'https://ai.biovuedigitalwellness.com/api/v1/api_service/projection/combined/',
                    'content_type'=> 'multipart/form-data',
                    'description' => 'Generates BOTH current-lifestyle and future-goal projections in parallel with AI body visualizer. Returns base64 encoded images without requiring client-side storage.',
                    'parameters'  => [
                        'image'                   => '(file, required) User full-body photo (JPG, PNG, WEBP max 15MB)',
                        'full_name'               => '(string, required) Client full name',
                        'email'                   => '(string, required) Client email address',
                        'age'                     => '(integer, required) Client age',
                        'gender'                  => '(string, required) male | female | other',
                        'height_cm'               => '(number, required) Height in centimeters',
                        'weight_lbs'              => '(number, required) Current weight in pounds',
                        'body_fat_percent'        => '(number, optional, default: 0.0) AI estimates from image if omitted',
                        'activity_level'          => '(string, default: moderate) sedentary | light | moderate | active | very_active',
                        'weekly_workout_sessions' => '(integer, required) e.g. 3',
                        'weekly_avg_daily_steps'  => '(integer, required) e.g. 8000',
                        'weekly_avg_sleep_hours'  => '(number, required) e.g. 7.5',
                        'weekly_avg_water_litres' => '(number, required) e.g. 2.5',
                        'nutrition_quality'       => '(integer, 1-10, required) Nutrition rating',
                        'alcohol_consumption'     => '(string, required) none | light | moderate | heavy',
                        'fast_food_frequency'     => '(string, required) none | rarely | 1-2 times/week | daily',
                        'stress_level'            => '(string, required) low | moderate | high',
                        'smoking'                 => '(string, required) none | occasional | regular',
                        'goal_type'               => '(string, required) Lean | Toned | Athletic | Muscular | Curvy Fit',
                        'goal_description'        => '(string, required) e.g. Build core definition and drop 10 lbs',
                        'goal_timeframe'          => '(string, required) 6 months | 1 year | 5 years',
                        'resolution'              => '(string, optional, default: 1K) 1K | 2K | 4K',
                    ],
                ],
                [
                    'name'        => 'Current Health Insights',
                    'method'      => 'POST',
                    'gateway_url' => '/api/v1/external/insights/current',
                    'direct_url'  => 'https://ai.biovuedigitalwellness.com/api/v1/api_service/insights/current/',
                    'content_type'=> 'application/x-www-form-urlencoded or JSON',
                    'description' => 'Generates multi-dimensional health and habit insights based on the client latest biometric data.',
                ],
                [
                    'name'        => 'Future Trajectory Insights',
                    'method'      => 'POST',
                    'gateway_url' => '/api/v1/external/insights/future',
                    'direct_url'  => 'https://ai.biovuedigitalwellness.com/api/v1/api_service/insights/future/',
                    'content_type'=> 'application/x-www-form-urlencoded or JSON',
                    'description' => 'Generates long-term health trajectory insights for 6 months, 1 year, or 5 years horizon.',
                ],
                [
                    'name'        => 'API Balance & Quota Check',
                    'method'      => 'GET',
                    'gateway_url' => '/api/v1/external-api/balance',
                    'direct_url'  => 'https://ai.biovuedigitalwellness.com/api/v1/api_service/balance/?api_key=<api_key>',
                    'description' => 'Returns remaining projection limits, insight limits, and expiration date.',
                ],
                [
                    'name'        => 'Validate API Key',
                    'method'      => 'POST',
                    'gateway_url' => '/api/v1/external-api/validate',
                    'description' => 'Verifies if an API key is active and returns authorized limits.',
                ],
                [
                    'name'        => 'Register Walk-In Client (Supplier/Clinic)',
                    'method'      => 'POST',
                    'gateway_url' => '/api/v1/supplier/walk-in-client',
                    'description' => 'Registers client biometric and target goals for business catalog matching.',
                ],
                [
                    'name'        => 'Find Product Match (Supplier)',
                    'method'      => 'POST',
                    'gateway_url' => '/api/v1/supplier/find-match',
                    'description' => 'Matches catalog products against user biometric profile using AI scoring.',
                ],
                [
                    'name'        => 'Notify Client (Supplier)',
                    'method'      => 'POST',
                    'gateway_url' => '/api/v1/supplier/notify-client',
                    'description' => 'Dispatches personalized recommendation emails from business to client.',
                ],
            ],
            'sample_curl' => [
                'balance_check' => 'curl -H "X-API-KEY: bv_live_your_key_here" ' . url('/api/v1/external-api/balance'),
                'projection'    => 'curl -X POST ' . url('/api/v1/external/projection/combined') . ' -H "X-API-KEY: bv_live_your_key_here" -F "image=@photo.jpg" -F "full_name=Alex Smith" -F "email=alex@example.com" -F "age=30" -F "gender=male" -F "height_cm=180" -F "weight_lbs=185" -F "weekly_workout_sessions=4" -F "weekly_avg_daily_steps=9000" -F "weekly_avg_sleep_hours=7.5" -F "weekly_avg_water_litres=3" -F "nutrition_quality=8" -F "alcohol_consumption=light" -F "fast_food_frequency=rarely" -F "stress_level=low" -F "smoking=none" -F "goal_type=Toned" -F "goal_description=Build lean muscle" -F "goal_timeframe=1 year" -F "resolution=1K"',
            ],
            'error_codes' => [
                200 => 'Success',
                400 => 'Bad Request: Missing or invalid parameters',
                401 => 'Unauthorized: Invalid or missing API Key',
                403 => 'Forbidden: API key expired or projection quota exhausted. Plan upgrade required.',
                422 => 'Validation Error: Parameter type mismatch',
                500 => 'Server Error: Internal issue contacting AI microservice',
            ]
        ]);
    }

    /**
     * Check if a given user has plan permissions for external API access.
     */
    protected function userHasApiAccess($user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->user_type === 'admin') {
            return true;
        }

        $plan = $user->plan;
        if (!$plan) {
            $latestPayment = PlanPayment::where('user_id', $user->id)
                ->whereIn('status', ['paid', 'active'])
                ->latest()
                ->first();
            $plan = $latestPayment?->plan;
        }

        if (!$plan) {
            return false;
        }

        $planName = strtolower($plan->name ?? '');
        if (str_contains($planName, 'tier 2') || 
            str_contains($planName, 'tier 3') || 
            str_contains($planName, 'enterprise') ||
            str_contains($planName, 'supplier')) {
            return true;
        }

        $features = is_array($plan->features) 
            ? $plan->features 
            : (is_string($plan->features) ? json_decode($plan->features, true) : []);

        if (is_array($features)) {
            foreach ($features as $f) {
                if (is_string($f) && stripos($f, 'api access') !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Helper: Create or rotate an API key for the user.
     */
    protected function createOrUpdateApiKeyForUser($user, bool $forceRegenerate = false): ExternalApi
    {
        $existing = ExternalApi::where('user_id', $user->id)->first();
        if ($existing && !$forceRegenerate) {
            return $existing;
        }

        $plan = $user->plan;
        if (!$plan) {
            $latestPayment = PlanPayment::where('user_id', $user->id)
                ->whereIn('status', ['paid', 'active'])
                ->latest()
                ->first();
            $plan = $latestPayment?->plan;
        }

        $planName = strtolower($plan?->name ?? '');
        $projLimit = 50;
        $insiteLimit = 50;
        $durationDays = 180;

        if (str_contains($planName, 'tier 3')) {
            $projLimit = 600;
            $insiteLimit = 600;
            $durationDays = 180;
        } elseif (str_contains($planName, 'enterprise')) {
            $projLimit = 10000;
            $insiteLimit = 10000;
            $durationDays = 365;
        } elseif (str_contains($planName, 'supplier')) {
            $projLimit = 100;
            $insiteLimit = 100;
            $durationDays = 30;
        } elseif ($plan && $plan->projection_limit > 0) {
            $projLimit = $plan->projection_limit;
            $insiteLimit = $plan->projection_limit;
        }

        do {
            $apiKey = 'bv_live_' . Str::random(48);
        } while (ExternalApi::where('api_key', $apiKey)->exists());

        return ExternalApi::updateOrCreate(
            ['user_id' => $user->id],
            [
                'api_key'          => $apiKey,
                'projection_limit' => ($existing && !$forceRegenerate) ? $existing->projection_limit : $projLimit,
                'insite_limit'     => ($existing && !$forceRegenerate) ? $existing->insite_limit : $insiteLimit,
                'start_date'       => ($existing && !$forceRegenerate) ? $existing->start_date : now(),
                'end_date'         => ($existing && !$forceRegenerate) ? $existing->end_date : now()->addDays($durationDays),
            ]
        );
    }

    /**
     * Resolve and validate ExternalApi record from incoming request.
     */
    protected function resolveExternalApi(Request $request): ?ExternalApi
    {
        $apiKey = $request->header('X-API-KEY') 
            ?: $request->header('X-Api-Key') 
            ?: $request->input('api_key');

        if (!$apiKey) {
            $authHeader = $request->header('Authorization', '');
            if (str_starts_with($authHeader, 'Bearer bv_')) {
                $apiKey = substr($authHeader, 7);
            }
        }

        if (!$apiKey && auth()->check()) {
            return ExternalApi::where('user_id', auth()->id())->first();
        }

        if (!$apiKey) {
            return null;
        }

        $externalApi = ExternalApi::where('api_key', $apiKey)->first();
        if (!$externalApi || $externalApi->isExpired()) {
            return null;
        }

        return $externalApi;
    }
}