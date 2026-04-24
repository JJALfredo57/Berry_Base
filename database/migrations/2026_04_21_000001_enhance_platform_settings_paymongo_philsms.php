<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add full PayMongo test/live structure to platform_settings
        if (Schema::hasTable('platform_settings')) {
            if (!Schema::hasColumn('platform_settings', 'paymongo_mode'))
                Schema::table('platform_settings', fn(Blueprint $t) => $t->string('paymongo_mode')->default('test')->after('max_products_basic'));
            if (!Schema::hasColumn('platform_settings', 'paymongo_test_secret'))
                Schema::table('platform_settings', fn(Blueprint $t) => $t->string('paymongo_test_secret')->nullable()->after('paymongo_mode'));
            if (!Schema::hasColumn('platform_settings', 'paymongo_test_public'))
                Schema::table('platform_settings', fn(Blueprint $t) => $t->string('paymongo_test_public')->nullable()->after('paymongo_test_secret'));
            if (!Schema::hasColumn('platform_settings', 'paymongo_live_secret'))
                Schema::table('platform_settings', fn(Blueprint $t) => $t->string('paymongo_live_secret')->nullable()->after('paymongo_test_public'));
            if (!Schema::hasColumn('platform_settings', 'paymongo_live_public'))
                Schema::table('platform_settings', fn(Blueprint $t) => $t->string('paymongo_live_public')->nullable()->after('paymongo_live_secret'));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('platform_settings')) {
            Schema::table('platform_settings', function (Blueprint $t) {
                foreach (['paymongo_mode','paymongo_test_secret','paymongo_test_public','paymongo_live_secret','paymongo_live_public'] as $col) {
                    if (Schema::hasColumn('platform_settings', $col)) $t->dropColumn($col);
                }
            });
        }
    }
};
