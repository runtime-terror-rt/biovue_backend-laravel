<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function getAdminStats(Request $request)
    {
        try {
            

            $lastMonthStart = now()->subMonth()->startOfMonth();
            $totalUsersAtStart = DB::table('plan_payments')->where('status', 'paid')->where('created_at', '<', $lastMonthStart)->count();
            $churnedUsers = DB::table('plan_payments')->whereIn('status', ['refunded', 'failed'])->whereBetween('updated_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])->count();
            $dynamicChurnRate = $totalUsersAtStart > 0 ? number_format(($churnedUsers / $totalUsersAtStart) * 100, 1) . '%' : '0%';

            $stats = [
                'total_signups'       => User::count(),
                'active_users'        => User::where('status', true)->count(),
                'total_subscriptions' => DB::table('plan_payments')->where('status', 'paid')->count(),
                'total_revenue'       => '$' . number_format(DB::table('plan_payments')->where('status', 'paid')->sum('amount'), 2),
                'monthly_revenue'     => '$' . number_format(DB::table('plan_payments')->where('status', 'paid')->whereMonth('created_at', now()->month)->sum('amount'), 2),
                'churn_rate'          => $dynamicChurnRate
            ];

            

            $userGrowth = User::select(
                DB::raw('count(id) as count'), 
                DB::raw("DATE_FORMAT(created_at, '%b') as month"))
                ->where('created_at', '>=', now()->subMonths(6))
                ->groupBy('month')
                ->orderBy(DB::raw("MIN(created_at)"), 'ASC')
                ->get();

            $revenueTrend = DB::table('plan_payments')->select(DB::raw('sum(amount) as amount'), DB::raw("DATE_FORMAT(created_at, '%b') as month"))
                ->where('status', 'paid')->where('created_at', '>=', now()->subMonths(6))
                ->groupBy('month')->orderBy(DB::raw("MIN(created_at)"), 'ASC')->get();

            $planSummary = DB::table('plan_payments')
                ->join('users', 'plan_payments.user_id', '=', 'users.id')
                ->select('users.user_type', 
                    DB::raw('count(plan_payments.id) as active_subscribers'), 
                    DB::raw('sum(plan_payments.amount) as monthly_revenue'))
                ->where('plan_payments.status', 'paid')
                ->groupBy('users.user_type')
                ->get();

            return response()->json([
                'success' => true,
                'overview' => $stats,
                'charts' => [
                    'user_growth' => $userGrowth,
                    'revenue_trend' => $revenueTrend
                ],
                'plan_summary' => $planSummary,
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Internal Server Error', 'error' => $e->getMessage()], 500);
        }
    }
    

}