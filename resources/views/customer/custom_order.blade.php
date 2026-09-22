@extends('layouts.app')
@section('content')
@php
  $customPrepSettings = app(\App\Services\PreparationWindowService::class)->settings($targetShop->id ?? null);
  $customPrepDays = (int) $customPrepSettings->custom_cake_prep_days;
  $customEarliestDate = app(\App\Services\PreparationWindowService::class)->earliestDate($targetShop->id ?? null, 'custom')->toDateString();
  $customScheduleSlots = app(\App\Services\FulfillmentScheduleService::class)->slotsForCheckout($targetShop->id ?? null, 'custom');
  $customScheduleSettings = app(\App\Services\FulfillmentScheduleService::class)->settings($targetShop->id ?? null);
@endphp
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
          <form action="{{ route('customer.custom_order.store') }}" method="POST" id="customOrderForm" enctype="multipart/form-data" data-prevent-double-submit>
            @csrf
            <input type="hidden" name="shop_slug" value="{{ $targetShop->shop_slug ?? '' }}">
            <input type="hidden" name="submit_action" id="customSubmitAction" value="place_order">

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
                           value="{{ old('quantity',1) }}" onchange="updatePriceSummary();updateCustomScheduleSlots(); checkCustCoAvailability()">
                    <div class="form-text text-muted small">For 11+ pcs or bulk next-month orders, send a seller-reviewed bulk request instead of normal checkout.</div>
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
                      <i class="bi bi-pin-map me-1" style="color:var(--primary)"></i><span id="deliveryPinLabel">Pin Your Delivery Location</span>
                      <span class="text-danger">*</span>
                    </label>
                    <div class="form-text mb-2" id="deliveryPinHelp"><i class="bi bi-info-circle me-1"></i>Tap <strong>Detect My Location</strong> or click the map to pin your exact delivery address.</div>
                    <div id="mapWrapper" style="position:relative">
                      <div id="map" style="height:300px;border-radius:.9rem;border:2px dashed #f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.15)"></div>
                      <div id="mapOverlay" style="position:absolute;top:12px;left:12px;right:64px;display:flex;z-index:999;pointer-events:none">
                        <div style="background:#fff;border:1.5px solid #bbf7d0;border-radius:.85rem;padding:.65rem .8rem;box-shadow:0 4px 18px rgba(0,0,0,.16);max-width:320px;font-size:.72rem;color:#166534;font-weight:700">
                          <i class="bi bi-map me-1"></i>The green coverage area shows where this seller delivers. You can also click the map to pin manually.
                        </div>
                      </div>
                      <button type="button" class="btn btn-primary btn-sm" onclick="detectMyLocation()" id="detectBtn" style="position:absolute;right:12px;bottom:12px;z-index:1000;border-radius:999px;box-shadow:0 6px 18px rgba(0,0,0,.18)">
                        <i class="bi bi-crosshair me-1"></i>Detect My Location
                      </button>
                    </div>
                    <div id="msgMap" class="cv-msg" style="font-size:.74rem;margin-top:4px;min-height:16px"></div>
                    <input type="hidden" name="latitude"  id="lat">
                    <input type="hidden" name="longitude" id="lng">
                    <input type="hidden" name="delivery_zone"  id="deliveryZoneInput" value="">
                    <input type="hidden" name="delivery_fee"   id="deliveryFeeInput"  value="0">
                    <input type="hidden" name="service_charge" value="0">
                  </div>

                  {{-- Coverage status --}}
                  <div id="coverageStatus" style="display:none;margin-top:.65rem;border-radius:.75rem;padding:.65rem .8rem;font-size:.78rem;font-weight:700" class="mb-3 small"></div>

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
                  <div class="mt-3 p-3 rounded-3 bb-surprise-card" style="border:1.5px solid color-mix(in srgb,var(--primary) 22%,#e5e7eb);background:linear-gradient(135deg,#fff 0%,var(--primary-light,#fff5f8) 100%);box-shadow:0 10px 24px rgba(15,23,42,.06)">
                    <div class="form-check form-switch d-flex align-items-center gap-2 mb-2">
                      <input class="form-check-input" type="checkbox" name="is_surprise_delivery" id="surpriseDelivery" value="1" onchange="toggleSurpriseDelivery()">
                      <label class="form-check-label fw-bold" for="surpriseDelivery"><i class="bi bi-gift me-1" style="color:var(--primary)"></i>Send as surprise gift</label>
                    </div>
                    <div class="text-muted small mb-3">Use this when the cake is for another recipient. Payment stays with you and the recipient will not be asked to pay.</div>
                    <div id="surpriseFields" style="display:none">
                      <div class="row g-3">
                        <div class="col-md-6"><label class="form-label fw-semibold small">Recipient Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="recipient_name" id="recipientName" maxlength="120" placeholder="Who will receive the cake?"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold small">Recipient Phone <span class="text-muted fw-normal">(optional)</span></label><input type="tel" class="form-control" name="recipient_phone" id="recipientPhone" maxlength="30" placeholder="09XXXXXXXXX"><div class="form-text">Optional. Rider will use this only if delivery cannot be completed. If blank, sender will be contacted first.</div></div>
                        <div class="col-md-6"><label class="form-label fw-semibold small">Sender Name on Card</label><input type="text" class="form-control" name="sender_display_name" maxlength="120" value="{{ session('user')['fullname'] ?? '' }}" placeholder="Example: Mama, Papa, Your friend"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold small">Contact Rule</label><select class="form-select" name="surprise_contact_policy"><option value="sender_first">Call me first before recipient</option><option value="recipient_if_needed">Call recipient only if needed</option><option value="recipient_ok">Recipient may be called directly</option></select></div>
                        <div class="col-12"><label class="form-label fw-semibold small">Gift Message</label><textarea class="form-control" name="gift_message" rows="2" maxlength="500" placeholder="Optional message for the recipient"></textarea></div>
                        <div class="col-12"><label class="form-label fw-semibold small">Rider / Seller Instructions</label><textarea class="form-control" name="delivery_instructions" rows="2" maxlength="500" placeholder="Example: Please call me first, do not mention the price, hand it to the guard if recipient is not outside."></textarea></div>
                        <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="hide_sender_name" id="hideSenderName" value="1"><label class="form-check-label small" for="hideSenderName">Hide my name from recipient-facing notes</label></div></div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row g-3 mt-1">
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small">Preferred Date</label>
                    <input type="date" class="form-control" name="schedule_date" id="custCoFieldDate"
                           min="{{ $customEarliestDate }}"
                           value="{{ old('schedule_date', $customEarliestDate) }}"
                           onchange="updateCustomScheduleSlots(); checkCustCoAvailability()">
                    <div id="custCoAvailability" class="mt-1" style="font-size:.8rem;min-height:18px"></div>
                    <div class="form-text text-muted small"><i class="bi bi-info-circle me-1"></i>Custom cakes need at least {{ $customPrepDays }} preparation day{{ $customPrepDays === 1 ? '' : 's' }}.</div>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small">Preferred Time</label>
                    <input type="time" class="form-control" name="time_slot" id="customFieldTime" min="{{ substr($customScheduleSettings->shop_open_time ?? '09:00', 0, 5) }}" max="{{ substr($customScheduleSettings->shop_close_time ?? '19:00', 0, 5) }}" onchange="updateCustomScheduleSlots()" oninput="updateCustomScheduleSlots()">
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

            <div class="custom-order-actions d-grid gap-2">
              <button type="button" class="btn btn-outline-primary w-100 py-3 fw-semibold fs-6" id="customAddToCartBtn" onclick="return submitCustomCakeToCart(this)">
                <i class="bi bi-cart-plus me-2"></i>Add Custom Cake to Cart
              </button>
              <div class="small text-muted rounded-3 px-3 py-2" id="surpriseDirectOrderNote" style="display:none;background:#fff7ed;color:#9a3412;border:1px solid #fed7aa"><i class="bi bi-info-circle me-1"></i>Surprise delivery must be placed directly so recipient details stay with this order.</div>
              <button type="button" class="btn btn-primary w-100 py-3 fw-semibold fs-5" onclick="return confirmCustomOrder(this)">
                <i class="bi bi-palette me-2"></i>Place Custom Order
              </button>
            </div>
          </form>
        </div>

        {{-- RIGHT: Price Summary --}}
        <div class="col-lg-4">
          <div class="card bb-sticky-order-summary">
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
              <div class="d-flex justify-content-between fw-bold align-items-start">
                <div>
                  <span>Total</span>
                  <div class="text-muted fw-normal mt-1" style="font-size:.82rem">(Pricing is subject to change. We will notify you immediately of any updates.)</div>
                </div>
                <span id="totalDisplay" style="color:var(--primary);font-size:1.1rem">₱1,200.00</span>
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
<script src="https://cdn.jsdelivr.net/npm/@turf/turf@6/turf.min.js"></script>
<script>
function customMinutesOf(time) {
  const parts = String(time || '').split(':').map(Number);
  return ((parts[0] || 0) * 60) + (parts[1] || 0);
}
function customFormatTime(totalMinutes) {
  totalMinutes = Math.max(0, Math.min(1439, Math.ceil(totalMinutes)));
  const h = Math.floor(totalMinutes / 60);
  const m = totalMinutes % 60;
  const suffix = h >= 12 ? 'PM' : 'AM';
  const hh = ((h + 11) % 12) + 1;
  return `${hh}:${String(m).padStart(2, '0')} ${suffix}`;
}
function updateCustomScheduleSlots() {
  const timeEl = document.getElementById('customFieldTime') || document.querySelector('[name="time_slot"]');
  if (!timeEl) return true;
  const open = @json(substr($customScheduleSettings->shop_open_time ?? '09:00', 0, 5));
  const close = @json(substr($customScheduleSettings->shop_close_time ?? '19:00', 0, 5));
  const openMins = customMinutesOf(open);
  const closeMins = customMinutesOf(close);
  const selectedMins = customMinutesOf(timeEl.value);
  timeEl.min = open;
  timeEl.max = close;
  timeEl.setCustomValidity('');
  if (timeEl.value && (selectedMins < openMins || selectedMins > closeMins)) {
    timeEl.setCustomValidity(`Please choose a time within shop hours: ${customFormatTime(openMins)} to ${customFormatTime(closeMins)}.`);
    return false;
  }
  return true;
}
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
  coverageRadius: {{ (int)($shopSettings->delivery_coverage_radius ?? 5000) }},
};
const COVERAGE_ZONES  = @json($deliveryZones->values());
const COVERAGE_RADIUS = Math.max(1000, SHOP_META.coverageRadius || 5000);
let deliveryFee = 0;
let map, marker, routeLine;
let deliveryCoverageBlocked = false;

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

