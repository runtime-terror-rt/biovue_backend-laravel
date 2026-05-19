<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ExternalApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class ExternalApiController extends Controller
{

    public function show(Request $request)
    {
        $user      = auth()->user();
        $externalApi = ExternalApi::where('user_id', $user->id)->first();

        if (!$externalApi) {
            return response()->json([
                'success' => false,
                'message' => 'No API access found. Please subscribe to an API plan.',
            ], 404);
        }

        $isExpired = now()->gt($externalApi->end_date);

        return response()->json([
            'success' => true,
            'data'    => [
                'api_key'          => $externalApi->api_key,
                'projection_limit' => $externalApi->projection_limit,
                'insite_limit'     => $externalApi->insite_limit,
                'start_date'       => $externalApi->start_date,
                'end_date'         => $externalApi->end_date,
                'is_active'        => !$isExpired,
                'expires_in_days'  => $isExpired ? 0 : now()->diffInDays($externalApi->end_date),
            ],
        ]);
    }

    public function index(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 10);

            $apis = ExternalApi::with('user')
                ->latest()
                ->paginate($perPage);

            $result = $apis->map(function ($api) {
                $isExpired = now()->gt($api->end_date);
                return [
                    'id'               => $api->id,
                    'user'             => [
                        'id'    => $api->user->id,
                        'name'  => $api->user->name,
                        'email' => $api->user->email,
                    ],
                    'api_key'          => $api->api_key,
                    'projection_limit' => $api->projection_limit,
                    'insite_limit'     => $api->insite_limit,
                    'start_date'       => $api->start_date,
                    'end_date'         => $api->end_date,
                    'is_active'        => !$isExpired,
                    'expires_in_days'  => $isExpired ? 0 : now()->diffInDays($api->end_date),
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

        $isExpired = now()->gt($externalApi->end_date);

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
                'projection_limit' => $externalApi->projection_limit,
                'insite_limit'     => $externalApi->insite_limit,
                'end_date'         => $externalApi->end_date,
                'expires_in_days'  => now()->diffInDays($externalApi->end_date),
            ],
        ]);
    }

    public function revealApiKey()
    {
        $user = auth()->user();
        $apiKey = DB::table('external_apis')->where('user_id', $user->id)->value('api_key');

        if (!$apiKey) {
            return response()->json(['success' => false, 'message' => 'No API Key found.'], 404);
        }

        return response()->json([
            'success' => true,
            'api_key' => $apiKey 
        ]);
    }

    public function getDeveloperProfile()
    {
        $user = auth()->user();
        
        $apiData = DB::table('external_apis')->where('user_id', $user->id)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'plan_name' => $user->plan ? $user->plan->name : 'Free Plan',
                'api_key' => $apiData ? 'bv_' . substr($apiData->api_key, 3, 6) . '...' . substr($apiData->api_key, -4) : null, 
                'usage_count' => $apiData ? (int)$apiData->usage_count : 0, 
            ]
        ]);
    }

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
}