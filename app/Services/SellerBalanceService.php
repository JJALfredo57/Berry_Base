<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Shared allocation and eligibility rules; paid transfers are immutable. */
class SellerBalanceService
{
    public static function awaitingCompletionLabel(?string $fulfillmentType): string
    {
        return match ($fulfillmentType) {
            'Pickup' => 'Waiting for Pickup',
            'Delivery' => 'Waiting for Delivery',
            default => 'Awaiting Order Completion',
        };
    }

    public function issue(object $order): ?string
    {
        if (in_array($order->cancel_status ?? '', ['pending', 'accepted'], true)
            || ($order->seller_cancel_state ?? '') === 'awaiting_customer') {
            return 'Cancellation / refund review';
        }
        if (Schema::hasTable('order_refunds') && DB::table('order_refunds')->where('order_id', $order->id)
            ->whereIn('status', ['pending', 'pending_seller_approval', 'approved_for_admin_refund', 'processing'])->exists()) {
            return 'Active refund request';
        }
        if (Schema::hasTable('post_delivery_return_cases') && DB::table('post_delivery_return_cases')->where('order_id', $order->id)
            ->whereNotIn('status', ['seller_rejected', 'rejected', 'refunded'])->exists()) {
            return 'Return / refund case';
        }
        if (! empty($order->issue_status) && ! in_array($order->issue_status, ['resolved', 'rejected'], true)) {
            return 'Unresolved order issue';
        }

        return null;
    }

    public function eligibility(object $order): array
    {
        if ($issue = $this->issue($order)) {
            return ['refund_reserved', $issue];
        }
        $online = (float) ($order->online_paid_amount ?? 0);
        if ($online > (float) ($order->total_price ?? 0) + .009) {
            return ['finance_review', 'Online receipts exceed the order total; reconcile before payout'];
        }
        if (in_array($order->status ?? '', ['Cancelled', 'No Show', 'Returned to Shop'], true)) {
            return ['refund_reserved', 'Order not eligible for payout'];
        }
        if (! in_array($order->status ?? '', ['Delivered', 'Picked Up'], true)) {
            return ['awaiting_completion', self::awaitingCompletionLabel($order->fulfillment_type ?? null)];
        }
        if (($order->payment_status ?? '') !== 'Paid') {
            return ['awaiting_completion', 'Payment balance not settled'];
        }
        if (strtoupper($order->payment_method ?? '') === 'GCASH' && $online + .009 < (float) $order->total_price) {
            return ['awaiting_completion', 'Verified online payment is incomplete'];
        }
        if (strtoupper($order->payment_method ?? '') === 'COD' && ($order->fulfillment_type ?? '') === 'Delivery') {
            $cash = max(0, (float) $order->total_price - (float) ($order->online_paid_amount ?? 0) - (float) ($order->delivery_fee ?? 0));
            if ($cash > 0.009) {
                $remittance = Schema::hasTable('rider_remittances') ? DB::table('rider_remittances')->where('order_id', $order->id)->where('status', 'confirmed')->first() : null;
                if (! $remittance) {
                    return ['awaiting_completion', 'Awaiting seller confirmation of COD cash'];
                }
                if (abs((float) ($remittance->cake_remittance_amount ?? $remittance->amount ?? 0) - $cash) >= .01) {
                    return ['finance_review', 'COD cake cash does not match the confirmed handover'];
                }
            }
        }

        return ['available', null];
    }