// ── On pin set ────────────────────────────────────────
function updateCustomMapPinState(ok, message, warning = false) {
  const mapEl = document.getElementById('map');
  const msg = document.getElementById('msgMap');
  const overlay = document.getElementById('mapOverlay');
  if (overlay && ok) overlay.style.display = 'none';
  if (!mapEl) return;
  if (!ok) {
    mapEl.style.border = '2px dashed #f59e0b';
    mapEl.style.boxShadow = '0 0 0 3px rgba(245,158,11,.15)';
    if (msg) { msg.className = 'cv-msg text-warning'; msg.textContent = message || 'Please pin your exact delivery location.'; }
    return;
  }
  mapEl.style.border = warning ? '2px solid #f97316' : '2px solid #16a34a';
  mapEl.style.boxShadow = warning ? '0 0 0 3px rgba(249,115,22,.2)' : '0 0 0 3px rgba(22,163,74,.15)';
  if (msg) {
    msg.className = warning ? 'cv-msg text-warning' : 'cv-msg text-success';
    msg.textContent = message || (warning ? 'Pinned location needs review.' : 'Location pinned.');
  }
}
function onPinSet(lat, lng) {
  document.getElementById('lat').value = lat;
  document.getElementById('lng').value = lng;

  // Coverage check
  const covered  = isInCoverage(lat, lng);
  const statusEl = document.getElementById('coverageStatus');
  if (covered === null) {
    deliveryCoverageBlocked = false;
    statusEl.style.display = 'none';
  } else if (covered) {
    deliveryCoverageBlocked = false;
    const nearest = nearestCoverageZone(lat, lng);
    const zoneName = nearest?.zone?.barangay || 'this seller';
    statusEl.style.cssText = 'display:block;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;border-radius:.5rem;padding:.5rem .75rem';
    statusEl.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Inside delivery coverage: ' + zoneName + '.';
    updateCustomMapPinState(true, 'Location pinned inside delivery coverage.');
  } else {
    deliveryCoverageBlocked = true;
    const nearest = nearestCoverageZone(lat, lng);
    const nearestText = nearest ? ' Nearest coverage: ' + (nearest.zone.barangay || 'coverage area') + ', ' + formatCoverageDistance(nearest.distance) + ' away.' : '';
    statusEl.style.cssText = 'display:block;background:#fff1f2;color:#9f1239;border:1px solid #fecdd3;border-radius:.5rem;padding:.5rem .75rem';
    statusEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>Outside this seller delivery area.' + nearestText + ' Move the pin or choose pickup.';
    updateCustomMapPinState(true, 'Pinned location is outside the seller delivery area.', true);
    deliveryFee = 0;
    document.getElementById('deliveryFeeInput').value = 0;
    document.getElementById('deliveryCalcBox').style.display = 'none';
    const feeRow = document.getElementById('feeRow');
    if (feeRow) feeRow.style.display = 'none';
    updatePriceSummary();
    reverseGeocode(lat, lng);
    return;
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

  const addonSummary = document.getElementById('addonSummary');
  if (addonSummary) {
    addonSummary.innerHTML = addonDetails.map(d =>
      '<div class="d-flex justify-content-between small mb-1 text-muted">'
      + '<span><i class="bi bi-check2 me-1" style="color:var(--primary)"></i>' + d.name + '</span>'
      + '<span>' + (d.price > 0 ? '+₱'+d.price.toFixed(2) : 'FREE') + '</span></div>'
    ).join('');
  }


  const qtyRow = document.getElementById('qtyRow');
  if (qtyRow) qtyRow.style.display = qty > 1 ? 'flex' : 'none';
  const qtyDisplay = document.getElementById('qtyDisplay');
  if (qtyDisplay) qtyDisplay.textContent = qty;

  const totalDisplay = document.getElementById('totalDisplay');
  if (totalDisplay) {
    totalDisplay.textContent = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits:2});
  }
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
  toggleSurpriseDelivery();
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
  const label = document.getElementById('deliveryPinLabel');
  const help = document.getElementById('deliveryPinHelp');
  if (label) label.textContent = enabled ? 'Pin Recipient Delivery Location' : 'Pin Your Delivery Location';
  if (help) help.innerHTML = enabled
    ? '<i class="bi bi-info-circle me-1"></i>Pin the recipient exact address. This is the location the rider will use for the surprise delivery.'
    : '<i class="bi bi-info-circle me-1"></i>Tap <strong>Detect My Location</strong> or click the map to pin your exact delivery address.';
  const cod = document.getElementById('cod');
  const gcash = document.getElementById('gcash');
  if (cod) cod.disabled = enabled;
  if (enabled && gcash) gcash.checked = true;
  const addToCartBtn = document.getElementById('customAddToCartBtn');
  const directOrderNote = document.getElementById('surpriseDirectOrderNote');
  if (addToCartBtn) addToCartBtn.style.display = enabled ? 'none' : '';
  if (directOrderNote) directOrderNote.style.display = enabled ? 'block' : 'none';
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
      html:'<div style="background:#22c55e;width:10px;height:10px;border-radius:50%;opacity:.85;border:2px solid #15803d"></div>',
      className:'', iconSize:[10,10], iconAnchor:[5,5]
    });
    L.marker([zLat, zLng], {icon:cIcon, interactive:false}).addTo(map).bindTooltip(z.barangay||'Coverage Area');
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

  drawCoverageAreas();

  @if($defaultAddr && ($defaultAddr->latitude ?? null) && ($defaultAddr->longitude ?? null))
    setMarkerAt(L.latLng({{ $defaultAddr->latitude }}, {{ $defaultAddr->longitude }}), true);
    map.setView([{{ $defaultAddr->latitude }}, {{ $defaultAddr->longitude }}], 15);
  @endif

  map.on('click', e => setMarkerAt(e.latlng, true));
}

