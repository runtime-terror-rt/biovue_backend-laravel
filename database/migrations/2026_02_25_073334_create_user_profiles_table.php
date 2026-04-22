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
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); 

            // Demographics 
            $table->integer('age')->nullable(); 
            $table->string('sex', 20)->nullable(); 
            $table->decimal('height', 10, 2)->nullable(); 
            $table->decimal('weight', 10, 2)->nullable();
            $table->enum('unit', ['metric', 'imperial'])->default('imperial');
            $table->string('body_fat', 20)->nullable(); 
            $table->string('location')->nullable(); 
            $table->string('zipcode', 20)->nullable();
            $table->boolean('agreed_terms')->default(false); 
            $table->string('image')->nullable();
            $table->string('current_image')->nullable();

            // Health Metrics 
            $table->boolean('smoking_status')->nullable(); 
            $table->boolean('alcohol_consumption')->nullable(); 
            $table->integer('stress_level')->nullable(); 
            $table->integer('daily_step')->nullable(); 
            $table->float('sleep_hour')->nullable(); 
            $table->string('water_consumption_week', 50)->nullable(); 
            $table->string('overall_diet_quality', 50)->nullable(); 
            $table->string('fast_food_frequency', 50)->nullable(); 
            $table->string('strength_training_week', 50)->nullable(); 
            $table->string('workout_week', 50)->nullable(); 
            
            // Fitness Goals 
            $table->boolean('is_athletic')->default(false); 
            $table->boolean('toned')->default(false); 
            $table->boolean('lean')->default(false); 
            $table->boolean('muscular')->default(false); 
            $table->boolean('curvy_fit')->default(false); 
            $table->string('notes')->nullable();

            //Trainer-specific fields
            $table->string('prof_service_type')->nullable();
            $table->text('bio')->nullable();
            $table->json('specialties')->nullable();
            $table->json('services')->nullable(); 
            $table->integer('experience_years')->nullable(); 

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
