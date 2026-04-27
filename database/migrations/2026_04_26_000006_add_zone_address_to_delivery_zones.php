<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('delivery_zones', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_zones', 'zone_address')) {
                $table->string('zone_address', 255)->nullable()->after('barangay');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_zones', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_zones', 'zone_address')) {
                $table->dropColumn('zone_address');
            }
        });
    }
};
