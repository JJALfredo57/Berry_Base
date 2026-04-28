@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
.dz-wrap{display:grid;grid-template-columns:1fr 360px;gap:1.25rem;align-items:start}
@media(max-width:960px){.dz-wrap{grid-template-columns:1fr}}
#dz-map{height:480px;border-radius:0 0 14px 14px;z-index:0}
#dz-map.pin-mode{cursor:crosshair}
.map-card{background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 2px 14px rgba(0,0,0,.09)}
.map-toolbar{display:flex;align-items:center;gap:.5rem;padding:.8rem 1rem;background:#fff;border-bottom:1px solid #f0f0f0;flex-wrap:wrap}
.map-toolbar h6{margin:0;font-weight:700;font-size:.95rem;flex:1}
.dz-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:.75rem;margin-bottom:1.25rem}
@media(max-width:640px){.dz-stats{grid-template-columns:repeat(2,1fr)}}
.dz-stat{background:#fff;border-radius:12px;padding:.85rem 1rem;box-shadow:0 2px 10px rgba(0,0,0,.07);text-align:center}
.dz-stat .val{font-size:1.6rem;font-weight:800;line-height:1}
.dz-stat .lbl{font-size:.68rem;color:#888;margin-top:4px;text-transform:uppercase;letter-spacing:.05em}
.add-panel{background:#fff;border-radius:16px;padding:1.1rem 1.2rem;box-shadow:0 2px 12px rgba(0,0,0,.08);margin-top:1rem}
.add-panel-head{display:flex;align-items:center;justify-content:space-between;cursor:pointer;user-select:none}
.add-panel-head h6{margin:0;font-weight:700;font-size:.93rem}
.add-panel-body{margin-top:.9rem}
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
.zone-search{position:relative;margin-bottom:.7rem}
.zone-search input{padding-left:2.2rem;border-radius:10px}
.zone-search .si{position:absolute;left:.65rem;top:50%;transform:translateY(-50%);color:#bbb;font-size:.9rem}
.pin-hint{font-size:.75rem;color:#6366f1;background:#eef2ff;border-radius:8px;padding:.45rem .8rem;display:none}
.pin-hint.show{display:block}
.modal-map-wrap{border-radius:10px;overflow:hidden}
#edit-map{height:220px}
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
     data-has-pin="{{ ($sLat && $sLng) ? '1' : '0' }}"
     data-fee-per-meter="{{ (float)($shopSettings->fee_per_meter ?? 0.05) }}"
     data-maintenance="{{ (float)($shopSettings->maintenance_per_km ?? 5) }}"
     data-fuel="{{ (float)($shopSettings->fuel_per_km ?? 8) }}"
     data-free-radius="{{ (int)($shopSettings->free_delivery_radius ?? 0) }}">
</div>

<div class="container-fluid py-3 px-3 px-md-4">
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-map me-2" style="color:var(--primary)"></i>Delivery Coverage</h4>
      <small class="text-muted">Define the areas you deliver to — customers outside these zones will not be able to place delivery orders.</small>
    </div>
  </div>

  @if(session('msg'))
  <div class="alert alert-success alert-dismissible fade show py-2">
    {{ session('msg') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  @endif
  @if(session('err'))
  <div class="alert alert-danger alert-dismissible fade show py-2">
    {{ session('err') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  @endif

  {{-- Stats --}}
  @php
    $total  = $zones->count();
    $active = $zones->where('is_active', true)->count();
    $pinned = $zones->filter(fn($z) => !empty($z->lat) && !empty($z->lng))->count();
    $shopSet = ($sLat && $sLng);
  @endphp
  <div class="dz-stats">
    <div class="dz-stat"><div class="val" style="color:var(--primary)">{{ $total }}</div><div class="lbl">Coverage Areas</div></div>
    <div class="dz-stat"><div class="val text-success">{{ $active }}</div><div class="lbl">Active</div></div>
    <div class="dz-stat"><div class="val text-info">{{ $pinned }}</div><div class="lbl">Pinned</div></div>
    <div class="dz-stat">
      <div class="val {{ $shopSet ? 'text-success' : 'text-danger' }}">
        <i class="bi bi-{{ $shopSet ? 'check-circle-fill' : 'x-circle-fill' }}" style="font-size:1.4rem"></i>
      </div>
      <div class="lbl">Shop Location</div>
    </div>
  </div>

  <div class="dz-wrap">

    {{-- LEFT: Map --}}
    <div>
      <div class="map-card">
        <div class="map-toolbar">
          <h6><i class="bi bi-geo-alt me-1" style="color:var(--primary)"></i>Coverage Map</h6>
          <button id="btn-my-shop" class="btn btn-sm btn-outline-secondary" onclick="locateShop()">
            <i class="bi bi-shop"></i> My Shop
          </button>
          <button id="btn-set-shop" class="btn btn-sm btn-outline-warning" onclick="toggleSetShopMode()">
            <i class="bi bi-crosshair"></i> Set Shop Location
          </button>
          <button id="btn-pin-mode" class="btn btn-sm btn-outline-primary" onclick="togglePinMode()">
            <i class="bi bi-geo-alt-plus"></i> Add Coverage Area
          </button>
        </div>

        {{-- Set shop hint --}}
        <div id="set-shop-hint" style="display:none;background:#fff8e1;border-bottom:1px solid #ffe082;padding:.5rem 1rem;font-size:.8rem;color:#b45309">
          <i class="bi bi-cursor-fill me-1"></i><strong>Shop Location Mode:</strong> Click anywhere on the map to pin your shop's exact location.
          <button class="btn btn-xs btn-warning ms-2" onclick="saveShopLocation()" id="btn-save-shop" style="display:none">
            <i class="bi bi-check-circle"></i> Save Location
          </button>
          <button class="btn btn-xs btn-light ms-1" onclick="cancelSetShop()">Cancel</button>
        </div>

        {{-- Pin mode hint --}}
        <div id="pin-mode-hint" style="display:none;background:#eef2ff;border-bottom:1px solid #c7d2fe;padding:.5rem 1rem;font-size:.8rem;color:#4338ca">
          <i class="bi bi-cursor-fill me-1"></i><strong>Coverage Pin Mode:</strong> Click anywhere on the map to mark a delivery area.
          <button class="btn btn-xs btn-light ms-1" onclick="cancelPinMode()">Cancel</button>
        </div>

        <div id="dz-map"></div>
      </div>

      {{-- Add Coverage Area panel --}}
      <div class="add-panel" id="add-panel" style="display:none">
        <div class="add-panel-head" onclick="toggleAddPanel()">
          <h6><i class="bi bi-geo-alt-fill me-1" style="color:var(--primary)"></i>New Coverage Area</h6>
          <i class="bi bi-chevron-down" id="add-chevron"></i>
        </div>
        <div id="add-panel-body" class="add-panel-body">
          <div class="d-flex align-items-start gap-2 p-2 rounded-3 mb-3" id="add-pin-info" style="background:#f0fdf4;border:1px solid #bbf7d0;font-size:.78rem">
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
                <label class="form-label small mb-1 fw-semibold">Full Address</label>
                <textarea name="zone_address" id="add-zone-address" class="form-control form-control-sm" rows="2"
                          placeholder="Auto-filled from pin location"></textarea>
              </div>
              <div class="col-12 d-flex gap-2 justify-content-end mt-1">
                <button type="button" class="btn btn-sm btn-light" onclick="cancelPinMode()">
                  <i class="bi bi-x-circle me-1"></i>Cancel
                </button>
                <button type="submit" class="btn btn-sm btn-primary px-4">
                  <i class="bi bi-check-circle me-1"></i>Save Coverage Area
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    {{-- RIGHT: Zone list --}}
    <div>
      @if($zones->isNotEmpty())
      <div class="zone-search">
        <i class="bi bi-search si"></i>
        <input type="text" id="zone-search" class="form-control form-control-sm"
               placeholder="Search coverage areas..." oninput="filterZoneCards(this.value)">
      </div>
      @endif

      <div class="zone-list" id="zone-list">
        @forelse($zones as $z)
        <div class="zone-card {{ $z->is_active ? '' : 'inactive' }}"
             id="zcard-{{ $z->id }}"
             data-name="{{ strtolower($z->barangay) }}"
             onclick="focusZone({{ $z->id }},{{ $z->lat ?? 'null' }},{{ $z->lng ?? 'null' }})">
          <div class="zc-top">
            <i class="bi bi-geo-alt-fill" style="color:{{ $z->is_active ? 'var(--primary)' : '#bbb' }};font-size:.85rem"></i>
            <span class="zname">{{ $z->barangay }}</span>
            @if(!$z->is_active)
              <span class="badge" style="background:#f3f4f6;color:#6b7280;font-size:.65rem">Hidden</span>
            @endif
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
            <form method="POST" action="{{ route('seller.zones.toggle', $z->id) }}" class="d-inline">
              @csrf
              <button class="btn btn-xs {{ $z->is_active ? 'btn-outline-secondary' : 'btn-outline-success' }}">
                <i class="bi bi-{{ $z->is_active ? 'eye-slash' : 'eye' }}"></i>
                {{ $z->is_active ? 'Hide' : 'Show' }}
              </button>
            </form>
            <form method="POST" action="{{ route('seller.zones.destroy', $z->id) }}" class="d-inline"
                  onsubmit="return confirm('Remove \'{{ addslashes($z->barangay) }}\' from coverage?')">
              @csrf
              <button class="btn btn-xs btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </div>
        </div>
        @empty
        <div class="text-center text-muted py-5">
          <i class="bi bi-map" style="font-size:2.5rem;opacity:.25;display:block"></i>
          <p class="mt-2 small">No coverage areas yet.<br>Click <strong>Add Coverage Area</strong> on the map to get started.</p>
        </div>
        @endforelse
      </div>
    </div>

  </div>{{-- /dz-wrap --}}
</div>

{{-- Edit Modal --}}
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
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ── Data ──────────────────────────────────────────────
const zonesData       = @json($zones);
const shopLat         = parseFloat(document.getElementById('shop-meta').dataset.lat) || 0;
const shopLng         = parseFloat(document.getElementById('shop-meta').dataset.lng) || 0;
const shopHasLocation = document.getElementById('shop-meta').dataset.hasPin === '1';

// ── Main map ──────────────────────────────────────────
const map = L.map('dz-map').setView(
    shopHasLocation ? [shopLat, shopLng] : [13.41, 122.56],
    shopHasLocation ? 15 : 6
);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution:'© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19
}).addTo(map);

// ── Shop icon ─────────────────────────────────────────
const shopIcon = L.divIcon({
    html: '<div style="background:#6366f1;width:16px;height:16px;border-radius:50%;border:3px solid #fff;box-shadow:0 0 8px rgba(99,102,241,.8)"></div>',
    className: '', iconSize: [16,16], iconAnchor: [8,8]
});

// ── Coverage zone icon ────────────────────────────────
function makeCoverageIcon(active) {
    const c = active ? 'var(--primary,#e91e8c)' : '#bbb';
    return L.divIcon({
        html: `<div style="background:${c};width:12px;height:12px;border-radius:50%;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.25)"></div>`,
        className: '', iconSize: [12,12], iconAnchor: [6,6]
    });
}

// ── Place zone markers ────────────────────────────────
const zoneMarkers = {};
zonesData.forEach(z => {
    if (!z.lat || !z.lng) return;
    const m = L.marker([z.lat, z.lng], {icon: makeCoverageIcon(z.is_active)})
        .addTo(map)
        .bindPopup(`<b>${z.barangay}</b>${z.zone_address ? '<br><small>'+z.zone_address+'</small>' : ''}`);
    zoneMarkers[z.id] = m;
});

// ── Shop marker ───────────────────────────────────────
let currentShopLat = shopLat, currentShopLng = shopLng, currentHasShop = shopHasLocation;
let shopMarker = null;

function initShopMarker() {
    if (currentHasShop && currentShopLat && currentShopLng) {
        if (shopMarker) map.removeLayer(shopMarker);
        shopMarker = L.marker([currentShopLat, currentShopLng], {icon: shopIcon})
            .addTo(map)
            .bindPopup('<b>Your Shop</b>');
    }
}
initShopMarker();

function locateShop() {
    if (!currentHasShop) { toggleSetShopMode(); return; }
    map.flyTo([currentShopLat, currentShopLng], 17, {duration: 0.8});
    if (shopMarker) shopMarker.openPopup();
}

// ── Set Shop Location mode ────────────────────────────
let setShopMode = false, tempShopMarker = null, pendingShopLat = null, pendingShopLng = null;

function toggleSetShopMode() {
    setShopMode = !setShopMode;
    const hint = document.getElementById('set-shop-hint');
    const btn  = document.getElementById('btn-set-shop');
    const mapEl = document.getElementById('dz-map');
    if (setShopMode) {
        if (pinModeActive) togglePinMode();
        hint.style.display = '';
        btn.classList.replace('btn-outline-warning', 'btn-warning');
        mapEl.style.cursor = 'crosshair';
        pendingShopLat = pendingShopLng = null;
    } else {
        hint.style.display = 'none';
        btn.classList.replace('btn-warning', 'btn-outline-warning');
        mapEl.style.cursor = '';
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
        if (shopMarker) map.removeLayer(shopMarker);
        if (tempShopMarker) { map.removeLayer(tempShopMarker); tempShopMarker = null; }
        shopMarker = L.marker([currentShopLat, currentShopLng], {icon: shopIcon})
            .addTo(map).bindPopup('<b>Your Shop</b>').openPopup();
        toggleSetShopMode();
        showToast('✅ Shop location saved!', 'success');
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle"></i> Save Location';
        showToast('❌ ' + err.message, 'danger');
    });
}

// ── Pin mode (add coverage area) ──────────────────────
let pinModeActive = false, tempMarker = null;

function togglePinMode() {
    pinModeActive = !pinModeActive;
    const btn    = document.getElementById('btn-pin-mode');
    const hint   = document.getElementById('pin-mode-hint');
    const panel  = document.getElementById('add-panel');
    const mapEl  = document.getElementById('dz-map');
    if (pinModeActive) {
        if (setShopMode) toggleSetShopMode();
        btn.classList.replace('btn-outline-primary', 'btn-primary');
        btn.innerHTML = '<i class="bi bi-x-circle"></i> Cancel';
        hint.style.display = '';
        mapEl.classList.add('pin-mode');
    } else {
        btn.classList.replace('btn-primary', 'btn-outline-primary');
        btn.innerHTML = '<i class="bi bi-geo-alt-plus"></i> Add Coverage Area';
        hint.style.display = 'none';
        mapEl.classList.remove('pin-mode');
        if (tempMarker) { map.removeLayer(tempMarker); tempMarker = null; }
        panel.style.display = 'none';
        document.getElementById('add-lat').value = '';
        document.getElementById('add-lng').value = '';
    }
}

function cancelPinMode() { if (pinModeActive) togglePinMode(); }

function toggleAddPanel() {
    const body   = document.getElementById('add-panel-body');
    const chev   = document.getElementById('add-chevron');
    const open   = body.style.display !== 'none';
    body.style.display = open ? 'none' : '';
    chev.className = open ? 'bi bi-chevron-down' : 'bi bi-chevron-up';
}

// ── Map click handler ─────────────────────────────────
map.on('click', function(e) {
    const {lat, lng} = e.latlng;

    if (setShopMode) {
        pendingShopLat = lat; pendingShopLng = lng;
        if (tempShopMarker) map.removeLayer(tempShopMarker);
        tempShopMarker = L.marker([lat, lng], {icon: shopIcon})
            .addTo(map).bindPopup('<b>Your Shop (pending)</b><br>'+lat.toFixed(5)+', '+lng.toFixed(5)).openPopup();
        const saveBtn = document.getElementById('btn-save-shop');
        saveBtn.style.display = '';
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="bi bi-check-circle"></i> Save Location';
        return;
    }

    if (!pinModeActive) return;

    document.getElementById('add-lat').value = lat.toFixed(7);
    document.getElementById('add-lng').value = lng.toFixed(7);
    if (tempMarker) map.removeLayer(tempMarker);
    tempMarker = L.marker([lat, lng]).addTo(map).bindPopup('New coverage area').openPopup();
    showCovPinInfo(lat, lng);

    const panel = document.getElementById('add-panel');
    panel.style.display = '';
    document.getElementById('add-panel-body').style.display = '';
    document.getElementById('add-chevron').className = 'bi bi-chevron-up';
});

// ── Reverse geocode for coverage pin ──────────────────
function showCovPinInfo(lat, lng) {
    const coords  = document.getElementById('add-coords-display');
    const address = document.getElementById('add-address-display');
    const addrField = document.getElementById('add-zone-address');

    const dist = (currentHasShop && currentShopLat && currentShopLng)
        ? haversine(currentShopLat, currentShopLng, lat, lng) : null;
    const distTxt = dist === null ? '(shop not set)' : dist < 1000 ? `${Math.round(dist)} m from shop` : `${(dist/1000).toFixed(2)} km from shop`;
    coords.innerHTML = `📍 ${lat.toFixed(6)}, ${lng.toFixed(6)} &nbsp;·&nbsp; <span class="text-primary">${distTxt}</span>`;
    address.innerHTML = '<span class="spinner-border spinner-border-sm me-1" style="width:.65rem;height:.65rem"></span> Looking up address…';

    const _ctrl1 = new AbortController(); setTimeout(() => _ctrl1.abort(), 6000);
    fetch(`/api/geocode/reverse?lat=${lat}&lng=${lng}`, { signal: _ctrl1.signal })
    .then(r => r.json())
    .then(d => {
        const a = d.address || {};
        const brgy = a.village || a.suburb || a.neighbourhood || a.quarter || a.hamlet || '';
        const full = [a.road, a.village || a.suburb || a.neighbourhood || a.quarter || a.hamlet,
                      a.city || a.town || a.municipality, a.state].filter(Boolean).join(', ') || d.display_name || '';
        const brgyEl = document.getElementById('add-barangay');
        if (brgyEl && brgy) brgyEl.value = brgy;
        if (addrField) addrField.value = full;
        address.textContent = full || 'Address not found';
    })
    .catch(() => { address.textContent = 'Could not resolve address'; });
}

// ── Haversine ─────────────────────────────────────────
function haversine(lat1, lon1, lat2, lon2) {
    const R = 6371000;
    const dLat = (lat2-lat1)*Math.PI/180, dLon = (lon2-lon1)*Math.PI/180;
    const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

// ── Focus zone ────────────────────────────────────────
function focusZone(id, lat, lng) {
    if (lat && lng) {
        map.flyTo([lat, lng], 16, {duration: 0.9});
        if (zoneMarkers[id]) zoneMarkers[id].openPopup();
    }
}

// ── Zone search ───────────────────────────────────────
function filterZoneCards(q) {
    const t = q.toLowerCase().trim();
    document.querySelectorAll('.zone-card').forEach(c => {
        c.style.display = (!t || c.dataset.name.includes(t)) ? '' : 'none';
    });
}

// ── Toast ─────────────────────────────────────────────
function showToast(msg, type) {
    const t = document.createElement('div');
    t.className = `alert alert-${type} alert-dismissible position-fixed bottom-0 end-0 m-3`;
    t.style.zIndex = 9999;
    t.innerHTML = msg + '<button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>';
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

// ── Edit modal ────────────────────────────────────────
let editMap = null, editMarker = null, editPinMode = false;

function openEditModal(id, barangay, zoneAddress, lat, lng) {
    const form = document.getElementById('edit-form');
    form.action = `/seller/zones/${id}/update`;
    document.getElementById('edit-barangay').value    = barangay;
    document.getElementById('edit-zone-address').value = zoneAddress || '';
    document.getElementById('edit-lat').value = lat || '';
    document.getElementById('edit-lng').value = lng || '';
    updateEditCoords(lat, lng, zoneAddress || '');

    const modal = new bootstrap.Modal(document.getElementById('editModal'));
    modal.show();

    document.getElementById('editModal').addEventListener('shown.bs.modal', function onShown() {
        this.removeEventListener('shown.bs.modal', onShown);
        const clat = lat || currentShopLat || 13.41;
        const clng = lng || currentShopLng || 122.56;
        if (!editMap) {
            editMap = L.map('edit-map').setView([clat, clng], 14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19}).addTo(editMap);
            editMap.on('click', function(e) {
                if (!editPinMode) return;
                setEditPin(e.latlng.lat, e.latlng.lng);
            });
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

    // Reverse geocode and fill fields
    const _ctrl2 = new AbortController(); setTimeout(() => _ctrl2.abort(), 6000);
    fetch(`/api/geocode/reverse?lat=${lat}&lng=${lng}`, { signal: _ctrl2.signal })
    .then(r => r.json())
    .then(d => {
        const a = d.address || {};
        const brgy = a.village || a.suburb || a.neighbourhood || a.quarter || a.hamlet || '';
        const full = [a.road, a.village || a.suburb || a.neighbourhood || a.quarter || a.hamlet,
                      a.city || a.town || a.municipality, a.state].filter(Boolean).join(', ') || d.display_name || '';
        const brgyField = document.getElementById('edit-barangay');
        const addrField = document.getElementById('edit-zone-address');
        if (brgyField && brgy) brgyField.value = brgy;
        if (addrField && full) addrField.value = full;
        updateEditCoords(lat, lng, full);
    })
    .catch(() => updateEditCoords(lat, lng, ''));
}

function updateEditCoords(lat, lng, addr) {
    const el = document.getElementById('edit-coords-display');
    if (!lat || !lng) { el.textContent = 'Not pinned yet'; return; }
    const dist = (currentHasShop && currentShopLat && currentShopLng)
        ? haversine(currentShopLat, currentShopLng, parseFloat(lat), parseFloat(lng)) : null;
    const distTxt = dist === null ? '' : ` · ${dist < 1000 ? Math.round(dist)+' m' : (dist/1000).toFixed(2)+' km'} from shop`;
    el.innerHTML = `<span class="fw-semibold text-primary">📍 ${parseFloat(lat).toFixed(6)}, ${parseFloat(lng).toFixed(6)}</span>`
        + `<span class="text-muted ms-1">${distTxt}</span>`
        + (addr ? `<br><span class="text-muted" style="font-size:.74rem">${addr}</span>` : '');
}

document.getElementById('editModal').addEventListener('hidden.bs.modal', function() {
    if (editPinMode) toggleEditPinMode();
});
</script>
@endpush
