<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('meal_items', function (Blueprint $table) {

            $table->id();
            $table->foreignId('meal_plan_id')->constrained('meal_plans')->onDelete('cascade');

            $table->string('meal_type'); // breakfast, lunch, snacks, dinner
            $table->string('food');
            $table->float('quantity');
            $table->string('unit');

            $table->timestamps();

        });
    }

    public function down()
    {
        Schema::dropIfExists('meal_items');
    }
};