<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RiderEarningService
{
    /** One online-funded delivery fee per order; retries never reopen paid funds. */
    public function earn(object $order, string $source, ?int $returnId = null): void
    {
        if (! Schema::hasTable('rider_delivery_earnings') || empty($order->rider_id)) {
            return;
        }
        DB::transaction(function () use ($order, $source, $returnId) {
            $fresh = DB::table('orders')->where('id', $order->id)->lockForUpdate()->first();
            if (! $fresh || empty($fresh->rider_id) || ($fresh->fulfillment_type ?? '') !== 'Delivery') {
                return;
            }
            if (! $returnId && $fresh->status !== 'Delivered') {
                return;
            }
            if ($returnId && ! DB::table('post_delivery_return_cases')->where('id', $returnId)->where('order_id', $fresh->id)
                ->whereIn('status', ['rider_collected_return', 'returned_to_seller', 'seller_received_return', 'refunded'])->exists()) {
                return;
            }
            if (DB::table('rider_delivery_earnings')->where('order_id', $fresh->id)->exists()) {
                return;
            }
            $shop = DB::table('shops')->where('id', $fresh->shop_id)->first();
            if (! $shop) {
                return;
            }
            $fresh->online_paid_amount = (float) DB::table('payment_transactions')->where('order_id', $fresh->id)
                ->where('payment_status', 'paid')->whereRaw('UPPER(method) = ?', ['GCASH'])->sum('amount');
            $snapshot = app(OrderSettlementService::class)->snapshot($fresh, $shop);
            $amount = min($fresh->online_paid_amount, (float) $snapshot['rider_online_fee_amount']);
            if ($amount <= 0) {
                return;
            }
            DB::table('rider_delivery_earnings')->insert([
                'event_key' => $fresh->id.'-online-delivery', 'rider_id' => $fresh->rider_id,
                'order_id' => $fresh->id, 'return_case_id' => $returnId, 'source' => $source,
                'amount' => $amount, 'status' => 'available', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }
}
