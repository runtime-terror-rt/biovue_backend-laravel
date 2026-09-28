<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ExternalApi;
use App\Notifications\AdminNotification;
use App\Notifications\SubscriptionNotification;
use Illuminate\Http\Request;
use App\Models\PlanPayment;
use App\Models\Plan;
use App\Models\ProjectionCredit;
use Stripe\StripeClient;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PlanPaymentController extends Controller
{

    public function index(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 10);

            $payments = PlanPayment::with(['user', 'plan'])
                ->latest()
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'meta'    => [
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

    public function show(Request $request)
    {
        $user = auth()->user();

        $payments = PlanPayment::with(['plan', 'targetPlan'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $latestPayment = $payments->first();
        $userPlan = $user->plan;

        $activeStripeSub = \App\Models\Subscription::where('user_id', $user->id)
            ->whereIn('stripe_status', ['trialing', 'active'])
            ->latest()
            ->first();

        $isTrialing = ($activeStripeSub && $activeStripeSub->stripe_status === 'trialing')
            || ($latestPayment && $latestPayment->status === 'trialing')
            || ($latestPayment && $latestPayment->is_trial && $latestPayment->trial_ends_at && $latestPayment->trial_ends_at->isFuture());

        $currentPlanName = $userPlan ? $userPlan->name : 'Free Trial';
        $planNameLower = strtolower($currentPlanName);
        $isPremium = str_contains($planNameLower, 'premium');
        $isPlus = str_contains($planNameLower, 'plus');
        $isFree = $isTrialing || !$userPlan || str_contains($planNameLower, 'free');

        // Target plan that will be charged after trial
        $targetPlan = $latestPayment?->targetPlan;
        $targetPlanName = $targetPlan ? $targetPlan->name : null;

        // Calculate trial expiration
        if ($isTrialing && $activeStripeSub?->trial_ends_at) {
            $trialExpiresAt = $activeStripeSub->trial_ends_at;
        } elseif ($isTrialing && $latestPayment?->trial_ends_at) {
            $trialExpiresAt = $latestPayment->trial_ends_at;
        } else {
            $joinedAt = $user->created_at ?? now();
            $trialExpiresAt = $joinedAt->copy()->addDays(7);
        }

        $trialDaysLeft = max(0, (int) ceil(now()->diffInSeconds($trialExpiresAt, false) / 86400));
        $cancelRequested = $latestPayment && in_array($latestPayment->status, ['pending_cancellation', 'cancelled']);

        return response()->json([
            'success'         => true,
            'user'            => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'subscription_details' => [
                'current_plan'            => $isTrialing ? 'Free Trial (7-Day Trial)' : $currentPlanName,
                'is_premium'              => $isPremium && !$isTrialing,
                'is_plus'                 => $isPlus && !$isTrialing,
                'is_free_trial'           => $isFree,
                'is_in_stripe_trial'      => $isTrialing,
                'target_plan_after_trial' => $targetPlanName,
                'auto_bills_plan'         => $targetPlanName,
                'auto_bills_at'           => $isTrialing ? $trialExpiresAt->format('d M, Y') : null,
                'auto_bills_amount'       => $latestPayment?->amount,
                'can_upgrade'             => !$isPremium || $isTrialing,
                'show_upgrade_btn'        => !$isPremium || $isTrialing,
                'cancel_requested'        => $cancelRequested,
                'show_expire_button'      => $cancelRequested,
                'trial_days_left'         => $isFree ? $trialDaysLeft : 0,
                'trial_ends_at'           => $isFree ? $trialExpiresAt->format('Y-m-d H:i:s') : null,
                'access_until'            => $latestPayment?->end_date 
                    ? \Carbon\Carbon::parse($latestPayment->end_date)->format('d M, Y') 
                    : ($isFree ? $trialExpiresAt->format('d M, Y') : null),
            ],
            'latest_payment'  => $latestPayment,
            'payment_history' => $payments,
        ]);
    }

    public function paymentProcess(Request $request)
    {
        $request->validate([
            'plan_id'          => 'required|exists:plans,id',
            'target_plan_id'   => 'nullable|exists:plans,id',
            'is_trial'         => 'nullable|boolean',
            'billing'          => 'required|in:monthly,half_annual,annual,custom',
            'meter_id'         => 'nullable|string',
            'meter_event_name' => 'nullable|string',
        ]);

        $selectedPlan = Plan::findOrFail($request->plan_id);
        $user = auth()->user();

        // Check if starting a 7-day trial:
        // Either explicitly requested via is_trial=true, or user clicked the Free Trial plan
        $isTrial = $request->boolean('is_trial')
            || strtolower($selectedPlan->name) === 'free trial'
            || $selectedPlan->price <= 0;

        if ($isTrial) {
            // Find target paid plan to be charged after 7 days
            if ($request->filled('target_plan_id')) {
                $targetPlan = Plan::findOrFail($request->target_plan_id);
            } elseif ($selectedPlan->price > 0) {
                $targetPlan = $selectedPlan;
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a paid plan (e.g. Plus or Premium) to auto-bill after your 7-day free trial.',
                ], 422);
            }

            if ($targetPlan->price <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected plan for auto-billing after trial must be a paid plan (e.g. Plus or Premium).',
                ], 422);
            }

            $freeTrialPlan = Plan::where('price', 0)->orWhere('name', 'Free Trial')->first() ?? $selectedPlan;

            [$finalPrice, $interval, $duration] = $this->resolvePriceAndInterval($targetPlan, $request->billing);

            try {
                $payment = PlanPayment::create([
                    'user_id'        => $user->id,
                    'plan_id'        => $freeTrialPlan->id,
                    'target_plan_id' => $targetPlan->id,
                    'transaction_id' => 'TEMP_' . uniqid(),
                    'amount'         => $finalPrice,
                    'currency'       => 'usd',
                    'billing'        => $request->billing,
                    'status'         => 'unpaid',
                    'is_trial'       => true,
                ]);

                $stripe = new StripeClient(config('services.stripe.secret'));

                $productName = $targetPlan->name . ' (7-Day Free Trial)';
                if ($targetPlan->plan_type === 'professional') {
                    $productName .= ' (Monthly Installment)';
                } elseif ($targetPlan->plan_type === 'api') {
                    $productName .= ' (API Access)';
                }

                $session = $stripe->checkout->sessions->create([
                    'mode'       => 'subscription',
                    'payment_method_types' => ['card'],
                    'line_items' => [[
                        'price_data' => [
                            'currency'     => 'usd',
                            'unit_amount'  => (int) ($finalPrice * 100),
                            'recurring'    => ['interval' => $interval],
                            'product_data' => [
                                'name'        => $productName,
                                'description' => "7 days free trial, then \${$finalPrice}/" . ($interval === 'year' ? 'year' : 'month') . ". Auto-bills unless cancelled.",
                            ],
                        ],
                        'quantity' => 1,
                    ]],
                    'subscription_data' => [
                        'trial_period_days' => 7,
                        'metadata' => [
                            'payment_id'     => (string) $payment->id,
                            'user_id'        => (string) $user->id,
                            'target_plan_id' => (string) $targetPlan->id,
                            'plan_id'        => (string) $freeTrialPlan->id,
                            'is_trial'       => 'true',
                        ],
                    ],
                    'metadata' => [
                        'payment_id'       => (string) $payment->id,
                        'user_id'          => (string) $user->id,
                        'plan_id'          => (string) $freeTrialPlan->id,
                        'target_plan_id'   => (string) $targetPlan->id,
                        'plan_type'        => $targetPlan->plan_type,
                        'duration_days'    => (string) $duration,
                        'is_trial'         => 'true',
                        'meter_id'         => $request->meter_id ?? null,
                        'meter_event_name' => $request->meter_event_name ?? null,
                    ],
                    'success_url' => 'https://biovuedigitalwellness.com/payment/show?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url'  => url('/api/v1/payment/cancel'),
                ]);

                $payment->update([
                    'transaction_id'    => $session->id,
                    'stripe_session_id' => $session->id,
                ]);

                return response()->json([
                    'success'      => true,
                    'checkout_url' => $session->url,
                    'session_id'   => $session->id,
                    'amount'       => $finalPrice,
                    'is_trial'     => true,
                    'trial_days'   => 7,
                    'target_plan'  => $targetPlan->name,
                    'billing_type' => $interval === 'year' ? 'Annual' : 'Monthly',
                    'message'      => "Please provide card details to start your 7-day free trial. You will auto-bill \${$finalPrice} for {$targetPlan->name} after 7 days.",
                ]);

            } catch (\Exception $e) {
                Log::error('Stripe Trial Payment Error: ' . $e->getMessage());
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        }

        // Standard Immediate Paid Subscription (without trial)
        [$finalPrice, $interval, $duration] = $this->resolvePriceAndInterval($selectedPlan, $request->billing);

        try {
            $payment = PlanPayment::create([
                'user_id'        => $user->id,
                'plan_id'        => $selectedPlan->id,
                'transaction_id' => 'TEMP_' . uniqid(),
                'amount'         => $finalPrice,
                'currency'       => 'usd',
                'billing'        => $request->billing,
                'status'         => 'unpaid',
                'is_trial'       => false,
            ]);

            $stripe = new StripeClient(config('services.stripe.secret'));

            $productName = $selectedPlan->name;
            if ($selectedPlan->plan_type === 'professional') {
                $productName .= ' (Monthly Installment)';
            } elseif ($selectedPlan->plan_type === 'api') {
                $productName .= ' (API Access)';
            }

            $session = $stripe->checkout->sessions->create([
                'mode'       => 'subscription',
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency'     => 'usd',
                        'unit_amount'  => (int) ($finalPrice * 100),
                        'recurring'    => ['interval' => $interval],
                        'product_data' => ['name' => $productName],
                    ],
                    'quantity' => 1,
                ]],
                'metadata' => [
                    'payment_id'       => (string) $payment->id,
                    'user_id'          => (string) $user->id,
                    'plan_id'          => (string) $selectedPlan->id,
                    'plan_type'        => $selectedPlan->plan_type,
                    'duration_days'    => (string) $duration,
                    'is_trial'         => 'false',
                    'meter_id'         => $request->meter_id ?? null,
                    'meter_event_name' => $request->meter_event_name ?? null,
                ],
                'success_url' => 'https://biovuedigitalwellness.com/payment/show?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => url('/api/v1/payment/cancel'),
            ]);

            $payment->update([
                'transaction_id'    => $session->id,
                'stripe_session_id' => $session->id,
            ]);

            return response()->json([
                'success'      => true,
                'checkout_url' => $session->url,
                'session_id'   => $session->id,
                'amount'       => $finalPrice,
                'is_trial'     => false,
                'billing_type' => $interval === 'year' ? 'Annual' : 'Monthly',
            ]);

        } catch (\Exception $e) {
            Log::error('Stripe Payment Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function handleStripeWebhook(Request $request)
    {
        $payload        = $request->getContent();
        $sigHeader      = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\Exception $e) {
            Log::error('Webhook Signature fail: ' . $e->getMessage());
            return response('Invalid signature', 400);
        }

        $stripe = new StripeClient(config('services.stripe.secret'));

        // =========================================================
        // 1. CHECKOUT SESSION COMPLETED (Initial Card Setup / Trial / Direct Purchase)
        // =========================================================
        if ($event->type === 'checkout.session.completed') {
            $session   = $event->data->object;
            $paymentId = $session->metadata->payment_id ?? null;

            DB::beginTransaction();
            try {
                $payment = $paymentId
                    ? PlanPayment::with(['user', 'plan'])->find($paymentId)
                    : PlanPayment::with(['user', 'plan'])->where('transaction_id', $session->id)->first();

                if (!$payment || in_array($payment->status, ['paid', 'trialing'])) {
                    DB::commit();
                    return response('Already handled', 200);
                }

                $subId = $session->subscription ?? null;
                if (!$subId) {
                    DB::commit();
                    return response('No subscription in session', 200);
                }

                $stripeSub = $stripe->subscriptions->retrieve($subId);

                $trialEnds = $stripeSub->trial_end
                    ? \Carbon\Carbon::createFromTimestamp($stripeSub->trial_end)
                    : null;

                $endsAt = $stripeSub->current_period_end
                    ? \Carbon\Carbon::createFromTimestamp($stripeSub->current_period_end)
                    : null;

                $subscription = \App\Models\Subscription::updateOrCreate(
                    ['stripe_id' => $subId],
                    [
                        'user_id'       => $payment->user_id,
                        'type'          => 'default',
                        'stripe_status' => $stripeSub->status,
                        'stripe_price'  => $payment->amount,
                        'quantity'      => 1,
                        'trial_ends_at' => $trialEnds,
                        'ends_at'       => $endsAt,
                    ]
                );

                \App\Models\SubscriptionItem::updateOrCreate(
                    ['subscription_id' => $subscription->id],
                    [
                        'stripe_id'        => $subId,
                        'stripe_product'   => $payment->plan->name ?? 'N/A',
                        'stripe_price'     => $payment->amount,
                        'quantity'         => 1,
                        'meter_id'         => $stripeSub->metadata->meter_id ?? null,
                        'meter_event_name' => $stripeSub->metadata->meter_event_name ?? null,
                    ]
                );

                if ($stripeSub->status === 'trialing') {
                    // ===================================================
                    // 7-DAY FREE TRIAL ACTIVATION
                    // ===================================================
                    $targetPlanId = $session->metadata->target_plan_id ?? $payment->target_plan_id;
                    $targetPlan = $targetPlanId ? Plan::find($targetPlanId) : null;
                    $freeTrialPlan = Plan::where('price', 0)->orWhere('name', 'Free Trial')->first();

                    $payment->update([
                        'status'                 => 'trialing',
                        'stripe_subscription_id' => $subId,
                        'is_trial'               => true,
                        'target_plan_id'         => $targetPlanId,
                        'trial_ends_at'          => $trialEnds,
                        'start_date'             => now(),
                        'end_date'               => $trialEnds,
                    ]);

                    // Assign Free Trial plan & features during the trial period
                    if ($freeTrialPlan) {
                        $payment->user->update(['plan_id' => $freeTrialPlan->id]);

                        ProjectionCredit::updateOrCreate(
                            ['user_id' => $payment->user_id],
                            [
                                'projection_limit' => $freeTrialPlan->projection_limit ?? 1,
                                'member_limit'     => $freeTrialPlan->member_limit,
                                'expiry_date'      => $trialEnds,
                                'updated_at'       => now(),
                            ]
                        );
                    }

                    $admin = User::find(1);
                    if ($admin) {
                        $admin->notify(new AdminNotification(
                            'New Trial Started',
                            "{$payment->user->name} started 7-day trial with card on file (Target: " . ($targetPlan?->name ?? 'Paid Plan') . ")",
                            'subscription'
                        ));
                    }

                    $targetName = $targetPlan ? $targetPlan->name : 'selected plan';
                    $payment->user->notify(new SubscriptionNotification(
                        'Free Trial Started',
                        "Your 7-day free trial is now active! Auto-billing for {$targetName} will occur on " . ($trialEnds ? $trialEnds->format('d M, Y') : 'in 7 days') . ".",
                        'subscription'
                    ));

                    Log::info("7-day trial started for User {$payment->user_id}, Payment ID {$payment->id}");

                } else {
                    // ===================================================
                    // IMMEDIATE ACTIVE SUBSCRIPTION
                    // ===================================================
                    $duration = (int) ($session->metadata->duration_days ?? 30);
                    $this->activateSubscription($payment, $payment->user, $payment->plan, $subId, $duration);

                    if ($payment->plan->plan_type === 'api') {
                        $this->storeExternalApi($payment->user, $payment->plan, $duration, $subId);
                    }

                    $admin = User::find(1);
                    if ($admin) {
                        $admin->notify(new AdminNotification(
                            'New Subscription',
                            "{$payment->user->name} onboarded",
                            'subscription'
                        ));
                    }
                    $payment->user->notify(new SubscriptionNotification(
                        'Success',
                        'Your subscription is active',
                        'subscription'
                    ));
                }

                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Webhook DB Error (checkout.session.completed): ' . $e->getMessage());
                return response('Internal Error', 500);
            }
        }

        // =========================================================
        // 2. INVOICE PAID (Auto-payment after 7-day trial or Recurring Renewal)
        // =========================================================
        elseif ($event->type === 'invoice.paid') {
            $invoice = $event->data->object;
            $subId   = $invoice->subscription ?? null;

            if ($subId && $invoice->amount_paid > 0) {
                DB::beginTransaction();
                try {
                    $stripeSub = $stripe->subscriptions->retrieve($subId);
                    $subscription = \App\Models\Subscription::where('stripe_id', $subId)->first();
                    $payment = PlanPayment::with(['user', 'plan', 'targetPlan'])
                        ->where('stripe_subscription_id', $subId)
                        ->latest()
                        ->first();

                    if ($payment && $payment->user) {
                        $targetPlanId = $payment->target_plan_id ?? $payment->plan_id;
                        $targetPlan = Plan::find($targetPlanId) ?? $payment->plan;

                        $periodEnd = $stripeSub->current_period_end
                            ? \Carbon\Carbon::createFromTimestamp($stripeSub->current_period_end)
                            : now()->addDays(30);

                        if ($subscription) {
                            $subscription->update([
                                'stripe_status' => 'active',
                                'trial_ends_at' => null,
                                'ends_at'       => $periodEnd,
                            ]);
                        }

                        // Update payment record to paid for target plan
                        $payment->update([
                            'status'         => 'paid',
                            'is_trial'       => false,
                            'plan_id'        => $targetPlan->id,
                            'amount'         => $invoice->amount_paid / 100,
                            'paid_at'        => now(),
                            'start_date'     => now(),
                            'end_date'       => $periodEnd,
                        ]);

                        // User plan is now upgraded to target paid plan!
                        $payment->user->update(['plan_id' => $targetPlan->id]);

                        // Unlock target plan credits
                        ProjectionCredit::updateOrCreate(
                            ['user_id' => $payment->user_id],
                            [
                                'projection_limit' => $targetPlan->projection_limit,
                                'member_limit'     => $targetPlan->member_limit,
                                'expiry_date'      => $periodEnd,
                                'updated_at'       => now(),
                            ]
                        );

                        if ($targetPlan->plan_type === 'api') {
                            $this->storeExternalApi($payment->user, $targetPlan, 30, $subId);
                        }

                        $payment->user->notify(new SubscriptionNotification(
                            'Subscription Activated',
                            "Your 7-day trial has ended and payment of $" . number_format($invoice->amount_paid / 100, 2) . " for {$targetPlan->name} was successful. Full features are now unlocked!",
                            'subscription'
                        ));

                        Log::info("Auto-billing completed for User {$payment->user_id}, Plan {$targetPlan->name}, Sub {$subId}");
                    }

                    DB::commit();

                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Webhook DB Error (invoice.paid): ' . $e->getMessage());
                    return response('Internal Error', 500);
                }
            }
        }

        // =========================================================
        // 3. INVOICE PAYMENT FAILED (Card Declined after 7-Day Trial)
        // =========================================================
        elseif ($event->type === 'invoice.payment_failed') {
            $invoice = $event->data->object;
            $subId   = $invoice->subscription ?? null;

            if ($subId) {
                try {
                    $subscription = \App\Models\Subscription::where('stripe_id', $subId)->first();
                    $payment = PlanPayment::where('stripe_subscription_id', $subId)->latest()->first();

                    if ($subscription) {
                        $subscription->update(['stripe_status' => 'past_due']);
                    }
                    if ($payment) {
                        $payment->update(['status' => 'failed']);
                        $payment->user?->notify(new SubscriptionNotification(
                            'Payment Failed',
                            'Your subscription auto-payment could not be processed. Please update your card information to keep your premium access.',
                            'subscription_alert'
                        ));
                    }
                } catch (\Exception $e) {
                    Log::error('Webhook Error (invoice.payment_failed): ' . $e->getMessage());
                }
            }
        }

        // =========================================================
        // 4. SUBSCRIPTION DELETED / CANCELLED
        // =========================================================
        elseif ($event->type === 'customer.subscription.deleted') {
            $stripeSub = $event->data->object;
            $subId     = $stripeSub->id;

            try {
                $subscription = \App\Models\Subscription::where('stripe_id', $subId)->first();
                $payment = PlanPayment::where('stripe_subscription_id', $subId)->latest()->first();

                if ($subscription) {
                    $subscription->update([
                        'stripe_status' => 'canceled',
                        'ends_at'       => now(),
                    ]);
                }
                if ($payment) {
                    $payment->update(['status' => 'cancelled']);
                }
            } catch (\Exception $e) {
                Log::error('Webhook Error (customer.subscription.deleted): ' . $e->getMessage());
            }
        }

        return response('Webhook Handled', 200);
    }

    public function cancelSubscription(Request $request)
    {
        $user = auth()->user();

        // Professional: 6-month lock
        if ($user->user_type === 'professional') {
            $minDate = $user->created_at->addMonths(6);
            if (now()->lt($minDate)) {
                return response()->json([
                    'success' => false,
                    'message' => "Professional users can only cancel after " . $minDate->format('d M, Y'),
                ], 403);
            }
        }

        $payment = PlanPayment::with('plan')
            ->where('user_id', $user->id)
            ->where('status', 'paid')
            ->whereNotNull('stripe_subscription_id')
            ->latest()
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found.',
            ], 404);
        }

        try {
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
            
            // Cancel at period end in Stripe so the customer retains access through the billing period
            $stripeSub = $stripe->subscriptions->update(
                $payment->stripe_subscription_id,
                ['cancel_at_period_end' => true]
            );

            $periodEndTimestamp = $stripeSub->current_period_end ?? null;
            $endDate = $periodEndTimestamp 
                ? \Carbon\Carbon::createFromTimestamp($periodEndTimestamp) 
                : ($payment->end_date ?? now()->addDays(30));

            $payment->update([
                'status'   => 'pending_cancellation',
                'end_date' => $endDate,
            ]);

            // Customer retains access through the remainder of their monthly or annual period
            $billingPeriod = ($payment->billing === 'annual' || $payment->billing === 'yearly') ? 'annual' : 'monthly';
            $accessDate = $endDate->format('d M, Y');

            return response()->json([
                'success'           => true,
                'status'            => 'pending_cancellation',
                'can_cancel'        => false,
                'cancel_requested'  => true,
                'access_until'      => $accessDate,
                'message'           => "Cancellation requested. You will retain full access through the remainder of your {$billingPeriod} billing period until {$accessDate}.",
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getCustomerPortal(Request $request)
    {
        $user   = auth()->user();
        $stripe = new StripeClient(config('services.stripe.secret'));

        $session = $stripe->billingPortal->sessions->create([
            'customer'   => $user->stripe_id,
            'return_url' => url('/user-dashboard'),
        ]);

        return response()->json(['url' => $session->url]);
    }

    private function resolvePriceAndInterval(Plan $plan, string $billing): array
    {
        $price    = $plan->price;
        $interval = 'month';
        $duration = 30;

        switch ($plan->plan_type) {

            case 'individual':
            case 'api':
                if ($billing === 'annual') {
                    $price    = $plan->price * 12 * 0.9;
                    $interval = 'year';
                    $duration = 365;
                } elseif ($billing === 'half_annual') {
                    $price    = $plan->price * 6 * 0.95;
                    $interval = 'month';
                    $duration = 180;
                } else {
                    $price    = $plan->price;
                    $interval = 'month';
                    $duration = 30;
                }
                break;

            case 'professional':
                $price    = $plan->price;
                $interval = 'month';
                $duration = 30;
                break;

            default:
                $price    = $plan->price;
                $interval = 'month';
                $duration = 30;
        }

        return [$price, $interval, $duration];
    }

    
    protected function activateSubscription(
        PlanPayment $payment,
        User $user,
        Plan $plan,
        ?string $subscriptionId = null,
        int $duration = 30
    ): void {
        $payment->update([
            'status'                 => 'paid',
            'stripe_subscription_id' => $subscriptionId,
            'paid_at'                => now(),
            'end_date'               => now()->addDays($duration),
        ]);

        $user->update(['plan_id' => $plan->id]);

        ProjectionCredit::updateOrCreate(
            ['user_id' => $user->id],
            [
                'projection_limit' => $plan->projection_limit,
                'member_limit'     => $plan->member_limit,
                'expiry_date'      => now()->addDays($duration),
                'updated_at'       => now(),
            ]
        );
    }

    protected function storeExternalApi(
        User $user,
        Plan $plan,
        int $duration = 30,
        ?string $subscriptionId = null
    ): void {
        do {
            $apiKey = Str::random(60);
        } while (ExternalApi::where('api_key', $apiKey)->exists());

        ExternalApi::updateOrCreate(
            ['user_id' => $user->id],
            [
                'api_key'          => $apiKey,
                'projection_limit' => $plan->projection_limit ?? 0,
                'insite_limit'     => $plan->projection_limit ?? 0,
                'start_date'       => now(),
                'end_date'         => now()->addDays($duration),
            ]
        );
    }
}