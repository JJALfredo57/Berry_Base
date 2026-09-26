<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('order_requests')) {
            return;
        }

        Schema::table('order_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('order_requests', 'selected_size')) {
                $table->string('selected_size', 120)->nullable()->after('quantity');
            }
            if (!Schema::hasColumn('order_requests', 'request_reason')) {
                $table->string('request_reason', 60)->nullable()->after('source');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('order_requests')) {
            return;
        }

        Schema::table('order_requests', function (Blueprint $table) {
            foreach (['request_reason', 'selected_size'] as $column) {
                if (Schema::hasColumn('order_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
