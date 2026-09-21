<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) return;

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'order_type')) {
                $table->string('order_type', 20)->default('regular')->after('product_id');
            }
        });

        if (Schema::hasTable('custom_orders')) {
            DB::table('orders')
                ->whereIn('id', DB::table('custom_orders')->select('order_id'))
                ->update(['order_type' => 'custom']);
        }

        DB::table('orders')
            ->whereNull('order_type')
            ->orWhere('order_type', '')
            ->update(['order_type' => 'regular']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders') || !Schema::hasColumn('orders', 'order_type')) return;

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('order_type');
        });
    }
};
