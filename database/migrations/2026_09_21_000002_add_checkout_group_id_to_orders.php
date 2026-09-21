<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) return;

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'checkout_group_id')) {
                $table->string('checkout_group_id', 40)->nullable()->after('order_type')->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders') || !Schema::hasColumn('orders', 'checkout_group_id')) return;

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('checkout_group_id');
        });
    }
};
