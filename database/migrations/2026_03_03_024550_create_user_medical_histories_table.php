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
        Schema::create('user_medical_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); 
            //medical history
            $table->boolean('diabetes')->default(false);
            $table->boolean('high_blood_pressure')->default(false);
            $table->boolean('high_cholesterol')->default(false);
            $table->boolean('heart_disease')->default(false);
            $table->boolean('asthma')->default(false);
            $table->boolean('athritis')->default(false);
            $table->boolean('depression')->default(false);
            $table->boolean('anxiety')->default(false);
            $table->boolean('sleep_apnea')->default(false);
            $table->boolean('thyroid_issue')->default(false);
            $table->string('current_medication')->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_medical_histories');
    }
};
