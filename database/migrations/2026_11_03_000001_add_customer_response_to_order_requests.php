<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_requests') && !Schema::hasColumn('order_requests', 'customer_response')) {
            Schema::table('order_requests', function (Blueprint $table) {
                $table->text('customer_response')->nullable()->after('customer_note');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_requests') && Schema::hasColumn('order_requests', 'customer_response')) {
            Schema::table('order_requests', function (Blueprint $table) {
                $table->dropColumn('customer_response');
            });
        }
    }
};
