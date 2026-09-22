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
        $users = User::with('targetGoals')
            ->where('user_type', 'individual')
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

    public function findMatchSupplements(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $user = User::with(['targetGoals', 'profile'])->findOrFail($request->user_id);
            $recommended = $user->targetGoals?->supplement_recommendation ?? [];

            if (is_string($recommended)) {
                $recommended = json_decode($recommended, true) ?? [];
            }

            $supplierId = auth()->id();
            $matches = [];

            foreach ((array)$recommended as $rec) {
                $keyword = is_array($rec) ? ($rec['name'] ?? $rec['title'] ?? '') : (string)$rec;
                $keyword = trim($keyword);
                if (empty($keyword)) continue;

                $products = Product::where('status', 'published')
                    ->where(function ($q) use ($supplierId, $keyword) {
                        $q->where('name', 'LIKE', "%{$keyword}%")
                          ->orWhere('description', 'LIKE', "%{$keyword}%")
                          ->orWhere('category', 'LIKE', "%{$keyword}%");
                    })
                    ->get()
                    ->map(function ($p) {
                        return [
                            'id'            => $p->id,
                            'name'          => $p->name,
                            'price'         => '$' . number_format($p->price, 2),
                            'redirect_url'  => $p->redirect_url,
                            'image'         => $p->image ? (str_starts_with($p->image, 'http') ? $p->image : asset('storage/' . $p->image)) : null,
                        ];
                    });

                $matches[] = [
                    'recommended_supplement' => $keyword,
                    'matched_products_count' => $products->count(),
                    'products'               => $products,
                ];
            }

            return response()->json([
                'success' => true,
                'client'  => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                ],
                'matches' => $matches,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function createWalkInClient(Request $request)
    {
        $validated = $request->validate([
            'name'                      => 'required|string|max:255',
            'email'                     => 'required|email|max:255',
            'phone'                     => 'nullable|string|max:30',
            'unit'                      => 'nullable|string',
            'height'                    => 'nullable|numeric',
            'weight'                    => 'nullable|numeric',
            'age'                       => 'nullable|integer',
            'sex'                       => 'nullable|string',
            'body_goal'                 => 'nullable|string',
            'target_weight'             => 'nullable|numeric',
            'daily_step_goal'           => 'nullable|integer',
            'is_athletic'               => 'nullable|boolean',
            'toned'                     => 'nullable|boolean',
            'lean'                      => 'nullable|boolean',
            'muscular'                  => 'nullable|boolean',
            'curvy_fit'                 => 'nullable|boolean',
            'supplement_recommendation' => 'nullable|array',
        ]);

        try {
            \DB::beginTransaction();

            $user = User::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name'           => $validated['name'],
                    'password'       => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(12)),
                    'user_type'      => 'individual',
                    'terms_accepted' => true,
                    'status'         => 'active',
                ]
            );

            \App\Models\UserProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'unit'        => $validated['unit'] ?? 'imperial',
                    'height'      => $validated['height'] ?? null,
                    'weight'      => $validated['weight'] ?? null,
                    'age'         => $validated['age'] ?? null,
                    'sex'         => $validated['sex'] ?? null,
                    'is_athletic' => $validated['is_athletic'] ?? false,
                    'toned'       => $validated['toned'] ?? false,
                    'lean'        => $validated['lean'] ?? false,
                    'muscular'    => $validated['muscular'] ?? false,
                    'curvy_fit'   => $validated['curvy_fit'] ?? false,
                    'notes'       => $validated['body_goal'] ?? null,
                ]
            );

            if (!empty($validated['target_weight']) || !empty($validated['supplement_recommendation'])) {
                \App\Models\TargetGoal::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'profession_id'             => auth()->id(),
                        'target_weight'             => $validated['target_weight'] ?? null,
                        'daily_step_goal'           => $validated['daily_step_goal'] ?? 10000,
                        'supplement_recommendation' => $validated['supplement_recommendation'] ?? [],
                        'is_active'                 => true,
                    ]
                );
            }

            \DB::table('connect_user_proffesions')->updateOrInsert(
                [
                    'profession_id' => auth()->id(),
                    'user_id'       => $user->id,
                ],
                [
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]
            );

            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Walk-in client profile created successfully.',
                'data'    => $user->load(['profile', 'targetGoals']),
            ], 201);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function notifyClient(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        try {
            $client = User::findOrFail($request->user_id);

            \Illuminate\Support\Facades\Mail::raw($request->message, function ($mail) use ($client, $request) {
                $mail->to($client->email)
                     ->subject($request->subject);
            });

            return response()->json([
                'success' => true,
                'message' => "Notification email sent to client {$client->email}.",
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

}
