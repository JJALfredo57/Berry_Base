<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only finance reporting. All totals are calculated before pagination. */
class FinanceBreakdownService
{
    private const METRICS = [
        'customer-funds' => ['Customer funds', 'Customer funds', 'online_settled', 'payments'],
        'customer-service-fee' => ['Customer funds', 'Customer-paid service fee', 'service_fee', 'payments'],
        'customer-checkout-total' => ['Customer funds', 'Customer checkout total', 'customer_charged', 'payments'],
        'refunds-sent' => ['Customer funds', 'Refunds sent', 'refund_amount', 'refunds'],
        'seller-in-process' => ['Seller funds in process', 'Seller funds in process', 'seller_net_amount', 'seller'],
        'awaiting-completion' => ['Seller funds in process', 'Awaiting Completion', 'seller_net_amount', 'seller'],
        'ready-payout' => ['Seller funds in process', 'Ready for seller payout', 'seller_net_amount', 'seller'],
        'refund-reserved' => ['Seller funds in process', 'Refund reserved', 'seller_net_amount', 'seller'],
        'transfer-pending' => ['Seller funds in process', 'Transfer pending', 'seller_net_amount', 'seller'],
        'paid-to-sellers' => ['Seller funds in process', 'Paid to sellers', 'seller_net_amount', 'seller'],
        'verified-commission' => ['Verified platform commission', 'Verified platform commission', 'commission', 'reconciliation'],
        'seller-awaiting-release' => ['Verified platform commission', 'Seller funds awaiting release', 'seller_payable', 'reconciliation'],
        'rider-awaiting-release' => ['Verified platform commission', 'Rider funds awaiting release', 'rider_payable', 'reconciliation'],
        'reconciled-seller-paid' => ['Verified platform commission', 'Paid to sellers', 'seller_paid', 'reconciliation'],
        'rider-fees-paid' => ['Verified platform commission', 'Rider fees paid', 'rider_paid', 'reconciliation'],
        'recorded-commission' => ['Verified platform commission', 'Recorded commission', 'commission', 'reconciliation'],
    ];

    public function definition(string $metric): ?array
    {
        return self::METRICS[$metric] ?? null;
    }

