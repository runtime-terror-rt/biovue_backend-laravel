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
        Schema::create('adjust_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); 
            $table->foreignId('profession_id')->constrained('users')->onDelete('cascade'); 
            $table->integer('target_weight')->nullable(); 
            $table->string('weekly_workouts')->nullable(); 
            $table->string('sleep_target_range')->nullable(); 
            $table->float('hydration_target')->nullable(); 
            $table->boolean('show_program_goals')->default(true); 
            $table->boolean('show_personal_targets')->default(true); 
            $table->boolean('show_progress_graphs')->default(true); 
            $table->boolean('show_ai_insights')->default(true); 
            $table->string('primary_focus_area')->nullable(); 
            $table->text('note')->nullable(); 
            $table->string('programs')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adjust_programs');
    }
};
