<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderActionService
{
    public function customerSummary(string $userId): array
    {
        $sellerCancellationRequests = 0;
        $priceConfirmations = 0;

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'seller_cancel_state')) {
            $sellerCancellationRequests = (int) DB::table('orders')
                ->where('user_id', $userId)
                ->where('seller_cancel_state', 'awaiting_customer')
                ->where('seller_cancel_expires_at', '>', now())
                ->count();
        }
        if (Schema::hasTable('custom_orders')) {
            $priceConfirmations = (int) DB::table('custom_orders as co')
                ->join('orders as o', 'o.id', '=', 'co.order_id')
                ->where('o.user_id', $userId)
                ->where('co.review_status', 'approved')
                ->where('co.price_confirmed', 'pending')
                ->where('o.status', '<>', 'Cancelled')
                ->count();
        }

        return [
            'seller_cancellations' => $sellerCancellationRequests,
            'price_confirmations' => $priceConfirmations,
            'total' => $sellerCancellationRequests + $priceConfirmations,
        ];
    }

    public function sellerSummary(string $shopId): array
    {
        $refundRequests = Schema::hasTable('order_refunds')
            ? (int) DB::table('order_refunds')->where('shop_id', $shopId)->where('status', 'pending')->count()
            : 0;

        return ['refund_requests' => $refundRequests, 'total' => $refundRequests];
    }
}