    public function period(Request $request): array
    {
        $date = static function ($value, string $fallback): string {
            try {
                $parsed = Carbon::createFromFormat('!Y-m-d', (string) $value);

                return $parsed && $parsed->format('Y-m-d') === (string) $value ? $parsed->toDateString() : $fallback;
            } catch (\Throwable) {
                return $fallback;
            }
        };
        $from = $date($request->input('from'), now()->subDays(29)->toDateString());
        $to = $date($request->input('to'), now()->toDateString());
        $period = (string) $request->input('period', $request->filled('from') || $request->filled('to') ? '' : 'all');
        $ranges = [
            'all' => ['1970-01-01', now()->toDateString()],
            'today' => [now()->toDateString(), now()->toDateString()],
            'week' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'month' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            'year' => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            'last30' => [now()->subDays(29)->toDateString(), now()->toDateString()],
        ];
        if (isset($ranges[$period])) {
            [$from, $to] = $ranges[$period];
        } else {
            $period = '';
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return compact('from', 'to', 'period');
    }

    public function sellerSummary(string $from, string $to): array
    {
        $rows = DB::table('seller_payout_ledgers')
            ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->select('shop_id', 'status')
            ->selectRaw('SUM(seller_net_amount) AS amount')
            ->groupBy('shop_id', 'status')->get();

        return [
            'awaiting' => round((float) $rows->where('status', 'awaiting_completion')->sum('amount'), 2),
            'available' => round((float) $rows->where('status', 'available')->sum(fn ($row) => max(0, (float) $row->amount)), 2),
            'reserved' => round((float) $rows->where('status', 'refund_reserved')->sum('amount'), 2),
            'processing' => round((float) $rows->whereIn('status', ['requested', 'processing'])->sum('amount'), 2),
            'paid' => round((float) $rows->where('status', 'paid')->sum('amount'), 2),
        ];
    }

    public function report(string $metric, array $filters, FinanceReconciliationService $reconciliation): array
    {
        $definition = $this->definition($metric);
        if (! $definition) {
            abort(404);
        }
        [$group, $title, $field, $source] = $definition;
        $rows = match ($source) {
            'payments' => $this->paymentRows($filters),
            'refunds' => $this->refundRows($filters, $reconciliation),
            'seller' => $this->sellerRows($metric, $filters),
            default => $this->reconciliationRows($metric, $filters, $reconciliation),
        };
        if ($source === 'payments') {
            $rows = $rows->map(function ($row) use ($field) {
                $row->amount = (float) $row->{$field};

                return $row;
            });
        }
        $rows = $rows->filter(fn ($row) => abs((float) $row->amount) >= 0.005)->values();
        $rawTotal = round((float) $rows->sum('amount'), 2);
        $nettingAdjustment = 0.0;
        if (in_array($metric, ['ready-payout', 'seller-in-process'], true)) {
            $available = $rows->where('ledger_status', 'available');
            $nettingAdjustment = round((float) $available->groupBy('shop_id')
                ->sum(fn ($shopRows) => max(0, -(float) $shopRows->sum('amount'))), 2);
        }
        $total = round($rawTotal + $nettingAdjustment, 2);
        $pageSize = in_array((int) ($filters['per_page'] ?? 10), [10, 25, 50], true) ? (int) $filters['per_page'] : 10;
        $page = max(1, (int) ($filters['page'] ?? 1));
        $sort = ($filters['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc';
        $rows = $sort === 'asc' ? $rows->sortBy('date')->values() : $rows->sortByDesc('date')->values();
        $paginated = new LengthAwarePaginator($rows->forPage($page, $pageSize)->values(), $rows->count(), $pageSize, $page, [
            'path' => url()->current(), 'query' => request()->query(),
        ]);
        $sources = $this->sourcesForOrders($paginated->getCollection()->pluck('order_id')->filter()->unique()->all());
        $cakeDetails = $this->cakeDetailsForOrders($paginated->getCollection()->pluck('order_id')->filter()->unique()->all());

        return compact('group', 'title', 'field', 'source', 'paginated', 'total', 'rawTotal', 'nettingAdjustment', 'sources', 'cakeDetails');
    }

    private function paymentRows(array $filters): Collection
    {
        if (! Schema::hasTable('payment_transactions')) {
            return collect();
        }
        $query = DB::table('payment_transactions as pt')
            ->leftJoin('orders as o', 'o.id', '=', 'pt.order_id')
            ->leftJoin('shops as s', 's.id', '=', 'o.shop_id')
            ->where('pt.payment_status', 'paid')->where('pt.method', 'GCash')
            ->whereBetween(DB::raw('DATE(COALESCE(pt.paid_at, pt.created_at))'), [$filters['from'], $filters['to']]);
        $this->applyCommonFilters($query, $filters, 'pt.order_id', 's.id', 's.shop_name', 'o.status', 'pt.provider_reference');

        return $query->select('pt.*', 'o.status as order_status', 's.id as shop_id', 's.shop_name')->get()
            ->map(fn ($payment) => (object) [
                'id' => 'payment-'.$payment->id, 'order_id' => $payment->order_id, 'shop_id' => $payment->shop_id,
                'shop_name' => $payment->shop_name, 'order_status' => $payment->order_status,
                'date' => $payment->paid_at ?: $payment->created_at, 'reference' => $payment->provider_reference,
                'kind' => 'GCash '.($payment->type ?: 'payment'), 'ledger_status' => null,
                'amount' => (float) $payment->amount, 'online_settled' => (float) $payment->amount,
                'service_fee' => (float) ($payment->payment_service_fee ?? 0),
                'customer_charged' => (float) ($payment->customer_paid_amount ?? ((float) $payment->amount + (float) ($payment->payment_service_fee ?? 0))),
            ]);
    }

    private function refundRows(array $filters, FinanceReconciliationService $reconciliation): Collection
    {
        $orderIds = $reconciliation->build($filters['from'], $filters['to'])['rows']->pluck('order_id')->all();
        if (! $orderIds) {
            return collect();
        }
        $rows = collect();
        foreach (['order_refunds' => ['refund_amount', 'reference_number', 'admin_refund_completed_at', 'Cancellation refund'],
            'post_delivery_return_cases' => ['COALESCE(approved_amount, requested_amount, 0)', 'refund_reference', 'refunded_at', 'Return refund']] as $table => [$amount, $reference, $date, $kind]) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $query = DB::table($table.' as r')->leftJoin('orders as o', 'o.id', '=', 'r.order_id')
                ->leftJoin('shops as s', 's.id', '=', 'o.shop_id')
                ->where('r.status', 'refunded')->whereIn('r.order_id', $orderIds);
            $this->applyCommonFilters($query, $filters, 'r.order_id', 's.id', 's.shop_name', 'o.status', 'r.'.$reference);
            $rows = $rows->concat($query->select('r.*', 'o.status as order_status', 's.id as report_shop_id', 's.shop_name')->get()
                ->map(fn ($refund) => (object) [
                    'id' => $table.'-'.$refund->id, 'order_id' => $refund->order_id, 'shop_id' => $refund->report_shop_id,
                    'shop_name' => $refund->shop_name, 'order_status' => $refund->order_status,
                    'date' => ($refund->{$date} ?? null) ?: $refund->updated_at, 'reference' => $refund->{$reference} ?? null,
                    'kind' => $kind, 'ledger_status' => null,
                    'amount' => (float) ($table === 'order_refunds' ? $refund->refund_amount : ($refund->approved_amount ?? $refund->requested_amount ?? 0)),
                ]));
        }

        return $rows;
    }

    private function sellerRows(string $metric, array $filters): Collection
    {
        $hasSourceOrder = Schema::hasColumn('seller_payout_ledgers', 'source_order_id');
        $statuses = match ($metric) {
            'seller-in-process' => ['awaiting_completion', 'available'],
            'awaiting-completion' => ['awaiting_completion'],
            'ready-payout' => ['available'],
            'refund-reserved' => ['refund_reserved'],
            'transfer-pending' => ['requested', 'processing'],
            default => ['paid'],
        };
        $query = DB::table('seller_payout_ledgers as l')
            ->leftJoin('orders as o', function ($join) use ($hasSourceOrder) {
                $join->on('o.id', '=', 'l.order_id');
                if ($hasSourceOrder) {
                    $join->orOn('o.id', '=', 'l.source_order_id');
                }
            })
            ->leftJoin('shops as s', 's.id', '=', 'l.shop_id')
            ->leftJoin('seller_payouts as p', 'p.id', '=', 'l.payout_id')
            ->whereIn('l.status', $statuses)
            ->whereBetween(DB::raw('DATE(l.created_at)'), [$filters['from'], $filters['to']]);
        $this->applyCommonFilters($query, $filters, 'l.order_id', 's.id', 's.shop_name', 'o.status', 'p.reference_number', $hasSourceOrder ? 'l.source_order_id' : null);

        return $query->select('l.*', 'o.status as order_status', 's.shop_name', 'p.reference_number as payout_reference')->get()
            ->map(fn ($ledger) => (object) [
                'id' => 'ledger-'.$ledger->id, 'order_id' => $ledger->source_order_id ?? $ledger->order_id,
                'shop_id' => $ledger->shop_id, 'shop_name' => $ledger->shop_name,
                'order_status' => $ledger->order_status, 'date' => $ledger->created_at,
                'reference' => $ledger->payout_reference ?: ($ledger->payout_id ? '#'.$ledger->payout_id : null),
                'kind' => ucfirst(str_replace('_', ' ', $ledger->status)), 'ledger_status' => $ledger->status,
                'amount' => (float) $ledger->seller_net_amount, 'cake_amount' => (float) ($ledger->cake_amount ?? $ledger->commission_base ?? 0),
                'commission' => (float) $ledger->commission_amount, 'online_collected' => (float) ($ledger->online_collected_amount ?? 0),
                'rider_allocation' => (float) ($ledger->rider_online_fee_amount ?? 0),
            ]);
    }

    private function reconciliationRows(string $metric, array $filters, FinanceReconciliationService $reconciliation): Collection
    {
        $rows = $reconciliation->build($filters['from'], $filters['to'])['rows'];
        $orders = DB::table('orders as o')->leftJoin('shops as s', 's.id', '=', 'o.shop_id')
            ->whereIn('o.id', $rows->pluck('order_id'))->select('o.id', 'o.shop_id', 's.shop_name')->get()->keyBy('id');
        $payments = DB::table('payment_transactions')->whereIn('order_id', $rows->pluck('order_id'))
            ->where('payment_status', 'paid')->where('method', 'GCash')
            ->select('order_id')->selectRaw('MAX(COALESCE(paid_at, created_at)) AS last_paid_at')
            ->selectRaw('MAX(provider_reference) AS reference')->groupBy('order_id')->get()->keyBy('order_id');
        $referenceMatches = collect();
        if ($filters['search'] !== '') {
            $referenceMatches = DB::table('payment_transactions')->where('provider_reference', 'like', '%'.$filters['search'].'%')
                ->whereIn('order_id', $rows->pluck('order_id'))->pluck('order_id')->flip();
        }

        return $rows->filter(fn ($row) => $metric !== 'verified-commission' || $row->state === 'reconciled')
            ->map(function ($row) use ($metric, $orders, $payments) {
                $order = $orders->get($row->order_id);
                $payment = $payments->get($row->order_id);

                return (object) [
                    'id' => 'order-'.$row->order_id, 'order_id' => $row->order_id,
                    'shop_id' => $order->shop_id ?? null, 'shop_name' => $order->shop_name ?? null,
                    'order_status' => $row->order_status, 'date' => $payment->last_paid_at ?? null,
                    'reference' => $payment->reference ?? null, 'kind' => ucfirst(str_replace('_', ' ', $row->state)),
                    'ledger_status' => null, 'amount' => (float) $row->{self::METRICS[$metric][2]},
                    'online_settled' => $row->online_settled, 'refunds' => $row->refunds,
                    'seller_amount' => $row->seller_amount, 'seller_payable' => $row->seller_payable,
                    'seller_paid' => $row->seller_paid, 'rider_amount' => $row->rider_amount,
                    'rider_payable' => $row->rider_payable, 'rider_paid' => $row->rider_paid,
                    'commission' => $row->commission, 'platform_funding' => $row->platform_funding,
                    'held' => $row->held, 'difference' => $row->difference,
                ];
            })->filter(fn ($row) => ($filters['shop'] === '' || (string) $row->shop_id === $filters['shop'])
                && ($filters['status'] === '' || $row->order_status === $filters['status'])
                && ($filters['search'] === '' || str_contains(mb_strtolower((string) $row->order_id), mb_strtolower($filters['search']))
                    || str_contains(mb_strtolower((string) $row->shop_name), mb_strtolower($filters['search']))
                    || str_contains(mb_strtolower((string) $row->reference), mb_strtolower($filters['search']))
                    || $referenceMatches->has($row->order_id)));
    }

    private function applyCommonFilters($query, array $filters, string $order, string $shop, string $shopName, string $status, string $reference, ?string $alternateOrder = null): void
    {
        if ($filters['shop'] !== '') {
            $query->where($shop, $filters['shop']);
        }
        if ($filters['status'] !== '') {
            $query->where($status, $filters['status']);
        }
        if ($filters['search'] !== '') {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($order, $alternateOrder, $shopName, $reference, $search) {
                $q->where($order, 'like', $search)->orWhere($shopName, 'like', $search)->orWhere($reference, 'like', $search);
                if ($alternateOrder) {
                    $q->orWhere($alternateOrder, 'like', $search);
                }
            });
        }
    }

    private function sourcesForOrders(array $orderIds): Collection
    {
        if (! $orderIds) {
            return collect();
        }
        $sources = collect($orderIds)->mapWithKeys(fn ($id) => [(string) $id => []]);
        $tables = [
            'payment_transactions' => ['GCash payment', 'amount', 'provider_reference'],
            'seller_payout_ledgers' => ['Seller ledger', 'seller_net_amount', 'payout_id'],
            'rider_delivery_earnings' => ['Rider earning', 'amount', 'payout_id'],
            'order_refunds' => ['Cancellation refund', 'refund_amount', 'reference_number'],
            'post_delivery_return_cases' => ['Return refund', 'approved_amount', 'refund_reference'],
        ];
        foreach ($tables as $table => [$label, $amount, $reference]) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $query = DB::table($table);
            if ($table === 'seller_payout_ledgers' && Schema::hasColumn($table, 'source_order_id')) {
                $query->where(fn ($q) => $q->whereIn('order_id', $orderIds)->orWhereIn('source_order_id', $orderIds));
            } else {
                $query->whereIn('order_id', $orderIds);
            }
            if ($table === 'payment_transactions') {
                $query->where('payment_status', 'paid')->where('method', 'GCash');
            }
            if (in_array($table, ['order_refunds', 'post_delivery_return_cases'], true)) {
                $query->where('status', 'refunded');
            }
            foreach ($query->get() as $record) {
                $id = (string) (($table === 'seller_payout_ledgers' ? ($record->source_order_id ?? null) : null) ?: $record->order_id);
                $entries = $sources->get($id, []);
                $entries[] = [
                    'id' => $record->id,
                    'type' => $table,
                    'label' => $label,
                    'amount' => (float) ($record->{$amount} ?? ($record->requested_amount ?? 0)),
                    'service_fee' => $table === 'payment_transactions' ? (float) ($record->payment_service_fee ?? 0) : null,
                    'customer_charged' => $table === 'payment_transactions' ? (float) ($record->customer_paid_amount ?? ((float) $record->amount + (float) ($record->payment_service_fee ?? 0))) : null,
                    'commission_amount' => $table === 'seller_payout_ledgers' ? (float) ($record->commission_amount ?? 0) : null,
                    'rider_allocation_amount' => $table === 'seller_payout_ledgers' ? (float) ($record->rider_online_fee_amount ?? 0) : null,
                    'status' => $record->status ?? null,
                    'reference' => $record->{$reference} ?? null,
                ];
                $sources->put($id, $entries);
            }
        }

        return $sources;
    }

