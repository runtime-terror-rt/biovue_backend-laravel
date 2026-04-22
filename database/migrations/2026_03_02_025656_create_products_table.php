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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('users')->onDelete('cascade');
    
            $table->string('name'); 
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->enum('category', ['fitness', 'nutrition', 'supplements'])->nullable();
            $table->decimal('price', 10, 2)->default(0.00); 
            
            $table->string('redirect_url')->nullable();
            
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
