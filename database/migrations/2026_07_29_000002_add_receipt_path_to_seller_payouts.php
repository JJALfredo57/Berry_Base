<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('seller_payouts') && !Schema::hasColumn('seller_payouts', 'payout_receipt_path')) {
            Schema::table('seller_payouts', function (Blueprint $table) {
                $table->string('payout_receipt_path')->nullable()->after('reference_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('seller_payouts') && Schema::hasColumn('seller_payouts', 'payout_receipt_path')) {
            Schema::table('seller_payouts', function (Blueprint $table) {
                $table->dropColumn('payout_receipt_path');
            });
        }
    }
};
