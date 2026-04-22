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
        Schema::create('user_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->boolean('coach_messages')->default(true);
            $table->boolean('client_messages')->default(true);
            $table->boolean('goal_updates')->default(true);
            $table->boolean('ai_insights')->default(false);

            $table->boolean('missed_checkin_alerts')->default(true);

            $table->boolean('program_milestone_updates')->default(true);
            $table->boolean('weekly_summary_email')->default(false);
            $table->boolean('auto_remind_missed_checkins')->default(true);
            $table->string('default_reminder_time')->default('09:00 AM');

            $table->boolean('check_in_reminder_alerts')->default(true);
            $table->boolean('subscription_updates')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_notification_settings');
    }
};
