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

        $payments = PlanPayment::with('plan')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'success'         => true,
            'user'            => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'latest_payment'  => $payments->first(),
            'payment_history' => $payments,
        ]);
    }

    public function paymentProcess(Request $request)
    {
        $request->validate([
            'plan_id'          => 'required|exists:plans,id',
            'billing'          => 'required|in:monthly,half_annual,annual,custom',
            'meter_id'         => 'nullable|string',
            'meter_event_name' => 'nullable|string',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $user = auth()->user();

        [$finalPrice, $interval, $duration] = $this->resolvePriceAndInterval($plan, $request->billing);

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

            if ($finalPrice <= 0) {
                DB::beginTransaction();
                try {
                    $this->activateSubscription($payment, $user, $plan, null, $duration);

                    if ($plan->plan_type === 'api') {
                        $this->storeExternalApi($user, $plan, $duration);
                    }
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Free Plan Activation Error: ' . $e->getMessage());
                    return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Free plan activated successfully.',
                ]);
            }

            $stripe = new StripeClient(config('services.stripe.secret'));

            $productName = $plan->name;
            if ($plan->plan_type === 'professional') {
                $productName .= ' (Monthly Installment)';
            } elseif ($plan->plan_type === 'api') {
                $productName .= ' (API Access)';
            }

            $session = $stripe->checkout->sessions->create([
                'mode'       => 'subscription',
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
                    'payment_id'       => $payment->id,
                    'user_id'          => $user->id,
                    'plan_type'        => $plan->plan_type,
                    'duration_days'    => $duration,
                    'meter_id'         => $request->meter_id ?? null,
                    'meter_event_name' => $request->meter_event_name ?? null,
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

        if ($event->type === 'checkout.session.completed') {
            $session   = $event->data->object;
            $paymentId = $session->metadata->payment_id ?? null;

            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

            DB::beginTransaction();
            try {
                $payment = $paymentId
                    ? PlanPayment::with(['user', 'plan'])->find($paymentId)
                    : PlanPayment::with(['user', 'plan'])->where('transaction_id', $session->id)->first();

                if (!$payment || $payment->status === 'paid') {
                    DB::commit();
                    return response('Already handled', 200);
                }

                $subId     = $session->subscription;
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
                        'stripe_status' => 'active',
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

                Log::info('Webhook processed for Payment ID: ' . $payment->id);

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
            $stripe->subscriptions->cancel($payment->stripe_subscription_id);

            $payment->update(['status' => 'cancelled']);
            $user->update(['plan_id' => null]);

            if ($payment->plan && $payment->plan->plan_type === 'api') {
                ExternalApi::where('user_id', $user->id)
                    ->update(['end_date' => now()]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Your subscription has been cancelled immediately. No refunds will be issued for the remaining period.',
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