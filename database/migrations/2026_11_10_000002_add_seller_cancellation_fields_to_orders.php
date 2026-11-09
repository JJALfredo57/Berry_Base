<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'seller_cancel_state')) $table->string('seller_cancel_state', 30)->nullable()->index();
            if (!Schema::hasColumn('orders', 'seller_cancel_expires_at')) $table->timestamp('seller_cancel_expires_at')->nullable()->index();
            if (!Schema::hasColumn('orders', 'seller_cancel_reason')) $table->text('seller_cancel_reason')->nullable();
            if (!Schema::hasColumn('orders', 'seller_cancel_responded_at')) $table->timestamp('seller_cancel_responded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['seller_cancel_state', 'seller_cancel_expires_at', 'seller_cancel_reason', 'seller_cancel_responded_at'] as $column) {
                if (Schema::hasColumn('orders', $column)) $table->dropColumn($column);
            }
        });
    }
};
