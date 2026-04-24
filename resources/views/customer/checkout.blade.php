@extends('layouts.app')
@section('content')
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
                    <div class="fw-bold" style="color:var(--primary);font-size:1.1rem" id="basePrice"
                         data-price="{{ $product->price * $checkout['quantity'] }}">
                      ₱{{ number_format($product->price * $checkout['quantity'], 2) }}
                    </div>
                    <div class="text-muted" style="font-size:.72rem">Base price</div>
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

                  {{-- ── Perishable Warning ───────────────────────────────── --}}
                  @if(($product->classification ?? '') === 'Perishable')
                  <div class="alert border-0 mb-3" style="background:#fff7ed;border-left:4px solid #f59e0b!important;border-radius:.7rem">
                    <div class="d-flex align-items-start gap-2">
                      <i class="bi bi-thermometer-high mt-1" style="color:#f59e0b;font-size:1.1rem"></i>
                      <div>
                        <div class="fw-semibold small" style="color:#854d0e">Ice Cream Cake — Perishable Item</div>
                        <div class="small" style="color:#92400e">
                          Only available for nearby deliveries (Poblacion &amp; surrounding barangays).
                          Longer trips may cause melting or design damage. Please coordinate with us first.
                        </div>
                      </div>
                    </div>
                  </div>
                  @endif

                  {{-- ── Barangay / Delivery Zone ────────────────────────── --}}
                  <div class="mb-3">
                    <label class="form-label fw-semibold small">
                      Select Barangay <span class="text-danger">*</span>
                    </label>
                    <select class="form-select" name="delivery_zone" id="zoneSelect" onchange="updateFee()">
                      <option value="">-- Select your Barangay --</option>
                      @php
                        $zoneTypeLabels = [
                          'free' => 'Poblacion',
                          'near' => 'Nearby',
                          'mid'  => 'Mid-range',
                          'far'  => 'Far',
                          'ooc'  => 'Out of Coverage',
                        ];
                        $zoneTypeColors = [
                          'free' => '#10b981',
                          'near' => '#0ea5e9',
                          'mid'  => '#f59e0b',
                          'far'  => '#f97316',
                          'ooc'  => '#e11d48',
                        ];
                        $grouped = $deliveryZones->groupBy('zone_type');
                        $order   = ['free','near','mid','far','ooc'];
                      @endphp
                      @foreach($order as $typeKey)
                        @php $group = $grouped[$typeKey] ?? collect(); @endphp
                        @if($group->count() > 0)
                        <optgroup label="{{ $zoneTypeLabels[$typeKey] ?? $typeKey }} — {{ $typeKey === 'free' ? 'FREE' : ($typeKey === 'ooc' ? '₱250+' : '₱'.$group->first()->fee) }}">
                          @foreach($group as $z)
                          <option value="{{ $z->barangay }}"
                                  data-fee="{{ $z->fee }}"
                                  data-type="{{ $z->zone_type }}">
                            {{ $z->barangay }}
                            @if($z->fee == 0) (Free)
                            @else — ₱{{ number_format($z->fee, 2) }}
                            @endif
                          </option>
                          @endforeach
                        </optgroup>
                        @endif
                      @endforeach
                    </select>
                    <input type="hidden" name="delivery_fee" id="deliveryFeeInput" value="0">

                    {{-- OOC Notice --}}
                    <div id="oocNotice" style="display:none" class="alert border-0 mt-2 py-2"
                         style="background:#fff1f2">
                      <i class="bi bi-info-circle me-1" style="color:#e11d48"></i>
                      <span class="small" style="color:#9f1239">
                        <strong>Out of Coverage</strong> — Delivery fee starts at ₱250.
                        Admin will confirm the exact amount after reviewing your location.
                      </span>
                    </div>
                  </div>

                  {{-- ── Delivery Fee Display ─────────────────────────────── --}}
                  <div class="mb-3 p-3 rounded" id="feePreview" style="background:#f0fdf4;display:none">
                    <div class="d-flex align-items-center justify-content-between">
                      <div class="small fw-semibold" style="color:#166534">
                        <i class="bi bi-bicycle me-1"></i>Delivery Fee
                      </div>
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
                    <input type="date" class="form-control" name="schedule_date" id="custFieldDate"
                           min="{{ date('Y-m-d') }}"
                           onchange="checkCustAvailability()">
                    <div id="custCheckoutAvailability" class="mt-1" style="font-size:.8rem;min-height:18px"></div>
                    <div class="form-text"><i class="bi bi-info-circle me-1"></i>Minimum 1 day ahead. You can order for today or any future date.</div>
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

            <button type="submit" class="btn btn-primary w-100 py-3 fw-semibold fs-5" id="placeOrderBtn"
                    onclick="disablePlaceOrder(this)">
              <i class="bi bi-bag-check me-2"></i>Place Order
            </button>
          </form>
        </div>

        {{-- RIGHT: Order Summary --}}
        <div class="col-lg-4">
          <div class="card sticky-top" style="top:80px">
            <div class="card-body p-4">
              <h6 class="fw-bold mb-3">📋 Order Summary</h6>

              {{-- Base price --}}
              <div class="d-flex justify-content-between small mb-2">
                <span>{{ $product->name }} × {{ $checkout['quantity'] }}</span>
                <span>₱{{ number_format($product->price * $checkout['quantity'],2) }}</span>
              </div>

              {{-- Add-ons summary (dynamic) --}}
              <div id="addonSummary"></div>

              {{-- Fee breakdown --}}
              <div class="d-flex justify-content-between small mb-1" id="feeRow" style="display:none!important">
                <span class="text-muted">Delivery Fee</span>
                <span id="feeDisplay">₱0.00</span>
              </div>
              <div class="d-flex justify-content-between small mb-1" id="serviceRow" style="display:none!important">
                <span class="text-muted">Service/Gasoline Charge</span>
                <span id="serviceDisplay">₱0.00</span>
              </div>

              <hr class="my-2">
              <div class="d-flex justify-content-between fw-bold">
                <span>Total</span>
                <span id="totalDisplay" style="color:var(--primary);font-size:1.1rem">
                  ₱{{ number_format($product->price * $checkout['quantity'],2) }}
                </span>
              </div>

              {{-- Selected addons list --}}
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
                <i class="bi bi-info-circle me-1"></i>
                Final price includes all selected add-ons and delivery fee (if applicable).
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
      if (data.status === 'available') {
        resultEl.innerHTML = `<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>${data.message}</span>`;
      } else if (data.status === 'almost') {
        resultEl.innerHTML = `<span class="text-warning fw-semibold"><i class="bi bi-exclamation-triangle-fill me-1"></i>${data.message}</span>`;
      } else if (data.status === 'full') {
        resultEl.innerHTML = `<span class="text-danger fw-semibold"><i class="bi bi-x-circle-fill me-1"></i>${data.message} — please choose another date.</span>`;
      } else if (data.status === 'invalid') {
        resultEl.innerHTML = `<span class="text-danger small"><i class="bi bi-x-circle me-1"></i>${data.message}</span>`;
      }
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
const BASE_PRICE  = {{ $product->price * $checkout['quantity'] }};
let deliveryFee   = 0;
let map, marker;

