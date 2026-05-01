@extends('layouts.app')
@section('content')
@php
  $originalSubtotal = $pricing['original_unit_price'] * $checkout['quantity'];
  $discountedSubtotal = $pricing['final_unit_price'] * $checkout['quantity'];
  $productDiscountTotal = $pricing['discount_amount'] * $checkout['quantity'];
@endphp
<script>
document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
document.body.classList.remove('modal-open');
document.body.style.overflow = '';
document.body.style.paddingRight = '';
</script>
<div class="py-4" style="max-width:960px;margin:0 auto;padding-left:clamp(12px,3vw,24px);padding-right:clamp(12px,3vw,24px)">
  <h4 class="fw-bold mb-4 text-center"><i class="bi bi-bag-check me-2" style="color:var(--primary)"></i>Checkout</h4>

  @if(session('error'))
    <div class="alert alert-danger border-0"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
  @endif

  <div class="row g-4">
    {{-- LEFT: Form --}}
    <div class="col-lg-8">
      <form action="{{ route('customer.checkout.place') }}" method="POST" id="checkoutForm">
        @csrf

        {{-- Product Summary --}}
        <div class="card mb-3" style="border:1.5px solid color-mix(in srgb,var(--primary) 15%,transparent);border-radius:1rem;overflow:hidden">
          <div class="card-body p-0">
            <div class="d-flex align-items-center gap-3 p-3" style="background:var(--primary-bg)">
              <img src="{{ $product->image_path }}" alt="{{ $product->name }}"
                   style="width:72px;height:72px;object-fit:cover;border-radius:.75rem;flex-shrink:0;box-shadow:0 4px 12px rgba(0,0,0,.12)"
                   onerror="this.src='https://placehold.co/72x72/fce4ec/e91e63?text=🎂'">
              <div class="flex-grow-1 min-width-0">
                <div class="fw-bold" style="font-size:1rem;color:var(--gray-900)">{{ $product->name }}</div>
                <div class="text-muted small mt-1">
                  <i class="bi bi-box me-1"></i>Qty: <strong>{{ $checkout['quantity'] }}</strong>
                  @if(!empty($checkout['selected_size'])) &ensp;<i class="bi bi-rulers me-1"></i>{{ $checkout['selected_size'] }} @endif
                  @if($checkout['custom_note']) <br><i class="bi bi-chat-left-text me-1"></i>{{ $checkout['custom_note'] }} @endif
                </div>
                @if(!empty($shop))
                <a href="/shop/{{ $shop->shop_slug }}" target="_blank"
                   class="d-inline-flex align-items-center gap-1 text-decoration-none mt-1"
                   style="font-size:.72rem;color:var(--primary)">
                  @if(!empty($shop->shop_logo))
                    <img src="{{ $shop->shop_logo }}" style="width:14px;height:14px;border-radius:3px;object-fit:cover">
                  @else
                    <i class="bi bi-shop" style="font-size:.65rem"></i>
                  @endif
                  {{ $shop->shop_name }}
                </a>
                @endif
              </div>
              <div class="text-end flex-shrink-0">
                @if(!empty($pricing['has_discount']))
                  <div class="text-muted text-decoration-line-through" style="font-size:.72rem">₱{{ number_format($originalSubtotal, 2) }}</div>
                @endif
                <div class="fw-bold" style="color:{{ !empty($pricing['has_discount']) ? '#dc2626' : 'var(--primary)' }};font-size:1.1rem" id="basePrice"
                     data-price="{{ $discountedSubtotal }}">
                  ₱{{ number_format($discountedSubtotal, 2) }}
                </div>
                <div class="text-muted" style="font-size:.72rem">{{ !empty($pricing['has_discount']) ? 'Discounted price' : 'Base price' }}</div>
              </div>
            </div>
          </div>
        </div>

        {{-- Customer Info --}}
        <div class="card mb-3">
          <div class="card-body p-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-person me-2" style="color:var(--primary)"></i>Your Information</h6>
            <div class="row g-2 small">
              <div class="col-sm-6">
                <div class="bg-light rounded p-2">
                  <div class="text-muted" style="font-size:.72rem">FULL NAME</div>
                  <div class="fw-semibold">{{ $customer->fullname }}</div>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="bg-light rounded p-2">
                  <div class="text-muted" style="font-size:.72rem">PHONE</div>
                  <div class="fw-semibold">{{ $customer->phone }}</div>
                </div>
              </div>
              <div class="col-12">
                <div class="bg-light rounded p-2">
                  <div class="text-muted" style="font-size:.72rem">EMAIL</div>
                  <div class="fw-semibold">{{ $customer->email }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- Fulfillment --}}
        <div class="card mb-3">
          <div class="card-body p-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-truck me-2" style="color:var(--primary)"></i>Fulfillment</h6>
            <div class="d-flex gap-3 mb-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="fulfillment_type" value="Pickup" id="pickup" checked onchange="toggleDelivery()">
                <label class="form-check-label fw-semibold" for="pickup"><i class="bi bi-bag me-1"></i>Pickup</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="fulfillment_type" value="Delivery" id="delivery" onchange="toggleDelivery()">
                <label class="form-check-label fw-semibold" for="delivery"><i class="bi bi-bicycle me-1"></i>Delivery</label>
              </div>
            </div>

            <div id="deliverySection" style="display:none">

              @if(($product->classification ?? '') === 'Perishable')
              <div class="alert border-0 mb-3" style="background:#fff7ed;border-left:4px solid #f59e0b!important;border-radius:.7rem">
                <div class="d-flex align-items-start gap-2">
                  <i class="bi bi-thermometer-high mt-1" style="color:#f59e0b;font-size:1.1rem"></i>
                  <div>
                    <div class="fw-semibold small" style="color:#854d0e">Ice Cream Cake — Perishable Item</div>
                    <div class="small" style="color:#92400e">
                      Only available for nearby deliveries. Longer trips may cause melting or design damage. Please coordinate with us first.
                    </div>
                  </div>
                </div>
              </div>
              @endif

              {{-- Detect location + Map --}}
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
              <div id="coverageStatus" style="display:none" class="mb-3 p-2 rounded-2 small">
                <i class="bi bi-geo-alt me-1"></i><span id="coverageMsg"></span>
              </div>

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
                <input type="date" class="form-control" name="schedule_date" id="custFieldDate"
                       min="{{ date('Y-m-d') }}"
                       onchange="checkCustAvailability()">
                <div id="custCheckoutAvailability" class="mt-1" style="font-size:.8rem;min-height:18px"></div>
                <div class="form-text"><i class="bi bi-info-circle me-1"></i>You can order for today or any future date.</div>
              </div>
              <div class="col-sm-6">
                <label class="form-label fw-semibold small">Preferred Time Slot</label>
                <select class="form-select" name="schedule_time">
                  <option value="">-- Select Time Slot --</option>
                  <option value="09:00">9:00 AM – 11:00 AM</option>
                  <option value="11:00">11:00 AM – 1:00 PM</option>
                  <option value="13:00">1:00 PM – 3:00 PM</option>
                  <option value="15:00">3:00 PM – 5:00 PM</option>
                  <option value="17:00">5:00 PM – 7:00 PM</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        {{-- Payment --}}
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

        <button type="submit" class="btn btn-primary w-100 py-3 fw-semibold fs-5" id="placeOrderBtn"
                onclick="return validateAndSubmit(this)">
          <i class="bi bi-bag-check me-2"></i>Place Order
        </button>
      </form>
    </div>

    {{-- RIGHT: Order Summary --}}
    <div class="col-lg-4">
      <div class="card sticky-top" style="top:80px">
        <div class="card-body p-4">
          <h6 class="fw-bold mb-3">📋 Order Summary</h6>
          <div class="d-flex justify-content-between small mb-2">
            <span>{{ $product->name }} × {{ $checkout['quantity'] }}</span>
            <span>₱{{ number_format($originalSubtotal,2) }}</span>
          </div>
          @if(!empty($pricing['has_discount']))
          <div class="d-flex justify-content-between small mb-2">
            <span class="text-muted">{{ $pricing['badge_text'] }} Product Discount</span>
            <span style="color:#dc2626">-₱{{ number_format($productDiscountTotal,2) }}</span>
          </div>
          @endif
          <div id="addonSummary"></div>
          <div class="d-flex justify-content-between small mb-1" id="feeRow" style="display:none!important">
            <span class="text-muted">Delivery Fee</span>
            <span id="feeDisplay">₱0.00</span>
          </div>
          <hr class="my-2">
          <div class="d-flex justify-content-between fw-bold">
            <span>Total</span>
            <span id="totalDisplay" style="color:var(--primary);font-size:1.1rem">
              ₱{{ number_format($discountedSubtotal,2) }}
            </span>
          </div>
          <div id="selectedAddonsList" class="mt-3" style="display:none">
            <div class="text-muted small fw-semibold mb-1">Selected Add-ons:</div>
            <div id="selectedAddonsDetail" class="small"></div>
          </div>
          @if(!empty($shop))
          <a href="/shop/{{ $shop->shop_slug }}" target="_blank"
             class="d-flex align-items-center gap-2 mt-3 p-2 rounded-2 text-decoration-none"
             style="background:#fff0f6;border:1px solid #fce7f3">
            @if(!empty($shop->shop_logo))
              <img src="{{ $shop->shop_logo }}" style="width:34px;height:34px;border-radius:8px;object-fit:cover;flex-shrink:0">
            @else
              <div style="width:34px;height:34px;border-radius:8px;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-shop-window text-white" style="font-size:.85rem"></i>
              </div>
            @endif
            <div class="flex-grow-1">
              <div class="fw-semibold" style="font-size:.8rem;color:#9d174d">{{ $shop->shop_name }}</div>
              <div class="text-muted" style="font-size:.68rem">View shop &rarr;</div>
            </div>
          </a>
          @endif
          <div class="mt-3 p-2 rounded small text-muted" style="background:#f8f9fa;font-size:.75rem">
            <i class="bi bi-info-circle me-1"></i>Delivery fee is calculated based on your pinned location distance from the shop.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function checkCustAvailability() {
  const date      = document.getElementById('custFieldDate')?.value;
  const productId = '{{ $product->id }}';
  const maxPerDay = {{ (int)($product->max_per_day ?? 0) }};
  const resultEl  = document.getElementById('custCheckoutAvailability');
  if (!date || !resultEl) return;
  if (maxPerDay === 0) { resultEl.innerHTML = ''; return; }
  resultEl.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split me-1"></i>Checking...</span>';
  fetch(`/catalog/availability?product_id=${productId}&date=${date}`)
    .then(r => r.json())
    .then(data => {
      if (data.status === 'available')
        resultEl.innerHTML = `<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>${data.message}</span>`;
      else if (data.status === 'almost')
        resultEl.innerHTML = `<span class="text-warning fw-semibold"><i class="bi bi-exclamation-triangle-fill me-1"></i>${data.message}</span>`;
      else if (data.status === 'full')
        resultEl.innerHTML = `<span class="text-danger fw-semibold"><i class="bi bi-x-circle-fill me-1"></i>${data.message} — please choose another date.</span>`;
      else
        resultEl.innerHTML = `<span class="text-danger small"><i class="bi bi-x-circle me-1"></i>${data.message}</span>`;
    })
    .catch(() => { resultEl.innerHTML = ''; });
}
</script>

