<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('psgc_provinces')) {
            Schema::create('psgc_provinces', function (Blueprint $table) {
                $table->string('code', 20)->primary();
                $table->string('name', 120);
                $table->string('region_code', 20)->nullable()->index();
                $table->string('region_name', 120)->nullable();
                $table->timestamps();
                $table->index('name');
            });
        }

        if (!Schema::hasTable('psgc_cities_municipalities')) {
            Schema::create('psgc_cities_municipalities', function (Blueprint $table) {
                $table->string('code', 20)->primary();
                $table->string('name', 160);
                $table->string('type', 40)->nullable();
                $table->string('province_code', 20)->nullable()->index();
                $table->string('province_name', 120)->nullable();
                $table->string('region_code', 20)->nullable()->index();
                $table->string('region_name', 120)->nullable();
                $table->timestamps();
                $table->index('name');
            });
        }

        if (!Schema::hasTable('psgc_barangays')) {
            Schema::create('psgc_barangays', function (Blueprint $table) {
                $table->string('code', 20)->primary();
                $table->string('name', 160);
                $table->string('city_municipality_code', 20)->nullable()->index();
                $table->string('city_municipality_name', 160)->nullable();
                $table->string('province_code', 20)->nullable()->index();
                $table->string('province_name', 120)->nullable();
                $table->string('region_code', 20)->nullable()->index();
                $table->string('region_name', 120)->nullable();
                $table->string('status', 40)->nullable();
                $table->timestamps();
                $table->index('name');
            });
        }

        foreach (['orders', 'user_addresses'] as $tableName) {
            if (!Schema::hasTable($tableName)) continue;
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'province_code')) $table->string('province_code', 20)->nullable();
                if (!Schema::hasColumn($tableName, 'province_name')) $table->string('province_name', 120)->nullable();
                if (!Schema::hasColumn($tableName, 'city_municipality_code')) $table->string('city_municipality_code', 20)->nullable();
                if (!Schema::hasColumn($tableName, 'city_municipality_name')) $table->string('city_municipality_name', 160)->nullable();
                if (!Schema::hasColumn($tableName, 'barangay_code')) $table->string('barangay_code', 20)->nullable();
                if (!Schema::hasColumn($tableName, 'barangay_name')) $table->string('barangay_name', 160)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['orders', 'user_addresses'] as $tableName) {
            if (!Schema::hasTable($tableName)) continue;
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['barangay_name', 'barangay_code', 'city_municipality_name', 'city_municipality_code', 'province_name', 'province_code'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) $table->dropColumn($column);
                }
            });
        }

        Schema::dropIfExists('psgc_barangays');
        Schema::dropIfExists('psgc_cities_municipalities');
        Schema::dropIfExists('psgc_provinces');
    }
};