// ── Addon Total ────────────────────────────────────────────────
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

  // Update summary panel
  const summaryEl = document.getElementById('addonSummary');
  summaryEl.innerHTML = '';
  details.forEach(d => {
    summaryEl.innerHTML += '
      <div class="d-flex justify-content-between small mb-1 text-muted">
        <span><i class="bi bi-check2 me-1" style="color:var(--primary)"></i>${d.name}</span>
        <span>${d.price > 0 ? '+₱'+d.price.toFixed(2) : 'FREE'}</span>
      </div>';
  });

  // Update selected addons list
  const listEl = document.getElementById('selectedAddonsList');
  const detailEl = document.getElementById('selectedAddonsDetail');
  if (details.length > 0) {
    listEl.style.display = 'block';
    detailEl.innerHTML = details.map(d =>
      '<div class="d-flex justify-content-between">
         <span>• ${d.name}</span>
         <span class="fw-semibold" style="color:var(--primary)">${d.price > 0 ? '+₱'+d.price.toFixed(2) : 'FREE'}</span>
       </div>'
    ).join('');
  } else {
    listEl.style.display = 'none';
  }

  updateTotal(addonTotal);
}

function highlightCard(input) {
  const card = input.closest('.addon-card');
  card.style.borderColor    = input.checked ? 'var(--primary)' : '#e9ecef';
  card.style.background     = input.checked ? 'var(--primary-light)' : '';
}

