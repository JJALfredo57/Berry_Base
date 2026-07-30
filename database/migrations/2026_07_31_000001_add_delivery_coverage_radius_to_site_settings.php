<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('site_settings', 'delivery_coverage_radius')) {
                $table->integer('delivery_coverage_radius')->default(5000)->after('free_delivery_radius');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            if (Schema::hasColumn('site_settings', 'delivery_coverage_radius')) {
                $table->dropColumn('delivery_coverage_radius');
            }
        });
    }
};
