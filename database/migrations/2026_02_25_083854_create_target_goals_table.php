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
        Schema::create('target_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->onDelete('cascade');
            $table->foreignId('profession_id')->index()->constrained('users')->onDelete('cascade');
            $table->decimal('target_weight', 5, 2)->nullable();
            $table->unsignedTinyInteger('weekly_workout_goal')->comment('5 Days per week')->nullable();
            $table->unsignedMediumInteger('daily_step_goal')->nullable();
            $table->decimal('sleep_target', 4, 2)->nullable();
            $table->unsignedTinyInteger('water_target')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('target_goals');
    }
};
