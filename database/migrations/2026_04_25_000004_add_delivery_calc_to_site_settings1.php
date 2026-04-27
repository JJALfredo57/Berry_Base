<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $t) {
            if (!Schema::hasColumn('site_settings', 'fee_per_meter'))
                $t->decimal('fee_per_meter', 8, 4)->default(0.05)->after('shop_lng');
            if (!Schema::hasColumn('site_settings', 'maintenance_per_km'))
                $t->decimal('maintenance_per_km', 8, 2)->default(5.00)->after('fee_per_meter');
            if (!Schema::hasColumn('site_settings', 'fuel_per_km'))
                $t->decimal('fuel_per_km', 8, 2)->default(8.00)->after('maintenance_per_km');
            if (!Schema::hasColumn('site_settings', 'free_delivery_radius'))
                $t->integer('free_delivery_radius')->default(0)->after('fuel_per_km');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $t) {
            $t->dropColumn(['fee_per_meter','maintenance_per_km','fuel_per_km','free_delivery_radius']);
        });
    }
};
