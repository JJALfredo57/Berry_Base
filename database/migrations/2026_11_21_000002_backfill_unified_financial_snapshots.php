<?php

use App\Services\OrderSettlementService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seller_payout_ledgers') || ! Schema::hasTable('orders') || ! Schema::hasTable('shops')) {
            return;
        }
        if (! Schema::hasColumn('seller_payout_ledgers', 'online_collected_amount')) {
            return;
        }

        DB::table('seller_payout_ledgers')
            ->where('online_collected_amount', 0)
            ->where('cash_collected_amount', 0)
            ->where('cash_to_seller_amount', 0)
            ->orderBy('id')
            ->chunkById(100, function ($ledgers) {
                foreach ($ledgers as $ledger) {
                    $order = DB::table('orders')->where('id', $ledger->order_id)->first();
                    $shop = $order ? DB::table('shops')->where('id', $order->shop_id)->first() : null;
                    if (! $order || ! $shop) {
                        continue;
                    }

                    $snapshot = app(OrderSettlementService::class)->snapshot($order, $shop);
                    // Preserve historically paid/requested financial amounts. This
                    // migration only fills the new report-only source breakdown.
                    DB::table('seller_payout_ledgers')->where('id', $ledger->id)->update([
                        'online_collected_amount' => $snapshot['online_collected_amount'],
                        'cash_collected_amount' => $snapshot['cash_collected_amount'],
                        'cash_to_seller_amount' => $snapshot['cash_to_seller_amount'],
                        'rider_cash_fee_amount' => $snapshot['rider_cash_fee_amount'],
                        'rider_online_fee_amount' => $snapshot['rider_online_fee_amount'],
                        'total_seller_earnings' => round((float) $snapshot['cash_to_seller_amount'] + max(0, (float) $ledger->seller_net_amount), 2),
                        'remaining_balance' => $snapshot['remaining_balance'],
                        'updated_at' => now(),
                    ]);
                }
            }, 'id');
    }

    public function down(): void
    {
        // Snapshot columns are shared with current orders; historical values
        // should not be cleared on rollback.
    }
};