    /** Load cake snapshots only for orders on the visible page. */
    private function cakeDetailsForOrders(array $orderIds): Collection
    {
        if (! $orderIds) {
            return collect();
        }

        $orders = DB::table('orders as o')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->whereIn('o.id', $orderIds)
            ->select('o.id', 'o.quantity', 'o.selected_size', 'o.product_id', 'p.name as product_name')
            ->get()->keyBy('id');
        $items = Schema::hasTable('order_items')
            ? DB::table('order_items')->whereIn('order_id', $orderIds)->orderBy('id')
                ->get(['order_id', 'product_name', 'quantity', 'selected_size', 'final_unit_price_snapshot', 'custom_note'])->groupBy('order_id')
            : collect();
        $addons = Schema::hasTable('order_addons')
            ? DB::table('order_addons')->whereIn('order_id', $orderIds)->orderBy('id')
                ->get(['order_id', 'addon_name', 'addon_price'])->groupBy('order_id')
            : collect();
        $customOrders = Schema::hasTable('custom_orders')
            ? DB::table('custom_orders')->whereIn('order_id', $orderIds)
                ->get(['order_id', 'cake_name', 'flavor', 'size', 'layers', 'design_complexity', 'dedication', 'custom_note'])->keyBy('order_id')
            : collect();

        return collect($orderIds)->mapWithKeys(fn ($id) => [(string) $id => [
            'order' => $orders->get($id),
            'items' => $items->get($id, collect()),
            'addons' => $addons->get($id, collect()),
            'custom' => $customOrders->get($id),
        ]]);
    }
}
