<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private function getShop()
    {
        $uid = session('user')['id'];
        return DB::table('shops')->where('seller_id', $uid)->first();
    }

    public function index()
    {
        $shop = $this->getShop();
        if (!$shop) return redirect()->route('seller.apply')->with('err','Your shop is not found.');
        if ($shop->status === 'pending')
            return view('seller.pending', compact('shop'));
        if ($shop->status === 'rejected')
            return view('seller.rejected', compact('shop'));
        if ($shop->status === 'suspended')
            return view('seller.suspended', compact('shop'));

        $shopId = $shop->id;

        // Stats — wrapped in try/catch in case shop_id columns not yet migrated
        try {
            $stats = [
                'pending'    => DB::table('orders')->where('shop_id',$shopId)->where('status','Pending')->count(),
                'confirmed'  => DB::table('orders')->where('shop_id',$shopId)->where('status','Confirmed')->count(),
                'preparing'  => DB::table('orders')->where('shop_id',$shopId)->where('status','Preparing')->count(),
                'total'      => DB::table('orders')->where('shop_id',$shopId)->whereNotIn('status',['Cancelled'])->count(),
                'revenue'    => DB::table('orders')->where('shop_id',$shopId)->where('payment_status','Paid')->sum('total_price'),
                'products'   => DB::table('products')->where('shop_id',$shopId)->where('is_available', true)->count(),
            ];
        } catch (\Exception $e) {
            $stats = ['pending'=>0,'confirmed'=>0,'preparing'=>0,'total'=>0,'revenue'=>0,'products'=>0];
        }

        $commissionEnabled = (bool)($shop->commission_enabled ?? 1);
        $commissionRate    = $commissionEnabled ? (float)($shop->commission_rate ?? 0) : 0;
        $commission        = round($stats['revenue'] * $commissionRate / 100, 2);
        $netRevenue        = $stats['revenue'] - $commission;

        // Recent orders
        try {
            $recentOrders = DB::table('orders as o')
                ->leftJoin('products as p','p.id','=','o.product_id')
                ->where('o.shop_id', $shopId)
                ->select('o.*','p.name as product_name')
                ->orderByRaw("CASE WHEN o.status IN ('Pending','Pending Review') THEN 0 ELSE 1 END")
                ->orderByDesc('o.id')
                ->limit(8)
                ->get();
        } catch (\Exception $e) { $recentOrders = collect(); }

        // Pending custom orders
        try {
            $pendingCustom = DB::table('custom_orders')
                ->where('shop_id', $shopId)
                ->where('review_status','pending')
                ->count();
        } catch (\Exception $e) { $pendingCustom = 0; }

        // Unread messages
        try {
            $unreadMsg = DB::table('messages as m')
                ->join('orders as o','o.id','=','m.order_id')
                ->where('o.shop_id', $shopId)
                ->where('m.sender_role','customer')
                ->where('m.is_read', false)
                ->count();
        } catch (\Exception $e) { $unreadMsg = 0; }

        return view('seller.dashboard', compact(
            'shop','stats','commission','netRevenue',
            'recentOrders','pendingCustom','unreadMsg'
        ));
    }
}
