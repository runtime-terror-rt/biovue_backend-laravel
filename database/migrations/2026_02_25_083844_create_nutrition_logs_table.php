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
        Schema::create('nutrition_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->onDelete('cascade');
            $table->date('log_date');
            $table->enum('meal_balance', ['balanced', 'high_carb', 'high_protein', 'keto'])->nullable();
            $table->unsignedTinyInteger('protein_servings')->default(0); 
            $table->unsignedTinyInteger('vegetable_servings')->default(0);
            $table->string('carb_quality')->nullable(); 
            $table->text('fat_sources')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'log_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nutrition_logs');
    }
};
