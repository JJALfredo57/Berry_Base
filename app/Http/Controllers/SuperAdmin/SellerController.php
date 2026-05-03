<?php
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Helpers\SmsHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerController extends Controller
{
    private function validateCommissionRate(Request $request): float
    {
        $validated = $request->validate([
            'commission_rate' => 'required|numeric|min:0|max:100',
        ], [
            'commission_rate.required' => 'Commission rate is required.',
            'commission_rate.numeric'  => 'Commission rate must be a valid number.',
            'commission_rate.min'      => 'Commission rate cannot be below 0%.',
            'commission_rate.max'      => 'Commission rate cannot exceed 100%.',
        ]);

        return round((float) $validated['commission_rate'], 2);
    }

    public function index()
    {
        $applications = DB::table('shops as s')
            ->join('users as u','u.id','=','s.seller_id')
            ->select('s.*','u.fullname','u.email','u.phone')
            ->orderByRaw("CASE WHEN s.status='pending' THEN 0 ELSE 1 END")
            ->orderByDesc('s.created_at')
            ->paginate(15);

        // Load documents per shop
        $shopIds = $applications->pluck('id')->toArray();
        $documents = [];
        if ($shopIds) {
            $docs = DB::table('seller_documents')->whereIn('shop_id', $shopIds)->get();
            foreach ($docs as $d) $documents[$d->shop_id][] = $d;
        }

        $platform = DB::table('platform_settings')->first()
            ?? (object) [
                'commission_rate_basic' => 0.00,
                'commission_rate_verified' => 0.00,
            ];

        $commissionStats = DB::table('shops')
            ->whereIn('status', ['approved', 'suspended'])
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN commission_enabled = 1 THEN 1 ELSE 0 END) as enabled')
            ->first();

        return view('superadmin.sellers', compact('applications','documents','platform','commissionStats'));
    }

    public function approve(Request $request, string $shopId)
    {
        $shop = DB::table('shops')->where('id',$shopId)->first();
        if (!$shop) return back()->with('err','Shop not found.');
        if ($shop->status === 'approved') return back()->with('err','Already approved.');

        DB::table('shops')->where('id',$shopId)->update([
            'status'      => 'approved',
            'verified_at' => now(),
        ]);

        // ✅ Mark the seller's user account as verified so they can log in
        DB::table('users')->where('id',$shop->seller_id)->update([
            'is_verified' => 1,
        ]);

        // Get platform commission
        $platform  = DB::table('platform_settings')->first();
        $commRate  = $shop->tier === 'verified'
            ? ($platform->commission_rate_verified ?? 0.00)
            : ($platform->commission_rate_basic    ?? 0.00);
        DB::table('shops')->where('id',$shopId)->update(['commission_rate' => $commRate]);

        // Notify seller via SMS
        $seller = DB::table('users')->where('id',$shop->seller_id)->first();
        if ($seller?->phone) {
            try {
                $siteName = config('app.name', 'Cake Shop');
                $header   = SmsHelper::header($siteName);
                SmsHelper::send($seller->phone,
                    "{$header}\n"
                    . "Congratulations! 🎉 Your application has been approved!\n\n"
                    . "Shop Name: {$shop->shop_name}\n\n"
                    . "Your shop is now live on {$siteName}. You may now log in to your seller dashboard and start listing your products.\n\n"
                    . "Welcome to the {$siteName} community!"
                );
            } catch (\Exception $e) {}
        }

        CakeshopHelper::logActivity(session('user')['id'],'admin','Approve Seller',"Shop: {$shop->shop_name}");
        return back()->with('msg',"Shop '{$shop->shop_name}' approved successfully.");
    }

    public function reject(Request $request, string $shopId)
    {
        $request->validate([
            'reason' => 'required|string|min:10|max:500',
        ],[
            'reason.required' => 'Please provide a reason for rejection.',
            'reason.min'      => 'Reason must be at least 10 characters.',
        ]);

        $shop = DB::table('shops')->where('id',$shopId)->first();
        if (!$shop) return back()->with('err','Shop not found.');

        $rejectedReason = trim($request->input('reason', ''));

        DB::table('shops')->where('id',$shopId)->update([
            'status'          => 'rejected',
            'rejected_reason' => $rejectedReason,
        ]);

        // Notify seller
        $seller = DB::table('users')->where('id',$shop->seller_id)->first();
        if ($seller?->phone) {
            try {
                $siteName = config('app.name', 'Cake Shop');
                $header   = SmsHelper::header($siteName);
                SmsHelper::send($seller->phone,
                    "{$header}\n"
                    . "We're sorry, your seller application was not approved.\n\n"
                    . "Shop Name: {$shop->shop_name}\n"
                    . "Reason: {$rejectedReason}\n\n"
                    . "You may re-apply after addressing the concern mentioned above. For questions, please contact our support team."
                );
            } catch (\Exception $e) {}
        }

        CakeshopHelper::logActivity(session('user')['id'],'admin','Reject Seller',"Shop: {$shop->shop_name}");
        return back()->with('msg',"Application rejected.");
    }

    public function suspend(string $shopId)
    {
        $shop = DB::table('shops')->where('id',$shopId)->first();
        if (!$shop) return back()->with('err','Shop not found.');

        $newStatus = $shop->status === 'suspended' ? 'approved' : 'suspended';
        DB::table('shops')->where('id',$shopId)->update(['status' => $newStatus]);

        $action = $newStatus === 'suspended' ? 'Suspend Seller' : 'Reactivate Seller';
        CakeshopHelper::logActivity(session('user')['id'],'admin',$action,"Shop: {$shop->shop_name}");
        return back()->with('msg',"Shop {$newStatus}.");
    }

    public function toggleCommission(string $shopId)
    {
        $shop = DB::table('shops')->where('id',$shopId)->first();
        if (!$shop) return back()->with('err','Shop not found.');
        if (!in_array($shop->status, ['approved', 'suspended'], true)) {
            return back()->with('err','Commission can only be changed for approved or suspended sellers.');
        }

        $newVal = $shop->commission_enabled ? 0 : 1;
        DB::table('shops')->where('id',$shopId)->update(['commission_enabled' => $newVal]);

        $label = $newVal ? 'enabled' : 'disabled';
        CakeshopHelper::logActivity(session('user')['id'],'admin','Toggle Commission',"Shop: {$shop->shop_name} — commission {$label}");
        return back()->with('msg',"Commission {$label} for {$shop->shop_name}.");
    }

    public function bulkCommission(Request $request)
    {
        $action = $request->input('action'); // 'enable', 'disable', or automatic toggle
        if ($action === 'toggle' || !$action) {
            $stats = DB::table('shops')
                ->whereIn('status', ['approved', 'suspended'])
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN commission_enabled = 1 THEN 1 ELSE 0 END) as enabled')
                ->first();

            $allEnabled = ((int)($stats->total ?? 0) > 0)
                && ((int)($stats->enabled ?? 0) === (int)($stats->total ?? 0));
            $action = $allEnabled ? 'disable' : 'enable';
        }

        if (!in_array($action, ['enable','disable'])) return back()->with('err','Invalid action.');

        $val = $action === 'enable' ? 1 : 0;
        DB::table('shops')->whereIn('status',['approved','suspended'])->update(['commission_enabled' => $val]);

        $label = $action === 'enable' ? 'enabled' : 'disabled';
        CakeshopHelper::logActivity(session('user')['id'],'admin','Bulk Commission',"Commission {$label} for all approved and suspended sellers");
        return back()->with('msg',"Commission {$label} for all approved and suspended sellers.");
    }

    public function updateCommissionRate(Request $request, string $shopId)
    {
        $shop = DB::table('shops')->where('id', $shopId)->first();
        if (!$shop) return back()->with('err', 'Shop not found.');
        if (!in_array($shop->status, ['approved', 'suspended'], true)) {
            return back()->with('err', 'Commission rate can only be changed for approved or suspended sellers.');
        }

        $rate = $this->validateCommissionRate($request);

        DB::table('shops')->where('id', $shopId)->update([
            'commission_rate' => $rate,
            'updated_at'      => now(),
        ]);

        CakeshopHelper::logActivity(session('user')['id'], 'admin', 'Update Commission Rate', "Shop: {$shop->shop_name} - {$rate}%");
        return back()->with('msg', "Commission rate updated to {$rate}% for {$shop->shop_name}.");
    }

    public function approveUpgrade(string $shopId)
    {
        $shop = DB::table('shops')->where('id', $shopId)->first();
        if (!$shop) return back()->with('err', 'Shop not found.');
        if (($shop->upgrade_request_status ?? null) !== 'pending') {
            return back()->with('err', 'No pending upgrade request for this shop.');
        }

        try {
            DB::table('shops')->where('id', $shopId)->update([
                'tier'                   => 'verified',
                'upgrade_request_status' => 'approved',
                'upgrade_request_note'   => null,
                'verified_at'            => now(),
            ]);
        } catch (\Throwable $e) {
            return back()->with('err', 'Failed to approve upgrade. Please try again.');
        }

        $seller = DB::table('users')->where('id', $shop->seller_id)->first();
        if ($seller?->phone) {
            try {
                $siteName = config('app.name', 'Cake Shop');
                $header   = SmsHelper::header($siteName);
                SmsHelper::send($seller->phone,
                    "{$header}\n"
                    . "Congratulations! Your shop has been upgraded to Verified Seller!\n\n"
                    . "Shop: {$shop->shop_name}\n\n"
                    . "You now have access to unlimited products, custom orders, and a Verified badge. Log in to your dashboard to get started!"
                );
            } catch (\Exception $e) {}
        }

        CakeshopHelper::logActivity(session('user')['id'], 'admin', 'Approve Upgrade', "Shop: {$shop->shop_name} → Verified");
        return back()->with('msg', "'{$shop->shop_name}' has been upgraded to Verified Seller.");
    }

    public function rejectUpgrade(Request $request, string $shopId)
    {
        $request->validate([
            'reason' => 'required|string|min:10|max:500',
        ], [
            'reason.required' => 'Please provide a reason for rejecting the upgrade.',
            'reason.min'      => 'Reason must be at least 10 characters.',
        ]);

        $shop = DB::table('shops')->where('id', $shopId)->first();
        if (!$shop) return back()->with('err', 'Shop not found.');

        $reason = trim($request->input('reason'));
        try {
            DB::table('shops')->where('id', $shopId)->update([
                'upgrade_request_status' => 'rejected',
                'upgrade_request_note'   => $reason,
            ]);
        } catch (\Throwable $e) {
            return back()->with('err', 'Failed to reject upgrade. Please try again.');
        }

        $seller = DB::table('users')->where('id', $shop->seller_id)->first();
        if ($seller?->phone) {
            try {
                $siteName = config('app.name', 'Cake Shop');
                $header   = SmsHelper::header($siteName);
                SmsHelper::send($seller->phone,
                    "{$header}\n"
                    . "Your upgrade request for '{$shop->shop_name}' was not approved.\n\n"
                    . "Reason: {$reason}\n\n"
                    . "You may re-submit your request after addressing the concern. Log in to your seller dashboard for more details."
                );
            } catch (\Exception $e) {}
        }

        CakeshopHelper::logActivity(session('user')['id'], 'admin', 'Reject Upgrade', "Shop: {$shop->shop_name}");
        return back()->with('msg', "Upgrade request rejected for '{$shop->shop_name}'.");
    }

    public function bulkCommissionRate(Request $request)
    {
        if ($request->has('commission_rate_basic') || $request->has('commission_rate_verified')) {
            $validated = $request->validate([
                'commission_rate_basic'    => 'required|numeric|min:0|max:100',
                'commission_rate_verified' => 'required|numeric|min:0|max:100',
            ], [
                'commission_rate_basic.required'    => 'Basic seller commission rate is required.',
                'commission_rate_verified.required' => 'Verified seller commission rate is required.',
            ]);

            $updates = [
                'commission_rate_basic'    => round((float) $validated['commission_rate_basic'], 2),
                'commission_rate_verified' => round((float) $validated['commission_rate_verified'], 2),
                'updated_at'               => now(),
            ];

            $existing = DB::table('platform_settings')->first();
            if ($existing) {
                DB::table('platform_settings')->where('id', $existing->id)->update($updates);
            } else {
                $updates['created_at'] = now();
                DB::table('platform_settings')->insert($updates);
            }

            CakeshopHelper::logActivity(session('user')['id'], 'admin', 'Update Commission Policy', 'Default seller commission rates updated');
            return back()->with('msg', 'Seller commission policy updated successfully.');
        }

        $rate = $this->validateCommissionRate($request);

        DB::table('shops')
            ->whereIn('status', ['approved', 'suspended'])
            ->update([
                'commission_rate' => $rate,
                'updated_at'      => now(),
            ]);

        CakeshopHelper::logActivity(session('user')['id'], 'admin', 'Bulk Commission Rate', "Commission rate set to {$rate}% for all approved and suspended sellers");
        return back()->with('msg', "Commission rate set to {$rate}% for all approved and suspended sellers.");
    }
}
