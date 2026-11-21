<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderDiscountFundingService
{
    public function forOrder(string $orderId): array
    {
        $funding = ['platform' => 0.0, 'seller' => 0.0];
        if (! Schema::hasTable('order_discounts')) {
            return $funding;
        }
        foreach (DB::table('order_discounts')->where('order_id', $orderId)->get() as $discount) {
            $source = strtolower((string) ($discount->funding_source ?? ''));
            if (! in_array($source, ['platform', 'seller'], true)) {
                $source = 'seller';
                if ($discount->source_type === 'loyalty') {
                    $source = 'platform';
                }
                if ($discount->source_type === 'voucher' && Schema::hasTable('vouchers')) {
                    $voucher = DB::table('vouchers')->where('id', $discount->source_id)->first();
                    if ($voucher && empty($voucher->shop_id)) {
                        $source = 'platform';
                    }
                }
            }
            $funding[$source] += max(0, (float) $discount->amount);
        }

        return array_map(fn ($amount) => round($amount, 2), $funding);
    }
}
