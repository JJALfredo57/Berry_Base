<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('seller_payout_ledgers')) return;
        Schema::table('seller_payout_ledgers', function (Blueprint $table) {
            foreach ([
                'online_collected_amount', 'cash_collected_amount', 'cash_to_seller_amount',
                'rider_cash_fee_amount', 'rider_online_fee_amount', 'total_seller_earnings',
                'remaining_balance',
            ] as $column) {
                if (!Schema::hasColumn('seller_payout_ledgers', $column)) {
                    $table->decimal($column, 12, 2)->default(0);
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('seller_payout_ledgers')) return;
        Schema::table('seller_payout_ledgers', function (Blueprint $table) {
            foreach (['online_collected_amount','cash_collected_amount','cash_to_seller_amount','rider_cash_fee_amount','rider_online_fee_amount','total_seller_earnings','remaining_balance'] as $column) {
                if (Schema::hasColumn('seller_payout_ledgers', $column)) $table->dropColumn($column);
            }
        });
    }
};
