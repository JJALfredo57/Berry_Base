@extends('layouts.app')
@section('page_title', $report['title'].' Breakdown')

@section('content')
@php
  $dateBasis = $report['source'] === 'seller' ? 'Seller ledger creation date; amounts show each ledger’s current status.' : 'GCash payment date; linked allocations and completed refunds are shown for those orders even if recorded later.';
  $overviewQuery = $filters['period'] !== '' ? ['period' => $filters['period']] : ['from' => $filters['from'], 'to' => $filters['to']];
@endphp
<style>
  .fb-shell{max-width:1160px;margin:0 auto;min-width:0}.fb-head{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap;margin-bottom:1rem}.fb-back{display:inline-flex;align-items:center;gap:.35rem;text-decoration:none;font-weight:700;margin-bottom:.4rem}.fb-title{margin:0;font-size:clamp(1.25rem,2vw,1.7rem);font-weight:800}.fb-sub{color:#64748b;margin:.25rem 0 0}.fb-filter,.fb-summary,.fb-row,.fb-footer{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:1rem;margin-bottom:1rem;min-width:0}.fb-filter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,170px),1fr));gap:.7rem;align-items:end}.fb-filter label{display:grid;gap:.25rem;font-size:.78rem;font-weight:700;color:#475569;min-width:0}.fb-filter .form-control,.fb-filter .form-select{min-height:42px;min-width:0}.fb-actions{display:flex;gap:.5rem;flex-wrap:wrap}.fb-actions>*{min-height:42px}.fb-periods{display:flex;gap:.45rem;overflow:auto;padding:.75rem 0 .15rem}.fb-periods .btn{white-space:nowrap;flex:0 0 auto}.fb-summary{display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap;background:#f8fafc}.fb-summary strong{font-size:1.35rem;font-variant-numeric:tabular-nums}.fb-note{font-size:.82rem;color:#64748b}.fb-list{list-style:none;margin:0;padding:0}.fb-row-head{display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap}.fb-row-head strong{font-size:1rem}.fb-row-amount{font-size:1.12rem;font-weight:800;font-variant-numeric:tabular-nums}.fb-meta{display:flex;gap:.45rem 1rem;flex-wrap:wrap;color:#64748b;font-size:.82rem;margin:.35rem 0}.fb-formula{margin:.7rem 0 0;padding:.7rem .8rem;background:#f8fafc;border-radius:10px;font-size:.85rem;overflow-wrap:anywhere}.fb-row details{margin-top:.6rem}.fb-row summary{cursor:pointer;font-weight:700;color:var(--primary);min-height:40px;display:flex;align-items:center}.fb-source{display:flex;justify-content:space-between;gap:.75rem;border-top:1px solid #e2e8f0;padding:.45rem 0;font-size:.83rem;overflow-wrap:anywhere}.fb-source>*{min-width:0}.fb-source strong{white-space:nowrap}.fb-footer{border-color:#cbd5e1;position:relative}.fb-footer-line{display:flex;justify-content:space-between;gap:1rem;align-items:center;font-variant-numeric:tabular-nums}.fb-footer-line strong{font-size:1.2rem}.fb-footer-line+.fb-footer-line{border-top:1px solid #e2e8f0;margin-top:.6rem;padding-top:.6rem}.fb-links{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.7rem}@media(max-width:425px){.fb-row,.fb-filter,.fb-summary,.fb-footer{padding:.85rem}.fb-row-head{display:block}.fb-row-amount{display:block;margin-top:.35rem}.fb-source{display:block}.fb-source strong{display:block;margin-top:.2rem}.fb-actions>*{flex:1 1 auto}}
</style>
<div class="fb-shell">
  <header class="fb-head"><div><a class="fb-back" href="{{ route('superadmin.payouts', $overviewQuery) }}"><i class="bi bi-arrow-left"></i> Finance &amp; Payouts</a><div class="small text-muted">{{ $report['group'] }} / Source breakdown</div><h1 class="fb-title">{{ $report['title'] }}</h1><p class="fb-sub">{{ $dateBasis }}</p></div><span class="badge text-bg-light">Read-only report</span></header>

  <section class="fb-filter" aria-label="Breakdown filters">
    <form method="GET" action="{{ route('superadmin.payouts.breakdown', $metric) }}">
      <input type="hidden" name="period" value="">
      <div class="fb-filter-grid">
        <label>From<input type="date" class="form-control" name="from" value="{{ $filters['from'] }}"></label>
        <label>To<input type="date" class="form-control" name="to" value="{{ $filters['to'] }}"></label>
        <label>Order / shop / reference<input type="search" class="form-control" name="search" maxlength="120" value="{{ $filters['search'] }}" placeholder="Search source records"></label>
        <label>Shop<select class="form-select" name="shop"><option value="">All shops</option>@foreach($shops as $shop)<option value="{{ $shop->id }}" @selected((string)$filters['shop']===(string)$shop->id)>{{ $shop->shop_name }}</option>@endforeach</select></label>
        <label>Order status<select class="form-select" name="status"><option value="">All statuses</option>@foreach($allowedStatuses as $status)<option value="{{ $status }}" @selected($filters['status']===$status)>{{ $status }}</option>@endforeach</select></label>
        <label>Sort<select class="form-select" name="sort"><option value="newest" @selected($filters['sort']==='newest')>Newest first</option><option value="oldest" @selected($filters['sort']==='oldest')>Oldest first</option></select></label>
        <label>Rows<select class="form-select" name="per_page">@foreach([10,25,50] as $size)<option value="{{ $size }}" @selected($filters['per_page']===$size)>{{ $size }} per page</option>@endforeach</select></label>
        <div class="fb-actions"><button class="btn btn-primary" type="submit">Apply filters</button><a class="btn btn-outline-secondary" href="{{ route('superadmin.payouts.breakdown', array_merge(['metric'=>$metric], $overviewQuery)) }}">Clear</a></div>
      </div>
    </form>
    <nav class="fb-periods" aria-label="Quick reporting periods">@foreach(['all'=>'All Time','today'=>'Today','week'=>'This Week','month'=>'This Month','last_month'=>'Last Month','year'=>'This Year','last30'=>'Last 30 days'] as $key=>$label)<a class="btn btn-sm {{ $filters['period']===$key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('superadmin.payouts.breakdown', array_merge(['metric'=>$metric], request()->except(['from','to','page']), ['period'=>$key])) }}">{{ $label }}</a>@endforeach</nav>
  </section>

  <div class="fb-summary"><div><div class="small text-muted">Total across all matching records</div><strong>₱{{ number_format($report['total'],2) }}</strong></div><div class="fb-note">{{ number_format($report['paginated']->total()) }} source record{{ $report['paginated']->total() === 1 ? '' : 's' }} · {{ $filters['from'] }} to {{ $filters['to'] }}</div></div>
  @if($metric === 'verified-commission')<p class="alert alert-info small">Only fully reconciled, completed orders contribute to this verified total. “Recorded commission” includes other order states and may be larger.</p>@endif
  @if($report['source'] === 'seller')<p class="fb-note">Ready payout is netted per shop. Other seller lines, such as reserved, pending transfer and already paid, are separate states—not additions to the “in process” headline.</p>@endif
  @if($metric === 'refunds-sent')<p class="fb-note">Refunds are deductions from customer funds. The list shows their positive transfer amounts; the overview displays them with a minus sign.</p>@endif

  <ol class="fb-list">
    @forelse($report['paginated'] as $row)
      <li class="fb-row"><div class="fb-row-head"><div><strong>Order #{{ $row->order_id ?: 'Unavailable' }}</strong><div class="fb-meta"><span>{{ $row->shop_name ?: 'Shop unavailable' }}</span><span>{{ $row->kind }}</span><span>{{ $row->order_status ?: 'Order unavailable' }}</span></div></div><span class="fb-row-amount">{{ $row->amount < 0 ? '−' : '' }}₱{{ number_format(abs($row->amount),2) }}</span></div>
        <div class="fb-meta"><span>{{ $row->date ? \Carbon\Carbon::parse($row->date)->format('M d, Y h:i A') : 'Date unavailable' }}</span>@if($row->reference)<span>Reference: {{ $row->reference }}</span>@endif</div>
        @if($report['source'] === 'payments')
          <div class="fb-formula">Payment ₱{{ number_format((float) $row->online_settled,2) }} + customer-paid fee ₱{{ number_format((float) $row->service_fee,2) }} = checkout total ₱{{ number_format((float) $row->customer_charged,2) }}. This row contributes <strong>₱{{ number_format($row->amount,2) }}</strong> to {{ $report['title'] }}.</div>
        @elseif($report['source'] === 'seller')
          <div class="fb-formula">Recorded seller net <strong>{{ $row->amount < 0 ? '−' : '' }}₱{{ number_format(abs($row->amount),2) }}</strong> · Online collected ₱{{ number_format($row->online_collected,2) }} · Cake value ₱{{ number_format($row->cake_amount,2) }} · Commission ₱{{ number_format($row->commission,2) }} · Online rider allocation ₱{{ number_format($row->rider_allocation,2) }}. Refund/recovery adjustments are preserved in the ledger amount.</div>
        @elseif($report['source'] === 'reconciliation')
          <div class="fb-formula">Online ₱{{ number_format($row->online_settled,2) }} + platform funding ₱{{ number_format($row->platform_funding,2) }} = held ₱{{ number_format($row->held,2) }} + refunds ₱{{ number_format($row->refunds,2) }} + seller allocation ₱{{ number_format($row->seller_amount,2) }} + rider allocation ₱{{ number_format($row->rider_amount,2) }} + commission ₱{{ number_format($row->commission,2) }} + difference {{ $row->difference < 0 ? '−' : '' }}₱{{ number_format(abs($row->difference),2) }}. Seller awaiting ₱{{ number_format($row->seller_payable,2) }}, seller paid ₱{{ number_format($row->seller_paid,2) }}; rider awaiting ₱{{ number_format($row->rider_payable,2) }}, rider paid ₱{{ number_format($row->rider_paid,2) }}.</div>
        @else
          <div class="fb-formula">Completed {{ strtolower($row->kind) }} · transfer amount <strong>₱{{ number_format($row->amount,2) }}</strong>@if($row->reference) · reference {{ $row->reference }}@endif.</div>
        @endif
        @if($row->order_id && $report['sources']->has((string) $row->order_id))
          <details><summary>View linked source records</summary><div class="fb-note">These are records linked to this order, including entries outside the selected reporting period.</div>
            @foreach($report['sources']->get((string) $row->order_id) as $source)
              <div class="fb-source"><span>{{ $source['label'] }}@if($source['reference']) · {{ $source['reference'] }}@endif</span><strong>{{ $source['amount'] < 0 ? '−' : '' }}₱{{ number_format(abs($source['amount']),2) }}</strong></div>
            @endforeach
          </details>
        @endif
        <div class="fb-links">@if($report['source'] === 'seller')<a href="{{ route('superadmin.payouts.sellers', ['search'=>$row->order_id]) }}" class="btn btn-sm btn-outline-primary">Seller payout records</a>@endif @if($report['source'] === 'refunds')<a href="{{ route('superadmin.return_cases') }}" class="btn btn-sm btn-outline-primary">Return &amp; Refund Cases</a>@endif</div>
      </li>
    @empty
      <li class="fb-row text-center text-muted py-4">No source records match these filters. Try All Time or clear the search and shop filters.</li>
    @endforelse
  </ol>
  @if($report['paginated']->hasPages())<div class="mb-3">{{ $report['paginated']->links() }}</div>@endif
  <footer class="fb-footer" aria-label="Breakdown totals">
    <div class="fb-footer-line"><span>Visible page subtotal (signed rows)</span><strong>₱{{ number_format($report['paginated']->getCollection()->sum('amount'),2) }}</strong></div>
    @if($report['nettingAdjustment'] > 0)<div class="fb-footer-line"><span>Per-shop zero-floor adjustment (negative available balance is not payout-ready)</span><strong>+₱{{ number_format($report['nettingAdjustment'],2) }}</strong></div>@endif
    <div class="fb-footer-line"><span>Total for all matching records</span><strong>₱{{ number_format($report['total'],2) }}</strong></div>
    <div class="fb-note mt-2">{{ $report['paginated']->total() }} matching records across all pages. Changing filters recalculates this total; the overview uses the same date range before additional breakdown filters.</div>
  </footer>
</div>
@endsection
