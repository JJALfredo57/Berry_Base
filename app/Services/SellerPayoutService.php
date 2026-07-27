<?php
namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SellerPayoutService
{
    public function settings(): object
    {
        $defaults = (object) [
            'payout_mode' => 'manual',
            'payout_hold_days' => 3,
            'payout_minimum_amount' => 500,
            'payout_schedule' => 'weekly',
            'payout_first_approval_required' => true,
            'payout_auto_paused' => true,
        ];

        $settings = DB::table('platform_settings')->orderByDesc('updated_at')->orderBy('id')->first();
        if (!$settings) return $defaults;

        $mode = strtolower(trim((string) ($settings->payout_mode ?? $defaults->payout_mode)));
        $settings->payout_mode = in_array($mode, ['manual', 'automatic'], true) ? $mode : 'manual';
        $settings->payout_hold_days = (int) ($settings->payout_hold_days ?? $defaults->payout_hold_days);
        $settings->payout_minimum_amount = (float) ($settings->payout_minimum_amount ?? $defaults->payout_minimum_amount);
        $settings->payout_schedule = (string) ($settings->payout_schedule ?? $defaults->payout_schedule);
        $settings->payout_first_approval_required = (bool) ($settings->payout_first_approval_required ?? $defaults->payout_first_approval_required);
        $settings->payout_auto_paused = (bool) ($settings->payout_auto_paused ?? $defaults->payout_auto_paused);

        return $settings;
    }

    public function syncDeliveredPaidOrders(): int
    {
        $settings = $this->settings();
        $holdDays = max(0, (int) ($settings->payout_hold_days ?? 3));
        $hasDeliveredAt = Schema::hasColumn('orders', 'delivered_at');
        $hasSettledAt = Schema::hasColumn('orders', 'settled_at');

        $orders = DB::table('orders as o')
            ->join('shops as s', 's.id', '=', 'o.shop_id')
            ->whereNotNull('o.shop_id')
            ->whereIn('o.payment_status', ['Paid', 'Partial Payment'])
            ->whereNotIn('o.status', ['Cancelled'])
            ->where(function ($q) use ($hasDeliveredAt, $hasSettledAt) {
                $q->where('o.status', 'Delivered');
                if ($hasDeliveredAt) {
                    $q->orWhereNotNull('o.delivered_at');
                }
                if ($hasSettledAt) {
                    $q->orWhereNotNull('o.settled_at');
                }
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('seller_payout_ledgers as spl')
                    ->whereColumn('spl.order_id', 'o.id');
            })
            ->select(
                'o.*',
                's.seller_id',
                's.commission_rate',
                's.commission_enabled'
            )
            ->limit(250)
            ->get();

        $created = 0;
        foreach ($orders as $order) {
            $gross = $this->collectedAmount($order);
            if ($gross <= 0) continue;

            $commissionBase = max(0, $gross - (float) ($order->delivery_fee ?? 0) - (float) ($order->service_charge ?? 0));
            $rate = !empty($order->commission_enabled) ? (float) ($order->commission_rate ?? 0) : 0;
            $commission = round($commissionBase * $rate / 100, 2);
            $net = max(0, round($gross - $commission, 2));

            $deliveredAt = ($hasDeliveredAt ? ($order->delivered_at ?? null) : null)
                ?? ($hasSettledAt ? ($order->settled_at ?? null) : null)
                ?? $order->updated_at
                ?? now();
            $releaseAt = Carbon::parse($deliveredAt)->addDays($holdDays);
            $status = $releaseAt->isFuture() ? 'clearing' : 'available';

            DB::table('seller_payout_ledgers')->insert([
                'order_id' => $order->id,
                'shop_id' => $order->shop_id,
                'seller_id' => $order->seller_id,
                'gross_amount' => $gross,
                'commission_base' => $commissionBase,
                'commission_rate' => $rate,
                'commission_amount' => $commission,
                'seller_net_amount' => $net,
                'status' => $status,
                'release_at' => $releaseAt,
                'notes' => 'Generated after paid delivered order. Delivery/service fees are excluded from commission base.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $created++;
        }

        DB::table('seller_payout_ledgers')
            ->where('status', 'clearing')
            ->where('release_at', '<=', now())
            ->update(['status' => 'available', 'updated_at' => now()]);

        return $created;
    }

    public function summaryForShop(string $shopId): array
    {
        $this->syncDeliveredPaidOrders();
        return [
            'pending' => (float) DB::table('seller_payout_ledgers')->where('shop_id', $shopId)->whereIn('status', ['pending', 'clearing'])->sum('seller_net_amount'),
            'available' => (float) DB::table('seller_payout_ledgers')->where('shop_id', $shopId)->where('status', 'available')->sum('seller_net_amount'),
            'processing' => (float) DB::table('seller_payout_ledgers')->where('shop_id', $shopId)->whereIn('status', ['requested', 'processing'])->sum('seller_net_amount'),
            'paid' => (float) DB::table('seller_payout_ledgers')->where('shop_id', $shopId)->where('status', 'paid')->sum('seller_net_amount'),
        ];
    }

    public function availableLedgersForShop(string $shopId): Collection
    {
        $this->syncDeliveredPaidOrders();
        return DB::table('seller_payout_ledgers')
            ->where('shop_id', $shopId)
            ->where('status', 'available')
            ->orderBy('release_at')
            ->get();
    }

    public function createPayoutForShop(string $shopId, string $mode, ?string $note = null): ?int
    {
        $shop = DB::table('shops')->where('id', $shopId)->first();
        if (!$shop || !empty($shop->payout_paused)) return null;
        if (
            empty($shop->payout_method) ||
            empty($shop->payout_account_name) ||
            empty($shop->payout_account_number) ||
            empty($shop->payout_details_verified)
        ) {
            return null;
        }

        $ledgers = $this->availableLedgersForShop($shopId);
        if ($ledgers->isEmpty()) return null;

        $settings = $this->settings();
        $net = round((float) $ledgers->sum('seller_net_amount'), 2);
        if ($net < (float) ($settings->payout_minimum_amount ?? 0)) return null;

        $payoutId = null;
        DB::transaction(function () use ($shop, $ledgers, $mode, $net, $note, &$payoutId) {
            $gross = round((float) $ledgers->sum('gross_amount'), 2);
            $commission = round((float) $ledgers->sum('commission_amount'), 2);
            $status = $mode === 'automatic' ? 'processing' : 'requested';

            $payoutId = DB::table('seller_payouts')->insertGetId([
                'shop_id' => $shop->id,
                'seller_id' => $shop->seller_id,
                'mode' => $mode,
                'status' => $status,
                'gross_amount' => $gross,
                'commission_amount' => $commission,
                'net_amount' => $net,
                'payout_method' => $shop->payout_method,
                'payout_institution' => $shop->payout_institution ?: 'GCash',
                'payout_account_name' => $shop->payout_account_name,
                'payout_account_number' => $shop->payout_account_number,
                'admin_note' => $note,
                'requested_at' => now(),
                'processed_at' => $mode === 'automatic' ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($ledgers as $ledger) {
                DB::table('seller_payout_items')->insert([
                    'payout_id' => $payoutId,
                    'ledger_id' => $ledger->id,
                    'order_id' => $ledger->order_id,
                    'net_amount' => $ledger->seller_net_amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('seller_payout_ledgers')
                ->whereIn('id', $ledgers->pluck('id')->all())
                ->update([
                    'status' => $status === 'processing' ? 'processing' : 'requested',
                    'payout_id' => $payoutId,
                    'updated_at' => now(),
                ]);
        });

        return $payoutId;
    }

    public function runAutomaticPreparation(): int
    {
        $settings = $this->settings();
        if (($settings->payout_mode ?? 'manual') !== 'automatic' || !empty($settings->payout_auto_paused)) {
            return 0;
        }

        $this->syncDeliveredPaidOrders();
        $shopIds = DB::table('seller_payout_ledgers as l')
            ->join('shops as s', 's.id', '=', 'l.shop_id')
            ->where('l.status', 'available')
            ->where('s.payout_details_verified', 1)
            ->where('s.payout_paused', 0)
            ->groupBy('l.shop_id')
            ->pluck('l.shop_id');

        $count = 0;
        foreach ($shopIds as $shopId) {
            if ($this->createPayoutForShop($shopId, 'automatic', 'Prepared by automatic payout mode. PayMongo disbursement API is pending final wallet setup.')) {
                $count++;
            }
        }

        return $count;
    }

    public function automaticPreparationBlockers(): array
    {
        $settings = $this->settings();
        $this->syncDeliveredPaidOrders();

        $blockers = [];
        if (($settings->payout_mode ?? 'manual') !== 'automatic') {
            $blockers[] = 'Payout mode is currently Manual.';
        }
        if (!empty($settings->payout_auto_paused)) {
            $blockers[] = 'Automatic payouts are paused globally.';
        }

        $availableLedgers = DB::table('seller_payout_ledgers')->where('status', 'available')->count();
        if ($availableLedgers === 0) {
            $blockers[] = 'No available ledgers yet. Orders must be paid, delivered, and past the hold period.';
        }

        $minimum = (float) ($settings->payout_minimum_amount ?? 0);
        $eligibleShops = DB::table('seller_payout_ledgers as l')
            ->join('shops as s', 's.id', '=', 'l.shop_id')
            ->where('l.status', 'available')
            ->where('s.payout_details_verified', 1)
            ->where('s.payout_paused', 0)
            ->select('l.shop_id')
            ->groupBy('l.shop_id')
            ->havingRaw('SUM(l.seller_net_amount) >= ?', [$minimum])
            ->count();

        if ($availableLedgers > 0 && $eligibleShops === 0) {
            $blockers[] = 'Available balances exist, but no seller is verified, unpaused, and above the minimum payout amount.';
        }

        return $blockers;
    }

    private function collectedAmount(object $order): float
    {
        if (($order->payment_status ?? '') === 'Paid') {
            return round((float) $order->total_price, 2);
        }

        if (($order->deposit_status ?? '') === 'paid') {
            return round((float) ($order->deposit_amount ?? 0), 2);
        }

        return 0.0;
    }
}
