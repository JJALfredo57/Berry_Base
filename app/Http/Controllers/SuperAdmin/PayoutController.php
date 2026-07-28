<?php
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SellerPayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayoutController extends Controller
{
    public function __construct(private SellerPayoutService $payouts)
    {
    }

    public function index()
    {
        $this->payouts->syncDeliveredPaidOrders();

        $settings = $this->payouts->settings();
        $summary = [
            'clearing' => (float) DB::table('seller_payout_ledgers')->whereIn('status', ['pending', 'clearing'])->sum('seller_net_amount'),
            'available' => (float) DB::table('seller_payout_ledgers')->where('status', 'available')->sum('seller_net_amount'),
            'processing' => (float) DB::table('seller_payout_ledgers')->whereIn('status', ['requested', 'processing'])->sum('seller_net_amount'),
            'paid' => (float) DB::table('seller_payout_ledgers')->where('status', 'paid')->sum('seller_net_amount'),
        ];

        $shops = DB::table('shops as s')
            ->leftJoin('users as u', 'u.id', '=', 's.seller_id')
            ->whereIn('s.status', ['approved', 'suspended'])
            ->select('s.*', 'u.fullname as seller_name')
            ->orderBy('s.shop_name')
            ->get()
            ->map(function ($shop) {
                $shop->available_balance = (float) DB::table('seller_payout_ledgers')
                    ->where('shop_id', $shop->id)
                    ->where('status', 'available')
                    ->sum('seller_net_amount');
                $shop->processing_balance = (float) DB::table('seller_payout_ledgers')
                    ->where('shop_id', $shop->id)
                    ->whereIn('status', ['requested', 'processing'])
                    ->sum('seller_net_amount');
                return $shop;
            });

        $payouts = DB::table('seller_payouts as p')
            ->join('shops as s', 's.id', '=', 'p.shop_id')
            ->leftJoin('users as u', 'u.id', '=', 'p.seller_id')
            ->select('p.*', 's.shop_name', 'u.fullname as seller_name')
            ->orderByDesc('p.created_at')
            ->limit(50)
            ->get();

        return view('superadmin.payouts', compact('settings', 'summary', 'shops', 'payouts'));
    }

    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'payout_mode' => 'required|in:manual,automatic',
            'payout_hold_days' => 'required|integer|min:0|max:30',
            'payout_minimum_amount' => 'required|numeric|min:0|max:999999',
            'payout_schedule' => 'required|in:daily,weekly,twice_monthly,monthly',
            'payout_first_approval_required' => 'nullable|boolean',
            'payout_auto_paused' => 'nullable|boolean',
        ], [
            'payout_mode.required' => 'Choose Manual or Automatic payout mode.',
            'payout_minimum_amount.min' => 'Minimum payout amount cannot be negative.',
        ]);

        $updates = [
            'payout_mode' => $validated['payout_mode'],
            'payout_hold_days' => (int) $validated['payout_hold_days'],
            'payout_minimum_amount' => round((float) $validated['payout_minimum_amount'], 2),
            'payout_schedule' => $validated['payout_schedule'],
            'payout_first_approval_required' => $request->boolean('payout_first_approval_required'),
            'payout_auto_paused' => $request->boolean('payout_auto_paused'),
            'updated_at' => now(),
        ];

        $existing = DB::table('platform_settings')->orderBy('id')->first();
        if ($existing) {
            DB::table('platform_settings')->where('id', $existing->id)->update($updates);
        } else {
            $updates['platform_name'] = 'Cake Shop Platform';
            $updates['created_at'] = now();
            DB::table('platform_settings')->insert($updates);
        }

        $saved = $this->payouts->settings();
        return redirect()->route('superadmin.payouts')
            ->with('msg', 'Payout settings saved. Current mode: '.ucfirst($saved->payout_mode).'. Automatic is '.($saved->payout_auto_paused ? 'paused' : 'active').'.');
    }

    public function verifySeller(Request $request, string $shopId)
    {
        $shop = DB::table('shops')->where('id', $shopId)->first();
        if (!$shop) return back()->with('err', 'Seller shop not found.');

        if (empty($shop->payout_method) || empty($shop->payout_account_name) || empty($shop->payout_account_number)) {
            return back()->with('err', 'Seller payout details are incomplete. Ask the seller to complete bank/e-wallet details first.');
        }

        DB::table('shops')->where('id', $shopId)->update([
            'payout_details_verified' => true,
            'updated_at' => now(),
        ]);

        return back()->with('msg', "{$shop->shop_name} payout details marked as verified.");
    }

    public function createManual(Request $request, string $shopId)
    {
        $id = $this->payouts->createPayoutForShop($shopId, 'manual', trim((string) $request->input('admin_note', '')) ?: null);
        if (!$id) {
            return back()->with('err', 'No eligible payout was created. Check available balance, minimum amount, payout pause, or seller details.');
        }
        return back()->with('msg', "Manual payout request #{$id} created for review/payment.");
    }

    public function markPaid(Request $request, int $payoutId)
    {
        $validated = $request->validate([
            'reference_number' => 'required|string|min:3|max:120',
            'admin_note' => 'nullable|string|max:500',
        ], [
            'reference_number.required' => 'Enter the GCash/bank reference number before marking payout as paid.',
        ]);

        $payout = DB::table('seller_payouts')->where('id', $payoutId)->first();
        if (!$payout) return back()->with('err', 'Payout not found.');
        if ($payout->status === 'paid') return back()->with('msg', 'This payout was already marked as paid.');

        DB::transaction(function () use ($payout, $validated) {
            DB::table('seller_payouts')->where('id', $payout->id)->update([
                'status' => 'paid',
                'reference_number' => $validated['reference_number'],
                'admin_note' => $validated['admin_note'] ?? $payout->admin_note,
                'processed_at' => $payout->processed_at ?? now(),
                'paid_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('seller_payout_ledgers')->where('payout_id', $payout->id)->update([
                'status' => 'paid',
                'updated_at' => now(),
            ]);
        });

        return back()->with('msg', "Payout #{$payoutId} marked as paid.");
    }

    public function runAutomatic()
    {
        $count = $this->payouts->runAutomaticPreparation();
        if ($count === 0) {
            $blockers = $this->payouts->automaticPreparationBlockers();
            $message = $blockers
                ? 'No automatic payouts prepared: '.implode(' ', $blockers)
                : 'No automatic payouts prepared. No seller currently meets the payout rules.';
            return back()->with('err', $message);
        }
        return back()->with('msg', "{$count} automatic payout batch(es) prepared for processing.");
    }
}
