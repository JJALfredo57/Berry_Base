<?php

namespace App\Console\Commands;

use App\Services\OrderDiscountFundingService;
use App\Services\OrderSettlementService;
use App\Services\SellerBalanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PreviewSellerBalances extends Command
{
    protected $signature = 'payouts:preview {--shop= : Limit to one shop}';

    protected $description = 'Read-only preview of verified payment allocations; transferred history is never rewritten';

    public function handle(OrderSettlementService $settlements, SellerBalanceService $balances): int
    {
        $this->line('Read-only preview | Environment: '.app()->environment().' | Driver: '.DB::connection()->getDriverName());
        $rows = [];
        DB::table('orders')->when($this->option('shop'), fn ($q) => $q->where('shop_id', $this->option('shop')))
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('payment_transactions as pt')->whereColumn('pt.order_id', 'orders.id')
                ->where('pt.payment_status', 'paid')->whereRaw('UPPER(pt.method) = ?', ['GCASH']))
            ->orderBy('id')->chunkById(100, function ($orders) use (&$rows, $settlements, $balances) {
                foreach ($orders as $order) {
                    $shop = DB::table('shops')->where('id', $order->shop_id)->first();
                    if (! $shop) {
                        continue;
                    }
                    $ledger = DB::table('seller_payout_ledgers')->where('order_id', $order->id)->first();
                    if ((int) ($ledger->allocation_version ?? 0) === 1) {
                        $shop->commission_rate = $ledger->commission_rate;
                        $shop->commission_enabled = (float) $ledger->commission_rate > 0;
                    }
                    $order->online_paid_amount = (float) DB::table('payment_transactions')->where('order_id', $order->id)
                        ->where('payment_status', 'paid')->whereRaw('UPPER(method) = ?', ['GCASH'])->sum('amount');
                    $snapshot = $settlements->snapshot($order, $shop, app(OrderDiscountFundingService::class)->forOrder((string) $order->id));
                    $protected = $ledger && ($ledger->payout_id || in_array($ledger->status, ['paid', 'cash_settled'], true));
                    [$state, $reason] = $balances->eligibility($order);
                    $rows[] = [$order->id, $ledger->status ?? 'missing', $state,
                        number_format((float) ($ledger->seller_net_amount ?? 0), 2), number_format($snapshot['seller_net_amount'], 2),
                        $protected ? 'PROTECTED: reconcile separately' : ($reason ?: 'Eligible after sync')];
                }
            });
        $this->table(['Order', 'Current state', 'Eligibility', 'Current net', 'Pre-refund net', 'Action / reason'], $rows);
        $this->info(count($rows).' verified orders inspected. No database records changed. Refund adjustments are applied by sync; paid/requested amounts remain protected.');

        return self::SUCCESS;
    }
}
