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
        Schema::create('stress_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->onDelete('cascade');
            $table->date('log_date');
            $table->unsignedTinyInteger('stress_level')->comment('1: Low, 10: Extreme')->nullable();
            $table->enum('mood', ['motivated','normal', 'low','happy', 'sad', 'neutral', 'angry', 'anxious'])->default('normal');
            $table->text('description')->nullable(); 
            $table->timestamps();
            $table->unique(['user_id', 'log_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stress_logs');
    }
};
