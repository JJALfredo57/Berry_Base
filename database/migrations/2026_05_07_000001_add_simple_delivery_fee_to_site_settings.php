<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $t) {
            if (!Schema::hasColumn('site_settings', 'base_fee'))
                $t->decimal('base_fee', 8, 2)->default(30.00)->after('free_delivery_radius');
            if (!Schema::hasColumn('site_settings', 'fee_per_km'))
                $t->decimal('fee_per_km', 8, 2)->default(15.00)->after('base_fee');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $t) {
            $t->dropColumn(['base_fee', 'fee_per_km']);
        });
    }
};
