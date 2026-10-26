<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class OrderHistoryPurgeService
{
    private const ORDER_CHILD_TABLES = [
        'order_addons',
        'order_tracking',
        'order_reviews',
        'custom_orders',
        'kitchen_tickets',
        'messages',
        'notifications',
        'mobile_notifications',
        'payment_transactions',
        'customer_payment_qrs',
        'order_refunds',
        'rider_remittances',
        'order_discounts',
        'loyalty_transactions',
        'order_items',
        'voucher_redemptions',
    ];

    private const FINANCIAL_TABLES = [
        'seller_payout_ledgers',
        'seller_payout_items',
        'rider_remittances',
        'order_refunds',
    ];

    public function preview(): array
    {
        $orders = DB::table('orders')->select('id', 'track_code')->get();
        $orderIds = $orders->pluck('id')->all();
        $trackCodes = $orders->pluck('track_code')->filter()->unique()->values()->all();
        $customOrderIds = Schema::hasTable('custom_orders') && $orderIds
            ? DB::table('custom_orders')->whereIn('order_id', $orderIds)->pluck('id')->all()
            : [];

        $counts = ['orders' => count($orderIds)];
        foreach (self::ORDER_CHILD_TABLES as $table) {
            $counts[$table] = $this->countOrderChildren($table, $orderIds, $trackCodes);
        }

        $counts['order_requests'] = $this->countConvertedRequests($orderIds, $customOrderIds);
        $counts['product_daily_orders'] = Schema::hasTable('product_daily_orders')
            ? DB::table('product_daily_orders')->count()
            : 0;
        $messageIds = Schema::hasTable('messages') && $orderIds
            ? DB::table('messages')->whereIn('order_id', $orderIds)->pluck('id')->all()
            : [];
        $counts['message_reactions'] = Schema::hasTable('message_reactions') && $messageIds
            ? DB::table('message_reactions')->whereIn('message_id', $messageIds)->count()
            : 0;

        $financialRows = [];
        foreach (self::FINANCIAL_TABLES as $table) {
            $count = $this->countOrderChildren($table, $orderIds, $trackCodes);
            if ($count > 0) {
                $financialRows[$table] = $count;
            }
        }

        return [
            'counts' => $counts,
            'financial_rows' => $financialRows,
            'custom_order_ids' => $customOrderIds,
            'order_ids' => $orderIds,
            'track_codes' => $trackCodes,
        ];
    }

    public function purge(): array
    {
        return DB::transaction(function (): array {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('LOCK TABLE orders IN EXCLUSIVE MODE');
            }

            $orders = DB::table('orders')->lockForUpdate()->select('id', 'track_code')->get();
            $orderIds = $orders->pluck('id')->all();
            $trackCodes = $orders->pluck('track_code')->filter()->unique()->values()->all();
            $customOrderIds = Schema::hasTable('custom_orders') && $orderIds
                ? DB::table('custom_orders')->whereIn('order_id', $orderIds)->pluck('id')->all()
                : [];

            $preview = $this->preview();
            if ($preview['financial_rows']) {
                throw new RuntimeException('Order-linked payout, remittance, or refund records exist. No data was deleted.');
            }

            $counts = $preview['counts'];
            if (!$orderIds) {
                if (Schema::hasTable('product_daily_orders')) {
                    DB::table('product_daily_orders')->delete();
                }
                return $counts;
            }

            $messageIds = Schema::hasTable('messages')
                ? DB::table('messages')->whereIn('order_id', $orderIds)->pluck('id')->all()
                : [];
            if ($messageIds && Schema::hasTable('message_reactions')) {
                DB::table('message_reactions')->whereIn('message_id', $messageIds)->delete();
            }

            foreach (self::ORDER_CHILD_TABLES as $table) {
                if (!Schema::hasTable($table)) {
                    continue;
                }

                if ($table === 'customer_payment_qrs') {
                    if ($trackCodes && Schema::hasColumn($table, 'track_code')) {
                        DB::table($table)->whereIn('track_code', $trackCodes)->delete();
                    }
                    continue;
                }

                if (Schema::hasColumn($table, 'order_id')) {
                    DB::table($table)->whereIn('order_id', $orderIds)->delete();
                }
            }

            if (Schema::hasTable('order_requests')) {
                $query = DB::table('order_requests');
                $hasConvertedOrderId = Schema::hasColumn('order_requests', 'converted_order_id');
                $hasCustomOrderId = Schema::hasColumn('order_requests', 'custom_order_id');
                if ($hasConvertedOrderId && $hasCustomOrderId && $customOrderIds) {
                    $query->whereIn('converted_order_id', $orderIds)
                        ->orWhereIn('custom_order_id', $customOrderIds);
                } elseif ($hasConvertedOrderId) {
                    $query->whereIn('converted_order_id', $orderIds);
                } elseif ($hasCustomOrderId && $customOrderIds) {
                    $query->whereIn('custom_order_id', $customOrderIds);
                } else {
                    $query = null;
                }
                $query?->delete();
            }

            if (Schema::hasTable('product_daily_orders')) {
                DB::table('product_daily_orders')->delete();
            }

            DB::table('orders')->whereIn('id', $orderIds)->delete();

            return $counts;
        });
    }

    private function countOrderChildren(string $table, array $orderIds, array $trackCodes): int
    {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        if ($table === 'customer_payment_qrs') {
            return $trackCodes && Schema::hasColumn($table, 'track_code')
                ? DB::table($table)->whereIn('track_code', $trackCodes)->count()
                : 0;
        }

        return $orderIds && Schema::hasColumn($table, 'order_id')
            ? DB::table($table)->whereIn('order_id', $orderIds)->count()
            : 0;
    }

    private function countConvertedRequests(array $orderIds, array $customOrderIds): int
    {
        if (!Schema::hasTable('order_requests')) {
            return 0;
        }

        $hasConvertedOrderId = Schema::hasColumn('order_requests', 'converted_order_id');
        $hasCustomOrderId = Schema::hasColumn('order_requests', 'custom_order_id');
        if ($hasConvertedOrderId && $hasCustomOrderId && $customOrderIds) {
            return DB::table('order_requests')
                ->whereIn('converted_order_id', $orderIds)
                ->orWhereIn('custom_order_id', $customOrderIds)
                ->count();
        }
        if ($hasConvertedOrderId && $orderIds) {
            return DB::table('order_requests')->whereIn('converted_order_id', $orderIds)->count();
        }
        if ($hasCustomOrderId && $customOrderIds) {
            return DB::table('order_requests')->whereIn('custom_order_id', $customOrderIds)->count();
        }

        return 0;
    }
}
