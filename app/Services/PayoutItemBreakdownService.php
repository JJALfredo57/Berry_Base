<?php

namespace App\Services;

use Illuminate\Support\Collection;

/** Display-only allocation; an order remains the indivisible payout unit. */
class PayoutItemBreakdownService
{
    public function forLedger(object $ledger, Collection $items): Collection
    {
        if ($items->isEmpty()) {
            return $items;
        }
        $weights = $items->map(fn ($item) => max(0, (float) ($item->final_unit_price_snapshot ?? 0)) * max(1, (int) $item->quantity));
        if ($weights->sum() <= 0) {
            $weights = $items->map(fn ($item) => max(1, (int) $item->quantity));
        }
        $weightTotal = (float) $weights->sum();
        $runningWeight = 0.0;
        $allocated = [];

        return $items->values()->map(function ($item, $index) use ($ledger, $weights, $weightTotal, &$runningWeight, &$allocated) {
            $item = clone $item;
            $runningWeight += $weights->values()->get($index);
            foreach (['cake_amount', 'commission_amount', 'seller_net_amount', 'cash_to_seller_amount'] as $field) {
                $cents = (int) round((float) ($ledger->{$field} ?? 0) * 100);
                $throughHere = (int) round($cents * $runningWeight / $weightTotal);
                $item->{'payout_'.$field} = ($throughHere - ($allocated[$field] ?? 0)) / 100;
                $allocated[$field] = $throughHere;
            }

            return $item;
        });
    }
}
