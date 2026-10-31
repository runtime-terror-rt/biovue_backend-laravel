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
                'active_users'        => User::where('is_active', true)->count(),
                'total_subscriptions' => DB::table('plan_payments')->where('status', 'paid')->count(),
                'total_revenue'       => '$' . number_format(DB::table('plan_payments')->where('status', 'paid')->sum('amount'), 2),
                'monthly_revenue'     => '$' . number_format(DB::table('plan_payments')->where('status', 'paid')->whereMonth('created_at', now()->month)->sum('amount'), 2),
                'churn_rate'          => $dynamicChurnRate
            ];

            

            $months = collect(range(5, 0))->map(fn($i) => now()->subMonths($i)->format('M'));

            $userGroup = User::select(
                DB::raw('count(id) as count'), 
                DB::raw("DATE_FORMAT(created_at, '%b') as month"))
                ->where('created_at', '>=', now()->subMonths(6))
                ->groupBy('month')
                ->get()
                ->keyBy('month');

            $userGrowth = $months->map(function ($month) use ($userGroup) {
                return [
                    'month' => $month,
                    'count' => $userGroup->get($month)->count ?? 0,
                ];
            });

            $revenueGroup = DB::table('plan_payments')
                ->select(DB::raw('sum(amount) as amount'), DB::raw("DATE_FORMAT(created_at, '%b') as month"))
                ->where('status', 'paid')
                ->where('created_at', '>=', now()->subMonths(6))
                ->groupBy('month')
                ->get()
                ->keyBy('month');

            $revenueTrend = $months->map(function ($month) use ($revenueGroup) {
                return [
                    'month' => $month,
                    'amount' => (float)($revenueGroup->get($month)->amount ?? 0),
                ];
            });

            $planSummary = DB::table('plans')
                ->select(
                    'plans.name as plan_name',
                    'plans.plan_type',
                    DB::raw('COUNT(CASE WHEN plan_payments.status = "paid" THEN plan_payments.id END) as active_subscribers'),
                    DB::raw('CONCAT("$", FORMAT(COALESCE(SUM(CASE WHEN plan_payments.status = "paid" THEN plan_payments.amount ELSE 0 END), 0), 2)) as monthly_revenue')
                )
                ->leftJoin('plan_payments', 'plans.id', '=', 'plan_payments.plan_id')
                ->groupBy('plans.id', 'plans.name', 'plans.plan_type')
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