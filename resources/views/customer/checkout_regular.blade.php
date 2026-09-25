@extends('layouts.app')
@section('content')
@php
  $checkoutItems = $checkoutItems ?? collect();
  $customCheckoutItems = $checkoutItems->filter(function ($item) {
      $meta = json_decode($item->meta ?? '[]', true) ?: [];
      return ($meta['cart_type'] ?? '') === 'custom_cake';
  })->values();
  $regularCheckoutItems = $checkoutItems->reject(function ($item) {
      $meta = json_decode($item->meta ?? '[]', true) ?: [];
      return ($meta['cart_type'] ?? '') === 'custom_cake';
  })->values();
  $hasCustomCheckout = $customCheckoutItems->isNotEmpty();
  $hasRegularFulfillment = !$checkoutItems->count() || $regularCheckoutItems->isNotEmpty();
  $isGroupCheckout = $checkoutItems->count() > 0;
  $originalSubtotal = $isGroupCheckout
      ? $checkoutItems->sum(fn($item) => (float)$item->unit_price_snapshot * (int)$item->quantity)
      : $pricing['original_unit_price'] * $checkout['quantity'];
  $discountedSubtotal = $isGroupCheckout
      ? $checkoutItems->sum(fn($item) => (float)$item->final_unit_price_snapshot * (int)$item->quantity)
      : $pricing['final_unit_price'] * $checkout['quantity'];
  $productDiscountTotal = $isGroupCheckout
      ? $checkoutItems->sum(fn($item) => (float)$item->discount_amount_snapshot * (int)$item->quantity)
      : $pricing['discount_amount'] * $checkout['quantity'];
  $checkoutCover = $shop->shop_cover ?? $shopSettings->bg_image_path ?? '';
  $loyaltySettings = $loyaltySettings ?? ['earn_enabled' => true, 'redeem_enabled' => true, 'points_base_amount' => 50, 'point_value' => 1, 'max_redemption_percent' => 50, 'redemption_requires_verified' => true];
  $pointValue = (float) ($loyaltySettings['point_value'] ?? 1);
  $maxRedeemPercent = (float) ($loyaltySettings['max_redemption_percent'] ?? 50);
  $earnBaseAmount = (float) ($loyaltySettings['points_base_amount'] ?? 50);
  $requiresVerifiedToRedeem = (bool) ($loyaltySettings['redemption_requires_verified'] ?? true);
  $readyPrepSettings = app(\App\Services\PreparationWindowService::class)->settings($product->shop_id ?? null);
  $readyPrepDays = (int) $readyPrepSettings->ready_made_prep_days;
  $readyMadeEarliestDate = app(\App\Services\PreparationWindowService::class)->earliestDate($product->shop_id ?? null, 'regular')->toDateString();
  $regularScheduleSlots = app(\App\Services\FulfillmentScheduleService::class)->slotsForCheckout($product->shop_id ?? null, 'regular');
  $scheduleSettings = app(\App\Services\FulfillmentScheduleService::class)->settings($product->shop_id ?? null);
  $sweetDealScheduleLimit = $isGroupCheckout
      ? app(\App\Services\SweetDealService::class)->scheduleLimitForCartItems($regularCheckoutItems)
      : app(\App\Services\SweetDealService::class)->scheduleLimitForDirectItem((string) ($product->id ?? ''), $pricing);
  $sweetDealMaxDate = $sweetDealScheduleLimit['date'] ?? null;
  $sweetDealMaxLabel = $sweetDealScheduleLimit['label'] ?? null;
