@extends('layouts.app')
@section('content')
<div>
  <h4 class="fw-bold mb-4"><i class="bi bi-fire me-2" style="color:var(--primary)"></i>Kitchen Tickets</h4>

  @if(session('msg'))<div class="alert alert-success border-0"><i class="bi bi-check-circle me-2"></i>{{ session('msg') }}</div>@endif
  @if(session('err'))<div class="alert alert-danger border-0"><i class="bi bi-exclamation-circle me-2"></i>{{ session('err') }}</div>@endif

  @php
    $ticketsPending   = $tickets->where('ticket_status', 'pending');
    $ticketsPreparing = $tickets->where('ticket_status', 'in_progress');
    $ticketsDone      = $tickets->where('ticket_status', 'done');
  @endphp

  {{-- ── STATUS TABS ─────────────────────────────────────────────── --}}
  <div class="d-flex gap-2 mb-4 flex-wrap">
    <button id="tab-pending" class="btn btn-sm fw-semibold kitchen-tab active-tab"
            onclick="showKitchenTab('pending')"
            style="border-radius:2rem;padding:.45rem 1.1rem">
      ⏳ Pending
      <span class="badge ms-1" id="cnt-pending"
            style="background:rgba(255,255,255,.35);color:inherit">{{ $ticketsPending->count() }}</span>
    </button>
    <button id="tab-in_progress" class="btn btn-sm fw-semibold kitchen-tab"
            onclick="showKitchenTab('in_progress')"
            style="border-radius:2rem;padding:.45rem 1.1rem">
      🍳 Preparing
      <span class="badge ms-1" id="cnt-in_progress"
            style="background:rgba(255,255,255,.35);color:inherit">{{ $ticketsPreparing->count() }}</span>
    </button>
    <button id="tab-done" class="btn btn-sm fw-semibold kitchen-tab"
            onclick="showKitchenTab('done')"
            style="border-radius:2rem;padding:.45rem 1.1rem">
      ✅ Completed
      <span class="badge ms-1" id="cnt-done"
            style="background:rgba(255,255,255,.35);color:inherit">{{ $ticketsDone->count() }}</span>
    </button>
  </div>

  <style>
    .kitchen-tab { background:#f3f4f6; color:#374151; border:1.5px solid #e5e7eb; }
    .kitchen-tab:hover { background:#e9ecef; color:#111; }
    .kitchen-tab.active-tab { background:var(--primary,#e91e8c); color:#fff; border-color:var(--primary,#e91e8c); }
    .kitchen-tab.active-tab .badge { background:rgba(255,255,255,.3)!important; color:#fff!important; }
  </style>

  {{-- ── PENDING PANE ─────────────────────────────────────────────── --}}
  <div id="pane-pending">
    @forelse($ticketsPending as $t)
      @php $isDelivery = $t->fulfillment_type === 'Delivery'; @endphp
      @include('seller._kitchen_ticket', ['t' => $t, 'isDelivery' => $isDelivery, 'riders' => $riders, 'customImages' => $customImages])
    @empty
      <div class="card text-center py-5">
        <i class="bi bi-hourglass" style="font-size:3rem;color:#ddd"></i>
        <p class="text-muted mt-3">No pending tickets.</p>
      </div>
    @endforelse
  </div>

  {{-- ── PREPARING PANE ───────────────────────────────────────────── --}}
  <div id="pane-in_progress" style="display:none">
    @forelse($ticketsPreparing as $t)
      @php $isDelivery = $t->fulfillment_type === 'Delivery'; @endphp
      @include('seller._kitchen_ticket', ['t' => $t, 'isDelivery' => $isDelivery, 'riders' => $riders, 'customImages' => $customImages])
    @empty
      <div class="card text-center py-5">
        <i class="bi bi-fire" style="font-size:3rem;color:#ddd"></i>
        <p class="text-muted mt-3">No tickets being prepared.</p>
      </div>
    @endforelse
  </div>

  {{-- ── COMPLETED PANE ───────────────────────────────────────────── --}}
  <div id="pane-done" style="display:none">
    @forelse($ticketsDone as $t)
      @php $isDelivery = $t->fulfillment_type === 'Delivery'; @endphp
      @include('seller._kitchen_ticket', ['t' => $t, 'isDelivery' => $isDelivery, 'riders' => $riders, 'customImages' => $customImages])
    @empty
      <div class="card text-center py-5">
        <i class="bi bi-check-all" style="font-size:3rem;color:#ddd"></i>
        <p class="text-muted mt-3">No completed tickets yet.</p>
      </div>
    @endforelse
  </div>
</div>

{{-- ── FULLSCREEN IMAGE VIEWER ─────────────────────────────── --}}
<div id="kitchenImgViewer"
     style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.94);
            flex-direction:column;align-items:center;justify-content:center"
     onclick="if(event.target===this)kitchenImgClose()">

  {{-- Header bar --}}
  <div style="position:absolute;top:0;left:0;right:0;padding:14px 20px;
              display:flex;align-items:center;justify-content:space-between;
              background:rgba(0,0,0,.5);z-index:2">
    <div id="kitchenImgTitle" style="color:#fff;font-size:clamp(.85rem,2vw,1rem);font-weight:600"></div>
    <button onclick="kitchenImgClose()"
            style="background:rgba(255,255,255,.15);border:none;color:#fff;
                   width:40px;height:40px;border-radius:50%;font-size:1.1rem;
                   cursor:pointer;display:flex;align-items:center;justify-content:center">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  {{-- Main image --}}
  <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;padding:70px 24px 80px">
    <img id="kitchenImgEl" src="" alt=""
         style="max-width:100%;max-height:100%;object-fit:contain;
                border-radius:.8rem;transition:transform .3s ease;cursor:zoom-in"
         onclick="kitchenImgToggleZoom()">
  </div>

  {{-- Zoom controls --}}
  <div style="position:absolute;bottom:20px;left:50%;transform:translateX(-50%);
              display:flex;gap:10px;align-items:center;z-index:2">
    <button onclick="kitchenImgZoom(-0.25)"
            style="background:rgba(255,255,255,.15);border:none;color:#fff;
                   width:40px;height:40px;border-radius:50%;font-size:1.2rem;cursor:pointer">−</button>
    <span id="kitchenImgZoomLbl" style="color:rgba(255,255,255,.7);font-size:.8rem;min-width:44px;text-align:center">100%</span>
    <button onclick="kitchenImgZoom(0.25)"
            style="background:rgba(255,255,255,.15);border:none;color:#fff;
                   width:40px;height:40px;border-radius:50%;font-size:1.2rem;cursor:pointer">+</button>
    <button onclick="kitchenImgReset()"
            style="background:rgba(255,255,255,.15);border:none;color:rgba(255,255,255,.7);
                   padding:0 14px;height:40px;border-radius:20px;font-size:.78rem;cursor:pointer">Reset</button>
  </div>

  {{-- Hint --}}
  <div style="position:absolute;bottom:68px;left:50%;transform:translateX(-50%);
              color:rgba(255,255,255,.35);font-size:.72rem">
    Click image to toggle zoom &bull; Scroll to zoom &bull; ESC to close
  </div>
</div>

<script>
// ── Tab switching ──────────────────────────────────────────────────────────
function showKitchenTab(status) {
  ['pending','in_progress','done'].forEach(function(s) {
    document.getElementById('pane-' + s).style.display = s === status ? '' : 'none';
    var btn = document.getElementById('tab-' + s);
    btn.classList.toggle('active-tab', s === status);
  });
}

// ── Image viewer ───────────────────────────────────────────────────────────
let _kScale = 1;

function kitchenImgOpen(src, title) {
  document.getElementById('kitchenImgEl').src = src;
  document.getElementById('kitchenImgTitle').textContent = title;
  kitchenImgReset();
  const v = document.getElementById('kitchenImgViewer');
  v.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function kitchenImgClose() {
  document.getElementById('kitchenImgViewer').style.display = 'none';
  document.body.style.overflow = '';
  kitchenImgReset();
}

function kitchenImgToggleZoom() {
  _kScale = _kScale >= 2 ? 1 : 2;
  _kitchenApplyZoom();
}

function kitchenImgZoom(delta) {
  _kScale = Math.min(4, Math.max(0.5, _kScale + delta));
  _kitchenApplyZoom();
}

function kitchenImgReset() {
  _kScale = 1;
  _kitchenApplyZoom();
}

function _kitchenApplyZoom() {
  const img = document.getElementById('kitchenImgEl');
  if (img) {
    img.style.transform = 'scale(' + _kScale + ')';
    img.style.cursor = _kScale > 1 ? 'zoom-out' : 'zoom-in';
  }
  const lbl = document.getElementById('kitchenImgZoomLbl');
  if (lbl) lbl.textContent = Math.round(_kScale * 100) + '%';
}

// Scroll to zoom
document.getElementById('kitchenImgViewer').addEventListener('wheel', function(e) {
  e.preventDefault();
  kitchenImgZoom(e.deltaY < 0 ? 0.15 : -0.15);
}, { passive: false });

// ESC to close
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') kitchenImgClose();
});
</script>

@endsection
