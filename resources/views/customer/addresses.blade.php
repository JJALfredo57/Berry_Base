@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush
@section('content')
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-end mb-3">
    <div><h2 class="mb-0">My Addresses</h2><div class="text-muted">Add multiple addresses, delete, and set default.</div></div>
    <a class="btn btn-outline-secondary pill" href="{{ route('customer.checkout') }}">Back to Checkout</a>
  </div>
  @if(session('msg'))<div class="alert alert-success">{{ session('msg') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card p-3">
        <h5 class="mb-3">Saved Addresses</h5>
        <div class="list-group">
          @forelse($list as $a)
          <div class="list-group-item">
            <div class="d-flex justify-content-between">
              <div>
                <div class="fw-semibold">{{ $a->label_name ?: 'Address' }} @if((int)$a->is_default === 1)<span class="badge text-bg-success ms-2">Default</span>@endif</div>
                <div class="small text-muted">{{ $a->full_address }}</div>
                <div class="small text-muted">Lat: {{ $a->latitude }}, Lng: {{ $a->longitude }}</div>
              </div>
              <div class="text-end">
                <a class="btn btn-sm btn-outline-primary pill mb-1" href="{{ route('customer.addresses.default', $a->id) }}">Set Default</a>
                <a class="btn btn-sm btn-outline-danger pill" href="{{ route('customer.addresses.destroy', $a->id) }}" onclick="confirmDelete('This address will be permanently removed.', () => window.location=this.href); return false;">Delete</a>
              </div>
            </div>
          </div>
          @empty
          <div class="list-group-item text-muted">No addresses yet.</div>
          @endforelse
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card p-3">
        <h5 class="mb-3">Add New Address</h5>
        <form method="post" action="{{ route('customer.addresses.store') }}">
          @csrf
          <div class="mb-2"><label class="form-label">Label (e.g., Home, Work)</label><input class="form-control pill" name="label_name" value="Home"></div>
          <div class="mb-2">
            <label class="form-label">Pin Exact Location</label>
            <div id="map" style="height:320px;border-radius:16px;overflow:hidden;"></div>
            <input type="hidden" name="latitude" id="lat">
            <input type="hidden" name="longitude" id="lng">
            <div class="small text-muted mt-2"><i class="bi bi-pin-map me-1"></i>Click anywhere on the map to auto-fill your address.</div>
          </div>
          <div class="mb-2">
            <label class="form-label">
              Complete Address
              <span id="addressLoading" style="display:none;font-size:.75rem;color:var(--primary);font-weight:400">
                <span class="spinner-border spinner-border-sm me-1" style="width:.7rem;height:.7rem"></span>
                Fetching address…
              </span>
            </label>
            <textarea class="form-control pill" name="full_address" id="address" rows="2" placeholder="House no., Street, Barangay, City/Province" required></textarea>
          </div>
          <div class="form-check mb-3"><input class="form-check-input" type="checkbox" value="1" id="makeDefault" name="make_default"><label class="form-check-label" for="makeDefault">Set as Default</label></div>
          <button class="btn btn-primary pill">Save Address</button>
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
  marker.on('dragend', e => {
    const ll = e.target.getLatLng();
    document.getElementById('lat').value = ll.lat;
    document.getElementById('lng').value = ll.lng;
    reverseGeocode(ll.lat, ll.lng);
  });
@endif

async function reverseGeocode(lat, lng) {
  const field     = document.getElementById('address');
  const indicator = document.getElementById('addressLoading');
  if (indicator) indicator.style.display = 'inline';
  try {
    const res  = await fetch(
      'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=' + lng + '&addressdetails=1',
      { headers: { 'Accept-Language': 'en', 'User-Agent': 'CakeshopApp/1.0' } }
    );
    const data = await res.json();
    if (data && data.display_name) {
      const a     = data.address || {};
      const parts = [
        a.house_number ? (a.house_number + ' ' + (a.road || '')) : a.road,
        a.suburb || a.village || a.neighbourhood,
        a.city_district || a.county,
        a.city || a.town || a.municipality,
        a.state,
      ].filter(Boolean);
      field.value = parts.length > 0 ? parts.join(', ') : data.display_name;
    }
  } catch (e) {
    // Silent fail — user can type manually
  } finally {
    if (indicator) indicator.style.display = 'none';
  }
}

function setMarkerAt(latlng) {
  if (marker) marker.setLatLng(latlng);
  else {
    marker = L.marker(latlng, { draggable: true }).addTo(map);
    marker.on('dragend', e => {
      const ll = e.target.getLatLng();
      document.getElementById('lat').value = ll.lat;
      document.getElementById('lng').value = ll.lng;
      reverseGeocode(ll.lat, ll.lng);
    });
  }
  document.getElementById('lat').value = latlng.lat;
  document.getElementById('lng').value = latlng.lng;
  reverseGeocode(latlng.lat, latlng.lng);
}

map.on('click', e => setMarkerAt(e.latlng));
</script>
@endpush
@endsection
