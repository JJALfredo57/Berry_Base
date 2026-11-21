<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SelectedPayoutService
{
    public function create(string $shopId, ?array $selectedIds, array $audit): ?int
    {
        app(SellerBalanceService::class)->sync($shopId);

        return DB::transaction(function () use ($shopId, $selectedIds, $audit) {
            $shop = DB::table('shops')->where('id', $shopId)->first();
            if (! $shop || ! empty($shop->payout_paused) || empty($shop->payout_method)
                || empty($shop->payout_account_name) || empty($shop->payout_account_number)
                || empty($shop->payout_details_verified)) {
                return null;
            }
            if ($audit['source'] === 'seller' && empty($selectedIds)) {
                throw new \DomainException('Select at least one eligible order.');
            }
            $ids = $selectedIds === null
                ? DB::table('seller_payout_ledgers')->where('shop_id', $shopId)->where('status', 'available')->where('seller_net_amount', '>', 0)->pluck('id')->all()
                : array_values(array_unique($selectedIds));
            if (! $ids) {
                return null;
            }
            $candidates = DB::table('seller_payout_ledgers')->where('shop_id', $shopId)->whereIn('id', $ids)->get();
            if ($candidates->count() !== count($ids)) {
                throw new \DomainException('Some selected orders are unavailable.');
            }
            foreach ($candidates->sortBy('order_id') as $candidate) {
                $this->checkOrder($candidate);
            }
            // Serialize selections and refund recovery offsets per shop.
            $shop = DB::table('shops')->where('id', $shopId)->lockForUpdate()->first();
            if (! $shop || ! empty($shop->payout_paused) || empty($shop->payout_details_verified)) {
                throw new \DomainException('Payout account verification or availability changed. Refresh and try again.');
            }
            $ledgers = DB::table('seller_payout_ledgers')->where('shop_id', $shopId)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            foreach ($ledgers as $ledger) {
                if ($ledger->status !== 'available' || $ledger->payout_id || (float) $ledger->seller_net_amount <= 0) {
                    throw new \DomainException('A selected order was already requested or is no longer eligible. Refresh the list.');
                }
            }
            $recoveries = DB::table('seller_payout_ledgers')->where('shop_id', $shopId)->where('status', 'available')
                ->whereNull('payout_id')->where('seller_net_amount', '<', 0)->orderBy('id')->lockForUpdate()->get();
            $ledgers = $ledgers->concat($recoveries);
            $net = round((float) $ledgers->sum('seller_net_amount'), 2);
            if ($net <= 0) {
                throw new \DomainException('Selected earnings do not cover the outstanding refund recovery.');
            }
            $payload = [
                'shop_id' => $shopId, 'seller_id' => $shop->seller_id, 'mode' => 'manual', 'status' => 'requested',
                'gross_amount' => round((float) $ledgers->sum('gross_amount'), 2),
                'commission_amount' => round((float) $ledgers->sum('commission_amount'), 2), 'net_amount' => $net,
                'payout_method' => $shop->payout_method, 'payout_institution' => $shop->payout_institution ?: 'GCash',
                'payout_account_name' => $shop->payout_account_name, 'payout_account_number' => $shop->payout_account_number,
                'admin_note' => $audit['note'], 'requested_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ];
            if (Schema::hasColumn('seller_payouts', 'request_source')) {
                $payload['request_source'] = $audit['source'];
            }
            if (Schema::hasColumn('seller_payouts', 'requested_by_role')) {
                $payload['requested_by_role'] = $audit['role'];
            }
            if (Schema::hasColumn('seller_payouts', 'requested_by_user_id')) {
                $payload['requested_by_user_id'] = $audit['actor'];
            }
            $id = DB::table('seller_payouts')->insertGetId($payload);
            foreach ($ledgers as $ledger) {
                DB::table('seller_payout_items')->insert([
                    'payout_id' => $id, 'ledger_id' => $ledger->id, 'order_id' => $ledger->source_order_id ?? $ledger->order_id,
                    'net_amount' => $ledger->seller_net_amount, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('seller_payout_ledgers')->whereIn('id', $ledgers->pluck('id')->all())
                ->update(['status' => 'requested', 'payout_id' => $id, 'updated_at' => now()]);
            app(SellerBalanceService::class)->event($shopId, null, 'payout_requested', $net, 'requested', 'Selected orders reserved for transfer.', $id);

            return $id;
        }, 3);
    }

    private function checkOrder(object $ledger): void
    {
        $order = DB::table('orders')->where('id', $ledger->order_id)->where('shop_id', $ledger->shop_id)->lockForUpdate()->first();
        if (! $order) {
            throw new \DomainException('A payout order is missing. Cancel and reconcile this request.');
        }
        $order->online_paid_amount = (float) ($ledger->online_collected_amount ?? 0);
        $verified = (float) DB::table('payment_transactions')->where('order_id', $order->id)
            ->where('payment_status', 'paid')->whereRaw('UPPER(method) = ?', ['GCASH'])->sum('amount');
        if ($verified <= 0 || abs($verified - $order->online_paid_amount) >= .01) {
            throw new \DomainException('Verified payment changed. Cancel and reconcile this payout before transfer.');
        }
        if (app(SellerBalanceService::class)->eligibility($order)[0] !== 'available') {
            throw new \DomainException('Transfer blocked: an order is incomplete or has an active refund/cancellation issue.');
        }
        $shop = DB::table('shops')->where('id', $ledger->shop_id)->first();
        if (! $shop) {
            throw new \DomainException('Seller shop is unavailable.');
        }
        $shop->commission_rate = $ledger->commission_rate;
        $shop->commission_enabled = (float) $ledger->commission_rate > 0;
        $current = app(OrderSettlementService::class)->snapshot($order, $shop, app(OrderDiscountFundingService::class)->forOrder((string) $order->id));
        $refundFraction = min(1, app(SellerBalanceService::class)->refundedAmount((string) $order->id) / $verified);
        foreach (['seller_net_amount', 'commission_amount'] as $field) {
            $current[$field] = round($current[$field] * (1 - $refundFraction), 2);
        }
        if (abs((float) $ledger->seller_net_amount - $current['seller_net_amount']) >= .01
            || abs((float) $ledger->commission_amount - $current['commission_amount']) >= .01) {
            throw new \DomainException('Transfer blocked: order allocation changed. Cancel and reconcile this request.');
        }
    }

    /** Call in the same transaction as the final paid state update. */
    public function assertTransferEligible(int $payoutId): void
    {
        $items = DB::table('seller_payout_ledgers')->where('payout_id', $payoutId)->orderBy('order_id')->get();
        if ($items->isEmpty()) {
            throw new \DomainException('This payout has no linked allocations.');
        }
        foreach ($items as $item) {
            if ((float) $item->seller_net_amount > 0) {
                $this->checkOrder($item);
            }
        }
        $payout = DB::table('seller_payouts')->where('id', $payoutId)->lockForUpdate()->first();
        if (! $payout || ! in_array($payout->status, ['requested', 'processing'], true)) {
            throw new \DomainException('This payout is no longer awaiting transfer.');
        }
        $locked = DB::table('seller_payout_ledgers')->where('payout_id', $payoutId)->orderBy('id')->lockForUpdate()->get();
        $original = DB::table('seller_payout_items')->where('payout_id', $payoutId)->get();
        if ($locked->count() !== $original->count()
            || abs((float) $locked->sum('seller_net_amount') - (float) $payout->net_amount) >= .01
            || abs((float) $original->sum('net_amount') - (float) $payout->net_amount) >= .01) {
            throw new \DomainException('Transfer blocked: allocation totals do not match the original request.');
        }
        $originalByLedger = $original->keyBy('ledger_id');
        foreach ($locked as $ledger) {
            $item = $originalByLedger->get($ledger->id);
            if (! $item || $ledger->shop_id !== $payout->shop_id || ! in_array($ledger->status, ['requested', 'processing'], true)
                || abs((float) $item->net_amount - (float) $ledger->seller_net_amount) >= .01) {
                throw new \DomainException('Transfer blocked: order allocation differs from the original payout item.');
            }
        }
        $shop = DB::table('shops')->where('id', $payout->shop_id)->lockForUpdate()->first();
        if (! $shop || ! empty($shop->payout_paused) || empty($shop->payout_details_verified)
            || $shop->payout_account_number !== $payout->payout_account_number
            || $shop->payout_account_name !== $payout->payout_account_name) {
            throw new \DomainException('Transfer blocked: payout account changed or is unverified. Cancel and request again.');
        }
    }

    public function cancel(int $payoutId, ?string $shopId = null): void
    {
        DB::transaction(function () use ($payoutId, $shopId) {
            $items = DB::table('seller_payout_ledgers')->where('payout_id', $payoutId)->orderBy('order_id')->get();
            foreach ($items as $item) {
                DB::table('orders')->where('id', $item->order_id)->lockForUpdate()->first();
            }
            $payout = DB::table('seller_payouts')->where('id', $payoutId)->when($shopId, fn ($q) => $q->where('shop_id', $shopId))->lockForUpdate()->first();
            if (! $payout || ! in_array($payout->status, ['requested', 'processing'], true)) {
                throw new \DomainException('Only untransferred requests can be cancelled.');
            }
            if ($shopId && $payout->status !== 'requested') {
                throw new \DomainException('This transfer is processing. Contact Super Admin before cancelling it.');
            }
            DB::table('seller_payouts')->where('id', $payoutId)->update(['status' => 'cancelled', 'updated_at' => now()]);
            DB::table('seller_payout_ledgers')->where('payout_id', $payoutId)->update(['payout_id' => null, 'status' => 'available', 'updated_at' => now()]);
            app(SellerBalanceService::class)->event($payout->shop_id, null, 'payout_cancelled', (float) $payout->net_amount, 'cancelled', 'Untransferred request cancelled; original items retained for audit.', $payoutId);
            foreach ($items as $item) {
                app(SellerBalanceService::class)->syncOrder($item->order_id);
            }
        }, 3);
    }
}
