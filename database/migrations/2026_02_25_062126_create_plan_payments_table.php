<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlanPaymentsTable extends Migration
{
    public function up()
    {
        Schema::create('plan_payments', function (Blueprint $table) {
            $table->id();

            // User who paid
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

           

            $table->foreignId('plan_id')
                ->nullable()
                ->constrained('plans')
                ->nullOnDelete();

            // Payment info
            $table->string('payment_method')->default('stripe');
            $table->string('transaction_id')->nullable()->unique(); // nullable for pending payments
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('usd');
           

            // payment status
            $table->enum('status', ['unpaid', 'paid', 'failed', 'refunded', 'cancelled'])
                  ->default('unpaid');

          

            $table->timestamps();
            $table->softDeletes();

           
        });
    }

    public function down()
    {
        Schema::dropIfExists('plan_payments');
    }
}