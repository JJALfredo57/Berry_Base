<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private array $columns = [
        'seller_approved_at', 'seller_approved_by_user_id', 'seller_approved_by_role',
        'admin_refund_started_at', 'admin_refund_completed_at', 'admin_refund_user_id',
        'refund_receipt_hash', 'receipt_scan_status', 'receipt_scan_data', 'receipt_scanned_at',
        'customer_refund_confirmed_at',
    ];

    public function up(): void
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            if (!Schema::hasColumn('order_refunds', 'seller_approved_at')) $table->timestamp('seller_approved_at')->nullable()->index();
            if (!Schema::hasColumn('order_refunds', 'seller_approved_by_user_id')) $table->string('seller_approved_by_user_id', 40)->nullable()->index();
            if (!Schema::hasColumn('order_refunds', 'seller_approved_by_role')) $table->string('seller_approved_by_role', 30)->nullable();
            if (!Schema::hasColumn('order_refunds', 'admin_refund_started_at')) $table->timestamp('admin_refund_started_at')->nullable();
            if (!Schema::hasColumn('order_refunds', 'admin_refund_completed_at')) $table->timestamp('admin_refund_completed_at')->nullable();
            if (!Schema::hasColumn('order_refunds', 'admin_refund_user_id')) $table->string('admin_refund_user_id', 40)->nullable()->index();
            if (!Schema::hasColumn('order_refunds', 'refund_receipt_hash')) $table->string('refund_receipt_hash', 64)->nullable();
            if (!Schema::hasColumn('order_refunds', 'receipt_scan_status')) $table->string('receipt_scan_status', 30)->nullable()->index();
            if (!Schema::hasColumn('order_refunds', 'receipt_scan_data')) $table->json('receipt_scan_data')->nullable();
            if (!Schema::hasColumn('order_refunds', 'receipt_scanned_at')) $table->timestamp('receipt_scanned_at')->nullable();
            if (!Schema::hasColumn('order_refunds', 'customer_refund_confirmed_at')) $table->timestamp('customer_refund_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            foreach ($this->columns as $column) if (Schema::hasColumn('order_refunds', $column)) $table->dropColumn($column);
        });
    }
};
