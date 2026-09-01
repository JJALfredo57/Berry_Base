<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            $revenueQuery = DB::table('orders as o')
                ->where('o.shop_id', $shopId)
                ->where('o.payment_status', 'Paid')
                ->whereNotIn('o.status', ['Cancelled']);

            if (Schema::hasTable('rider_remittances')) {
                $revenueQuery->leftJoin('rider_remittances as rr', 'rr.order_id', '=', 'o.id')
                    ->where(function ($q) {
                        $q->where(function ($nonCodDelivery) {
                            $nonCodDelivery
                                ->where(function ($method) {
                                    $method->whereNull('o.payment_method')
                                        ->orWhereRaw('UPPER(o.payment_method) <> ?', ['COD']);
                                })
                                ->orWhere(function ($fulfillment) {
                                    $fulfillment->whereNull('o.fulfillment_type')
                                        ->orWhere('o.fulfillment_type', '<>', 'Delivery');
                                });
                        })->orWhere('rr.status', 'confirmed');
                    });
            }

            $pendingRemittance = Schema::hasTable('rider_remittances')
                ? (float) DB::table('rider_remittances')
                    ->where('shop_id', $shopId)
                    ->whereIn('status', ['pending', 'submitted', 'rejected', 'awaiting_payment', 'qr_expired'])
                    ->sum('amount')
                : 0;

            $stats = [
                'pending'    => DB::table('orders')->where('shop_id',$shopId)->where('status','Pending')->count(),
                'confirmed'  => DB::table('orders')->where('shop_id',$shopId)->where('status','Confirmed')->count(),
                'preparing'  => DB::table('orders')->where('shop_id',$shopId)->where('status','Preparing')->count(),
                'total'      => DB::table('orders')->where('shop_id',$shopId)->whereNotIn('status',['Cancelled'])->count(),
                'revenue'    => (float) $revenueQuery->sum('o.total_price'),
                'pending_remittance' => $pendingRemittance,
                'products'   => DB::table('products')->where('shop_id',$shopId)->where('is_available', true)->count(),
            ];
        } catch (\Exception $e) {
            $stats = ['pending'=>0,'confirmed'=>0,'preparing'=>0,'total'=>0,'revenue'=>0,'pending_remittance'=>0,'products'=>0];
        }

        $commissionEnabled = (bool)($shop->commission_enabled ?? 1);
        $commissionRate    = $commissionEnabled ? (float)($shop->commission_rate ?? 0) : 0;
        $commission        = round($stats['revenue'] * $commissionRate / 100, 2);
        $netRevenue        = $stats['revenue'] - $commission;
        $payoutSummary     = app(\App\Services\SellerPayoutService::class)->summaryForShop((string)$shopId);

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
            'payoutSummary','recentOrders','pendingCustom','unreadMsg'
        ));
    }

    public function sidebarCounts()
    {
        $shop = $this->getShop();
        if (!$shop || $shop->status !== 'approved') {
            return response()->json(['ok' => false, 'counts' => []], 403);
        }

        return response()->json([
            'ok' => true,
            'counts' => $this->countsForShop((string) $shop->id),
        ]);
    }

    private function countsForShop(string $shopId): array
    {
        $counts = [
            'orders' => 0,
            'kitchen' => 0,
            'messages' => 0,
            'custom_orders' => 0,
            'reviews' => 0,
            'feedback' => 0,
            'pickup_ready' => 0,
            'pending_orders' => 0,
            'remittance_actions' => 0,
            'kitchen_pending' => 0,
            'kitchen_preparing' => 0,
        ];

        try {
            $counts['pending_orders'] = (int) DB::table('orders')
                ->where('shop_id', $shopId)
                ->whereIn('status', ['Pending', 'Pending Review'])
                ->count();
            $counts['pickup_ready'] = (int) DB::table('orders')
                ->where('shop_id', $shopId)
                ->where('status', 'Pickup')
                ->count();
            if (Schema::hasTable('rider_remittances')) {
                $counts['remittance_actions'] = (int) DB::table('rider_remittances')
                    ->where('shop_id', $shopId)
                    ->whereIn('status', ['pending', 'submitted', 'rejected'])
                    ->count();
            }
            $counts['orders'] = $counts['pending_orders'] + $counts['pickup_ready'] + $counts['remittance_actions'];

            $counts['messages'] = (int) DB::table('messages as m')
                ->join('orders as o', 'o.id', '=', 'm.order_id')
                ->where('o.shop_id', $shopId)
                ->where('m.sender_role', 'customer')
                ->where('m.is_read', false)
                ->count();

            if (Schema::hasTable('kitchen_tickets')) {
                $counts['kitchen_pending'] = (int) DB::table('kitchen_tickets as kt')
                    ->join('orders as o', 'o.id', '=', 'kt.order_id')
                    ->where('o.shop_id', $shopId)
                    ->where('kt.status', 'pending')
                    ->count();
                $counts['kitchen_preparing'] = (int) DB::table('kitchen_tickets as kt')
                    ->join('orders as o', 'o.id', '=', 'kt.order_id')
                    ->where('o.shop_id', $shopId)
                    ->where('kt.status', 'in_progress')
                    ->count();
                $counts['kitchen'] = $counts['kitchen_pending'] + $counts['kitchen_preparing'];
            }

            if (Schema::hasTable('custom_orders')) {
                $counts['custom_orders'] = (int) DB::table('custom_orders')
                    ->where('shop_id', $shopId)
                    ->where('review_status', 'pending')
                    ->count();
            }

            if (Schema::hasTable('order_reviews')) {
                $counts['reviews'] = (int) DB::table('order_reviews')
                    ->where('shop_id', $shopId)
                    ->where('review_status', 'pending')
                    ->count();
            }

            if (Schema::hasTable('customer_feedback')) {
                $feedbackQuery = DB::table('customer_feedback')->where('status', 'open');
                if (Schema::hasColumn('customer_feedback', 'shop_id')) {
                    $feedbackQuery->where('shop_id', $shopId);
                }
                if (Schema::hasColumn('customer_feedback', 'source_role')) {
                    $feedbackQuery->where('source_role', 'seller');
                }
                $counts['feedback'] = (int) $feedbackQuery->count();
            }
        } catch (\Throwable $e) {}

        return $counts;
    }
}
