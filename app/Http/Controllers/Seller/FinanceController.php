<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
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
        if ($from > $to) [$from, $to] = [$to, $from];

        $ledgerRows = DB::table('seller_payout_ledgers')
            ->where('shop_id', $shop->id)->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->orderByDesc('created_at')->get();
        $expenses = Schema::hasTable('seller_expenses')
            ? DB::table('seller_expenses')->where('shop_id', $shop->id)->whereBetween('expense_date', [$from, $to])->orderByDesc('expense_date')->orderByDesc('id')->get()
            : collect();
        $payoutRows = DB::table('seller_payouts')->where('shop_id', $shop->id)->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])->orderByDesc('created_at')->get();

        $gross = (float) $ledgerRows->where('gross_amount', '>', 0)->sum('gross_amount');
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
        $transactions = collect()
            ->merge($ledgerRows->map(fn ($row) => (object) ['date' => $row->created_at, 'type' => $row->seller_net_amount < 0 ? 'Refund deduction' : 'Order earnings', 'reference' => 'Order #'.$row->order_id, 'gross' => (float) $row->gross_amount, 'commission' => (float) $row->commission_amount, 'net' => (float) $row->seller_net_amount, 'status' => $row->status, 'expense_id' => null]))
            ->merge($expenses->map(fn ($row) => (object) ['date' => $row->expense_date, 'type' => 'Business expense', 'reference' => $row->description, 'gross' => 0, 'commission' => 0, 'net' => -(float) $row->amount, 'status' => $row->category, 'expense_id' => $row->id]))
            ->merge($payoutRows->map(fn ($row) => (object) ['date' => $row->paid_at ?? $row->created_at, 'type' => 'Seller payout', 'reference' => 'Payout #'.$row->id, 'gross' => 0, 'commission' => 0, 'net' => -(float) $row->net_amount, 'status' => $row->status, 'expense_id' => null]))
            ->sortByDesc('date')->values();

        $summary = $this->payouts->summaryForShop((string) $shop->id);
        return view('seller.finance', compact('shop', 'from', 'to', 'gross', 'commission', 'refunds', 'netEarnings', 'expenseTotal', 'chartData', 'transactions', 'expenses', 'summary'));
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
        DB::table('seller_expenses')->insert($data + ['shop_id' => $shop->id, 'seller_id' => session('user')['id'], 'created_at' => now(), 'updated_at' => now()]);
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
        $data['amount'] = round($data['amount'] * $data['quantity'], 2); $data['updated_at']=now();
        DB::table('seller_expenses')->where('id',$expenseId)->where('shop_id',$shop->id)->update($data);
        return back()->with('msg','Expense updated.');
    }
}
