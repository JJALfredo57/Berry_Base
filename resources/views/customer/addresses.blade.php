@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
.address-card{border:1px solid #e5e7eb;border-radius:.85rem;background:#fff;box-shadow:0 8px 22px rgba(15,23,42,.05)}
.address-actions .btn{border-radius:999px}
.address-map{height:320px;border-radius:.85rem;overflow:hidden;border:1.5px solid color-mix(in srgb,var(--primary) 18%,#e5e7eb)}
@media(max-width:575.98px){.address-actions{width:100%}.address-actions .btn,.address-actions form{width:100%}}
</style>
@endpush
@section('content')
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-end mb-3 flex-wrap gap-2">
    <div><h2 class="mb-0">My Addresses</h2><div class="text-muted">Manage saved delivery locations and choose your default checkout address.</div></div>
    <a class="btn btn-outline-secondary pill" href="{{ route('customer.profile') }}">Back to Profile</a>
  </div>
  @if(session('msg'))<div class="alert alert-success">{{ session('msg') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card p-3 h-100">
        <div class="d-flex align-items-center justify-content-between mb-3"><h5 class="mb-0">Saved Addresses</h5><span class="badge text-bg-light">{{ $list->count() }} active</span></div>
        <div class="d-grid gap-2">
          @forelse($list as $a)
          <div class="address-card p-3">
            <div class="d-flex justify-content-between gap-3 flex-wrap">
              <div class="flex-grow-1">
                <div class="fw-semibold"><i class="bi bi-geo-alt me-1" style="color:var(--primary)"></i>{{ $a->label_name ?: 'Address' }} @if((int)$a->is_default === 1)<span class="badge text-bg-success ms-2">Default</span>@endif</div>
                <div class="small text-muted mt-1">{{ $a->full_address }}</div>
                <div class="small text-muted mt-1">Lat: {{ $a->latitude }}, Lng: {{ $a->longitude }}</div>
              </div>
              <div class="address-actions d-flex gap-1 flex-wrap justify-content-end">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleEditAddress({{ $a->id }})"><i class="bi bi-pencil-square me-1"></i>Edit</button>
                @if((int)$a->is_default !== 1)
                <form method="POST" action="{{ route('customer.addresses.set_default', $a->id) }}">@csrf<button type="submit" class="btn btn-sm btn-outline-primary">Set Default</button></form>
                @endif
                <form method="POST" action="{{ route('customer.addresses.archive', $a->id) }}">@csrf<button type="submit" class="btn btn-sm btn-outline-danger" data-cs-confirm="Archive this address? It will be hidden from checkout but can be restored." data-cs-title="Archive Address" data-cs-ok="Archive"><i class="bi bi-archive me-1"></i>Archive</button></form>
              </div>
            </div>
            <form method="POST" action="{{ route('customer.addresses.update', $a->id) }}" id="editAddress{{ $a->id }}" class="mt-3 p-3 rounded-3" style="display:none;background:#f8fafc;border:1px solid #e5e7eb">
              @csrf
              <div class="row g-2">
                <div class="col-sm-5"><label class="form-label small fw-semibold">Label</label><input class="form-control pill" name="label_name" value="{{ $a->label_name ?: 'Address' }}"></div>
                <div class="col-sm-7"><label class="form-label small fw-semibold">Complete Address</label><textarea class="form-control pill" name="full_address" rows="2">{{ $a->full_address }}</textarea></div>
              </div>
              <input type="hidden" name="latitude" value="{{ $a->latitude }}"><input type="hidden" name="longitude" value="{{ $a->longitude }}">
              <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="make_default" id="editDefault{{ $a->id }}" {{ (int)$a->is_default === 1 ? 'checked' : '' }}><label class="form-check-label small" for="editDefault{{ $a->id }}">Set as default</label></div>
              <div class="d-flex justify-content-end gap-2 mt-2"><button type="button" class="btn btn-sm btn-outline-secondary pill" onclick="toggleEditAddress({{ $a->id }})">Cancel</button><button class="btn btn-sm btn-primary pill">Save Changes</button></div>
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
      <div class="card p-3 h-100">
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
          <div class="mb-2"><label class="form-label">Complete Address <span id="addressLoading" style="display:none;font-size:.75rem;color:var(--primary);font-weight:400"><span class="spinner-border spinner-border-sm me-1" style="width:.7rem;height:.7rem"></span>Fetching address...</span></label><textarea class="form-control pill" name="full_address" id="address" rows="2" placeholder="House no., Street, Barangay, City/Province" required>{{ old('full_address') }}</textarea></div>
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
const map = L.map('map').setView([{{ $defLat }}, {{ $defLng }}], 14);
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
@if($defaultAddr)
  marker = L.marker([{{ $defaultAddr->latitude }}, {{ $defaultAddr->longitude }}], { draggable: true }).addTo(map);
  document.getElementById('lat').value = {{ $defaultAddr->latitude }};
  document.getElementById('lng').value = {{ $defaultAddr->longitude }};
  marker.on('dragend', e => { const ll = e.target.getLatLng(); document.getElementById('lat').value = ll.lat; document.getElementById('lng').value = ll.lng; reverseGeocode(ll.lat, ll.lng); });
@endif
function toggleEditAddress(id) { const el = document.getElementById('editAddress' + id); if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none'; }
async function reverseGeocode(lat, lng) {
  const field = document.getElementById('address');
  const indicator = document.getElementById('addressLoading');
  if (indicator) indicator.style.display = 'inline';
  try {
    const ctrl = new AbortController(); setTimeout(() => ctrl.abort(), 6000);
    const res = await fetch(`/api/geocode/reverse?lat=${lat}&lng=${lng}`, { signal: ctrl.signal });
    const data = await res.json();
    if (data && data.display_name) {
      const a = data.address || {};
      const parts = [a.house_number ? (a.house_number + ' ' + (a.road || '')) : a.road, a.suburb || a.village || a.neighbourhood, a.city_district || a.county, a.city || a.town || a.municipality, a.state].filter(Boolean);
      field.value = parts.length > 0 ? parts.join(', ') : data.display_name;
    }
  } catch (e) {} finally { if (indicator) indicator.style.display = 'none'; }
}
function setMarkerAt(latlng) {
  if (marker) marker.setLatLng(latlng);
  else {
    marker = L.marker(latlng, { draggable: true }).addTo(map);
    marker.on('dragend', e => { const ll = e.target.getLatLng(); document.getElementById('lat').value = ll.lat; document.getElementById('lng').value = ll.lng; reverseGeocode(ll.lat, ll.lng); });
  }
  document.getElementById('lat').value = latlng.lat;
  document.getElementById('lng').value = latlng.lng;
  reverseGeocode(latlng.lat, latlng.lng);
}
function detectMyLocation() {
  const btn = document.getElementById('detectAddressBtn');
  if (!navigator.geolocation) { alert('Location detection is not supported by this browser.'); return; }
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Detecting...';
  navigator.geolocation.getCurrentPosition(pos => {
    btn.disabled = false; btn.innerHTML = '<i class="bi bi-crosshair me-1"></i>Detect My Location';
    const latlng = L.latLng(pos.coords.latitude, pos.coords.longitude);
    map.setView(latlng, 17); setMarkerAt(latlng);
  }, () => {
    btn.disabled = false; btn.innerHTML = '<i class="bi bi-crosshair me-1"></i>Detect My Location';
    alert('Could not detect your location. Please allow location access or pin manually on the map.');
  }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 });
}
map.on('click', e => setMarkerAt(e.latlng));
</script>
@endpush
@endsection