<?php

namespace App\Http\Controllers\Supplyer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;

class SupplyerController extends Controller
{
    public function index()
    {
        try {
            $user = auth()->user();

            $totalProducts = Product::where('supplier_id', $user->id)->count();
            $activeProducts = Product::where('supplier_id', $user->id)
                                ->where('status', 'published')->count();
            $draftProducts = Product::where('supplier_id', $user->id)
                                ->where('status', 'draft')->count();
            
            $products = Product::where('supplier_id', $user->id)
                ->whereIn('status', ['published'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($product) {
                    return [
                        'id'            => $product->id,
                        'product_image' => $product->image ? asset('storage/' . $product->image) : null,
                        'product_name'  => $product->name,
                        'redirect_url'  => $product->redirect_url,
                        'status'        => ucfirst($product->status),
                        'price'         => '$' . number_format($product->price, 2),
                    ];
                });

            return response()->json([
                'success' => true,
                'stats' => [
                    'total_products'  => $totalProducts,
                    'active_products' => $activeProducts,
                    'draft_products'  => $draftProducts,
                    'total_orders'    => 342, 
                ],
                'recent_products' => $products
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // public function userIndex()
    // {
    //     try {
    //         $users = User::where('user_type', 'individual')
    //                     ->orderBy('created_at', 'desc')
    //                     ->get()
    //                     ->map(function ($user) {
    //                         return [
    //                             'id' => $user->id,
    //                             'name' => $user->name,
    //                             'email' => $user->email,
    //                             'user_type' => ucfirst($user->user_type),
    //                             'profile_image' => $user->image ? asset('storage/' . $user->image) : null,
    //                             'joined_at' => $user->created_at->format('Y-m-d'),
    //                             'target_goals' => $user->targetGoals()->where('is_active', true)->get()->map(function ($goal) {
    //                                 return [
    //                                     'id' => $goal->id,
    //                                     'target_weight' => $goal->target_weight,
    //                                     'weekly_workout_goal' => $goal->weekly_workout_goal,
    //                                     'daily_step_goal' => $goal->daily_step_goal,
    //                                     'sleep_target' => $goal->sleep_target,
    //                                     'water_target' => $goal->water_target,
    //                                     'supplement_recommendation' => $goal->supplement_recommendation,
    //                                     'start_date' => $goal->start_date ? $goal->start_date->format('Y-m-d') : null,
    //                                     'end_date' => $goal->end_date ? $goal->end_date->format('Y-m-d') : null,
    //                                 ];
    //                             }),
    //                         ];
    //                     });

    //         return response()->json([
    //             'success' => true,
    //             'data' => $users
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false, 
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }



    public function userIndex()
{
    try {
        $users = User::where('user_type', 'individual')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($user) {

                $goal = $user->targetGoals; // hasOne relation

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'user_type' => ucfirst($user->user_type),
                    'profile_image' => $user->image ? asset('storage/' . $user->image) : null,
                    'joined_at' => $user->created_at->format('Y-m-d'),

                    'target_goals' => $goal ? [
                        'id' => $goal->id,
                        'target_weight' => $goal->target_weight,
                        'weekly_workout_goal' => $goal->weekly_workout_goal,
                        'daily_step_goal' => $goal->daily_step_goal,
                        'sleep_target' => $goal->sleep_target,
                        'water_target' => $goal->water_target,
                        'supplement_recommendation' => $goal->supplement_recommendation,
                        'start_date' => optional($goal->start_date)->format('Y-m-d'),
                        'end_date' => optional($goal->end_date)->format('Y-m-d'),
                    ] : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $users
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

}
