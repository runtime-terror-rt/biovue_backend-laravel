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
        Schema::create('projection_data', function (Blueprint $table) {
           $table->id();
            $table->string('projection_id')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('input_image');
            $table->string('timeframe')->nullable();
            $table->string('resolution')->nullable();
            $table->json('projections_data'); 
            $table->json('summary_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projection_data');
    }
};
