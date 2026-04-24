@extends('layouts.app')
@section('content')
<div class="container-fluid py-4">
  <div class="row justify-content-center">
    <div class="col-lg-10">

      <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('customer.catalog') }}" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrow-left"></i>
        </a>
        <div>
          <h4 class="fw-bold mb-0">
            <i class="bi bi-palette me-2" style="color:var(--primary)"></i>Customize Your Cake
          </h4>
          <p class="text-muted small mb-0">Build your dream cake — we'll bake it exactly as you envision it.</p>
        </div>
      </div>

      @if(session('error'))
        <div class="alert alert-danger border-0"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
      @endif



      <div class="row g-4">
        <div class="col-lg-8">
          <form action="{{ route('customer.custom_order.store') }}" method="POST" id="customOrderForm" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="shop_slug" value="{{ $targetShop->shop_slug ?? '' }}">

            {{-- 1. Cake Details --}}
            <div class="card mb-3">
              <div class="card-body p-4">
                <h6 class="fw-bold mb-3">
                  <i class="bi bi-cake2 me-2" style="color:var(--primary)"></i>Cake Details
                </h6>
                <div class="mb-3">
                  <label class="form-label fw-semibold small">Cake / Occasion Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="cake_name"
                         value="{{ old('cake_name') }}"
                         placeholder="e.g. Birthday Cake for Maria, Wedding Cake"
                         required maxlength="120">
                </div>

                {{-- Reference Images --}}
                <div class="mb-3">
                  <label class="form-label fw-semibold small">
                    📎 Reference / Inspiration Images
                    <span class="text-muted fw-normal">(optional, up to 5)</span>
                  </label>
                  <div class="border rounded-3 p-3" style="border-style:dashed!important;border-color:var(--primary)!important;background:#fff9fb">
                    <div id="refImgPreviewStrip" class="d-flex flex-wrap gap-2 mb-2" style="min-height:0"></div>
                    <label for="refImgInput" class="btn btn-outline-primary btn-sm" id="refImgBtn">
                      <i class="bi bi-image me-1"></i>Choose Images
                      <input type="file" id="refImgInput" name="reference_images[]"
                             accept="image/*" multiple hidden onchange="addRefImages(this)">
                    </label>
                    <span class="small text-muted ms-2" id="refImgCount">0 / 5 selected</span>
                    <div class="text-muted mt-1" style="font-size:.75rem">
                      <i class="bi bi-info-circle me-1"></i>Upload photos of the cake design you want — from Pinterest, other bakeries, or your own ideas.
                    </div>
                  </div>
                </div>
                <div class="row g-3">
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small">Flavor <span class="text-danger">*</span></label>
                    <select class="form-select" name="flavor" required>
                      <option value="">-- Select Flavor --</option>
                      @foreach($flavors as $f)
                        <option value="{{ $f->label }}" {{ old('flavor')==$f->label ? 'selected':'' }}>
                          {{ $f->label }}{{ $f->description ? ' — '.$f->description : '' }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small">Quantity</label>
                    <input type="number" class="form-control" name="quantity" min="1" max="10"
                           value="{{ old('quantity',1) }}" onchange="updatePriceSummary()">
                  </div>
                </div>
              </div>
            </div>

            {{-- 2. Size & Layers --}}
            <div class="card mb-3">
              <div class="card-body p-4">
                <h6 class="fw-bold mb-3">
                  <i class="bi bi-rulers me-2" style="color:var(--primary)"></i>Size &amp; Layers
                  <span class="badge ms-1" style="background:#fff0f5;color:var(--primary);font-size:.7rem">Affects price</span>
                </h6>
                <div class="row g-3">
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small">Diameter <span class="text-danger">*</span></label>
                    <select class="form-select" name="size" required onchange="updatePriceSummary()">
                      <option value="">-- Select Size --</option>
                      @foreach($sizes as $s)
                        <option value="{{ $s->label }}" {{ old('size')==$s->label ? 'selected':'' }}>
                          {{ $s->label }}{{ $s->price > 0 ? ' (+₱'.number_format($s->price,2).')' : '' }}
                          {{ $s->description ? ' — '.$s->description : '' }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small">Number of Layers</label>
                    <select class="form-select" name="layers">
                      <option value="">-- Select Layers --</option>
                      @foreach($layers as $l)
                        <option value="{{ $l->label }}" {{ old('layers')==$l->label ? 'selected':'' }}>
                          {{ $l->label }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
              </div>
            </div>

            {{-- 3. Design Complexity --}}
            <div class="card mb-3">
              <div class="card-body p-4">
                <h6 class="fw-bold mb-3">
                  <i class="bi bi-magic me-2" style="color:var(--primary)"></i>Design Complexity
                  <span class="badge ms-1" style="background:#fff0f5;color:var(--primary);font-size:.7rem">Affects price</span>
                </h6>
                @if($complexities->count() > 0)
                <div class="row g-2">
                  @foreach($complexities as $c)
                  <div class="col-sm-6">
                    <label class="complexity-card d-block p-3 rounded"
                           style="border:2px solid #e9ecef;cursor:pointer;transition:.15s"
                           onmouseenter="if(!this.querySelector('input').checked)this.style.borderColor='var(--primary)'"
                           onmouseleave="if(!this.querySelector('input').checked)this.style.borderColor='#e9ecef'">
                      <div class="d-flex align-items-start gap-2">
                        <input type="radio" name="design_complexity" value="{{ $c->label }}"
                               class="form-check-input mt-1 flex-shrink-0"
                               {{ (old('design_complexity', $complexities->first()?->label) == $c->label) ? 'checked' : '' }}
                               onchange="updatePriceSummary(); highlightComplexity(this)">
                        <div>
                          <div class="fw-semibold small">{{ $c->label }}</div>
                          @if($c->description)
                            <div class="text-muted" style="font-size:.72rem">{{ $c->description }}</div>
                          @endif
                          <div class="fw-bold small mt-1" style="color:var(--primary)">
                            {{ $c->price > 0 ? '+₱'.number_format($c->price,2) : 'Included' }}
                          </div>
                        </div>
                      </div>
                    </label>
                  </div>
                  @endforeach
                </div>
                @else
                <p class="text-muted small">No complexity options set yet. Contact the shop.</p>
                @endif
              </div>
            </div>

            {{-- 4. Dedication --}}
            <div class="card mb-3">
              <div class="card-body p-4">
                <h6 class="fw-bold mb-3">
                  <i class="bi bi-chat-heart me-2" style="color:var(--primary)"></i>Cake Message / Dedication
                </h6>
                <div class="mb-3">
                  <label class="form-label fw-semibold small">Message on Cake <span class="text-muted fw-normal">(optional)</span></label>
                  <input type="text" class="form-control" name="dedication"
                         value="{{ old('dedication') }}"
                         placeholder='e.g. "Happy 18th Birthday, Maria!"'
                         maxlength="120">
                  <div class="form-text">This text will be written on the cake.</div>
                </div>
                <div>
                  <label class="form-label fw-semibold small">Additional Instructions <span class="text-muted fw-normal">(optional)</span></label>
                  <textarea class="form-control" name="custom_note" rows="3"
                            placeholder="Color scheme, theme references, allergies, etc.">{{ old('custom_note') }}</textarea>
                </div>
              </div>
            </div>

            {{-- 5. Add-ons --}}
            @if($addonCategories->count() > 0)
            <div class="card mb-3">
              <div class="card-body p-4">
                <h6 class="fw-bold mb-1">
                  <i class="bi bi-stars me-2" style="color:var(--primary)"></i>Add-ons &amp; Extras
                  <span class="badge ms-2" style="background:var(--primary-light);color:var(--primary);font-size:.72rem">Optional</span>
                </h6>
                <p class="text-muted small mb-4">Select any extras to include with your custom cake.</p>
                @foreach($addonCategories as $cat)
                @php $catAddons = $addonsByCategory[$cat->id] ?? collect(); @endphp
                @if($catAddons->count() > 0)
                <div class="mb-4">
                  <div class="d-flex align-items-center gap-2 mb-2 pb-1" style="border-bottom:2px solid var(--primary-light)">
                    <i class="bi {{ $cat->icon }}" style="color:var(--primary)"></i>
                    <span class="fw-semibold small">{{ $cat->name }}</span>
                  </div>
                  <div class="row g-2">
                    @foreach($catAddons as $addon)
                    <div class="col-sm-6">
                      <label class="addon-card d-flex align-items-center gap-2 p-2 rounded"
                             style="border:1.5px solid #e9ecef;cursor:pointer;transition:.15s"
                             data-price="{{ $addon->price }}"
                             onmouseenter="this.style.borderColor='var(--primary)'"
                             onmouseleave="if(!this.querySelector('input').checked)this.style.borderColor='#e9ecef'">
                        <input type="checkbox" name="addons[]" value="{{ $addon->id }}"
                               class="addon-check form-check-input flex-shrink-0 mt-0"
                               style="width:18px;height:18px"
                               onchange="updatePriceSummary(); highlightAddonCard(this)">
                        <div class="flex-grow-1">
                          <div class="fw-semibold small">{{ $addon->name }}</div>
                          @if($addon->description)
                            <div class="text-muted" style="font-size:.72rem">{{ $addon->description }}</div>
                          @endif
                        </div>
                        <div class="text-end flex-shrink-0">
                          @if($addon->price > 0)
                            <span class="fw-bold small" style="color:var(--primary)">+₱{{ number_format($addon->price,2) }}</span>
                          @else
                            <span class="badge" style="background:#d1fae5;color:#065f46;font-size:.68rem">FREE</span>
                          @endif
                        </div>
                      </label>
                    </div>
                    @endforeach
                  </div>
                </div>
                @endif
                @endforeach
              </div>
            </div>
            @endif

            {{-- 6. Fulfillment --}}
            <div class="card mb-3">
              <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-truck me-2" style="color:var(--primary)"></i>Fulfillment</h6>
                <div class="d-flex gap-3 mb-3">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="fulfillment_type" value="Pickup"
                           id="pickup" checked onchange="toggleDelivery()">
                    <label class="form-check-label fw-semibold" for="pickup"><i class="bi bi-bag me-1"></i>Pickup</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="fulfillment_type" value="Delivery"
                           id="delivery" onchange="toggleDelivery()">
                    <label class="form-check-label fw-semibold" for="delivery"><i class="bi bi-bicycle me-1"></i>Delivery</label>
                  </div>
                </div>

                <div id="deliverySection" style="display:none">
                  <div class="mb-3">
                    <label class="form-label fw-semibold small">
                      Select Barangay <span class="text-danger">*</span>
                    </label>
                    <select class="form-select" name="delivery_zone" id="zoneSelect" onchange="updateFee()">
                      <option value="">-- Select your Barangay --</option>
                      @php
                        $zoneTypeLabels = ['free'=>'Poblacion','near'=>'Nearby','mid'=>'Mid-range','far'=>'Far','ooc'=>'Out of Coverage'];
                        $grouped = $deliveryZones->groupBy('zone_type');
                        $order   = ['free','near','mid','far','ooc'];
                      @endphp
                      @foreach($order as $typeKey)
                        @php $group = $grouped[$typeKey] ?? collect(); @endphp
                        @if($group->count() > 0)
                        <optgroup label="{{ $zoneTypeLabels[$typeKey] ?? $typeKey }} — {{ $typeKey === 'free' ? 'FREE' : ($typeKey === 'ooc' ? '₱250+' : '₱'.$group->first()->fee) }}">
                          @foreach($group as $z)
                          <option value="{{ $z->barangay }}" data-fee="{{ $z->fee }}" data-type="{{ $z->zone_type }}">
                            {{ $z->barangay }}{{ $z->fee == 0 ? ' (Free)' : ' — ₱'.number_format($z->fee,2) }}
                          </option>
                          @endforeach
                        </optgroup>
                        @endif
                      @endforeach
                    </select>
                    <input type="hidden" name="delivery_fee" id="deliveryFeeInput" value="0">
                    <input type="hidden" name="service_charge" id="serviceChargeInput" value="0">
                    <div id="oocNotice" style="display:none" class="alert border-0 mt-2 py-2" style="background:#fff1f2">
                      <i class="bi bi-info-circle me-1" style="color:#e11d48"></i>
                      <span class="small" style="color:#9f1239">
                        <strong>Out of Coverage</strong> — Delivery fee starts at ₱250. Admin will confirm after reviewing your location.
                      </span>
                    </div>
                  </div>
                  <div class="mb-3 p-3 rounded" id="feePreview" style="background:#f0fdf4;display:none">
                    <div class="d-flex align-items-center justify-content-between">
                      <div class="small fw-semibold" style="color:#166534"><i class="bi bi-bicycle me-1"></i>Delivery Fee</div>
                      <div class="fw-bold" id="feePreviewAmount" style="color:#166534">₱0.00</div>
                    </div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-semibold small">Pin Your Location</label>
                    <div id="map" style="height:240px;border-radius:.9rem;border:1px solid #dee2e6"></div>
                    <input type="hidden" name="latitude"  id="lat">
                    <input type="hidden" name="longitude" id="lng">
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-semibold small">
                      Full Address
                      <span id="addressLoading" style="display:none;font-size:.75rem;color:var(--primary);font-weight:400">
                        <span class="spinner-border spinner-border-sm me-1" style="width:.7rem;height:.7rem"></span>
                        Fetching address…
                      </span>
                    </label>
                    <textarea class="form-control" name="address" id="addressField" rows="2"
                      placeholder="Click the map to auto-fill, or type your address here">{{ $defaultAddr ? $defaultAddr->full_address : '' }}</textarea>
                    <div class="form-text"><i class="bi bi-pin-map me-1"></i>Click anywhere on the map to auto-fill your address.</div>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="save_default_address" id="saveAddr">
                    <label class="form-check-label small" for="saveAddr">Save as default address</label>
                  </div>
                </div>

                <div class="row g-3 mt-1">
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small">Preferred Date</label>
                    <input type="date" class="form-control" name="schedule_date" min="{{ date('Y-m-d') }}">
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small">Preferred Time Slot</label>
                    <select class="form-select" name="time_slot">
                      <option value="">-- Select Time Slot --</option>
                      @foreach($timeSlots as $slot)
                        <option value="{{ $slot->label }}">{{ $slot->label }}</option>
                      @endforeach
                    </select>
                  </div>
                </div>
              </div>
            </div>

            {{-- 7. Payment --}}
            <div class="card mb-3">
              <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-credit-card me-2" style="color:var(--primary)"></i>Payment Method</h6>
                <div class="d-flex gap-3 flex-wrap">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="payment_method" value="COD" id="cod" checked>
                    <label class="form-check-label fw-semibold" for="cod"><i class="bi bi-cash-coin me-1"></i>Cash on Delivery</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="payment_method" value="GCash" id="gcash">
                    <label class="form-check-label fw-semibold" for="gcash">
                      <i class="bi bi-phone me-1"></i>GCash
                      @php $pmMode = \App\Helpers\CakeshopHelper::getPaymongoMode(); @endphp
                      @if($pmMode === 'test')
                        <span class="badge ms-1" style="background:#fef9c3;color:#854d0e;font-size:.65rem">TEST MODE</span>
                      @else
                        <span class="badge ms-1" style="background:#d1fae5;color:#065f46;font-size:.65rem">LIVE</span>
                      @endif
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-3 fw-semibold fs-5"
                    onclick="return confirmCustomOrder(this)">
              <i class="bi bi-palette me-2"></i>Place Custom Order
            </button>
          </form>
        </div>

        {{-- RIGHT: Price Summary --}}
        <div class="col-lg-4">
          <div class="card sticky-top" style="top:80px">
            <div class="card-body p-4">
              <h6 class="fw-bold mb-3">🎂 Price Summary</h6>
              <div class="d-flex justify-content-between small mb-2">
                <span class="text-muted">Base (Customized)</span>
                <span class="fw-semibold">₱1,200.00</span>
              </div>
              <div class="d-flex justify-content-between small mb-2" id="sizeSurchargeRow" style="display:none!important">
                <span class="text-muted">Size Surcharge</span>
                <span id="sizeSurchargeDisplay" class="fw-semibold" style="color:var(--primary)">+₱0.00</span>
              </div>
              <div class="d-flex justify-content-between small mb-2" id="complexityRow" style="display:none!important">
                <span class="text-muted">Design Complexity</span>
                <span id="complexityDisplay" class="fw-semibold" style="color:var(--primary)">+₱0.00</span>
              </div>
              <div id="addonSummary"></div>
              <div class="d-flex justify-content-between small mb-1" id="feeRow" style="display:none!important">
                <span class="text-muted">Delivery Fee</span>
                <span id="feeDisplay">₱0.00</span>
              </div>
              <div class="d-flex justify-content-between small mb-1" id="serviceRow" style="display:none!important">
                <span class="text-muted">Service/Gasoline</span>
                <span id="serviceDisplay">₱0.00</span>
              </div>
              <div class="d-flex justify-content-between small mb-1 text-muted" id="qtyRow" style="display:none">
                <span>× <span id="qtyDisplay">1</span> cake(s)</span>
                <span></span>
              </div>
              <hr class="my-2">
              <div class="d-flex justify-content-between fw-bold">
                <span>Total</span>
                <span id="totalDisplay" style="color:var(--primary);font-size:1.1rem">₱1,200.00</span>
              </div>
              <div class="mt-3 p-2 rounded small" style="background:#fff0f5;font-size:.75rem">
                <i class="bi bi-palette me-1" style="color:var(--primary)"></i>
                Starts at ₱1,200. Final price depends on size, design, and add-ons.
              </div>
              <div class="mt-3 pt-3 border-top">
                <div class="text-muted small fw-semibold mb-2">Your Info</div>
                <div class="small">
                  <div><i class="bi bi-person me-1"></i>{{ $customer->fullname }}</div>
                  <div><i class="bi bi-phone me-1"></i>{{ $customer->phone }}</div>
                  <div><i class="bi bi-envelope me-1"></i>{{ $customer->email }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ── DB-driven price maps (from PHP) ───────────────────────────
const SIZE_PRICES = {
  @foreach($sizes as $s)
    {!! json_encode($s->label) !!}: {{ (float)$s->price }},
  @endforeach
};
const COMPLEXITY_PRICES = {
  @foreach($complexities as $c)
    {!! json_encode($c->label) !!}: {{ (float)$c->price }},
  @endforeach
};
const BASE_CUSTOM = 1200;
// ─────────────────────────────────────────────────────────────
let deliveryFee = 0;
let map, marker;

function updatePriceSummary() {
  const qty             = parseInt(document.querySelector('[name=quantity]')?.value || 1);
  const size            = document.querySelector('[name=size]')?.value || '';
  const complexity      = document.querySelector('[name=design_complexity]:checked')?.value || '';
  const isDelivery      = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';
  const svcCharge       = parseFloat(document.getElementById('serviceChargeInput')?.value || 0);

  // Use the DB-driven price maps defined above
  const sizeSurcharge       = SIZE_PRICES[size] ?? 0;
  const complexitySurcharge = COMPLEXITY_PRICES[complexity] ?? 0;

  let addonTotal = 0;
  const addonDetails = [];
  document.querySelectorAll('.addon-check:checked').forEach(chk => {
    const card  = chk.closest('.addon-card');
    const price = parseFloat(card.dataset.price) || 0;
    const name  = card.querySelector('.fw-semibold.small').textContent.trim();
    addonTotal += price;
    addonDetails.push({ name, price });
  });

  const unitPrice = BASE_CUSTOM + sizeSurcharge + complexitySurcharge;
  const subtotal  = unitPrice * qty;
  const total     = subtotal + addonTotal + (isDelivery ? deliveryFee + svcCharge : 0);

  const sizeSurRow = document.getElementById('sizeSurchargeRow');
  sizeSurRow.style.display = sizeSurcharge > 0 ? 'flex' : 'none';
  if (sizeSurcharge > 0)
    document.getElementById('sizeSurchargeDisplay').textContent = '+₱' + sizeSurcharge.toFixed(2);

  const cplxRow = document.getElementById('complexityRow');
  cplxRow.style.display = complexitySurcharge > 0 ? 'flex' : 'none';
  if (complexitySurcharge > 0)
    document.getElementById('complexityDisplay').textContent = '+₱' + complexitySurcharge.toFixed(2);

  document.getElementById('addonSummary').innerHTML = addonDetails.map(d =>
    '<div class="d-flex justify-content-between small mb-1 text-muted">
       <span><i class="bi bi-check2 me-1" style="color:var(--primary)"></i>${d.name}</span>
       <span>${d.price > 0 ? '+₱'+d.price.toFixed(2) : 'FREE'}</span>
     </div>'
  ).join('');

  const qtyRow = document.getElementById('qtyRow');
  qtyRow.style.display = qty > 1 ? 'flex' : 'none';
  document.getElementById('qtyDisplay').textContent = qty;

  document.getElementById('totalDisplay').textContent =
    '₱' + total.toLocaleString('en-PH', {minimumFractionDigits:2});
}

function highlightComplexity(input) {
  document.querySelectorAll('.complexity-card').forEach(c => {
    c.style.borderColor = '#e9ecef';
    c.style.background  = '';
  });
  const card = input.closest('.complexity-card');
  card.style.borderColor = 'var(--primary)';
  card.style.background  = 'var(--primary-light)';
}

function highlightAddonCard(input) {
  const card = input.closest('.addon-card');
  card.style.borderColor = input.checked ? 'var(--primary)' : '#e9ecef';
  card.style.background  = input.checked ? 'var(--primary-light)' : '';
}

function toggleDelivery() {
  const isDelivery = document.querySelector('[name=fulfillment_type]:checked').value === 'Delivery';
  document.getElementById('deliverySection').style.display = isDelivery ? 'block' : 'none';
  if (isDelivery && !map) initMap();
  updateFee();
}

async function reverseGeocode(lat, lng) {
  const field     = document.getElementById('addressField');
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
  } catch (e) {}
  finally { if (indicator) indicator.style.display = 'none'; }
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

function initMap() {
  @php $defLat = $defaultAddr->latitude ?? 14.5995; $defLng = $defaultAddr->longitude ?? 120.9842; @endphp
  map = L.map('map').setView([{{ $defLat }}, {{ $defLng }}], 14);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
  @if($defaultAddr)
    marker = L.marker([{{ $defaultAddr->latitude }}, {{ $defaultAddr->longitude }}], {draggable:true}).addTo(map);
    document.getElementById('lat').value = {{ $defaultAddr->latitude }};
    document.getElementById('lng').value = {{ $defaultAddr->longitude }};
    marker.on('dragend', e => {
      const ll = e.target.getLatLng();
      document.getElementById('lat').value = ll.lat;
      document.getElementById('lng').value = ll.lng;
      reverseGeocode(ll.lat, ll.lng);
    });
  @endif
  map.on('click', e => setMarkerAt(e.latlng));
}

function updateFee() {
  const sel      = document.getElementById('zoneSelect');
  const opt      = sel?.options[sel.selectedIndex];
  const zoneType = opt?.dataset?.type || '';
  deliveryFee    = parseFloat(opt?.dataset?.fee || 0);
  const isDelivery = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';

  const oocNotice = document.getElementById('oocNotice');
  if (oocNotice) oocNotice.style.display = (zoneType === 'ooc') ? 'block' : 'none';

  const feePreview    = document.getElementById('feePreview');
  const feePreviewAmt = document.getElementById('feePreviewAmount');
  if (feePreview && opt && opt.value) {
    feePreview.style.display = 'block';
    feePreview.style.background = deliveryFee === 0 ? '#f0fdf4' : '#eff6ff';
    if (feePreviewAmt) {
      feePreviewAmt.style.color = deliveryFee === 0 ? '#166534' : '#1e40af';
      feePreviewAmt.textContent = deliveryFee === 0 ? 'FREE' :
        (zoneType === 'ooc' ? '₱250+ (to be confirmed)' : '₱' + deliveryFee.toFixed(2));
    }
  } else if (feePreview) {
    feePreview.style.display = 'none';
  }

  const feeRow = document.getElementById('feeRow');
  if (isDelivery && deliveryFee > 0) {
    if (feeRow) { feeRow.style.display = 'flex'; document.getElementById('feeDisplay').textContent = zoneType === 'ooc' ? '₱250+' : '₱' + deliveryFee.toFixed(2); }
  } else {
    if (feeRow) feeRow.style.display = 'none';
  }
  if (document.getElementById('deliveryFeeInput'))
    document.getElementById('deliveryFeeInput').value = deliveryFee;
  updatePriceSummary();
}

function confirmCustomOrder(btn) {
  const isDelivery = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';
  if (isDelivery) {
    const zone = document.getElementById('zoneSelect')?.value;
    if (!zone) { alert('Please select your barangay for delivery.'); return false; }
  }
  const total = document.getElementById('totalDisplay').textContent;
  cakeConfirm({ title: '📋 Confirm Custom Order?', message: 'Total: ' + total + ' — Your order will be reviewed by the baker.', icon: 'bi-cake2', okLabel: 'Place Order', onConfirm: () => document.getElementById('customOrderForm').submit() }); return false;
  if (ok) setTimeout(() => { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Placing Order…'; }, 10);
  return ok;
}

document.addEventListener('DOMContentLoaded', () => {
  const checked = document.querySelector('[name=design_complexity]:checked');
  if (checked) highlightComplexity(checked);
  updatePriceSummary();
});

// ── Reference image multi-select with preview ────────────────────────
let refFiles = [];
const MAX_REF = 5;

function addRefImages(input) {
  const newFiles = Array.from(input.files);
  const remaining = MAX_REF - refFiles.length;
  const toAdd = newFiles.slice(0, remaining);
  refFiles = [...refFiles, ...toAdd];
  input.value = ''; // reset so same file can be picked again
  renderRefPreviews();
}

function renderRefPreviews() {
  const strip = document.getElementById('refImgPreviewStrip');
  const count = document.getElementById('refImgCount');
  const btn   = document.getElementById('refImgBtn');
  strip.innerHTML = '';
  count.textContent = refFiles.length + ' / ' + MAX_REF + ' selected';
  btn.style.display = refFiles.length >= MAX_REF ? 'none' : '';

  refFiles.forEach((file, idx) => {
    const wrap = document.createElement('div');
    wrap.style = 'position:relative;display:inline-block';
    const img = document.createElement('img');
    img.style = 'width:72px;height:72px;object-fit:cover;border-radius:.5rem;border:2px solid var(--primary)';
    const reader = new FileReader();
    reader.onload = e => img.src = e.target.result;
    reader.readAsDataURL(file);
    const rm = document.createElement('button');
    rm.type = 'button'; rm.innerHTML = '✕';
    rm.style = 'position:absolute;top:-5px;right:-5px;width:18px;height:18px;border-radius:50%;background:#ef4444;border:none;color:white;font-size:.6rem;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0';
    rm.onclick = () => { refFiles.splice(idx, 1); renderRefPreviews(); syncRefInput(); };
    wrap.appendChild(img); wrap.appendChild(rm);
    strip.appendChild(wrap);
  });
  syncRefInput();
}

function syncRefInput() {
  // Sync the actual file input so form submits the correct files
  const input = document.getElementById('refImgInput');
  const dt = new DataTransfer();
  refFiles.forEach(f => dt.items.add(f));
  input.files = dt.files;
}
</script>
@endpush
