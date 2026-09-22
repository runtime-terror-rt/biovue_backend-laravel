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
        // 1. user_nutrition_calculates (user_id + log_date lookup)
        if (Schema::hasTable('user_nutrition_calculates')) {
            Schema::table('user_nutrition_calculates', function (Blueprint $table) {
                $table->index(['user_id', 'log_date'], 'idx_unc_user_logdate');
            });
        }

        // 2. target_goals (user_id + is_active lookup)
        if (Schema::hasTable('target_goals')) {
            Schema::table('target_goals', function (Blueprint $table) {
                $table->index(['user_id', 'is_active'], 'idx_tg_user_active');
            });
        }

        // 3. projections (user_id + created_at lookup)
        if (Schema::hasTable('projections')) {
            Schema::table('projections', function (Blueprint $table) {
                $table->index(['user_id', 'created_at'], 'idx_proj_user_created');
            });
        }

        // 4. plan_payments (user_id + status lookup)
        if (Schema::hasTable('plan_payments')) {
            Schema::table('plan_payments', function (Blueprint $table) {
                $table->index(['user_id', 'status'], 'idx_pp_user_status');
            });
        }

        // 5. products (status and supplier_id + status)
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index('status', 'idx_prod_status');
                $table->index(['supplier_id', 'status'], 'idx_prod_supplier_status');
            });
        }

        // 6. faqs (is_active)
        if (Schema::hasTable('faqs')) {
            Schema::table('faqs', function (Blueprint $table) {
                $table->index('is_active', 'idx_faqs_active');
            });
        }

        // 7. ads_settings (status)
        if (Schema::hasTable('ads_settings')) {
            Schema::table('ads_settings', function (Blueprint $table) {
                $table->index('status', 'idx_ads_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('user_nutrition_calculates')) {
            Schema::table('user_nutrition_calculates', function (Blueprint $table) {
                $table->dropIndex('idx_unc_user_logdate');
            });
        }

        if (Schema::hasTable('target_goals')) {
            Schema::table('target_goals', function (Blueprint $table) {
                $table->dropIndex('idx_tg_user_active');
            });
        }

        if (Schema::hasTable('projections')) {
            Schema::table('projections', function (Blueprint $table) {
                $table->dropIndex('idx_proj_user_created');
            });
        }

        if (Schema::hasTable('plan_payments')) {
            Schema::table('plan_payments', function (Blueprint $table) {
                $table->dropIndex('idx_pp_user_status');
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('idx_prod_status');
                $table->dropIndex('idx_prod_supplier_status');
            });
        }

        if (Schema::hasTable('faqs')) {
            Schema::table('faqs', function (Blueprint $table) {
                $table->dropIndex('idx_faqs_active');
            });
        }

        if (Schema::hasTable('ads_settings')) {
            Schema::table('ads_settings', function (Blueprint $table) {
                $table->dropIndex('idx_ads_status');
            });
        }
    }
};
