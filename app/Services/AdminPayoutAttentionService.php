<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminPayoutAttentionService
{
    public function counts(): array
    {
        $sellerRequests = Schema::hasTable('seller_payouts')
            ? DB::table('seller_payouts')->whereIn('status', ['requested', 'processing'])->count() : 0;
        $sellerDetails = Schema::hasTable('shops')
            ? DB::table('shops')->where('status', 'approved')
                ->whereNotNull('payout_method')->where('payout_method', '<>', '')
                ->whereNotNull('payout_account_name')->where('payout_account_name', '<>', '')
                ->whereNotNull('payout_account_number')->where('payout_account_number', '<>', '')
                ->where(fn ($query) => $query->where('payout_details_verified', false)->orWhereNull('payout_details_verified'))->count() : 0;
        $riderRequests = Schema::hasTable('rider_payout_requests')
            ? DB::table('rider_payout_requests')->where('status', 'requested')->count() : 0;
        $riderRecovery = Schema::hasTable('rider_delivery_earnings') && Schema::hasTable('rider_payout_requests')
            ? DB::table('rider_delivery_earnings as e')->where('e.status', 'requested')
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')->from('rider_payout_requests as rp')
                        ->whereColumn('rp.rider_id', 'e.rider_id')->where('rp.status', 'requested');
                })->distinct()->count('e.rider_id') : 0;

        return [
            'seller' => $sellerRequests + $sellerDetails,
            'rider' => $riderRequests + $riderRecovery,
            'seller_requests' => $sellerRequests,
            'seller_details' => $sellerDetails,
            'rider_requests' => $riderRequests,
            'rider_recovery' => $riderRecovery,
        ];
    }
}
