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

            // ✅ which billing user selected
            $table->enum('billing', ['days', 'monthly', 'annual'])
                  ->nullable()
                  ->after('plan_id');

            // ✅ subscription start
            $table->timestamp('start_date')->nullable();

            // ✅ subscription end
            $table->timestamp('end_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_payments', function (Blueprint $table) {
            $table->dropColumn(['billing', 'start_date', 'end_date']);
        });
    }
};
