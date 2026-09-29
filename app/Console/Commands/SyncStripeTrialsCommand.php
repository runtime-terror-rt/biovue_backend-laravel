<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PlanPayment;
use App\Http\Controllers\Payment\PlanPaymentController;
use Stripe\StripeClient;
use Illuminate\Support\Facades\DB;

class SyncStripeTrialsCommand extends Command
{
    protected $signature = 'stripe:sync-trials {--user_id= : Specific user ID to sync}';
    protected $description = 'Sync Stripe Checkout sessions for trial payments that are stuck as unpaid or missing trial_ends_at';

    public function handle()
    {
        $userId = $this->option('user_id');

        $query = PlanPayment::with(['user', 'plan', 'targetPlan'])
            ->where('is_trial', true)
            ->where(function ($q) {
                $q->where('status', 'unpaid')
                  ->orWhereNull('trial_ends_at');
            })
            ->where('transaction_id', 'like', 'cs_%');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $pendingPayments = $query->get();

        if ($pendingPayments->isEmpty()) {
            $this->info('No pending trial checkout sessions found to sync.');
            return 0;
        }

        $this->info("Found {$pendingPayments->count()} trial payments to check with Stripe...");

        $stripe = new StripeClient(config('services.stripe.secret'));
        $controller = app(PlanPaymentController::class);

        $syncedCount = 0;

        foreach ($pendingPayments as $payment) {
            $sessionId = $payment->transaction_id;
            $this->line("Checking Payment #{$payment->id} (User #{$payment->user_id}) - Session: {$sessionId}");

            try {
                $session = $stripe->checkout->sessions->retrieve($sessionId);

                if ($session && $session->status === 'complete') {
                    DB::beginTransaction();
                    $success = $controller->fulfillCheckoutSession($session, $stripe);
                    DB::commit();

                    if ($success) {
                        $payment->refresh();
                        $this->info(" -> SUCCESS: Synced User #{$payment->user_id}! Status: {$payment->status}, Trial ends at: {$payment->trial_ends_at}");
                        $syncedCount++;
                    } else {
                        $this->warn(" -> Session complete but fulfillment returned false.");
                    }
                } else {
                    $status = $session ? $session->status : 'unknown';
                    $this->comment(" -> Stripe session status is: '{$status}' (not completed).");
                }
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error(" -> Error for Payment #{$payment->id}: " . $e->getMessage());
            }
        }

        $this->info("Sync completed. Successfully updated {$syncedCount} trial(s).");
        return 0;
    }
}
