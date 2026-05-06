@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
/* ── Layout ─────────────────────────────────────────── */
.dz-wrap{display:grid;grid-template-columns:1fr 360px;gap:1.25rem;align-items:start}
@media(max-width:960px){.dz-wrap{grid-template-columns:1fr}}
#dz-map{height:480px;border-radius:0 0 14px 14px;z-index:0}
.map-card{background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 2px 14px rgba(0,0,0,.09)}
.map-toolbar{display:flex;align-items:center;gap:.45rem;padding:.75rem 1rem;background:#fff;border-bottom:1px solid #f0f0f0;flex-wrap:wrap}
.map-toolbar h6{margin:0;font-weight:700;font-size:.95rem;flex:1;min-width:120px}

/* ── Stats ──────────────────────────────────────────── */
.dz-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:.75rem;margin-bottom:1.25rem}
@media(max-width:640px){.dz-stats{grid-template-columns:repeat(2,1fr)}}
.dz-stat{background:#fff;border-radius:12px;padding:.85rem 1rem;box-shadow:0 2px 10px rgba(0,0,0,.07);text-align:center}
.dz-stat .val{font-size:1.6rem;font-weight:800;line-height:1}
.dz-stat .lbl{font-size:.68rem;color:#888;margin-top:4px;text-transform:uppercase;letter-spacing:.05em}

/* ── Panels ─────────────────────────────────────────── */
.side-panel{background:#fff;border-radius:16px;padding:1.1rem 1.2rem;box-shadow:0 2px 12px rgba(0,0,0,.08);margin-top:1rem}
.panel-head{display:flex;align-items:center;justify-content:space-between;cursor:pointer;user-select:none}
.panel-head h6{margin:0;font-weight:700;font-size:.93rem}

/* ── Zone list ──────────────────────────────────────── */
.zone-list{display:flex;flex-direction:column;gap:.6rem;max-height:560px;overflow-y:auto;padding-right:2px}
.zone-list::-webkit-scrollbar{width:4px}
.zone-list::-webkit-scrollbar-thumb{background:#e0e0e0;border-radius:4px}
.zone-card{background:#fff;border-radius:12px;padding:.85rem 1rem;box-shadow:0 2px 8px rgba(0,0,0,.06);border-left:4px solid var(--primary,#e91e8c);transition:.15s;cursor:pointer}
.zone-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.13);transform:translateY(-1px)}
.zone-card.inactive{opacity:.52;border-left-color:#ccc}
.zc-top{display:flex;align-items:center;gap:.5rem}
.zname{font-weight:700;font-size:.91rem;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.zaddr{font-size:.74rem;color:#666;margin-top:.25rem;line-height:1.35}
.zbtns{display:flex;gap:.3rem;margin-top:.55rem;flex-wrap:wrap}
.btn-xs{font-size:.72rem !important;padding:2px 8px !important;border-radius:6px !important}

/* ── Zone search ────────────────────────────────────── */
.zone-search{position:relative;margin-bottom:.7rem}
.zone-search input{padding-left:2.2rem;border-radius:10px}
.zone-search .si{position:absolute;left:.65rem;top:50%;transform:translateY(-50%);color:#bbb;font-size:.9rem}

/* ── Hint bars ──────────────────────────────────────── */
.hint-bar{padding:.55rem 1rem;font-size:.8rem;border-bottom:1px solid transparent;display:none;align-items:center;gap:.5rem;flex-wrap:wrap}
.hint-bar.show{display:flex}
.hint-bar.yellow{background:#fff8e1;border-color:#ffe082;color:#b45309}
.hint-bar.indigo{background:#eef2ff;border-color:#c7d2fe;color:#4338ca}
.hint-bar.green{background:linear-gradient(135deg,#f0fdf4 60%,#ecfdf5);border-color:#86efac;color:#15803d}

/* ── Edit modal map ─────────────────────────────────── */
.modal-map-wrap{border-radius:10px;overflow:hidden}
#edit-map{height:220px}
.pin-hint{font-size:.75rem;color:#6366f1;background:#eef2ff;border-radius:8px;padding:.45rem .8rem;display:none}
.pin-hint.show{display:block}

/* ── Route Coverage Panel ───────────────────────────── */
.route-panel{background:#fff;border-radius:16px;padding:1.3rem 1.3rem 1rem;box-shadow:0 2px 16px rgba(99,102,241,.12);border:1.5px solid #e0e7ff;margin-top:1rem}
.route-panel-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:.9rem}
.route-panel-title{display:flex;align-items:center;gap:.6rem;font-weight:700;font-size:.95rem}
.route-icon-wrap{background:#eef2ff;width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}

/* ── Progress bar ───────────────────────────────────── */
.detect-progress{height:6px;background:#e0e7ff;border-radius:99px;overflow:hidden;margin:.5rem 0 .65rem}
.detect-bar{height:100%;background:linear-gradient(90deg,#6366f1,#818cf8,#a78bfa);background-size:200% 100%;border-radius:99px;width:0;transition:width .55s cubic-bezier(.4,0,.2,1);animation:barShimmer 1.8s linear infinite}
@keyframes barShimmer{0%{background-position:100% 0}100%{background-position:-100% 0}}

/* ── Detected barangay cards ────────────────────────── */
.brgy-cards-list{display:flex;flex-direction:column;gap:.45rem;max-height:300px;overflow-y:auto;padding-right:3px;margin-top:.4rem}
.brgy-cards-list::-webkit-scrollbar{width:3px}
.brgy-cards-list::-webkit-scrollbar-thumb{background:#c7d2fe;border-radius:4px}
.brgy-card{display:flex;align-items:center;gap:.7rem;border-radius:10px;padding:.6rem .85rem;opacity:0;transform:translateY(12px);transition:opacity .38s ease,transform .38s ease}
.brgy-card.in{opacity:1;transform:translateY(0)}
.brgy-card.new{background:#f5f7ff;border:1.5px solid #c7d2fe}
.brgy-card.dup{background:#f0fdf4;border:1.5px solid #bbf7d0}

/* ── Save area ──────────────────────────────────────── */
.route-save-area{border-top:1.5px solid #e0e7ff;margin-top:.9rem;padding-top:.9rem}
.save-controls{display:flex;align-items:center;justify-content:space-between;margin-bottom:.6rem;flex-wrap:wrap;gap:.4rem}
</style>
@endpush

@section('page_title','Delivery Coverage')
@section('content')
@php
    $sLat = (float)($shopSettings->shop_lat ?? 0);
    $sLng = (float)($shopSettings->shop_lng ?? 0);
@endphp

<div id="shop-meta" style="display:none"
     data-lat="{{ $sLat }}"
     data-lng="{{ $sLng }}"
     data-has-pin="{{ ($sLat && $sLng) ? '1' : '0' }}">
</div>

<div class="container-fluid py-3 px-3 px-md-4">

  {{-- Header --}}
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-map me-2" style="color:var(--primary)"></i>Delivery Coverage</h4>
      <small class="text-muted">Define the barangays you deliver to — customers outside these zones cannot place delivery orders.</small>
    </div>
  </div>

  @if(session('msg'))
  <div class="alert alert-success alert-dismissible fade show py-2 mb-3">
    <i class="bi bi-check-circle-fill me-2"></i>{{ session('msg') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  @endif
  @if(session('err'))
  <div class="alert alert-danger alert-dismissible fade show py-2 mb-3">
    <i class="bi bi-exclamation-circle-fill me-2"></i>{{ session('err') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  @endif

  {{-- Stats --}}
  @php
    $total   = $zones->count();
    $active  = $zones->where('is_active', true)->count();
    $pinned  = $zones->filter(fn($z) => !empty($z->lat) && !empty($z->lng))->count();
    $shopSet = ($sLat && $sLng);
  @endphp
  <div class="dz-stats">
    <div class="dz-stat">
      <div class="val" style="color:var(--primary)">{{ $total }}</div>
      <div class="lbl">Coverage Areas</div>
    </div>
    <div class="dz-stat">
      <div class="val text-success">{{ $active }}</div>
      <div class="lbl">Active</div>
    </div>
    <div class="dz-stat">
      <div class="val text-info">{{ $pinned }}</div>
      <div class="lbl">Pinned on Map</div>
    </div>
    <div class="dz-stat">
      <div class="val {{ $shopSet ? 'text-success' : 'text-danger' }}">
        <i class="bi bi-{{ $shopSet ? 'check-circle-fill' : 'x-circle-fill' }}" style="font-size:1.4rem"></i>
      </div>
      <div class="lbl">Shop Location</div>
    </div>
  </div>

  <div class="dz-wrap">

    {{-- ── LEFT: Map column ────────────────────────── --}}
    <div>
      <div class="map-card">

        {{-- Toolbar --}}
        <div class="map-toolbar">
          <h6><i class="bi bi-geo-alt me-1" style="color:var(--primary)"></i>Coverage Map</h6>
          <button class="btn btn-sm btn-outline-secondary" onclick="locateShop()" title="Fly to my shop">
            <i class="bi bi-shop"></i> My Shop
          </button>
          <button id="btn-set-shop" class="btn btn-sm btn-outline-warning" onclick="toggleSetShopMode()">
            <i class="bi bi-crosshair"></i> Set Shop Location
          </button>
          <button id="btn-route-mode" class="btn btn-sm btn-outline-success" onclick="toggleRouteMode()"
                  title="Draw a route and auto-detect barangays along it">
            <i class="bi bi-bezier2"></i> Route Coverage
          </button>
          <button id="btn-pin-mode" class="btn btn-sm btn-outline-primary" onclick="togglePinMode()">
            <i class="bi bi-geo-alt-plus"></i> Add Area
          </button>
        </div>

        {{-- Set shop hint --}}
        <div id="set-shop-hint" class="hint-bar yellow">
          <i class="bi bi-cursor-fill"></i>
          <strong>Shop Location Mode:</strong>
          Click anywhere on the map to pin your shop's exact location.
          <button class="btn btn-xs btn-warning ms-1" onclick="saveShopLocation()" id="btn-save-shop" style="display:none">
            <i class="bi bi-check-circle"></i> Save Location
          </button>
          <button class="btn btn-xs btn-light" onclick="cancelSetShop()">Cancel</button>
        </div>

        {{-- Route mode hint --}}
        <div id="route-mode-hint" class="hint-bar green">
          <i class="bi bi-bezier2"></i>
          <strong>Route Coverage:</strong>
          <span id="route-hint-text">Click anywhere on the map to set your delivery destination.</span>
          <button class="btn btn-xs btn-light ms-auto" onclick="cancelRouteMode()">Cancel</button>
        </div>

        {{-- Pin mode hint --}}
        <div id="pin-mode-hint" class="hint-bar indigo">
          <i class="bi bi-cursor-fill"></i>
          <strong>Coverage Pin Mode:</strong>
          Click anywhere on the map to mark a single delivery area.
          <button class="btn btn-xs btn-light ms-1" onclick="cancelPinMode()">Cancel</button>
        </div>

        <div id="dz-map"></div>
      </div>

      {{-- ── Route Coverage Detection Panel ─────────── --}}
      <div id="route-detect-panel" class="route-panel" style="display:none">
        <div class="route-panel-head">
          <div class="route-panel-title">
            <div class="route-icon-wrap">
              <i class="bi bi-bezier2" style="color:#6366f1;font-size:.95rem"></i>
            </div>
            Route Coverage Detection
          </div>
          <button class="btn btn-xs btn-light" onclick="cancelRouteMode()">
            <i class="bi bi-x-lg"></i> Clear
          </button>
        </div>

        {{-- Progress --}}
        <div class="detect-progress">
          <div class="detect-bar" id="detect-bar"></div>
        </div>
        {{-- Spinner + status text are SIBLINGS so setStatus(innerHTML) never destroys the spinner --}}
        <div class="d-flex align-items-center gap-2 small mb-2" style="min-height:1.4rem">
          <span id="detect-spinner" class="spinner-border spinner-border-sm flex-shrink-0"
                style="width:.72rem;height:.72rem;border-color:#a5b4fc;border-right-color:transparent"></span>
          <span id="detect-status-text" style="color:#4338ca">Initializing...</span>
        </div>

        {{-- Detected cards --}}
        <div class="brgy-cards-list" id="brgy-cards-list"></div>

        {{-- Empty state --}}
        <div id="route-empty" style="display:none;text-align:center;padding:1.25rem 0">
          <i class="bi bi-map" style="font-size:2rem;color:#c7d2fe;display:block;margin-bottom:.4rem"></i>
          <p class="text-muted small mb-0">No new barangays detected along this route.<br>Try a longer route or add areas manually.</p>
        </div>

        {{-- Save area --}}
        <div id="route-save-area" class="route-save-area" style="display:none">
          <div class="save-controls">
            <span class="fw-semibold small" id="route-save-label" style="color:#4338ca"></span>
            <div class="d-flex gap-1">
              <button class="btn btn-xs btn-outline-secondary" onclick="selectAllBrgy(true)">All</button>
              <button class="btn btn-xs btn-outline-secondary" onclick="selectAllBrgy(false)">None</button>
            </div>
          </div>
          <button class="btn btn-primary w-100 fw-semibold" id="btn-save-route" onclick="saveRouteCoverage()"
                  style="border-radius:10px">
            <i class="bi bi-check-circle me-1"></i>
            <span id="save-btn-text">Save Coverage Areas</span>
          </button>
        </div>
      </div>

      {{-- ── Single-pin Add Coverage Panel ───────────── --}}
      <div class="side-panel" id="add-panel" style="display:none">
        <div class="panel-head" onclick="toggleAddPanel()">
          <h6><i class="bi bi-geo-alt-fill me-1" style="color:var(--primary)"></i>New Coverage Area</h6>
          <i class="bi bi-chevron-down" id="add-chevron"></i>
        </div>
        <div id="add-panel-body" style="margin-top:.9rem">
          <div class="d-flex align-items-start gap-2 p-2 rounded-3 mb-3"
               style="background:#f0fdf4;border:1px solid #bbf7d0;font-size:.78rem">
            <i class="bi bi-geo-alt-fill text-success mt-1"></i>
            <div>
              <div id="add-coords-display" class="fw-semibold text-success"></div>
              <div id="add-address-display" class="text-muted mt-1">
                <span class="spinner-border spinner-border-sm me-1" style="width:.7rem;height:.7rem"></span>
                Looking up address…
              </div>
            </div>
          </div>
          <form method="POST" action="{{ route('seller.zones.store') }}">
            @csrf
            <input type="hidden" name="lat" id="add-lat">
            <input type="hidden" name="lng" id="add-lng">
            <div class="row g-2">
              <div class="col-12">
                <label class="form-label small mb-1 fw-semibold">Area / Barangay Name <span class="text-danger">*</span></label>
                <input type="text" name="barangay" id="add-barangay" class="form-control form-control-sm"
                       placeholder="Auto-filled from pin location" required>
              </div>
              <div class="col-12">
                <label class="form-label small mb-1 fw-semibold">Full Address <span class="text-muted fw-normal">(optional)</span></label>
                <textarea name="zone_address" id="add-zone-address" class="form-control form-control-sm" rows="2"
                          placeholder="Auto-filled from pin location"></textarea>
              </div>
              <div class="col-12 d-flex gap-2 justify-content-end mt-1">
                <button type="button" class="btn btn-sm btn-light" onclick="cancelPinMode()">
                  <i class="bi bi-x-circle me-1"></i>Cancel
                </button>
                <button type="submit" class="btn btn-sm btn-primary px-4">
                  <i class="bi bi-check-circle me-1"></i>Save Area
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    {{-- ── RIGHT: Zone list ─────────────────────────── --}}
    <div>
      @if($zones->isNotEmpty())
      <div class="zone-search">
        <i class="bi bi-search si"></i>
        <input type="text" id="zone-search" class="form-control form-control-sm"
               placeholder="Search coverage areas…" oninput="filterZoneCards(this.value)">
      </div>
      @endif

      <div class="zone-list" id="zone-list">
        @forelse($zones as $z)
        <div class="zone-card"
             id="zcard-{{ $z->id }}"
             data-name="{{ strtolower($z->barangay) }}"
             onclick="focusZone({{ $z->id }},{{ $z->lat ?? 'null' }},{{ $z->lng ?? 'null' }})">
          <div class="zc-top">
            <i class="bi bi-geo-alt-fill" style="color:var(--primary);font-size:.85rem;flex-shrink:0"></i>
            <span class="zname">{{ $z->barangay }}</span>
            @if($z->lat && $z->lng)
              <i class="bi bi-pin-fill text-success" style="font-size:.72rem" title="Pinned"></i>
            @else
              <i class="bi bi-pin text-muted" style="font-size:.72rem" title="Not pinned"></i>
            @endif
          </div>
          @if(!empty($z->zone_address))
          <div class="zaddr"><i class="bi bi-signpost-split me-1 text-muted"></i>{{ $z->zone_address }}</div>
          @elseif($z->lat && $z->lng)
          <div class="zaddr text-muted"><i class="bi bi-geo me-1"></i>{{ number_format($z->lat,5) }}, {{ number_format($z->lng,5) }}</div>
          @endif
          <div class="zbtns" onclick="event.stopPropagation()">
            <button class="btn btn-xs btn-outline-primary"
              onclick="openEditModal({{ $z->id }}, @js($z->barangay), @js($z->zone_address ?? ''), {{ $z->lat ?? 'null' }}, {{ $z->lng ?? 'null' }})">
              <i class="bi bi-pencil"></i> Edit
            </button>
            <form method="POST" action="{{ route('seller.zones.archive', $z->id) }}" class="d-inline"
                  onsubmit="return confirm('Archive \'{{ addslashes($z->barangay) }}\' from coverage? You can restore it anytime.')">
              @csrf
              <button class="btn btn-xs btn-outline-secondary" title="Archive">
                <i class="bi bi-archive"></i>
              </button>
            </form>
          </div>
        </div>
        @empty
        <div class="text-center text-muted py-5">
          <i class="bi bi-map" style="font-size:2.5rem;opacity:.25;display:block"></i>
          <p class="mt-2 small">No coverage areas yet.<br>Use <strong>Route Coverage</strong> or <strong>Add Area</strong> on the map to start.</p>
        </div>
        @endforelse
      </div>
    </div>

  </div>{{-- /dz-wrap --}}
</div>

{{-- ── Edit Modal ──────────────────────────────────────── --}}
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0" style="border-radius:16px">
      <div class="modal-header py-2 border-0 pb-0">
        <h6 class="modal-title fw-bold"><i class="bi bi-pencil-square me-1"></i>Edit Coverage Area</h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="edit-form">
        @csrf
        <div class="modal-body pt-2">
          <input type="hidden" name="lat" id="edit-lat">
          <input type="hidden" name="lng" id="edit-lng">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small fw-semibold mb-1">Area / Barangay Name <span class="text-danger">*</span></label>
              <input type="text" name="barangay" id="edit-barangay" class="form-control form-control-sm" required>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold mb-1">Full Address</label>
              <textarea name="zone_address" id="edit-zone-address" class="form-control form-control-sm" rows="2"></textarea>
            </div>
            <div class="col-12">
              <div class="d-flex align-items-center justify-content-between mb-1">
                <label class="form-label small fw-semibold mb-0">Pin Location on Map</label>
                <button type="button" class="btn btn-xs btn-outline-primary" onclick="toggleEditPinMode()">
                  <i class="bi bi-geo-alt"></i> <span id="edit-pin-btn-txt">Repin on Map</span>
                </button>
              </div>
              <div id="edit-pin-hint" class="pin-hint mb-1">
                <i class="bi bi-cursor-fill me-1"></i>Click the map below to reposition this coverage pin.
              </div>
              <div class="modal-map-wrap">
                <div id="edit-map"></div>
              </div>
              <div class="text-muted small mt-1" id="edit-coords-display"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 py-2">
          <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">
            <i class="bi bi-x-circle me-1"></i>Cancel
          </button>
          <button type="submit" class="btn btn-sm btn-primary px-4">
            <i class="bi bi-check-circle me-1"></i>Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- ── Archived Zones ──────────────────────────────────── --}}
@if($archivedZones->isNotEmpty())
<div class="mt-4" style="max-width:720px">
  <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-2 mb-3"
          type="button" data-bs-toggle="collapse" data-bs-target="#archivedZones">
    <i class="bi bi-archive"></i> Archived Coverage Areas
    <span class="badge bg-secondary">{{ $archivedZones->count() }}</span>
    <i class="bi bi-chevron-down" style="font-size:.75rem"></i>
  </button>
  <div class="collapse" id="archivedZones">
    <div class="card" style="border:1.5px dashed #dee2e6;border-radius:14px;overflow:hidden">
      @foreach($archivedZones as $az)
      <div class="d-flex align-items-center justify-content-between px-4 py-3 border-bottom" style="background:#f8f9fa">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-geo-alt text-muted"></i>
          <span class="fw-semibold text-muted">{{ $az->barangay }}</span>
          @if($az->zone_address)
            <span class="text-muted small">— {{ $az->zone_address }}</span>
          @endif
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="text-muted" style="font-size:.72rem">Archived {{ \Carbon\Carbon::parse($az->archived_at)->diffForHumans() }}</span>
          <form method="POST" action="{{ route('seller.zones.restore', $az->id) }}" class="d-inline">
            @csrf
            <button class="btn btn-sm btn-outline-success">
              <i class="bi bi-arrow-counterclockwise me-1"></i>Restore
            </button>
          </form>
        </div>
      </div>
      @endforeach
    </div>
  </div>
</div>
@endif

@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ── State ──────────────────────────────────────────────
const zonesData       = @json($zones);
const existingNames   = new Set(zonesData.map(z => z.barangay.toLowerCase()));
const shopLat         = parseFloat(document.getElementById('shop-meta').dataset.lat) || 0;
const shopLng         = parseFloat(document.getElementById('shop-meta').dataset.lng) || 0;
const shopHasLocation = document.getElementById('shop-meta').dataset.hasPin === '1';
let currentShopLat    = shopLat;
let currentShopLng    = shopLng;
let currentHasShop    = shopHasLocation;

// ── Map init ───────────────────────────────────────────
const map = L.map('dz-map').setView(
    currentHasShop ? [currentShopLat, currentShopLng] : [13.41, 122.56],
    currentHasShop ? 15 : 6
);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19
}).addTo(map);

// ── Icons ──────────────────────────────────────────────
const shopIcon = L.divIcon({
    html: `<div style="background:#6366f1;width:18px;height:18px;border-radius:50%;border:3px solid #fff;
                box-shadow:0 0 0 3px rgba(99,102,241,.35),0 2px 8px rgba(99,102,241,.5)"></div>`,
    className: '', iconSize: [18,18], iconAnchor: [9,9]
});

const destIcon = L.divIcon({
    html: `<div style="background:#10b981;width:18px;height:18px;border-radius:50%;border:3px solid #fff;
                box-shadow:0 0 0 3px rgba(16,185,129,.3),0 2px 8px rgba(16,185,129,.55)"></div>`,
    className: '', iconSize: [18,18], iconAnchor: [9,9]
});

function makeCoverageIcon(active) {
    const c = active ? 'var(--primary,#e91e8c)' : '#bbb';
    return L.divIcon({
        html: `<div style="background:${c};width:12px;height:12px;border-radius:50%;border:2.5px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3)"></div>`,
        className: '', iconSize: [12,12], iconAnchor: [6,6]
    });
}

// ── Place zone markers ─────────────────────────────────
const zoneMarkers = {};
zonesData.forEach(z => {
    if (!z.lat || !z.lng) return;
    zoneMarkers[z.id] = L.marker([z.lat, z.lng], { icon: makeCoverageIcon(z.is_active) })
        .addTo(map)
        .bindPopup(`<b>${z.barangay}</b>${z.zone_address ? '<br><small>'+z.zone_address+'</small>' : ''}`);
});

// ── Shop marker ────────────────────────────────────────
let shopMarker = null;
function placeShopMarker(lat, lng) {
    if (shopMarker) map.removeLayer(shopMarker);
    shopMarker = L.marker([lat, lng], { icon: shopIcon })
        .addTo(map).bindPopup('<b>🏪 Your Shop</b>');
}
if (currentHasShop) placeShopMarker(currentShopLat, currentShopLng);

function locateShop() {
    if (!currentHasShop) { toggleSetShopMode(); return; }
    map.flyTo([currentShopLat, currentShopLng], 17, { duration: 0.9 });
    if (shopMarker) shopMarker.openPopup();
}

// ── Set Shop Location mode ─────────────────────────────
let setShopMode = false, tempShopMarker = null, pendingShopLat = null, pendingShopLng = null;

function toggleSetShopMode() {
    setShopMode = !setShopMode;
    const hint = document.getElementById('set-shop-hint');
    const btn  = document.getElementById('btn-set-shop');
    if (setShopMode) {
        if (pinModeActive)  cancelPinMode();
        if (routeModeActive) cancelRouteMode();
        hint.classList.add('show');
        btn.classList.replace('btn-outline-warning', 'btn-warning');
        document.getElementById('dz-map').style.cursor = 'crosshair';
    } else {
        hint.classList.remove('show');
        btn.classList.replace('btn-warning', 'btn-outline-warning');
        document.getElementById('dz-map').style.cursor = '';
        if (tempShopMarker) { map.removeLayer(tempShopMarker); tempShopMarker = null; }
        document.getElementById('btn-save-shop').style.display = 'none';
    }
}
function cancelSetShop() { if (setShopMode) toggleSetShopMode(); }

function saveShopLocation() {
    if (!pendingShopLat || !pendingShopLng) return;
    const btn = document.getElementById('btn-save-shop');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving…';

    const fd = new FormData();
    fd.append('_token', '{{ csrf_token() }}');
    fd.append('shop_lat', pendingShopLat);
    fd.append('shop_lng', pendingShopLng);
    fd.append('_ajax', '1');

    fetch('{{ route("seller.zones.shop_location") }}', { method: 'POST', body: fd })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(d => {
            if (!d.ok) throw new Error('Server error');
            currentShopLat = pendingShopLat;
            currentShopLng = pendingShopLng;
            currentHasShop = true;
            const meta = document.getElementById('shop-meta');
            meta.dataset.lat = currentShopLat;
            meta.dataset.lng = currentShopLng;
            meta.dataset.hasPin = '1';
            if (tempShopMarker) { map.removeLayer(tempShopMarker); tempShopMarker = null; }
            placeShopMarker(currentShopLat, currentShopLng);
            shopMarker.openPopup();
            toggleSetShopMode();
            showToast('✅ Shop location saved!', 'success');
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle"></i> Save Location';
            showToast('❌ ' + err.message, 'danger');
        });
}

// ── Single-pin coverage mode ───────────────────────────
let pinModeActive = false, tempMarker = null;

function togglePinMode() {
    pinModeActive = !pinModeActive;
    const btn   = document.getElementById('btn-pin-mode');
    const hint  = document.getElementById('pin-mode-hint');
    const panel = document.getElementById('add-panel');
    const mapEl = document.getElementById('dz-map');
    if (pinModeActive) {
        if (setShopMode)    cancelSetShop();
        if (routeModeActive) cancelRouteMode();
        btn.classList.replace('btn-outline-primary', 'btn-primary');
        btn.innerHTML = '<i class="bi bi-x-circle"></i> Cancel';
        hint.classList.add('show');
        mapEl.style.cursor = 'crosshair';
    } else {
        btn.classList.replace('btn-primary', 'btn-outline-primary');
        btn.innerHTML = '<i class="bi bi-geo-alt-plus"></i> Add Area';
        hint.classList.remove('show');
        mapEl.style.cursor = '';
        if (tempMarker) { map.removeLayer(tempMarker); tempMarker = null; }
        panel.style.display = 'none';
        document.getElementById('add-lat').value = '';
        document.getElementById('add-lng').value = '';
    }
}
function cancelPinMode() { if (pinModeActive) togglePinMode(); }

function toggleAddPanel() {
    const body = document.getElementById('add-panel-body');
    const chev = document.getElementById('add-chevron');
    const open = body.style.display !== 'none';
    body.style.display = open ? 'none' : '';
    chev.className = open ? 'bi bi-chevron-down' : 'bi bi-chevron-up';
}

// ── Map click ──────────────────────────────────────────
map.on('click', function(e) {
    const { lat, lng } = e.latlng;

    if (setShopMode) {
        pendingShopLat = lat; pendingShopLng = lng;
        if (tempShopMarker) map.removeLayer(tempShopMarker);
        tempShopMarker = L.marker([lat, lng], { icon: shopIcon })
            .addTo(map)
            .bindPopup('<b>Your Shop (pending)</b><br>' + lat.toFixed(5) + ', ' + lng.toFixed(5))
            .openPopup();
        const saveBtn = document.getElementById('btn-save-shop');
        saveBtn.style.display = '';
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="bi bi-check-circle"></i> Save Location';
        return;
    }

    if (routeModeActive) {
        handleRouteClick(lat, lng);
        return;
    }

    if (!pinModeActive) return;

    document.getElementById('add-lat').value = lat.toFixed(7);
    document.getElementById('add-lng').value = lng.toFixed(7);
    if (tempMarker) map.removeLayer(tempMarker);
    tempMarker = L.marker([lat, lng]).addTo(map).bindPopup('📍 New coverage area').openPopup();
    showCovPinInfo(lat, lng);

    const panel = document.getElementById('add-panel');
    panel.style.display = '';
    document.getElementById('add-panel-body').style.display = '';
    document.getElementById('add-chevron').className = 'bi bi-chevron-up';
});

// ── Reverse-geocode for single pin ─────────────────────
function showCovPinInfo(lat, lng) {
    const coords    = document.getElementById('add-coords-display');
    const addrDisp  = document.getElementById('add-address-display');
    const addrField = document.getElementById('add-zone-address');
    const dist      = currentHasShop ? haversine(currentShopLat, currentShopLng, lat, lng) : null;
    const distTxt   = dist === null ? '(shop not set)' : dist < 1000
        ? `${Math.round(dist)} m from shop` : `${(dist/1000).toFixed(2)} km from shop`;
    coords.innerHTML = `📍 ${lat.toFixed(6)}, ${lng.toFixed(6)} &nbsp;·&nbsp; <span class="text-primary">${distTxt}</span>`;
    addrDisp.innerHTML = '<span class="spinner-border spinner-border-sm me-1" style="width:.65rem;height:.65rem"></span> Looking up…';
    fetchWithTimeout(`/api/geocode/reverse?lat=${lat}&lng=${lng}`, {}, 7000)
        .then(r => r.json())
        .then(d => {
            const a    = d.address || {};
            const brgy = a.village || a.suburb || a.neighbourhood || a.quarter || a.hamlet || '';
            const full = [a.road, a.village || a.suburb || a.neighbourhood || a.quarter || a.hamlet,
                          a.city || a.town || a.municipality, a.state].filter(Boolean).join(', ') || d.display_name || '';
            if (brgy) document.getElementById('add-barangay').value = brgy;
            if (full)  addrField.value = full;
            addrDisp.textContent = full || 'Address not found';
        })
        .catch(() => { addrDisp.textContent = 'Could not resolve address'; });
}

// ── Haversine ──────────────────────────────────────────
function haversine(lat1, lon1, lat2, lon2) {
    const R = 6371000, toRad = d => d * Math.PI / 180;
    const dLat = toRad(lat2-lat1), dLon = toRad(lon2-lon1);
    const a = Math.sin(dLat/2)**2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

// ── Focus zone card + marker ───────────────────────────
function focusZone(id, lat, lng) {
    if (lat && lng) {
        map.flyTo([lat, lng], 16, { duration: 0.9 });
        if (zoneMarkers[id]) zoneMarkers[id].openPopup();
    }
}

// ── Zone list search ───────────────────────────────────
function filterZoneCards(q) {
    const t = q.toLowerCase().trim();
    document.querySelectorAll('.zone-card').forEach(c => {
        c.style.display = (!t || c.dataset.name.includes(t)) ? '' : 'none';
    });
}

// ── Toast ──────────────────────────────────────────────
function showToast(msg, type) {
    const t = document.createElement('div');
    t.className = `alert alert-${type} alert-dismissible position-fixed bottom-0 end-0 m-3`;
    t.style.cssText = 'z-index:9999;min-width:260px;box-shadow:0 4px 20px rgba(0,0,0,.15);border-radius:12px';
    t.innerHTML = msg + '<button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>';
    document.body.appendChild(t);
    setTimeout(() => { t.style.transition = 'opacity .4s'; t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 3500);
}

// ═══════════════════════════════════════════════════════
// ── ROUTE COVERAGE MODE ────────────────────────────────
// ═══════════════════════════════════════════════════════

let routeModeActive = false;
let routeLine = null, routeBgLine = null, destMarker = null;
let detectedBrgys = [];

function toggleRouteMode() {
    if (routeModeActive) { cancelRouteMode(); return; }
    routeModeActive = true;
    if (setShopMode)  cancelSetShop();
    if (pinModeActive) cancelPinMode();
    document.getElementById('btn-route-mode').classList.replace('btn-outline-success', 'btn-success');
    document.getElementById('btn-route-mode').innerHTML = '<i class="bi bi-x-circle"></i> Cancel';
    document.getElementById('route-mode-hint').classList.add('show');
    document.getElementById('dz-map').style.cursor = 'crosshair';
}

function cancelRouteMode() {
    routeModeActive = false;
    document.getElementById('btn-route-mode').classList.replace('btn-success', 'btn-outline-success');
    document.getElementById('btn-route-mode').innerHTML = '<i class="bi bi-bezier2"></i> Route Coverage';
    document.getElementById('route-mode-hint').classList.remove('show');
    document.getElementById('dz-map').style.cursor = '';

    if (routeLine)   { map.removeLayer(routeLine);   routeLine   = null; }
    if (routeBgLine) { map.removeLayer(routeBgLine); routeBgLine = null; }
    if (destMarker)  { map.removeLayer(destMarker);  destMarker  = null; }

    document.getElementById('route-detect-panel').style.display = 'none';
    detectedBrgys = [];
}

async function handleRouteClick(lat, lng) {
    if (!currentHasShop) {
        showToast('⚠️ Please set your shop location first.', 'warning');
        cancelRouteMode();
        return;
    }

    // Lock route mode — no more clicks
    routeModeActive = false;
    document.getElementById('dz-map').style.cursor = '';
    document.getElementById('route-mode-hint').classList.remove('show');
    document.getElementById('btn-route-mode').classList.replace('btn-success', 'btn-outline-success');
    document.getElementById('btn-route-mode').innerHTML = '<i class="bi bi-bezier2"></i> Route Coverage';

    // Place destination pin
    if (destMarker) map.removeLayer(destMarker);
    destMarker = L.marker([lat, lng], { icon: destIcon })
        .addTo(map).bindPopup('<b>📍 Destination</b>').openPopup();

    // Show + reset panel
    const panel = document.getElementById('route-detect-panel');
    panel.style.display = 'block';
    document.getElementById('brgy-cards-list').innerHTML = '';
    document.getElementById('route-save-area').style.display  = 'none';
    document.getElementById('route-empty').style.display      = 'none';
    document.getElementById('detect-spinner').style.display   = '';
    detectedBrgys = [];
    setStatus(5, 'Fetching route from OSRM…');

    try {
        // ── 1. Fetch route ─────────────────────────────
        const osrmUrl = `https://router.project-osrm.org/route/v1/driving/`
            + `${currentShopLng},${currentShopLat};${lng},${lat}`
            + `?overview=full&geometries=geojson`;

        const osrmResp = await fetchWithTimeout(osrmUrl, {}, 12000);
        if (!osrmResp.ok) throw new Error('OSRM responded ' + osrmResp.status);
        const osrmData = await osrmResp.json();

        if (!osrmData.routes || !osrmData.routes[0])
            throw new Error('No driving route found between these two points.');

        const coords  = osrmData.routes[0].geometry.coordinates; // [[lng,lat],…]
        const latlngs = coords.map(c => [c[1], c[0]]); // → Leaflet [lat,lng]
        const distKm  = (osrmData.routes[0].distance / 1000).toFixed(1);
        const durMin  = Math.round(osrmData.routes[0].duration / 60);

        setStatus(20, `<i class="bi bi-check-circle-fill text-success me-1"></i>Route found: <strong>${distKm} km</strong> · ~${durMin} min — drawing…`);

        // ── 2. Animate route line ──────────────────────
        await drawAnimatedRoute(latlngs);
        map.fitBounds(L.latLngBounds(latlngs).pad(0.13), { animate: true, duration: 0.8 });
        setStatus(30, '<i class="bi bi-search me-1"></i>Detecting barangays along the route…');

        // ── 3. Sample + geocode ────────────────────────
        const samples = samplePoints(latlngs, 14);
        await detectAlongRoute(samples);

    } catch (err) {
        document.getElementById('detect-spinner').style.display = 'none';
        setStatus(0, `<i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>${escHtml(err.message)}`);
    }
}

// ── Route animation ────────────────────────────────────
async function drawAnimatedRoute(latlngs) {
    if (routeBgLine) map.removeLayer(routeBgLine);
    if (routeLine)   map.removeLayer(routeLine);

    // Ghost track underneath
    routeBgLine = L.polyline(latlngs, {
        color: '#c7d2fe', weight: 8, opacity: 0.55
    }).addTo(map);

    // Foreground line drawn progressively (animation)
    routeLine = L.polyline([], {
        color: '#6366f1', weight: 4.5, opacity: 0.9,
        lineCap: 'round', lineJoin: 'round'
    }).addTo(map);

    const total    = latlngs.length;
    const duration = Math.min(2000, Math.max(700, total * 4)); // scale with route length
    const fps      = 60;
    const ptsPerFrame = Math.max(1, total / ((duration / 1000) * fps));
    let drawn = 0;

    return new Promise(resolve => {
        function frame() {
            drawn = Math.min(drawn + ptsPerFrame, total);
            routeLine.setLatLngs(latlngs.slice(0, Math.ceil(drawn)));
            if (drawn < total) requestAnimationFrame(frame);
            else { routeLine.setLatLngs(latlngs); resolve(); }
        }
        requestAnimationFrame(frame);
    });
}

// ── Evenly sample N points from route coords ───────────
function samplePoints(latlngs, n) {
    const total = latlngs.length;
    if (total <= n) return [...latlngs];
    const step    = total / n;
    const samples = [];
    for (let i = 0; i < n; i++) samples.push(latlngs[Math.round(i * step)]);
    const last = latlngs[total - 1];
    if (samples[samples.length - 1] !== last) samples.push(last);
    return samples;
}

// ── Nominatim detection loop ───────────────────────────
async function detectAlongRoute(samples) {
    const seenKeys = new Set();
    let processed  = 0;

    for (const [lat, lng] of samples) {
        try {
            const r = await fetchWithTimeout(`/api/geocode/reverse?lat=${lat}&lng=${lng}`, {}, 7000);
            const d = await r.json();
            const a = d.address || {};
            const brgy = (a.village || a.suburb || a.neighbourhood || a.quarter || a.hamlet || '').trim();

            if (brgy && !seenKeys.has(brgy.toLowerCase())) {
                seenKeys.add(brgy.toLowerCase());
                const full = [
                    a.village || a.suburb || a.neighbourhood || a.quarter || a.hamlet,
                    a.city || a.town || a.municipality,
                    a.state
                ].filter(Boolean).join(', ');
                const isDup = existingNames.has(brgy.toLowerCase());
                if (!isDup) detectedBrgys.push({ label: brgy, address: full, lat, lng });
                addBrgyCard(brgy, full, lat, lng, isDup);
            }
        } catch (e) { /* skip failed points silently */ }

        processed++;
        const pct = 30 + Math.round((processed / samples.length) * 66);
        setStatus(pct, `<i class="bi bi-search me-1"></i>Checking point ${processed} of ${samples.length}…`);

        if (processed < samples.length) await sleep(1100); // Nominatim rate-limit: 1 req/s
    }

    // ── Detection complete ─────────────────────────────
    document.getElementById('detect-spinner').style.display = 'none';
    const newCount = detectedBrgys.length;
    const dupCount = document.querySelectorAll('.brgy-card.dup').length;

    if (newCount === 0 && dupCount === 0) {
        setStatus(100, '<i class="bi bi-info-circle me-1"></i>Detection complete — no barangays found along this route.');
        document.getElementById('route-empty').style.display = 'block';
    } else if (newCount === 0) {
        setStatus(100, `<i class="bi bi-check-circle-fill text-success me-1"></i>All ${dupCount} detected barangay${dupCount > 1 ? 's' : ''} are already in your coverage.`);
    } else {
        setStatus(100, `<i class="bi bi-check-circle-fill text-success me-1"></i>Done — <strong>${newCount}</strong> new barangay${newCount > 1 ? 's' : ''} found.`);
        updateSaveLabel();
        document.getElementById('route-save-area').style.display = 'block';
    }
}

// ── Append a detection result card ────────────────────
function addBrgyCard(brgy, address, lat, lng, isDup) {
    const list = document.getElementById('brgy-cards-list');
    const div  = document.createElement('div');
    div.className = `brgy-card ${isDup ? 'dup' : 'new'}`;

    div.innerHTML = isDup
        ? `<i class="bi bi-check-circle-fill flex-shrink-0" style="color:#10b981"></i>
           <div style="flex:1;min-width:0">
             <div class="fw-semibold small" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escHtml(brgy)}</div>
             <div class="text-muted" style="font-size:.7rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escHtml(address)}</div>
           </div>
           <span style="background:#dcfce7;color:#166534;font-size:.65rem;font-weight:700;padding:.2rem .5rem;border-radius:6px;white-space:nowrap;flex-shrink:0">
             Already added
           </span>`
        : `<input type="checkbox" class="form-check-input mt-0 flex-shrink-0 brgy-chk" checked
               data-brgy="${escHtml(brgy)}" data-address="${escHtml(address)}"
               data-lat="${lat}" data-lng="${lng}" onchange="updateSaveLabel()">
           <div style="flex:1;min-width:0">
             <div class="fw-semibold small" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escHtml(brgy)}</div>
             <div class="text-muted" style="font-size:.7rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escHtml(address)}</div>
           </div>
           <i class="bi bi-geo-alt-fill flex-shrink-0" style="color:#6366f1;font-size:.82rem"></i>`;

    list.appendChild(div);
    // Slide-in animation — needs two rAF to ensure layout pass
    requestAnimationFrame(() => requestAnimationFrame(() => div.classList.add('in')));
}

// ── Checkbox helpers ───────────────────────────────────
function updateSaveLabel() {
    const n = document.querySelectorAll('.brgy-chk:checked').length;
    document.getElementById('route-save-label').textContent = `${n} area${n !== 1 ? 's' : ''} selected`;
    const txt = document.getElementById('save-btn-text');
    if (txt) txt.textContent = n > 0
        ? `Save ${n} Coverage Area${n !== 1 ? 's' : ''}`
        : 'Select at least one';
}
function selectAllBrgy(state) {
    document.querySelectorAll('.brgy-chk').forEach(cb => cb.checked = state);
    updateSaveLabel();
}

// ── Bulk save ──────────────────────────────────────────
async function saveRouteCoverage() {
    const checked = [...document.querySelectorAll('.brgy-chk:checked')];
    if (!checked.length) { showToast('⚠️ Please select at least one barangay.', 'warning'); return; }

    const items = checked.map(cb => ({
        barangay: cb.dataset.brgy,
        address:  cb.dataset.address,
        lat:      cb.dataset.lat,
        lng:      cb.dataset.lng,
    }));

    const btn = document.getElementById('btn-save-route');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

    try {
        const resp = await fetch('{{ route("seller.zones.storeBulk") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ items })
        });
        const data = await resp.json();
        if (!data.ok) throw new Error(data.message || 'Server error');

        const msg = `✅ ${data.saved} coverage area${data.saved !== 1 ? 's' : ''} saved!`
            + (data.skipped > 0 ? ` (${data.skipped} already existed)` : '');
        showToast(msg, 'success');
        setTimeout(() => location.reload(), 1400);
    } catch (e) {
        showToast('❌ Save failed: ' + e.message, 'danger');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle me-1"></i><span id="save-btn-text">Retry</span>';
    }
}

// ── Status helper ──────────────────────────────────────
function setStatus(pct, html) {
    const bar  = document.getElementById('detect-bar');
    const stat = document.getElementById('detect-status-text');
    if (bar)  bar.style.width = pct + '%';
    if (stat) stat.innerHTML  = html;
}

// ═══════════════════════════════════════════════════════
// ── EDIT MODAL ─────────────────────────────────────────
// ═══════════════════════════════════════════════════════

let editMap = null, editMarker = null, editPinMode = false;

function openEditModal(id, barangay, zoneAddress, lat, lng) {
    const form = document.getElementById('edit-form');
    form.action = `/seller/zones/${id}/update`;
    document.getElementById('edit-barangay').value     = barangay;
    document.getElementById('edit-zone-address').value = zoneAddress || '';
    document.getElementById('edit-lat').value          = lat  || '';
    document.getElementById('edit-lng').value          = lng  || '';
    updateEditCoords(lat, lng, zoneAddress || '');

    const modal = new bootstrap.Modal(document.getElementById('editModal'));
    modal.show();

    document.getElementById('editModal').addEventListener('shown.bs.modal', function onShown() {
        this.removeEventListener('shown.bs.modal', onShown);
        const clat = lat  || currentShopLat || 13.41;
        const clng = lng  || currentShopLng || 122.56;
        if (!editMap) {
            editMap = L.map('edit-map').setView([clat, clng], 14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(editMap);
            editMap.on('click', e => { if (editPinMode) setEditPin(e.latlng.lat, e.latlng.lng); });
        } else {
            editMap.setView([clat, clng], 14);
        }
        if (editMarker) { editMap.removeLayer(editMarker); editMarker = null; }
        if (lat && lng) editMarker = L.marker([lat, lng]).addTo(editMap).bindPopup('Coverage area').openPopup();
        editMap.invalidateSize();
    });
}

function toggleEditPinMode() {
    editPinMode = !editPinMode;
    document.getElementById('edit-pin-btn-txt').textContent = editPinMode ? 'Cancel Repinning' : 'Repin on Map';
    document.getElementById('edit-pin-hint').classList.toggle('show', editPinMode);
    document.getElementById('edit-map').style.cursor = editPinMode ? 'crosshair' : '';
}

function setEditPin(lat, lng) {
    document.getElementById('edit-lat').value = lat.toFixed(7);
    document.getElementById('edit-lng').value = lng.toFixed(7);
    if (editMarker) editMap.removeLayer(editMarker);
    editMarker = L.marker([lat, lng]).addTo(editMap).bindPopup('Coverage area').openPopup();
    if (editPinMode) toggleEditPinMode();

    fetchWithTimeout(`/api/geocode/reverse?lat=${lat}&lng=${lng}`, {}, 7000)
        .then(r => r.json())
        .then(d => {
            const a    = d.address || {};
            const brgy = a.village || a.suburb || a.neighbourhood || a.quarter || a.hamlet || '';
            const full = [a.road, a.village || a.suburb || a.neighbourhood || a.quarter || a.hamlet,
                          a.city || a.town || a.municipality, a.state].filter(Boolean).join(', ') || d.display_name || '';
            if (brgy) document.getElementById('edit-barangay').value    = brgy;
            if (full)  document.getElementById('edit-zone-address').value = full;
            updateEditCoords(lat, lng, full);
        })
        .catch(() => updateEditCoords(lat, lng, ''));
}

function updateEditCoords(lat, lng, addr) {
    const el = document.getElementById('edit-coords-display');
    if (!lat || !lng) { el.textContent = 'Not pinned yet'; return; }
    const dist    = currentHasShop ? haversine(currentShopLat, currentShopLng, parseFloat(lat), parseFloat(lng)) : null;
    const distTxt = dist === null ? '' : ` · ${dist < 1000 ? Math.round(dist)+' m' : (dist/1000).toFixed(2)+' km'} from shop`;
    el.innerHTML  = `<span class="fw-semibold text-primary">📍 ${parseFloat(lat).toFixed(6)}, ${parseFloat(lng).toFixed(6)}</span>`
        + `<span class="text-muted ms-1">${distTxt}</span>`
        + (addr ? `<br><span class="text-muted" style="font-size:.74rem">${addr}</span>` : '');
}

document.getElementById('editModal').addEventListener('hidden.bs.modal', () => {
    if (editPinMode) toggleEditPinMode();
});

// ── Utilities ──────────────────────────────────────────
function fetchWithTimeout(url, opts, ms) {
    const ctrl = new AbortController();
    setTimeout(() => ctrl.abort(), ms);
    return fetch(url, { ...opts, signal: ctrl.signal });
}
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }
function escHtml(s) {
    return (s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
@endpush
