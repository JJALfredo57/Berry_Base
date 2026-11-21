<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only, order-level view of money that has entered the platform.
 *
 * This intentionally does not create, change, or release any financial
 * record.  A non-zero difference is a finance-review blocker, never profit.
 */
class FinanceReconciliationService
{
    private const TERMINAL_ORDER_STATUSES = ['Delivered', 'Picked Up', 'Cancelled', 'No Show'];

    public function build(?string $from = null, ?string $to = null): array
    {
        if (! Schema::hasTable('payment_transactions')) {
            return ['summary' => $this->emptySummary(), 'rows' => collect()];
        }

        $hasCustomerPaidAmount = Schema::hasColumn('payment_transactions', 'customer_paid_amount');
        $paymentsQuery = DB::table('payment_transactions')
            ->where('payment_status', 'paid')
            ->where('method', 'GCash');
        if ($from && $to) {
            $paymentsQuery->whereBetween(DB::raw('DATE(COALESCE(paid_at, created_at))'), [$from, $to]);
        }
        $payments = $paymentsQuery
            ->select('order_id')
            ->selectRaw('COUNT(*) AS payment_count')
            ->selectRaw('SUM(amount) AS online_settled')
            ->selectRaw('SUM(COALESCE(payment_service_fee, 0)) AS service_fee')
            ->selectRaw($hasCustomerPaidAmount
                ? 'SUM(COALESCE(customer_paid_amount, amount + COALESCE(payment_service_fee, 0))) AS customer_charged'
                : 'SUM(amount + COALESCE(payment_service_fee, 0)) AS customer_charged')
            ->groupBy('order_id')
            ->get()
            ->keyBy('order_id');

        $orderIds = collect($payments->keys())->filter();
        $normalRefunds = $this->normalRefunds();
        $returnRefunds = $this->returnRefunds();
        $sellerLedgers = $this->sellerLedgers();
        $riderEarnings = $this->riderEarnings();

        if (! $from || ! $to) {
            foreach ([$normalRefunds, $returnRefunds, $sellerLedgers, $riderEarnings] as $records) {
                $orderIds = $orderIds->merge($records->keys());
            }
        }

        $orderIds = $orderIds->filter()->unique()->values();
        $orders = $orderIds->isEmpty()
            ? collect()
            : DB::table('orders')->whereIn('id', $orderIds)->get()->keyBy('id');

        $rows = $orderIds->map(function ($orderId) use ($payments, $normalRefunds, $returnRefunds, $sellerLedgers, $riderEarnings, $orders) {
            $payment = $payments->get($orderId);
            $order = $orders->get($orderId);
            $normalRefund = (float) ($normalRefunds->get($orderId) ?? 0);
            $returnRefund = (float) ($returnRefunds->get($orderId) ?? 0);
            $seller = $sellerLedgers->get($orderId, (object) ['amount' => 0, 'commission' => 0]);
            $rider = $riderEarnings->get($orderId, (object) ['amount' => 0, 'review_amount' => 0]);
            $onlineSettled = round((float) ($payment->online_settled ?? 0), 2);
            // Verified allocations are visible even before fulfilment. Only
            // unallocated receipts remain customer funds, not seller earnings.
            $isHeld = $order && ! $sellerLedgers->has($orderId);
            $refunds = round($normalRefund + $returnRefund, 2);
            $held = $isHeld ? max(0, $onlineSettled - $refunds) : 0.0;
            $sellerAmount = $isHeld ? 0.0 : round((float) ($seller->amount ?? 0), 2);
            $sellerPayable = $isHeld ? 0.0 : round((float) ($seller->payable ?? 0), 2);
            $sellerPaid = $isHeld ? 0.0 : round((float) ($seller->paid ?? 0), 2);
            $riderAllocation = (float) ($seller->rider_allocation ?? 0);
            $riderAmount = $isHeld ? 0.0 : round(max($riderAllocation, (float) ($rider->amount ?? 0)), 2);
            $riderPayable = $isHeld ? 0.0 : round(max(0, $riderAmount - (float) ($rider->paid ?? 0)), 2);
            $riderPaid = $isHeld ? 0.0 : round((float) ($rider->paid ?? 0), 2);
            $commission = $isHeld ? 0.0 : round((float) ($seller->commission ?? 0), 2);
            $platformFunding = $isHeld ? 0.0 : round((float) ($seller->platform_funding ?? 0), 2);
            if ((float) ($seller->original_paid ?? 0) > 0 && $onlineSettled > 0 && $refunds > 0) {
                // Paid transfer snapshots are immutable. Report their refunded
                // commission separately from the original receipt history.
                $commission = round($commission * max(0, 1 - min(1, $refunds / $onlineSettled)), 2);
                $platformFunding = round($platformFunding * max(0, 1 - min(1, $refunds / $onlineSettled)), 2);
            }
            $difference = round($onlineSettled + $platformFunding - $held - $refunds - $sellerAmount - $riderAmount - $commission, 2);

            return (object) [
                'order_id' => (string) $orderId,
                'order_status' => $order->status ?? 'Order record unavailable',
                'online_settled' => $onlineSettled,
                'payment_count' => (int) ($payment->payment_count ?? 0),
                'customer_charged' => round((float) ($payment->customer_charged ?? 0), 2),
                'service_fee' => round((float) ($payment->service_fee ?? 0), 2),
                'normal_refund' => $normalRefund,
                'return_refund' => $returnRefund,
                'refunds' => $refunds,
                'seller_amount' => $sellerAmount,
                'seller_payable' => $sellerPayable,
                'seller_paid' => $sellerPaid,
                'rider_amount' => $riderAmount,
                'rider_payable' => $riderPayable,
                'rider_paid' => $riderPaid,
                'rider_review_amount' => round((float) ($rider->review_amount ?? 0), 2),
                'commission' => $commission,
                'platform_funding' => $platformFunding,
                'held' => $held,
                'difference' => $difference,
                'state' => $this->rowState($order, $difference, (float) ($rider->review_amount ?? 0)),
            ];
        })->sortByDesc(fn ($row) => $row->state !== 'reconciled')
            ->sortByDesc('difference')
            ->values();

        return ['summary' => $this->summary($rows), 'rows' => $rows];
    }