@endsection
@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ── Shop & coverage data from server ──────────────────
const SHOP_META = {
  lat:           {{ $shopSettings->shop_lat        ?? 'null' }},
  lng:           {{ $shopSettings->shop_lng        ?? 'null' }},
  feePerMeter:   {{ (float)($shopSettings->fee_per_meter       ?? 0.05) }},
  maintenanceKm: {{ (float)($shopSettings->maintenance_per_km  ?? 5) }},
  fuelKm:        {{ (float)($shopSettings->fuel_per_km         ?? 8) }},
  freeRadius:    {{ (int)($shopSettings->free_delivery_radius   ?? 0) }},
};
const COVERAGE_ZONES   = @json($deliveryZones->values());
const COVERAGE_RADIUS  = 3000; // metres per coverage pin
const BASE_PRICE       = {{ $pricing['final_unit_price'] * $checkout['quantity'] }};
const HAS_PRODUCT_DISCOUNT = {{ !empty($pricing['has_discount']) ? 'true' : 'false' }};
let deliveryFee = 0;
let map, marker, routeLine;

// ── Haversine ─────────────────────────────────────────
function haversine(lat1, lon1, lat2, lon2) {
  const R = 6371000;
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)**2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

// ── Fee + ETA calculation ─────────────────────────────
function calcFee(dist) {
  if (SHOP_META.freeRadius > 0 && dist <= SHOP_META.freeRadius) return 0;
  const km = dist / 1000;
  return Math.ceil(SHOP_META.feePerMeter * dist + (SHOP_META.maintenanceKm + SHOP_META.fuelKm) * km);
}

