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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); 
            $table->enum('plan_type', ['individual', 'professional'])->default('individual');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); 
            $table->enum('billing_cycle', ['days', 'monthly', 'half_annual', 'annual', 'custom']);
            $table->decimal('price', 10, 2)->default(0.00);
            $table->integer('duration')->nullable(); 
            $table->integer('member_limit')->nullable(); 
            $table->json('features')->nullable();
            $table->boolean('status')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
