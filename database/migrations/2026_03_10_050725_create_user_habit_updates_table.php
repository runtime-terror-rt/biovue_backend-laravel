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
        Schema::create('user_habit_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->text('focus_on_trends')->nullable();

            // Sleep
            $table->string('sleep_status')->nullable();
            $table->text('sleep_why_this_matters')->nullable();
            $table->text('sleep_biovue_insights')->nullable();

            // Nutrition
            $table->string('nutrition_status')->nullable();
            $table->text('nutrition_why_this_matters')->nullable();
            $table->text('nutrition_biovue_insights')->nullable();

            // Activity
            $table->string('activity_status')->nullable();
            $table->text('activity_why_this_matters')->nullable();
            $table->text('activity_biovue_insights')->nullable();

            // Stress
            $table->string('stress_status')->nullable();
            $table->text('stress_why_this_matters')->nullable();
            $table->text('stress_biovue_insights')->nullable();

            // Hydration
            $table->string('hydration_status')->nullable();
            $table->text('hydration_why_this_matters')->nullable();
            $table->text('hydration_biovue_insights')->nullable();

            $table->timestamps();

            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_habit_updates');
    }
};