function calcEtaMinutes(dist) {
  return Math.ceil((15 + Math.round((dist / 1000) * 4)) / 5) * 5;
}

function etaText(mins) {
  if (mins < 60) return mins + ' mins';
  const h = Math.floor(mins / 60), m = mins % 60;
  return m > 0 ? h + ' hr ' + m + ' mins' : h + ' hr';
}

// ── Coverage check ────────────────────────────────────
function isInCoverage(lat, lng) {
  if (!COVERAGE_ZONES.length) return null; // null = no zones configured
  return COVERAGE_ZONES.some(z => z.lat && z.lng && haversine(lat, lng, z.lat, z.lng) <= COVERAGE_RADIUS);
}

// ── On customer pin set ────────────────────────────────
function onPinSet(lat, lng) {
  document.getElementById('lat').value = lat;
  document.getElementById('lng').value = lng;

  // Coverage check
  const covered = isInCoverage(lat, lng);
  const statusEl = document.getElementById('coverageStatus');
  const msgEl    = document.getElementById('coverageMsg');
  if (covered === null) {
    statusEl.style.display = 'none';
  } else if (covered) {
    statusEl.style.cssText = 'display:block;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;border-radius:.5rem;padding:.5rem .75rem';
    msgEl.textContent = '✓ Your location is within the delivery coverage area.';
  } else {
    statusEl.style.cssText = 'display:block;background:#fff1f2;color:#9f1239;border:1px solid #fecdd3;border-radius:.5rem;padding:.5rem .75rem';
    msgEl.textContent = '⚠ Your location appears to be outside the delivery area. Your order may be rejected.';
  }

  // Fee + ETA
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

    // Fee
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
        `<div class="d-flex justify-content-between"><span><i class="bi bi-geo-alt me-1 text-indigo"></i>₱${SHOP_META.feePerMeter}/m × ${Math.round(dist)} m</span><span class="fw-semibold">₱${basePart}</span></div>` +
        `<div class="d-flex justify-content-between"><span><i class="bi bi-droplet me-1 text-indigo"></i>Fuel + maintenance × ${km.toFixed(2)} km</span><span class="fw-semibold">₱${kmPart}</span></div>`;
    } else {
      bd.innerHTML = '';
    }

    calcBox.style.display = '';

    // Route line from shop to pin
    if (SHOP_META.lat && SHOP_META.lng) {
      const pts = [[SHOP_META.lat, SHOP_META.lng], [lat, lng]];
      if (routeLine) routeLine.setLatLngs(pts);
      else routeLine = L.polyline(pts, {
        color: '#6366f1', weight: 2, dashArray: '7 5', opacity: .65
      }).addTo(map);
    }

    // Update summary panel fee row
    const feeRow = document.getElementById('feeRow');
    if (feeRow) {
      feeRow.style.display = fee > 0 ? 'flex' : 'none';
      const feeDisp = document.getElementById('feeDisplay');
      if (feeDisp) feeDisp.textContent = '₱' + fee.toFixed(2);
    }
  } else {
    calcBox.style.display = 'none';
  }

  updateTotal(getCurrentAddonTotal());
  reverseGeocode(lat, lng);
}

