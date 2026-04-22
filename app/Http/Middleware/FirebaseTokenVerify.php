<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kreait\Firebase\Auth as FirebaseAuth;
use Kreait\Laravel\Firebase\Facades\Firebase;

class FirebaseTokenVerify
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Firebase token not found'
            ], 401);
        }

        try {
            $verifiedToken = Firebase::auth()->verifyIdToken($token);

            // You can save uid or email
            $request->merge([
                'firebase_uid' => $verifiedToken->claims()->get('sub')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Firebase token'
            ], 401);
        }

        return $next($request);
    }
}
