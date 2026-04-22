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
        Schema::create('projection_future_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('image'); // uploaded image filename
             $table->enum('duration', ['6 months', '1 year', '5 years'])
                  ->default('1 year');

            $table->enum('resolution', ['2K', '4K'])
                  ->default('2K');

            $table->enum('tier', ['ultra', 'fast'])
                  ->default('ultra');
            $table->boolean('use_default_goal')->default(true); // whether using default goal
            $table->text('goal')->nullable(); // custom goal, nullable
            $table->text('goal_description')->nullable(); // custom goal description, nullable
            $table->string('projection_id')->nullable(); // API-generated projection ID
            $table->string('projection_url')->nullable(); // URL of generated projection
            $table->string('route')->nullable(); // e.g., "future-goal"
            $table->string('timeframe')->nullable(); // e.g., "1 year"
            $table->string('est_bmi')->nullable(); // estimated BMI
            $table->string('est_weight')->nullable(); // estimated weight
            $table->json('expected_changes')->nullable(); // JSON array of expected changes
            $table->string('confidence_score')->nullable(); // API confidence score
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projection_future_goals');
    }
};