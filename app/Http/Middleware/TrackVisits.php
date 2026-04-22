<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrackVisits
{
    public function handle(Request $request, Closure $next)
    {
        $platform = $request->header('X-Platform');

        if (!$platform) {
            $userAgent = strtolower($request->header('User-Agent'));
            if (str_contains($userAgent, 'okhttp') || str_contains($userAgent, 'dart') || str_contains($userAgent, 'android') || str_contains($userAgent, 'iphone')) {
                $platform = 'app';
            } else {
                $platform = 'web';
            }
        }

        \DB::table('site_visits')->insert([
            'platform' => $platform,
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return $next($request);
    }
}
