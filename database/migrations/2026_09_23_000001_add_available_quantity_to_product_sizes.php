<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('product_sizes') && !Schema::hasColumn('product_sizes', 'available_quantity')) {
            Schema::table('product_sizes', function (Blueprint $table) {
                $table->integer('available_quantity')->nullable()->after('price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_sizes') && Schema::hasColumn('product_sizes', 'available_quantity')) {
            Schema::table('product_sizes', function (Blueprint $table) {
                $table->dropColumn('available_quantity');
            });
        }
    }
};
