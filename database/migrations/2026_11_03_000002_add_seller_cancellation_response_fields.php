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
            if (!Schema::hasColumn('orders', 'seller_cancel_state')) {
                $table->string('seller_cancel_state', 40)->nullable()->index()->after('cancel_status');
            }
            if (!Schema::hasColumn('orders', 'seller_cancel_expires_at')) {
                $table->timestamp('seller_cancel_expires_at')->nullable()->index()->after('seller_cancel_state');
            }
            if (!Schema::hasColumn('orders', 'seller_cancel_reason')) {
                $table->text('seller_cancel_reason')->nullable()->after('seller_cancel_expires_at');
            }
            if (!Schema::hasColumn('orders', 'seller_cancel_responded_at')) {
                $table->timestamp('seller_cancel_responded_at')->nullable()->after('seller_cancel_reason');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) return;

        Schema::table('orders', function (Blueprint $table) {
            foreach (['seller_cancel_responded_at', 'seller_cancel_reason', 'seller_cancel_expires_at', 'seller_cancel_state'] as $column) {
                if (Schema::hasColumn('orders', $column)) $table->dropColumn($column);
            }
        });
    }
};
