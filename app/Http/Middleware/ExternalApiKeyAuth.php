<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ExternalApi;
use Symfony\Component\HttpFoundation\Response;

class ExternalApiKeyAuth
{
    /**
     * Handle an incoming request.
     *
     * Supports X-API-KEY header or Bearer bv_... token for external businesses.
     * If neither is present, passes through cleanly to default auth guards.
     */
    public function handle(Request $request, Closure $next): Response
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

        if ($apiKey) {
            $externalApi = ExternalApi::where('api_key', $apiKey)->first();

            if (!$externalApi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid API Key provided.'
                ], 401);
            }

            if ($externalApi->isExpired()) {
                return response()->json([
                    'success' => false,
                    'message' => 'API Key has expired. Please renew your subscription.'
                ], 403);
            }

            $user = $externalApi->user;
            if ($user) {
                // Authenticate user for the current request
                auth()->setUser($user);
                try {
                    auth('sanctum')->setUser($user);
                } catch (\Throwable $e) {
                    // Safe fallback
                }
                $request->setUserResolver(fn () => $user);
            }

            $request->attributes->set('external_api', $externalApi);
        }

        return $next($request);
    }
}