    private function normalRefunds(): Collection
    {
        if (! Schema::hasTable('order_refunds')) {
            return collect();
        }

        return DB::table('order_refunds')
            ->where('status', 'refunded')
            ->select('order_id', DB::raw('SUM(refund_amount) AS amount'))
            ->groupBy('order_id')
            ->pluck('amount', 'order_id');
    }

    private function returnRefunds(): Collection
    {
        if (! Schema::hasTable('post_delivery_return_cases')) {
            return collect();
        }

        return DB::table('post_delivery_return_cases')
            ->where('status', 'refunded')
            ->select('order_id', DB::raw('SUM(COALESCE(approved_amount, requested_amount, 0)) AS amount'))
            ->groupBy('order_id')
            ->pluck('amount', 'order_id');
    }

    private function sellerLedgers(): Collection
    {
        if (! Schema::hasTable('seller_payout_ledgers')) {
            return collect();
        }

        $orderKey = Schema::hasColumn('seller_payout_ledgers', 'source_order_id') ? 'COALESCE(source_order_id, order_id)' : 'order_id';

        return DB::table('seller_payout_ledgers')
            ->where('status', '<>', 'cash_settled')
            ->selectRaw($orderKey.' AS order_id')
            ->selectRaw('SUM(seller_net_amount) AS amount')
            ->selectRaw("SUM(CASE WHEN status IN ('awaiting_completion', 'refund_reserved', 'available', 'requested', 'processing') THEN seller_net_amount ELSE 0 END) AS payable")
            ->selectRaw("SUM(CASE WHEN status = 'paid' THEN seller_net_amount ELSE 0 END) AS paid")
            ->selectRaw("SUM(CASE WHEN status = 'paid' AND seller_net_amount > 0 THEN seller_net_amount ELSE 0 END) AS original_paid")
            ->selectRaw('SUM(COALESCE(commission_amount, 0)) AS commission')
            ->selectRaw(Schema::hasColumn('seller_payout_ledgers', 'platform_funded_discount') ? 'SUM(COALESCE(platform_funded_discount, 0)) AS platform_funding' : '0 AS platform_funding')
            ->selectRaw(Schema::hasColumn('seller_payout_ledgers', 'rider_online_fee_amount') ? 'SUM(COALESCE(rider_online_fee_amount, 0)) AS rider_allocation' : '0 AS rider_allocation')
            ->groupByRaw($orderKey)
            ->get()
            ->keyBy('order_id');
    }

