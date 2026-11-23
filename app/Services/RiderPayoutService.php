<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RiderPayoutService
{
    /** Create and link one payout to its exact earnings inside the caller's transaction. */
    public function request(int $riderId, string $gcashName, string $gcashNumber): object
    {
        if (! DB::table('riders')->where('id', $riderId)->lockForUpdate()->first()) {
            throw new \DomainException('Rider account is unavailable.');
        }
        if (DB::table('rider_payout_requests')->where('rider_id', $riderId)->where('status', 'requested')->exists()) {
            throw new \DomainException('A payout request is already awaiting transfer.');
        }

        $earnings = DB::table('rider_delivery_earnings')->where('rider_id', $riderId)
            ->where('status', 'available')->lockForUpdate()->get();
        $amount = round((float) $earnings->sum('amount'), 2);
        if ($earnings->isEmpty() || $amount <= 0) {
            throw new \DomainException('No delivery earnings are available for payout.');
        }

        $id = DB::table('rider_payout_requests')->insertGetId([
            'rider_id' => $riderId, 'amount' => $amount, 'gcash_name' => $gcashName,
            'gcash_number' => $gcashNumber, 'status' => 'requested',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $linked = DB::table('rider_delivery_earnings')->whereIn('id', $earnings->pluck('id'))
            ->where('rider_id', $riderId)->where('status', 'available')->whereNull('payout_id')
            ->update(['status' => 'requested', 'payout_id' => $id, 'updated_at' => now()]);
        if ($linked !== $earnings->count()) {
            throw new \DomainException('Rider earnings changed while requesting payout. Please try again.');
        }

        return (object) ['id' => $id, 'amount' => $amount];
    }

    /** Called inside a transaction; never mix earnings from different transfers. */
    public function allocations(object $request): Collection
    {
        DB::table('riders')->where('id', $request->rider_id)->lockForUpdate()->first();
        $fresh = DB::table('rider_payout_requests')->where('id', $request->id)->lockForUpdate()->first();
        if (! $fresh || $fresh->status !== 'requested') {
            throw new \DomainException('Rider payout is no longer awaiting transfer.');
        }
        $rows = DB::table('rider_delivery_earnings')->where('payout_id', $fresh->id)->where('rider_id', $fresh->rider_id)->lockForUpdate()->get();
        if (DB::table('rider_delivery_earnings')->where('payout_id', $fresh->id)->where('rider_id', '!=', $fresh->rider_id)->exists()) {
            throw new \DomainException('Rider allocations do not match this transfer. No funds were marked paid.');
        }
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

    /** Return an unpaid request's exact linked earnings to the rider balance. */
    public function cancelRequest(int $payoutId): object
    {
        return DB::transaction(function () use ($payoutId) {
            $request = DB::table('rider_payout_requests')->where('id', $payoutId)->first();
            if (! $request) {
                throw new \DomainException('Rider payout request is unavailable.');
            }

            DB::table('riders')->where('id', $request->rider_id)->lockForUpdate()->first();
            $request = DB::table('rider_payout_requests')->where('id', $payoutId)->lockForUpdate()->first();
            if (! $request || $request->status !== 'requested' || $request->reference_number
                || $request->receipt_path || $request->receipt_hash || $request->paid_at || $request->paid_by_user_id) {
                throw new \DomainException('Only an unpaid rider payout without transfer proof can be returned to the balance.');
            }

            $earnings = DB::table('rider_delivery_earnings')->where('payout_id', $payoutId)->lockForUpdate()->get();
            if ($earnings->isEmpty() || $earnings->contains(fn ($row) => (int) $row->rider_id !== (int) $request->rider_id || $row->status !== 'requested')
                || abs((float) $earnings->sum('amount') - (float) $request->amount) >= .01) {
                throw new \DomainException('Linked rider earnings do not match this request. No balance was changed.');
            }

            $changed = DB::table('rider_delivery_earnings')->whereIn('id', $earnings->pluck('id'))
                ->where('status', 'requested')->where('payout_id', $payoutId)
                ->update(['status' => 'available', 'payout_id' => null, 'updated_at' => now()]);
            if ($changed !== $earnings->count() || DB::table('rider_payout_requests')->where('id', $payoutId)->where('status', 'requested')->delete() !== 1) {
                throw new \DomainException('The payout changed while returning funds. No balance was changed.');
            }

            return $request;
        });
    }
}
