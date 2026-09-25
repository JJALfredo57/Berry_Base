@extends('layouts.app')

@section('content')
@php
  $statusMeta = [
    'pending' => ['label' => 'Pending', 'bg' => '#fff7ed', 'fg' => '#9a3412', 'icon' => 'bi-hourglass-split'],
    'accepted' => ['label' => 'Accepted', 'bg' => '#dcfce7', 'fg' => '#166534', 'icon' => 'bi-check-circle'],
    'alternative_offered' => ['label' => 'Alternative Offered', 'bg' => '#eff6ff', 'fg' => '#1d4ed8', 'icon' => 'bi-shuffle'],
    'schedule_suggested' => ['label' => 'Schedule Suggested', 'bg' => '#f0f9ff', 'fg' => '#0369a1', 'icon' => 'bi-calendar-event'],
    'needs_more_details' => ['label' => 'Needs Details', 'bg' => '#fef3c7', 'fg' => '#92400e', 'icon' => 'bi-question-circle'],
    'declined' => ['label' => 'Declined', 'bg' => '#fee2e2', 'fg' => '#991b1b', 'icon' => 'bi-x-circle'],
    'expired' => ['label' => 'Expired', 'bg' => '#f1f5f9', 'fg' => '#475569', 'icon' => 'bi-clock-history'],
  ];
  $tabs = [
    'pending' => 'Pending',
    'rush' => 'Rush',
    'suggested' => 'Seller Replies',
    'accepted' => 'Accepted',
    'declined' => 'Closed',
    'all' => 'All',
  ];
@endphp

