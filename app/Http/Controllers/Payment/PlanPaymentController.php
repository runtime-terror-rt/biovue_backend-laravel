<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AdminNotification;
use App\Notifications\SubscriptionNotification;
use Illuminate\Http\Request;
use App\Models\PlanPayment;
use App\Models\Plan;
use App\Models\ProjectionCredit;
use Stripe\StripeClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PlanPaymentController extends Controller
{
    /**
     * List all payments (Admin View)
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 10);

            $payments = PlanPayment::with(['user', 'plan'])
                ->latest()
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'meta' => [
                    'current_page' => $payments->currentPage(),
                    'last_page'    => $payments->lastPage(),
                    'per_page'     => $payments->perPage(),
                    'total'        => $payments->total(),
                ],
                'data' => $payments->items(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show authenticated user's payments
     */
    public function show(Request $request)
    {
        $user = auth()->user();
        
        $payments = PlanPayment::with('plan')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'latest_payment'   => $payments->first(),
            'payment_history'  => $payments,
        ]);
    }

    /**
     * Process Payment & Initiate Stripe Session
     */
    public function paymentProcess(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing' => 'required|in:monthly,half_annual,annual,custom',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $user = auth()->user();

        // Price Calculation
        $finalPrice = $plan->price;
        if ($request->billing === 'annual') {
            $finalPrice = $plan->price * 12 * 0.9; // 10% Discount
        }

        try {
            $payment = PlanPayment::create([
                'user_id'        => $user->id,
                'plan_id'        => $plan->id,
                'transaction_id' => 'TEMP_' . uniqid(),
                'amount'         => $finalPrice,
                'currency'       => 'usd',
                'billing'        => $request->billing,
                'status'         => 'unpaid',
            ]);

            // Handle Free Plan
            if ($finalPrice <= 0) {
                $this->activateSubscription($payment, $user, $plan);
                return response()->json([
                    'success' => true,
                    'message' => 'Free plan activated successfully.',
                ]);
            }

            $stripe = new StripeClient(config('services.stripe.secret'));

            $session = $stripe->checkout->sessions->create([
                'mode' => 'subscription', // Change to 'subscription' if using Stripe Products/Prices for recurring
                'line_items' => [[
                    'price_data' => [
                        'currency'     => 'usd',
                        'unit_amount'  => (int)($finalPrice * 100),
                        'recurring'    => [
                            'interval' => $request->billing === 'annual' ? 'year' : 'month',
                        ],
                        'product_data' => [
                            'name' => $plan->name,
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'metadata' => [
                    'payment_id' => $payment->id,
                    'user_id'    => $user->id,
                ],
                'success_url' => 'https://biovuedigitalwellness.com/payment/show?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => url('/api/v1/payment/cancel'),
            ]);

            $payment->update(['transaction_id' => $session->id]);

            return response()->json([
                'success'      => true,
                'checkout_url' => $session->url,
                'session_id'   => $session->id,
                'amount'       => $finalPrice,
            ]);

        } catch (\Exception $e) {
            Log::error('Stripe Payment Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Helper to activate credits/plan
     */
    protected function activateSubscription($payment, $user, $plan, $subscriptionId = null)
    {
        $duration = 30;
        if ($payment->billing === 'annual') $duration = 365;
        if ($payment->billing === 'half_annual') $duration = 180;

        $payment->update([
            'status' => 'paid',
            'stripe_subscription_id' => $subscriptionId,
            'paid_at' => now(),
            'end_date' => now()->addDays($duration),
        ]);

        $user->update(['plan_id' => $plan->id]);

        ProjectionCredit::updateOrCreate(
            ['user_id' => $user->id],
            [
                'projection_limit' => $plan->projection_limit,
                'member_limit'     => $plan->member_limit,
                'updated_at'       => now(),
            ]
        );
    }

    /**
     * Stripe Webhook
     */

    public function handleStripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\Exception $e) {
            Log::error('Webhook Signature fail: ' . $e->getMessage());
            return response('Invalid signature', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            
            $paymentId = $session->metadata->payment_id ?? null;

            DB::beginTransaction();
            try {
                $payment = null;
                if ($paymentId) {
                    $payment = PlanPayment::with(['user', 'plan'])->find($paymentId);
                } else {
                    $payment = PlanPayment::with(['user', 'plan'])
                        ->where('transaction_id', $session->id)
                        ->first();
                }

                if ($payment && $payment->status !== 'paid') {
                    $subId = $session->subscription ?? $session->id;
                    
                    $this->activateSubscription($payment, $payment->user, $payment->plan, $subId);
                    
                    // Notifications
                    $admin = User::find(1);
                    if($admin) $admin->notify(new AdminNotification('New Subscription', "{$payment->user->name} onboarded", 'subscription'));
                    $payment->user->notify(new SubscriptionNotification('Success', 'Your subscription is active', 'subscription'));
                    
                    Log::info('Payment marked as paid for ID: ' . $payment->id);
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Webhook DB Error: ' . $e->getMessage());
                return response('Internal Error', 500);
            }
        }

        return response('Webhook Handled', 200);
    }

    public function cancelSubscription(Request $request)
    {
        $user = auth()->user();

        // 1. Professional User Restriction (6-Month Lock)
        if ($user->user_type === 'professional') {
            $minDate = $user->created_at->addMonths(6);
            if (now()->lt($minDate)) {
                return response()->json([
                    'success' => false,
                    'message' => "Professional users can only cancel after " . $minDate->format('d M, Y')
                ], 403);
            }
        }

        // 2. Find the active payment record
        $payment = PlanPayment::where('user_id', $user->id)
            ->where('status', 'paid')
            ->whereNotNull('stripe_subscription_id')
            ->latest()
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false, 
                'message' => 'No active subscription found.'
            ], 404);
        }

        try {
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

            /** * IMMEDIATE CANCELLATION
             * By calling cancel(), the subscription ends immediately.
             * Stripe will not automatically refund the remaining days.
             */
            $stripe->subscriptions->cancel($payment->stripe_subscription_id);

            // Optional: Update your local database status immediately
            $payment->update(['status' => 'cancelled']); 
            $user->update(['plan_id' => null]); // Remove plan access

            return response()->json([
                'success' => true,
                'message' => 'Your subscription has been cancelled immediately. No refunds will be issued for the remaining period.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getCustomerPortal(Request $request) {
        $user = auth()->user();
        $stripe = new StripeClient(config('services.stripe.secret'));

        $session = $stripe->billingPortal->sessions->create([
            'customer' => $user->stripe_id, // User model-e stripe_id thakte hobe
            'return_url' => url('/user-dashboard'),
        ]);

        return response()->json(['url' => $session->url]);
    }
}