@endphp
@push('styles')
<style>
.checkout-branded-shell{position:relative;min-height:auto;padding:8px clamp(8px,1.4vw,18px) 12px;background:transparent}
.checkout-branded-content{position:relative;width:100%;max-width:none;margin:0}
.checkout-branded-content>.row>.col-lg-8 .card,.checkout-branded-content>.row>.col-lg-4 .card{background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.06)}
.bb-delivery-route{stroke-dasharray:10 12;stroke-linecap:round;animation:bbRouteDash 1.1s linear infinite;filter:drop-shadow(0 2px 4px rgba(15,23,42,.22))}
.bb-customer-pin-wrap{background:transparent;border:0}
.bb-customer-pin{position:relative;width:34px;height:34px;border-radius:50%;background:var(--primary,#e91e63);border:3px solid #fff;box-shadow:0 6px 18px color-mix(in srgb,var(--primary) 45%,transparent);display:flex;align-items:center;justify-content:center}
.bb-customer-pin::before{content:"";position:absolute;inset:-8px;border-radius:50%;border:2px solid color-mix(in srgb,var(--primary) 35%,transparent);animation:bbPinPulse 1.8s ease-out infinite}
.bb-customer-pin span{width:10px;height:10px;border-radius:50%;background:#fff;box-shadow:0 0 0 3px color-mix(in srgb,#fff 40%,transparent)}
@keyframes bbRouteDash{to{stroke-dashoffset:-22}}
@keyframes bbPinPulse{0%{transform:scale(.75);opacity:.85}100%{transform:scale(1.45);opacity:0}}
@media(max-width:575.98px){.checkout-branded-shell{padding:6px 0 10px}.checkout-branded-content{padding-left:8px;padding-right:8px}}
</style>
@endpush
<script>
document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
document.body.classList.remove('modal-open');
document.body.style.overflow = '';
document.body.style.paddingRight = '';
</script>
<div class="checkout-branded-shell">
<div class="checkout-branded-content py-2 py-md-3">
  <h4 class="fw-bold mb-3 text-center"><i class="bi bi-bag-check me-2" style="color:var(--primary)"></i>Checkout</h4>

  @if(session('error'))
    <div class="alert alert-danger border-0"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
  @endif

  <div class="row g-4">
    {{-- LEFT: Form --}}
    <div class="col-lg-8 col-xl-9">
      <form action="{{ route('customer.checkout.place') }}" method="POST" id="checkoutForm" data-prevent-double-submit>
        @csrf
        <input type="hidden" name="selected_size" value="{{ $checkout['selected_size'] ?? '' }}">

        {{-- Product Summary --}}
        <div class="card mb-3" style="border:1.5px solid color-mix(in srgb,var(--primary) 15%,transparent);border-radius:1rem;overflow:hidden">
          <div class="card-body p-0">
            <div class="d-flex align-items-center gap-3 p-3" style="background:var(--primary-bg)">
              <img src="{{ $product->image_path }}" alt="{{ $product->name }}"
                   style="width:72px;height:72px;object-fit:cover;border-radius:.75rem;flex-shrink:0;box-shadow:0 4px 12px rgba(0,0,0,.12)"
                   onerror="this.src='https://placehold.co/72x72/fce4ec/e91e63?text=🎂'">
              <div class="flex-grow-1 min-width-0">
                <div class="fw-bold" style="font-size:1rem;color:var(--gray-900)">{{ $isGroupCheckout ? 'Seller group checkout' : $product->name }}</div>
                <div class="text-muted small mt-1">
                  <i class="bi bi-box me-1"></i>Qty: <strong>{{ $checkout['quantity'] }}</strong>
                  @if(!$isGroupCheckout && !empty($checkout['selected_size'])) &ensp;<i class="bi bi-rulers me-1"></i>{{ $checkout['selected_size'] }} @endif
                  @if(!$isGroupCheckout && $checkout['custom_note']) <br><i class="bi bi-chat-left-text me-1"></i>{{ $checkout['custom_note'] }} @endif
                </div>
                @if($isGroupCheckout)
                  <div class="mt-2 d-grid gap-1">
                    @foreach($checkoutItems as $ci)
                      <div class="small d-flex justify-content-between gap-2">
                        <span>{{ $ci->product_name }} <span class="text-muted">x{{ $ci->quantity }}{{ $ci->selected_size ? ' · '.$ci->selected_size : '' }}</span></span>
                        <span class="fw-semibold">PHP {{ number_format($ci->final_unit_price_snapshot * $ci->quantity, 2) }}</span>
                      </div>
                    @endforeach
                  </div>
                @endif
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

        {{-- Cake Message --}}
        <div class="card mb-3">
          <div class="card-body p-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-chat-left-quote me-2" style="color:var(--primary)"></i>Cake Message</h6>
            @if($isGroupCheckout)
              <div class="d-grid gap-3">
                @foreach($checkoutItems as $ci)
                  <div class="p-3 rounded-3" style="background:#f8fafc;border:1px solid #e5e7eb">
                    <label class="form-label fw-semibold small mb-1" for="itemNote{{ $ci->id }}">
                      {{ $ci->product_name }}
                      <span class="text-muted fw-normal">(optional)</span>
                      <span class="text-muted fw-normal">x{{ $ci->quantity }}{{ $ci->selected_size ? ' · '.$ci->selected_size : '' }}</span>
                    </label>
                    <textarea class="form-control" name="item_notes[{{ $ci->id }}]" id="itemNote{{ $ci->id }}" rows="2" maxlength="160"
                      placeholder="Example: Happy Birthday, Maria!">{{ old('item_notes.'.$ci->id, $ci->custom_note ?? '') }}</textarea>
                  </div>
                @endforeach
              </div>
              <div class="form-text"><i class="bi bi-info-circle me-1"></i>Each message is saved to its matching cake.</div>
            @else
            <label class="form-label fw-semibold small" for="customNoteField">Product note/message <span class="text-muted fw-normal">(optional)</span></label>
            <textarea class="form-control" name="custom_note" id="customNoteField" rows="2" maxlength="160"
              placeholder="Example: Happy Birthday, Maria!">{{ old('custom_note', $checkout['custom_note'] ?? '') }}</textarea>
            <div class="form-text"><i class="bi bi-info-circle me-1"></i>This note will be sent to the kitchen with your order.</div>
            @endif
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
            @if($hasCustomCheckout)
              <div class="mb-3 d-grid gap-2">
                @foreach($customCheckoutItems as $customItem)
                  @php
                    $customMeta = json_decode($customItem->meta ?? '[]', true) ?: [];
                    $holdExpires = !empty($customMeta['fulfillment_hold_expires_at']) ? \Carbon\Carbon::parse($customMeta['fulfillment_hold_expires_at'], config('app.timezone')) : null;
                    $holdActive = $holdExpires && $holdExpires->gt(now(config('app.timezone')));
                  @endphp
                  <div class="p-3 rounded-3" style="background:#fff7fb;border:1px solid #fce7f3">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                      <div>
                        <div class="fw-bold small" style="color:var(--primary)"><i class="bi bi-lock-fill me-1"></i>Custom cake schedule {{ $holdActive ? 'locked' : 'needs update' }}</div>
                        <div class="text-muted small mt-1">{{ $customItem->product_name }} x{{ $customItem->quantity }}</div>
                      </div>
                      <span class="badge {{ $holdActive ? 'text-bg-success' : 'text-bg-danger' }}">{{ $holdActive ? 'Hold active' : 'Expired' }}</span>
                    </div>
                    <div class="row g-2 mt-2 small">
                      <div class="col-sm-6"><i class="bi bi-calendar-event me-1"></i>{{ $customMeta['schedule_date'] ?? 'No date saved' }}</div>
                      <div class="col-sm-6"><i class="bi bi-clock me-1"></i>{{ $customMeta['time_slot'] ?? 'No time saved' }}</div>
                      <div class="col-sm-6"><i class="bi bi-bag-check me-1"></i>{{ $customMeta['fulfillment_type'] ?? 'Pickup' }}</div>
                      @if(($customMeta['fulfillment_type'] ?? 'Pickup') === 'Delivery')
                        <div class="col-sm-6"><i class="bi bi-geo-alt me-1"></i>{{ $customMeta['address'] ?? 'Address saved with custom request' }}</div>
                      @endif
                    </div>
                    <div class="text-muted mt-2" style="font-size:.78rem">
                      @if($holdActive)
                        This saved date and time will be used for the custom cake. You can update fulfillment after the hold expires.
                      @else
                        Schedule hold expired. Please return to the cart and update fulfillment before checkout.
                      @endif
                    </div>
                  </div>
                @endforeach
              </div>
            @endif
            @if($hasRegularFulfillment)
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
                <div style="border-radius:.9rem;overflow:hidden;border:1.5px solid color-mix(in srgb,var(--primary) 22%,#e5e7eb);box-shadow:0 4px 16px color-mix(in srgb,var(--primary) 12%,transparent)">
                  <div id="deliveryCalcHeader" style="padding:.7rem 1.1rem;background:linear-gradient(135deg,var(--primary) 0%,var(--primary-dark,var(--primary)) 100%)">
                    <div class="d-flex align-items-center justify-content-between">
                      <span class="fw-semibold text-white" style="font-size:.85rem">
                        <i class="bi bi-bicycle me-2"></i>Delivery Quote
                      </span>
                      <span id="deliveryFreeTag" style="display:none;background:rgba(255,255,255,.25);color:#fff;font-size:.68rem;font-weight:700;border-radius:2rem;padding:2px 10px;letter-spacing:.05em">
                        FREE DELIVERY
                      </span>
                    </div>
                  </div>
                  <div style="background:var(--primary-light,#fff0f6);padding:.9rem 1rem">
                    <div class="row g-0 text-center mb-2">
                      <div class="col-4" style="border-right:1px solid color-mix(in srgb,var(--primary) 22%,#e5e7eb)">
                        <div class="fw-bold" id="distDisplay" style="font-size:1.05rem;color:var(--primary);line-height:1.2">—</div>
                        <div class="text-muted" style="font-size:.62rem;letter-spacing:.04em;text-transform:uppercase;margin-top:2px">Distance</div>
                      </div>
                      <div class="col-4" style="border-right:1px solid color-mix(in srgb,var(--primary) 22%,#e5e7eb)">
                        <div class="fw-bold" id="calcFeeDisplay" style="font-size:1.05rem;color:var(--primary-dark,var(--primary));line-height:1.2">₱0.00</div>
                        <div class="text-muted" style="font-size:.62rem;letter-spacing:.04em;text-transform:uppercase;margin-top:2px">Delivery Fee</div>
                      </div>
                      <div class="col-4">
                        <div class="fw-bold" id="calcEtaDisplay" style="font-size:1.05rem;color:var(--primary);line-height:1.2">—</div>
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
                  <div class="mt-3 p-3 rounded-3 bb-surprise-card" style="border:1.5px solid color-mix(in srgb,var(--primary) 22%,#e5e7eb);background:linear-gradient(135deg,#fff 0%,var(--primary-light,#fff5f8) 100%);box-shadow:0 10px 24px rgba(15,23,42,.06)">
                    <div class="form-check form-switch d-flex align-items-center gap-2 mb-2">
                      <input class="form-check-input" type="checkbox" name="is_surprise_delivery" id="surpriseDelivery" value="1" onchange="toggleSurpriseDelivery()">
                      <label class="form-check-label fw-bold" for="surpriseDelivery"><i class="bi bi-gift me-1" style="color:var(--primary)"></i>Send as surprise gift</label>
                    </div>
                    <div class="text-muted small mb-3">Use this when the cake is for another recipient. Payment stays with you and the recipient will not be asked to pay.</div>
                    <div id="surpriseFields" style="display:none">
                      <div class="row g-3">
                        <div class="col-md-6">
                          <label class="form-label fw-semibold small">Recipient Name <span class="text-danger">*</span></label>
                          <input type="text" class="form-control" name="recipient_name" id="recipientName" maxlength="120" placeholder="Who will receive the cake?">
                        </div>
                        <div class="col-md-6">
                          <label class="form-label fw-semibold small">Recipient Phone <span class="text-muted fw-normal">(optional)</span></label>
                          <input type="tel" class="form-control" name="recipient_phone" id="recipientPhone" maxlength="30" placeholder="09XXXXXXXXX"><div class="form-text">Optional. Rider will use this only if delivery cannot be completed. If blank, sender will be contacted first.</div>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label fw-semibold small">Sender Name on Card</label>
                          <input type="text" class="form-control" name="sender_display_name" maxlength="120" value="{{ session('user')['fullname'] ?? '' }}" placeholder="Example: Mama, Papa, Your friend">
                        </div>
                        <div class="col-md-6">
                          <label class="form-label fw-semibold small">Contact Rule</label>
                          <select class="form-select" name="surprise_contact_policy">
                            <option value="sender_first">Call me first before recipient</option>
                            <option value="recipient_if_needed">Call recipient only if needed</option>
                            <option value="recipient_ok">Recipient may be called directly</option>
                          </select>
                        </div>
                        <div class="col-12">
                          <label class="form-label fw-semibold small">Gift Message</label>
                          <textarea class="form-control" name="gift_message" rows="2" maxlength="500" placeholder="Optional message for the recipient"></textarea>
                        </div>
                        <div class="col-12">
                          <label class="form-label fw-semibold small">Rider / Seller Instructions</label>
                          <textarea class="form-control" name="delivery_instructions" rows="2" maxlength="500" placeholder="Example: Please call me first, do not mention the price, hand it to the guard if recipient is not outside."></textarea>
                        </div>
                        <div class="col-12">
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="hide_sender_name" id="hideSenderName" value="1">
                            <label class="form-check-label small" for="hideSenderName">Hide my name from recipient-facing notes</label>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-sm-6">
                <label class="form-label fw-semibold small">Preferred Date</label>
                <input type="date" class="form-control" name="schedule_date" id="custFieldDate"
                       value="{{ old('schedule_date', $checkout['schedule_date'] ?? '') }}"
                       min="{{ !empty($checkout['request_offer_checkout']) ? ($checkout['schedule_date'] ?? $readyMadeEarliestDate) : $readyMadeEarliestDate }}"
                       @if($sweetDealMaxDate) max="{{ $sweetDealMaxDate }}" data-sweet-deal-max-label="{{ $sweetDealMaxLabel }}" @endif
                       onchange="updateRegularScheduleSlots('custFieldDate','custFieldTime','custScheduleNotice')">
                <div id="custScheduleNotice" class="mt-1" style="font-size:.8rem;min-height:18px"></div>
                <div class="form-text"><i class="bi bi-info-circle me-1"></i>You can order for today or any future date while a time is still open.</div>
                @if($sweetDealMaxLabel)
                  <div class="form-text" style="color:#be123c"><i class="bi bi-tags me-1"></i>Sweet Deal schedules must be on or before {{ $sweetDealMaxLabel }}.</div>
                @endif
              </div>
              <div class="col-sm-6">
                <label class="form-label fw-semibold small">Preferred Time</label>
                <input type="time" class="form-control" name="schedule_time" id="custFieldTime" value="{{ old('schedule_time', $checkout['schedule_time'] ?? '') }}" min="{{ substr($scheduleSettings->shop_open_time ?? '09:00', 0, 5) }}" max="{{ substr($scheduleSettings->shop_close_time ?? '19:00', 0, 5) }}" onchange="updateRegularScheduleSlots('custFieldDate','custFieldTime','custScheduleNotice')" oninput="updateRegularScheduleSlots('custFieldDate','custFieldTime','custScheduleNotice')">
              </div>
            </div>
            @endif
          </div>
        </div>

        {{-- Promo / Voucher --}}
        <div class="card mb-3">
          <div class="card-body p-4">
            <h6 class="fw-bold mb-2"><i class="bi bi-ticket-perforated me-2" style="color:var(--primary)"></i>Promo Code</h6>
            @if(!empty($availableVouchers))
              <div class="d-grid gap-2 mb-3">
                @foreach($availableVouchers as $voucher)
                  <div class="p-2 rounded-3 d-flex flex-wrap align-items-center justify-content-between gap-2" style="background:#f8fafc;border:1px solid #e5e7eb">
                    <div>
                      <div class="fw-semibold small">{{ $voucher->name }}</div>
                      <div class="text-muted" style="font-size:.76rem">
                        <span class="fw-semibold">{{ $voucher->code }}</span>
                        @if($voucher->shop_id)
                          <span class="ms-1">Shop voucher</span>
                        @else
                          <span class="ms-1">Platform voucher</span>
                        @endif
                        @if($voucher->requires_verified_customer)
                          <span class="badge text-bg-light ms-1">Verified</span>
                        @endif
                      </div>
                      <div class="text-muted" style="font-size:.72rem">{{ $voucher->validation_ok ? 'Estimated discount PHP '.number_format($voucher->computed_discount, 2) : $voucher->validation_message }}</div>
                    </div>
                    <button type="button" class="btn btn-sm {{ $voucher->validation_ok ? 'btn-outline-primary' : 'btn-outline-secondary' }}" data-voucher-code="{{ $voucher->code }}" data-voucher-discount="{{ number_format((float) ($voucher->computed_discount ?? 0), 2, '.', '') }}" {{ $voucher->validation_ok ? '' : 'disabled' }}>
                      Use
                    </button>
                  </div>
                @endforeach
              </div>
            @endif
            <div class="input-group">
              <input type="text" class="form-control text-uppercase" name="voucher_code" id="voucherCodeInput" maxlength="40" placeholder="Enter voucher code">
              <span class="input-group-text"><i class="bi bi-stars"></i></span>
            </div>
            <div class="form-text">Tap Use to preview voucher savings. Final validation still happens securely when you place the order.</div>
          </div>
        </div>

        {{-- Rewards Points --}}
        <div class="card mb-3">
          <div class="card-body p-4">
            <h6 class="fw-bold mb-2"><i class="bi bi-award me-2" style="color:var(--primary)"></i>Use Rewards Points</h6>
            <div class="d-flex flex-wrap justify-content-between gap-2 small mb-2">
              <span class="text-muted">Available: <strong>{{ (int)($loyaltyQuote['balance'] ?? 0) }} pts</strong></span>
              <span class="text-muted">Max this order: <strong>{{ (int)($loyaltyQuote['max'] ?? 0) }} pts</strong></span>
            </div>
            <div class="input-group">
              <input type="number" class="form-control" name="points_to_redeem" id="pointsToRedeem" min="0" max="{{ (int)($loyaltyQuote['max'] ?? 0) }}" value="{{ old('points_to_redeem', 0) }}" {{ $verificationStatus === 'approved' ? '' : 'disabled' }}>
              <span class="input-group-text">points</span>
            </div>
            <div class="form-text">
              @if($verificationStatus === 'approved')
                1 point = PHP {{ number_format($pointValue, 2) }} discount. Points can cover up to {{ rtrim(rtrim(number_format($maxRedeemPercent, 2), '0'), '.') }}% of product subtotal after vouchers.
              @else
                {{ $requiresVerifiedToRedeem ? 'Verify your account first to redeem points.' : 'Rewards redemption is available for your account.' }} You can still earn points from paid completed orders.
              @endif
            </div>
            @if(!empty($loyaltyEarnEstimate['enabled']))
              <div class="small mt-2" style="color:var(--primary)">
                <i class="bi bi-plus-circle me-1"></i>Estimated earning after paid completion: <strong>{{ (int) ($loyaltyEarnEstimate['points'] ?? 0) }} pts</strong>
                <span class="text-muted">({{ number_format((float) ($loyaltyEarnEstimate['multiplier'] ?? 1), 2) }}x tier multiplier)</span>
              </div>
            @endif
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
    <div class="col-lg-4 col-xl-3 bb-sticky-order-column">
      <div class="card">
        <div class="card-body p-4">
          <h6 class="fw-bold mb-3">📋 Order Summary</h6>
          <div class="d-flex justify-content-between small mb-2">
            <span>{{ $product->name }} × {{ $checkout['quantity'] }}</span>
            <span>₱{{ number_format($originalSubtotal,2) }}</span>
          </div>
          @if($isGroupCheckout)
            @foreach($checkoutItems as $ci)
              <div class="d-flex justify-content-between small mb-2 text-muted">
                <span>{{ $ci->product_name }} x{{ $ci->quantity }}</span>
                <span>PHP {{ number_format($ci->final_unit_price_snapshot * $ci->quantity, 2) }}</span>
              </div>
            @endforeach
          @endif
          @if(!empty($pricing['has_discount']))
          <div class="d-flex justify-content-between small mb-2">
            <span class="text-muted">{{ $pricing['badge_text'] }} Product Discount</span>
            <span style="color:#dc2626">-₱{{ number_format($productDiscountTotal,2) }}</span>
          </div>
          @endif
          <div id="addonSummary"></div>
          <div class="d-flex justify-content-between small mb-1" id="voucherPreviewRow" style="display:none"><span class="text-muted">Promo Code</span><span id="voucherPreviewDisplay" style="color:#16a34a">-PHP 0.00</span></div>
          <div class="d-flex justify-content-between small mb-1" id="pointsPreviewRow" style="display:none">
            <span class="text-muted">Rewards Points</span>
            <span id="pointsPreviewDisplay" style="color:#16a34a">-PHP 0.00</span>
          </div>
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
</div>

<script>
const FULFILLMENT_SCHEDULE = {
  readyPrep: {{ (int)($scheduleSettings->ready_made_prep_minutes ?? 90) }},
  pickupBuffer: {{ (int)($scheduleSettings->pickup_buffer_minutes ?? 0) }},
  deliveryBaseBuffer: {{ (int)($scheduleSettings->delivery_base_buffer_minutes ?? 30) }},
  deliveryMinutesPerKm: {{ (int)($scheduleSettings->delivery_minutes_per_km ?? 5) }},
  shopOpen: @json(substr($scheduleSettings->shop_open_time ?? '09:00', 0, 5)),
  shopClose: @json(substr($scheduleSettings->shop_close_time ?? '19:00', 0, 5)),
  shopLat: {{ $scheduleSettings->shop_lat !== null ? (float)$scheduleSettings->shop_lat : 'null' }},
  shopLng: {{ $scheduleSettings->shop_lng !== null ? (float)$scheduleSettings->shop_lng : 'null' }}
};
const SERVER_NOW = new Date(@json(now(config('app.timezone'))->format('Y-m-d H:i:s')));
function minutesOf(time) {
  const parts = String(time || '').split(':').map(Number);
  return ((parts[0] || 0) * 60) + (parts[1] || 0);
}
function formatScheduleTime(totalMinutes) {
  totalMinutes = Math.max(0, Math.min(1439, Math.ceil(totalMinutes)));
  const h = Math.floor(totalMinutes / 60);
  const m = totalMinutes % 60;
  const suffix = h >= 12 ? 'PM' : 'AM';
  const hh = ((h + 11) % 12) + 1;
  return `${hh}:${String(m).padStart(2, '0')} ${suffix}`;
}
function activeFulfillment() {
  return document.querySelector('[name="fulfillment_type"]:checked')?.value || 'Pickup';
}
function distanceKm(lat1, lng1, lat2, lng2) {
  const toRad = deg => deg * Math.PI / 180;
  const dLat = toRad(lat2 - lat1);
  const dLng = toRad(lng2 - lng1);
  const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
  return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}
function requiredLeadMinutes() {
  const fulfillment = activeFulfillment();
  let minutes = Number(FULFILLMENT_SCHEDULE.readyPrep || 0);
  if (fulfillment === 'Delivery') {
    minutes += Number(FULFILLMENT_SCHEDULE.deliveryBaseBuffer || 0);
    const lat = parseFloat(document.getElementById('lat')?.value || document.querySelector('[name="latitude"]')?.value || '');
    const lng = parseFloat(document.getElementById('lng')?.value || document.querySelector('[name="longitude"]')?.value || '');
    if (!Number.isNaN(lat) && !Number.isNaN(lng) && FULFILLMENT_SCHEDULE.shopLat !== null && FULFILLMENT_SCHEDULE.shopLng !== null) {
      minutes += Math.ceil(distanceKm(Number(FULFILLMENT_SCHEDULE.shopLat), Number(FULFILLMENT_SCHEDULE.shopLng), lat, lng) * Number(FULFILLMENT_SCHEDULE.deliveryMinutesPerKm || 0));
    }
  } else {
    minutes += Number(FULFILLMENT_SCHEDULE.pickupBuffer || 0);
  }
  return Math.max(0, minutes);
}
function setScheduleNotice(notice, message, type) {
  if (!notice) return;
  if (notice.classList.contains('cv-msg')) notice.className = 'cv-msg ' + (type === 'error' ? 'cv-err' : 'cv-ok');
  notice.innerHTML = message;
}
function updateRegularScheduleSlots(dateId, timeId, noticeId) {
  const dateEl = document.getElementById(dateId);
  const timeEl = document.getElementById(timeId);
  const notice = document.getElementById(noticeId);
  if (!dateEl || !timeEl) return true;

  const openMins = minutesOf(FULFILLMENT_SCHEDULE.shopOpen || '09:00');
  const closeMins = minutesOf(FULFILLMENT_SCHEDULE.shopClose || '19:00');
  timeEl.min = FULFILLMENT_SCHEDULE.shopOpen || '09:00';
  timeEl.max = FULFILLMENT_SCHEDULE.shopClose || '19:00';
  timeEl.setCustomValidity('');

  const selectedDate = dateEl.value;
  const selectedMins = minutesOf(timeEl.value);
  const today = SERVER_NOW.toISOString().slice(0, 10);
  const earliestMins = SERVER_NOW.getHours() * 60 + SERVER_NOW.getMinutes() + requiredLeadMinutes();
  const earliestAllowed = selectedDate === today ? Math.max(openMins, earliestMins) : openMins;
  const hoursText = `${formatScheduleTime(openMins)} to ${formatScheduleTime(closeMins)}`;

  if (!selectedDate) {
    setScheduleNotice(notice, '', 'ok');
    return true;
  }
  if (selectedDate === today && earliestAllowed > closeMins) {
    const msg = '<span class="text-danger fw-semibold"><i class="bi bi-x-circle-fill me-1"></i>No remaining time today has enough preparation' + (activeFulfillment() === 'Delivery' ? ' and delivery travel time.' : ' time.') + '</span>';
    timeEl.setCustomValidity('No available time today. Please choose another date.');
    setScheduleNotice(notice, msg, 'error');
    return false;
  }
  if (!timeEl.value) {
    const msg = selectedDate === today
      ? '<span class="text-warning fw-semibold"><i class="bi bi-clock-fill me-1"></i>Earliest available today: ' + formatScheduleTime(earliestAllowed) + '. Shop hours: ' + hoursText + '.</span>'
      : '<span class="text-muted"><i class="bi bi-clock me-1"></i>Shop hours: ' + hoursText + '.</span>';
    setScheduleNotice(notice, msg, 'ok');
    return true;
  }
  if (selectedMins < openMins || selectedMins > closeMins) {
    timeEl.setCustomValidity('Please choose a time within shop hours.');
    setScheduleNotice(notice, '<span class="text-danger fw-semibold"><i class="bi bi-exclamation-circle-fill me-1"></i>Please choose a time within shop hours: ' + hoursText + '.</span>', 'error');
    return false;
  }
  if (selectedDate === today && selectedMins < earliestAllowed) {
    timeEl.setCustomValidity('That time is too soon for preparation.');
    setScheduleNotice(notice, '<span class="text-danger fw-semibold"><i class="bi bi-exclamation-circle-fill me-1"></i>Earliest available today is ' + formatScheduleTime(earliestAllowed) + '.</span>', 'error');
    return false;
  }
  setScheduleNotice(notice, selectedDate === today ? '<span class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>This time has enough preparation allowance.</span>' : '', 'ok');
  return true;
}
document.addEventListener('DOMContentLoaded', function() {
  updateRegularScheduleSlots('custFieldDate','custFieldTime','custScheduleNotice');
});
</script>

@endsection
@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@turf/turf@6/turf.min.js"></script>
<script>
// ── Shop & coverage data from server ──────────────────
const SHOP_META = {
  lat:       {{ $shopSettings->shop_lat  ?? 'null' }},
  lng:       {{ $shopSettings->shop_lng  ?? 'null' }},
  baseFee:   {{ (float)($shopSettings->base_fee   ?? 30) }},
  feePerKm:  {{ (float)($shopSettings->fee_per_km ?? 15) }},
  freeRadius: {{ (int)($shopSettings->free_delivery_radius ?? 0) }},
  coverageRadius: {{ (int)($shopSettings->delivery_coverage_radius ?? 5000) }},
};
const COVERAGE_ZONES   = @json($deliveryZones->values());
const COVERAGE_RADIUS  = Math.max(1000, SHOP_META.coverageRadius || 5000);
const BASE_PRICE       = {{ (float) $discountedSubtotal }};
const HAS_PRODUCT_DISCOUNT = {{ !empty($pricing['has_discount']) ? 'true' : 'false' }};
const POINT_BALANCE = {{ (int)($loyaltyQuote['balance'] ?? 0) }};
const POINT_VALUE = {{ json_encode((float) $pointValue) }};
const MAX_REDEEM_PERCENT = {{ json_encode((float) $maxRedeemPercent) }};
let MAX_REDEEMABLE_POINTS = {{ (int)($loyaltyQuote['max'] ?? 0) }};
let voucherPreviewDiscount = 0;
let deliveryFee = 0;
let map, marker, routeLine;
let deliveryCoverageBlocked = false;
let addressLookupSeq = 0;

function getThemeColor() {
  return getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#e91e63';
}

function curvedDeliveryRoute(startLat, startLng, endLat, endLng) {
  const points = [];
  const dx = endLng - startLng;
  const dy = endLat - startLat;
  const distance = Math.sqrt(dx * dx + dy * dy) || 0.001;
  const curve = Math.min(0.012, Math.max(0.002, distance * 0.22));
  const offsetLat = -dx / distance * curve;
  const offsetLng = dy / distance * curve;

  for (let i = 0; i <= 28; i++) {
    const t = i / 28;
    const lift = Math.sin(Math.PI * t);
    points.push([
      startLat + dy * t + offsetLat * lift,
      startLng + dx * t + offsetLng * lift
    ]);
  }
  return points;
}

function updateDeliveryRoute(lat, lng) {
  if (!map || !SHOP_META.lat || !SHOP_META.lng) return;
  const points = curvedDeliveryRoute(parseFloat(SHOP_META.lat), parseFloat(SHOP_META.lng), parseFloat(lat), parseFloat(lng));
  if (routeLine) {
    routeLine.setLatLngs(points);
    routeLine.setStyle({ color: getThemeColor() });
  } else {
    routeLine = L.polyline(points, {
      color: getThemeColor(),
      weight: 4,
      opacity: .9,
      dashArray: '10 12',
      lineCap: 'round',
      className: 'bb-delivery-route'
    }).addTo(map);
  }
  routeLine.bringToFront();
}

function cleanAddressParts(parts) {
  const seen = new Set();
  return parts
    .map(part => String(part || '').replace(/\s+/g, ' ').trim())
    .filter(part => {
      if (!part) return false;
      const key = part.toLowerCase();
      if (seen.has(key)) return false;
      seen.add(key);
      return true;
    });
}

function formatNominatimAddress(data) {
  const a = data?.address || {};
  const street = cleanAddressParts([
    a.house_number && a.road ? a.house_number + ' ' + a.road : null,
    !a.house_number ? a.road : null,
    a.building || a.amenity || a.shop
  ]);
  const area = a.suburb || a.village || a.neighbourhood || a.quarter || a.hamlet || a.city_district;
  const city = a.city || a.town || a.municipality;
  const parts = cleanAddressParts([
    ...street,
    area,
    city,
    a.state || a.province,
    a.postcode,
    a.country
  ]);
  return parts.length ? parts.join(', ') : String(data?.display_name || '').replace(/\s+/g, ' ').trim();
}

function getDetectedAreaName(data) {
  const a = data?.address || {};
  return a.suburb || a.village || a.neighbourhood || a.quarter || a.hamlet || a.city_district || '';
}

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
  const km = dist / 1000;
  const freeKm = Math.max(0, (SHOP_META.freeRadius || 0) / 1000);
  const chargeKm = Math.max(0, km - freeKm);
  if (chargeKm <= 0) return 0;
  return Math.ceil(SHOP_META.baseFee + SHOP_META.feePerKm * chargeKm);
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

function formatCoverageDistance(distance) {
  return distance < 1000 ? Math.round(distance) + ' m' : (distance / 1000).toFixed(2) + ' km';
}

function nearestCoverageZone(lat, lng) {
  let nearest = null;
  COVERAGE_ZONES.forEach(z => {
    if (!z.lat || !z.lng) return;
    const distance = haversine(lat, lng, parseFloat(z.lat), parseFloat(z.lng));
    if (!nearest || distance < nearest.distance) nearest = { zone: z, distance };
  });
  return nearest;
}

// ── On customer pin set ────────────────────────────────
function onPinSet(lat, lng) {
  document.getElementById('lat').value = lat;
  document.getElementById('lng').value = lng;
  updateDeliveryRoute(lat, lng);

  // Coverage check
  const covered = isInCoverage(lat, lng);
  const statusEl = document.getElementById('coverageStatus');
  const msgEl    = document.getElementById('coverageMsg');
  if (covered === null) {
    deliveryCoverageBlocked = false;
    statusEl.style.display = 'none';
  } else if (covered) {
    deliveryCoverageBlocked = false;
    const nearest = nearestCoverageZone(lat, lng);
    const zoneName = nearest?.zone?.barangay || 'this seller';
    statusEl.style.cssText = 'display:block;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;border-radius:.5rem;padding:.5rem .75rem';
    msgEl.textContent = 'Inside delivery coverage: ' + zoneName + '.';
  } else {
    deliveryCoverageBlocked = true;
    const nearest = nearestCoverageZone(lat, lng);
    const nearestText = nearest ? ' Nearest coverage: ' + (nearest.zone.barangay || 'coverage area') + ', ' + formatCoverageDistance(nearest.distance) + ' away.' : '';
    statusEl.style.cssText = 'display:block;background:#fff1f2;color:#9f1239;border:1px solid #fecdd3;border-radius:.5rem;padding:.5rem .75rem';
    msgEl.textContent = 'Outside this seller delivery area.' + nearestText + ' Move the pin or choose pickup.';
    deliveryFee = 0;
    document.getElementById('deliveryFeeInput').value = 0;
    document.getElementById('deliveryCalcBox').style.display = 'none';
    const feeRow = document.getElementById('feeRow');
    if (feeRow) feeRow.style.display = 'none';
    updateTotal(getCurrentAddonTotal());
    reverseGeocode(lat, lng);
    return;
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
      feeEl.style.color  = 'var(--primary)';
      freeEl.style.display = '';
      hdrEl.style.background = 'linear-gradient(135deg,var(--primary) 0%,var(--primary-dark,var(--primary)) 100%)';
    } else {
      feeEl.textContent  = '₱' + fee.toFixed(2);
      feeEl.style.color  = 'var(--primary-dark,var(--primary))';
      freeEl.style.display = 'none';
      hdrEl.style.background = 'linear-gradient(135deg,var(--primary) 0%,var(--primary-dark,var(--primary)) 100%)';
    }

    // ETA
    document.getElementById('calcEtaDisplay').textContent = '~' + etaText(mins);

    // Breakdown
    const bd = document.getElementById('feeBreakdown');
    if (fee === 0 && SHOP_META.freeRadius > 0) {
      const freeLabel = SHOP_META.freeRadius >= 1000
        ? (SHOP_META.freeRadius / 1000).toFixed(1) + ' km' : SHOP_META.freeRadius + ' m';
      bd.innerHTML = `<i class="bi bi-gift me-1" style="color:var(--primary)"></i>Free delivery within ${freeLabel} from shop`;
    } else if (fee > 0) {
      const freeKm = Math.max(0, (SHOP_META.freeRadius || 0) / 1000);
      const chargeKm = Math.max(0, km - freeKm);
      const kmPart = SHOP_META.feePerKm * chargeKm;
      bd.innerHTML =
        `<div class="d-flex justify-content-between"><span><i class="bi bi-gift me-1" style="color:var(--primary)"></i>Free distance</span><span class="fw-semibold">${freeKm.toFixed(1)} km</span></div>` +
        `<div class="d-flex justify-content-between"><span><i class="bi bi-truck me-1" style="color:var(--primary)"></i>Base delivery fee</span><span class="fw-semibold">₱${SHOP_META.baseFee.toFixed(2)}</span></div>` +
        `<div class="d-flex justify-content-between"><span><i class="bi bi-geo-alt me-1" style="color:var(--primary)"></i>₱${SHOP_META.feePerKm.toFixed(2)}/km × ${chargeKm.toFixed(2)} km excess</span><span class="fw-semibold">₱${kmPart.toFixed(2)}</span></div>`;
    } else {
      bd.innerHTML = '';
    }

    calcBox.style.display = '';

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
      html: `<div style="background:var(--primary);width:36px;height:36px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 3px 12px color-mix(in srgb,var(--primary) 45%,transparent);display:flex;align-items:center;justify-content:center">
               <span style="transform:rotate(45deg);font-size:15px;line-height:1">🏪</span>
             </div>`,
      className: '', iconSize: [36,36], iconAnchor: [18,36]
    });
    L.marker([SHOP_META.lat, SHOP_META.lng], {icon: shopIcon, interactive: true})
      .addTo(map).bindTooltip('Cake Shop', {permanent: false, direction: 'top'});
  }

  drawCoverageAreas();

  // Pre-load default address pin
  @if($defaultAddr && $defaultAddr->latitude && $defaultAddr->longitude)
    setMarkerAt(L.latLng({{ $defaultAddr->latitude }}, {{ $defaultAddr->longitude }}), true);
    map.setView([{{ $defaultAddr->latitude }}, {{ $defaultAddr->longitude }}], 15);
  @endif

  map.on('click', e => setMarkerAt(e.latlng, true));
}