// ── Map ───────────────────────────────────────────────
function initMap() {
  @php
    $defLat = $shopSettings->shop_lat ?? ($defaultAddr->latitude ?? 14.5995);
    $defLng = $shopSettings->shop_lng ?? ($defaultAddr->longitude ?? 120.9842);
    $defZoom = ($shopSettings->shop_lat ?? null) ? 14 : (($defaultAddr->latitude ?? null) ? 15 : 13);
  @endphp
  map = L.map('map').setView([{{ $defLat }}, {{ $defLng }}], {{ $defZoom }});
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

  // Shop marker
  if (SHOP_META.lat && SHOP_META.lng) {
    const shopIcon = L.divIcon({
      html: `<div style="background:#6366f1;width:36px;height:36px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 3px 12px rgba(99,102,241,.55);display:flex;align-items:center;justify-content:center">
               <span style="transform:rotate(45deg);font-size:15px;line-height:1">🏪</span>
             </div>`,
      className: '', iconSize: [36,36], iconAnchor: [18,36]
    });
    L.marker([SHOP_META.lat, SHOP_META.lng], {icon: shopIcon, interactive: true})
      .addTo(map).bindTooltip('Cake Shop', {permanent: false, direction: 'top'});
  }

  // Coverage zone markers + radius circles
  COVERAGE_ZONES.forEach(z => {
    if (!z.lat || !z.lng) return;
    L.circle([z.lat, z.lng], {
      radius: COVERAGE_RADIUS, color: '#e91e8c', weight: 1.5,
      fillColor: '#e91e8c', fillOpacity: .05, dashArray: '6 4', interactive: false
    }).addTo(map);
    const cIcon = L.divIcon({
      html: '<div style="background:var(--primary,#e91e8c);width:10px;height:10px;border-radius:50%;opacity:.5;border:2px solid rgba(233,30,140,.7)"></div>',
      className: '', iconSize: [10,10], iconAnchor: [5,5]
    });
    L.marker([z.lat, z.lng], {icon: cIcon, interactive: false}).addTo(map)
      .bindTooltip(z.barangay || 'Coverage Area');
  });

  // Pre-load default address pin
  @if($defaultAddr && $defaultAddr->latitude && $defaultAddr->longitude)
    setMarkerAt(L.latLng({{ $defaultAddr->latitude }}, {{ $defaultAddr->longitude }}), false);
    map.setView([{{ $defaultAddr->latitude }}, {{ $defaultAddr->longitude }}], 15);
  @endif

  map.on('click', e => setMarkerAt(e.latlng, true));
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
    marker = L.marker(latlng, { draggable: true, icon: pinIcon }).addTo(map);
    marker.bindTooltip('Your location', {direction: 'top'});
    marker.on('dragend', e => {
      const ll = e.target.getLatLng();
      onPinSet(ll.lat, ll.lng);
    });
  }
  if (triggerPin) onPinSet(latlng.lat, latlng.lng);
  else {
    document.getElementById('lat').value = latlng.lat;
    document.getElementById('lng').value = latlng.lng;
  }
}

