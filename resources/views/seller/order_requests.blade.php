@extends('layouts.app')

@section('content')
@php
  $statusMeta = [
    'pending' => ['label' => 'Pending', 'bg' => '#fff7ed', 'fg' => '#9a3412', 'icon' => 'bi-hourglass-split'],
    'accepted' => ['label' => 'Waiting for Customer', 'bg' => '#dcfce7', 'fg' => '#166534', 'icon' => 'bi-check-circle'],
    'customer_accepted' => ['label' => 'Customer Accepted', 'bg' => '#dbeafe', 'fg' => '#1d4ed8', 'icon' => 'bi-bag-check'],
    'customer_declined' => ['label' => 'Customer Declined', 'bg' => '#fee2e2', 'fg' => '#991b1b', 'icon' => 'bi-person-x'],
    'converted' => ['label' => 'Order Created', 'bg' => '#ede9fe', 'fg' => '#6d28d9', 'icon' => 'bi-receipt'],
    'alternative_offered' => ['label' => 'Alternative Offered', 'bg' => '#eff6ff', 'fg' => '#1d4ed8', 'icon' => 'bi-shuffle'],
    'schedule_suggested' => ['label' => 'Schedule Suggested', 'bg' => '#f0f9ff', 'fg' => '#0369a1', 'icon' => 'bi-calendar-event'],
    'needs_more_details' => ['label' => 'Needs Details', 'bg' => '#fef3c7', 'fg' => '#92400e', 'icon' => 'bi-question-circle'],
    'declined' => ['label' => 'Declined', 'bg' => '#fee2e2', 'fg' => '#991b1b', 'icon' => 'bi-x-circle'],
    'expired' => ['label' => 'Expired', 'bg' => '#f1f5f9', 'fg' => '#475569', 'icon' => 'bi-clock-history'],
  ];
  $tabs = [
    'pending' => 'Pending',
    'suggested' => 'Seller Replies',
    'accepted' => 'Accepted',
    'converted' => 'Order Created',
    'declined' => 'Closed',
    'all' => 'All',
  ];
@endphp

