@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
.address-shell{max-width:1440px;margin:0 auto}
.address-panel{border:1px solid #edf0f4;border-radius:1rem;background:#fff;box-shadow:0 12px 30px rgba(15,23,42,.06)}
.address-card{border:1px solid #e5e7eb;border-radius:.95rem;background:#fff;box-shadow:0 8px 22px rgba(15,23,42,.05);transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}
.address-card:hover{transform:translateY(-1px);box-shadow:0 14px 28px rgba(15,23,42,.08);border-color:color-mix(in srgb,var(--primary) 22%,#e5e7eb)}
.address-title-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.8rem;align-items:start}
.address-actions{display:flex;gap:.45rem;flex-wrap:wrap;justify-content:flex-end;align-items:center}
.address-actions form{margin:0}
.address-actions .btn,.address-edit-actions .btn{border-radius:999px;display:inline-flex;align-items:center;justify-content:center;gap:.35rem;white-space:nowrap;min-height:32px}
.address-map{height:320px;border-radius:.85rem;overflow:hidden;border:1.5px solid color-mix(in srgb,var(--primary) 18%,#e5e7eb);background:#f8fafc}
.address-edit-map{height:240px;border-radius:.8rem;overflow:hidden;border:1.5px solid #dbe3ee;background:#f8fafc}
.address-edit-panel{display:none;background:#f8fafc;border:1px solid #e5e7eb;border-radius:.9rem;overflow:hidden;opacity:0;transform:translateY(-6px);transition:opacity .2s ease,transform .2s ease}
.address-edit-panel.is-open{display:block;animation:addressSlideIn .22s ease forwards}
.address-coordinate-pill{display:inline-flex;align-items:center;gap:.35rem;border-radius:999px;background:#fff;border:1px solid #e5e7eb;padding:.28rem .55rem;font-size:.72rem;color:#64748b}
.address-loading{display:none;font-size:.75rem;color:var(--primary);font-weight:400}
@keyframes addressSlideIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:767.98px){.address-shell{padding-left:2px;padding-right:2px}.address-title-row{grid-template-columns:1fr}.address-actions{justify-content:stretch;width:100%;display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.address-actions form{width:100%}.address-actions .btn{width:100%;min-height:38px}.address-edit-actions{display:grid!important;grid-template-columns:1fr;gap:.5rem}.address-edit-actions .btn{width:100%}.address-map{height:280px}.address-edit-map{height:230px}}
@media(max-width:420px){.address-actions{grid-template-columns:1fr}}
</style>
@endpush
@section('content')
<div class="container-fluid py-4 address-shell">
  <div class="d-flex justify-content-between align-items-end mb-3 flex-wrap gap-2">
    <div>
      <h2 class="mb-0">My Addresses</h2>
      <div class="text-muted">Manage saved delivery locations and choose your default checkout address.</div>
    </div>
    <a class="btn btn-outline-secondary pill" href="{{ route('customer.profile') }}"><i class="bi bi-arrow-left me-1"></i>Back to Profile</a>
  </div>

  @if(session('msg'))<div class="alert alert-success">{{ session('msg') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="address-panel p-3 h-100">
        <div class="d-flex align-items-center justify-content-between mb-3 gap-2">
          <h5 class="mb-0">Saved Addresses</h5>
          <span class="badge text-bg-light">{{ $list->count() }} active</span>
        </div>
        <div class="d-grid gap-2">
          @forelse($list as $a)
          <div class="address-card p-3">
            <div class="address-title-row">
              <div class="min-width-0">
                <div class="fw-semibold d-flex align-items-center gap-1 flex-wrap">
                  <i class="bi bi-geo-alt" style="color:var(--primary)"></i>
                  <span>{{ $a->label_name ?: 'Address' }}</span>
                  @if((int)$a->is_default === 1)<span class="badge text-bg-success">Default</span>@endif
                </div>
                <div class="small text-muted mt-1">{{ $a->full_address }}</div>
                <div class="d-flex flex-wrap gap-1 mt-2">
                  <span class="address-coordinate-pill"><i class="bi bi-compass"></i>Lat {{ $a->latitude }}</span>
                  <span class="address-coordinate-pill"><i class="bi bi-compass"></i>Lng {{ $a->longitude }}</span>
                </div>
              </div>
              <div class="address-actions">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleEditAddress({{ $a->id }}, {{ (float)$a->latitude }}, {{ (float)$a->longitude }})"><i class="bi bi-pencil-square"></i><span>Edit</span></button>
                @if((int)$a->is_default !== 1)
                <form method="POST" action="{{ route('customer.addresses.set_default', $a->id) }}">@csrf<button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-star"></i><span>Default</span></button></form>
                @endif
                <form method="POST" action="{{ route('customer.addresses.archive', $a->id) }}">@csrf<button type="submit" class="btn btn-sm btn-outline-danger" data-cs-confirm="Archive this address? It will be hidden from checkout but can be restored." data-cs-title="Archive Address" data-cs-ok="Archive"><i class="bi bi-archive"></i><span>Archive</span></button></form>
              </div>
            </div>

            <form method="POST" action="{{ route('customer.addresses.update', $a->id) }}" id="editAddress{{ $a->id }}" class="address-edit-panel mt-3 p-3">
              @csrf
              <div class="row g-2">
                <div class="col-sm-5">
                  <label class="form-label small fw-semibold">Label</label>
                  <input class="form-control pill" name="label_name" value="{{ $a->label_name ?: 'Address' }}">
                </div>
                <div class="col-sm-7">
                  <label class="form-label small fw-semibold">Complete Address <span class="address-loading" id="editAddressLoading{{ $a->id }}"><span class="spinner-border spinner-border-sm me-1" style="width:.7rem;height:.7rem"></span>Fetching address...</span></label>
                  <textarea class="form-control pill" name="full_address" id="editFullAddress{{ $a->id }}" rows="2" required>{{ $a->full_address }}</textarea>
                </div>
              </div>
              <div class="mt-3">
                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-2">
                  <label class="form-label small fw-semibold mb-0">Edit Map Pin</label>
                  <button type="button" class="btn btn-sm btn-outline-primary pill" id="detectEditBtn{{ $a->id }}" onclick="detectEditLocation({{ $a->id }})"><i class="bi bi-crosshair me-1"></i>Detect My Location</button>
                </div>
                <div id="editMap{{ $a->id }}" class="address-edit-map"></div>
                <input type="hidden" name="latitude" id="editLat{{ $a->id }}" value="{{ $a->latitude }}">
                <input type="hidden" name="longitude" id="editLng{{ $a->id }}" value="{{ $a->longitude }}">
                <div class="small text-muted mt-2"><i class="bi bi-info-circle me-1"></i>Move the pin or use detection. Complete Address updates automatically, then you can add house number or landmark.</div>
              </div>
              <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="make_default" id="editDefault{{ $a->id }}" {{ (int)$a->is_default === 1 ? 'checked' : '' }}><label class="form-check-label small" for="editDefault{{ $a->id }}">Set as default</label></div>
              <div class="address-edit-actions d-flex justify-content-end gap-2 mt-3"><button type="button" class="btn btn-sm btn-outline-secondary pill" onclick="toggleEditAddress({{ $a->id }}, {{ (float)$a->latitude }}, {{ (float)$a->longitude }})"><i class="bi bi-x-lg me-1"></i>Cancel</button><button class="btn btn-sm btn-primary pill"><i class="bi bi-check2 me-1"></i>Save Changes</button></div>
            </form>
          </div>
          @empty
          <div class="text-muted p-4 text-center rounded-3" style="background:#f8fafc;border:1px dashed #cbd5e1">No active addresses yet.</div>
          @endforelse
        </div>

        @if(($archived ?? collect())->count())
        <div class="mt-4">
          <h6 class="text-muted mb-2"><i class="bi bi-archive me-1"></i>Archived Addresses</h6>
          <div class="d-grid gap-2">
            @foreach($archived as $a)
            <div class="p-3 rounded-3 d-flex justify-content-between gap-2 flex-wrap" style="background:#f8fafc;border:1px solid #e5e7eb;opacity:.82">
              <div><div class="fw-semibold small">{{ $a->label_name ?: 'Address' }}</div><div class="small text-muted">{{ $a->full_address }}</div></div>
              <form method="POST" action="{{ route('customer.addresses.restore', $a->id) }}">@csrf<button class="btn btn-sm btn-outline-primary pill"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore</button></form>
            </div>
            @endforeach
          </div>
        </div>
        @endif
      </div>
    </div>

    <div class="col-lg-6">
      <div class="address-panel p-3 h-100">
        <h5 class="mb-3">Add New Address</h5>
        <form method="post" action="{{ route('customer.addresses.store') }}">
          @csrf
          <div class="mb-2"><label class="form-label">Label</label><input class="form-control pill" name="label_name" value="{{ old('label_name', 'Home') }}" placeholder="Home, Work, Office"></div>
          <div class="mb-2">
            <label class="form-label">Pin Exact Location</label>
            <div id="map" class="address-map"></div>
            <input type="hidden" name="latitude" id="lat" value="{{ old('latitude') }}"><input type="hidden" name="longitude" id="lng" value="{{ old('longitude') }}">
            <button type="button" class="btn btn-outline-primary btn-sm pill mt-2" id="detectAddressBtn" onclick="detectMyLocation()"><i class="bi bi-crosshair me-1"></i>Detect My Location</button>
            <div class="small text-muted mt-2"><i class="bi bi-pin-map me-1"></i>Use detection or click the map to pin your exact delivery location.</div>
          </div>
          <div class="mb-2"><label class="form-label">Complete Address <span class="address-loading" id="addressLoading"><span class="spinner-border spinner-border-sm me-1" style="width:.7rem;height:.7rem"></span>Fetching address...</span></label><textarea class="form-control pill" name="full_address" id="address" rows="2" placeholder="House no., Street, Barangay, City/Province" required>{{ old('full_address') }}</textarea></div>
          <div class="form-check mb-3"><input class="form-check-input" type="checkbox" value="1" id="makeDefault" name="make_default"><label class="form-check-label" for="makeDefault">Set as Default</label></div>
          <button class="btn btn-primary pill"><i class="bi bi-save me-1"></i>Save Address</button>
        </form>
      </div>
    </div>
  </div>
</div>
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
@php
  $defLat = $defaultAddr->latitude ?? 14.5995;
  $defLng = $defaultAddr->longitude ?? 120.9842;
@endphp
let marker = null;
let editMaps = {};
const map = L.map('map').setView([{{ $defLat }}, {{ $defLng }}], 14);
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
@if($defaultAddr)
  marker = L.marker([{{ $defaultAddr->latitude }}, {{ $defaultAddr->longitude }}], { draggable: true }).addTo(map);
  document.getElementById('lat').value = {{ $defaultAddr->latitude }};
  document.getElementById('lng').value = {{ $defaultAddr->longitude }};
  marker.on('dragend', e => { const ll = e.target.getLatLng(); setAddLatLng(ll.lat, ll.lng, true); });
@endif
function toggleEditAddress(id, lat, lng) {
  const el = document.getElementById('editAddress' + id);
  if (!el) return;
  const opening = !el.classList.contains('is-open');
  document.querySelectorAll('.address-edit-panel.is-open').forEach(panel => { if (panel.id !== el.id) panel.classList.remove('is-open'); });
  el.classList.toggle('is-open', opening);
  if (opening) setTimeout(() => initEditMap(id, lat, lng), 80);
}
function formatAddress(data) {
  const a = data.address || {};
  const parts = [a.house_number ? (a.house_number + ' ' + (a.road || '')) : a.road, a.suburb || a.village || a.neighbourhood, a.city_district || a.county, a.city || a.town || a.municipality, a.state].filter(Boolean);
  return parts.length > 0 ? parts.join(', ') : data.display_name;
}
async function reverseGeocodeTo(lat, lng, fieldId, loadingId) {
  const field = document.getElementById(fieldId);
  const indicator = document.getElementById(loadingId);
  if (indicator) indicator.style.display = 'inline';
  try {
    const ctrl = new AbortController(); setTimeout(() => ctrl.abort(), 6000);
    const res = await fetch(`/api/geocode/reverse?lat=${lat}&lng=${lng}`, { signal: ctrl.signal });
    const data = await res.json();
    if (field && data && data.display_name) field.value = formatAddress(data);
  } catch (e) {
  } finally {
    if (indicator) indicator.style.display = 'none';
  }
}
function setAddLatLng(lat, lng, lookup) {
  document.getElementById('lat').value = lat;
  document.getElementById('lng').value = lng;
  if (lookup) reverseGeocodeTo(lat, lng, 'address', 'addressLoading');
}
function setMarkerAt(latlng) {
  if (marker) marker.setLatLng(latlng);
  else {
    marker = L.marker(latlng, { draggable: true }).addTo(map);
    marker.on('dragend', e => { const ll = e.target.getLatLng(); setAddLatLng(ll.lat, ll.lng, true); });
  }
  setAddLatLng(latlng.lat, latlng.lng, true);
}
function initEditMap(id, lat, lng) {
  const mapId = 'editMap' + id;
  const latField = document.getElementById('editLat' + id);
  const lngField = document.getElementById('editLng' + id);
  const startLat = parseFloat(latField?.value || lat || 14.5995);
  const startLng = parseFloat(lngField?.value || lng || 120.9842);
  if (!editMaps[id]) {
    const editMap = L.map(mapId).setView([startLat, startLng], 16);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(editMap);
    const editMarker = L.marker([startLat, startLng], { draggable: true }).addTo(editMap);
    const sync = ll => {
      latField.value = ll.lat;
      lngField.value = ll.lng;
      reverseGeocodeTo(ll.lat, ll.lng, 'editFullAddress' + id, 'editAddressLoading' + id);
    };
    editMarker.on('dragend', e => sync(e.target.getLatLng()));
    editMap.on('click', e => { editMarker.setLatLng(e.latlng); sync(e.latlng); });
    editMaps[id] = { map: editMap, marker: editMarker };
  } else {
    editMaps[id].map.invalidateSize();
    editMaps[id].map.setView([startLat, startLng], 16);
    editMaps[id].marker.setLatLng([startLat, startLng]);
  }
  setTimeout(() => editMaps[id].map.invalidateSize(), 120);
}
function detectPosition(btn, onSuccess) {
  if (!navigator.geolocation) { alert('Location detection is not supported by this browser.'); return; }
  const original = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Detecting...';
  navigator.geolocation.getCurrentPosition(pos => {
    btn.disabled = false;
    btn.innerHTML = original;
    onSuccess(pos.coords.latitude, pos.coords.longitude);
  }, () => {
    btn.disabled = false;
    btn.innerHTML = original;
    alert('Could not detect your location. Please allow location access or pin manually on the map.');
  }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 });
}
function detectMyLocation() {
  detectPosition(document.getElementById('detectAddressBtn'), (lat, lng) => {
    const latlng = L.latLng(lat, lng);
    map.setView(latlng, 17);
    setMarkerAt(latlng);
  });
}
function detectEditLocation(id) {
  const btn = document.getElementById('detectEditBtn' + id);
  if (!editMaps[id]) initEditMap(id, document.getElementById('editLat' + id)?.value, document.getElementById('editLng' + id)?.value);
  detectPosition(btn, (lat, lng) => {
    const latlng = L.latLng(lat, lng);
    editMaps[id].map.setView(latlng, 17);
    editMaps[id].marker.setLatLng(latlng);
    document.getElementById('editLat' + id).value = lat;
    document.getElementById('editLng' + id).value = lng;
    reverseGeocodeTo(lat, lng, 'editFullAddress' + id, 'editAddressLoading' + id);
  });
}
map.on('click', e => setMarkerAt(e.latlng));
</script>
@endpush
@endsection