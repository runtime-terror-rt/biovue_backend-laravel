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
        // Change enum to varchar(50) to allow 'trialing', 'pending_cancellation', etc.
        DB::statement("ALTER TABLE plan_payments MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'unpaid'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE plan_payments MODIFY COLUMN status ENUM('unpaid', 'paid', 'failed', 'refunded', 'cancelled') NOT NULL DEFAULT 'unpaid'");
    }
};
