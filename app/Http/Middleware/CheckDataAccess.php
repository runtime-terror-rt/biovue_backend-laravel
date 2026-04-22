<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;

class CheckDataAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        $targetId = $request->route('userId') ?: $user->id;

        if ($user->user_type === 'admin' || $user->id == $targetId) {
            return $next($request);
        }

        $isConnected = DB::table('connect_user_proffesions')
            ->where('profession_id', $user->id)
            ->where('user_id', $targetId)
            ->exists();

        if (!$isConnected) {
            return response()->json(['success' => false, 'message' => 'Access Denied'], 403);
        }

        return $next($request);
    }
}
