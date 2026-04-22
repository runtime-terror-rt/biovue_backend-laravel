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
        Schema::create('programs_sets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('profession_id')->constrained('users')->onDelete('cascade');
                $table->string('name')->nullable(); 
                $table->integer('duration')->nullable();
                $table->string('primary_goal')->nullable();

                $table->enum('target_intensity', ['Light', 'Moderate', 'High'])->nullable();
                $table->text('description')->nullable();

                $table->text('notes')->nullable();
                $table->json('weekly_targets')->nullable();
                $table->json('habit_focus_areas')->nullable(); 
                $table->json('program_focus')->nullable(); 
                $table->json('focus_areas')->nullable();
                $table->json('habit_focus')->nullable();

                $table->unsignedInteger('calories')->nullable(); 
                $table->unsignedInteger('protein')->nullable(); 
                $table->unsignedInteger('carbs')->nullable(); 
                $table->unsignedInteger('fat')->nullable(); 

                $table->json('supplement_recommendation')->nullable(); 
                $table->json('supplement')->nullable();
                $table->softDeletes();

                $table->timestamps();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programs_sets');
    }
};
