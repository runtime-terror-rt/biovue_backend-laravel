<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PlanPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Notifications\InsightNotification;

class SubscriptionExpiryReminder extends Command
{
    protected $signature = 'subscription:remind-expiry';
    protected $description = 'Send email reminder 3 days before subscription expires';

    public function handle()
    {
        $targetDate = Carbon::now()->addDays(3)->toDateString();

        $expiringPayments = PlanPayment::with('user')
            ->whereDate('end_date', $targetDate)
            ->where('status', 'paid')
            ->get();

        foreach ($expiringPayments as $payment) {
            if ($payment->user) {
                try {
                    $payment->user->notify(new InsightNotification(
                        'Subscription Expiring Soon',
                        'Your plan will expire in 3 days. Please ensure auto-renewal is active. Visit your account to manage your subscription.',
                        'subscription_alert'
                    ));
                    
                    $this->info("Reminder sent to: " . $payment->user->email);
                } catch (\Exception $e) {
                    $this->error("Failed to send to: " . $payment->user->email);
                }
            }
        }

        $this->info('All reminders processed.');
    }
}