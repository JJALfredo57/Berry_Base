<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'original_unit_price')) {
                $table->decimal('original_unit_price', 10, 2)->nullable()->after('selected_size_price');
            }
            if (!Schema::hasColumn('orders', 'discount_label')) {
                $table->string('discount_label', 100)->nullable()->after('original_unit_price');
            }
            if (!Schema::hasColumn('orders', 'discount_type')) {
                $table->string('discount_type', 20)->nullable()->after('discount_label');
            }
            if (!Schema::hasColumn('orders', 'discount_value')) {
                $table->decimal('discount_value', 10, 2)->nullable()->after('discount_type');
            }
            if (!Schema::hasColumn('orders', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)->nullable()->after('discount_value');
            }
            if (!Schema::hasColumn('orders', 'final_unit_price')) {
                $table->decimal('final_unit_price', 10, 2)->nullable()->after('discount_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $drops = [];
            foreach ([
                'original_unit_price',
                'discount_label',
                'discount_type',
                'discount_value',
                'discount_amount',
                'final_unit_price',
            ] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $drops[] = $column;
                }
            }
            if ($drops) {
                $table->dropColumn($drops);
            }
        });
    }
};
