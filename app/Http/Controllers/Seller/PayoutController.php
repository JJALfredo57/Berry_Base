<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\SellerPayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayoutController extends Controller
{
    public function __construct(private SellerPayoutService $payouts)
    {
    }

    private function shop()
    {
        return DB::table('shops')->where('seller_id', session('user')['id'])->first();
    }

    public function index()
    {
        $shop = $this->shop();
        if (!$shop) return redirect()->route('seller.apply')->with('err', 'Your shop is not found.');

        $payoutSettings = $this->payouts->settings();
        $summary = $this->payouts->summaryForShop($shop->id);
        $ledgers = DB::table('seller_payout_ledgers')
            ->where('shop_id', $shop->id)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();
        $payouts = DB::table('seller_payouts')
            ->where('shop_id', $shop->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('seller.payouts', compact('shop', 'payoutSettings', 'summary', 'ledgers', 'payouts'));
    }

    public function saveDetails(Request $request)
    {
        $shop = $this->shop();
        if (!$shop) return redirect()->route('seller.apply')->with('err', 'Your shop is not found.');

        $validated = $request->validate([
            'payout_account_name' => 'required|string|min:3|max:150',
            'payout_account_number' => 'required|string|min:5|max:80',
        ], [
            'payout_account_name.required' => 'Enter the GCash account name exactly as registered.',
            'payout_account_number.required' => 'Enter the GCash mobile number.',
        ]);

        DB::table('shops')->where('id', $shop->id)->update([
            'payout_method' => 'gcash',
            'payout_institution' => 'GCash',
            'payout_account_name' => trim($validated['payout_account_name']),
            'payout_account_number' => trim($validated['payout_account_number']),
            'payout_details_verified' => false,
            'updated_at' => now(),
        ]);

        return redirect()->route('seller.payouts')->with('msg', 'Payout details saved. Admin verification is required before automatic payouts.');
    }

    public function requestManual()
    {
        $shop = $this->shop();
        if (!$shop) return redirect()->route('seller.apply')->with('err', 'Your shop is not found.');

        if (empty($shop->payout_method) || empty($shop->payout_account_name) || empty($shop->payout_account_number)) {
            return back()->with('err', 'Complete your payout details before requesting a payout.');
        }

        $id = $this->payouts->createPayoutForShop($shop->id, 'manual', 'Requested by seller.');
        if (!$id) {
            return back()->with('err', 'No eligible payout yet. Check available balance, minimum payout amount, or clearing period.');
        }

        return back()->with('msg', "Payout request #{$id} submitted. Admin will verify and mark it paid after transfer.");
    }
}
