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
        Schema::table('activity_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_logs', 'workout_type')) {
                $table->string('workout_type')->nullable()->after('water_glasses');
            }
            if (!Schema::hasColumn('activity_logs', 'workout_time')) {
                $table->unsignedInteger('workout_time')->nullable()->comment('Duration in minutes')->after('workout_type');
            }
            if (!Schema::hasColumn('activity_logs', 'workout_calories')) {
                $table->unsignedInteger('workout_calories')->nullable()->comment('Calories burned')->after('workout_time');
            }
            if (!Schema::hasColumn('activity_logs', 'workouts')) {
                $table->json('workouts')->nullable()->comment('Detailed list of multiple workout sessions')->after('workout_calories');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn(['workout_type', 'workout_time', 'workout_calories', 'workouts']);
        });
    }
};
