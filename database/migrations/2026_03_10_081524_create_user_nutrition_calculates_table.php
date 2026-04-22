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
        Schema::create('user_nutrition_calculates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->date('log_date')->nullable();
            // Foods JSON column
            $table->json('foods')->nullable();

            // Calories
            $table->decimal('calories_value', 8, 2)->nullable();
            $table->string('calories_unit', 10)->nullable();

            // Macros
            $table->decimal('protein_value', 8, 2)->nullable();
            $table->string('protein_unit', 10)->nullable();

            $table->decimal('carbs_value', 8, 2)->nullable();
            $table->string('carbs_unit', 10)->nullable();

            $table->decimal('fat_value', 8, 2)->nullable();
            $table->string('fat_unit', 10)->nullable();

            // Total nutrition
            $table->decimal('total', 10, 2)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_nutrition_calculates');
    }
};