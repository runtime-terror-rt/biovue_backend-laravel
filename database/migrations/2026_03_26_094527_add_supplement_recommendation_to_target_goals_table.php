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
        Schema::table('target_goals', function (Blueprint $table) {
            $table->json('supplement_recommendation')->nullable()->after('water_target');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('target_goals', function (Blueprint $table) {
            $table->dropColumn('supplement_recommendation');
        });
    }
};
