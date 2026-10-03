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
        Schema::table('adjust_programs', function (Blueprint $table) {
            if (!Schema::hasColumn('adjust_programs', 'calories')) {
                $table->unsignedInteger('calories')->nullable()->after('programs');
            }
            if (!Schema::hasColumn('adjust_programs', 'protein')) {
                $table->unsignedInteger('protein')->nullable()->after('calories');
            }
            if (!Schema::hasColumn('adjust_programs', 'carbs')) {
                $table->unsignedInteger('carbs')->nullable()->after('protein');
            }
            if (!Schema::hasColumn('adjust_programs', 'fat')) {
                $table->unsignedInteger('fat')->nullable()->after('carbs');
            }
        });

        Schema::table('programs_sets', function (Blueprint $table) {
            if (!Schema::hasColumn('programs_sets', 'workout_types')) {
                $table->json('workout_types')->nullable()->after('habit_focus');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adjust_programs', function (Blueprint $table) {
            $table->dropColumn(['calories', 'protein', 'carbs', 'fat']);
        });

        Schema::table('programs_sets', function (Blueprint $table) {
            $table->dropColumn(['workout_types']);
        });
    }
};
