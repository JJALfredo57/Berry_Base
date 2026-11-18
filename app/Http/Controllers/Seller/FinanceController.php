<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Helpers\PaymentTransactionHelper;
use App\Services\SellerPayoutService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinanceController extends Controller
{
    public function __construct(private SellerPayoutService $payouts) {}

    private function shop(): ?object
    {
        return DB::table('shops')->where('seller_id', session('user')['id'] ?? null)->first();
    }

    public function index(Request $request)
    {
        $shop = $this->shop();
        if (!$shop) return redirect()->route('seller.apply')->with('err', 'Your shop is not found.');

        $this->payouts->syncDeliveredPaidOrders();
        try { $from = Carbon::parse((string) $request->input('from', now()->subDays(29)->toDateString()))->toDateString(); }
        catch (\Throwable $e) { $from = now()->subDays(29)->toDateString(); }
        try { $to = Carbon::parse((string) $request->input('to', now()->toDateString()))->toDateString(); }
        catch (\Throwable $e) { $to = now()->toDateString(); }
        $period = (string) $request->input('period', '');
        if ($period === 'today') [$from, $to] = [now()->toDateString(), now()->toDateString()];
        elseif ($period === 'week') [$from, $to] = [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()];
        elseif ($period === 'month') [$from, $to] = [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];
        elseif ($period === 'last_month') [$from, $to] = [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()];
        elseif ($period === 'year') [$from, $to] = [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()];
        if ($from > $to) [$from, $to] = [$to, $from];
        $fulfillment = strtolower((string) $request->input('fulfillment', ''));
        if (!in_array($fulfillment, ['delivery', 'pickup'], true)) $fulfillment = '';

        $ledgerRows = DB::table('seller_payout_ledgers as ledger')
            ->leftJoin('orders as o', 'o.id', '=', 'ledger.order_id')
            ->where('ledger.shop_id', $shop->id)
            ->whereBetween(DB::raw('DATE(ledger.created_at)'), [$from, $to])
            ->select('ledger.*', 'o.fulfillment_type')
            ->orderByDesc('ledger.created_at')->get();
        $expenses = Schema::hasTable('seller_expenses')
            ? DB::table('seller_expenses')->where('shop_id', $shop->id)->whereBetween('expense_date', [$from, $to])->orderByDesc('expense_date')->orderByDesc('id')->get()
            : collect();
        $payoutRows = DB::table('seller_payouts')->where('shop_id', $shop->id)->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])->orderByDesc('created_at')->get();
        // Payment receipts are real-time platform-held funds. They must be visible
        // before delivery/pickup, but never treated as seller earnings until the
        // completion ledger exists.
        $paymentRows = Schema::hasTable('payment_transactions')
            ? DB::table('payment_transactions as pt')
                ->join('orders as o', 'o.id', '=', 'pt.order_id')
                ->where('o.shop_id', $shop->id)
                ->whereRaw("LOWER(COALESCE(o.status, '')) <> ?", ['cancelled'])
                ->where('pt.payment_status', 'paid')
                ->whereBetween(DB::raw('DATE(COALESCE(pt.paid_at, pt.created_at))'), [$from, $to])
                ->select('pt.*', 'o.status as order_status', 'o.fulfillment_type', 'o.track_code')
                ->orderByDesc('pt.paid_at')->orderByDesc('pt.id')->get()
            : collect();

        $customerPaymentRows = $paymentRows->map(function ($payment) {
            $payment->label = PaymentTransactionHelper::typeLabel($payment->type ?? null);
            return $payment;
        });

        $deliveryAllocations = Schema::hasColumn('seller_payout_ledgers', 'delivery_fund_amount')
            ? DB::table('seller_payout_ledgers as ledger')
                ->join('orders as o', 'o.id', '=', 'ledger.order_id')
                ->where('ledger.shop_id', $shop->id)
                ->whereRaw("LOWER(COALESCE(o.status, '')) <> ?", ['cancelled'])
                ->whereBetween(DB::raw('DATE(ledger.created_at)'), [$from, $to])
                ->where('ledger.delivery_fund_amount', '>', 0)
                ->select('ledger.order_id', 'ledger.delivery_fund_amount', 'ledger.created_at', 'o.track_code', 'o.status as order_status')
                ->orderByDesc('ledger.created_at')->get()
            : collect();
        if ($deliveryAllocations->isNotEmpty() && Schema::hasTable('order_items')) {
            $itemsByOrder = DB::table('order_items')
                ->whereIn('order_id', $deliveryAllocations->pluck('order_id')->unique()->values())
                ->orderBy('id')->get(['order_id', 'product_name', 'quantity'])
                ->groupBy('order_id');
            $deliveryAllocations->each(function ($allocation) use ($itemsByOrder) {
                $allocation->products = $itemsByOrder->get($allocation->order_id, collect());
            });
        } else {
            $deliveryAllocations->each(fn ($allocation) => $allocation->products = collect());
        }

        // A completed order has one finance-history event: its settlement. Its
        // customer receipts remain attached to that settlement as proof instead
        // of becoming a second, visually duplicate primary row.
        $settledOrderIds = DB::table('seller_payout_ledgers')
            ->where('shop_id', $shop->id)
            ->whereNotNull('order_id')
            ->pluck('order_id')
            ->flip();
        $unsettledPaymentRows = $paymentRows
            ->filter(fn ($payment) => !$settledOrderIds->has((string) $payment->order_id))
            ->values();

        $gross = (float) $paymentRows->sum('amount');
        $deliveryFund = (float) $deliveryAllocations->sum('delivery_fund_amount');
        $commission = (float) $ledgerRows->sum('commission_amount');
        $refunds = abs((float) $ledgerRows->where('seller_net_amount', '<', 0)->sum('seller_net_amount'));
        $netEarnings = (float) $ledgerRows->sum('seller_net_amount');
        $expenseTotal = (float) $expenses->sum('amount');
        $chart = $ledgerRows->groupBy(fn ($row) => substr((string) $row->created_at, 0, 10))
            ->map(fn ($rows) => ['gross' => round((float) $rows->where('gross_amount', '>', 0)->sum('gross_amount'), 2), 'net' => round((float) $rows->sum('seller_net_amount'), 2)])
            ->sortKeys();
        $expenseByDay = $expenses->groupBy('expense_date')->map(fn ($rows) => round((float) $rows->sum('amount'), 2));
        $labels = collect(range(0, \Carbon\Carbon::parse($from)->diffInDays(\Carbon\Carbon::parse($to))))
            ->map(fn ($offset) => \Carbon\Carbon::parse($from)->addDays($offset)->toDateString());
        $chartData = [
            'labels' => $labels->map(fn ($date) => \Carbon\Carbon::parse($date)->format('M j'))->values(),
            'gross' => $labels->map(fn ($date) => $chart[$date]['gross'] ?? 0)->values(),
            'net' => $labels->map(fn ($date) => $chart[$date]['net'] ?? 0)->values(),
            'expenses' => $labels->map(fn ($date) => $expenseByDay[$date] ?? 0)->values(),
        ];
        // Payment receipts stay nested under their order earning.  Creating a second
        // ledger row for a 50% deposit or remaining payment would double-count the
        // seller's earnings and payout balance.
        $paymentEventsByOrder = collect();
        if (Schema::hasTable('payment_transactions') && $ledgerRows->isNotEmpty()) {
            $paymentEventsByOrder = DB::table('payment_transactions')
                ->whereIn('order_id', $ledgerRows->pluck('order_id')->filter()->unique()->values())
                ->where('payment_status', 'paid')
                ->orderBy('paid_at')
                ->orderBy('id')
                ->get()
                ->map(function ($payment) {
                    $payment->label = PaymentTransactionHelper::typeLabel($payment->type ?? null);
                    return $payment;
                })
                ->groupBy('order_id');
        }

        $hasCakeAmount = Schema::hasColumn('seller_payout_ledgers', 'cake_amount');
        $hasDeliveryFund = Schema::hasColumn('seller_payout_ledgers', 'delivery_fund_amount');
        $transactions = collect()
            ->merge($unsettledPaymentRows->map(function ($payment) {
                return (object) [
                    'date' => $payment->paid_at ?? $payment->created_at,
                    'type' => 'Customer payment received',
                    'reference' => 'Order #'.$payment->order_id.' · '.PaymentTransactionHelper::typeLabel($payment->type ?? null),
                    'gross' => (float) $payment->amount,
                    'commission' => 0,
                    'net' => 0,
                    'status' => 'held - awaiting completion',
                    'expense_id' => null,
                    'payment_events' => collect(),
                    'fulfillment_type' => $payment->fulfillment_type ?? null,
                    'rider_delivery_fee' => 0.0,
                ];
            }))
            ->merge($ledgerRows->map(function ($row) use ($paymentEventsByOrder, $hasCakeAmount, $hasDeliveryFund) {
                return (object) [
                    'date' => $row->created_at,
                    'type' => $row->seller_net_amount < 0 ? 'Refund deduction' : 'Order earnings',
                    'reference' => 'Order #'.$row->order_id,
                    'gross' => (float) $row->gross_amount,
                    'commission' => (float) $row->commission_amount,
                    'net' => (float) $row->seller_net_amount,
                    'status' => $row->status,
                    'expense_id' => null,
                    'order_id' => $row->order_id,
                    'payment_events' => $paymentEventsByOrder->get($row->order_id, collect()),
                    'cake_amount' => $hasCakeAmount ? (float) $row->cake_amount : (float) $row->commission_base,
                    'delivery_fund' => $hasDeliveryFund ? (float) $row->delivery_fund_amount : 0.0,
                    'commission_rate' => (float) $row->commission_rate,
                    'release_at' => $row->release_at,
                    'notes' => $row->notes,
                    'fulfillment_type' => $row->fulfillment_type ?? null,
                    'rider_delivery_fee' => $hasDeliveryFund ? (float) $row->delivery_fund_amount : 0.0,
                ];
            }))
            ->merge($expenses->map(fn ($row) => (object) ['date' => $row->expense_date, 'type' => 'Business expense', 'reference' => $row->description, 'gross' => 0, 'commission' => 0, 'net' => -(float) $row->amount, 'status' => $row->category, 'expense_id' => $row->id]))
            ->merge($payoutRows->map(fn ($row) => (object) ['date' => $row->paid_at ?? $row->created_at, 'type' => 'Seller payout', 'reference' => 'Payout #'.$row->id, 'gross' => 0, 'commission' => 0, 'net' => -(float) $row->net_amount, 'status' => $row->status, 'expense_id' => null]))
            ->sortByDesc('date')->values();
        if ($fulfillment) {
            $transactions = $transactions->filter(fn ($transaction) => strtolower((string) ($transaction->fulfillment_type ?? '')) === $fulfillment)->values();
        }

        $summary = $this->payouts->summaryForShop((string) $shop->id);
        return view('seller.finance', compact('shop', 'from', 'to', 'period', 'fulfillment', 'gross', 'deliveryFund', 'commission', 'refunds', 'netEarnings', 'expenseTotal', 'chartData', 'transactions', 'expenses', 'summary', 'customerPaymentRows', 'deliveryAllocations'));
    }

    public function storeExpense(Request $request)
    {
        $shop = $this->shop();
        if (!$shop) abort(404);
        if (!Schema::hasTable('seller_expenses')) return back()->with('err', 'Expense records are not ready yet. Please try again after the database migration finishes.');
        $data = $request->validate([
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', 'in:ingredients,labor,packaging,delivery,utilities,marketing,other'],
            'description' => ['required', 'string', 'min:2', 'max:300'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);
        $data['amount'] = round($data['amount'] * $data['quantity'], 2);
        if (!Schema::hasColumn('seller_expenses', 'quantity')) unset($data['quantity']);
        try {
            $recentDuplicate = DB::table('seller_expenses')
                ->where('shop_id', $shop->id)
                ->whereDate('expense_date', $data['expense_date'])
                ->where('category', $data['category'])
                ->where('description', $data['description'])
                ->where('amount', $data['amount'])
                ->where('created_at', '>=', now()->subSeconds(10))
                ->exists();
            if ($recentDuplicate) {
                return back()->withInput()->with('err', 'This identical expense was just saved. Check the list before adding it again.');
            }
            DB::table('seller_expenses')->insert($data + ['shop_id' => $shop->id, 'seller_id' => session('user')['id'], 'created_at' => now(), 'updated_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('err', 'Unable to save this expense right now. Please check the values and try again.');
        }
        return back()->with('msg', 'Business expense saved. Profit figures are updated.');
    }

    public function expenses(Request $request)
    {
        $shop = $this->shop();
        if (!$shop) return redirect()->route('seller.apply')->with('err', 'Your shop is not found.');
        if (!Schema::hasTable('seller_expenses')) {
            return redirect()->route('seller.finance')->with('err', 'Expense records are not ready yet. The database migration must finish before expenses can be added.');
        }
        $selected = Carbon::parse((string) $request->input('date', now()->toDateString()))->toDateString();
        $month = Carbon::parse((string) $request->input('month', $selected))->startOfMonth();
        $start = $month->copy()->startOfWeek(Carbon::SUNDAY);
        $end = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);
        $totals = DB::table('seller_expenses')->where('shop_id', $shop->id)->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])->selectRaw('expense_date, SUM(amount) total, COUNT(*) count')->groupBy('expense_date')->get()->keyBy('expense_date');
        $expenses = DB::table('seller_expenses')->where('shop_id', $shop->id)->where('expense_date', $selected)->orderByDesc('id')->get();
        return view('seller.expenses', compact('shop', 'selected', 'month', 'start', 'end', 'totals', 'expenses'));
    }

    public function destroyExpense(int $expenseId)
    {
        $shop = $this->shop();
        if (!$shop) abort(404);
        if (!Schema::hasTable('seller_expenses')) return back()->with('err', 'Expense records are not ready yet. Please try again after the database migration finishes.');
        DB::table('seller_expenses')->where('id', $expenseId)->where('shop_id', $shop->id)->delete();
        return back()->with('msg', 'Business expense removed.');
    }

    public function updateExpense(Request $request, int $expenseId)
    {
        $shop = $this->shop(); if (!$shop || !Schema::hasTable('seller_expenses')) abort(404);
        $data = $request->validate(['category'=>['required','in:ingredients,labor,packaging,delivery,utilities,marketing,other'],'description'=>['required','string','min:2','max:300'],'quantity'=>['required','integer','min:1','max:100000'],'amount'=>['required','numeric','gt:0','max:999999.99']]);
        $data['amount'] = round($data['amount'] * $data['quantity'], 2); if (!Schema::hasColumn('seller_expenses', 'quantity')) unset($data['quantity']); $data['updated_at']=now();
        try { DB::table('seller_expenses')->where('id',$expenseId)->where('shop_id',$shop->id)->update($data); }
        catch (\Throwable $e) { report($e); return back()->with('err','Unable to update this expense right now.'); }
        return back()->with('msg','Expense updated.');
    }
}
