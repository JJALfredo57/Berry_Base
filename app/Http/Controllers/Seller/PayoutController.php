<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\PayoutItemBreakdownService;
use App\Services\SelectedPayoutService;
use App\Services\SellerPayoutService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class PayoutController extends Controller
{
    public function __construct(private SellerPayoutService $payouts) {}

    private function shop()
    {
        return DB::table('shops')->where('seller_id', session('user')['id'])->first();
    }

    public function index(Request $request)
    {
        $shop = $this->shop();
        if (! $shop) {
            return redirect()->route('seller.apply')->with('err', 'Your shop is not found.');
        }

        if (Schema::hasTable('mobile_notifications')) {
            DB::table('mobile_notifications')
                ->where('role', 'seller')
                ->where('user_id', (string) session('user')['id'])
                ->where('is_read', false)
                ->where('event_type', 'like', 'seller_payout%')
                ->update(['is_read' => true, 'updated_at' => now()]);
        }

        $payoutSettings = $this->payouts->settings();
        $summary = $this->payouts->summaryForShop($shop->id);
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
        $period = (string) $request->input('period', '');
        $allDates = $period === 'all' || ($period === '' && ! $request->filled('from') && ! $request->filled('to'));
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
        $search = trim((string) $request->input('search', ''));
        $method = (string) $request->input('method', '');
        $fulfillment = (string) $request->input('fulfillment', '');
        $state = (string) $request->input('state', '');
        $orderStatus = (string) $request->input('order_status', '');
        $historyStatus = (string) $request->input('history_status', '');
        $sort = $request->input('sort') === 'oldest' ? 'oldest' : 'newest';
        $ledgerQuery = DB::table('seller_payout_ledgers as l')
            ->leftJoin('orders as o', 'o.id', '=', DB::raw('COALESCE(l.source_order_id, l.order_id)'))
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->where('l.shop_id', $shop->id)
            ->when(! $allDates, fn ($q) => $q->whereBetween(DB::raw('DATE(l.created_at)'), [$from, $to]))
            ->when(in_array($method, ['GCash', 'COD', 'COP']), fn ($q) => $q->where('o.payment_method', $method))
            ->when(in_array($fulfillment, ['Delivery', 'Pickup']), fn ($q) => $q->where('o.fulfillment_type', $fulfillment))
            ->when(in_array($state, ['available', 'awaiting_completion', 'refund_reserved', 'requested', 'processing', 'paid', 'refunded', 'cash_settled', 'cancelled', 'finance_review']), fn ($q) => $q->where('l.status', $state))
            ->when($state === 'unrequested', fn ($q) => $q->whereIn('l.status', ['awaiting_completion', 'available']))
            ->when($state === 'transfer_pending', fn ($q) => $q->whereIn('l.status', ['requested', 'processing']))
            ->when($orderStatus !== '', fn ($q) => $q->where('o.status', $orderStatus))
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('l.order_id', 'like', '%'.$search.'%')->orWhere('l.source_order_id', 'like', '%'.$search.'%')->orWhere('p.name', 'like', '%'.$search.'%');
                if (Schema::hasTable('order_items')) {
                    $q->orWhereExists(fn ($items) => $items->selectRaw('1')->from('order_items')
                        ->whereColumn('order_items.order_id', 'l.order_id')->where('product_name', 'like', '%'.$search.'%'));
                }
            }));
        $filteredTotal = (float) (clone $ledgerQuery)->sum('l.seller_net_amount');
        $ledgers = $ledgerQuery->select('l.*', 'o.status as order_status', 'o.payment_method', 'o.fulfillment_type', 'p.name as product_name')
            ->orderBy('l.id', $sort === 'oldest' ? 'asc' : 'desc')->paginate(12, ['*'], 'orders_page')->withQueryString();
        $itemsByOrder = Schema::hasTable('order_items')
            ? DB::table('order_items')->whereIn('order_id', $ledgers->pluck('order_id'))->orderBy('id')->get()->groupBy('order_id')
            : collect();
        foreach ($ledgers as $ledger) {
            $ledger->display_order_id = $ledger->source_order_id ?: $ledger->order_id;
            $ledger->items = app(PayoutItemBreakdownService::class)->forLedger($ledger, $itemsByOrder->get($ledger->order_id, collect()));
        }

        $payouts = DB::table('seller_payouts')->where('shop_id', $shop->id)
            ->when(! $allDates, fn ($q) => $q->whereBetween(DB::raw('DATE(created_at)'), [$from, $to]))
            ->when(in_array($historyStatus, ['requested', 'processing', 'paid', 'cancelled']), fn ($q) => $q->where('status', $historyStatus))
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', '%'.$search.'%')->orWhere('id', 'like', '%'.$search.'%')
                    ->orWhereExists(fn ($items) => $items->selectRaw('1')->from('seller_payout_items')
                        ->whereColumn('seller_payout_items.payout_id', 'seller_payouts.id')->where('order_id', 'like', '%'.$search.'%'));
            }))->orderBy('id', $sort === 'oldest' ? 'asc' : 'desc')->paginate(10, ['*'], 'history_page')->withQueryString();
        $payoutItems = DB::table('seller_payout_items')->whereIn('payout_id', $payouts->pluck('id'))->get()->groupBy('payout_id');
        $refunds = Schema::hasTable('order_refunds')
            ? DB::table('order_refunds')->where('shop_id', $shop->id)->when(! $allDates, fn ($q) => $q->whereBetween(DB::raw('DATE(created_at)'), [$from, $to]))
                ->when($search !== '', fn ($q) => $q->where('order_id', 'like', '%'.$search.'%'))->orderByDesc('id')->paginate(10, ['*'], 'refund_page')->withQueryString()
            : collect();
        $returnRefunds = Schema::hasTable('post_delivery_return_cases')
            ? DB::table('post_delivery_return_cases')->where('shop_id', $shop->id)->when(! $allDates, fn ($q) => $q->whereBetween(DB::raw('DATE(created_at)'), [$from, $to]))
                ->when($search !== '', fn ($q) => $q->where('order_id', 'like', '%'.$search.'%'))->orderByDesc('id')->paginate(10, ['*'], 'return_page')->withQueryString()
            : collect();
        $activityType = (string) $request->input('activity_type', '');
        $events = Schema::hasTable('seller_balance_events')
            ? DB::table('seller_balance_events')->where('shop_id', $shop->id)
                ->when(! $allDates, fn ($q) => $q->whereBetween(DB::raw('DATE(created_at)'), [$from, $to]))
                ->when($search !== '', fn ($q) => $q->where('order_id', 'like', '%'.$search.'%'))
                ->when($activityType !== '', fn ($q) => $q->where('type', $activityType))
                ->orderBy('id', $sort === 'oldest' ? 'asc' : 'desc')->paginate(10, ['*'], 'activity_page')->withQueryString()
            : collect();
        if ($allDates) {
            [$from, $to] = ['', ''];
        }

        return view('seller.payouts', compact(
            'shop', 'payoutSettings', 'summary', 'ledgers', 'payouts', 'payoutItems', 'refunds', 'returnRefunds',
            'from', 'to', 'period', 'search', 'method', 'fulfillment', 'state', 'orderStatus', 'historyStatus', 'sort', 'filteredTotal', 'events', 'activityType'
        ));
    }

    public function saveDetails(Request $request)
    {
        $shop = $this->shop();
        if (! $shop) {
            return redirect()->route('seller.apply')->with('err', 'Your shop is not found.');
        }

        $validated = $request->validate([
            'payout_account_name' => 'required|string|min:3|max:150',
            'payout_account_number' => ['required', 'string', 'regex:/^(09\d{9}|\+639\d{9})$/'],
        ], [
            'payout_account_name.required' => 'Enter the GCash account name exactly as registered.',
            'payout_account_number.required' => 'Enter the GCash mobile number.',
            'payout_account_number.regex' => 'Enter a valid GCash mobile number, like 09XXXXXXXXX or +639XXXXXXXXX.',
        ]);

        DB::table('shops')->where('id', $shop->id)->update([
            'payout_method' => 'gcash',
            'payout_institution' => 'GCash',
            'payout_account_name' => trim($validated['payout_account_name']),
            'payout_account_number' => trim($validated['payout_account_number']),
            'payout_details_verified' => false,
            'updated_at' => now(),
        ]);

        return redirect()->route('seller.payouts')->with('msg', 'Payout details saved. Admin verification is required before requesting a payout.');
    }

    public function requestManual(Request $request)
    {
        $shop = $this->shop();
        if (! $shop) {
            return redirect()->route('seller.apply')->with('err', 'Your shop is not found.');
        }

        if (empty($shop->payout_method) || empty($shop->payout_account_name) || empty($shop->payout_account_number)) {
            return back()->with('err', 'Complete your payout details before requesting a payout.');
        }

        $data = $request->validate(['ledger_ids' => ['required', 'array', 'min:1', 'max:100'], 'ledger_ids.*' => ['required', 'integer', 'distinct']]);
        try {
            $id = $this->payouts->createPayoutForShop($shop->id, 'manual', 'Selected orders requested by seller.', 'seller', 'seller', (string) session('user')['id'], $data['ledger_ids']);
        } catch (\DomainException $e) {
            return back()->with('err', $e->getMessage());
        }
        if (! $id) {
            return back()->with('err', 'No eligible payout. Check completed orders, account verification, and payout restrictions.');
        }

        return back()->with('msg', "Payout request #{$id} submitted. Admin will verify and mark it paid after transfer.");
    }

    public function cancelRequest(int $payoutId)
    {
        $shop = $this->shop();
        if (! $shop) {
            abort(403);
        }
        if (DB::table('seller_payouts')->where('id', $payoutId)->where('shop_id', $shop->id)->value('status') !== 'requested') {
            return back()->with('err', 'Only requests not yet processing can be cancelled here. Contact Super Admin about an active transfer.');
        }
        try {
            app(SelectedPayoutService::class)->cancel($payoutId, $shop->id);
        } catch (\DomainException $e) {
            return back()->with('err', $e->getMessage());
        }

        return back()->with('msg', 'Untransferred request cancelled. Funds were reconciled; original history is preserved.');
    }

    public function downloadReceipt(int $payoutId)
    {
        $shop = $this->shop();
        if (! $shop) {
            abort(404);
        }

        $payout = DB::table('seller_payouts')
            ->where('id', $payoutId)
            ->where('shop_id', $shop->id)
            ->first();

        if (! $payout || empty($payout->payout_receipt_path)) {
            abort(404);
        }

        return $this->downloadReceiptResponse((string) $payout->payout_receipt_path, 'payout-receipt-'.$payout->id.'.jpg');
    }

    public function downloadRefundReceipt(string $kind, int $refundId)
    {
        $shop = $this->shop();
        if (! $shop || ! in_array($kind, ['cancellation', 'return'], true)) {
            abort(404);
        }
        $table = $kind === 'return' ? 'post_delivery_return_cases' : 'order_refunds';
        $record = DB::table($table)->where('id', $refundId)->where('shop_id', $shop->id)->where('status', 'refunded')->first();
        $path = $kind === 'return' ? ($record->refund_receipt_path ?? null) : ($record->receipt_path ?? null);
        if (! $path) {
            abort(404);
        }

        return $this->downloadReceiptResponse($path, 'refund-'.$kind.'-'.$refundId.'.jpg');
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
