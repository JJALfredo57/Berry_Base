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
            'total_revenue'  => DB::table('orders')->where('payment_status','Paid')->sum('total_price'),
            'total_customers'=> DB::table('users')->where('role','customer')->count(),
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

        // Revenue this month
        $revenueMonth = DB::table('orders')
            ->where('payment_status','Paid')
            ->whereYear('paid_at', now()->year)
            ->whereMonth('paid_at', now()->month)
            ->sum('total_price');

        $platform = DB::table('platform_settings')->first();

        return view('superadmin.dashboard', compact(
            'stats','pendingApps','recentShops','revenueMonth','platform'
        ));
    }
}