function updateTotal(addonTotal) {
  const isDelivery    = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';
  const serviceCharge = parseFloat(document.getElementById('serviceChargeInput')?.value || 0);
  const total = BASE_PRICE + (addonTotal ?? getCurrentAddonTotal()) + (isDelivery ? deliveryFee + serviceCharge : 0);
  document.getElementById('totalDisplay').textContent = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits:2});
}

function getCurrentAddonTotal() {
  let t = 0;
  document.querySelectorAll('.addon-check:checked').forEach(chk => {
    t += parseFloat(chk.closest('.addon-card').dataset.price) || 0;
  });
  return t;
}

// ── Delivery ───────────────────────────────────────────────────
function toggleDelivery() {
  const isDelivery = document.querySelector('[name=fulfillment_type]:checked').value === 'Delivery';
  document.getElementById('deliverySection').style.display = isDelivery ? 'block' : 'none';
  if (isDelivery && !map) initMap();
  updateFee();
}

// ── Reverse Geocoding via OpenStreetMap Nominatim ─────────────
async function reverseGeocode(lat, lng) {
  const field = document.getElementById('addressField');
  const indicator = document.getElementById('addressLoading');
  if (indicator) indicator.style.display = 'inline';
  try {
    const res  = await fetch(
      'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=' + lng + '&addressdetails=1',
      { headers: { 'Accept-Language': 'en', 'User-Agent': 'CakeshopApp/1.0' } }
    );
    const data = await res.json();
    if (data && data.display_name) {
      // Build a clean readable address
      const a    = data.address || {};
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

function initMap() {
  @php
    $defLat = $defaultAddr->latitude ?? 14.5995;
    $defLng = $defaultAddr->longitude ?? 120.9842;
  @endphp
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

  // OOC notice
  const oocNotice = document.getElementById('oocNotice');
  if (oocNotice) oocNotice.style.display = (zoneType === 'ooc') ? 'block' : 'none';

  // Fee preview box
  const feePreview = document.getElementById('feePreview');
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

  // Order summary panel
  const feeRow     = document.getElementById('feeRow');
  const serviceRow = document.getElementById('serviceRow');
  if (isDelivery && deliveryFee > 0) {
    if (feeRow) { feeRow.style.display = 'flex'; document.getElementById('feeDisplay').textContent = zoneType === 'ooc' ? '₱250+' : '₱' + deliveryFee.toFixed(2); }
  } else {
    if (feeRow) feeRow.style.display = 'none';
  }

  if (document.getElementById('deliveryFeeInput'))
    document.getElementById('deliveryFeeInput').value = deliveryFee;
  updateTotal(getCurrentAddonTotal());
}

// ── Prevent duplicate submit ───────────────────────────────────
function disablePlaceOrder(btn) {
  const isDelivery = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';
  if (isDelivery) {
    const zone = document.getElementById('zoneSelect')?.value;
    if (!zone) {
      alert('Please select your barangay for delivery.');
      return false;
    }
  }
  setTimeout(() => {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Placing Order…';
  }, 10);
}
</script>
@endpush