<style>
.order-request-shell{max-width:1240px;margin:0 auto;padding:1rem 1rem 2rem}.or-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1rem}.or-title{font-size:clamp(1.25rem,2vw,1.75rem);font-weight:800;margin:0;color:#111827}.or-sub{color:#64748b;margin:.25rem 0 0;font-size:.92rem}.or-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem;margin-bottom:1rem}.or-stat{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:.9rem 1rem;box-shadow:0 10px 24px rgba(15,23,42,.05)}.or-stat strong{display:block;font-size:1.4rem;line-height:1;color:#111827}.or-stat span{font-size:.78rem;color:#64748b}.or-toolbar{display:flex;gap:.75rem;align-items:center;justify-content:space-between;flex-wrap:wrap;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:.75rem;margin-bottom:1rem}.or-tabs{display:flex;gap:.45rem;overflow:auto;padding-bottom:.1rem}.or-tab{display:inline-flex;align-items:center;gap:.35rem;white-space:nowrap;border:1px solid #e5e7eb;background:#fff;color:#475569;text-decoration:none;border-radius:999px;padding:.45rem .72rem;font-weight:700;font-size:.82rem}.or-tab.active{background:#e91e63;color:#fff;border-color:#e91e63}.or-tab .count{font-size:.72rem;background:rgba(15,23,42,.08);border-radius:999px;padding:.06rem .38rem}.or-tab.active .count{background:rgba(255,255,255,.22)}.or-search{display:flex;gap:.5rem;min-width:min(100%,320px)}.or-search .form-control{border-radius:999px}.or-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;margin-bottom:1rem;box-shadow:0 14px 34px rgba(15,23,42,.06)}.or-card.is-rush{border-color:#fb7185;box-shadow:0 16px 36px rgba(225,29,72,.12)}.or-card-head{display:flex;gap:.9rem;padding:1rem;border-bottom:1px solid #f1f5f9;align-items:center}.or-img{width:78px;height:78px;border-radius:12px;object-fit:cover;background:#f8fafc;flex:0 0 auto}.or-img-ph{width:78px;height:78px;border-radius:12px;background:#fce7f3;color:#be185d;display:flex;align-items:center;justify-content:center;font-size:1.6rem}.or-main{flex:1;min-width:0}.or-name{font-weight:800;color:#111827;margin:0;font-size:1.02rem}.or-meta{display:flex;gap:.45rem;flex-wrap:wrap;margin-top:.45rem}.or-pill{display:inline-flex;align-items:center;gap:.3rem;border-radius:999px;padding:.28rem .55rem;font-size:.72rem;font-weight:800;background:#f8fafc;color:#475569}.or-pill.rush{background:#fff1f2;color:#be123c}.or-pill.status{background:var(--or-bg);color:var(--or-fg)}.or-body{display:grid;grid-template-columns:1.15fr .85fr;gap:1rem;padding:1rem}.or-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem}.or-detail{background:#f8fafc;border:1px solid #eef2f7;border-radius:12px;padding:.75rem}.or-label{display:block;text-transform:uppercase;letter-spacing:.04em;color:#94a3b8;font-size:.68rem;font-weight:800;margin-bottom:.25rem}.or-value{color:#111827;font-weight:750;font-size:.9rem}.or-note{grid-column:1/-1}.or-actions{background:#fbfdff;border:1px solid #e5e7eb;border-radius:14px;padding:.85rem}.or-actions summary{cursor:pointer;font-weight:800;color:#334155;margin:.2rem 0}.or-actions details{border-top:1px solid #e5e7eb;padding-top:.65rem;margin-top:.65rem}.or-actions details:first-child{border-top:0;padding-top:0;margin-top:0}.or-actions textarea{min-height:72px}.or-empty{background:#fff;border:1px dashed #cbd5e1;border-radius:16px;padding:2rem;text-align:center;color:#64748b}.countdown{font-variant-numeric:tabular-nums}.countdown.urgent{color:#be123c}.btn-or-primary{background:#e91e63;color:#fff;border:0;border-radius:10px;padding:.55rem .8rem;font-weight:800}.btn-or-soft{background:#f8fafc;color:#334155;border:1px solid #cbd5e1;border-radius:10px;padding:.55rem .8rem;font-weight:800}@media(max-width:768px){.order-request-shell{padding:.75rem}.or-header{display:block}.or-summary{grid-template-columns:1fr 1fr}.or-toolbar{align-items:stretch}.or-search{width:100%}.or-card-head{align-items:flex-start}.or-body{grid-template-columns:1fr}.or-detail-grid{grid-template-columns:1fr}.or-img,.or-img-ph{width:64px;height:64px}.or-actions{padding:.75rem}}@media(max-width:430px){.or-summary{grid-template-columns:1fr}.or-card-head{padding:.85rem}.or-body{padding:.85rem}.or-name{font-size:.95rem}}
</style>

<div class="order-request-shell">
  <div class="or-header">
    <div>
      <h1 class="or-title"><i class="bi bi-lightning-charge me-1" style="color:#e91e63"></i>Order Requests</h1>
      <p class="or-sub">Out-of-stock and special schedule requests. Rush items are sorted first.</p>
    </div>
    <a href="{{ route('seller.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
  </div>

  <div class="or-summary">
    <div class="or-stat"><strong>{{ number_format($counts['rush'] ?? 0) }}</strong><span>Rush pending</span></div>
    <div class="or-stat"><strong>{{ number_format($counts['pending'] ?? 0) }}</strong><span>Total pending</span></div>
    <div class="or-stat"><strong>{{ number_format($counts['suggested'] ?? 0) }}</strong><span>Waiting for customer</span></div>
  </div>

  <div class="or-toolbar">
    <div class="or-tabs">
      @foreach($tabs as $key => $label)
        <a class="or-tab {{ $tab === $key ? 'active' : '' }}" href="{{ route('seller.order_requests', array_filter(['tab' => $key, 'search' => $search])) }}">
          {{ $label }} <span class="count">{{ $counts[$key] ?? 0 }}</span>
        </a>
      @endforeach
    </div>
    <form method="GET" action="{{ route('seller.order_requests') }}" class="or-search">
      <input type="hidden" name="tab" value="{{ $tab }}">
      <input type="search" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search cake, customer, phone">
      <button class="btn-or-soft" type="submit"><i class="bi bi-search"></i></button>
    </form>
  </div>

  @forelse($requests as $req)
    @php
      $meta = $statusMeta[$req->status] ?? $statusMeta['pending'];
      $customerName = $req->user_fullname ?: ($req->guest_name ?: 'Guest customer');
      $customerPhone = $req->user_phone ?: $req->guest_phone;
      $preferred = $req->preferred_datetime ? \Carbon\Carbon::parse($req->preferred_datetime) : null;
      $notice = is_numeric($req->requested_notice_minutes) ? (int) $req->requested_notice_minutes : null;
      $noticeLabel = $notice === null ? 'Not computed' : ($notice >= 1440 ? floor($notice / 1440).' day'.(floor($notice / 1440) == 1 ? '' : 's') : max(0, floor($notice / 60)).' hr '.max(0, $notice % 60).' min');
    @endphp
    <div class="or-card {{ $req->is_rush ? 'is-rush' : '' }}">
      <div class="or-card-head">
        @if($req->image_path)
          <img class="or-img" src="{{ $req->image_path }}" alt="{{ $req->product_name }}">
        @else
          <div class="or-img-ph"><i class="bi bi-cake2"></i></div>
        @endif
        <div class="or-main">
          <h2 class="or-name">{{ $req->product_name ?: 'Cake request' }}</h2>
          <div class="small text-muted">Request #{{ $req->id }} by {{ $customerName }}{{ $customerPhone ? ' - '.$customerPhone : '' }}</div>
          <div class="or-meta">
            @if($req->is_rush)<span class="or-pill rush"><i class="bi bi-lightning-charge-fill"></i>Rush</span>@endif
            <span class="or-pill status" style="--or-bg:{{ $meta['bg'] }};--or-fg:{{ $meta['fg'] }}"><i class="bi {{ $meta['icon'] }}"></i>{{ $meta['label'] }}</span>
            <span class="or-pill"><i class="bi bi-bag"></i>{{ $req->quantity }} pc{{ (int)$req->quantity === 1 ? '' : 's' }}</span>
            @if($req->allow_similar_cake)<span class="or-pill"><i class="bi bi-shuffle"></i>Allows similar cake</span>@endif
          </div>
        </div>
      </div>

      <div class="or-body">
        <div class="or-detail-grid">
          <div class="or-detail"><span class="or-label">Preferred Schedule</span><div class="or-value">{{ $preferred ? $preferred->format('M d, Y g:i A') : trim($req->preferred_date.' '.$req->preferred_time) }}</div></div>
          <div class="or-detail"><span class="or-label">Time Left</span><div class="or-value countdown" data-countdown="{{ $preferred ? $preferred->toIso8601String() : '' }}">Checking...</div></div>
          <div class="or-detail"><span class="or-label">Seller Prep Setting</span><div class="or-value">{{ (int)($req->seller_prep_days_at_request ?? 0) }} day{{ (int)($req->seller_prep_days_at_request ?? 0) === 1 ? '' : 's' }}</div></div>
          <div class="or-detail"><span class="or-label">Customer Notice</span><div class="or-value">{{ $noticeLabel }}</div></div>
          <div class="or-detail"><span class="or-label">Flavor / Type</span><div class="or-value">{{ $req->flavor ?: 'Not specified' }}{{ $req->classification ? ' - '.$req->classification : '' }}</div></div>
          <div class="or-detail"><span class="or-label">Source</span><div class="or-value">{{ ucfirst(str_replace('_',' ', $req->source ?: 'shop')) }}</div></div>
          @if($req->customer_note)
            <div class="or-detail or-note"><span class="or-label">Customer Note</span><div class="or-value">{{ $req->customer_note }}</div></div>
          @endif
          @if($req->seller_response)
            <div class="or-detail or-note"><span class="or-label">Last Seller Response</span><div class="or-value">{{ $req->seller_response }}</div></div>
          @endif
        </div>

        <div class="or-actions">
          @if(in_array($req->status, ['pending','alternative_offered','schedule_suggested','needs_more_details'], true))
            <details open>
              <summary><i class="bi bi-check2-circle me-1"></i>Accept request</summary>
              <form action="{{ route('seller.order_requests.update', $req->id) }}" method="POST" class="mt-2">
                @csrf
                <input type="hidden" name="action" value="accept">
                <label class="form-label small fw-semibold">Accepted price (optional)</label>
                <input type="number" name="accepted_price" min="0" step="0.01" class="form-control form-control-sm mb-2" placeholder="Leave blank if price is unchanged">
                <textarea name="seller_response" class="form-control form-control-sm mb-2" placeholder="Short message for the customer"></textarea>
                <button class="btn-or-primary w-100" type="submit"><i class="bi bi-check-lg me-1"></i>Accept</button>
              </form>
            </details>
            <details>
              <summary><i class="bi bi-calendar-event me-1"></i>Suggest another schedule</summary>
              <form action="{{ route('seller.order_requests.update', $req->id) }}" method="POST" class="mt-2">
                @csrf
                <input type="hidden" name="action" value="suggest_schedule">
                <div class="row g-2 mb-2"><div class="col-7"><input type="date" name="suggested_date" class="form-control form-control-sm" required></div><div class="col-5"><input type="time" name="suggested_time" class="form-control form-control-sm" required></div></div>
                <textarea name="seller_response" class="form-control form-control-sm mb-2" placeholder="Explain why this schedule is better"></textarea>
                <button class="btn-or-soft w-100" type="submit">Send schedule</button>
              </form>
            </details>
            <details>
              <summary><i class="bi bi-shuffle me-1"></i>Offer alternative cake</summary>
              <form action="{{ route('seller.order_requests.update', $req->id) }}" method="POST" class="mt-2">
                @csrf
                <input type="hidden" name="action" value="offer_alternative">
                <select name="alternative_product_id" class="form-select form-select-sm mb-2">
                  <option value="">No specific product</option>
                  @foreach($alternativeProducts as $alt)
                    <option value="{{ $alt->id }}">{{ $alt->name }}</option>
                  @endforeach
                </select>
                <textarea name="seller_response" class="form-control form-control-sm mb-2" placeholder="Describe the alternative"></textarea>
                <button class="btn-or-soft w-100" type="submit">Offer alternative</button>
              </form>
            </details>
            <details>
              <summary><i class="bi bi-x-circle me-1"></i>Decline / ask details</summary>
              <form action="{{ route('seller.order_requests.update', $req->id) }}" method="POST" class="mt-2">
                @csrf
                <textarea name="seller_response" class="form-control form-control-sm mb-2" placeholder="Reason or question for the customer" required></textarea>
                <div class="d-flex gap-2"><button class="btn-or-soft flex-fill" name="action" value="needs_more_details" type="submit">Ask details</button><button class="btn btn-outline-danger flex-fill fw-bold" name="action" value="decline" type="submit">Decline</button></div>
              </form>
            </details>
          @else
            <div class="text-muted small">This request is already closed. Customer notifications were sent when it changed status.</div>
          @endif
        </div>
      </div>
    </div>
  @empty
    <div class="or-empty">
      <i class="bi bi-inbox" style="font-size:2rem;color:#cbd5e1"></i>
      <h2 class="h5 mt-2 mb-1">No requests here</h2>
      <p class="mb-0">New request orders will appear here with rush ones at the top.</p>
    </div>
  @endforelse

  <div class="mt-3">{{ $requests->links() }}</div>
</div>

<script>
(function(){
  function render(el){
    var raw = el.getAttribute('data-countdown');
    if(!raw){ el.textContent = 'No schedule'; return; }
    var target = new Date(raw).getTime();
    var diff = target - Date.now();
    if(diff <= 0){ el.textContent = 'Due now / overdue'; el.classList.add('urgent'); return; }
    var mins = Math.floor(diff / 60000);
    var days = Math.floor(mins / 1440);
    var hours = Math.floor((mins % 1440) / 60);
    var rem = mins % 60;
    el.textContent = days > 0 ? days + 'd ' + hours + 'h left' : hours + 'h ' + rem + 'm left';
    el.classList.toggle('urgent', mins < 240);
  }
  function tick(){ document.querySelectorAll('[data-countdown]').forEach(render); }
  tick();
  setInterval(tick, 30000);
})();
</script>
@endsection
