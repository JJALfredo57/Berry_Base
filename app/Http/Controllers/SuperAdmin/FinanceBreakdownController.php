<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\FinanceBreakdownService;
use App\Services\FinanceReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceBreakdownController extends Controller
{
    public function show(Request $request, string $metric, FinanceBreakdownService $breakdowns, FinanceReconciliationService $reconciliation)
    {
        abort_unless($breakdowns->definition($metric), 404);
        $filters = $breakdowns->period($request);
        $filters['search'] = mb_substr(trim((string) $request->input('search', '')), 0, 120);
        $shop = (string) $request->input('shop', '');
        $filters['shop'] = preg_match('/^[A-Za-z0-9_-]{1,40}$/', $shop) ? $shop : '';
        $allowedStatuses = ['Pending', 'Confirmed', 'Preparing', 'Ready for Pickup', 'Out for Delivery', 'Delivered', 'Picked Up', 'Cancelled', 'No Show'];
        $filters['status'] = in_array($request->input('status'), $allowedStatuses, true) ? (string) $request->input('status') : '';
        $filters['sort'] = $request->input('sort') === 'oldest' ? 'oldest' : 'newest';
        $filters['per_page'] = in_array((int) $request->input('per_page', 10), [10, 25, 50], true) ? (int) $request->input('per_page', 10) : 10;
        $filters['page'] = max(1, (int) $request->input('page', 1));
        $report = $breakdowns->report($metric, $filters, $reconciliation);
        $shops = DB::table('shops')->orderBy('shop_name')->get(['id', 'shop_name']);

        return view('superadmin.finance-breakdown', compact('metric', 'filters', 'report', 'shops', 'allowedStatuses'));
    }
}