    public function syncOrder(string $orderId): bool
    {
        if (! Schema::hasColumn('seller_payout_ledgers', 'allocation_version')) {
            return false;
        }

        return DB::transaction(function () use ($orderId) {
            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            if (! $order || empty($order->shop_id)) {
                return false;
            }
            $shop = DB::table('shops')->where('id', $order->shop_id)->first();
            if (! $shop) {
                return false;
            }
            $ledger = DB::table('seller_payout_ledgers')->where('order_id', $orderId)->lockForUpdate()->first();
            if ($ledger && in_array($ledger->status, ['paid', 'cash_settled'], true)) {
                return false;
            }

            // The payment receipt, not an order status or browser redirect, is
            // the source of online funds. Freeze the assigned rate on first allocation.
            $order->online_paid_amount = round((float) DB::table('payment_transactions')->where('order_id', $orderId)
                ->where('payment_status', 'paid')->whereRaw('UPPER(method) = ?', ['GCASH'])->sum('amount'), 2);
            if ($order->online_paid_amount <= 0) {
                if ($ledger && $ledger->blocked_reason !== 'Missing verified online payment evidence') {
                    DB::table('seller_payout_ledgers')->where('id', $ledger->id)->update([
                        'status' => $ledger->payout_id ? $ledger->status : 'finance_review',
                        'blocked_reason' => 'Missing verified online payment evidence', 'release_at' => null, 'updated_at' => now(),
                    ]);
                    $this->event($order->shop_id, $orderId, 'transfer_blocked', (float) $ledger->seller_net_amount,
                        'finance_review', 'Missing verified online payment evidence', $ledger->payout_id);
                }

                return false;
            }
            if ($ledger && $ledger->payout_id) {
                [$state, $reason] = $this->eligibility($order);
                // Keep the transfer snapshot intact. A new issue blocks both
                // receipt scanning and final payment until the request is cancelled.
                if ($ledger->blocked_reason !== $reason) {
                    DB::table('seller_payout_ledgers')->where('id', $ledger->id)->update(['blocked_reason' => $reason, 'updated_at' => now()]);
                    if ($reason) {
                        $this->event($order->shop_id, $orderId, 'transfer_blocked', (float) $ledger->seller_net_amount, $state, $reason, (int) $ledger->payout_id);
                    }
                }

                return false;
            }
            if ($ledger && (int) $ledger->allocation_version === 1) {
                $shop->commission_rate = $ledger->commission_rate;
                $shop->commission_enabled = (float) $ledger->commission_rate > 0;
            }
            $snapshot = app(OrderSettlementService::class)->snapshot($order, $shop, app(OrderDiscountFundingService::class)->forOrder($orderId));
            $snapshot += ['online_funds_refunded' => 0, 'seller_refund_amount' => 0, 'commission_refund_amount' => 0];
            [$state, $reason] = $this->eligibility($order);
            $refunded = $this->refundedAmount($orderId);
            if ($refunded > 0) {
                $fraction = min(1, $refunded / $order->online_paid_amount);
                $snapshot['online_funds_refunded'] = min($refunded, $order->online_paid_amount);
                $snapshot['seller_refund_amount'] = round($snapshot['seller_net_amount'] * $fraction, 2);
                $snapshot['commission_refund_amount'] = round($snapshot['commission_amount'] * $fraction, 2);
                foreach (['seller_net_amount', 'commission_amount', 'rider_online_fee_amount', 'delivery_fund_amount', 'platform_funded_discount'] as $field) {
                    $snapshot[$field] = round($snapshot[$field] * (1 - $fraction), 2);
                }
                $snapshot['total_seller_earnings'] = round($snapshot['cash_to_seller_amount'] + $snapshot['seller_net_amount'], 2);
                $state = $snapshot['seller_net_amount'] > 0 ? $state : 'refunded';
            }
            $values = $snapshot + [
                'shop_id' => $order->shop_id, 'seller_id' => $shop->seller_id,
                'status' => $state, 'blocked_reason' => $reason, 'allocation_version' => 1,
                'release_at' => null, 'updated_at' => now(),
                'notes' => 'Verified online allocation. Direct cash is excluded from payout. No time-based hold.',
            ];
            if ($ledger) {
                DB::table('seller_payout_ledgers')->where('id', $ledger->id)->update($values);
            } else {
                DB::table('seller_payout_ledgers')->insert($values + ['order_id' => $orderId, 'created_at' => now()]);
            }
            if (! $ledger || $ledger->status !== $state || abs((float) $ledger->seller_net_amount - $snapshot['seller_net_amount']) >= .01) {
                $this->event($order->shop_id, $orderId, 'allocation', $snapshot['seller_net_amount'], $state,
                    $reason ?: 'Verified payment allocated; commission uses the full cake amount.');
            }

            return ! $ledger;
        }, 3);
    }

    public function sync(?string $shopId = null): int
    {
        if (! Schema::hasColumn('seller_payout_ledgers', 'allocation_version')) {
            return 0;
        }
        $count = 0;
        DB::table('orders as o')->whereNotNull('o.shop_id')
            ->when($shopId, fn ($q) => $q->where('o.shop_id', $shopId))
            ->where(fn ($q) => $q->whereExists(fn ($q) => $q->selectRaw('1')->from('payment_transactions as pt')
                ->whereColumn('pt.order_id', 'o.id')->where('pt.payment_status', 'paid')->whereRaw('UPPER(pt.method) = ?', ['GCASH']))
                ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('seller_payout_ledgers as l')->whereColumn('l.order_id', 'o.id')->whereNotIn('l.status', ['paid', 'cash_settled'])))
            ->select('o.id')->orderBy('o.id')->chunkById(100, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    $count += (int) $this->syncOrder((string) $order->id);
                }
            }, 'o.id', 'id');

        return $count;
    }

    public function refundedAmount(string $orderId): float
    {
        $amount = Schema::hasTable('order_refunds')
            ? (float) DB::table('order_refunds')->where('order_id', $orderId)->where('status', 'refunded')->sum('refund_amount') : 0;
        if (Schema::hasTable('post_delivery_return_cases')) {
            $amount += (float) DB::table('post_delivery_return_cases')->where('order_id', $orderId)
                ->where('status', 'refunded')->sum(DB::raw('COALESCE(approved_amount, requested_amount, 0)'));
        }

        return round(max(0, $amount), 2);
    }

    public function event(string $shopId, ?string $orderId, string $type, float $amount, string $state, string $note, ?int $payoutId = null): void
    {
        if (! Schema::hasTable('seller_balance_events')) {
            return;
        }
        DB::table('seller_balance_events')->insert([
            'shop_id' => $shopId, 'order_id' => $orderId, 'payout_id' => $payoutId,
            'type' => $type, 'amount' => $amount, 'status' => $state, 'note' => $note,
            'created_at' => now(),
        ]);
    }
}
