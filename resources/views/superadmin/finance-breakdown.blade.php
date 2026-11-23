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
<style>
  .fb-row{padding:0;overflow:hidden}.fb-row-trigger{display:flex;align-items:center;justify-content:space-between;gap:1rem;width:100%;min-height:76px;padding:.85rem 1rem;border:0;background:#fff;color:inherit;text-align:left;cursor:pointer}.fb-row-trigger:hover,.fb-row-trigger:focus-visible{background:#fff8fa}.fb-row-trigger:focus-visible{outline:3px solid var(--primary);outline-offset:-3px}.fb-row-summary{display:grid;gap:.2rem;min-width:0}.fb-row-summary strong{font-size:.98rem}.fb-row-meta{color:#64748b;font-size:.78rem;overflow-wrap:anywhere}.fb-row-end{display:flex;align-items:center;gap:.65rem;flex:none}.fb-row-end .bi{color:#64748b}.fb-row-amount{white-space:nowrap}.fb-detail-modal .modal-dialog{max-width:min(760px,calc(100% - 1.25rem))}.fb-detail-modal .modal-body{max-height:calc(100dvh - 12rem);overflow-y:auto}.fb-detail-section+.fb-detail-section{border-top:1px solid #e2e8f0;margin-top:1rem;padding-top:1rem}.fb-detail-section h3{font-size:.96rem;font-weight:700;margin-bottom:.65rem}.fb-detail-amount{display:flex;justify-content:space-between;gap:1rem;padding:.75rem;background:#f8fafc;border-radius:10px;font-weight:700;font-variant-numeric:tabular-nums}.fb-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.45rem .8rem}.fb-detail-field{display:flex;justify-content:space-between;gap:.5rem;border-bottom:1px solid #f1f5f9;padding:.35rem 0;font-size:.85rem}.fb-detail-field span{color:#64748b}.fb-detail-field strong{font-variant-numeric:tabular-nums;text-align:right}.fb-cake-item{padding:.65rem .75rem;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:.5rem}.fb-cake-item strong{display:block}.fb-cake-item small{display:block;color:#64748b;margin-top:.2rem}.fb-detail-modal .fb-links{margin-top:1rem}@media(max-width:425px){.fb-row-trigger{padding:.75rem .85rem;min-height:70px}.fb-row-end{gap:.35rem}.fb-row-amount{font-size:1rem}.fb-detail-grid{grid-template-columns:1fr}.fb-detail-modal .modal-dialog{margin:.35rem auto;max-width:calc(100% - .7rem)}.fb-detail-modal .modal-body{max-height:calc(100dvh - 8rem)}}
</style>
<style>
  .fb-proof{border-top:1px solid #e2e8f0;margin-top:1rem;padding-top:1rem}.fb-proof h3{font-size:.96rem;font-weight:700;margin-bottom:.55rem}.fb-proof p{font-size:.86rem;color:#475569}.fb-proof .fb-source{padding:.55rem 0}.fb-disclosure{border-top:1px solid #e2e8f0;margin-top:1rem;padding-top:.65rem}.fb-disclosure>summary{display:flex;align-items:center;min-height:44px;cursor:pointer;font-weight:700;color:var(--primary)}.fb-disclosure[open]>summary{margin-bottom:.7rem}.fb-disclosure .fb-detail-section:first-of-type{border:0;margin-top:0;padding-top:0}
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
  @if($report['nettingAdjustment'] > 0)<p class="fb-note">Per-shop zero-floor adjustment: +₱{{ number_format($report['nettingAdjustment'],2) }} is included in the overall total because negative available balances are not payout-ready.</p>@endif
  @if($metric === 'verified-commission')<p class="alert alert-info small">Only fully reconciled, completed orders contribute to this verified total. “Recorded commission” includes other order states and may be larger.</p>@endif
  @if($report['source'] === 'seller')<p class="fb-note">Ready payout is netted per shop. Other seller lines, such as reserved, pending transfer and already paid, are separate states—not additions to the “in process” headline.</p>@endif
  @if($metric === 'refunds-sent')<p class="fb-note">Refunds are deductions from customer funds. The list shows their positive transfer amounts; the overview displays them with a minus sign.</p>@endif

  <ol class="fb-list">
    @forelse($report['paginated'] as $row)
      @php
        $cake = $report['cakeDetails']->get((string) $row->order_id);
        $sourceRows = collect($report['sources']->get((string) $row->order_id, []));
        $directSources = $sourceRows->filter(function ($source) use ($metric, $report, $row) {
          if ($report['source'] === 'payments') return $source['type'] === 'payment_transactions' && 'payment-'.$source['id'] === $row->id;
          if ($report['source'] === 'seller') return $source['type'] === 'seller_payout_ledgers' && 'ledger-'.$source['id'] === $row->id;
          if ($report['source'] === 'refunds') return $source['type'].'-'.$source['id'] === $row->id;
          return match ($metric) {
            'verified-commission', 'recorded-commission' => $source['type'] === 'seller_payout_ledgers' && $source['status'] !== 'cash_settled' && abs($source['commission_amount'] ?? 0) >= 0.005,
            'seller-awaiting-release' => $source['type'] === 'seller_payout_ledgers' && in_array($source['status'], ['awaiting_completion', 'refund_reserved', 'available', 'requested', 'processing'], true),
            'reconciled-seller-paid' => $source['type'] === 'seller_payout_ledgers' && $source['status'] === 'paid',
            'rider-awaiting-release' => $source['type'] === 'rider_delivery_earnings' && in_array($source['status'], ['available', 'requested'], true),
            'rider-fees-paid' => $source['type'] === 'rider_delivery_earnings' && $source['status'] === 'paid',
            default => false,
          };
        });
        if ($metric === 'rider-awaiting-release' && $directSources->isEmpty()) {
          $directSources = $sourceRows->filter(fn ($source) => $source['type'] === 'seller_payout_ledgers' && ($source['rider_allocation_amount'] ?? 0) > 0);
        }
      @endphp
      <li class="fb-row">
        <button class="fb-row-trigger" type="button" data-bs-toggle="modal" data-bs-target="#financeOrderModal" data-order-detail="fb-detail-{{ $loop->index }}" data-order-label="Order #{{ $row->order_id ?: 'Unavailable' }}" aria-haspopup="dialog" aria-label="View details for order {{ $row->order_id ?: 'unavailable' }}">
          <span class="fb-row-summary"><strong>Order #{{ $row->order_id ?: 'Unavailable' }}</strong><span class="fb-row-meta">{{ $row->shop_name ?: 'Shop unavailable' }} · {{ $row->kind }} · {{ $row->order_status ?: 'Order unavailable' }}</span></span>
          <span class="fb-row-end"><span class="fb-row-amount">{{ $row->amount < 0 ? '−' : '' }}₱{{ number_format(abs($row->amount),2) }}</span><i class="bi bi-chevron-right" aria-hidden="true"></i></span>
        </button>
        <template id="fb-detail-{{ $loop->index }}">
          <div class="fb-detail-amount"><span>Amount in {{ $report['title'] }}</span><strong>{{ $row->amount < 0 ? '−' : '' }}₱{{ number_format(abs($row->amount),2) }}</strong></div>
          <div class="fb-meta"><span>{{ $row->shop_name ?: 'Shop unavailable' }}</span><span>{{ $row->kind }}</span><span>{{ $row->order_status ?: 'Order unavailable' }}</span><span>{{ $row->date ? \Carbon\Carbon::parse($row->date)->format('M d, Y h:i A') : 'Date unavailable' }}</span>@if($row->reference)<span>Reference: {{ $row->reference }}</span>@endif</div>
          <section class="fb-proof"><h3>Why this amount?</h3>
            @if($metric === 'customer-checkout-total')
              <p>GCash payment plus the customer-paid service fee makes the checkout total.</p>
              <div class="fb-detail-field"><span>GCash payment</span><strong>₱{{ number_format((float) $row->online_settled,2) }}</strong></div><div class="fb-detail-field"><span>Customer-paid service fee</span><strong>+₱{{ number_format((float) $row->service_fee,2) }}</strong></div>
            @elseif($metric === 'customer-service-fee')
              <p>This fee was paid by the customer and recorded with the GCash transaction; it is separate from the order payment.</p>
            @elseif($metric === 'customer-funds')
              <p>This is the settled GCash payment recorded for this order.</p>
            @elseif($metric === 'refunds-sent')
              <p>This is the completed refund transfer for this order, not an available seller payout.</p>
            @elseif($report['source'] === 'seller')
              <p>This is the seller ledger's recorded net amount in the “{{ $row->kind }}” state. Any refund or recovery adjustment is already included.</p>
            @elseif($metric === 'rider-awaiting-release')
              <p>Rider fee allocated to this order, less the amount already paid to the rider.</p>
              <div class="fb-detail-field"><span>Rider allocation</span><strong>₱{{ number_format($row->rider_amount,2) }}</strong></div><div class="fb-detail-field"><span>Already paid to rider</span><strong>−₱{{ number_format($row->rider_paid,2) }}</strong></div>
            @elseif($metric === 'seller-awaiting-release')
              <p>This is the sum of seller ledger balances in unpaid states for this order. Paid seller ledgers are excluded.</p>
            @elseif($metric === 'reconciled-seller-paid')
              <p>Only seller ledger amounts marked paid are included here.</p>
            @elseif($metric === 'rider-fees-paid')
              <p>Only rider earnings marked paid are included here.</p>
            @elseif($metric === 'verified-commission')
              <p>Platform commission from this fully reconciled order. Completed refunds may reduce the reported amount; orders still needing review are excluded.</p>
            @elseif($metric === 'recorded-commission')
              <p>Commission recorded in the seller ledger, including orders not yet fully reconciled. Completed refunds may reduce the reported amount.</p>
            @endif
            @if($report['source'] === 'reconciliation' && (float) ($row->refunds ?? 0) > 0)<div class="alert alert-warning py-2 small mb-2">Completed refunds of ₱{{ number_format($row->refunds,2) }} are included in this order's reconciliation.</div>@endif
            @if($report['source'] === 'reconciliation' && abs((float) ($row->difference ?? 0)) >= 0.01)<div class="alert alert-danger py-2 small mb-2">Review needed: reconciliation difference {{ $row->difference < 0 ? '−' : '' }}₱{{ number_format(abs($row->difference),2) }}.</div>@endif
            @if($directSources->isNotEmpty())
              <div class="small fw-semibold mt-2">Records behind this amount</div>
              @foreach($directSources as $source)
                @php
                  $sourceAmount = match (true) {
                    $metric === 'customer-service-fee' => $source['service_fee'],
                    $metric === 'customer-checkout-total' => $source['customer_charged'],
                    in_array($metric, ['verified-commission', 'recorded-commission'], true) => $source['commission_amount'],
                    $metric === 'rider-awaiting-release' && $source['type'] === 'seller_payout_ledgers' => $source['rider_allocation_amount'],
                    default => $source['amount'],
                  };
                @endphp
                <div class="fb-source"><span>@if(in_array($metric, ['verified-commission', 'recorded-commission'], true)) Seller ledger commission @elseif($metric === 'rider-awaiting-release' && $source['type'] === 'seller_payout_ledgers') Rider allocation in seller ledger @else {{ $source['label'] }} @endif @if($source['status']) · {{ ucfirst(str_replace('_', ' ', $source['status'])) }}@endif @if($source['reference']) · {{ $source['reference'] }}@endif</span><strong>{{ $sourceAmount < 0 ? '−' : '' }}₱{{ number_format(abs($sourceAmount),2) }}</strong></div>
              @endforeach
            @else
              <p class="fb-note mb-0">No separate source record is available for this amount; see the complete audit breakdown below.</p>
            @endif
          </section>
          <details class="fb-disclosure fb-cake-details"><summary>Cake details</summary><div><h3 class="visually-hidden">Cakes in this order</h3>
            @if($cake && $cake['items']->isNotEmpty())
              @foreach($cake['items'] as $item)
                <div class="fb-cake-item"><strong>{{ $item->product_name }} × {{ $item->quantity }}</strong><small>@if($item->selected_size)Size: {{ $item->selected_size }} · @endif Cake amount: ₱{{ number_format((float) $item->final_unit_price_snapshot * (int) $item->quantity,2) }}</small>@if($item->custom_note)<small>Note: {{ $item->custom_note }}</small>@endif</div>
              @endforeach
            @elseif($cake && $cake['custom'])
              <div class="fb-cake-item"><strong>{{ $cake['custom']->cake_name ?: 'Custom cake' }}</strong><small>Item-level price snapshot is unavailable for this older order.</small></div>
            @elseif($cake && $cake['order'])
              <div class="fb-cake-item"><strong>{{ $cake['order']->product_name ?: 'Cake details unavailable' }} × {{ $cake['order']->quantity ?: 1 }}</strong>@if($cake['order']->selected_size)<small>Size: {{ $cake['order']->selected_size }}</small>@endif<small>Item-level price snapshot is unavailable for this older order; the name above may reflect the current catalog.</small></div>
            @else
              <p class="fb-note mb-0">Cake details are unavailable for this order.</p>
            @endif
            @if($cake && $cake['custom'])<div class="fb-cake-item"><strong>Custom cake specifications</strong>@foreach(['Flavor'=>'flavor','Size'=>'size','Layers'=>'layers','Design'=>'design_complexity','Dedication'=>'dedication','Note'=>'custom_note'] as $label=>$field)@if($cake['custom']->{$field})<small>{{ $label }}: {{ $cake['custom']->{$field} }}</small>@endif @endforeach</div>@endif
            @if($cake && $cake['addons']->isNotEmpty())<div class="small fw-semibold mt-2">Add-ons</div>@foreach($cake['addons'] as $addon)<div class="fb-detail-field"><span>{{ $addon->addon_name ?: 'Add-on' }}</span><strong>₱{{ number_format((float) $addon->addon_price,2) }}</strong></div>@endforeach @endif
          </div></details>
          <details class="fb-disclosure fb-audit"><summary>Complete audit breakdown</summary><div class="fb-detail-section"><h3>All recorded amounts</h3><div class="fb-detail-grid">
            @if($report['source'] === 'payments')
              <div class="fb-detail-field"><span>GCash payment</span><strong>₱{{ number_format((float) $row->online_settled,2) }}</strong></div><div class="fb-detail-field"><span>Customer-paid service fee</span><strong>₱{{ number_format((float) $row->service_fee,2) }}</strong></div><div class="fb-detail-field"><span>Customer checkout total</span><strong>₱{{ number_format((float) $row->customer_charged,2) }}</strong></div>
            @elseif($report['source'] === 'seller')
              <div class="fb-detail-field"><span>Seller ledger net</span><strong>{{ $row->amount < 0 ? '−' : '' }}₱{{ number_format(abs($row->amount),2) }}</strong></div><div class="fb-detail-field"><span>Online collected</span><strong>₱{{ number_format($row->online_collected,2) }}</strong></div><div class="fb-detail-field"><span>Cake value</span><strong>₱{{ number_format($row->cake_amount,2) }}</strong></div><div class="fb-detail-field"><span>Commission</span><strong>₱{{ number_format($row->commission,2) }}</strong></div><div class="fb-detail-field"><span>Online rider allocation</span><strong>₱{{ number_format($row->rider_allocation,2) }}</strong></div>
            @elseif($report['source'] === 'reconciliation')
              <div class="fb-detail-field"><span>GCash received</span><strong>₱{{ number_format($row->online_settled,2) }}</strong></div><div class="fb-detail-field"><span>Platform funding</span><strong>₱{{ number_format($row->platform_funding,2) }}</strong></div><div class="fb-detail-field"><span>Held funds</span><strong>₱{{ number_format($row->held,2) }}</strong></div><div class="fb-detail-field"><span>Refunds</span><strong>₱{{ number_format($row->refunds,2) }}</strong></div><div class="fb-detail-field"><span>Seller allocation</span><strong>₱{{ number_format($row->seller_amount,2) }}</strong></div><div class="fb-detail-field"><span>Rider allocation</span><strong>₱{{ number_format($row->rider_amount,2) }}</strong></div><div class="fb-detail-field"><span>Commission</span><strong>₱{{ number_format($row->commission,2) }}</strong></div><div class="fb-detail-field"><span>Difference</span><strong>{{ $row->difference < 0 ? '−' : '' }}₱{{ number_format(abs($row->difference),2) }}</strong></div><div class="fb-detail-field"><span>Seller awaiting</span><strong>₱{{ number_format($row->seller_payable,2) }}</strong></div><div class="fb-detail-field"><span>Seller paid</span><strong>₱{{ number_format($row->seller_paid,2) }}</strong></div><div class="fb-detail-field"><span>Rider awaiting</span><strong>₱{{ number_format($row->rider_payable,2) }}</strong></div><div class="fb-detail-field"><span>Rider paid</span><strong>₱{{ number_format($row->rider_paid,2) }}</strong></div>
            @else
              <div class="fb-detail-field"><span>Completed {{ strtolower($row->kind) }}</span><strong>₱{{ number_format($row->amount,2) }}</strong></div>
            @endif
          </div>@if($report['source'] === 'seller')<p class="fb-note mt-2 mb-0">Refund or recovery adjustments are already included in the seller ledger net amount.</p>@endif</div>
          <section class="fb-detail-section"><h3>Linked source records</h3><p class="fb-note">These records are linked to this order; some may fall outside the selected reporting period.</p>
            @if($row->order_id && $report['sources']->has((string) $row->order_id))
              @foreach($report['sources']->get((string) $row->order_id) as $source)<div class="fb-source"><span>{{ $source['label'] }}@if($source['reference']) · {{ $source['reference'] }}@endif</span><strong>{{ $source['amount'] < 0 ? '−' : '' }}₱{{ number_format(abs($source['amount']),2) }}</strong></div>@endforeach
            @else<p class="fb-note mb-0">No linked source records are available.</p>@endif
          </section></details>
          <div class="fb-links">@if($report['source'] === 'seller')<a href="{{ route('superadmin.payouts.sellers', ['search'=>$row->order_id]) }}" class="btn btn-sm btn-outline-primary">Seller payout records</a>@endif @if($report['source'] === 'refunds')<a href="{{ route('superadmin.return_cases') }}" class="btn btn-sm btn-outline-primary">Return &amp; Refund Cases</a>@endif</div>
        </template>
      </li>
    @empty
      <li class="fb-row text-center text-muted py-4">No source records match these filters. Try All Time or clear the search and shop filters.</li>
    @endforelse
  </ol>
  @if($report['paginated']->hasPages())<div class="mb-3">{{ $report['paginated']->links() }}</div>@endif
  <footer class="fb-footer" aria-label="Breakdown totals">
    <div class="fb-footer-line"><span>Visible page subtotal (signed rows)</span><strong>₱{{ number_format($report['paginated']->getCollection()->sum('amount'),2) }}</strong></div>
    <div class="fb-note mt-2">Subtotal of the {{ $report['paginated']->count() }} record{{ $report['paginated']->count() === 1 ? '' : 's' }} on this page.</div>
  </footer>
</div>
<div class="modal fade fb-detail-modal" id="financeOrderModal" tabindex="-1" aria-labelledby="financeOrderModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5" id="financeOrderModalTitle">Order details</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body" id="financeOrderModalBody"></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div></div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('financeOrderModal');
  if (!modal) return;
  document.body.appendChild(modal);
  modal.addEventListener('show.bs.modal', event => {
    const trigger = event.relatedTarget;
    const content = trigger?.dataset.orderDetail ? document.getElementById(trigger.dataset.orderDetail) : null;
    modal.querySelector('#financeOrderModalTitle').textContent = trigger?.dataset.orderLabel || 'Order details';
    modal.querySelector('#financeOrderModalBody').replaceChildren(...(content ? [content.content.cloneNode(true)] : []));
  });
  modal.addEventListener('hidden.bs.modal', () => modal.querySelector('#financeOrderModalBody').replaceChildren());
});
</script>
@endsection
