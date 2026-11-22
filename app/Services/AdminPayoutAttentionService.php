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
                ->where(function ($query) {
                    $query->whereNull('payout_method')->orWhere('payout_method', '')
                        ->orWhereNull('payout_account_name')->orWhere('payout_account_name', '')
                        ->orWhereNull('payout_account_number')->orWhere('payout_account_number', '')
                        ->orWhere('payout_details_verified', false)->orWhereNull('payout_details_verified');
                })->count() : 0;
        $riderRequests = Schema::hasTable('rider_payout_requests')
            ? DB::table('rider_payout_requests')->where('status', 'requested')->count() : 0;
        return [
            'seller' => $sellerRequests + $sellerDetails,
            'rider' => $riderRequests,
            'seller_requests' => $sellerRequests,
            'seller_details' => $sellerDetails,
            'rider_requests' => $riderRequests,
        ];
    }
}
