<?php
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Platform stats
        $stats = [
            'total_shops'    => DB::table('shops')->where('status','approved')->count(),
            'pending_apps'   => DB::table('shops')->where('status','pending')->count(),
            'total_products' => DB::table('products')->count(),
            'total_orders'   => DB::table('orders')->whereNotIn('status',['Cancelled'])->count(),
            'total_commission' => $this->commissionQuery()->sum(DB::raw('orders.total_price * shops.commission_rate / 100')),
            'total_customers'=> (
                DB::table('orders')->whereNotIn('status',['Cancelled'])->whereNotNull('user_id')->distinct()->count('user_id') +
                DB::table('orders')->whereNotIn('status',['Cancelled'])->whereNull('user_id')->count()
            ),
        ];

        // Pending seller applications
        $pendingApps = DB::table('shops as s')
            ->join('users as u','u.id','=','s.seller_id')
            ->where('s.status','pending')
            ->select('s.*','u.fullname','u.email','u.phone')
            ->orderBy('s.created_at')
            ->limit(5)
            ->get();

        // Recent shops
        $recentShops = DB::table('shops as s')
            ->join('users as u','u.id','=','s.seller_id')
            ->where('s.status','approved')
            ->select('s.*','u.fullname')
            ->orderByDesc('s.verified_at')
            ->limit(5)
            ->get();

        // Commission this month
        $commissionMonth = $this->commissionQuery()
            ->whereYear('orders.paid_at', now()->year)
            ->whereMonth('orders.paid_at', now()->month)
            ->sum(DB::raw('orders.total_price * shops.commission_rate / 100'));

        $platform = DB::table('platform_settings')->first();

        return view('superadmin.dashboard', compact(
            'stats','pendingApps','recentShops','commissionMonth','platform'
        ));
    }

    public function commissions()
    {
        $platform = DB::table('platform_settings')->first();

        $totalCommission = $this->commissionQuery()
            ->sum(DB::raw('orders.total_price * shops.commission_rate / 100'));

        $commissionMonth = $this->commissionQuery()
            ->whereYear('orders.paid_at', now()->year)
            ->whereMonth('orders.paid_at', now()->month)
            ->sum(DB::raw('orders.total_price * shops.commission_rate / 100'));

        $paidOrdersMonth = $this->commissionQuery()
            ->whereYear('orders.paid_at', now()->year)
            ->whereMonth('orders.paid_at', now()->month)
            ->count();

        $grossSalesMonth = $this->commissionQuery()
            ->whereYear('orders.paid_at', now()->year)
            ->whereMonth('orders.paid_at', now()->month)
            ->sum('orders.total_price');

        $monthlyLabels = [];
        $monthlyCommission = [];
        $monthlyGrossSales = [];
        $monthlyOrderCounts = [];
        $monthlyBuckets = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);
            $key = $month->format('Y-m');

            $monthlyBuckets[$key] = [
                'commission' => 0,
                'gross_sales' => 0,
                'paid_orders' => 0,
            ];
        }

        $monthlyStart = now()->startOfMonth()->subMonths(5);
        $this->commissionQuery()
            ->where('orders.paid_at', '>=', $monthlyStart)
            ->select('orders.paid_at', 'orders.total_price', 'shops.commission_rate')
            ->orderBy('orders.paid_at')
            ->get()
            ->each(function ($order) use (&$monthlyBuckets) {
                $key = substr((string) $order->paid_at, 0, 7);

                if (!isset($monthlyBuckets[$key])) {
                    return;
                }

                $grossSales = (float) $order->total_price;
                $monthlyBuckets[$key]['commission'] += $this->commissionAmount($grossSales, (float) $order->commission_rate);
                $monthlyBuckets[$key]['gross_sales'] += $grossSales;
                $monthlyBuckets[$key]['paid_orders']++;
            });

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);
            $key = $month->format('Y-m');
            $row = $monthlyBuckets[$key];

            $monthlyLabels[] = $month->format('M Y');
            $monthlyCommission[] = round((float) $row['commission'], 2);
            $monthlyGrossSales[] = round((float) $row['gross_sales'], 2);
            $monthlyOrderCounts[] = (int) $row['paid_orders'];
        }

        $topShops = $this->commissionQuery()
            ->select(
                'shops.shop_name',
                'shops.tier',
                'shops.commission_rate',
                DB::raw('SUM(orders.total_price * shops.commission_rate / 100) as commission'),
                DB::raw('SUM(orders.total_price) as gross_sales'),
                DB::raw('COUNT(*) as paid_orders')
            )
            ->groupBy('shops.id', 'shops.shop_name', 'shops.tier', 'shops.commission_rate')
            ->orderByDesc('commission')
            ->limit(5)
            ->get();

        $chartData = [
            'labels' => $monthlyLabels,
            'commission' => $monthlyCommission,
            'grossSales' => $monthlyGrossSales,
            'orders' => $monthlyOrderCounts,
        ];

        return view('superadmin.commission_analytics', compact(
            'platform',
            'totalCommission',
            'commissionMonth',
            'paidOrdersMonth',
            'grossSalesMonth',
            'topShops',
            'chartData'
        ));
    }

    private function commissionQuery()
    {
        return DB::table('orders')
            ->join('shops', 'shops.id', '=', 'orders.shop_id')
            ->where('orders.payment_status', 'Paid')
            ->whereNotIn('orders.status', ['Cancelled'])
            ->whereNotNull('orders.paid_at')
            ->where('shops.commission_enabled', 1)
            ->where('shops.commission_rate', '>', 0);
    }

    private function commissionAmount(float $grossSales, float $commissionRate): float
    {
        return $grossSales * $commissionRate / 100;
    }
}
