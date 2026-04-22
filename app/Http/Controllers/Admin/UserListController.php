<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserListController extends Controller
{
    public function getUser(Request $request)
    {
        try {
            if (!$request->user()->hasRole('admin')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $latestPaymentSub = DB::table('plan_payments')
                ->select('user_id', 'status as subscription')
                ->whereIn('id', function ($query) {
                    $query->select(DB::raw('MAX(id)'))
                        ->from('plan_payments')
                        ->groupBy('user_id');
                });
            
            $users = User::select(
                    'users.id', 
                    'users.name', 
                    'users.email', 
                    'users.user_type', 
                    'users.status as account_status',
                    'users.created_at as joined_date', 
                    'user_profiles.image as profile_image',
                    'latest_payments.subscription'
                )
                ->leftJoin('user_profiles', 'users.id', '=', 'user_profiles.user_id')
                ->leftJoinSub($latestPaymentSub, 'latest_payments', function ($join) {
                    $join->on('users.id', '=', 'latest_payments.user_id');
                })
                ->latest('users.created_at')
                ->get();

            $users->transform(function ($user) {
                $user->profile_image = $user->profile_image 
                    ? asset('storage/' . $user->profile_image) 
                    : asset('assets/default-avatar.png');

                $user->subscription = $user->subscription ?? 'No Plan';
                
                $user->account_status = $user->account_status ? 'Active' : 'Inactive';
                
                return $user;
            });

            return response()->json([
                'success' => true,
                'users_table' => $users
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Internal Server Error', 
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUserById(Request $request, $id)
    {
        try {
            if (!$request->user()->hasRole('admin')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $user = User::with(['profile', 'planPayments' => function($query) {
                $query->where('status', 'paid')->latest()->limit(1);
            }])->find($id);

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }

            $user->profile_image = $user->profile && $user->profile->image 
                ? asset('storage/' . $user->profile->image) 
                : asset('assets/default-avatar.png');

            $latestPayment = $user->planPayments->first();
            $user->subscription_status = $latestPayment ? 'Active' : 'Free Trial';

            $user->account_status_text = $user->status ? 'Active' : 'Inactive';

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'user_type' => ucfirst($user->user_type),
                    'status' => $user->account_status_text,
                    'subscription' => $user->subscription_status,
                    'member_since' => $user->created_at->format('Y-m-d'),
                    'profile_image' => $user->profile_image
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Internal Server Error', 
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            if (!$request->user()->hasRole('admin')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $user = User::find($id);
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }

            $user->delete();

            return response()->json(['success' => true, 'message' => 'User deleted successfully'], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Internal Server Error', 'error' => $e->getMessage()], 500);
        }
    }
}
