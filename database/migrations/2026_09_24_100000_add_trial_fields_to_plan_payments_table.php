<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plan_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('plan_payments', 'target_plan_id')) {
                $table->foreignId('target_plan_id')
                    ->nullable()
                    ->after('plan_id')
                    ->constrained('plans')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('plan_payments', 'is_trial')) {
                $table->boolean('is_trial')
                    ->default(false)
                    ->after('status');
            }

            if (!Schema::hasColumn('plan_payments', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')
                    ->nullable()
                    ->after('is_trial');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_payments', function (Blueprint $table) {
            if (Schema::hasColumn('plan_payments', 'target_plan_id')) {
                $table->dropForeign(['target_plan_id']);
                $table->dropColumn('target_plan_id');
            }
            if (Schema::hasColumn('plan_payments', 'trial_ends_at')) {
                $table->dropColumn('trial_ends_at');
            }
            if (Schema::hasColumn('plan_payments', 'is_trial')) {
                $table->dropColumn('is_trial');
            }
        });
    }
};