<style>
.order-request-shell{max-width:1240px;margin:0 auto;padding:1rem 1rem 2rem}.or-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1rem}.or-title{font-size:clamp(1.25rem,2vw,1.75rem);font-weight:800;margin:0;color:#111827}.or-sub{color:#64748b;margin:.25rem 0 0;font-size:.92rem}.or-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem;margin-bottom:1rem}.or-stat{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:.9rem 1rem;box-shadow:0 10px 24px rgba(15,23,42,.05)}.or-stat strong{display:block;font-size:1.4rem;line-height:1;color:#111827}.or-stat span{font-size:.78rem;color:#64748b}.or-toolbar{display:flex;gap:.75rem;align-items:center;justify-content:space-between;flex-wrap:wrap;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:.75rem;margin-bottom:1rem}.or-tabs{display:flex;gap:.45rem;overflow:auto;padding-bottom:.1rem}.or-tab{display:inline-flex;align-items:center;gap:.35rem;white-space:nowrap;border:1px solid #e5e7eb;background:#fff;color:#475569;text-decoration:none;border-radius:999px;padding:.45rem .72rem;font-weight:700;font-size:.82rem}.or-tab.active{background:#e91e63;color:#fff;border-color:#e91e63}.or-tab .count{font-size:.72rem;background:rgba(15,23,42,.08);border-radius:999px;padding:.06rem .38rem}.or-tab.active .count{background:rgba(255,255,255,.22)}.or-search{display:flex;gap:.5rem;min-width:min(100%,320px)}.or-search .form-control{border-radius:999px}.or-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;margin-bottom:1rem;box-shadow:0 14px 34px rgba(15,23,42,.06);animation:or-in .3s ease both}.or-card.is-rush{border-color:#fb7185;box-shadow:0 16px 36px rgba(225,29,72,.12)}.or-card-head{display:flex;gap:.9rem;padding:1rem;border-bottom:1px solid #f1f5f9;align-items:center}.or-img{width:78px;height:78px;border-radius:12px;object-fit:cover;background:#f8fafc;flex:0 0 auto}.or-img-ph{width:78px;height:78px;border-radius:12px;background:#fce7f3;color:#be185d;display:flex;align-items:center;justify-content:center;font-size:1.6rem}.or-main{flex:1;min-width:0}.or-name{font-weight:800;color:#111827;margin:0;font-size:1.02rem;overflow-wrap:anywhere}.or-meta{display:flex;gap:.45rem;flex-wrap:wrap;margin-top:.45rem}.or-pill{display:inline-flex;align-items:center;gap:.3rem;border-radius:999px;padding:.28rem .55rem;font-size:.72rem;font-weight:800;background:#f8fafc;color:#475569}.or-pill.rush{background:#fff1f2;color:#be123c}.or-pill.status{background:var(--or-bg);color:var(--or-fg)}.or-body{display:grid;grid-template-columns:1.15fr .85fr;gap:1rem;padding:1rem}.or-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem}.or-detail{background:#f8fafc;border:1px solid #eef2f7;border-radius:12px;padding:.75rem;min-width:0}.or-label{display:block;text-transform:uppercase;letter-spacing:.04em;color:#94a3b8;font-size:.68rem;font-weight:800;margin-bottom:.25rem}.or-value{color:#111827;font-weight:750;font-size:.9rem;overflow-wrap:anywhere}.or-note{grid-column:1/-1}.or-actions{background:#fbfdff;border:1px solid #e5e7eb;border-radius:14px;padding:.85rem}.or-actions summary{cursor:pointer;font-weight:800;color:#334155;margin:.2rem 0}.or-actions details{border-top:1px solid #e5e7eb;padding-top:.65rem;margin-top:.65rem}.or-actions details:first-child{border-top:0;padding-top:0;margin-top:0}.or-actions textarea{min-height:72px}.or-empty{background:#fff;border:1px dashed #cbd5e1;border-radius:16px;padding:2rem;text-align:center;color:#64748b}.countdown{font-variant-numeric:tabular-nums}.countdown.urgent{color:#be123c}.btn-or-primary{background:#e91e63;color:#fff;border:0;border-radius:10px;padding:.55rem .8rem;font-weight:800}.btn-or-soft{background:#f8fafc;color:#334155;border:1px solid #cbd5e1;border-radius:10px;padding:.55rem .8rem;font-weight:800}@keyframes or-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}@media(max-width:768px){.order-request-shell{padding:.75rem}.or-header{display:block}.or-header .btn{margin-top:.7rem}.or-summary{grid-template-columns:1fr 1fr}.or-toolbar{align-items:stretch}.or-search{width:100%}.or-card-head{align-items:flex-start}.or-body{grid-template-columns:1fr}.or-detail-grid{grid-template-columns:1fr}.or-img,.or-img-ph{width:64px;height:64px}.or-actions{padding:.75rem}.or-actions button{min-height:42px}}@media(max-width:430px){.or-summary{grid-template-columns:1fr}.or-card-head{padding:.85rem}.or-body{padding:.85rem}.or-name{font-size:.95rem}.or-search{min-width:0}.or-search .form-control{min-width:0}.or-tabs{margin-inline:-.1rem}.or-pill{max-width:100%;white-space:normal}}
.or-card{margin-bottom:.55rem;border-radius:14px;box-shadow:none;overflow:visible}.or-card-head{padding:.7rem .85rem;border-bottom:0}.or-img,.or-img-ph{width:52px;height:52px;border-radius:10px}.or-img-ph{font-size:1.25rem}.or-name{font-size:.95rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.or-meta{margin-top:.25rem}.or-card>.or-body{display:none}.or-row-actions{margin-left:auto;display:flex;align-items:center;gap:.5rem;flex-shrink:0}.or-details-btn{white-space:nowrap}.or-details-modal .modal-dialog{max-width:920px}.or-details-modal .modal-content{border:0;border-radius:18px;overflow:hidden}.or-details-modal .modal-body{padding:0}.or-details-modal .or-body{display:grid;grid-template-columns:1.15fr .85fr;gap:1rem;padding:1rem}.or-details-modal .or-actions{position:sticky;top:0;align-self:start}.or-details-modal .modal-header{border-bottom:1px solid #f1f5f9}.or-details-modal.fade .modal-dialog{transform:translateY(16px) scale(.98);transition:transform .2s ease-out}.or-details-modal.show .modal-dialog{transform:translateY(0) scale(1)}@media(max-width:768px){.or-card-head{padding:.65rem}.or-row-actions{align-self:flex-start}.or-details-btn{padding:.42rem .55rem;font-size:.76rem}.or-details-modal .modal-dialog{margin:.65rem}.or-details-modal .or-body{grid-template-columns:1fr;padding:.85rem}.or-details-modal .or-actions{position:static}}@media(max-width:430px){.or-card-head{gap:.6rem}.or-img,.or-img-ph{width:46px;height:46px}.or-card-head .small{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.or-meta .or-pill:nth-child(n+3){display:none}}
</style>

<div class="order-request-shell">
  <div class="or-header">
    <div>
      <h1 class="or-title"><i class="bi bi-lightning-charge me-1" style="color:#e91e63"></i>Kitchen Requests</h1>
      <p class="or-sub">Out-of-stock ready-made cakes, unavailable size requests, and special schedule requests. Rush items are sorted first.</p>
    </div>
    <a href="{{ route('seller.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
  </div>

  <div class="or-summary">
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
      $actionDeadline = !empty($req->action_expires_at)
        ? \Carbon\Carbon::parse($req->action_expires_at)
        : (!empty($req->expires_at) ? \Carbon\Carbon::parse($req->expires_at) : null);
      $notice = is_numeric($req->requested_notice_minutes) ? (int) $req->requested_notice_minutes : null;
      $noticeLabel = $notice === null ? 'Not computed' : ($notice >= 1440 ? floor($notice / 1440).' day'.(floor($notice / 1440) == 1 ? '' : 's') : max(0, floor($notice / 60)).' hr '.max(0, $notice % 60).' min');
    @endphp
    <div class="or-card">
      <div class="or-card-head">
        @if($req->image_path)
          <img class="or-img" src="{{ $req->image_path }}" alt="{{ $req->product_name }}">
        @else
          <div class="or-img-ph"><i class="bi bi-cake2"></i></div>
        @endif
        <div class="or-main">
          <h2 class="or-name">{{ $req->product_name ?: 'Cake request' }}</h2>
          <div class="small text-muted d-flex align-items-center gap-1">
            @if(!empty($req->customer_profile_photo))<img src="{{ $req->customer_profile_photo }}" alt="{{ $customerName }}" style="width:22px;height:22px;border-radius:50%;object-fit:cover" onerror="this.remove()">@else<span style="width:22px;height:22px;border-radius:50%;background:var(--primary);color:#fff;font-size:.6rem;display:inline-flex;align-items:center;justify-content:center">{{ strtoupper(substr($customerName, 0, 1)) }}</span>@endif
            <span>Request #{{ $req->id }} by {{ $customerName }}{{ $customerPhone ? ' - '.$customerPhone : '' }}</span>
          </div>
          <div class="or-meta">
            <span class="or-pill status" style="--or-bg:{{ $meta['bg'] }};--or-fg:{{ $meta['fg'] }}"><i class="bi {{ $meta['icon'] }}"></i>{{ $meta['label'] }}</span>
            <span class="or-pill"><i class="bi bi-bag"></i>{{ $req->quantity }} pc{{ (int)$req->quantity === 1 ? '' : 's' }}</span>
            @if(($req->request_reason ?? '') === 'size_out_of_stock')<span class="or-pill"><i class="bi bi-rulers"></i>Size request</span>@elseif(($req->request_reason ?? '') === 'out_of_stock')<span class="or-pill"><i class="bi bi-box-seam"></i>Request to bake</span>@endif
            @if($req->allow_similar_cake)<span class="or-pill"><i class="bi bi-shuffle"></i>Allows similar cake</span>@endif
          </div>
        </div>
      </div>

      <div class="or-body">
        <div class="or-detail-grid">
          <div class="or-detail"><span class="or-label">Preferred Schedule</span><div class="or-value">{{ $preferred ? $preferred->format('M d, Y g:i A') : trim($req->preferred_date.' '.$req->preferred_time) }}</div></div>
          @if($req->accepted_price)
            <div class="or-detail"><span class="or-label">Accepted Offer</span><div class="or-value">PHP {{ number_format((float)$req->accepted_price, 2) }} each</div></div>
          @endif
          @if($req->accepted_date)
            <div class="or-detail"><span class="or-label">Accepted Schedule</span><div class="or-value">{{ \Carbon\Carbon::parse($req->accepted_date.' '.($req->accepted_time ?: '00:00'))->format('M d, Y g:i A') }}</div></div>
          @endif
          @if($req->converted_order_id)
            <div class="or-detail"><span class="or-label">Order</span><div class="or-value">#{{ $req->converted_order_id }}</div></div>
          @endif
          <div class="or-detail"><span class="or-label">Action Deadline</span><div class="or-value countdown" data-countdown="{{ $actionDeadline ? $actionDeadline->toIso8601String() : ($preferred ? $preferred->toIso8601String() : '') }}">Checking...</div></div>
          <div class="or-detail"><span class="or-label">Seller Prep Setting</span><div class="or-value">{{ (int)($req->seller_prep_days_at_request ?? 0) }} day{{ (int)($req->seller_prep_days_at_request ?? 0) === 1 ? '' : 's' }}</div></div>
          <div class="or-detail"><span class="or-label">Customer Notice</span><div class="or-value">{{ $noticeLabel }}</div></div>
          @if($req->selected_size ?? null)
            <div class="or-detail"><span class="or-label">Requested Size</span><div class="or-value">{{ $req->selected_size }}</div></div>
          @endif
          <div class="or-detail"><span class="or-label">Flavor / Type</span><div class="or-value">{{ $req->flavor ?: 'Not specified' }}{{ $req->classification ? ' - '.$req->classification : '' }}</div></div>
          <div class="or-detail"><span class="or-label">Source</span><div class="or-value">{{ ucfirst(str_replace('_',' ', $req->source ?: 'shop')) }}</div></div>
          @if($req->customer_note)
            <div class="or-detail or-note"><span class="or-label">Customer Note</span><div class="or-value">{{ $req->customer_note }}</div></div>
          @endif
          @if($req->seller_response)
            <div class="or-detail or-note"><span class="or-label">Last Seller Response</span><div class="or-value">{{ $req->seller_response }}</div></div>
          @endif
          @if($req->customer_response ?? null)
            <div class="or-detail or-note"><span class="or-label">Customer Reply</span><div class="or-value">{{ $req->customer_response }}</div></div>
          @endif
        </div>

        <div class="or-actions">
          @if($req->status === 'pending')
            <details open>
              <summary><i class="bi bi-check2-circle me-1"></i>Accept kitchen request</summary>
              <form action="{{ route('seller.order_requests.update', $req->id) }}" method="POST" class="mt-2">
                @csrf
                <input type="hidden" name="action" value="accept">
                <div class="small rounded-3 p-2 mb-2" style="background:#ecfdf5;color:#166534"><i class="bi bi-lock-fill me-1"></i>Price is based on your published catalog price. The customer has 24 hours to review.</div>
                <div class="small rounded-3 p-2 mb-2" style="background:#f8fafc;color:#475569"><i class="bi bi-calendar-check me-1"></i>Customer schedule locked: {{ $req->preferred_date }} {{ substr((string)$req->preferred_time,0,5) }}</div>
                <textarea name="seller_response" class="form-control form-control-sm mb-2" placeholder="Short message for the customer"></textarea>
                <button class="btn-or-primary w-100" type="submit"><i class="bi bi-check-lg me-1"></i>Accept &amp; send to customer</button>
              </form>
            </details>
            @if((bool) $req->allow_similar_cake)
            <details>
              <summary><i class="bi bi-shuffle me-1"></i>Offer alternative cake</summary>
              <form action="{{ route('seller.order_requests.update', $req->id) }}" method="POST" class="mt-2">
                @csrf
                <input type="hidden" name="action" value="offer_alternative">
                <div class="small rounded-3 p-2 mb-2" style="background:#f8fafc;color:#475569"><i class="bi bi-calendar-check me-1"></i>The customer’s requested date and time stay unchanged.</div>
                <select name="alternative_product_id" class="form-select form-select-sm mb-2" required>
                  <option value="">Choose a catalog cake</option>
                  @foreach($alternativeProducts as $alt)
                    @if((string) $alt->id !== (string) $req->product_id)<option value="{{ $alt->id }}">{{ $alt->name }}</option>@endif
                  @endforeach
                </select>
                <textarea name="seller_response" class="form-control form-control-sm mb-2" minlength="5" maxlength="500" placeholder="Describe the similar cake" required></textarea>
                <button class="btn-or-soft w-100" type="submit">Offer fixed-price alternative</button>
              </form>
            </details>
            @endif
            <details>
              <summary><i class="bi bi-x-circle me-1"></i>Decline / ask details</summary>
              <form action="{{ route('seller.order_requests.update', $req->id) }}" method="POST" class="mt-2">
                @csrf
                <textarea name="seller_response" class="form-control form-control-sm mb-2" placeholder="Reason or question for the customer" required></textarea>
                <div class="d-flex gap-2"><button class="btn-or-soft flex-fill" name="action" value="needs_more_details" type="submit">Ask details</button><button class="btn btn-outline-danger flex-fill fw-bold" name="action" value="decline" type="submit">Decline</button></div>
              </form>
            </details>
          @else
            <div class="text-muted small">This request is waiting for the customer, converted, or already closed. Customer notifications were sent when it changed status.</div>
          @endif
        </div>
      </div>
    </div>
  @empty
    <div class="or-empty">
      <i class="bi bi-inbox" style="font-size:2rem;color:#cbd5e1"></i>
      <h2 class="h5 mt-2 mb-1">No requests here</h2>
      <p class="mb-0">New request-to-bake items will appear here for your review.</p>
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
    var secs = Math.floor(diff / 1000);
    var days = Math.floor(secs / 86400);
    var hours = Math.floor((secs % 86400) / 3600);
    var mins = Math.floor((secs % 3600) / 60);
    var rem = secs % 60;
    el.textContent = days > 0 ? days + 'd ' + hours + 'h ' + mins + 'm ' + rem + 's left' : hours + 'h ' + mins + 'm ' + rem + 's left';
    el.classList.toggle('urgent', secs < 4 * 3600);
  }
  function tick(){ document.querySelectorAll('[data-countdown]').forEach(render); }
  tick();
  setInterval(tick, 1000);
})();
</script>
<script>
(() => {
  document.querySelectorAll('.or-card').forEach((card, index) => {
    const details = card.querySelector(':scope > .or-body');
    const title = card.querySelector('.or-name')?.textContent?.trim() || 'Request details';
    if (!details) return;
    const modalId = `order-request-details-${index}`;
    const modal = document.createElement('div');
    modal.className = 'modal fade or-details-modal';
    modal.id = modalId;
    modal.tabIndex = -1;
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = `<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header py-2"><h2 class="modal-title h6 fw-bold mb-0"></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"></div></div></div>`;
    modal.querySelector('.modal-title').textContent = title;
    modal.querySelector('.modal-body').appendChild(details);
    document.body.appendChild(modal);
    const actions = document.createElement('div');
    actions.className = 'or-row-actions';
    actions.innerHTML = '<button type="button" class="btn btn-sm btn-outline-primary fw-semibold or-details-btn"><i class="bi bi-eye me-1"></i>See details</button>';
    actions.querySelector('button').addEventListener('click', () => bootstrap.Modal.getOrCreateInstance(modal).show());
    card.querySelector('.or-card-head').appendChild(actions);
  });
})();
</script>
<script>document.addEventListener('DOMContentLoaded',()=>{const reduced=matchMedia('(prefers-reduced-motion: reduce)').matches;function animate(d,open){const s=d.querySelector(':scope>summary');if(!s||d.dataset.animating)return;const start=d.offsetHeight;d.dataset.animating='1';if(open)d.open=true;const end=open?d.offsetHeight:s.offsetHeight;const done=()=>{if(!open)d.open=false;d.style.height='';delete d.dataset.animating};if(reduced||start===end)return done();d.style.height=start+'px';const a=d.animate([{height:start+'px',opacity:open?.98:1},{height:end+'px',opacity:1}],{duration:220,easing:'cubic-bezier(.22,1,.36,1)'});a.onfinish=done;a.oncancel=done}document.addEventListener('click',e=>{const s=e.target.closest('.or-actions>details>summary');if(!s)return;e.preventDefault();const d=s.parentElement,open=!d.open;if(open)d.closest('.or-actions').querySelectorAll('details[open]').forEach(x=>{if(x!==d)animate(x,false)});animate(d,open)})})</script>
<script>document.addEventListener('DOMContentLoaded',()=>{const catalog=@json($alternativeProducts);document.querySelectorAll('select[name="alternative_product_id"]').forEach(select=>{const btn=document.createElement('button');btn.type='button';btn.className='btn btn-outline-primary w-100 mb-2';btn.textContent='Choose a catalog cake';select.style.display='none';select.before(btn);const modal=document.createElement('div');modal.className='modal fade';modal.innerHTML='<div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Choose a catalog cake</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"></div></div></div></div>';const grid=modal.querySelector('.row');catalog.forEach(p=>{const col=document.createElement('div');col.className='col-12 col-sm-6 col-lg-4';const stock=Number(p.available_quantity||0);col.innerHTML='<button type="button" class="card h-100 w-100 text-start border-0 shadow-sm p-2"><img class="w-100 rounded" style="height:120px;object-fit:cover;background:#f8fafc"><strong class="d-block mt-2"></strong><small class="text-muted"></small><small class="d-block mt-1 fw-bold"></small></button>';const b=col.querySelector('button');col.querySelector('img').src=p.image_path||'';col.querySelector('strong').textContent=p.name;col.querySelectorAll('small')[0].textContent=[p.flavor,p.classification].filter(Boolean).join(' · ');col.querySelectorAll('small')[1].textContent=stock>0?'Ready stock: '+stock:'Made to order / stock check required';b.onclick=()=>{select.value=p.id;btn.textContent='Selected: '+p.name;bootstrap.Modal.getInstance(modal).hide()};grid.appendChild(col)});document.body.appendChild(modal);btn.onclick=()=>bootstrap.Modal.getOrCreateInstance(modal).show()})})</script><style>.modal .card{transition:transform .18s ease,box-shadow .18s ease}.modal .card:hover,.modal .card:focus{transform:translateY(-4px);box-shadow:0 12px 24px rgba(15,23,42,.14)!important}.modal .card:active{transform:scale(.98)}@media(prefers-reduced-motion:reduce){.modal .card{transition:none}}</style>
@endsection