    private function riderEarnings(): Collection
    {
        if (! Schema::hasTable('rider_delivery_earnings')) {
            return collect();
        }

        return DB::table('rider_delivery_earnings')
            ->select('order_id')
            ->selectRaw("SUM(CASE WHEN status IN ('available', 'requested', 'paid') THEN amount ELSE 0 END) AS amount")
            ->selectRaw("SUM(CASE WHEN status IN ('available', 'requested') THEN amount ELSE 0 END) AS payable")
            ->selectRaw("SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) AS paid")
            ->selectRaw("SUM(CASE WHEN status = 'finance_review' THEN amount ELSE 0 END) AS review_amount")
            ->groupBy('order_id')
            ->get()
            ->keyBy('order_id');
    }

    private function rowState(?object $order, float $difference, float $riderReviewAmount): string
    {
        if ($riderReviewAmount > 0) {
            return 'needs_review';
        }
        if (! $order) {
            return 'needs_review';
        }
        if (abs($difference) < 0.01) {
            if (! in_array((string) $order->status, self::TERMINAL_ORDER_STATUSES, true)) {
                return 'held';
            }

            return 'reconciled';
        }

        return 'needs_review';
    }

    private function summary(Collection $rows): array
    {
        $sum = fn (string $field): float => round((float) $rows->sum($field), 2);
        $reviewRows = $rows->where('state', 'needs_review');

        return [
            'online_amount' => $sum('online_settled'),
            'customer_charged' => $sum('customer_charged'),
            'service_fees' => $sum('service_fee'),
            'count' => (int) $rows->sum('payment_count'),
            'refunds_sent' => $sum('refunds'),
            'normal_refunds_sent' => $sum('normal_refund'),
            'return_refunds_sent' => $sum('return_refund'),
            'held_customer_funds' => $sum('held'),
            'seller_amount' => $sum('seller_amount'),
            'seller_payable' => $sum('seller_payable'),
            'seller_paid' => $sum('seller_paid'),
            'rider_amount' => $sum('rider_amount'),
            'rider_payable' => $sum('rider_payable'),
            'rider_paid' => $sum('rider_paid'),
            'commission_recorded' => $sum('commission'),
            'platform_funding' => $sum('platform_funding'),
            'verified_platform_commission' => round((float) $rows->where('state', 'reconciled')->sum('commission'), 2),
            'unreconciled_positive' => round((float) $reviewRows->where('difference', '>', 0)->sum('difference'), 2),
            'unreconciled_negative' => round(abs((float) $reviewRows->where('difference', '<', 0)->sum('difference')), 2),
            'review_count' => $reviewRows->count(),
            'net_online_after_refunds' => round($sum('online_settled') - $sum('refunds'), 2),
            // Kept only for existing view compatibility. It is deliberately not an available balance.
            'platform_available_balance' => 0.0,
        ];
    }

    private function emptySummary(): array
    {
        return [
            'online_amount' => 0.0, 'customer_charged' => 0.0, 'service_fees' => 0.0,
            'count' => 0, 'refunds_sent' => 0.0, 'normal_refunds_sent' => 0.0,
            'return_refunds_sent' => 0.0, 'held_customer_funds' => 0.0,
            'seller_amount' => 0.0, 'seller_payable' => 0.0, 'seller_paid' => 0.0,
            'rider_amount' => 0.0, 'rider_payable' => 0.0, 'rider_paid' => 0.0, 'commission_recorded' => 0.0,
            'platform_funding' => 0.0,
            'verified_platform_commission' => 0.0, 'unreconciled_positive' => 0.0,
            'unreconciled_negative' => 0.0, 'review_count' => 0,
            'net_online_after_refunds' => 0.0, 'platform_available_balance' => 0.0,
        ];
    }
}
