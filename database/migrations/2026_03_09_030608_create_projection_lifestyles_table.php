<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projection_lifestyles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // request fields
            $table->string('image')->nullable();

            $table->enum('duration', ['6 months', '1 year', '5 years'])
                  ->default('1 year');

            $table->enum('resolution', ['2K', '4K'])
                  ->default('2K');

            $table->enum('tier', ['ultra', 'fast'])
                  ->default('ultra');

            // response fields
            $table->string('projection_id')->nullable();
            $table->text('projection_url')->nullable();
            $table->string('route')->nullable();
            $table->string('timeframe')->nullable();
            $table->string('est_bmi')->nullable();
            $table->string('est_weight')->nullable();

            $table->json('expected_changes')->nullable();
            $table->decimal('confidence_score', 3, 2)->nullable();

            $table->timestamps();

            
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projection_lifestyles');
    }
};
