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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('users'); 
            $table->foreignId('client_id')->constrained('users'); 
            $table->date('schedule_date');
            $table->time('schedule_time');
            $table->string('check_in_type'); 
            $table->text('private_note')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'missed'])->default('scheduled');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
