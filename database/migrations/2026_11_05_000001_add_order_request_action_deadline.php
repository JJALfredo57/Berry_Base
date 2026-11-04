<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('order_requests') || Schema::hasColumn('order_requests', 'action_expires_at')) return;
        Schema::table('order_requests', function (Blueprint $table) {
            $table->timestamp('action_expires_at')->nullable()->index()->after('expires_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('order_requests') && Schema::hasColumn('order_requests', 'action_expires_at')) {
            Schema::table('order_requests', fn (Blueprint $table) => $table->dropColumn('action_expires_at'));
        }
    }
};
