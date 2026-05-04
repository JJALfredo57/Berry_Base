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
                    <select class="form-select" name="layers" onchange="updatePriceSummary()">
                      <option value="">-- Select Layers --</option>
                      @foreach($layers as $l)
                        <option value="{{ $l->label }}" {{ old('layers')==$l->label ? 'selected':'' }}>
                          {{ $l->label }}@if($l->price > 0) (+&#8369;{{ number_format($l->price,2) }})@endif
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
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
                <p class="text-muted small mb-3">Add-ons are optional. Open this only if you want extra toppings, fillings, toppers, candles, or packaging.</p>
                <button type="button" class="btn btn-outline-primary w-100 fw-semibold mb-3"
                        id="addonToggleBtn"
                        onclick="toggleOptionalAddons()">
                  <i class="bi bi-plus-circle me-1"></i><span id="addonToggleLabel">Add optional add-ons</span>
                  <span id="addonSelectedCount" class="badge ms-2" style="display:none;background:var(--primary);color:#fff">0 selected</span>
                </button>
                <div id="optionalAddonsPanel" style="display:none">
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
                               onchange="updatePriceSummary(); highlightAddonCard(this); updateAddonPanelState()">
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
                  <div class="mt-2">
                    <label class="form-label fw-semibold small">
                      Add-on Instructions <span class="text-muted fw-normal">(optional)</span>
                    </label>
                    <textarea class="form-control" name="addon_instructions" rows="3"
                              placeholder="Example: put mango slices only on top, use gold topper, no nuts, write candle number 7, separate the candles in the box.">{{ old('addon_instructions') }}</textarea>
                    <div class="form-text">
                      This note will be shown to the seller's kitchen together with your selected add-ons after confirmation.
                    </div>
                  </div>
                </div>
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
                  {{-- Map + detect location --}}
                  <div class="mb-2">
                    <label class="form-label fw-semibold small">
                      <i class="bi bi-pin-map me-1" style="color:var(--primary)"></i>Pin Your Delivery Location
                      <span class="text-danger">*</span>
                    </label>
                    <div class="d-flex gap-2 mb-2 align-items-center flex-wrap">
                      <button type="button" class="btn btn-sm btn-outline-primary" onclick="detectMyLocation()" id="detectBtn">
                        <i class="bi bi-crosshair me-1"></i>Detect My Location
                      </button>
                      <span class="text-muted" style="font-size:.78rem">or click/tap anywhere on the map</span>
                    </div>
                    <div id="map" style="height:260px;border-radius:.9rem;border:1.5px solid #dee2e6"></div>
                    <input type="hidden" name="latitude"  id="lat">
                    <input type="hidden" name="longitude" id="lng">
                    <input type="hidden" name="delivery_zone"  id="deliveryZoneInput" value="">
                    <input type="hidden" name="delivery_fee"   id="deliveryFeeInput"  value="0">
                    <input type="hidden" name="service_charge" value="0">
                  </div>

                  {{-- Coverage status --}}
                  <div id="coverageStatus" style="display:none" class="mb-3 small"></div>

                  {{-- Fee + ETA display --}}
                  <div id="deliveryCalcBox" style="display:none" class="mb-3">
                    <div style="border-radius:.9rem;overflow:hidden;border:1.5px solid #ddd6fe;box-shadow:0 4px 16px rgba(99,102,241,.1)">
                      <div id="deliveryCalcHeader" style="padding:.7rem 1.1rem;background:linear-gradient(135deg,#6366f1 0%,#4f46e5 100%)">
                        <div class="d-flex align-items-center justify-content-between">
                          <span class="fw-semibold text-white" style="font-size:.85rem">
                            <i class="bi bi-bicycle me-2"></i>Delivery Quote
                          </span>
                          <span id="deliveryFreeTag" style="display:none;background:rgba(255,255,255,.25);color:#fff;font-size:.68rem;font-weight:700;border-radius:2rem;padding:2px 10px;letter-spacing:.05em">
                            FREE DELIVERY
                          </span>
                        </div>
                      </div>
                      <div style="background:#f5f3ff;padding:.9rem 1rem">
                        <div class="row g-0 text-center mb-2">
                          <div class="col-4" style="border-right:1px solid #ddd6fe">
                            <div class="fw-bold" id="distDisplay" style="font-size:1.05rem;color:#6366f1;line-height:1.2">—</div>
                            <div class="text-muted" style="font-size:.62rem;letter-spacing:.04em;text-transform:uppercase;margin-top:2px">Distance</div>
                          </div>
                          <div class="col-4" style="border-right:1px solid #ddd6fe">
                            <div class="fw-bold" id="calcFeeDisplay" style="font-size:1.05rem;color:#1e40af;line-height:1.2">₱0.00</div>
                            <div class="text-muted" style="font-size:.62rem;letter-spacing:.04em;text-transform:uppercase;margin-top:2px">Delivery Fee</div>
                          </div>
                          <div class="col-4">
                            <div class="fw-bold" id="calcEtaDisplay" style="font-size:1.05rem;color:#059669;line-height:1.2">—</div>
                            <div class="text-muted" style="font-size:.62rem;letter-spacing:.04em;text-transform:uppercase;margin-top:2px">Est. Arrival</div>
                          </div>
                        </div>
                        <div id="feeBreakdown" style="border-top:1px dashed #c4b5fd;padding-top:.5rem;font-size:.72rem;color:#6b7280;line-height:1.7"></div>
                      </div>
                    </div>
                  </div>

                  {{-- Address --}}
                  <div class="mb-3">
                    <label class="form-label fw-semibold small">
                      Full Address
                      <span id="addressLoading" style="display:none;font-size:.75rem;color:var(--primary);font-weight:400">
                        <span class="spinner-border spinner-border-sm me-1" style="width:.7rem;height:.7rem"></span>
                        Fetching address…
                      </span>
                    </label>
                    <textarea class="form-control" name="address" id="addressField" rows="2"
                      placeholder="Pin your location on the map to auto-fill, or type your address">{{ $defaultAddr ? $defaultAddr->full_address : '' }}</textarea>
                  </div>
                  <div class="form-check mb-1">
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
                    <label class="form-check-label fw-semibold" for="cod">
                      <i class="bi bi-cash-coin me-1"></i><span id="codLabelText">Cash on Pickup (COP)</span>
                    </label>
                    <div class="text-muted" id="codHelpText" style="font-size:clamp(.68rem,1.3vw,.72rem)">Pay cash when you pick up your order.</div>
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

              <div class="d-flex justify-content-between small mb-2" id="layerSurchargeRow" style="display:none!important">
                <span class="text-muted">Layer Surcharge</span>
                <span id="layerSurchargeDisplay" class="fw-semibold" style="color:var(--primary)">+&#8369;0.00</span>
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
// ── Price maps ────────────────────────────────────────
const SIZE_PRICES = {
  @foreach($sizes as $s)
    {!! json_encode($s->label) !!}: {{ (float)$s->price }},
  @endforeach
};
const LAYER_PRICES = {
  @foreach($layers as $l)
    {!! json_encode($l->label) !!}: {{ (float)$l->price }},
  @endforeach
};

const BASE_CUSTOM = 1200;

// ── Shop & coverage data ──────────────────────────────
const SHOP_META = {
  lat:           {{ $shopSettings->shop_lat        ?? 'null' }},
  lng:           {{ $shopSettings->shop_lng        ?? 'null' }},
  feePerMeter:   {{ (float)($shopSettings->fee_per_meter       ?? 0.05) }},
  maintenanceKm: {{ (float)($shopSettings->maintenance_per_km  ?? 5) }},
  fuelKm:        {{ (float)($shopSettings->fuel_per_km         ?? 8) }},
  freeRadius:    {{ (int)($shopSettings->free_delivery_radius   ?? 0) }},
};
const COVERAGE_ZONES  = @json($deliveryZones->values());
const COVERAGE_RADIUS = 3000;
let deliveryFee = 0;
let map, marker, routeLine;

// ── Haversine ─────────────────────────────────────────
function haversine(lat1, lon1, lat2, lon2) {
  const R = 6371000;
  const dLat = (lat2-lat1)*Math.PI/180, dLon = (lon2-lon1)*Math.PI/180;
  const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)**2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

function calcFee(dist) {
  if (SHOP_META.freeRadius > 0 && dist <= SHOP_META.freeRadius) return 0;
  const km = dist / 1000;
  return Math.ceil(SHOP_META.feePerMeter * dist + (SHOP_META.maintenanceKm + SHOP_META.fuelKm) * km);
}

function calcEtaMinutes(dist) {
  return Math.ceil((15 + Math.round((dist/1000)*4)) / 5) * 5;
}

function etaText(mins) {
  if (mins < 60) return mins + ' mins';
  const h = Math.floor(mins/60), m = mins%60;
  return m > 0 ? h + ' hr ' + m + ' mins' : h + ' hr';
}

function isInCoverage(lat, lng) {
  if (!COVERAGE_ZONES.length) return null;
  return COVERAGE_ZONES.some(z => z.lat && z.lng && haversine(lat, lng, z.lat, z.lng) <= COVERAGE_RADIUS);
}

// ── On pin set ────────────────────────────────────────
function onPinSet(lat, lng) {
  document.getElementById('lat').value = lat;
  document.getElementById('lng').value = lng;

  // Coverage check
  const covered  = isInCoverage(lat, lng);
  const statusEl = document.getElementById('coverageStatus');
  if (covered === null) {
    statusEl.style.display = 'none';
  } else if (covered) {
    statusEl.style.cssText = 'display:block;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;border-radius:.5rem;padding:.5rem .75rem';
    statusEl.innerHTML = '<i class="bi bi-geo-alt me-1"></i>✓ Your location is within the delivery coverage area.';
  } else {
    statusEl.style.cssText = 'display:block;background:#fff1f2;color:#9f1239;border:1px solid #fecdd3;border-radius:.5rem;padding:.5rem .75rem';
    statusEl.innerHTML = '<i class="bi bi-geo-alt me-1"></i>⚠ Your location appears to be outside the delivery area. Your order may be rejected.';
  }

  const calcBox = document.getElementById('deliveryCalcBox');
  if (SHOP_META.lat && SHOP_META.lng) {
    const dist  = haversine(lat, lng, SHOP_META.lat, SHOP_META.lng);
    const km    = dist / 1000;
    const fee   = calcFee(dist);
    const mins  = calcEtaMinutes(dist);
    deliveryFee = fee;

    document.getElementById('deliveryFeeInput').value = fee;

    // Distance
    document.getElementById('distDisplay').textContent =
      dist < 1000 ? Math.round(dist) + ' m' : km.toFixed(2) + ' km';

    // Fee + header color
    const feeEl  = document.getElementById('calcFeeDisplay');
    const hdrEl  = document.getElementById('deliveryCalcHeader');
    const freeEl = document.getElementById('deliveryFreeTag');
    if (fee === 0) {
      feeEl.textContent  = 'FREE';
      feeEl.style.color  = '#059669';
      freeEl.style.display = '';
      hdrEl.style.background = 'linear-gradient(135deg,#059669 0%,#047857 100%)';
    } else {
      feeEl.textContent  = '₱' + fee.toFixed(2);
      feeEl.style.color  = '#1e40af';
      freeEl.style.display = 'none';
      hdrEl.style.background = 'linear-gradient(135deg,#6366f1 0%,#4f46e5 100%)';
    }

    // ETA
    document.getElementById('calcEtaDisplay').textContent = '~' + etaText(mins);

    // Breakdown
    const bd = document.getElementById('feeBreakdown');
    if (fee === 0 && SHOP_META.freeRadius > 0) {
      const freeLabel = SHOP_META.freeRadius >= 1000
        ? (SHOP_META.freeRadius / 1000).toFixed(1) + ' km' : SHOP_META.freeRadius + ' m';
      bd.innerHTML = `<i class="bi bi-gift me-1" style="color:#059669"></i>Free delivery within ${freeLabel} from shop`;
    } else if (fee > 0) {
      const basePart = (SHOP_META.feePerMeter * dist).toFixed(2);
      const kmPart   = ((SHOP_META.maintenanceKm + SHOP_META.fuelKm) * km).toFixed(2);
      bd.innerHTML =
        `<div class="d-flex justify-content-between"><span><i class="bi bi-geo-alt me-1"></i>₱${SHOP_META.feePerMeter}/m × ${Math.round(dist)} m</span><span class="fw-semibold">₱${basePart}</span></div>` +
        `<div class="d-flex justify-content-between"><span><i class="bi bi-droplet me-1"></i>Fuel + maintenance × ${km.toFixed(2)} km</span><span class="fw-semibold">₱${kmPart}</span></div>`;
    } else {
      bd.innerHTML = '';
    }

    calcBox.style.display = '';

    // Route line
    const pts = [[SHOP_META.lat, SHOP_META.lng], [lat, lng]];
    if (routeLine) routeLine.setLatLngs(pts);
    else routeLine = L.polyline(pts, {
      color: '#6366f1', weight: 2, dashArray: '7 5', opacity: .65
    }).addTo(map);

    // Summary fee row
    const feeRow = document.getElementById('feeRow');
    if (feeRow) {
      feeRow.style.display = fee > 0 ? 'flex' : 'none';
      const fd = document.getElementById('feeDisplay');
      if (fd) fd.textContent = '₱' + fee.toFixed(2);
    }
  } else {
    calcBox.style.display = 'none';
  }

  updatePriceSummary();
  reverseGeocode(lat, lng);
}

function updatePriceSummary() {
  const qty             = parseInt(document.querySelector('[name=quantity]')?.value || 1);
  const size            = document.querySelector('[name=size]')?.value || '';
  const layer           = document.querySelector('[name=layers]')?.value || '';
  const isDelivery      = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';
  const sizeSurcharge   = SIZE_PRICES[size] ?? 0;
  const layerSurcharge  = LAYER_PRICES[layer] ?? 0;

  let addonTotal = 0;
  const addonDetails = [];
  document.querySelectorAll('.addon-check:checked').forEach(chk => {
    const card  = chk.closest('.addon-card');
    const price = parseFloat(card.dataset.price) || 0;
    const name  = card.querySelector('.fw-semibold.small').textContent.trim();
    addonTotal += price;
    addonDetails.push({ name, price });
  });

  const unitPrice = BASE_CUSTOM + sizeSurcharge + layerSurcharge;
  const subtotal  = unitPrice * qty;
  const total     = subtotal + addonTotal + (isDelivery ? deliveryFee : 0);

  const sizeSurRow = document.getElementById('sizeSurchargeRow');
  sizeSurRow.style.display = sizeSurcharge > 0 ? 'flex' : 'none';
  if (sizeSurcharge > 0)
    document.getElementById('sizeSurchargeDisplay').textContent = '+₱' + sizeSurcharge.toFixed(2);

  const layerSurRow = document.getElementById('layerSurchargeRow');
  layerSurRow.style.display = layerSurcharge > 0 ? 'flex' : 'none';
  if (layerSurcharge > 0)
    document.getElementById('layerSurchargeDisplay').textContent = '+\u20b1' + layerSurcharge.toFixed(2);

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

function highlightAddonCard(input) {
  const card = input.closest('.addon-card');
  card.style.borderColor = input.checked ? 'var(--primary)' : '#e9ecef';
  card.style.background  = input.checked ? 'var(--primary-light)' : '';
}

function toggleOptionalAddons() {
  const panel = document.getElementById('optionalAddonsPanel');
  const btn = document.getElementById('addonToggleBtn');
  if (!panel || !btn) return;
  const willOpen = panel.style.display === 'none';
  panel.style.display = willOpen ? 'block' : 'none';
  btn.querySelector('i').className = willOpen ? 'bi bi-dash-circle me-1' : 'bi bi-plus-circle me-1';
  const label = document.getElementById('addonToggleLabel');
  if (label) label.textContent = willOpen ? 'Hide optional add-ons' : 'Add optional add-ons';
}

function updateAddonPanelState() {
  const count = document.querySelectorAll('.addon-check:checked').length;
  const badge = document.getElementById('addonSelectedCount');
  if (!badge) return;
  badge.style.display = count ? 'inline-block' : 'none';
  badge.textContent = count + ' selected';
}

function toggleDelivery() {
  const isDelivery = document.querySelector('[name=fulfillment_type]:checked').value === 'Delivery';
  document.getElementById('deliverySection').style.display = isDelivery ? 'block' : 'none';
  if (isDelivery && !map) initMap();
  if (!isDelivery) { deliveryFee = 0; }
  updatePaymentMethodLabel();
  updatePriceSummary();
}

function updatePaymentMethodLabel() {
  const isDelivery = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';
  const codLabel = document.getElementById('codLabelText');
  const codHelp = document.getElementById('codHelpText');
  if (codLabel) {
    codLabel.textContent = isDelivery ? 'Cash on Delivery (COD)' : 'Cash on Pickup (COP)';
  }
  if (codHelp) {
    codHelp.textContent = isDelivery
      ? 'Pay cash when your order arrives.'
      : 'Pay cash when you pick up your order.';
  }
}

async function reverseGeocode(lat, lng) {
  const field = document.getElementById('addressField');
  const ind   = document.getElementById('addressLoading');
  if (ind) ind.style.display = 'inline';
  try {
    const res  = await fetch(`/api/geocode/reverse?lat=${lat}&lng=${lng}`);
    const data = await res.json();
    if (data && data.display_name) {
      const a = data.address || {};
      const parts = [
        a.house_number ? (a.house_number+' '+(a.road||'')) : a.road,
        a.suburb || a.village || a.neighbourhood,
        a.city_district || a.county,
        a.city || a.town || a.municipality,
        a.state,
      ].filter(Boolean);
      field.value = parts.length > 0 ? parts.join(', ') : data.display_name;
      const brgy = a.village || a.suburb || a.neighbourhood || a.quarter || a.hamlet || '';
      document.getElementById('deliveryZoneInput').value = brgy || parts[0] || '';
    }
  } catch (e) {}
  finally { if (ind) ind.style.display = 'none'; }
}

function setMarkerAt(latlng, triggerPin = true) {
  if (marker) {
    marker.setLatLng(latlng);
  } else {
    const pinIcon = L.divIcon({
      html: `<div style="position:relative;width:28px;height:40px">
               <div style="background:var(--primary,#e91e8c);width:28px;height:28px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 3px 10px rgba(233,30,140,.55)"></div>
               <div style="position:absolute;top:4px;left:4px;width:12px;height:12px;background:#fff;border-radius:50%;transform:rotate(45deg)"></div>
             </div>`,
      className: '', iconSize: [28,40], iconAnchor: [14,40]
    });
    marker = L.marker(latlng, {draggable: true, icon: pinIcon}).addTo(map);
    marker.bindTooltip('Your location', {direction: 'top'});
    marker.on('dragend', e => { const ll = e.target.getLatLng(); onPinSet(ll.lat, ll.lng); });
  }
  if (triggerPin) onPinSet(latlng.lat, latlng.lng);
  else { document.getElementById('lat').value = latlng.lat; document.getElementById('lng').value = latlng.lng; }
}

function initMap() {
  @php
    $defLat  = $shopSettings->shop_lat ?? ($defaultAddr->latitude  ?? 14.5995);
    $defLng  = $shopSettings->shop_lng ?? ($defaultAddr->longitude ?? 120.9842);
    $defZoom = ($shopSettings->shop_lat ?? null) ? 14 : (($defaultAddr->latitude ?? null) ? 15 : 13);
  @endphp
  map = L.map('map').setView([{{ $defLat }}, {{ $defLng }}], {{ $defZoom }});
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

  if (SHOP_META.lat && SHOP_META.lng) {
    const shopIcon = L.divIcon({
      html: `<div style="background:#6366f1;width:36px;height:36px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 3px 12px rgba(99,102,241,.55);display:flex;align-items:center;justify-content:center">
               <span style="transform:rotate(45deg);font-size:15px;line-height:1">🏪</span>
             </div>`,
      className:'', iconSize:[36,36], iconAnchor:[18,36]
    });
    L.marker([SHOP_META.lat, SHOP_META.lng], {icon:shopIcon, interactive:true}).addTo(map).bindTooltip('Cake Shop', {permanent:false, direction:'top'});
  }

  COVERAGE_ZONES.forEach(z => {
    if (!z.lat || !z.lng) return;
    L.circle([z.lat, z.lng], {
      radius: COVERAGE_RADIUS, color: '#e91e8c', weight: 1.5,
      fillColor: '#e91e8c', fillOpacity: .05, dashArray: '6 4', interactive: false
    }).addTo(map);
    const cIcon = L.divIcon({
      html:'<div style="background:var(--primary,#e91e8c);width:10px;height:10px;border-radius:50%;opacity:.5;border:2px solid rgba(233,30,140,.7)"></div>',
      className:'', iconSize:[10,10], iconAnchor:[5,5]
    });
    L.marker([z.lat, z.lng], {icon:cIcon, interactive:false}).addTo(map).bindTooltip(z.barangay||'Coverage Area');
  });

  @if($defaultAddr && ($defaultAddr->latitude ?? null) && ($defaultAddr->longitude ?? null))
    setMarkerAt(L.latLng({{ $defaultAddr->latitude }}, {{ $defaultAddr->longitude }}), false);
    map.setView([{{ $defaultAddr->latitude }}, {{ $defaultAddr->longitude }}], 15);
  @endif

  map.on('click', e => setMarkerAt(e.latlng, true));
}

function detectMyLocation() {
  if (!navigator.geolocation) { alert('Geolocation is not supported by your browser.'); return; }
  const btn = document.getElementById('detectBtn');
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Detecting…';
  navigator.geolocation.getCurrentPosition(
    pos => {
      btn.disabled = false; btn.innerHTML = '<i class="bi bi-crosshair me-1"></i>Detect My Location';
      const ll = L.latLng(pos.coords.latitude, pos.coords.longitude);
      setMarkerAt(ll, true); map.flyTo(ll, 16);
    },
    () => {
      btn.disabled = false; btn.innerHTML = '<i class="bi bi-crosshair me-1"></i>Detect My Location';
      alert('Could not detect your location. Please pin it manually on the map.');
    }
  );
}

function confirmCustomOrder(btn) {
  const isDelivery = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';
  if (isDelivery) {
    const lat  = document.getElementById('lat')?.value;
    const addr = document.getElementById('addressField')?.value?.trim();
    if (!lat || !addr) { alert('Please pin your location on the map and enter your address.'); return false; }
  }
  const total = document.getElementById('totalDisplay').textContent;
  cakeConfirm({
    title: '📋 Confirm Custom Order?',
    message: 'Total: ' + total + ' — Your order will be reviewed by the baker.',
    icon: 'bi-cake2', okLabel: 'Place Order',
    onConfirm: () => {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Placing Order…';
      document.getElementById('customOrderForm').submit();
    }
  });
  return false;
}

document.addEventListener('DOMContentLoaded', () => {
  updatePaymentMethodLabel();
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