function detectMyLocation() {
  if (!navigator.geolocation) { alert('Geolocation is not supported by your browser.'); return; }
  const btn = document.getElementById('detectBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Detecting…';
  navigator.geolocation.getCurrentPosition(
    pos => {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-crosshair me-1"></i>Detect My Location';
      const ll = L.latLng(pos.coords.latitude, pos.coords.longitude);
      setMarkerAt(ll, true);
      map.flyTo(ll, 16);
    },
    () => {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-crosshair me-1"></i>Detect My Location';
      alert('Could not detect your location. Please pin it manually on the map.');
    },
    { enableHighAccuracy: false, timeout: 10000, maximumAge: 30000 }
  );
}

// ── Reverse geocode ───────────────────────────────────
async function reverseGeocode(lat, lng) {
  const field = document.getElementById('addressField');
  const ind   = document.getElementById('addressLoading');
  if (ind) ind.style.display = 'inline';
  try {
    const _ctrl = new AbortController(); setTimeout(() => _ctrl.abort(), 6000);
    const res  = await fetch(`/api/geocode/reverse?lat=${lat}&lng=${lng}`, { signal: _ctrl.signal });
    const data = await res.json();
    if (data && data.display_name) {
      const a = data.address || {};
      const parts = [
        a.house_number ? (a.house_number + ' ' + (a.road || '')) : a.road,
        a.suburb || a.village || a.neighbourhood,
        a.city_district || a.county,
        a.city || a.town || a.municipality,
        a.state,
      ].filter(Boolean);
      field.value = parts.length > 0 ? parts.join(', ') : data.display_name;
      // Also store area name as delivery zone
      const brgy = a.village || a.suburb || a.neighbourhood || a.quarter || a.hamlet || '';
      document.getElementById('deliveryZoneInput').value = brgy || parts[0] || '';
    }
  } catch (e) {}
  finally { if (ind) ind.style.display = 'none'; }
}

// ── Fulfillment toggle ────────────────────────────────
function toggleDelivery() {
  const isDelivery = document.querySelector('[name=fulfillment_type]:checked').value === 'Delivery';
  document.getElementById('deliverySection').style.display = isDelivery ? 'block' : 'none';
  if (isDelivery && !map) initMap();
  if (!isDelivery) deliveryFee = 0;
  updatePaymentMethodLabel();
  updateTotal(getCurrentAddonTotal());
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

// ── Totals ────────────────────────────────────────────
function getCurrentAddonTotal() {
  let t = 0;
  document.querySelectorAll('.addon-check:checked').forEach(chk => {
    t += parseFloat(chk.closest('.addon-card').dataset.price) || 0;
  });
  return t;
}

function updateAddonTotal() {
  let addonTotal = 0;
  const details  = [];
  document.querySelectorAll('.addon-check:checked').forEach(chk => {
    const card  = chk.closest('.addon-card');
    const price = parseFloat(card.dataset.price) || 0;
    const name  = card.querySelector('.fw-semibold.small').textContent.trim();
    addonTotal += price;
    details.push({ name, price });
  });
  const summaryEl = document.getElementById('addonSummary');
  if (summaryEl) {
    summaryEl.innerHTML = details.map(d =>
      `<div class="d-flex justify-content-between small mb-1 text-muted">
        <span><i class="bi bi-check2 me-1" style="color:var(--primary)"></i>${d.name}</span>
        <span>${d.price > 0 ? '+₱'+d.price.toFixed(2) : 'FREE'}</span>
      </div>`
    ).join('');
  }
  const listEl   = document.getElementById('selectedAddonsList');
  const detailEl = document.getElementById('selectedAddonsDetail');
  if (listEl && detailEl) {
    if (details.length > 0) {
      listEl.style.display = 'block';
      detailEl.innerHTML = details.map(d =>
        `<div class="d-flex justify-content-between">
           <span>• ${d.name}</span>
           <span class="fw-semibold" style="color:var(--primary)">${d.price > 0 ? '+₱'+d.price.toFixed(2) : 'FREE'}</span>
         </div>`
      ).join('');
    } else {
      listEl.style.display = 'none';
    }
  }
  updateTotal(addonTotal);
}

function highlightCard(input) {
  const card = input.closest('.addon-card');
  card.style.borderColor = input.checked ? 'var(--primary)' : '#e9ecef';
  card.style.background  = input.checked ? 'var(--primary-bg,#fdf0f5)' : '#fff';
}

function onAddonChange(input) {
  highlightCard(input);
  updateAddonTotal();
}

function updateTotal(addonTotal) {
  const isDelivery = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';
  const total = BASE_PRICE + (addonTotal ?? getCurrentAddonTotal()) + (isDelivery ? deliveryFee : 0);
  const el = document.getElementById('totalDisplay');
  if (el) el.textContent = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits:2});
}

// ── Form submit validation ────────────────────────────
function validateAndSubmit(btn) {
  const isDelivery = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';
  if (isDelivery) {
    const lat = document.getElementById('lat')?.value;
    const addr = document.getElementById('addressField')?.value?.trim();
    if (!lat || !addr) {
      alert('Please pin your location on the map and enter your address.');
      return false;
    }
  }
  setTimeout(() => {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Placing Order…';
  }, 10);
  return true;
}

if (HAS_PRODUCT_DISCOUNT) {
  const basePriceEl = document.getElementById('basePrice');
  if (basePriceEl) basePriceEl.style.color = '#dc2626';
}
updatePaymentMethodLabel();
</script>
@endpush
