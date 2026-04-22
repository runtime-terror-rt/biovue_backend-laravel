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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->onDelete('cascade');
            $table->date('log_date'); 
            
            $table->decimal('weight', 5, 2)->nullable(); 
            
            $table->unsignedInteger('daily_steps')->default(0);
            $table->decimal('sleep_hours', 4, 2)->nullable(); 
            $table->unsignedTinyInteger('water_glasses')->default(0);
            
            $table->timestamps();
            $table->unique(['user_id', 'log_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
