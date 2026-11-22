@extends('layouts.app')
@section('page_title', 'COD Rider Remittances')
@section('content')
<style>
.remit-shell{max-width:1320px;margin:0 auto}.remit-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1rem}.remit-head h1{font-size:clamp(1.3rem,2.4vw,1.85rem);font-weight:900;margin:0;color:#111827}.remit-head p{margin:.25rem 0 0;color:#64748b}.remit-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.75rem;margin-bottom:1rem}.remit-summary a,.remit-summary article{display:block;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:1rem;text-decoration:none;color:inherit}.remit-summary strong{display:block;font-size:1.35rem;line-height:1.15;color:#111827}.remit-summary span{display:block;margin-top:.25rem;font-size:.76rem;color:#64748b;font-weight:700}.remit-filter{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:1rem;margin-bottom:1rem}.remit-filter-grid{display:grid;grid-template-columns:1.5fr repeat(6,minmax(120px,1fr));gap:.75rem;align-items:end}.remit-filter label{display:block;font-size:.72rem;font-weight:800;color:#475569;margin-bottom:.3rem}.remit-quick{display:flex;gap:.45rem;flex-wrap:wrap;margin-top:.75rem}.remit-quick a{padding:.35rem .65rem;border:1px solid #e2e8f0;border-radius:999px;color:#475569;text-decoration:none;font-size:.76rem;font-weight:800}.remit-quick a.active{background:#b45309;color:#fff;border-color:#b45309}.remit-list{display:grid;gap:.7rem}.remit-row{display:grid;grid-template-columns:minmax(150px,1.1fr) minmax(160px,1.25fr) minmax(130px,1fr) repeat(3,minmax(105px,.72fr)) minmax(145px,.95fr);gap:.75rem;align-items:center;background:#fff;border:1px solid #e5e7eb;border-radius:15px;padding:.9rem 1rem;box-shadow:0 8px 24px rgba(15,23,42,.04)}.remit-cell{min-width:0}.remit-label{display:block;font-size:.66rem;letter-spacing:.045em;text-transform:uppercase;color:#94a3b8;font-weight:900;margin-bottom:.2rem}.remit-value{font-size:.84rem;color:#111827;font-weight:700;overflow-wrap:anywhere}.remit-sub{font-size:.72rem;color:#64748b;margin-top:.15rem}.remit-money{text-align:right;font-variant-numeric:tabular-nums}.remit-due{color:#b45309;font-size:.95rem}.remit-status{display:inline-flex;align-items:center;padding:.28rem .55rem;border-radius:999px;font-size:.7rem;font-weight:900;background:var(--status-bg);color:var(--status-fg)}.remit-action form{display:grid;gap:.4rem}.remit-action .form-control{font-size:.75rem}.remit-action .btn{min-height:38px}.remit-totals{margin-top:1rem;background:#fffaf5;border:1px solid #fed7aa;border-radius:15px;padding:1rem;display:flex;align-items:center;justify-content:flex-end;gap:2rem;flex-wrap:wrap}.remit-total-item span{display:block;font-size:.7rem;color:#92400e;font-weight:800;text-transform:uppercase}.remit-total-item strong{display:block;font-size:1.15rem;color:#78350f;text-align:right}.remit-empty{padding:2.5rem 1rem;text-align:center;border:1px dashed #cbd5e1;border-radius:15px;background:#fff;color:#64748b}.remit-pagination{margin-top:1rem}.remit-results{font-size:.78rem;color:#64748b;margin-bottom:.65rem}.remit-back{white-space:nowrap}
@media(max-width:1100px){.remit-summary{grid-template-columns:repeat(3,minmax(0,1fr))}.remit-filter-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.remit-row{grid-template-columns:repeat(3,minmax(0,1fr))}.remit-money{text-align:left}}
@media(max-width:640px){.remit-head{display:block}.remit-back{margin-top:.75rem}.remit-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.remit-filter-grid{grid-template-columns:1fr 1fr}.remit-filter-grid>div:first-child{grid-column:1/-1}.remit-row{grid-template-columns:1fr 1fr;padding:.85rem}.remit-row .remit-action{grid-column:1/-1}.remit-totals{justify-content:space-between;gap:.75rem}.remit-total-item strong{text-align:left}}
@media(max-width:375px){.remit-summary,.remit-filter-grid,.remit-row{grid-template-columns:1fr}.remit-filter-grid>div:first-child,.remit-row .remit-action{grid-column:auto}}
</style>

@php
  $statusMeta = function ($status) {
    return match($status) {
      'submitted' => ['Awaiting confirmation', '#dbeafe', '#1d4ed8'],
      'confirmed' => ['Confirmed', '#dcfce7', '#166534'],
      'awaiting_payment' => ['Waiting for rider payment', '#fef3c7', '#92400e'],
      'qr_expired' => ['Payment expired', '#fee2e2', '#991b1b'],
      'rejected' => ['Needs rider retry', '#fee2e2', '#991b1b'],
      default => ['Waiting for rider', '#fef3c7', '#92400e'],
    };
  };
@endphp

<div class="remit-shell">
  <header class="remit-head">
    <div><h1><i class="bi bi-cash-stack me-2" style="color:#b45309"></i>COD Rider Remittances</h1><p>Track the cake cash riders must hand over. Delivery fees retained by riders are shown separately.</p></div>
    <a class="btn btn-outline-secondary btn-sm remit-back" href="{{ route('seller.dashboard') }}"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
  </header>

  @if(session('msg'))<div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>{{ session('msg') }}</div>@endif
  @if(session('err'))<div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i>{{ session('err') }}</div>@endif

  <section class="remit-summary" aria-label="Account-wide remittance summary">
    <a href="{{ route('seller.remittances', ['status'=>'outstanding','period'=>'all']) }}"><strong>₱{{ number_format($summary['outstanding'],2) }}</strong><span>Outstanding cake cash</span></a>
    <a href="{{ route('seller.remittances', ['status'=>'waiting','period'=>'all']) }}"><strong>₱{{ number_format($summary['waiting'],2) }}</strong><span>Waiting for rider</span></a>
    <a href="{{ route('seller.remittances', ['status'=>'submitted','period'=>'all']) }}"><strong>₱{{ number_format($summary['submitted'],2) }}</strong><span>Awaiting confirmation</span></a>
    <a href="{{ route('seller.remittances', ['status'=>'confirmed','period'=>'all']) }}"><strong>₱{{ number_format($summary['confirmed'],2) }}</strong><span>Confirmed cash</span></a>
    <a href="{{ route('seller.remittances', ['status'=>'all','period'=>'all']) }}"><strong>{{ number_format($summary['record_count']) }}</strong><span>All remittance records</span></a>
  </section>

  <form method="GET" action="{{ route('seller.remittances') }}" class="remit-filter">
    <div class="remit-filter-grid">
      <div><label for="remitSearch">Search</label><input id="remitSearch" class="form-control" name="search" value="{{ $filters['search'] }}" placeholder="Order, tracking, customer, rider, product, reference"></div>
      <div><label for="remitStatus">Status</label><select id="remitStatus" class="form-select" name="status"><option value="outstanding" @selected($filters['status']==='outstanding')>Outstanding</option><option value="waiting" @selected($filters['status']==='waiting')>Waiting for rider</option><option value="submitted" @selected($filters['status']==='submitted')>Awaiting confirmation</option><option value="confirmed" @selected($filters['status']==='confirmed')>Confirmed</option><option value="all" @selected($filters['status']==='all')>All</option></select></div>
      <div><label for="remitRider">Rider</label><select id="remitRider" class="form-select" name="rider_id"><option value="">All riders</option>@foreach($riders as $rider)<option value="{{ $rider->id }}" @selected((int)$filters['rider_id']===(int)$rider->id)>{{ $rider->name }}</option>@endforeach</select></div>
      <div><label for="remitFrom">From</label><input id="remitFrom" class="form-control" type="date" name="from" value="{{ $filters['from'] }}"></div>
      <div><label for="remitTo">To</label><input id="remitTo" class="form-control" type="date" name="to" value="{{ $filters['to'] }}"></div>
      <div><label for="remitSort">Sort</label><select id="remitSort" class="form-select" name="sort"><option value="newest" @selected($filters['sort']==='newest')>Newest</option><option value="oldest" @selected($filters['sort']==='oldest')>Oldest</option><option value="amount_high" @selected($filters['sort']==='amount_high')>Amount: high to low</option><option value="amount_low" @selected($filters['sort']==='amount_low')>Amount: low to high</option></select></div>
      <div><label for="remitPerPage">Rows</label><select id="remitPerPage" class="form-select" name="per_page">@foreach([10,25,50] as $size)<option value="{{ $size }}" @selected($filters['per_page']===$size)>{{ $size }} per page</option>@endforeach</select></div>
    </div>
    <input type="hidden" name="period" value="all">
    <div class="d-flex gap-2 flex-wrap mt-3"><button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Apply Filters</button><a class="btn btn-outline-secondary" href="{{ route('seller.remittances') }}">Clear Filters</a></div>
    <div class="remit-quick">@foreach(['all'=>'All Time','today'=>'Today','week'=>'This Week','month'=>'This Month'] as $key=>$label)<a class="{{ $filters['period']===$key?'active':'' }}" href="{{ route('seller.remittances', array_merge(request()->except(['page','from','to']), ['period'=>$key])) }}">{{ $label }}</a>@endforeach</div>
  </form>

  <div class="remit-results">{{ number_format($filteredCount) }} matching record{{ $filteredCount===1?'':'s' }} · page {{ $rows->currentPage() }} of {{ max(1,$rows->lastPage()) }}</div>
  <section class="remit-list" aria-label="COD rider remittance list">
    @forelse($rows as $row)
      @php([$statusLabel,$statusBg,$statusFg] = $statusMeta($row->status))
      <article class="remit-row">
        <div class="remit-cell"><span class="remit-label">Order</span><a class="remit-value text-decoration-none" href="{{ route('seller.orders',['search'=>$row->order_id]) }}">#{{ $row->order_id }}</a><div class="remit-sub">{{ $row->track_code ?: 'No tracking code' }} · {{ $row->product_name }} × {{ $row->quantity }}</div></div>
        <div class="remit-cell"><span class="remit-label">Customer / rider</span><div class="remit-value">{{ $row->customer_name }}</div><div class="remit-sub"><i class="bi bi-bicycle me-1"></i>{{ $row->rider_name }}</div></div>
        <div class="remit-cell"><span class="remit-label">Status</span><span class="remit-status" style="--status-bg:{{ $statusBg }};--status-fg:{{ $statusFg }}">{{ $statusLabel }}</span><div class="remit-sub">Order: {{ $row->order_status }} · {{ strtoupper($row->payment_method) }}</div></div>
        <div class="remit-cell remit-money"><span class="remit-label">Customer paid rider</span><div class="remit-value">₱{{ number_format($row->customer_cash_collected,2) }}</div></div>
        <div class="remit-cell remit-money"><span class="remit-label">Rider keeps</span><div class="remit-value">₱{{ number_format($row->rider_delivery_fee,2) }}</div><div class="remit-sub">Delivery fee</div></div>
        <div class="remit-cell remit-money"><span class="remit-label">Cake amount to remit</span><div class="remit-value remit-due">₱{{ number_format($row->cake_amount_due,2) }}</div></div>
        <div class="remit-cell remit-action"><span class="remit-label">Action / recorded</span>@if($row->status==='submitted')<form method="POST" action="{{ route('seller.orders.remittance_confirm',['id'=>$row->order_id,'remittanceId'=>$row->id]) }}" data-prevent-double-submit>@csrf<input class="form-control form-control-sm" name="seller_note" maxlength="500" placeholder="Confirmation note (optional)"><button class="btn btn-success btn-sm" type="submit" data-cs-confirm="Confirm that this COD money was received by the seller?" data-cs-title="Confirm COD Remittance" data-cs-ok="Confirm Received" data-cs-icon="bi-check2-circle" data-cs-icon-bg="#dcfce7" data-cs-icon-color="#16a34a"><i class="bi bi-check2-circle me-1"></i>Confirm Received</button></form>@elseif($row->status==='confirmed')<div class="remit-value">{{ $row->confirmed_at ? \Carbon\Carbon::parse($row->confirmed_at)->format('M j, Y g:i A') : 'Confirmed' }}</div>@else<div class="remit-value">Waiting for rider handover</div><div class="remit-sub">{{ \Carbon\Carbon::parse($row->collected_at ?: $row->created_at)->format('M j, Y g:i A') }}</div>@endif</div>
      </article>
    @empty
      <div class="remit-empty"><i class="bi bi-cash-stack d-block mb-2" style="font-size:2rem"></i>No remittances match these filters.</div>
    @endforelse
  </section>

  <div class="remit-totals">
    <div class="remit-total-item"><span>Current page subtotal</span><strong>₱{{ number_format($pageTotal,2) }}</strong></div>
    <div class="remit-total-item"><span>Filtered cake amount</span><strong>₱{{ number_format($filteredTotal,2) }}</strong></div>
  </div>
  @if($rows->hasPages())<div class="remit-pagination">{{ $rows->links() }}</div>@endif
</div>
@endsection