function setMarkerAt(latlng, triggerPin = true) {
  if (marker) {
    marker.setLatLng(latlng);
  } else {
    const pinIcon = L.divIcon({
      html: '<div class="bb-customer-pin"><span></span></div>',
      className: 'bb-customer-pin-wrap', iconSize: [34,34], iconAnchor: [17,17]
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
    updateDeliveryRoute(latlng.lat, latlng.lng);
  }
  setTimeout(() => updateRegularScheduleSlots('custFieldDate','custFieldTime','custScheduleNotice'), 250);
}

function drawCoverageAreas() {
  const coverageFeatures = [];
  COVERAGE_ZONES.forEach(z => {
    if (!z.lat || !z.lng) return;
    const zLat = parseFloat(z.lat);
    const zLng = parseFloat(z.lng);
    if (!Number.isFinite(zLat) || !Number.isFinite(zLng)) return;

    if (window.turf) {
      coverageFeatures.push(turf.circle([zLng, zLat], COVERAGE_RADIUS / 1000, {
        steps: 72,
        units: 'kilometers',
        properties: { name: z.barangay || 'Coverage Area' }
      }));
    } else {
      L.circle([zLat, zLng], {
        radius: COVERAGE_RADIUS, color: '#16a34a', weight: 1.5,
        fillColor: '#22c55e', fillOpacity: .09, dashArray: '6 4', interactive: false
      }).addTo(map);
    }

    const cIcon = L.divIcon({
      html: '<div style="background:#22c55e;width:10px;height:10px;border-radius:50%;opacity:.85;border:2px solid #15803d"></div>',
      className: '', iconSize: [10,10], iconAnchor: [5,5]
    });
    L.marker([zLat, zLng], {icon: cIcon, interactive: false}).addTo(map)
      .bindTooltip(z.barangay || 'Coverage Area');
  });

  if (coverageFeatures.length) {
    let merged = coverageFeatures[0];
    for (let i = 1; i < coverageFeatures.length; i++) {
      try { merged = turf.union(merged, coverageFeatures[i]) || merged; }
      catch (e) { merged = turf.featureCollection(coverageFeatures); break; }
    }
    L.geoJSON(merged, {
      interactive: false,
      style: {
        color: '#15803d',
        weight: 2,
        opacity: .75,
        fillColor: '#22c55e',
        fillOpacity: .18
      }
    }).addTo(map);
  }
}

function detectMyLocation() {
  if (!window.berryBaseHasLocationSupport?.()) { alert('Geolocation is not supported by your browser.'); return; }
  const btn = document.getElementById('detectBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Detecting…';
  window.berryBaseGetCurrentPosition(
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
function isSurpriseDeliverySelected() {
  return document.getElementById('surpriseDelivery')?.checked === true;
}

function toggleSurpriseDelivery() {
  const enabled = isSurpriseDeliverySelected();
  const fields = document.getElementById('surpriseFields');
  if (fields) fields.style.display = enabled ? 'block' : 'none';
  ['recipientName'].forEach(id => {
    const field = document.getElementById(id);
    if (!field) return;
    field.required = enabled;
    field.setAttribute('aria-required', enabled ? 'true' : 'false');
  });
  const saveAddr = document.getElementById('saveAddr');
  const saveWrap = saveAddr?.closest('.form-check');
  if (saveAddr) {
    saveAddr.checked = enabled ? false : saveAddr.checked;
    saveAddr.disabled = enabled;
  }
  if (saveWrap) saveWrap.style.display = enabled ? 'none' : '';
  const cod = document.getElementById('cod');
  const gcash = document.getElementById('gcash');
  if (cod) cod.disabled = enabled;
  if (enabled && gcash) gcash.checked = true;
  const codLabel = document.getElementById('codLabelText');
  const codHelp = document.getElementById('codHelpText');
  if (enabled) {
    if (codLabel) codLabel.textContent = 'Cash payment disabled for surprise';
    if (codHelp) codHelp.textContent = 'Use GCash so the recipient will not be asked to pay.';
  } else {
    const isDelivery = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';
    if (codLabel) codLabel.textContent = isDelivery ? 'Cash on Delivery (COD)' : 'Cash on Pickup (COP)';
    if (codHelp) codHelp.textContent = isDelivery ? 'Pay cash when your order arrives.' : 'Pay cash when you pick up your order.';
  }
}

function validateSurpriseDelivery() {
  if (!isSurpriseDeliverySelected()) return true;
  const nameField = document.getElementById('recipientName');
  const name = nameField?.value?.trim();
  const gcash = document.getElementById('gcash');
  if (!name) {
    alert('Please enter the surprise recipient name before continuing.');
    nameField?.focus();
    nameField?.reportValidity?.();
    return false;
  }
  if (gcash && !gcash.checked) {
    alert('Surprise delivery must use GCash so the recipient will not be asked to pay.');
    gcash.checked = true;
    return false;
  }
  return true;
}
async function reverseGeocode(lat, lng) {
  const lookupId = ++addressLookupSeq;
  const field = document.getElementById('addressField');
  const ind   = document.getElementById('addressLoading');
  if (ind) ind.style.display = 'inline';
  try {
    const _ctrl = new AbortController(); setTimeout(() => _ctrl.abort(), 6000);
    const res  = await fetch(`/api/geocode/reverse?lat=${lat}&lng=${lng}`, { signal: _ctrl.signal });
    const data = await res.json();
    if (lookupId === addressLookupSeq && data && data.display_name) {
      field.value = formatNominatimAddress(data);
      // Also store area name as delivery zone
      const area = getDetectedAreaName(data);
      document.getElementById('deliveryZoneInput').value = area || field.value.split(',')[0] || '';
    }
    return data;
  } catch (e) {}
  finally { if (lookupId === addressLookupSeq && ind) ind.style.display = 'none'; }
  return null;
}

// ── Fulfillment toggle ────────────────────────────────
function toggleDelivery() {
  const isDelivery = document.querySelector('[name=fulfillment_type]:checked').value === 'Delivery';
  document.getElementById('deliverySection').style.display = isDelivery ? 'block' : 'none';
  if (isDelivery && !map) initMap();
  if (!isDelivery) deliveryFee = 0;
  updatePaymentMethodLabel();
  toggleSurpriseDelivery();
  updateTotal(getCurrentAddonTotal());
  updateRegularScheduleSlots('custFieldDate','custFieldTime','custScheduleNotice');
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
  const pointsInput = document.getElementById('pointsToRedeem');
  let points = pointsInput && !pointsInput.disabled ? parseInt(pointsInput.value || '0', 10) : 0;
  const subtotalAfterVoucher = Math.max(0, BASE_PRICE + (addonTotal ?? getCurrentAddonTotal()) - voucherPreviewDiscount);
  const maxBySubtotal = Math.floor((subtotalAfterVoucher * (MAX_REDEEM_PERCENT / 100)) / Math.max(0.01, POINT_VALUE));
  MAX_REDEEMABLE_POINTS = Math.max(0, Math.min(POINT_BALANCE, maxBySubtotal));
  if (pointsInput) pointsInput.max = MAX_REDEEMABLE_POINTS;
  points = Math.max(0, Math.min(points || 0, MAX_REDEEMABLE_POINTS));
  if (pointsInput && !pointsInput.disabled && String(pointsInput.value || '') !== String(points)) pointsInput.value = points;
  const pointsRow = document.getElementById('pointsPreviewRow');
  const pointsDisplay = document.getElementById('pointsPreviewDisplay');
  if (pointsRow && pointsDisplay) {
    pointsRow.style.display = points > 0 ? 'flex' : 'none';
    const pointDiscount = points * POINT_VALUE;
    pointsDisplay.textContent = '-PHP ' + pointDiscount.toLocaleString('en-PH', {minimumFractionDigits:2});
  }
  const voucherRow = document.getElementById('voucherPreviewRow');
  const voucherDisplay = document.getElementById('voucherPreviewDisplay');
  if (voucherRow && voucherDisplay) {
    voucherRow.style.display = voucherPreviewDiscount > 0 ? 'flex' : 'none';
    voucherDisplay.textContent = '-PHP ' + voucherPreviewDiscount.toLocaleString('en-PH', {minimumFractionDigits:2});
  }
  const total = Math.max(0, BASE_PRICE + (addonTotal ?? getCurrentAddonTotal()) - voucherPreviewDiscount - (points * POINT_VALUE)) + (isDelivery ? deliveryFee : 0);
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
    if (deliveryCoverageBlocked) {
      alert('This pinned location is outside the seller delivery area. Move the pin inside the green coverage area or choose pickup.');
      return false;
    }
  }
  if (!validateSurpriseDelivery()) return false;
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
toggleSurpriseDelivery();
document.querySelectorAll('[data-voucher-code]').forEach(btn => {
  btn.addEventListener('click', () => {
    const input = document.getElementById('voucherCodeInput');
    if (input) {
      input.value = btn.dataset.voucherCode || '';
      voucherPreviewDiscount = Math.max(0, parseFloat(btn.dataset.voucherDiscount || '0') || 0);
      input.focus();
      updateTotal(getCurrentAddonTotal());
    }
  });
});
document.getElementById('voucherCodeInput')?.addEventListener('input', () => {
  voucherPreviewDiscount = 0;
  updateTotal(getCurrentAddonTotal());
});
document.getElementById('pointsToRedeem')?.addEventListener('input', () => updateTotal(getCurrentAddonTotal()));
</script>
@endpush




