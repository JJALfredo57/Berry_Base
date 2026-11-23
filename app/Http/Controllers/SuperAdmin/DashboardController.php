<?php
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = $this->dashboardStats();

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

        $commissionMonth = $stats['commission_month'];

        $platform = DB::table('platform_settings')->first();

        return view('superadmin.dashboard', compact(
            'stats','pendingApps','recentShops','commissionMonth','platform'
        ));
    }

    public function stats()
    {
        return response()
            ->json(['stats' => $this->dashboardStats()])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function commissions(Request $request)
    {
        try { $from = Carbon::parse((string) $request->input('from', now()->subDays(29)->toDateString()))->toDateString(); } catch (\Throwable $e) { $from = now()->subDays(29)->toDateString(); }
        try { $to = Carbon::parse((string) $request->input('to', now()->toDateString()))->toDateString(); } catch (\Throwable $e) { $to = now()->toDateString(); }
        $period = (string) $request->input('period', 'last30');
        if ($period === 'today') [$from, $to] = [now()->toDateString(), now()->toDateString()];
        elseif ($period === 'week') [$from, $to] = [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()];
        elseif ($period === 'month') [$from, $to] = [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];
        elseif ($period === 'last_month') [$from, $to] = [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()];
        elseif ($period === 'year') [$from, $to] = [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()];
        elseif ($period === 'last30') [$from, $to] = [now()->subDays(29)->toDateString(), now()->toDateString()];
        if ($from > $to) [$from, $to] = [$to, $from];
        $commissionPeriodQuery = fn () => $this->commissionQuery()->whereBetween(DB::raw('DATE(o.paid_at)'), [$from, $to]);
        $platform = DB::table('platform_settings')->first();

        $totalCommission = $commissionPeriodQuery()
            ->sum('l.commission_amount');

        $commissionMonth = $commissionPeriodQuery()
            ->sum('l.commission_amount');

        $paidOrdersMonth = $commissionPeriodQuery()
            ->count();

        $monthlyLabels = [];
        $monthlyCommission = [];
        $monthlyOrderCounts = [];
        $monthlyBuckets = [];

        $start = Carbon::parse($from); $end = Carbon::parse($to); $daily = $start->diffInDays($end) <= 62;
        $cursor = $daily ? $start->copy() : $start->copy()->startOfMonth();
        $last = $daily ? $end->copy() : $end->copy()->startOfMonth();
        while ($cursor <= $last) {
            $key = $daily ? $cursor->format('Y-m-d') : $cursor->format('Y-m');

            $monthlyBuckets[$key] = [
                'commission' => 0,
                'paid_orders' => 0,
            ];
            $daily ? $cursor->addDay() : $cursor->addMonth();
        }

        $commissionPeriodQuery()
            ->select('o.paid_at', 'l.commission_amount')
            ->orderBy('o.paid_at')
            ->get()
            ->each(function ($order) use (&$monthlyBuckets, $daily) {
                $key = $daily ? substr((string) $order->paid_at, 0, 10) : substr((string) $order->paid_at, 0, 7);

                if (!isset($monthlyBuckets[$key])) {
                    return;
                }

                $monthlyBuckets[$key]['commission'] += (float) $order->commission_amount;
                $monthlyBuckets[$key]['paid_orders']++;
            });

        foreach ($monthlyBuckets as $key => $row) {
            $bucketDate = Carbon::parse($daily ? $key : $key.'-01');

            $monthlyLabels[] = $daily ? $bucketDate->format('M j') : $bucketDate->format('M Y');
            $monthlyCommission[] = round((float) $row['commission'], 2);
            $monthlyOrderCounts[] = (int) $row['paid_orders'];
        }

        $topShops = $commissionPeriodQuery()
            ->select(
                'shops.shop_name',
                'shops.tier',
                'shops.commission_rate',
                DB::raw('SUM(l.commission_amount) as commission'),
                DB::raw('COUNT(*) as paid_orders')
            )
            ->groupBy('shops.id', 'shops.shop_name', 'shops.tier', 'shops.commission_rate')
            ->orderByDesc('commission')
            ->limit(5)
            ->get();

        $chartData = [
            'labels' => $monthlyLabels,
            'commission' => $monthlyCommission,
            'orders' => $monthlyOrderCounts,
        ];

        return view('superadmin.commission_analytics', compact(
            'platform',
            'totalCommission',
            'commissionMonth',
            'paidOrdersMonth',
            'topShops',
            'chartData', 'from', 'to', 'period'
        ));
    }

    private function commissionQuery()
    {
        return DB::table('seller_payout_ledgers as l')
            ->join('orders as o', 'o.id', '=', 'l.order_id')
            ->join('shops', 'shops.id', '=', 'l.shop_id')
            ->whereNotNull('o.paid_at')
            ->where('l.commission_amount', '>', 0);
    }

    private function dashboardStats(): array
    {
        try {
            $openFeedback = DB::table('customer_feedback')->where('status', 'open')->count();
        } catch (\Throwable $e) {
            $openFeedback = 0;
        }

        $commissionMonth = (float) $this->commissionQuery()
            ->whereYear('o.paid_at', now()->year)
            ->whereMonth('o.paid_at', now()->month)
            ->sum('l.commission_amount');

        return [
            'total_shops' => DB::table('shops')->where('status', 'approved')->count(),
            'pending_apps' => DB::table('shops')->where('status', 'pending')->count(),
            'total_products' => DB::table('products')->count(),
            'open_feedback' => $openFeedback,
            'total_commission' => (float) $this->commissionQuery()->sum('l.commission_amount'),
            'commission_month' => $commissionMonth,
        ];
    }
}
