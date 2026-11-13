<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('custom_orders')) return;

        Schema::table('custom_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('custom_orders', 'source_product_id')) {
                $table->string('source_product_id', 12)->nullable()->index();
            }
            if (!Schema::hasColumn('custom_orders', 'source_product_name_snapshot')) {
                $table->string('source_product_name_snapshot', 160)->nullable();
            }
            if (!Schema::hasColumn('custom_orders', 'source_product_image_snapshot')) {
                $table->string('source_product_image_snapshot', 1000)->nullable();
            }
            if (!Schema::hasColumn('custom_orders', 'source_product_price_snapshot')) {
                $table->decimal('source_product_price_snapshot', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('custom_orders', 'source_type')) {
                $table->string('source_type', 40)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('custom_orders')) return;

        Schema::table('custom_orders', function (Blueprint $table) {
            foreach (['source_type', 'source_product_price_snapshot', 'source_product_image_snapshot', 'source_product_name_snapshot', 'source_product_id'] as $column) {
                if (Schema::hasColumn('custom_orders', $column)) $table->dropColumn($column);
            }
        });
    }
};
