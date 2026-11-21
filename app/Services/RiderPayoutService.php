<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RiderPayoutService
{
    /** Called inside a transaction; never mix earnings from different transfers. */
    public function allocations(object $request): Collection
    {
        DB::table('riders')->where('id', $request->rider_id)->lockForUpdate()->first();
        $fresh = DB::table('rider_payout_requests')->where('id', $request->id)->lockForUpdate()->first();
        if (! $fresh || $fresh->status !== 'requested') {
            throw new \DomainException('Rider payout is no longer awaiting transfer.');
        }
        $rows = DB::table('rider_delivery_earnings')->where('payout_id', $fresh->id)->where('rider_id', $fresh->rider_id)->lockForUpdate()->get();
        if ($rows->isEmpty()) {
            // Old requests have no payout_id. Recover only an unambiguous exact
            // match; anything else requires reviewed reconciliation, not a guess.
            if (DB::table('rider_payout_requests')->where('rider_id', $fresh->rider_id)->where('status', 'requested')->count() !== 1) {
                throw new \DomainException('Legacy rider requests need reconciliation before transfer.');
            }
            $rows = DB::table('rider_delivery_earnings')->where('rider_id', $fresh->rider_id)->where('status', 'requested')->whereNull('payout_id')->lockForUpdate()->get();
        }
        if ($rows->isEmpty() || $rows->contains(fn ($row) => $row->status !== 'requested')
            || abs((float) $rows->sum('amount') - (float) $fresh->amount) >= .01) {
            throw new \DomainException('Rider allocations do not match this transfer. No funds were marked paid.');
        }

        return $rows;
    }
}
