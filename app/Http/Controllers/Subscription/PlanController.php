<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Stripe\StripeClient;
use App\Models\User;
use App\Mail\PlanUpdatedMail;
use Illuminate\Support\Facades\Mail;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;



class PlanController extends Controller
{
    /**
     * List plans (with optional type & billing filter)
     */
    public function index(Request $request)
    {
        try {
            $type = $request->query('type'); 
            $billing = strtolower($request->query('billing', 'monthly'));

            $query = Plan::query(); 

            if ($type && in_array($type, ['individual', 'professional'])) {
                $query->where('plan_type', $type);
            }

            $plans = $query->latest()->get();

            $data = $plans->map(function ($plan) use ($billing) {
                return [
                    'id'             => $plan->id,
                    'name'           => $plan->name,
                    'plan_type'      => $plan->plan_type,
                    'billing_cycle'  => $plan->billing_cycle,
                    'duration'       => $plan->duration,
                    'member_limit'   => $plan->member_limit,
                    'features'       => $plan->features,
                    'status'         => $plan->status,
                    'price'          => $billing === 'annual' ? $plan->annual_price : $plan->price,
                    'projection_limit' => $plan->projection_limit,
                    'status_label'     => $plan->status ? 'Active' : 'Inactive',
                ];
            });

            return response()->json([
                'success' => true,
                'count'   => $data->count(),
                'data'    => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch all plans: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function storeOrUpdatePlan(Request $request)
    {
        $request->validate([
            'id'               => 'nullable|integer|exists:plans,id',
            'name'             => 'required|string|max:255',
            'plan_type'        => 'required|in:individual,professional',
            'billing_cycle'    => 'required|in:days,monthly,half_annual,annual,custom',
            'price'            => 'required|numeric|min:0',
            'duration'         => 'nullable|integer',
            'member_limit'     => 'nullable|integer',
            'features'         => 'nullable|array',
            'status'           => 'boolean',
            'projection_limit' => 'nullable|integer'
        ]);

        try {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $planId = $request->id;
            $existingPlan = $planId ? Plan::find($planId) : null;

            $stripePriceId = $existingPlan ? $existingPlan->stripe_price_id : null;

            if (!$existingPlan || (float)$existingPlan->price != (float)$request->price) {
                
                if ($existingPlan && $existingPlan->stripe_product_id) {
                    $stripeProduct = $stripe->products->update($existingPlan->stripe_product_id, [
                        'name' => $request->name
                    ]);
                    $stripeProductId = $stripeProduct->id;
                } else {
                    $stripeProduct = $stripe->products->create([
                        'name' => $request->name,
                    ]);
                    $stripeProductId = $stripeProduct->id;
                }

                if ((float)$request->price > 0) {
                    $interval = $request->billing_cycle === 'annual' ? 'year' : 'month';
                    
                    $stripePrice = $stripe->prices->create([
                        'unit_amount' => (int)($request->price * 100),
                        'currency'    => 'usd',
                        'recurring'   => ['interval' => $interval],
                        'product'     => $stripeProductId,
                    ]);
                    $stripePriceId = $stripePrice->id;
                }
            } else {
                $stripeProductId = $existingPlan->stripe_product_id;
            }

            $plan = Plan::updateOrCreate(
                ['id' => $planId],
                [
                    'name'               => $request->name,
                    'plan_type'          => $request->plan_type,
                    'user_id'            => $request->user()->id,
                    'billing_cycle'      => $request->billing_cycle,
                    'price'              => $request->price,
                    'duration'           => $request->duration,
                    'member_limit'       => $request->plan_type === 'professional' ? $request->member_limit : null,
                    'features'           => $request->features,
                    'status'             => $request->status ?? true,
                    'projection_limit'   => $request->projection_limit ?? null,
                    'stripe_product_id'  => $stripeProductId ?? null,
                    'stripe_price_id'    => $stripePriceId ?? null,
                ]
            );

            $message = $request->filled('id') ? 'Plan updated successfully in DB & Stripe.' : 'Plan created successfully in DB & Stripe.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $plan
            ], $request->filled('id') ? 200 : 201);

        } catch (\Exception $e) {
            \Log::error('Plan Sync Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show single plan
     */
    public function show(Request $request, $id)
    {
        try {
            $plan = Plan::findOrFail($id);

            $billing = strtolower($request->query('billing', 'monthly'));
            if (!in_array($billing, ['monthly', 'annual'])) {
                $billing = 'monthly';
            }

            $data = [
                'id'            => $plan->id,
                'name'          => $plan->name,
                'plan_type'     => $plan->plan_type,
                'billing_cycle' => $plan->billing_cycle,
                'duration'      => $plan->duration,
                'member_limit'  => $plan->member_limit,
                'features'      => $plan->features,
                'status'        => (bool) $plan->status,
                'price'         => $billing === 'annual' ? $plan->annual_price : $plan->price,
            ];

            return response()->json([
                'success' => true,
                'data'    => $data
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch plan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function toggleStatus($id)
    {
        try {
            $plan = Plan::findOrFail($id);
            $plan->status = !$plan->status;
            $plan->save();

            if ($plan->status == false) {
                $users = User::where('plan_id', $id)->get();

                foreach ($users as $user) {
                    Mail::to($user->email)->send(new PlanUpdatedMail($user, $id));
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Plan status updated successfully',
                'data' => [
                    'id'     => $plan->id,
                    'name'   => $plan->name,
                    'status' => $plan->status ? 'Active' : 'Inactive'
                ]
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update plan status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete plan
     */
    public function destroy($id)
    {
        // Fetch the plan with counts of related users and payments
        $plan = Plan::withCount(['users', 'payments'])->find($id);

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found'
            ], 404);
        }

        // Check if any user is currently assigned to this plan
        if ($plan->users_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete: This plan is currently being used by active users.'
            ], 422);
        }

        // Check if there is any payment history linked to this plan
        if ($plan->payments_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete: This plan has existing payment records (Audit Trail).'
            ], 422);
        }

        $plan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Plan deleted successfully'
        ], 200);
    }
}