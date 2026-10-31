<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change enum('days', 'monthly', 'annual') to varchar(50) to allow 'half_annual', 'custom', etc.
        if (Schema::hasTable('plan_payments')) {
            try {
                DB::statement("ALTER TABLE plan_payments MODIFY COLUMN billing VARCHAR(50) NULL DEFAULT 'monthly'");
            } catch (\Exception $e) {
                // In case DB doesn't support direct statement or sqlite in testing
                Schema::table('plan_payments', function (Blueprint $table) {
                    $table->string('billing', 50)->nullable()->default('monthly')->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('plan_payments')) {
            try {
                DB::statement("ALTER TABLE plan_payments MODIFY COLUMN billing ENUM('days', 'monthly', 'annual') NULL DEFAULT 'monthly'");
            } catch (\Exception $e) {
                // Ignore rollback failure
            }
        }
    }
};

