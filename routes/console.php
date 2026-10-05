<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\SyncStripeTrialsCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('stripe:sync-trials {--user_id= : Specific user ID to sync}', function () {
    $this->call(SyncStripeTrialsCommand::class, [
        '--user_id' => $this->option('user_id'),
    ]);
})->description('Sync Stripe Checkout sessions for trial payments that are stuck as unpaid or missing trial_ends_at');

Schedule::command('subscription:remind-expiry')->daily();
