@extends('layouts.app')

@section('content')
@php
  $tabs = [
    'active' => ['label' => 'Active', 'icon' => 'bi-activity'],
    'offers' => ['label' => 'Offers', 'icon' => 'bi-check2-circle'],
    'closed' => ['label' => 'Closed', 'icon' => 'bi-archive'],
    'all' => ['label' => 'All', 'icon' => 'bi-grid'],
  ];
@endphp

<style>
  .request-page { max-width: 1180px; margin: 0 auto; }
  .request-hero {
    background: linear-gradient(135deg, rgba(236, 37, 99, .12), rgba(255, 166, 77, .14));
    border: 1px solid rgba(236, 37, 99, .14);
    border-radius: 18px;
    padding: 1.1rem;
  }
  .request-tabs { display:flex; gap:.55rem; overflow-x:auto; padding:.2rem .05rem .45rem; scrollbar-width: thin; }
  .request-tab {
    display:inline-flex; align-items:center; gap:.45rem; white-space:nowrap;
    padding:.65rem .9rem; border-radius:999px; text-decoration:none; font-weight:700;
    color:#6b7280; border:1px solid #e5e7eb; background:#fff;
    box-shadow:0 8px 20px rgba(15,23,42,.05);
  }
  .request-tab.active { color:#fff; background:var(--primary); border-color:var(--primary); }
  .request-tab .count { font-size:.75rem; padding:.12rem .45rem; border-radius:999px; background:rgba(15,23,42,.08); }
  .request-tab.active .count { background:rgba(255,255,255,.22); }
  .request-card {
    background:#fff; border:1px solid #edf0f4; border-radius:16px; overflow:hidden;
    box-shadow:0 10px 26px rgba(15,23,42,.07); transition:transform .18s ease, box-shadow .18s ease;
    animation:requestCardIn .32s ease both;
  }
  .request-card:hover { transform:translateY(-2px); box-shadow:0 16px 34px rgba(15,23,42,.1); }
  .request-img { width:92px; height:92px; object-fit:cover; border-radius:14px; background:#f8fafc; flex:0 0 auto; }
  .request-status { display:inline-flex; align-items:center; gap:.35rem; border-radius:999px; padding:.32rem .62rem; font-size:.76rem; font-weight:800; }
  .request-status.success { color:#047857; background:#d1fae5; }
  .request-status.primary { color:#1d4ed8; background:#dbeafe; }
  .request-status.warning { color:#92400e; background:#fef3c7; }
  .request-status.danger { color:#b91c1c; background:#fee2e2; }
  .request-status.muted { color:#64748b; background:#f1f5f9; }
  .request-status.pending { color:#9f1239; background:#ffe4ec; }
  .request-chip { display:inline-flex; align-items:center; gap:.35rem; border-radius:999px; padding:.28rem .58rem; background:#f8fafc; color:#64748b; font-size:.78rem; font-weight:700; border:1px solid #e5e7eb; }
  .request-chip.rush { color:#be123c; background:#fff1f2; border-color:#fecdd3; }
  .request-note { background:#f8fafc; border:1px solid #edf0f4; border-radius:12px; padding:.7rem .8rem; color:#64748b; font-size:.86rem; }
  .request-price { color:var(--primary); font-size:1.25rem; font-weight:900; }
  .request-actions { display:flex; gap:.55rem; flex-wrap:wrap; justify-content:flex-end; }
  .request-empty { border:1px dashed #e5e7eb; border-radius:18px; padding:3rem 1rem; background:#fff; }
  @keyframes requestCardIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
  @media (max-width: 767.98px) {
    .request-page { padding-left:.75rem; padding-right:.75rem; }
    .request-card .p-sm-4 { padding:1rem !important; }
    .request-card .d-flex.gap-3 { gap:.8rem !important; }
    .request-card .row { --bs-gutter-x:.6rem; }
  }
  @media (max-width: 575.98px) {
    .request-hero { border-radius:14px; padding:1rem; }
    .request-card { border-radius:14px; }
    .request-img { width:76px; height:76px; border-radius:12px; }
    .request-actions { justify-content:stretch; }
    .request-actions .btn { width:100%; min-height:44px; }
    .request-status { max-width:100%; white-space:normal; }
    .request-note, .request-note * { overflow-wrap:anywhere; }
  }
</style>

<div class="container-fluid py-4 request-page">
  <div class="request-hero mb-3">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
      <div>
        <div class="text-uppercase fw-bold small mb-1" style="color:var(--primary);letter-spacing:.04em">My Requests</div>
        <h4 class="fw-bold mb-1">Kitchen requests and seller offers</h4>
        <p class="text-muted mb-0 small">Track request-to-bake items here, including rush kitchen requests, seller accepted offers, declined requests, and requests converted to orders.</p>
      </div>
      @if(($counts['offers'] ?? 0) > 0)
        <a href="{{ route('customer.order_requests.index', ['filter' => 'offers']) }}" class="btn btn-primary fw-semibold">
          <span class="position-relative d-inline-block"><i class="bi bi-check2-circle me-1"></i><span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle"><span class="visually-hidden">Needs action</span></span></span> Review {{ $counts['offers'] }} update{{ ($counts['offers'] ?? 0) > 1 ? 's' : '' }}
        </a>
      @endif
    </div>
  </div>

  <div class="request-tabs mb-3">
    @foreach($tabs as $key => $tab)
      <a href="{{ route('customer.order_requests.index', ['filter' => $key]) }}" class="request-tab {{ $filter === $key ? 'active' : '' }}">
        <i class="bi {{ $tab['icon'] }}"></i>{{ $tab['label'] }} <span class="count">{{ $counts[$key] ?? 0 }}</span>
      </a>
    @endforeach
  </div>

  @forelse($requests as $requestItem)
    @php
      $meta = $requestItem->status_meta;
      $statusTone = $meta['tone'] ?? 'pending';
      $acceptedPrice = $requestItem->accepted_price ?? $requestItem->seller_price ?? null;
      $requestedPrice = $requestItem->requested_price ?? $requestItem->base_price ?? null;
    @endphp
    <div class="request-card mb-3">
      <div class="p-3 p-sm-4">
        <div class="d-flex gap-3 align-items-start">
          <img class="request-img" src="{{ $requestItem->display_image ?: 'https://placehold.co/160x160/f8fafc/94a3b8?text=Cake' }}" alt="{{ $requestItem->display_name }}" onerror="this.src='https://placehold.co/160x160/f8fafc/94a3b8?text=Cake'">
          <div class="flex-grow-1 min-width-0">
            <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
              <div>
                <h6 class="fw-bold mb-1">{{ $requestItem->display_name }}</h6>
                <div class="text-muted small">
                  <i class="bi bi-shop me-1"></i>{{ $requestItem->shop_name ?? 'Cake shop' }}
                  <span class="mx-1">.</span>
                  Requested {{ \Carbon\Carbon::parse($requestItem->created_at)->format('M d, Y') }}
                </div>
              </div>
              <span class="request-status {{ $statusTone }}">
                <i class="bi {{ in_array($requestItem->status, ['accepted','customer_accepted','converted'], true) ? 'bi-check-circle-fill' : ($statusTone === 'danger' ? 'bi-x-circle-fill' : 'bi-clock-fill') }}"></i>
                {{ $meta['label'] ?? 'Waiting for seller' }}
              </span>
            </div>

            <div class="d-flex gap-2 flex-wrap my-3">
              @if($requestItem->is_rush)
                <span class="request-chip rush"><i class="bi bi-lightning-charge-fill"></i>Rush request</span>
              @endif
              @if(($requestItem->request_reason ?? '') === 'size_out_of_stock')
                <span class="request-chip"><i class="bi bi-rulers"></i>Size request</span>
              @elseif(($requestItem->request_reason ?? '') === 'out_of_stock')
                <span class="request-chip"><i class="bi bi-box-seam"></i>Request to bake</span>
              @endif
              @if($requestItem->product_flavor)
                <span class="request-chip"><i class="bi bi-droplet"></i>{{ $requestItem->product_flavor }}</span>
              @endif
              @if($requestItem->selected_size ?? $requestItem->size_label ?? $requestItem->size ?? null)
                <span class="request-chip"><i class="bi bi-rulers"></i>{{ $requestItem->selected_size ?? $requestItem->size_label ?? $requestItem->size }}</span>
              @endif
              @if($requestItem->preferred_label)
                <span class="request-chip"><i class="bi bi-calendar-event"></i>{{ $requestItem->preferred_label }}</span>
              @endif
              @php $responseDeadline = $requestItem->action_expires_at ?? $requestItem->expires_at ?? null; @endphp
              @if(in_array($requestItem->status, ['pending','accepted','schedule_suggested','alternative_offered','needs_more_details'], true) && $responseDeadline)
                <span class="request-chip rush request-action-countdown" data-request-deadline="{{ \Carbon\Carbon::parse($responseDeadline)->toIso8601String() }}"><i class="bi bi-hourglass-split"></i> Response due: checking...</span>
              @endif
            </div>

            <div class="row g-2 align-items-stretch">
              <div class="col-md-7">
                <div class="request-note h-100">
                  <div class="fw-semibold text-dark mb-1">{{ $meta['hint'] ?? 'Request status updated.' }}</div>
                  @if(!empty($requestItem->seller_response))
                    <div><span class="fw-semibold">Seller note:</span> {{ $requestItem->seller_response }}</div>
                  @elseif(!empty($requestItem->customer_note))
                    <div><span class="fw-semibold">Your note:</span> {{ $requestItem->customer_note }}</div>
                  @else
                    <div>No extra note yet.</div>
                  @endif
                  @if(!empty($requestItem->alternative_product_name))
                    <div class="mt-1"><span class="fw-semibold">Alternative:</span> {{ $requestItem->alternative_product_name }}</div>
                  @endif
                  @if(!empty($requestItem->customer_response))
                    <div class="mt-1"><span class="fw-semibold">Your reply:</span> {{ $requestItem->customer_response }}</div>
                  @endif
                </div>
              </div>
              <div class="col-md-5">
                <div class="request-note h-100 bg-white">
                  @if($acceptedPrice)
                    <div class="text-muted small fw-semibold">Seller offer</div>
                    <div class="request-price">PHP {{ number_format((float) $acceptedPrice, 2) }}</div>
                    @if($requestItem->accepted_label)
                      <div class="small text-muted"><i class="bi bi-calendar-check me-1"></i>{{ $requestItem->accepted_label }}</div>
                    @endif
                  @elseif($requestedPrice)
                    <div class="text-muted small fw-semibold">Requested price</div>
                    <div class="request-price">PHP {{ number_format((float) $requestedPrice, 2) }}</div>
                  @else
                    <div class="text-muted small">Seller price will appear here after review.</div>
                  @endif
                </div>
              </div>
            </div>

            <div class="request-actions mt-3">
              @if(in_array($requestItem->status, ['accepted', 'customer_accepted'], true))
                <a href="{{ route('customer.order_requests.show', $requestItem->id) }}" class="btn btn-primary fw-semibold">
                  <i class="bi bi-box-arrow-up-right me-1"></i>{{ $requestItem->status === 'customer_accepted' ? 'Continue Checkout' : 'View Offer' }}
                </a>
              @elseif($requestItem->status === 'converted' && $requestItem->converted_track_code)
                <a href="{{ route('track.order', $requestItem->converted_track_code) }}" class="btn btn-outline-primary fw-semibold">
                  <i class="bi bi-truck me-1"></i>Track Order
                </a>
              @elseif($requestItem->status === 'pending')
                <button class="btn btn-light fw-semibold" disabled><i class="bi bi-hourglass-split me-1"></i>Waiting for seller</button>
              @elseif(in_array($requestItem->status, ['schedule_suggested', 'alternative_offered'], true))
                <button type="button" class="btn btn-primary fw-semibold request-review-trigger" onclick='openRequestReview(@json([
                  "title" => $requestItem->display_name,
                  "seller" => $requestItem->shop_name ?? "Cake shop",
                  "status" => $requestItem->status,
                  "schedule" => $requestItem->status === "schedule_suggested" ? trim(($requestItem->suggested_date ?? "") . " " . ($requestItem->suggested_time ?? "")) : $requestItem->preferred_label,
                  "note" => $requestItem->seller_response ?? "Review this seller update.",
                  "deadline" => $responseDeadline ? \Carbon\Carbon::parse($responseDeadline)->toIso8601String() : "",
                  "acceptUrl" => route("customer.order_requests.respond", $requestItem->id),
                  "acceptAction" => $requestItem->status === "schedule_suggested" ? "accept_schedule" : "accept_alternative",
                  "declineUrl" => route("customer.order_requests.decline", $requestItem->id),
                ])'>
                  <i class="bi bi-arrow-repeat me-1"></i>Review seller update
                </button>
              @elseif($requestItem->status === 'needs_more_details')
                <a href="{{ route('customer.order_requests.show', $requestItem->id) }}" class="btn btn-primary fw-semibold">
                  <i class="bi bi-chat-square-text me-1"></i>Reply to seller
                </a>
              @else
                <a href="{{ route('customer.catalog') }}" class="btn btn-outline-primary fw-semibold">
                  <i class="bi bi-shop me-1"></i>Browse Cakes
                </a>
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>
  @empty
    <div class="request-empty text-center">
      <i class="bi bi-send" style="font-size:2.5rem;color:#cbd5e1"></i>
      <h6 class="fw-bold mt-3 mb-1">No requests here yet</h6>
      <p class="text-muted small mb-3">Accepted seller offers, rush kitchen requests, and pending request-to-bake items will show in this account page.</p>
      <a href="{{ route('customer.catalog') }}" class="btn btn-primary fw-semibold"><i class="bi bi-shop me-1"></i>Browse Cakes</a>
    </div>
  @endforelse

  @if(method_exists($requests, 'links'))
    <div class="mt-3">{{ $requests->links() }}</div>
  @endif
</div>
<div class="request-review-modal" id="requestReviewModal" aria-hidden="true" onclick="if(event.target===this) closeRequestReview()">
  <section class="request-review-dialog" role="dialog" aria-modal="true" aria-labelledby="requestReviewTitle" tabindex="-1">
    <header><div><div class="small text-muted fw-semibold">SELLER UPDATE</div><h5 id="requestReviewTitle" class="mb-0 fw-bold"></h5></div><button type="button" class="btn-close" aria-label="Close" onclick="closeRequestReview()"></button></header>
    <div class="request-review-body">
      <div class="request-review-meta" id="requestReviewSeller"></div>
      <div class="request-review-schedule"><span>Proposed schedule</span><strong id="requestReviewSchedule"></strong></div>
      <div class="request-review-note" id="requestReviewNote"></div>
      <div class="request-review-deadline" id="requestReviewDeadline" role="status" aria-live="polite"></div>
      <div class="request-review-error" id="requestReviewError" role="alert"></div>
    </div>
    <footer>
      <form id="requestReviewAcceptForm" method="POST" data-prevent-double-submit><input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="action" id="requestReviewAction"><button class="btn btn-primary w-100 fw-semibold" type="submit">Accept update</button></form>
      <button class="btn btn-outline-danger w-100 fw-semibold" type="button" onclick="document.getElementById('requestReviewDeclineBox').classList.toggle('show')">Decline / Cancel</button>
      <form id="requestReviewDeclineBox" class="request-review-decline" method="POST" data-prevent-double-submit><input type="hidden" name="_token" value="{{ csrf_token() }}"><textarea name="customer_decision_note" class="form-control mb-2" rows="2" maxlength="500" placeholder="Optional reason for declining"></textarea><button class="btn btn-danger w-100 fw-semibold" type="submit">Confirm decline</button></form>
    </footer>
  </section>
</div>
<style>
  .request-review-modal{position:fixed;inset:0;z-index:1100;display:none;align-items:center;justify-content:center;padding:14px;background:rgba(15,23,42,.58)}
  .request-review-modal.is-open{display:flex;animation:requestReviewFade .18s ease}.request-review-dialog{width:min(520px,100%);max-height:calc(100dvh - 28px);display:flex;flex-direction:column;background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 28px 84px rgba(15,23,42,.3);animation:requestReviewIn .22s ease}.request-review-dialog header,.request-review-dialog footer{padding:16px;flex:0 0 auto}.request-review-dialog header{display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #eef2f7}.request-review-body{padding:16px;overflow:auto}.request-review-meta{font-size:.82rem;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.request-review-schedule,.request-review-note,.request-review-deadline{border-radius:12px;padding:12px;margin-top:12px}.request-review-schedule{background:#eff6ff;color:#1e3a8a}.request-review-schedule span{display:block;font-size:.72rem;font-weight:800;text-transform:uppercase}.request-review-schedule strong{display:block;margin-top:3px}.request-review-note{background:#f8fafc;color:#334155;white-space:pre-wrap}.request-review-deadline{background:#fff7ed;color:#9a3412;font-weight:800}.request-review-error{display:none;color:#b91c1c;font-size:.82rem;font-weight:700;margin-top:10px}.request-review-dialog footer{display:grid;gap:8px;border-top:1px solid #eef2f7}.request-review-decline{display:none}.request-review-decline.show{display:block}@keyframes requestReviewFade{from{opacity:0}to{opacity:1}}@keyframes requestReviewIn{from{opacity:0;transform:translateY(12px) scale(.97)}to{opacity:1;transform:none}}@media(max-width:575px){.request-review-modal{padding:8px}.request-review-dialog{max-height:calc(100dvh - 16px);border-radius:16px}.request-review-dialog header,.request-review-dialog footer,.request-review-body{padding:14px}}
</style>
<script>
let requestReviewInvoker = null;
let requestReviewTimer = null;
function openRequestReview(data) {
  const modal = document.getElementById('requestReviewModal');
  if (!modal) return;
  requestReviewInvoker = document.activeElement;
  document.getElementById('requestReviewTitle').textContent = data.title || 'Kitchen request';
  document.getElementById('requestReviewSeller').textContent = data.seller || '';
  document.getElementById('requestReviewSchedule').textContent = data.schedule || 'Schedule to confirm';
  document.getElementById('requestReviewNote').textContent = data.note || '';
  document.getElementById('requestReviewAcceptForm').action = data.acceptUrl || '';
  document.getElementById('requestReviewAction').value = data.acceptAction || '';
  document.getElementById('requestReviewDeclineBox').action = data.declineUrl || '';
  document.getElementById('requestReviewDeclineBox').classList.remove('show');
  document.getElementById('requestReviewError').style.display = 'none';
  const deadline = document.getElementById('requestReviewDeadline');
  const target = new Date(data.deadline || '').getTime();
  const tick = () => { const seconds=Math.floor((target-Date.now())/1000); deadline.textContent = seconds > 0 ? 'Response time: ' + Math.floor(seconds/3600) + 'h ' + Math.floor((seconds%3600)/60) + 'm ' + (seconds%60) + 's' : 'Response window expired'; };
  clearInterval(requestReviewTimer); tick(); requestReviewTimer = setInterval(tick, 1000);
  modal.classList.add('is-open'); modal.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden';
  modal.querySelector('.request-review-dialog').focus();
}
function closeRequestReview() { const modal=document.getElementById('requestReviewModal'); if(!modal)return; modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');document.body.style.overflow='';clearInterval(requestReviewTimer);requestReviewInvoker?.focus(); }
document.addEventListener('keydown', e => { if(e.key==='Escape' && document.getElementById('requestReviewModal')?.classList.contains('is-open')) closeRequestReview(); });
(() => {
  const render = el => {
    const due = new Date(el.dataset.requestDeadline || '').getTime();
    const diff = due - Date.now();
    if (!Number.isFinite(due) || diff <= 0) { el.textContent = 'Response expired'; return; }
    const secs = Math.floor(diff / 1000);
    const hours = Math.floor(secs / 3600);
    const mins = Math.floor((secs % 3600) / 60);
    const seconds = secs % 60;
    el.textContent = 'Response due: ' + (hours > 0 ? hours + 'h ' : '') + mins + 'm ' + seconds + 's';
    el.classList.toggle('rush', diff < 4 * 3600000);
  };
  const tick = () => document.querySelectorAll('[data-request-deadline]').forEach(render);
  tick(); setInterval(tick, 1000);
})();
</script>
@endsection
