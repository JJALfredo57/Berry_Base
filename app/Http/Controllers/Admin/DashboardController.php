<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $totalOrders   = \Illuminate\Support\Facades\DB::table('orders')->count();
        $pendingOrders = \Illuminate\Support\Facades\DB::table('orders')->where('status','Pending')->count();
        $totalRevenue  = \Illuminate\Support\Facades\DB::table('orders')->where('payment_status','Paid')->sum('total_price');
        $revenueToday  = \Illuminate\Support\Facades\DB::table('orders')->where('payment_status','Paid')->whereDate('paid_at', today())->sum('total_price');
        $revenueMonth  = \Illuminate\Support\Facades\DB::table('orders')->where('payment_status','Paid')->whereYear('paid_at', now()->year)->whereMonth('paid_at', now()->month)->sum('total_price');
        $revenueCOD    = \Illuminate\Support\Facades\DB::table('orders')->where('payment_status','Paid')->where('payment_method','COD')->sum('total_price');
        $revenueGCash  = \Illuminate\Support\Facades\DB::table('orders')->where('payment_status','Paid')->where('payment_method','GCash')->sum('total_price');
        return view('admin.dashboard', compact('totalOrders','pendingOrders','totalRevenue','revenueToday','revenueMonth','revenueCOD','revenueGCash'));
    }
}
