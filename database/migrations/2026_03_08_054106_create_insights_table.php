<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insights', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('priority')->nullable();
            $table->string('category')->nullable();
            $table->string('insight')->nullable();

            $table->text('why_this_matters')->nullable();
            $table->text('expected_impact')->nullable();
            $table->text('trainers_note')->nullable();

            $table->json('action_steps')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insights');
    }
};