<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('platform_settings')) {
            if (Schema::hasColumn('platform_settings', 'loyalty_points_base_amount')) {
                DB::table('platform_settings')
                    ->where('loyalty_points_base_amount', 50)
                    ->update(['loyalty_points_base_amount' => 100]);
            }

            if (Schema::hasColumn('platform_settings', 'loyalty_point_value')) {
                DB::table('platform_settings')
                    ->where('loyalty_point_value', 1)
                    ->update(['loyalty_point_value' => 0.10]);
            }

            if (Schema::hasColumn('platform_settings', 'loyalty_max_redemption_percent')) {
                DB::table('platform_settings')
                    ->where('loyalty_max_redemption_percent', 50)
                    ->update(['loyalty_max_redemption_percent' => 20]);
            }
        }

        if (Schema::hasTable('loyalty_tiers')) {
            DB::table('loyalty_tiers')
                ->where('perk_summary', 'Highest rewards rate and priority trust signals.')
                ->update(['perk_summary' => 'Highest rewards rate and Customer Loyalty Trust benefits.']);
        }
    }

    public function down(): void
    {
        // Intentionally keep safer rewards settings on rollback.
    }
};
