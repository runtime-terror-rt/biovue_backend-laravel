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
        // 1. Expand adjust_programs.programs text limit from VARCHAR(255) to LONGTEXT
        Schema::table('adjust_programs', function (Blueprint $table) {
            $table->longText('programs')->nullable()->change();
        });

        // 2. Add timezone to user_profiles
        Schema::table('user_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('user_profiles', 'timezone')) {
                $table->string('timezone', 64)->nullable()->after('zipcode');
            }
        });

        // 3. Add calories, protein, carbs, fat, supplement_notes, and workout_routine to target_goals
        Schema::table('target_goals', function (Blueprint $table) {
            if (!Schema::hasColumn('target_goals', 'calories')) {
                $table->unsignedInteger('calories')->nullable()->after('water_target');
            }
            if (!Schema::hasColumn('target_goals', 'protein')) {
                $table->unsignedInteger('protein')->nullable()->after('calories');
            }
            if (!Schema::hasColumn('target_goals', 'carbs')) {
                $table->unsignedInteger('carbs')->nullable()->after('protein');
            }
            if (!Schema::hasColumn('target_goals', 'fat')) {
                $table->unsignedInteger('fat')->nullable()->after('carbs');
            }
            if (!Schema::hasColumn('target_goals', 'supplement_notes')) {
                $table->text('supplement_notes')->nullable()->after('fat');
            }
            if (!Schema::hasColumn('target_goals', 'workout_routine')) {
                $table->longText('workout_routine')->nullable()->after('supplement_notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adjust_programs', function (Blueprint $table) {
            $table->string('programs', 255)->nullable()->change();
        });

        Schema::table('user_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('user_profiles', 'timezone')) {
                $table->dropColumn('timezone');
            }
        });

        Schema::table('target_goals', function (Blueprint $table) {
            $cols = [];
            foreach (['calories', 'protein', 'carbs', 'fat', 'supplement_notes', 'workout_routine'] as $col) {
                if (Schema::hasColumn('target_goals', $col)) {
                    $cols[] = $col;
                }
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
