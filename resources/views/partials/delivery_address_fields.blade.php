@php
  $prefix = $prefix ?? 'delivery';
  $title = $title ?? 'Delivery Address Details';
@endphp
<input type="hidden" name="_structured_address" value="1">
<div class="delivery-address-fields rounded-3 p-3 mb-3" style="background:#f8fafc;border:1px solid #e5e7eb">
  <div class="fw-bold small mb-2"><i class="bi bi-geo-alt me-1" style="color:var(--primary)"></i>{{ $title }}</div>
  <div class="row g-2">
    <div class="col-sm-6">
      <label class="form-label small fw-semibold">House / Unit / Building No.</label>
      <input class="form-control" name="address_house" id="{{ $prefix }}AddressHouse" value="{{ old('address_house') }}" placeholder="House 12, Unit 3B">
    </div>
    <div class="col-sm-6">
      <label class="form-label small fw-semibold">Street / Road</label>
      <input class="form-control" name="address_street" id="{{ $prefix }}AddressStreet" value="{{ old('address_street') }}" placeholder="Rizal Street">
    </div>
    <div class="col-sm-6">
      <label class="form-label small fw-semibold">Subdivision / Village / Purok / Sitio</label>
      <input class="form-control" name="address_subdivision" id="{{ $prefix }}AddressSubdivision" value="{{ old('address_subdivision') }}" placeholder="Purok 2 or Villa Subdivision">
    </div>
    <div class="col-sm-6">
      <label class="form-label small fw-semibold">Barangay</label>
      <input class="form-control" name="address_barangay" id="{{ $prefix }}AddressBarangay" value="{{ old('address_barangay') }}" placeholder="Poblacion">
    </div>
    <div class="col-sm-6">
      <label class="form-label small fw-semibold">City / Municipality</label>
      <input class="form-control" name="address_city" id="{{ $prefix }}AddressCity" value="{{ old('address_city') }}" placeholder="Bautista">
    </div>
    <div class="col-sm-4">
      <label class="form-label small fw-semibold">Province</label>
      <input class="form-control" name="address_province" id="{{ $prefix }}AddressProvince" value="{{ old('address_province') }}" placeholder="Pangasinan">
    </div>
    <div class="col-sm-2">
      <label class="form-label small fw-semibold">Postal Code</label>
      <input class="form-control" name="address_postal_code" id="{{ $prefix }}AddressPostal" value="{{ old('address_postal_code') }}" inputmode="numeric" placeholder="2424">
    </div>
    <div class="col-12">
      <label class="form-label small fw-semibold">Landmark</label>
      <input class="form-control" name="address_landmark" id="{{ $prefix }}AddressLandmark" value="{{ old('address_landmark') }}" placeholder="Near 7-Eleven, blue gate, beside chapel">
    </div>
  </div>
  <div class="form-text mt-2"><i class="bi bi-info-circle me-1"></i>These details are required for delivery and must match the pinned map location.</div>
</div>