<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('platform_settings')) {
            return;
        }

        Schema::table('platform_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('platform_settings', 'loyalty_earn_enabled')) {
                $table->boolean('loyalty_earn_enabled')->default(true)->after('philsms_sender');
            }
            if (!Schema::hasColumn('platform_settings', 'loyalty_redeem_enabled')) {
                $table->boolean('loyalty_redeem_enabled')->default(true)->after('loyalty_earn_enabled');
            }
            if (!Schema::hasColumn('platform_settings', 'loyalty_points_base_amount')) {
                $table->decimal('loyalty_points_base_amount', 10, 2)->default(50)->after('loyalty_redeem_enabled');
            }
            if (!Schema::hasColumn('platform_settings', 'loyalty_point_value')) {
                $table->decimal('loyalty_point_value', 10, 2)->default(1)->after('loyalty_points_base_amount');
            }
            if (!Schema::hasColumn('platform_settings', 'loyalty_max_redemption_percent')) {
                $table->decimal('loyalty_max_redemption_percent', 5, 2)->default(50)->after('loyalty_point_value');
            }
            if (!Schema::hasColumn('platform_settings', 'loyalty_redemption_requires_verified')) {
                $table->boolean('loyalty_redemption_requires_verified')->default(true)->after('loyalty_max_redemption_percent');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('platform_settings')) {
            return;
        }

        Schema::table('platform_settings', function (Blueprint $table) {
            foreach ([
                'loyalty_redemption_requires_verified',
                'loyalty_max_redemption_percent',
                'loyalty_point_value',
                'loyalty_points_base_amount',
                'loyalty_redeem_enabled',
                'loyalty_earn_enabled',
            ] as $column) {
                if (Schema::hasColumn('platform_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
