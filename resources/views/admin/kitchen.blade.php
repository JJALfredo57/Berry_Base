@extends('layouts.app')
@section('content')
<div>
  <h4 class="fw-bold mb-4"><i class="bi bi-fire me-2" style="color:var(--primary)"></i>Kitchen Tickets</h4>

  @if(session('msg'))<div class="alert alert-success border-0"><i class="bi bi-check-circle me-2"></i>{{ session('msg') }}</div>@endif
  @if(session('err'))<div class="alert alert-danger border-0"><i class="bi bi-exclamation-circle me-2"></i>{{ session('err') }}</div>@endif

  @forelse($tickets as $t)
  @php $isDelivery = $t->fulfillment_type === 'Delivery'; @endphp
  <div class="card mb-3">
    <div class="card-body p-4">
      <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
          <h6 class="fw-bold mb-0">Order #{{ $t->order_id }} — {{ $t->product_name }}</h6>
          <div class="text-muted small">
            {{ $t->fullname }}
            &bull;
            <span class="badge {{ $isDelivery ? 'bg-info text-dark' : '' }}" style="{{ !$isDelivery ? 'background:#ede9fe;color:#5b21b6' : '' }}">
              {{ $isDelivery ? '🚴 Delivery' : '🏪 Pickup' }}
            </span>
            &bull; {{ \Carbon\Carbon::parse($t->sent_at)->format('M d, Y g:i A') }}
          </div>
          {{-- Rider assignment status --}}
          @if($isDelivery)
          <div class="mt-1">
            @if($t->rider_id)
              @php $assignedRider = $riders->firstWhere('id', $t->rider_id); @endphp
              <span class="badge bg-success" style="font-size:clamp(.66rem,1.3vw,.7rem)">
                <i class="bi bi-bicycle me-1"></i>Rider: {{ $assignedRider->name ?? 'Assigned' }}
              </span>
            @else
              <span class="badge bg-warning text-dark" style="font-size:clamp(.66rem,1.3vw,.7rem)">
                <i class="bi bi-exclamation-triangle me-1"></i>No rider assigned yet
              </span>
            @endif
          </div>
          @endif
        </div>

        <div class="d-flex gap-2 align-items-center flex-wrap">
          <span class="badge {{ $t->ticket_status==='done' ? 'bg-success' : ($t->ticket_status==='in_progress' ? 'bg-warning text-dark' : 'bg-secondary') }}">
            {{ $t->ticket_status === 'in_progress' ? '🍳 In Progress' : ($t->ticket_status === 'done' ? '✅ Done' : '⏳ Pending') }}
          </span>

          @php
            $kitchenNext = ['pending'=>'in_progress','in_progress'=>'done','done'=>null];
            $nextKitchen = $kitchenNext[$t->ticket_status] ?? null;
            $kitchenBtnLabel = ['in_progress'=>'🍳 Start Preparing','done'=>'✅ Mark Done'];
            $kitchenBtnClass = ['in_progress'=>'btn-warning text-dark','done'=>'btn-success'];
          @endphp

          @if($nextKitchen)
            @if($nextKitchen === 'done' && $isDelivery && !$t->rider_id)
            {{-- BLOCK: Delivery + no rider → show assign modal button --}}
            <button type="button" class="btn btn-sm btn-success"
                    data-bs-toggle="modal" data-bs-target="#assignRiderModal{{ $t->order_id }}">
              ✅ Mark Done
            </button>
            @else
            {{-- Normal: Pickup OR Delivery with rider assigned --}}
            <form action="{{ route('admin.kitchen.update', $t->id) }}" method="POST" class="d-inline">
              @csrf
              <input type="hidden" name="status" value="{{ $nextKitchen }}">
              <button type="submit" class="btn btn-sm {{ $kitchenBtnClass[$nextKitchen] ?? 'btn-primary' }}"
                      data-cs-confirm="{{ $kitchenBtnLabel[$nextKitchen] }} for Order #{{ $t->order_id }}?"
                      data-cs-title="Kitchen Update"
                      data-cs-ok="{{ $kitchenBtnLabel[$nextKitchen] }}"
                      data-cs-icon="bi-fire"
                      data-cs-icon-bg="#fef3c7"
                      data-cs-icon-color="#d97706">
                {{ $kitchenBtnLabel[$nextKitchen] }}
              </button>
            </form>
            @endif
          @else
          <span class="badge bg-success px-3 py-2"><i class="bi bi-check-all me-1"></i>Completed</span>
          @endif
        </div>
      </div>

      {{-- ── IMAGES ────────────────────────────────────────────────── --}}
      @php
        $refImgs = $customImages[$t->order_id] ?? [];
        $isCustom = count($refImgs) > 0;
      @endphp

      <div class="mb-3">
        <div class="d-flex flex-wrap gap-2 align-items-start mb-2">

          {{-- Product image — hide if custom order (shows default placeholder) --}}
          @if($t->product_image && !$isCustom)
          <div>
            <div class="text-muted small fw-semibold mb-1">
              <i class="bi bi-image me-1"></i>Product Photo
            </div>
            <img src="{{ $t->product_image }}"
                 alt="{{ $t->product_name }}"
                 onclick="kitchenImgOpen(this.src, '{{ addslashes($t->product_name) }}')"
                 onerror="this.parentElement.style.display='none'"
                 style="width:90px;height:90px;object-fit:cover;border-radius:.8rem;cursor:zoom-in;border:2px solid #f3f4f6;transition:transform .2s ease;box-shadow:0 2px 8px rgba(0,0,0,.1)"
                 onmouseover="this.style.transform='scale(1.06)'"
                 onmouseout="this.style.transform='scale(1)'">
          </div>
          @endif

          {{-- Custom order reference images --}}
          @if($isCustom)
          <div>
            <div class="text-muted small fw-semibold mb-1">
              <i class="bi bi-images me-1" style="color:var(--primary)"></i>
              Customer Reference Photos ({{ count($refImgs) }})
            </div>
            <div class="d-flex flex-wrap gap-2">
              @foreach($refImgs as $idx => $img)
              <img src="{{ $img }}"
                   alt="Reference {{ $idx + 1 }}"
                   onclick="kitchenImgOpen('{{ $img }}', 'Reference Photo {{ $idx + 1 }}')"
                   onerror="this.style.display='none'"
                   style="width:90px;height:90px;object-fit:cover;border-radius:.8rem;cursor:zoom-in;border:2px solid #fce7f3;transition:transform .2s ease;box-shadow:0 2px 8px rgba(233,30,99,.12)"
                   onmouseover="this.style.transform='scale(1.06)'"
                   onmouseout="this.style.transform='scale(1)'">
              @endforeach
            </div>
          </div>
          @elseif(!$t->product_image)
          {{-- No image at all --}}
          <div class="text-muted small"><i class="bi bi-image me-1"></i>No photo available</div>
          @endif

        </div>
      </div>

      <pre class="mb-0 p-3 rounded small" style="background:#f8f9fa;white-space:pre-wrap;font-family:'Courier New',monospace">{{ $t->instructions }}</pre>
    </div>
  </div>

  {{-- Assign Rider Modal (only for Delivery + no rider + Mark Done) --}}
  @if($isDelivery && !$t->rider_id)
  <div class="modal fade" id="assignRiderModal{{ $t->order_id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
      <div class="modal-content border-0" style="border-radius:1.2rem;overflow:hidden">
        <div class="modal-header border-0 pt-4 px-4">
          <h5 class="modal-title fw-bold">🚴 Assign Rider — Order #{{ $t->order_id }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body px-4 pt-0 pb-3">
          <div class="alert border-0 py-2 mb-3" style="background:#fef9c3">
            <i class="bi bi-exclamation-triangle me-1" style="color:#854d0e"></i>
            <span style="color:#854d0e;font-size:clamp(.78rem,1.6vw,.85rem)">
              This is a <strong>Delivery order</strong>. Please assign a rider before marking as done.
            </span>
          </div>
          <form action="{{ route('admin.kitchen.assign_rider', $t->order_id) }}" method="POST">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold small">Select Rider <span class="text-danger">*</span></label>
              <select class="form-select" name="rider_id" required>
                <option value="">— Choose a rider —</option>
                @foreach($riders as $r)
                <option value="{{ $r->id }}">{{ $r->name }}{{ $r->nickname ? ' ('.$r->nickname.')' : '' }} — {{ $r->vehicle_type }}</option>
                @endforeach
              </select>
              @if($riders->isEmpty())
              <div class="form-text text-danger">No active riders. <a href="{{ route('admin.riders.index') }}">Add riders here →</a></div>
              @endif
            </div>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-success flex-fill fw-semibold">
                <i class="bi bi-bicycle me-1"></i>Assign & Mark Done
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  @endif

  @empty
  <div class="card text-center py-5">
    <i class="bi bi-fire" style="font-size:3rem;color:#ddd"></i>
    <p class="text-muted mt-3">No kitchen tickets yet.</p>
  </div>
  @endforelse
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
