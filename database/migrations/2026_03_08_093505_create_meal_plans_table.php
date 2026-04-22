<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('meal_plans', function (Blueprint $table) {

            $table->id();

             $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->integer('target_calorie');
            $table->integer('target_protein');
            $table->integer('target_carbs');
            $table->integer('target_fat');

            $table->timestamps();

        });
    }

    public function down()
    {
        Schema::dropIfExists('meal_plans');
    }
};