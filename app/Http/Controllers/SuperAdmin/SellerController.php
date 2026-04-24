<?php
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Helpers\SmsHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerController extends Controller
{
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

        return view('superadmin.sellers', compact('applications','documents'));
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
            'rejected_reason' => 'required|string|min:10|max:500',
        ],[
            'reason.required' => 'Please provide a reason for rejection.',
            'reason.min'      => 'Reason must be at least 10 characters.',
        ]);

        $shop = DB::table('shops')->where('id',$shopId)->first();
        if (!$shop) return back()->with('err','Shop not found.');

        $rejectedReason = trim($request->input('rejected_reason', ''));

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
}
