<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SellerPayoutService
{
    public function settings(): object
    {
        $defaults = (object) [
            'payout_mode' => 'manual',
            'payout_hold_days' => 0,
            'payout_minimum_amount' => 0,
            'payout_schedule' => 'weekly',
            'payout_first_approval_required' => true,
            'payout_auto_paused' => true,
        ];

        $settings = DB::table('platform_settings')->orderBy('id')->first();
        if (! $settings) {
            return $defaults;
        }

        $mode = strtolower(trim((string) ($settings->payout_mode ?? $defaults->payout_mode)));
        $settings->payout_mode = 'manual';
        $settings->payout_hold_days = 0;
        $settings->payout_minimum_amount = 0;
        $settings->payout_schedule = (string) ($settings->payout_schedule ?? $defaults->payout_schedule);
        $settings->payout_first_approval_required = (bool) ($settings->payout_first_approval_required ?? $defaults->payout_first_approval_required);
        $settings->payout_auto_paused = (bool) ($settings->payout_auto_paused ?? $defaults->payout_auto_paused);

        return $settings;
    }

    /** Existing callers and scheduler reconcile allocations; no holding period. */
    public function syncDeliveredPaidOrders(): int
    {
        return app(SellerBalanceService::class)->sync();
    }

    public function summaryForShop(string $shopId): array
    {
        app(SellerBalanceService::class)->sync($shopId);
        $rows = DB::table('seller_payout_ledgers')->where('shop_id', $shopId)->get();
        $sum = fn (array $states) => round((float) $rows->whereIn('status', $states)->sum('seller_net_amount'), 2);

        return [
            'pending' => 0.0,
            'awaiting' => $sum(['awaiting_completion']),
            'reserved' => $sum(['refund_reserved']),
            'review' => $sum(['finance_review']),
            'net_online' => max(0, $sum(['awaiting_completion', 'available'])),
            'available' => max(0, $sum(['available'])),
            'processing' => $sum(['requested', 'processing']),
            'paid' => $sum(['paid']),
            'refund_deductions' => abs((float) $rows->where('status', 'available')->where('seller_net_amount', '<', 0)->sum('seller_net_amount')),
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

    public function createPayoutForShop(string $shopId, string $mode, ?string $note = null, string $source = 'system', ?string $actorRole = null, ?string $actorId = null, ?array $selectedIds = null): ?int
    {
        return app(SelectedPayoutService::class)->create($shopId, $selectedIds, [
            'note' => $note, 'source' => $source, 'role' => $actorRole, 'actor' => $actorId,
        ]);
    }

    public function runAutomaticPreparation(): int
    {
        $settings = $this->settings();
        if (($settings->payout_mode ?? 'manual') !== 'automatic' || ! empty($settings->payout_auto_paused)) {
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
        if (! empty($settings->payout_auto_paused)) {
            $blockers[] = 'Automatic payouts are paused globally.';
        }

        $availableLedgers = DB::table('seller_payout_ledgers')->where('status', 'available')->count();
        if ($availableLedgers === 0) {
            $blockers[] = 'No available ledgers yet. Orders must be paid, completed and free of active issues.';
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
        $isCodDelivery = strtoupper((string) ($order->payment_method ?? '')) === 'COD'
            && ($order->fulfillment_type ?? '') === 'Delivery';
        $isCashPickup = ($order->fulfillment_type ?? '') === 'Pickup'
            && in_array(strtoupper((string) ($order->payment_method ?? '')), ['COP', 'COD'], true);

        if ($isCodDelivery) {
            $amount = ($order->deposit_status ?? '') === 'paid'
                ? round((float) ($order->deposit_amount ?? 0), 2)
                : 0.0;

            if (($order->remittance_status ?? '') === 'confirmed'
                && ($order->remittance_method ?? '') === 'gcash_paymongo') {
                $amount += round((float) ($order->remittance_amount ?? 0), 2);
            }

            $total = round((float) ($order->total_price ?? 0), 2);

            return round(min(max(0, $amount), max(0, $total)), 2);
        }

        if ($isCashPickup) {
            return ($order->deposit_status ?? '') === 'paid'
                ? round((float) ($order->deposit_amount ?? 0), 2)
                : 0.0;
        }

        if (($order->payment_status ?? '') === 'Paid') {
            return round((float) $order->total_price, 2);
        }

        if (($order->deposit_status ?? '') === 'paid') {
            return round((float) ($order->deposit_amount ?? 0), 2);
        }

        return 0.0;
    }

    private function ledgerNote(object $order): string
    {
        if (strtoupper((string) ($order->payment_method ?? '')) === 'COD'
            && ($order->fulfillment_type ?? '') === 'Delivery') {
            if (($order->remittance_status ?? '') === 'confirmed'
                && ($order->remittance_method ?? '') === 'gcash_paymongo') {
                return 'Generated from online customer deposit plus PayMongo QR COD remittance. Direct cash handover is excluded from payout.';
            }

            return 'Generated from online customer deposit only. Direct COD cash is excluded from payout.';
        }

        if (($order->fulfillment_type ?? '') === 'Pickup'
            && in_array(strtoupper((string) ($order->payment_method ?? '')), ['COP', 'COD'], true)) {
            return 'Generated from the online deposit only. Cash-on-pickup balance was handed directly to the seller.';
        }

        return 'Generated after paid delivered order. Delivery/service fees are excluded from commission base.';
    }
}
