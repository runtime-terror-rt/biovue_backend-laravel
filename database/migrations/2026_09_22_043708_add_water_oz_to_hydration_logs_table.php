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
        Schema::table('hydration_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('hydration_logs', 'water_oz')) {
                $table->decimal('water_oz', 8, 2)->nullable()->after('water_glasses');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hydration_logs', function (Blueprint $table) {
            if (Schema::hasColumn('hydration_logs', 'water_oz')) {
                $table->dropColumn('water_oz');
            }
        });
    }
};
