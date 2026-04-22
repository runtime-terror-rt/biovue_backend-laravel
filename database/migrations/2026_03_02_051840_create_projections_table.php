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
        Schema::create('projections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            $table->string('projection_id')->nullable(); 
            $table->string('lifestyle_type')->default('current-lifestyle'); 

            $table->enum('duration', ['6 months', '1 year', '5 years'])->default('1 year');
            $table->enum('resolution', ['2K', '4K'])->default('2K');
            $table->enum('tier', ['ultra', 'fast'])->default('fast');
            
            $table->string('time_horizon')->nullable(); 

            $table->string('current_photo')->nullable(); 
            $table->string('projected_photo')->nullable();
            
            $table->float('projected_bmi')->nullable();
            $table->float('projected_weight')->nullable();
            $table->string('confidence_score')->nullable(); 
            
            $table->json('expected_changes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projections');
    }
};
