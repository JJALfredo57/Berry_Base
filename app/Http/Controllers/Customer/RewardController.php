<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\CustomerVerificationService;
use App\Services\LoyaltyService;
use App\Services\VoucherService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RewardController extends Controller
{
    public function index(LoyaltyService $loyalty, CustomerVerificationService $verification, VoucherService $vouchers)
    {
        $uid = session('user')['id'];
        $overview = $loyalty->membershipOverview($uid);
        $verificationStatus = $verification->status($uid);

        $transactions = collect();
        if (Schema::hasTable('loyalty_transactions')) {
            $transactions = DB::table('loyalty_transactions')
                ->where('user_id', $uid)
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }

        $walletVouchers = collect();
        if (Schema::hasTable('vouchers')) {
            $hasAudience = Schema::hasColumn('vouchers', 'audience');
            $walletVouchers = DB::table('vouchers as v')
                ->leftJoin('shops as s', 's.id', '=', 'v.shop_id')
                ->where('v.is_active', true)
                ->when($hasAudience, function ($query) use ($uid) {
                    $query->where(function ($q) use ($uid) {
                        $q->where('v.audience', 'public')
                            ->orWhere(function ($sq) use ($uid) {
                            $sq->where('v.audience', 'assigned')
                               ->whereExists(function ($exists) use ($uid) {
                                   $exists->selectRaw('1')
                                       ->from('voucher_assignments as va')
                                       ->whereColumn('va.voucher_id', 'v.id')
                                       ->where('va.user_id', $uid)
                                       ->where('va.status', 'active')
                                       ->where(fn ($qq) => $qq->whereNull('va.expires_at')->orWhere('va.expires_at', '>=', now()));
                               });
                            });
                    });
                })
                ->select('v.*', 's.shop_name', 's.shop_slug')
                ->when($hasAudience, fn ($q) => $q->orderByRaw('CASE WHEN v.audience = ? THEN 0 ELSE 1 END', ['assigned']))
                ->orderBy('v.ends_at')
                ->orderBy('v.code')
                ->get()
                ->map(function ($voucher) use ($vouchers, $uid) {
                    $result = $vouchers->validate($voucher->code, max(0, (float) $voucher->minimum_order_amount), $voucher->shop_id, $uid);
                    $voucher->validation_ok = $result['ok'];
                    $voucher->validation_message = $result['message'] ?? '';
                    $voucher->computed_discount = (float) ($result['discount'] ?? 0);
                    $voucher->audience = $voucher->audience ?? 'public';
                    return $voucher;
                });
        }

        return view('customer.rewards', compact('overview', 'transactions', 'walletVouchers', 'verificationStatus'));
    }
}
