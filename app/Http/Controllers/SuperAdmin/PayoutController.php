<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\FinanceReconciliationService;
use App\Services\AdminPayoutAttentionService;
use App\Services\MobileNotificationService;
use App\Services\OrderRefundService;
use App\Services\PayoutReceiptScanService;
use App\Services\RiderPayoutService;
use App\Services\SelectedPayoutService;
use App\Services\SellerBalanceService;
use App\Services\SellerPayoutService;
use App\Traits\UploadsFiles;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class PayoutController extends Controller
{
    use UploadsFiles;

    public function __construct(private SellerPayoutService $payouts, private MobileNotificationService $notifications, private PayoutReceiptScanService $receiptScanner, private FinanceReconciliationService $financeReconciliation) {}

    public function sellerPayouts(Request $request)
    {
        return $this->index($request, 'seller');
    }

    public function index(Request $request, string $page = 'hub')
    {
        // Existing bookmarked finance filters continue to open the seller payout list.
        if ($page === 'hub' && $request->hasAny(['shop', 'payout_status', 'search', 'shops_page', 'requests_page', 'payouts_page'])) {
            $page = 'seller';
        }
        $this->payouts->syncDeliveredPaidOrders();

        $payoutSettings = $this->payouts->settings();
        if (session()->has('payout_settings_saved')) {
            foreach ((array) session('payout_settings_saved') as $key => $value) {
                if (str_starts_with($key, 'payout_')) {
                    $payoutSettings->{$key} = $value;
                }
            }
        }
        if (($payoutSettings->payout_mode ?? 'manual') === 'automatic') {
            $payoutSettings->payout_mode = 'manual';
            $payoutSettings->payout_auto_paused = false;
        }
        $summary = [
            'awaiting' => (float) DB::table('seller_payout_ledgers')->where('status', 'awaiting_completion')->sum('seller_net_amount'),
            'reserved' => (float) DB::table('seller_payout_ledgers')->where('status', 'refund_reserved')->sum('seller_net_amount'),
            'available' => (float) DB::table('seller_payout_ledgers')->where('status', 'available')->selectRaw('shop_id, SUM(seller_net_amount) AS balance')->groupBy('shop_id')->get()->sum(fn ($row) => max(0, (float) $row->balance)),
            'processing' => (float) DB::table('seller_payout_ledgers')->whereIn('status', ['requested', 'processing'])->sum('seller_net_amount'),
            'paid' => (float) DB::table('seller_payout_ledgers')->where('status', 'paid')->sum('seller_net_amount'),
            'seller_recovery_pending' => abs((float) DB::table('seller_payout_ledgers')->where('seller_net_amount', '<', 0)->whereIn('status', ['available', 'requested', 'processing'])->sum('seller_net_amount')),
            'pending_refunds' => Schema::hasTable('order_refunds') ? (int) DB::table('order_refunds')->whereIn('status', ['pending', 'pending_seller_approval', 'approved_for_admin_refund', 'processing'])->count() : 0,
        ];

        try {
            $from = Carbon::parse((string) $request->input('from', now()->subDays(29)->toDateString()))->toDateString();
        } catch (\Throwable $e) {
            $from = now()->subDays(29)->toDateString();
        }
        try {
            $to = Carbon::parse((string) $request->input('to', now()->toDateString()))->toDateString();
        } catch (\Throwable $e) {
            $to = now()->toDateString();
        }
        $period = (string) $request->input('period', $request->filled('from') || $request->filled('to') ? '' : 'all');
        if ($period === 'all') {
            [$from, $to] = ['1970-01-01', now()->toDateString()];
        }
        if ($period === 'today') {
            [$from, $to] = [now()->toDateString(), now()->toDateString()];
        } elseif ($period === 'week') {
            [$from, $to] = [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()];
        } elseif ($period === 'month') {
            [$from, $to] = [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];
        } elseif ($period === 'last_month') {
            [$from, $to] = [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()];
        } elseif ($period === 'year') {
            [$from, $to] = [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()];
        } elseif ($period === 'last30') {
            [$from, $to] = [now()->subDays(29)->toDateString(), now()->toDateString()];
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $search = mb_substr(trim((string) $request->input('search', '')), 0, 120);
        $payoutStatus = in_array($request->input('payout_status'), ['requested', 'processing', 'paid', 'cancelled', 'failed'], true) ? (string) $request->input('payout_status') : '';
        $shopInput = (string) $request->input('shop', '');
        $shopFilter = preg_match('/^[A-Za-z0-9_-]{1,40}$/', $shopInput) ? $shopInput : '';
        $sort = $request->input('sort') === 'oldest' ? 'asc' : 'desc';
        $perPage = in_array((int) $request->input('per_page', 10), [10, 25, 50], true) ? (int) $request->input('per_page', 10) : 10;
        $payoutAttention = app(AdminPayoutAttentionService::class)->counts();

        $currentFinanceReconciliation = $this->financeReconciliation->build();
        $financeReconciliation = $this->financeReconciliation->build($from, $to);
        $paymentSummary = $financeReconciliation['summary'];
        $paymentSummary['cash_completed'] = Schema::hasTable('payment_transactions')
            ? (float) DB::table('payment_transactions')->where('payment_status', 'paid')->where('method', 'Cash')->whereBetween(DB::raw('DATE(COALESCE(paid_at, created_at))'), [$from, $to])->sum('amount')
            : 0.0;
        $heldCustomerFunds = $paymentSummary['held_customer_funds'];
        $riderFunds = Schema::hasTable('rider_remittances')
            ? DB::table('rider_remittances as rr')->leftJoin('orders as o', 'o.id', '=', 'rr.order_id')
                ->leftJoin('shops as s', 's.id', '=', 'o.shop_id')
                ->whereBetween(DB::raw('DATE(rr.created_at)'), [$from, $to])
                ->select('rr.*', 'o.status as order_status', 'o.delivery_fee', 's.shop_name')
                ->orderByDesc('rr.created_at')->limit(50)->get()
            : collect();
        $riderFundSummary = [
            'pending' => (float) $riderFunds->whereIn('status', ['pending', 'submitted', 'awaiting_payment', 'qr_expired', 'rejected'])->sum('amount'),
            'confirmed' => (float) $riderFunds->where('status', 'confirmed')->sum('amount'),
        ];

        $commissionSummary = [
            'recorded' => (float) DB::table('seller_payout_ledgers')->where('commission_amount', '>', 0)->sum('commission_amount'),
            'cash_due' => (float) DB::table('seller_payout_ledgers')->where('status', 'cash_settled')->where('commission_amount', '>', 0)->sum('commission_amount'),
        ];

        $shops = DB::table('shops as s')
            ->leftJoin('users as u', 'u.id', '=', 's.seller_id')
            ->where('s.status', 'approved')
            ->when($shopFilter !== '', fn ($q) => $q->where('s.id', $shopFilter))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('s.shop_name', 'like', '%'.$search.'%')->orWhere('u.fullname', 'like', '%'.$search.'%')))
            ->select('s.*', 'u.fullname as seller_name')
            ->orderBy('s.shop_name')
            ->paginate($perPage, ['*'], 'shops_page')->withQueryString()
            ->through(function ($shop) {
                $shop->available_balance = (float) DB::table('seller_payout_ledgers')
                    ->where('shop_id', $shop->id)
                    ->where('status', 'available')
                    ->sum('seller_net_amount');
                $shop->processing_balance = (float) DB::table('seller_payout_ledgers')
                    ->where('shop_id', $shop->id)
                    ->whereIn('status', ['requested', 'processing'])
                    ->sum('seller_net_amount');
                $shop->awaiting_balance = (float) DB::table('seller_payout_ledgers')->where('shop_id', $shop->id)->where('status', 'awaiting_completion')->sum('seller_net_amount');
                $shop->refund_deductions = abs((float) DB::table('seller_payout_ledgers')
                    ->where('shop_id', $shop->id)
                    ->where('seller_net_amount', '<', 0)
                    ->sum('seller_net_amount'));
                $shop->pending_refunds = Schema::hasTable('order_refunds')
                    ? (int) DB::table('order_refunds')->where('shop_id', $shop->id)->whereIn('status', ['pending', 'pending_seller_approval', 'approved_for_admin_refund', 'processing'])->count()
                    : 0;

                return $shop;
            });

        $payoutQuery = fn (bool $filterStatus = true) => DB::table('seller_payouts as p')
            ->join('shops as s', 's.id', '=', 'p.shop_id')
            ->leftJoin('users as u', 'u.id', '=', 'p.seller_id')
            ->when($shopFilter !== '', fn ($q) => $q->where('p.shop_id', $shopFilter))
            ->when($filterStatus && $payoutStatus !== '', fn ($q) => $q->where('p.status', $payoutStatus))
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('s.shop_name', 'like', '%'.$search.'%')->orWhere('u.fullname', 'like', '%'.$search.'%')
                    ->orWhere('p.reference_number', 'like', '%'.$search.'%')->when(ctype_digit($search), fn ($q) => $q->orWhere('p.id', (int) $search))
                    ->orWhereExists(fn ($items) => $items->selectRaw('1')->from('seller_payout_items as pi')->whereColumn('pi.payout_id', 'p.id')->where('pi.order_id', 'like', '%'.$search.'%'));
            }))
            ->select('p.*', 's.shop_name', 'u.fullname as seller_name');

        $payoutRequests = $payoutQuery()
            ->whereIn('p.status', ['requested', 'processing'])
            ->whereBetween(DB::raw('DATE(p.created_at)'), [$from, $to])->orderBy('p.id', $sort)
            ->paginate($perPage, ['*'], 'requests_page')->withQueryString();
        $recentPayouts = $payoutQuery()
            ->whereIn('p.status', ['paid', 'cancelled', 'failed'])
            ->whereBetween(DB::raw('DATE(COALESCE(p.paid_at, p.created_at))'), [$from, $to])->orderBy('p.id', $sort)
            ->paginate($perPage, ['*'], 'payouts_page')->withQueryString();
        $sellerTotals = $payoutQuery(false)
            ->whereBetween(DB::raw('DATE(COALESCE(p.paid_at, p.created_at))'), [$from, $to])
            ->select('p.status')->selectRaw('COUNT(*) as count, SUM(p.net_amount) as amount')
            ->groupBy('p.status')->get()->keyBy('status');
        $requestItems = DB::table('seller_payout_items')->whereIn('payout_id', $payoutRequests->pluck('id')->merge($recentPayouts->pluck('id')))
            ->get()->groupBy('payout_id');
        foreach ($payoutRequests as $pending) {
            $pending->blocked_reason = DB::table('seller_payout_ledgers')->where('payout_id', $pending->id)->whereNotNull('blocked_reason')->value('blocked_reason');
        }
        $onlinePayments = Schema::hasTable('payment_transactions')
            ? DB::table('payment_transactions as pt')
                ->leftJoin('orders as o', 'o.id', '=', 'pt.order_id')
                ->leftJoin('shops as s', 's.id', '=', 'o.shop_id')
                ->where('pt.payment_status', 'paid')->where('pt.method', 'GCash')->whereBetween(DB::raw('DATE(COALESCE(pt.paid_at, pt.created_at))'), [$from, $to])
                ->select('pt.*', 'o.status as order_status', 's.shop_name')
                ->orderByDesc('pt.paid_at')->orderByDesc('pt.id')->limit(50)->get()
            : collect();
        $cashPayments = Schema::hasTable('payment_transactions')
            ? DB::table('payment_transactions as pt')
                ->leftJoin('orders as o', 'o.id', '=', 'pt.order_id')
                ->leftJoin('shops as s', 's.id', '=', 'o.shop_id')
                ->where('pt.payment_status', 'paid')->where('pt.method', 'Cash')->whereBetween(DB::raw('DATE(COALESCE(pt.paid_at, pt.created_at))'), [$from, $to])
                ->select('pt.*', 'o.status as order_status', 's.shop_name')
                ->orderByDesc('pt.paid_at')->orderByDesc('pt.id')->limit(50)->get()
            : collect();

        $paymentBreakdown = DB::table('payment_transactions')
            ->where('payment_status', 'paid')
            ->select('order_id')
            ->selectRaw("SUM(CASE WHEN method = 'GCash' THEN amount ELSE 0 END) as online_paid")
            ->selectRaw("SUM(CASE WHEN method = 'Cash' THEN amount ELSE 0 END) as cash_paid")
            ->groupBy('order_id');

        $commissionRows = DB::table('seller_payout_ledgers as l')
            ->leftJoin('shops as s', 's.id', '=', 'l.shop_id')
            ->leftJoinSub($paymentBreakdown, 'payments', fn ($join) => $join->on('payments.order_id', '=', 'l.order_id'))
            ->where('l.commission_amount', '>', 0)->whereBetween(DB::raw('DATE(l.created_at)'), [$from, $to])
            ->select('l.*', 's.shop_name', 'payments.online_paid', 'payments.cash_paid')
            ->orderByDesc('l.created_at')->limit(50)->get();

        $riderPayoutRequests = Schema::hasTable('rider_payout_requests') ? DB::table('rider_payout_requests as rp')->join('riders as r', 'r.id', '=', 'rp.rider_id')->where('rp.status', 'requested')->select('rp.*', 'r.name as rider_name')->orderByDesc('rp.id')->limit(30)->get() : collect();
        $recentRiderPayouts = Schema::hasTable('rider_payout_requests') ? DB::table('rider_payout_requests as rp')->join('riders as r', 'r.id', '=', 'rp.rider_id')->where('rp.status', 'paid')->whereBetween(DB::raw('DATE(COALESCE(rp.paid_at,rp.created_at))'), [$from, $to])->select('rp.*', 'r.name as rider_name')->orderByDesc('rp.paid_at')->limit(30)->get() : collect();
        $orphanedRiderPayouts = Schema::hasTable('rider_delivery_earnings') && Schema::hasTable('rider_payout_requests')
            ? DB::table('rider_delivery_earnings as e')->join('riders as r', 'r.id', '=', 'e.rider_id')->where('e.status', 'requested')->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('rider_payout_requests as rp')->whereColumn('rp.rider_id', 'e.rider_id')->where('rp.status', 'requested');
            })->select('e.rider_id', 'r.name as rider_name', 'r.gcash_name', 'r.gcash_number', DB::raw('SUM(e.amount) as amount'))->groupBy('e.rider_id', 'r.name', 'r.gcash_name', 'r.gcash_number')->get()
            : collect();
        // Refund completion belongs to Return & Refund Cases; retain an empty collection only for legacy view compatibility.
        $refunds = collect();
        $shopOptions = DB::table('shops as s')->leftJoin('users as u', 'u.id', '=', 's.seller_id')
            ->where('s.status', 'approved')->orderBy('s.shop_name')
            ->get(['s.id', 's.shop_name', 'u.fullname as seller_name']);

        return view('superadmin.payouts', compact('page', 'perPage', 'payoutAttention', 'sellerTotals', 'payoutSettings', 'summary', 'paymentSummary', 'heldCustomerFunds', 'riderFunds', 'riderFundSummary', 'commissionSummary', 'shops', 'payoutRequests', 'recentPayouts', 'recentRiderPayouts', 'refunds', 'onlinePayments', 'cashPayments', 'commissionRows', 'riderPayoutRequests', 'orphanedRiderPayouts', 'financeReconciliation', 'currentFinanceReconciliation', 'from', 'to', 'period', 'search', 'payoutStatus', 'shopFilter', 'shopOptions', 'sort', 'requestItems'));
    }

    public function riderPayouts(Request $request)
    {
        $search = mb_substr(trim((string) $request->input('search', '')), 0, 120);
        $riderId = ctype_digit((string) $request->input('rider', '')) ? (int) $request->input('rider') : null;
        $status = in_array($request->input('status'), ['requested', 'paid', 'recovery'], true) ? $request->input('status') : '';
        $sort = in_array($request->input('sort'), ['oldest', 'amount_high', 'amount_low'], true) ? $request->input('sort') : 'newest';
        $perPage = in_array((int) $request->input('per_page', 10), [10, 25, 50], true) ? (int) $request->input('per_page', 10) : 10;
        $period = in_array($request->input('period'), ['all', 'today', 'week', 'month'], true) ? $request->input('period') : 'all';
        try {
            $from = $request->filled('from') ? Carbon::createFromFormat('Y-m-d', (string) $request->input('from'))->toDateString() : null;
            $to = $request->filled('to') ? Carbon::createFromFormat('Y-m-d', (string) $request->input('to'))->toDateString() : null;
        } catch (\Throwable $e) {
            $from = $to = null;
        }
        if ($period === 'today') [$from, $to] = [now()->toDateString(), now()->toDateString()];
        if ($period === 'week') [$from, $to] = [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()];
        if ($period === 'month') [$from, $to] = [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];
        if ($from && $to && $from > $to) [$from, $to] = [$to, $from];

        $base = fn () => DB::table('rider_payout_requests as rp')->join('riders as r', 'r.id', '=', 'rp.rider_id')
            ->when($riderId, fn ($query) => $query->where('rp.rider_id', $riderId))
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search) {
                $nested->where('r.name', 'like', '%'.$search.'%')
                    ->orWhere('r.phone', 'like', '%'.$search.'%')
                    ->orWhere('rp.gcash_name', 'like', '%'.$search.'%')
                    ->orWhere('rp.gcash_number', 'like', '%'.$search.'%')
                    ->orWhere('rp.reference_number', 'like', '%'.$search.'%')
                    ->when(ctype_digit($search), fn ($q) => $q->orWhere('rp.id', (int) $search));
            }))
            ->when($from, fn ($query) => $query->whereDate('rp.created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('rp.created_at', '<=', $to));
        $totals = Schema::hasTable('rider_payout_requests')
            ? $base()->select('rp.status')->selectRaw('COUNT(*) as count, SUM(rp.amount) as amount')->groupBy('rp.status')->get()->keyBy('status')
            : collect();
        $records = new LengthAwarePaginator([], 0, $perPage);
        if (Schema::hasTable('rider_payout_requests')) {
            $query = $base()->when($status !== '' && $status !== 'recovery', fn ($q) => $q->where('rp.status', $status))
                ->select('rp.*', 'r.name as rider_name');
            if ($sort === 'amount_high') $query->orderByDesc('rp.amount');
            elseif ($sort === 'amount_low') $query->orderBy('rp.amount');
            $query->orderBy('rp.id', $sort === 'oldest' ? 'asc' : 'desc');
            $records = $query->paginate($perPage)->withQueryString();
        }
        $recoveryQuery = Schema::hasTable('rider_delivery_earnings') && Schema::hasTable('rider_payout_requests')
            ? DB::table('rider_delivery_earnings as e')->join('riders as r', 'r.id', '=', 'e.rider_id')
                ->where('e.status', 'requested')
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('rider_payout_requests as rp')
                    ->whereColumn('rp.rider_id', 'e.rider_id')->where('rp.status', 'requested'))
                ->when($riderId, fn ($q) => $q->where('e.rider_id', $riderId))
                ->when($search !== '', fn ($q) => $q->where(fn ($nested) => $nested->where('r.name', 'like', '%'.$search.'%')
                    ->orWhere('r.phone', 'like', '%'.$search.'%')->orWhere('r.gcash_name', 'like', '%'.$search.'%')
                    ->orWhere('r.gcash_number', 'like', '%'.$search.'%')))
                ->when($from, fn ($q) => $q->whereDate('e.created_at', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('e.created_at', '<=', $to))
                ->select('e.rider_id', 'r.name as rider_name', 'r.gcash_name', 'r.gcash_number')
                ->selectRaw('SUM(e.amount) as amount')->groupBy('e.rider_id', 'r.name', 'r.gcash_name', 'r.gcash_number')
            : null;
        $recoveryTotals = $recoveryQuery ? (clone $recoveryQuery)->get() : collect();
        $recovery = $recoveryQuery
            ? $recoveryQuery->orderBy('r.name')->paginate($perPage, ['*'], 'recovery_page')->withQueryString()
            : new LengthAwarePaginator([], 0, $perPage);
        $riders = Schema::hasTable('riders')
            ? DB::table('riders as r')->leftJoin('shops as s', 's.id', '=', 'r.shop_id')
                ->orderBy('r.name')->get(['r.id', 'r.name', 's.shop_name'])
            : collect();
        $payoutAttention = app(AdminPayoutAttentionService::class)->counts();

        return view('superadmin.rider-payouts', compact('records', 'recovery', 'recoveryTotals', 'totals', 'riders', 'payoutAttention', 'search', 'riderId', 'status', 'sort', 'perPage', 'period', 'from', 'to'));
    }

    public function saveSettings(Request $request)
    {
        $validated = $request->validate([

            'payout_schedule' => 'nullable|in:daily,weekly,twice_monthly,monthly',
            'payout_first_approval_required' => 'nullable|boolean',
            'payout_auto_paused' => 'nullable|boolean',
        ], [
            'payout_minimum_amount.min' => 'Minimum payout amount cannot be negative.',
        ]);

        $forcedPayoutMode = 'manual';

        $updates = [
            'payout_mode' => $forcedPayoutMode,
            'payout_hold_days' => 0,
            'payout_minimum_amount' => 0,
            'payout_schedule' => $validated['payout_schedule'] ?? 'weekly',
            'payout_first_approval_required' => $forcedPayoutMode === 'automatic'
                ? $request->boolean('payout_first_approval_required')
                : false,
            'payout_auto_paused' => $forcedPayoutMode === 'automatic'
                ? $request->boolean('payout_auto_paused')
                : false,
            'updated_at' => now(),
        ];

        $existing = DB::table('platform_settings')->orderBy('id')->first();
        if ($existing) {
            DB::table('platform_settings')->where('id', $existing->id)->update($updates);
            DB::table('platform_settings')->where('id', '<>', $existing->id)->update($updates);
        } else {
            $updates['platform_name'] = 'Cake Shop Platform';
            $updates['created_at'] = now();
            DB::table('platform_settings')->insert($updates);
        }

        $saved = (object) array_merge((array) $this->payouts->settings(), $updates);

        return redirect()->route('superadmin.payouts')
            ->with('payout_settings_saved', $updates)
            ->with('msg', 'Payout settings saved. Current mode: '.ucfirst($saved->payout_mode).'. Automatic is '.($saved->payout_auto_paused ? 'paused' : 'active').'.');
    }

    public function verifySeller(Request $request, string $shopId)
    {
        $shop = DB::table('shops')->where('id', $shopId)->first();
        if (! $shop) {
            return back()->with('err', 'Seller shop not found.');
        }

        if (empty($shop->payout_method) || empty($shop->payout_account_name) || empty($shop->payout_account_number)) {
            return back()->with('err', 'Seller payout details are incomplete. Ask the seller to complete bank/e-wallet details first.');
        }

        DB::table('shops')->where('id', $shopId)->update([
            'payout_details_verified' => true,
            'updated_at' => now(),
        ]);

        $this->notifySellerPayout($shop, 'Payout Details Verified', 'Your GCash payout details have been verified. You can now request eligible payouts.', [
            'event' => 'seller_payout_details_verified',
            'shop_id' => (string) $shop->id,
        ]);

        return back()->with('msg', "{$shop->shop_name} payout details marked as verified.");
    }

    public function requestSellerDetails(Request $request, string $shopId)
    {
        $shop = DB::table('shops')->where('id', $shopId)->first();
        if (! $shop) {
            return back()->with('err', 'Seller shop not found.');
        }

        if (! empty($shop->payout_method) && ! empty($shop->payout_account_name) && ! empty($shop->payout_account_number)) {
            return back()->with('msg', "{$shop->shop_name} already submitted payout details.");
        }

        $result = $this->notifySellerPayout($shop, 'Payout Details Needed', 'Please add your exact GCash account name and mobile number so your payout can be processed.', [
            'event' => 'seller_payout_details_requested',
            'shop_id' => (string) $shop->id,
        ]);

        $channel = $result['channel'] ?? 'none';
        $message = $channel === 'push'
            ? "Payout details request sent to {$shop->shop_name} by mobile push."
            : "Payout details request saved for {$shop->shop_name}. It will show on the seller website payout badge.";

        return back()->with('msg', $message);
    }

    public function createManual(Request $request, string $shopId)
    {
        try {
            $id = $this->payouts->createPayoutForShop($shopId, 'manual', trim((string) $request->input('admin_note', '')) ?: 'Created by Super Admin.', 'super_admin', 'super_admin', (string) (session('user')['id'] ?? ''));
        } catch (\DomainException $e) {
            return back()->with('err', $e->getMessage());
        }
        if (! $id) {
            return back()->with('err', 'No eligible payout was created. Check completed orders, issues, payout pause, and verified seller details.');
        }

        $payout = DB::table('seller_payouts')->where('id', $id)->first();
        if ($payout) {
            $this->notifySellerPayout($payout, 'Payout Request Created', 'A payout request for PHP '.number_format((float) $payout->net_amount, 2).' was created and is waiting for transfer.', [
                'event' => 'seller_payout_created',
                'payout_id' => (string) $payout->id,
                'shop_id' => (string) $payout->shop_id,
                'amount' => number_format((float) $payout->net_amount, 2, '.', ''),
                'status' => (string) $payout->status,
            ]);
        }

        return back()->with('msg', "Manual payout request #{$id} created for review/payment.");
    }

    public function markPaid(Request $request, int $payoutId)
    {
        $validated = $request->validate([
            'payout_receipt' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'reference_number' => 'required|string|min:6|max:120',
            'admin_note' => 'nullable|string|max:500',
        ], [
            'payout_receipt.required' => 'Upload the GCash receipt screenshot before marking payout as paid.',
            'payout_receipt.image' => 'The payout receipt must be a valid image file.',
        ]);

        $payout = DB::table('seller_payouts')->where('id', $payoutId)->first();
        if (! $payout) {
            return back()->with('err', 'Payout not found.');
        }
        if ($payout->status === 'paid') {
            return back()->with('msg', 'This payout was already marked as paid.');
        }

        try {
            DB::transaction(fn () => app(SelectedPayoutService::class)->assertTransferEligible((int) $payout->id));
        } catch (\DomainException $e) {
            return $request->expectsJson() ? response()->json(['ok' => false, 'errors' => [$e->getMessage()]], 422) : back()->with('err', $e->getMessage());
        }
        $scan = $this->receiptScanner->scan($request->file('payout_receipt'), $payout);
        if (! $scan['ok'] || ($scan['reference_number'] ?? null) !== $validated['reference_number']) {
            return back()->with('err', implode(' ', $scan['errors'] ?? ['Receipt scan failed. Please scan the receipt again.']));
        }
        if (DB::table('seller_payouts')->where('id', '<>', $payoutId)->where('reference_number', $scan['reference_number'])->exists()) {
            return back()->with('err', 'This receipt reference is already linked to another payout.');
        }

        $receiptPath = $this->uploadFile($request->file('payout_receipt'), 'uploads/payout_receipts');
        if (! $receiptPath) {
            return back()->with('err', 'Receipt upload failed. Please try again with a smaller JPG/PNG/WebP image.');
        }

        try {
            DB::transaction(function () use ($payout, $validated, $receiptPath, $scan, $request) {
                app(SelectedPayoutService::class)->assertTransferEligible((int) $payout->id);
                $updates = [
                    'status' => 'paid',
                    'reference_number' => $scan['reference_number'],
                    'admin_note' => $validated['admin_note'] ?? $payout->admin_note,
                    'processed_at' => $payout->processed_at ?? now(),
                    'paid_at' => now(),
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('seller_payouts', 'payout_receipt_path')) {
                    $updates['payout_receipt_path'] = $receiptPath;
                }
                if (Schema::hasColumn('seller_payouts', 'receipt_scan_status')) {
                    $updates['receipt_scan_status'] = 'verified';
                    $updates['receipt_scan_data'] = json_encode($scan);
                    $updates['receipt_scanned_at'] = now();
                    $updates['payout_receipt_hash'] = hash_file('sha256', $request->file('payout_receipt')->getRealPath());
                }

                DB::table('seller_payouts')->where('id', $payout->id)->update($updates);
                DB::table('seller_payout_ledgers')->where('payout_id', $payout->id)->update([
                    'status' => 'paid',
                    'updated_at' => now(),
                ]);
                app(SellerBalanceService::class)->event($payout->shop_id, null, 'payout_paid', (float) $payout->net_amount, 'paid', 'Verified transfer. Reference: '.$scan['reference_number'], (int) $payout->id);
            });
        } catch (\DomainException $e) {
            return back()->with('err', $e->getMessage());
        }

        $this->notifySellerPayout($payout, 'Payout Paid', 'Your payout #'.$payoutId.' for PHP '.number_format((float) $payout->net_amount, 2).' has been marked as paid.', [
            'event' => 'seller_payout_paid',
            'payout_id' => (string) $payoutId,
            'shop_id' => (string) $payout->shop_id,
            'amount' => number_format((float) $payout->net_amount, 2, '.', ''),
            'status' => 'paid',
        ]);

        return back()->with('msg', "Payout #{$payoutId} marked as paid.");
    }

    public function scanReceipt(Request $request, int $payoutId)
    {
        $request->validate(['payout_receipt' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $payout = DB::table('seller_payouts')->where('id', $payoutId)->first();
        if (! $payout || ! in_array($payout->status, ['requested', 'processing'], true)) {
            return response()->json(['ok' => false, 'errors' => ['This payout is not available for payment.']], 422);
        }
        try {
            DB::transaction(fn () => app(SelectedPayoutService::class)->assertTransferEligible($payoutId));
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'errors' => [$e->getMessage()]], 422);
        }
        $scan = $this->receiptScanner->scan($request->file('payout_receipt'), $payout);
        if (($scan['reference_number'] ?? null) && DB::table('seller_payouts')->where('id', '<>', $payoutId)->where('reference_number', $scan['reference_number'])->exists()) {
            $scan['errors'][] = 'This receipt reference is already linked to another payout.';
        }
        $scan['ok'] = empty($scan['errors']);

        return response()->json($scan, $scan['ok'] ? 200 : 422);
    }

    public function scanRefundReceipt(Request $request, int $refundId)
    {
        $request->validate(['refund_receipt' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $refund = DB::table('order_refunds')->where('id', $refundId)->first();
        if (! $refund || ! in_array($refund->status, ['approved_for_admin_refund', 'processing'], true)) {
            return response()->json(['ok' => false, 'errors' => ['This refund is not awaiting Super Admin action.']], 422);
        }
        $scan = $this->receiptScanner->scanRefund($request->file('refund_receipt'), $refund);

        return response()->json($scan, $scan['ok'] ? 200 : 422);
    }

    public function markRefundSent(Request $request, int $refundId, OrderRefundService $refunds)
    {
        $validated = $request->validate([
            'refund_receipt' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'reference_number' => 'required|string|min:6|max:120',
            'admin_note' => 'nullable|string|max:500',
        ]);
        $refund = DB::table('order_refunds')->where('id', $refundId)->first();
        if (! $refund || ! in_array($refund->status, ['approved_for_admin_refund', 'processing'], true)) {
            return back()->with('err', 'This refund is not awaiting Super Admin action.');
        }
        $order = DB::table('orders')->where('id', $refund->order_id)->first();
        if (! $order) {
            return back()->with('err', 'The order for this refund no longer exists.');
        }
        $scan = $this->receiptScanner->scanRefund($request->file('refund_receipt'), $refund);
        if (! $scan['ok'] || ! hash_equals((string) ($scan['reference_number'] ?? ''), trim($validated['reference_number']))) {
            return back()->with('err', implode(' ', $scan['errors'] ?? ['Receipt scan validation failed.']));
        }
        $receiptPath = $this->uploadFile($request->file('refund_receipt'), 'uploads/refund_receipts');
        if (! $receiptPath) {
            return back()->with('err', 'Receipt upload failed. Please try again with a valid image.');
        }
        try {
            $refunds->completeAdminRefund($order, $refund, $receiptPath, $scan, trim((string) ($validated['admin_note'] ?? '')), (string) (session('user')['id'] ?? ''), hash_file('sha256', $request->file('refund_receipt')->getRealPath()));
        } catch (\DomainException $e) {
            return back()->with('err', $e->getMessage());
        }

        return back()->with('msg', "Refund #{$refundId} marked as completed.");
    }

    public function scanRiderPayoutReceipt(Request $request, int $id)
    {
        $request->validate(['receipt' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $p = DB::table('rider_payout_requests')->where('id', $id)->where('status', 'requested')->first();
        if (! $p) {
            return response()->json(['ok' => false, 'errors' => ['Request unavailable.']], 422);
        }
        try {
            DB::transaction(fn () => app(RiderPayoutService::class)->allocations($p));
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'errors' => [$e->getMessage()]], 422);
        }
        $scan = $this->receiptScanner->scanRefund($request->file('receipt'), (object) ['id' => 0, 'refund_amount' => $p->amount, 'refund_gcash_name' => $p->gcash_name, 'refund_gcash_number' => $p->gcash_number]);

        return response()->json($scan, $scan['ok'] ? 200 : 422);
    }

    public function markRiderPayoutPaid(Request $request, int $id)
    {
        $request->validate(['receipt' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120', 'reference_number' => 'required|string|min:6|max:120']);
        $p = DB::table('rider_payout_requests')->where('id', $id)->where('status', 'requested')->first();
        if (! $p) {
            return back()->with('err', 'Rider payout request unavailable.');
        }$scan = $this->receiptScanner->scanRefund($request->file('receipt'), (object) ['id' => 0, 'refund_amount' => $p->amount, 'refund_gcash_name' => $p->gcash_name, 'refund_gcash_number' => $p->gcash_number]);
        if (! $scan['ok'] || ! hash_equals((string) $scan['reference_number'], trim($request->reference_number))) {
            return back()->with('err', implode(' ', $scan['errors'] ?? ['Receipt verification failed.']));
        }
        $path = $this->uploadFile($request->file('receipt'), 'uploads/rider_payout_receipts');
        if (! $path) {
            return back()->with('err', 'Receipt upload failed. No funds were marked paid.');
        }
        try {
            DB::transaction(function () use ($p, $path, $scan) {
                $allocations = app(RiderPayoutService::class)->allocations($p);
                DB::table('rider_payout_requests')->where('id', $p->id)->update(['status' => 'paid', 'reference_number' => $scan['reference_number'], 'receipt_path' => $path, 'paid_at' => now(), 'paid_by_user_id' => session('user')['id'] ?? null, 'updated_at' => now()]);
                DB::table('rider_delivery_earnings')->whereIn('id', $allocations->pluck('id'))->update(['status' => 'paid', 'payout_id' => $p->id, 'updated_at' => now()]);
            });
        } catch (\DomainException $e) {
            return back()->with('err', $e->getMessage());
        }$rider = DB::table('riders')->where('id', $p->rider_id)->first();
        $receiptUrl = route('rider.payouts.receipt', $p->id, false);
        $this->notifications->notifyRider((int) $p->rider_id, $rider->phone ?? null, 'Payout paid', 'Your delivery-fee payout of PHP '.number_format($p->amount, 2).' is paid. View your receipt.', ['event' => 'rider_payout_paid', 'url' => $receiptUrl], 'Your delivery-fee payout of PHP '.number_format($p->amount, 2).' is paid. Receipt: '.$receiptUrl, $receiptUrl);

        return back()->with('msg', 'Rider delivery-fee payout marked paid.');
    }

    public function recoverRiderPayout(Request $request, int $riderId)
    {
        $rider = DB::table('riders')->where('id', $riderId)->first();
        if (! $rider) {
            return back()->with('err', 'Rider not found.');
        }try {
            DB::transaction(function () use ($rider) {
                if (DB::table('rider_payout_requests')->where('rider_id', $rider->id)->where('status', 'requested')->lockForUpdate()->exists()) {
                    throw new \DomainException('A payout request already exists for this rider.');
                }$amount = round((float) DB::table('rider_delivery_earnings')->where('rider_id', $rider->id)->where('status', 'requested')->lockForUpdate()->sum('amount'), 2);
                if ($amount <= 0) {
                    throw new \DomainException('No unlinked requested rider earnings remain.');
                }DB::table('rider_payout_requests')->insert(['rider_id' => $rider->id, 'amount' => $amount, 'gcash_name' => $rider->gcash_name ?? $rider->name, 'gcash_number' => $rider->gcash_number ?? '', 'status' => 'requested', 'created_at' => now(), 'updated_at' => now()]);
            });
        } catch (\DomainException $e) {
            return back()->with('err', $e->getMessage());
        }

        return back()->with('msg', 'Rider payout request recovered and ready for payment.');
    }

    public function runAutomatic()
    {
        return back()->with('err', 'Automatic payouts are not available yet. Manual payouts remain active while business verification and payout provider access are being prepared.');
    }

    public function downloadReceipt(int $payoutId)
    {
        $payout = DB::table('seller_payouts')->where('id', $payoutId)->first();
        if (! $payout || empty($payout->payout_receipt_path)) {
            abort(404);
        }

        return $this->downloadReceiptResponse((string) $payout->payout_receipt_path, 'payout-receipt-'.$payout->id.'.jpg');
    }

    public function downloadRiderPayoutReceipt(int $payoutId)
    {
        $payout = DB::table('rider_payout_requests')->where('id', $payoutId)->where('status', 'paid')->first();
        if (! $payout || empty($payout->receipt_path)) {
            abort(404);
        }

        return $this->downloadReceiptResponse((string) $payout->receipt_path, 'rider-payout-receipt-'.$payout->id.'.jpg');
    }

    private function notifySellerPayout(object $source, string $title, string $message, array $data): array
    {
        $sellerId = $source->seller_id ?? null;
        if (! $sellerId && ! empty($source->shop_id)) {
            $sellerId = DB::table('shops')->where('id', $source->shop_id)->value('seller_id');
        }

        $phone = $sellerId ? DB::table('users')->where('id', $sellerId)->value('phone') : null;
        $url = route('seller.payouts', [], false);

        return $this->notifications->notifyUser(
            'seller',
            $sellerId ? (string) $sellerId : null,
            $phone,
            $title,
            $message,
            $data + ['url' => $url],
            null,
            $url
        );
    }

    private function downloadReceiptResponse(string $url, string $filename)
    {
        $response = Http::timeout(20)->get($url);
        if (! $response->successful()) {
            abort(404);
        }

        $contentType = $response->header('Content-Type') ?: 'image/jpeg';
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) ?: 'payout-receipt.jpg';

        return response($response->body(), 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=60',
        ]);
    }
}
