@extends('layouts.app')
@section('content')
<div>
  <div class="row justify-content-center">
    <div class="col-lg-10">
      <h4 class="fw-bold mb-4"><i class="bi bi-bag-check me-2" style="color:var(--primary)"></i>Checkout</h4>

      @if(session('error'))
        <div class="alert alert-danger border-0"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
      @endif

      <div class="row g-4">
        {{-- LEFT: Form --}}
        <div class="col-lg-8">
          <form action="{{ route('customer.checkout.place') }}" method="POST" id="checkoutForm">
            @csrf

            {{-- Product Summary --}}
            <div class="card mb-3">
              <div class="card-body d-flex align-items-center gap-3 p-3">
                <img src="{{ $product->image_path }}" alt="{{ $product->name }}"
                     style="width:64px;height:64px;object-fit:cover;border-radius:.7rem"
                     onerror="this.src='https://placehold.co/64x64/fce4ec/e91e63?text=🎂'">
                <div class="flex-grow-1">
                  <div class="fw-bold fs-6">{{ $product->name }}</div>
                  <div class="text-muted small">Qty: {{ $checkout['quantity'] }}
                    @if($checkout['custom_note']) &bull; {{ $checkout['custom_note'] }} @endif
                  </div>
                </div>
                <div class="fw-bold fs-6" style="color:var(--primary)" id="basePrice"
                     data-price="{{ $product->price * $checkout['quantity'] }}">
                  ₱{{ number_format($product->price * $checkout['quantity'], 2) }}
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
                  <div class="mb-3">
                    <label class="form-label fw-semibold small">Delivery Zone</label>
                    <select class="form-select" name="delivery_zone" id="zoneSelect" onchange="updateFee()">
                      <option value="">-- Select Zone --</option>
                      <option value="Zone A" data-fee="50">Zone A — ₱50</option>
                      <option value="Zone B" data-fee="80">Zone B — ₱80</option>
                      <option value="Zone C" data-fee="120">Zone C — ₱120</option>
                    </select>
                    <input type="hidden" name="delivery_fee" id="deliveryFeeInput" value="0">
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-semibold small">Pin Your Location</label>
                    <div id="map" style="height:240px;border-radius:.9rem;border:1px solid #dee2e6"></div>
                    <input type="hidden" name="latitude"  id="lat">
                    <input type="hidden" name="longitude" id="lng">
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-semibold small">Full Address</label>
                    <textarea class="form-control" name="address" id="addressField" rows="2"
                      placeholder="Street, Barangay, City">{{ $defaultAddr ? $defaultAddr->full_address : '' }}</textarea>
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
                    <label class="form-label fw-semibold small">Preferred Time</label>
                    <input type="time" class="form-control" name="schedule_time">
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
                    <label class="form-check-label fw-semibold" for="cod"><i class="bi bi-cash-coin me-1"></i><span id="codLabelText">Cash on Pickup (COP)</span></label>
                    <div class="text-muted" id="codHelpText" style="font-size:.72rem">Pay cash when you pick up your order.</div>
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

              {{-- Delivery fee --}}
              <div class="d-flex justify-content-between small mb-2" id="feeRow" style="display:none!important">
                <span>Delivery Fee</span>
                <span id="feeDisplay">₱0.00</span>
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

              <div class="mt-3 p-2 rounded small text-muted" style="background:#f8f9fa;font-size:.75rem">
                <i class="bi bi-info-circle me-1"></i>
                Final price includes all selected add-ons and delivery fee (if applicable).
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
  const isDelivery = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';
  const total = BASE_PRICE + (addonTotal ?? getCurrentAddonTotal()) + (isDelivery ? deliveryFee : 0);
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
  const codLabel = document.getElementById('codLabelText');
  const codHelp = document.getElementById('codHelpText');
  if (codLabel) codLabel.textContent = isDelivery ? 'Cash on Delivery (COD)' : 'Cash on Pickup (COP)';
  if (codHelp) codHelp.textContent = isDelivery
    ? 'Pay cash when your order arrives.'
    : 'Pay cash when you pick up your order.';
  updateFee();
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
      document.getElementById('lat').value = e.target.getLatLng().lat;
      document.getElementById('lng').value = e.target.getLatLng().lng;
    });
  @endif
  map.on('click', e => {
    if (marker) marker.setLatLng(e.latlng);
    else marker = L.marker(e.latlng, {draggable:true}).addTo(map);
    document.getElementById('lat').value = e.latlng.lat;
    document.getElementById('lng').value = e.latlng.lng;
    marker.on('dragend', ev => {
      document.getElementById('lat').value = ev.target.getLatLng().lat;
      document.getElementById('lng').value = ev.target.getLatLng().lng;
    });
  });
}

function updateFee() {
  const sel = document.getElementById('zoneSelect');
  const opt = sel.options[sel.selectedIndex];
  deliveryFee = parseFloat(opt?.dataset?.fee || 0);
  const isDelivery = document.querySelector('[name=fulfillment_type]:checked').value === 'Delivery';
  const feeRow = document.getElementById('feeRow');
  if (isDelivery && deliveryFee > 0) {
    feeRow.style.display = 'flex';
    document.getElementById('feeDisplay').textContent = '₱' + deliveryFee.toFixed(2);
  } else {
    feeRow.style.display = 'none';
  }
  document.getElementById('deliveryFeeInput').value = deliveryFee;
  updateTotal(getCurrentAddonTotal());
}

// ── Prevent duplicate submit ───────────────────────────────────
function disablePlaceOrder(btn) {
  setTimeout(() => {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Placing Order…';
  }, 10);
}
</script>
@endpush
