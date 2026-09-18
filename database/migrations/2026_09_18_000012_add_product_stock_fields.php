<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'available_quantity')) {
            Schema::table('products', function (Blueprint $table) {
                $table->integer('available_quantity')->nullable()->after('is_available');
            });
        }

        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'stock_released_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $column = $table->timestamp('stock_released_at')->nullable();
                if (Schema::hasColumn('orders', 'cancel_requested_at')) {
                    $column->after('cancel_requested_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'stock_released_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('stock_released_at');
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'available_quantity')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('available_quantity');
            });
        }
    }
};