function detectMyLocation() {
  if (!map) initMap();
  if (!window.berryBaseHasLocationSupport?.()) { alert('Geolocation is not supported by your browser.'); return; }
  const btn = document.getElementById('detectBtn');
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Detecting…';
  window.berryBaseGetCurrentPosition(
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

function customSetSubmitAction(action) {
  const input = document.getElementById('customSubmitAction');
  if (input) input.value = action;
}

function submitCustomCakeToCart(btn) {
  customSetSubmitAction('add_to_cart');
  const form = document.getElementById('customOrderForm');
  const requiredFields = Array.from(form.querySelectorAll('[name="cake_name"],[name="flavor"],[name="size"],[name="schedule_date"]'));
  for (const field of requiredFields) {
    if (!field.value) { field.reportValidity(); return false; }
  }
  const isDelivery = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';
  if (isDelivery) {
    const lat = document.getElementById('lat')?.value;
    const addr = document.getElementById('addressField')?.value?.trim();
    if (!lat || !addr) { alert('Please pin your location on the map and enter your address.'); return false; }
    if (deliveryCoverageBlocked) { alert('This pinned location is outside the seller delivery area. Move the pin inside the green coverage area or choose pickup.'); return false; }
  }
  if (isSurpriseDeliverySelected()) { alert('Surprise delivery is not saved when adding a custom cake to cart. Please place the custom order directly or turn off surprise delivery.'); return false; }
  if (custCoAvailabilityPending) { alert('Please wait for the custom cake availability check to finish.'); return false; }
  if (custCoAvailabilityIssue) { alert(custCoAvailabilityIssue); return false; }
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Adding to cart...';
  form.submit();
  return false;
}
function confirmCustomOrder(btn) {
  const isDelivery = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';
  if (isDelivery) {
    const lat  = document.getElementById('lat')?.value;
    const addr = document.getElementById('addressField')?.value?.trim();
    if (!lat || !addr) { alert('Please pin your location on the map and enter your address.'); return false; }
    if (deliveryCoverageBlocked) { alert('This pinned location is outside the seller delivery area. Move the pin inside the green coverage area or choose pickup.'); return false; }
  }
  if (!validateSurpriseDelivery()) return false;
  if (custCoAvailabilityPending) { alert('Please wait for the custom cake availability check to finish.'); return false; }
  if (custCoAvailabilityIssue) { alert(custCoAvailabilityIssue); return false; }
  const total = document.getElementById('totalDisplay').textContent;
  cakeConfirm({
    title: '📋 Confirm Custom Order?',
    message: 'Estimated Total: ' + total + '\n\nNote: This is a base estimate only. The final price will be confirmed by our baker after reviewing your design reference.',
    icon: 'bi-cake2', okLabel: 'Place Order',
    onConfirm: () => {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Placing Order…';
      csSubmitOnce(document.getElementById('customOrderForm'), btn, '<span class="spinner-border spinner-border-sm me-2"></span>Placing Order...');
    }
  });
  return false;
}

document.addEventListener('DOMContentLoaded', () => {
  updatePaymentMethodLabel();
  toggleSurpriseDelivery();
  updatePriceSummary();
  updateCustomScheduleSlots(); checkCustCoAvailability();

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

let custCoAvailabilityIssue = '';
let custCoAvailabilityPending = false;

function customCapacityText(data, fallback) {
  const max = parseInt(data.max ?? 0, 10);
  const remaining = data.remaining;
  if (typeof remaining === 'number' && max > 0) {
    const label = remaining === 1 ? 'custom cake slot' : 'custom cake slots';
    return `${remaining} of ${max} ${label} available.`;
  }
  return fallback || data.message || 'Available.';
}

function checkCustCoAvailability() {
  const dateEl = document.getElementById('custCoFieldDate');
  if (dateEl && !dateEl.value && dateEl.min) dateEl.value = dateEl.min;
  const date   = dateEl?.value;
  const shopId = '{{ $targetShop->id ?? '' }}';
  const qty    = parseInt(document.querySelector('[name=quantity]')?.value || '1', 10);
  const el     = document.getElementById('custCoAvailability');
  custCoAvailabilityIssue = '';
  custCoAvailabilityPending = false;
  if (!date || !el) return;
  custCoAvailabilityPending = true;
  el.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split me-1"></i>Checking custom cake availability...</span>';
  const url = '/catalog/availability?date=' + encodeURIComponent(date) + (shopId ? '&shop_id=' + encodeURIComponent(shopId) : '');
  fetch(url)
    .then(r => r.json())
    .then(data => {
      custCoAvailabilityPending = false;
      if (data.status === 'capacity_not_configured') {
        custCoAvailabilityIssue = data.message || 'This shop has not set custom cake daily capacity yet.';
        el.innerHTML = '<span class="text-danger fw-semibold"><i class="bi bi-exclamation-triangle-fill me-1"></i>' + custCoAvailabilityIssue + '</span>';
      } else if (data.status === 'invalid') {
        custCoAvailabilityIssue = data.message || 'Date not available.';
        el.innerHTML = '<span class="text-danger fw-semibold"><i class="bi bi-x-circle-fill me-1"></i>' + custCoAvailabilityIssue + '</span>';
      } else if (data.status === 'full') {
        custCoAvailabilityIssue = (data.message || 'This date is fully booked.') + ' Please choose another date.';
        el.innerHTML = '<span class="text-danger fw-semibold"><i class="bi bi-x-circle-fill me-1"></i>' + custCoAvailabilityIssue + '</span>';
      } else if (typeof data.remaining === 'number' && qty > data.remaining) {
        custCoAvailabilityIssue = 'Only ' + data.remaining + ' custom cake slot' + (data.remaining !== 1 ? 's' : '') + ' available on this date. Please choose another date or reduce quantity.';
        el.innerHTML = '<span class="text-danger fw-semibold"><i class="bi bi-x-circle-fill me-1"></i>' + custCoAvailabilityIssue + '</span>';
      } else if (data.status === 'almost') {
        custCoAvailabilityIssue = '';
        el.innerHTML = '<span class="text-warning fw-semibold"><i class="bi bi-exclamation-triangle-fill me-1"></i>' + customCapacityText(data, data.message) + '</span>';
      } else if (data.status === 'available') {
        custCoAvailabilityIssue = '';
        el.innerHTML = '<span class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>' + customCapacityText(data, data.message) + '</span>';
      } else {
        custCoAvailabilityIssue = '';
        el.innerHTML = '';
      }
    })
    .catch(() => {
      custCoAvailabilityPending = false;
      custCoAvailabilityIssue = 'We could not check custom cake availability. Please try again.';
      el.innerHTML = '<span class="text-danger fw-semibold"><i class="bi bi-exclamation-triangle-fill me-1"></i>' + custCoAvailabilityIssue + '</span>';
    });
}
</script>
@endpush




