<?php
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\MobileNotificationService;
use App\Services\SellerPayoutService;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayoutController extends Controller
{
    use UploadsFiles;

    public function __construct(private SellerPayoutService $payouts, private MobileNotificationService $notifications)
    {
    }

    public function index()
    {
        $this->payouts->syncDeliveredPaidOrders();

        $payoutSettings = $this->payouts->settings();
        if (session()->has('payout_settings_saved')) {
            foreach ((array) session('payout_settings_saved') as $key => $value) {
                if (str_starts_with($key, 'payout_')) {
                    $payoutSettings->{$key} = $value;
                }
            }
        }
        if (($payoutSettings->payout_mode ?? 'manual') === 'automatic') {
            $payoutSettings->payout_mode = 'manual';
            $payoutSettings->payout_auto_paused = false;
        }
        $summary = [
            'clearing' => (float) DB::table('seller_payout_ledgers')->whereIn('status', ['pending', 'clearing'])->sum('seller_net_amount'),
            'available' => (float) DB::table('seller_payout_ledgers')->where('status', 'available')->sum('seller_net_amount'),
            'processing' => (float) DB::table('seller_payout_ledgers')->whereIn('status', ['requested', 'processing'])->sum('seller_net_amount'),
            'paid' => (float) DB::table('seller_payout_ledgers')->where('status', 'paid')->sum('seller_net_amount'),
        ];

        $shops = DB::table('shops as s')
            ->leftJoin('users as u', 'u.id', '=', 's.seller_id')
            ->where('s.status', 'approved')
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
                $shop->clearing_balance = (float) DB::table('seller_payout_ledgers')
                    ->where('shop_id', $shop->id)
                    ->whereIn('status', ['pending', 'clearing'])
                    ->sum('seller_net_amount');
                $nextClearing = DB::table('seller_payout_ledgers')
                    ->where('shop_id', $shop->id)
                    ->whereIn('status', ['pending', 'clearing'])
                    ->whereNotNull('release_at')
                    ->orderBy('release_at')
                    ->first();
                $shop->next_release_at = $nextClearing->release_at ?? null;
                $shop->next_release_amount = $nextClearing ? (float) $nextClearing->seller_net_amount : 0;
                return $shop;
            });

        $payouts = DB::table('seller_payouts as p')
            ->join('shops as s', 's.id', '=', 'p.shop_id')
            ->leftJoin('users as u', 'u.id', '=', 'p.seller_id')
            ->select('p.*', 's.shop_name', 'u.fullname as seller_name')
            ->orderByDesc('p.created_at')
            ->limit(50)
            ->get();

        return view('superadmin.payouts', compact('payoutSettings', 'summary', 'shops', 'payouts'));
    }

    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'payout_mode' => 'required|in:manual,automatic',
            'payout_hold_days' => 'required|integer|min:0|max:30',
            'payout_minimum_amount' => 'required|numeric|min:0|max:999999',
            'payout_schedule' => 'nullable|in:daily,weekly,twice_monthly,monthly',
            'payout_first_approval_required' => 'nullable|boolean',
            'payout_auto_paused' => 'nullable|boolean',
        ], [
            'payout_mode.required' => 'Choose Manual or Automatic payout mode.',
            'payout_minimum_amount.min' => 'Minimum payout amount cannot be negative.',
        ]);

        if ($validated['payout_mode'] === 'automatic') {
            return back()
                ->withInput()
                ->with('err', 'Automatic payouts are not available yet. We are preparing this feature for a future website update, but manual payouts will remain the active and supported payout method for now.');
        }

        $updates = [
            'payout_mode' => $validated['payout_mode'],
            'payout_hold_days' => (int) $validated['payout_hold_days'],
            'payout_minimum_amount' => round((float) $validated['payout_minimum_amount'], 2),
            'payout_schedule' => $validated['payout_schedule'] ?? 'weekly',
            'payout_first_approval_required' => $validated['payout_mode'] === 'automatic'
                ? $request->boolean('payout_first_approval_required')
                : false,
            'payout_auto_paused' => $validated['payout_mode'] === 'automatic'
                ? $request->boolean('payout_auto_paused')
                : false,
            'updated_at' => now(),
        ];

        $existing = DB::table('platform_settings')->orderBy('id')->first();
        if ($existing) {
            DB::table('platform_settings')->where('id', $existing->id)->update($updates);
            DB::table('platform_settings')->where('id', '<>', $existing->id)->update($updates);
        } else {
            $updates['platform_name'] = 'Cake Shop Platform';
            $updates['created_at'] = now();
            DB::table('platform_settings')->insert($updates);
        }

        $saved = (object) array_merge((array) $this->payouts->settings(), $updates);
        return redirect()->route('superadmin.payouts')
            ->with('payout_settings_saved', $updates)
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

        $this->notifySellerPayout($shop, 'Payout Details Verified', 'Your GCash payout details have been verified. You can now request eligible payouts.', [
            'event' => 'seller_payout_details_verified',
            'shop_id' => (string) $shop->id,
        ]);

        return back()->with('msg', "{$shop->shop_name} payout details marked as verified.");
    }

    public function requestSellerDetails(Request $request, string $shopId)
    {
        $shop = DB::table('shops')->where('id', $shopId)->first();
        if (!$shop) return back()->with('err', 'Seller shop not found.');

        if (!empty($shop->payout_method) && !empty($shop->payout_account_name) && !empty($shop->payout_account_number)) {
            return back()->with('msg', "{$shop->shop_name} already submitted payout details.");
        }

        $result = $this->notifySellerPayout($shop, 'Payout Details Needed', 'Please add your exact GCash account name and mobile number so your payout can be processed.', [
            'event' => 'seller_payout_details_requested',
            'shop_id' => (string) $shop->id,
        ]);

        $channel = $result['channel'] ?? 'none';
        $message = $channel === 'push'
            ? "Payout details request sent to {$shop->shop_name} by mobile push."
            : "Payout details request saved for {$shop->shop_name}. It will show on the seller website payout badge.";

        return back()->with('msg', $message);
    }

    public function createManual(Request $request, string $shopId)
    {
        $id = $this->payouts->createPayoutForShop($shopId, 'manual', trim((string) $request->input('admin_note', '')) ?: null);
        if (!$id) {
            return back()->with('err', 'No eligible payout was created. Check available balance, minimum amount, payout pause, or seller details.');
        }

        $payout = DB::table('seller_payouts')->where('id', $id)->first();
        if ($payout) {
            $this->notifySellerPayout($payout, 'Payout Request Created', 'A payout request for PHP '.number_format((float) $payout->net_amount, 2).' was created and is waiting for transfer.', [
                'event' => 'seller_payout_created',
                'payout_id' => (string) $payout->id,
                'shop_id' => (string) $payout->shop_id,
                'amount' => number_format((float) $payout->net_amount, 2, '.', ''),
                'status' => (string) $payout->status,
            ]);
        }

        return back()->with('msg', "Manual payout request #{$id} created for review/payment.");
    }

    public function markPaid(Request $request, int $payoutId)
    {
        $validated = $request->validate([
            'payout_receipt' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'reference_number' => 'nullable|string|min:3|max:120',
            'admin_note' => 'nullable|string|max:500',
        ], [
            'payout_receipt.required' => 'Upload the GCash receipt screenshot before marking payout as paid.',
            'payout_receipt.image' => 'The payout receipt must be a valid image file.',
        ]);

        $payout = DB::table('seller_payouts')->where('id', $payoutId)->first();
        if (!$payout) return back()->with('err', 'Payout not found.');
        if ($payout->status === 'paid') return back()->with('msg', 'This payout was already marked as paid.');

        $receiptPath = $this->uploadFile($request->file('payout_receipt'), 'uploads/payout_receipts');
        if (!$receiptPath) {
            return back()->with('err', 'Receipt upload failed. Please try again with a smaller JPG/PNG/WebP image.');
        }

        DB::transaction(function () use ($payout, $validated, $receiptPath) {
            $updates = [
                'status' => 'paid',
                'reference_number' => $validated['reference_number'] ?? $payout->reference_number,
                'admin_note' => $validated['admin_note'] ?? $payout->admin_note,
                'processed_at' => $payout->processed_at ?? now(),
                'paid_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('seller_payouts', 'payout_receipt_path')) {
                $updates['payout_receipt_path'] = $receiptPath;
            }

            DB::table('seller_payouts')->where('id', $payout->id)->update($updates);
            DB::table('seller_payout_ledgers')->where('payout_id', $payout->id)->update([
                'status' => 'paid',
                'updated_at' => now(),
            ]);
        });

        $this->notifySellerPayout($payout, 'Payout Paid', 'Your payout #'.$payoutId.' for PHP '.number_format((float) $payout->net_amount, 2).' has been marked as paid.', [
            'event' => 'seller_payout_paid',
            'payout_id' => (string) $payoutId,
            'shop_id' => (string) $payout->shop_id,
            'amount' => number_format((float) $payout->net_amount, 2, '.', ''),
            'status' => 'paid',
        ]);

        return back()->with('msg', "Payout #{$payoutId} marked as paid.");
    }

    public function runAutomatic()
    {
        return back()->with('err', 'Automatic payouts are not available yet. Manual payouts remain active while business verification and payout provider access are being prepared.');
    }

    private function notifySellerPayout(object $source, string $title, string $message, array $data): array
    {
        $sellerId = $source->seller_id ?? null;
        if (!$sellerId && !empty($source->shop_id)) {
            $sellerId = DB::table('shops')->where('id', $source->shop_id)->value('seller_id');
        }

        $phone = $sellerId ? DB::table('users')->where('id', $sellerId)->value('phone') : null;
        $url = route('seller.payouts', [], false);

        return $this->notifications->notifyUser(
            'seller',
            $sellerId ? (string) $sellerId : null,
            $phone,
            $title,
            $message,
            $data + ['url' => $url],
            null,
            $url
        );
    }
}
