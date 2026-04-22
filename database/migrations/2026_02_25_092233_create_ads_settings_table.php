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
        Schema::create('ads_settings', function (Blueprint $table) {
            $table->id();
            $table->string('ads_title')->nullable(); 
            $table->string('ads_type')->nullable(); 
            $table->string('image')->nullable(); 
            $table->string('placement')->nullable(); 
            $table->date('start_date')->nullable(); 
            $table->date('end_date')->nullable();
            $table->boolean('status')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ads_settings');
    }
};
