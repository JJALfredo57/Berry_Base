<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('seller_payouts', function (Blueprint $table) {
            if (!Schema::hasColumn('seller_payouts', 'receipt_scan_status')) $table->string('receipt_scan_status', 30)->nullable()->index();
            if (!Schema::hasColumn('seller_payouts', 'receipt_scan_data')) $table->json('receipt_scan_data')->nullable();
            if (!Schema::hasColumn('seller_payouts', 'receipt_scanned_at')) $table->timestamp('receipt_scanned_at')->nullable();
            if (!Schema::hasColumn('seller_payouts', 'payout_receipt_hash')) $table->string('payout_receipt_hash', 64)->nullable()->index();
        });
    }
    public function down(): void
    {
        Schema::table('seller_payouts', function (Blueprint $table) {
            foreach (['receipt_scan_status', 'receipt_scan_data', 'receipt_scanned_at', 'payout_receipt_hash'] as $column) {
                if (Schema::hasColumn('seller_payouts', $column)) $table->dropColumn($column);
            }
        });
    }
};
