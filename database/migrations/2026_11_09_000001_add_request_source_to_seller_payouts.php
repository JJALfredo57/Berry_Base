<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('seller_payouts')) return;
        Schema::table('seller_payouts', function (Blueprint $table) {
            if (!Schema::hasColumn('seller_payouts', 'request_source')) $table->string('request_source', 30)->nullable()->after('mode');
            if (!Schema::hasColumn('seller_payouts', 'requested_by_role')) $table->string('requested_by_role', 30)->nullable()->after('request_source');
            if (!Schema::hasColumn('seller_payouts', 'requested_by_user_id')) $table->string('requested_by_user_id', 30)->nullable()->after('requested_by_role');
        });
    }
    public function down(): void {
        if (!Schema::hasTable('seller_payouts')) return;
        Schema::table('seller_payouts', function (Blueprint $table) {
            foreach (['requested_by_user_id','requested_by_role','request_source'] as $column) if (Schema::hasColumn('seller_payouts', $column)) $table->dropColumn($column);
        });
    }
};
