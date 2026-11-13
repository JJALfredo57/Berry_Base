@extends('layouts.app')
@section('page_title', 'Business Finance')

@section('content')
<style>
  .finance-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem}.finance-card,.finance-panel{background:var(--surface,#fff);border:1px solid var(--gray-100,#e5e7eb);border-radius:16px;padding:1.05rem;box-shadow:0 1px 2px rgba(15,23,42,.04)}.finance-label{font-size:.75rem;color:var(--gray-500,#64748b);font-weight:700;text-transform:uppercase;letter-spacing:.04em}.finance-value{font-size:1.35rem;font-weight:800;color:var(--gray-900,#111827);margin-top:.35rem}.finance-help{font-size:.78rem;color:var(--gray-500,#64748b);margin-top:.3rem}.finance-layout{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(280px,.8fr);gap:1rem;margin-top:1rem}.finance-chart{height:290px;position:relative}.finance-table{width:100%;min-width:760px}.finance-table th{font-size:.72rem;text-transform:uppercase;color:var(--gray-500,#64748b);letter-spacing:.03em}.finance-table td{font-size:.84rem;vertical-align:middle}.finance-positive{color:#15803d}.finance-negative{color:#b91c1c}.finance-filter{display:flex;gap:.7rem;align-items:end;flex-wrap:wrap}.finance-filter .form-group{min-width:145px}.finance-expense-form{display:grid;grid-template-columns:1fr 1fr;gap:.7rem}.finance-expense-form .wide{grid-column:1/-1}@media(max-width:1100px){.finance-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.finance-layout{grid-template-columns:1fr}}@media(max-width:560px){.finance-grid{grid-template-columns:1fr}.finance-expense-form{grid-template-columns:1fr}.finance-table{min-width:650px}.finance-value{font-size:1.2rem}}
</style>

<div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-3">
  <div><h4 class="cs-page-title mb-1"><i class="bi bi-graph-up-arrow me-2" style="color:var(--primary)"></i>Business Finance</h4><p class="cs-page-sub mb-0">Live view of your sales, platform deductions, payouts, expenses, and estimated profit.</p></div>
  <span class="badge text-bg-light border" id="finance-live"><i class="bi bi-broadcast me-1 text-success"></i>Live data</span>
</div>

<div class="finance-panel mb-3">
  <form method="GET" class="finance-filter">
    <div class="form-group"><label class="form-label small fw-semibold">From</label><input class="form-control" type="date" name="from" value="{{ $from }}"></div>
    <div class="form-group"><label class="form-label small fw-semibold">To</label><input class="form-control" type="date" name="to" value="{{ $to }}"></div>
    <a class="btn btn-outline-secondary" href="{{ route('seller.finance') }}">Last 30 days</a>
  </form>
</div>

<div class="finance-grid">
  <article class="finance-card"><div class="finance-label">Collected sales</div><div class="finance-value">₱{{ number_format($gross,2) }}</div><div class="finance-help">Money collected for delivered eligible orders</div></article>
  <article class="finance-card"><div class="finance-label">Platform commission</div><div class="finance-value finance-negative">−₱{{ number_format($commission,2) }}</div><div class="finance-help">Your shop's commission only</div></article>
  <article class="finance-card"><div class="finance-label">Net marketplace earnings</div><div class="finance-value finance-positive">₱{{ number_format($netEarnings,2) }}</div><div class="finance-help">After commission and refund deductions</div></article>
  <article class="finance-card"><div class="finance-label">Estimated profit</div><div class="finance-value {{ $netEarnings-$expenseTotal >= 0 ? 'finance-positive' : 'finance-negative' }}">₱{{ number_format($netEarnings-$expenseTotal,2) }}</div><div class="finance-help">Net earnings − your recorded expenses</div></article>
</div>

<div class="finance-layout">
  <section class="finance-panel"><div class="d-flex justify-content-between align-items-center mb-2"><div><h6 class="mb-0 fw-bold">Earnings trend</h6><small class="text-muted">Gross sales, net marketplace earnings, and expenses</small></div><span class="small text-muted">Auto-refreshes every 60 seconds</span></div><div class="finance-chart"><canvas id="financeChart"></canvas></div></section>
  <section class="finance-panel"><h6 class="fw-bold mb-1">Business expenses</h6><p class="small text-muted">Record expenses by date, review spending, and keep your profit estimate accurate.</p><a class="btn btn-primary w-100" href="{{ route('seller.finance.expenses') }}"><i class="bi bi-calendar3 me-1"></i>Record Business Expense</a><hr><div class="d-flex justify-content-between"><span class="small text-muted">Expenses in selected period</span><strong class="finance-negative">−₱{{ number_format($expenseTotal,2) }}</strong></div></section>
</div>

<section class="finance-panel mt-3"><div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-2"><div><h6 class="fw-bold mb-0">Transaction history</h6><small class="text-muted">All figures belong only to {{ $shop->shop_name }}.</small></div><span class="badge text-bg-light">Available to request: ₱{{ number_format($summary['available'] ?? 0,2) }}</span></div><div class="table-responsive"><table class="table finance-table align-middle mb-0"><thead><tr><th>Date</th><th>Transaction</th><th>Reference</th><th>Status</th><th class="text-end">Gross</th><th class="text-end">Commission</th><th class="text-end">Net effect</th></tr></thead><tbody>@forelse($transactions as $transaction)<tr><td>{{ \Carbon\Carbon::parse($transaction->date)->format('M j, Y') }}</td><td class="fw-semibold">{{ $transaction->type }}</td><td>{{ $transaction->reference }} @if($transaction->expense_id)<form class="d-inline" method="POST" action="{{ route('seller.finance.expenses.delete',$transaction->expense_id) }}" data-prevent-double-submit>@csrf<button class="btn btn-link btn-sm text-danger p-0 ms-1" type="submit">Remove</button></form>@endif</td><td><span class="badge text-bg-light">{{ ucfirst(str_replace('_',' ',$transaction->status)) }}</span></td><td class="text-end">{{ $transaction->gross ? '₱'.number_format($transaction->gross,2) : '—' }}</td><td class="text-end">{{ $transaction->commission ? '−₱'.number_format($transaction->commission,2) : '—' }}</td><td class="text-end fw-bold {{ $transaction->net >= 0 ? 'finance-positive' : 'finance-negative' }}">{{ $transaction->net >= 0 ? '+' : '−' }}₱{{ number_format(abs($transaction->net),2) }}</td></tr>@empty<tr><td colspan="7" class="text-center text-muted py-4">No finance activity for this period yet.</td></tr>@endforelse</tbody></table></div></section>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () { const form=document.querySelector('.finance-filter'); const dates=form?.querySelectorAll('input[type="date"]'); dates?.forEach(input=>input.addEventListener('change',()=>{ if(dates[0].value&&dates[1].value) form.requestSubmit(); })); const ctx=document.getElementById('financeChart'); if(!ctx||!window.Chart)return; new Chart(ctx,{type:'line',data:{labels:@json($chartData['labels']),datasets:[{label:'Gross sales',data:@json($chartData['gross']),borderColor:'#2563eb',backgroundColor:'rgba(37,99,235,.08)',fill:true,tension:.35},{label:'Net earnings',data:@json($chartData['net']),borderColor:'#16a34a',tension:.35},{label:'Expenses',data:@json($chartData['expenses']),borderColor:'#dc2626',tension:.35}]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'bottom'}},scales:{y:{ticks:{callback:v=>'₱'+Number(v).toLocaleString()}}}}}); setTimeout(()=>location.reload(),60000); });
</script>
@endpush
@endsection
