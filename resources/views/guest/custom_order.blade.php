@extends('layouts.app')
@section('content')
<div class="container-fluid py-4" style="padding-left:clamp(12px,3vw,32px);padding-right:clamp(12px,3vw,32px)">

      <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('catalog') }}" class="btn btn-outline-secondary btn-sm">
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
          <form action="{{ route('guest.custom_order.store') }}" method="POST" id="customOrderForm" enctype="multipart/form-data" data-prevent-double-submit>
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
                  <input type="text" class="form-control cv-field" name="cake_name" id="fieldCakeName"
                         value="{{ old('cake_name') }}"
                         placeholder="e.g. Birthday Cake for Maria, Wedding Cake"
                         required maxlength="120"
                         oninput="cvClearErr(this,'msgCakeName')">
                  <div class="cv-msg" id="msgCakeName"></div>
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
                    <div class="text-muted mt-1" style="font-size:clamp(.7rem,1.4vw,.75rem)">
                      <i class="bi bi-info-circle me-1"></i>Upload photos of the cake design you want — from Pinterest, other bakeries, or your own ideas.
                    </div>
                  </div>
                </div>
                <div class="row g-3">
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small">Flavor <span class="text-danger">*</span></label>
                    <select class="form-select cv-field" name="flavor" id="fieldFlavor" required
                            onchange="cvClearErr(this,'msgFlavor')">
                      <option value="">-- Select Flavor --</option>
                      @foreach($flavors as $f)
                        <option value="{{ $f->label }}" {{ old('flavor')==$f->label ? 'selected':'' }}>
                          {{ $f->label }}{{ $f->description ? ' — '.$f->description : '' }}
                        </option>
                      @endforeach
                    </select>
                    <div class="cv-msg" id="msgFlavor"></div>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small">Quantity</label>
                    <input type="number" class="form-control" name="quantity" min="1" max="10"
                           value="{{ old('quantity',1) }}" onchange="updatePriceSummary();checkCoGuestAvailability()">
                  </div>
                </div>
              </div>
            </div>

            {{-- 2. Size & Layers --}}
            <div class="card mb-3">
              <div class="card-body p-4">
                <h6 class="fw-bold mb-3">
                  <i class="bi bi-rulers me-2" style="color:var(--primary)"></i>Size &amp; Layers
                  <span class="badge ms-1" style="background:#fff0f5;color:var(--primary);font-size:clamp(.66rem,1.3vw,.7rem)">Affects price</span>
                </h6>
                <div class="row g-3">
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small">Diameter <span class="text-danger">*</span></label>
                    <select class="form-select cv-field" name="size" id="fieldSize" required
                            onchange="updatePriceSummary();cvClearErr(this,'msgSize')">
                      <option value="">-- Select Size --</option>
                      @foreach($sizes as $s)
                        <option value="{{ $s->label }}" {{ old('size')==$s->label ? 'selected':'' }}>
                          {{ $s->label }}{{ $s->price > 0 ? ' (+₱'.number_format($s->price,2).')' : '' }}
                          {{ $s->description ? ' — '.$s->description : '' }}
                        </option>
                      @endforeach
                    </select>
                    <div class="cv-msg" id="msgSize"></div>
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

            {{-- 4. Additional Instructions --}}
            <div class="card mb-3">
              <div class="card-body p-4">
                <h6 class="fw-bold mb-3">
                  <i class="bi bi-chat-left-text me-2" style="color:var(--primary)"></i>Special Instructions
                </h6>
                <div>
                  <label class="form-label fw-semibold small">Additional Instructions <span class="text-muted fw-normal">(optional)</span></label>
                  <textarea class="form-control" name="custom_note" rows="3"
                            placeholder="Color scheme, theme references, allergies, special design requests, etc.">{{ old('custom_note') }}</textarea>
                  <div class="form-text"><i class="bi bi-info-circle me-1"></i>Tell us anything special about your cake — color scheme, theme, allergies, or design preferences.</div>
                </div>
              </div>
            </div>

            {{-- 5. Add-ons --}}
            @if($addonCategories->count() > 0)
            <div class="card mb-3">
              <div class="card-body p-4">
                <h6 class="fw-bold mb-1">
                  <i class="bi bi-stars me-2" style="color:var(--primary)"></i>Add-ons &amp; Extras
                  <span class="badge ms-2" style="background:var(--primary-light);color:var(--primary);font-size:clamp(.68rem,1.3vw,.72rem)">Optional</span>
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
                            <div class="text-muted" style="font-size:clamp(.68rem,1.3vw,.72rem)">{{ $addon->description }}</div>
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
                    <div class="text-muted" style="font-size:clamp(.68rem,1.3vw,.72rem)">Pick up your order at our shop — no delivery fee.</div>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="fulfillment_type" value="Delivery"
                           id="delivery" onchange="toggleDelivery()">
                    <label class="form-check-label fw-semibold" for="delivery"><i class="bi bi-bicycle me-1"></i>Delivery</label>
                    <div class="text-muted" style="font-size:clamp(.68rem,1.3vw,.72rem)">Your cake will be delivered to your address.</div>
                  </div>
                </div>

                <div id="deliverySection" style="display:none">
                  {{-- Hidden zone select — used by updateFee() / autoSelectBarangayFromCoords() --}}
                  @php
                    $zoneTypeLabels = ['free'=>'Poblacion','near'=>'Nearby','mid'=>'Mid-range','far'=>'Far','ooc'=>'Out of Coverage'];
                    $zoneTypeColors = ['free'=>'#10b981','near'=>'#0ea5e9','mid'=>'#f59e0b','far'=>'#f97316','ooc'=>'#e11d48'];
                    $grouped = $deliveryZones->groupBy('zone_type');
                    $order   = ['free','near','mid','far','ooc'];
                  @endphp
                  <select id="zoneSelect" name="delivery_zone" style="display:none" onchange="updateFee();cvValidateZone()">
                    <option value=""></option>
                    @foreach($order as $typeKey)
                      @php $group = $grouped[$typeKey] ?? collect(); @endphp
                      @foreach($group as $z)
                      <option value="{{ $z->barangay }}"
                              data-fee="{{ $z->delivery_fee ?? $z->fee ?? 0 }}"
                              data-lat="{{ $z->lat ?? '' }}"
                              data-lng="{{ $z->lng ?? '' }}"
                              data-type="{{ $z->zone_type }}"
                              data-eta="{{ $z->estimated_time ?? '30-45 mins' }}"
                              data-label="{{ $zoneTypeLabels[$z->zone_type] ?? $z->zone_type }}"
                              data-color="{{ $zoneTypeColors[$z->zone_type] ?? '#888' }}">{{ $z->barangay }}</option>
                      @endforeach
                    @endforeach
                  </select>

                  <div class="mb-3">
                    <label class="form-label fw-semibold small">Pin Your Location</label>
                    <div class="form-text mb-1"><i class="bi bi-info-circle me-1"></i>Click the map or use "Find My Location" to pin your exact delivery address — delivery fee and ETA update automatically.</div>
                    <button type="button" id="useMyLocationBtn" onclick="useMyLocation()"
                            class="btn btn-outline-primary btn-sm mb-2 w-100" style="border-radius:.7rem">
                      <i class="bi bi-geo-alt-fill me-1"></i>📍 Find My Location
                    </button>
                    <div id="mapWrapper" style="position:relative">
                      <div id="map" style="height:260px;border-radius:.9rem;border:2px dashed #f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.15)"></div>
                      <div style="position:absolute;top:12px;left:12px;right:12px;z-index:999;pointer-events:none">
                        <div style="background:#fff;border:1.5px solid #bbf7d0;border-radius:.85rem;padding:.65rem .8rem;box-shadow:0 4px 18px rgba(0,0,0,.16);max-width:300px;font-size:.72rem;color:#166534;font-weight:700">
                          <i class="bi bi-map me-1"></i>The green coverage area shows where this seller delivers.
                        </div>
                      </div>
                    </div>
                    <div id="coverageStatus" style="display:none;margin-top:.65rem;border-radius:.75rem;padding:.65rem .8rem;font-size:.78rem;font-weight:700"></div>
                    <div class="cv-msg" id="msgMap"></div>
                    <input type="hidden" name="latitude"  id="lat">
                    <input type="hidden" name="longitude" id="lng">
                  </div>

                  {{-- ── Auto-detected Delivery Zone Card ──────────────────── --}}
                  <div id="feePreview" style="display:none;margin-top:.75rem;border-radius:.85rem;padding:.9rem 1.1rem;background:#f0fdf4;border:1.5px solid #d1fae5;transition:background .3s">
                    <div class="d-flex align-items-start gap-2">
                      <i class="bi bi-geo-alt-fill mt-1" style="font-size:.95rem;flex-shrink:0;color:#059669"></i>
                      <div style="flex:1;min-width:0">
                        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-2">
                          <span class="fw-semibold small" style="color:#064e3b">Delivery Zone Detected</span>
                          <span id="zoneBadge"></span>
                        </div>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                          <span class="small fw-semibold">
                            <i class="bi bi-bicycle me-1"></i>Fee:&nbsp;<span id="feePreviewAmount" style="color:#166534">₱0.00</span>
                          </span>
                          <div id="etaDisplay" style="display:none">
                            <span class="small" style="color:#0369a1">
                              <i class="bi bi-clock me-1"></i>ETA:&nbsp;<strong id="etaText"></strong>
                            </span>
                          </div>
                        </div>
                        <div class="small text-muted mt-1" style="font-size:clamp(.64rem,1.2vw,.68rem)">
                          <i class="bi bi-exclamation-circle me-1"></i>ETA is approximate travel time from our shop — not cake preparation time.
                        </div>
                      </div>
                    </div>
                    <div id="oocNotice" style="display:none" class="alert border-0 mt-2 py-2 mb-0">
                      <i class="bi bi-info-circle me-1" style="color:#e11d48"></i>
                      <span class="small" style="color:#9f1239">
                        <strong>Out of Coverage</strong> — Delivery fee starts at ₱250. Admin will confirm after reviewing your location.
                      </span>
                    </div>
                  </div>
                  <input type="hidden" name="delivery_fee" id="deliveryFeeInput" value="0">
                  <input type="hidden" name="service_charge" id="serviceChargeInput" value="0">
                  <div class="cv-msg" id="msgZone"></div>

                  <div class="mb-3">
                    <label class="form-label fw-semibold small">
                      Full Address
                      <span id="addressLoading" style="display:none;font-size:clamp(.7rem,1.4vw,.75rem);color:var(--primary);font-weight:400">
                        <span class="spinner-border spinner-border-sm me-1" style="width:.7rem;height:.7rem"></span>
                        Fetching address…
                      </span>
                    </label>
                    <textarea class="form-control" name="address" id="addressField" rows="2"
                      placeholder="Click the map to pin your location and auto-fill address"></textarea>
                    <div class="form-text"><i class="bi bi-pin-map me-1"></i>Add your house number, street name, or a landmark for easier delivery (e.g. House #12, Rizal St., near the blue gate).</div>
                  </div>
                </div>

                <div class="row g-3 mt-1">
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small">Preferred Date</label>
                    <input type="date" class="form-control cv-field" name="schedule_date" id="fieldDate"
                           min="{{ date('Y-m-d', strtotime('+1 day')) }}"
                           onchange="cvValidateDate(this);checkCoGuestAvailability()"
                           oninput="cvValidateDate(this)">
                    <div class="cv-msg" id="msgDate"></div>
                    <div id="coGuestAvailability" class="mt-1" style="font-size:.8rem;min-height:18px"></div>
                    <div class="form-text"><i class="bi bi-info-circle me-1"></i>Choose your preferred delivery or pickup date. Custom cakes need at least 2–3 days for preparation.</div>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small">Preferred Time Slot</label>
                    <select class="form-select" name="time_slot">
                      <option value="">-- Select Time Slot --</option>
                      @foreach($timeSlots as $slot)
                        <option value="{{ $slot->label }}">{{ $slot->label }}</option>
                      @endforeach
                    </select>
                    <div class="form-text"><i class="bi bi-info-circle me-1"></i>Choose your preferred time slot. Please make sure someone is available to receive the order.</div>
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
                    <input class="form-check-input" type="radio" name="payment_method" value="COD" id="cod" checked onchange="updateCustomPaymentTransparency()">
                    <label class="form-check-label fw-semibold" for="cod">
                      <i class="bi bi-cash-coin me-1"></i><span id="codLabelText">Cash on Pickup (COP)</span>
                    </label>
                    <div class="text-muted" id="codHelpText" style="font-size:clamp(.68rem,1.3vw,.72rem)">Pay cash when you pick up your order.</div>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="payment_method" value="GCash" id="gcash" onchange="updateCustomPaymentTransparency()">
                    <label class="form-check-label fw-semibold" for="gcash">
                      <i class="bi bi-phone me-1"></i>GCash
                      @php $pmMode = \App\Helpers\CakeshopHelper::getPaymongoMode(); @endphp
                      @if($pmMode === 'test')
                        <span class="badge ms-1" style="background:#fef9c3;color:#854d0e;font-size:clamp(.62rem,1.2vw,.65rem)">TEST MODE</span>
                      @else
                        <span class="badge ms-1" style="background:#d1fae5;color:#065f46;font-size:clamp(.62rem,1.2vw,.65rem)">LIVE</span>
                      @endif
                    </label>
                    <div class="text-muted" style="font-size:clamp(.68rem,1.3vw,.72rem)">Pay online via GCash — you'll receive a payment link once your order is out for delivery or ready for pickup.</div>
                  </div>
                </div>
                <div id="paymentTransparencyBox" class="mt-3 p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0">
                  <div class="small fw-semibold mb-1" id="paymentTransparencyTitle" style="color:#0f172a">
                    <i class="bi bi-info-circle me-1"></i>Cash on Pickup selected
                  </div>
                  <div class="small text-muted mb-1" id="paymentTransparencyText">GCash down payment is required first. The remaining balance will be paid in cash on pickup.</div>
                  <div class="small" id="paymentTransparencyBreakdown" style="color:#475569"></div>
                </div>
              </div>
            </div>

            {{-- Guest Verification --}}
            <div class="card mb-3" id="verifyCard">
              <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                  <h6 class="fw-bold mb-0">
                    <i class="bi bi-shield-lock me-2" style="color:var(--primary)"></i>Verify Your Identity
                  </h6>
                  <span id="otpStatusBadge" class="badge" style="background:#fef9c3;color:#854d0e;font-size:.72rem;padding:.3rem .75rem">
                    <i class="bi bi-exclamation-circle me-1"></i>Verification required before placing order
                  </span>
                </div>
                <div class="row g-3">
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small">Your Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control cv-field" name="guest_name" id="fieldName"
                           placeholder="Full name" required maxlength="120"
                           data-cv-rule="name"
                           oninput="cvValidate(this)" onkeypress="cvBlockChar(event,'name')">
                    <div class="cv-msg" id="msgName"></div>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small">Phone Number <span class="text-danger">*</span></label>
                    <div class="input-group">
                      <input type="text" class="form-control cv-field" id="coGuestPhone" name="phone"
                             placeholder="09XXXXXXXXX" maxlength="13"
                             data-cv-rule="phone"
                             oninput="cvValidate(this)" onkeypress="cvBlockChar(event,'phone')">
                      <button type="button" class="btn btn-primary" id="coSendOtpBtn" onclick="sendCoGuestOtp()">
                        <i class="bi bi-phone-fill me-1"></i>Send OTP
                      </button>
                    </div>
                    <div class="cv-msg" id="msgPhone"></div>
                    <div class="form-text"><i class="bi bi-info-circle me-1"></i>We'll send a 6-digit code to verify your number.</div>
                  </div>
                </div>
                <div class="mt-3" id="coOtpSection" style="display:none">
                  <div class="p-3 rounded-3" style="background:#f0fdf4;border:1.5px solid #bbf7d0">
                    <div class="fw-semibold small mb-2" style="color:#15803d">
                      <i class="bi bi-check-circle-fill me-1"></i>OTP sent — check your SMS
                    </div>
                    <label class="form-label fw-semibold small">Enter 6-digit OTP <span class="text-danger">*</span></label>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                      <input type="text" class="form-control cv-field" name="otp_code" id="fieldOtp"
                             placeholder="000000" maxlength="6"
                             style="letter-spacing:.3em;font-size:clamp(.95rem,2.5vw,1.2rem);max-width:160px"
                             oninput="cvValidateOtp(this)" onkeypress="cvBlockChar(event,'otp')">
                    </div>
                    <div class="cv-msg" id="msgOtp"></div>
                    <div id="coDevOtpHint"></div>
                    <div class="form-text mt-1"><i class="bi bi-clock me-1"></i>Valid for 10 minutes.</div>
                  </div>
                </div>
              </div>
            </div>
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
              <div class="d-flex justify-content-between fw-bold align-items-start">
                <div>
                  <span>Total</span>
                  <div class="text-muted fw-normal mt-1" style="font-size:clamp(.78rem,1.6vw,.82rem)">(Pricing is subject to change. We will notify you immediately of any updates.)</div>
                </div>
                <span id="totalDisplay" style="color:var(--primary);font-size:clamp(.9rem,2.2vw,1.1rem)">₱1,200.00</span>
              </div>
              @if(!empty($customer))
              <div class="mt-3 pt-3 border-top">
                <div class="text-muted small fw-semibold mb-2">Your Info</div>
                <div class="small">
                  <div><i class="bi bi-person me-1"></i>{{ $customer->fullname }}</div>
                  <div><i class="bi bi-phone me-1"></i>{{ $customer->phone }}</div>
                  <div><i class="bi bi-envelope me-1"></i>{{ $customer->email }}</div>
                </div>
              </div>
              @endif
            </div>
          </div>
        </div>
      </div>
</div>
@endsection
@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
.cv-field { transition: border-color .2s, box-shadow .2s; }
.cv-field.cv-valid   { border-color:#16a34a !important; box-shadow:0 0 0 3px rgba(22,163,74,.15) !important; }
.cv-field.cv-invalid { border-color:#ef4444 !important; box-shadow:0 0 0 3px rgba(239,68,68,.15) !important; }
@keyframes cvShake {
  0%,100%{transform:translateX(0)} 20%{transform:translateX(-5px)}
  40%{transform:translateX(5px)}  60%{transform:translateX(-3px)} 80%{transform:translateX(3px)}
}
.cv-shake { animation: cvShake .35s ease; }
.cv-msg   { font-size:.74rem; margin-top:4px; min-height:16px; }
.cv-msg.cv-ok  { color:#16a34a; }
.cv-msg.cv-err { color:#ef4444; }
</style>
@endpush
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@turf/turf@6/turf.min.js"></script>
<script>
// ── Price calculation ─────────────────────────────────────────────────
var SIZE_PRICES = {
  @foreach($sizes as $s)
    {!! json_encode($s->label) !!}: {{ (float)$s->price }},
  @endforeach
};
var LAYER_PRICES = {
  @foreach($layers as $l)
    {!! json_encode($l->label) !!}: {{ (float)$l->price }},
  @endforeach
};
var BASE_CUSTOM = 1200;
var deliveryFee = 0;
var map, marker;
var COVERAGE_RADIUS = Math.max(1000, {{ (int)($shopSettings->delivery_coverage_radius ?? 5000) }});
var deliveryCoverageBlocked = false;

function haversine(lat1, lon1, lat2, lon2) {
  var R = 6371000;
  var dLat = (lat2 - lat1) * Math.PI / 180;
  var dLon = (lon2 - lon1) * Math.PI / 180;
  var a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)**2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

function formatCoverageDistance(distance) {
  return distance < 1000 ? Math.round(distance) + ' m' : (distance / 1000).toFixed(2) + ' km';
}

function findClosestZoneOption(lat, lng) {
  var sel = document.getElementById('zoneSelect');
  if (!sel) return null;

  var closest = null;
  for (var i = 0; i < sel.options.length; i++) {
    var opt = sel.options[i];
    var zLat = parseFloat(opt.dataset.lat || '');
    var zLng = parseFloat(opt.dataset.lng || '');
    if (!opt.value || !Number.isFinite(zLat) || !Number.isFinite(zLng)) continue;

    var distance = haversine(lat, lng, zLat, zLng);
    if (!closest || distance < closest.distance) {
      closest = { index: i, option: opt, distance: distance };
    }
  }
  return closest;
}

function updateCoverageStatus(lat, lng, matchedOption) {
  var statusEl = document.getElementById('coverageStatus');
  if (!statusEl) return;

  var closest = findClosestZoneOption(lat, lng);
  if (!closest && !matchedOption) {
    deliveryCoverageBlocked = false;
    statusEl.style.display = 'none';
    return;
  }

  var hasPinnedCoverage = !!closest;
  var covered = hasPinnedCoverage ? closest.distance <= COVERAGE_RADIUS : !!matchedOption;
  var zoneName = (covered && matchedOption ? matchedOption : (closest ? closest.option : null))?.value || 'coverage area';

  if (covered) {
    deliveryCoverageBlocked = false;
    statusEl.style.cssText = 'display:block;margin-top:.65rem;border-radius:.75rem;padding:.65rem .8rem;font-size:.78rem;font-weight:700;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0';
    statusEl.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Inside delivery coverage: ' + zoneName + '.';
  } else {
    deliveryCoverageBlocked = true;
    statusEl.style.cssText = 'display:block;margin-top:.65rem;border-radius:.75rem;padding:.65rem .8rem;font-size:.78rem;font-weight:700;background:#fff7ed;color:#9a3412;border:1px solid #fed7aa';
    statusEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>Outside this seller&apos;s delivery area. Nearest coverage: ' + zoneName + ', ' + formatCoverageDistance(closest.distance) + ' away. Move the pin or choose pickup.';
    deliveryFee = 0;
    var feeInput = document.getElementById('deliveryFeeInput');
    if (feeInput) feeInput.value = 0;
    var feePreview = document.getElementById('feePreview');
    if (feePreview) feePreview.style.display = 'none';
    var feeRow = document.getElementById('feeRow');
    if (feeRow) feeRow.style.display = 'none';
    updatePriceSummary();
  }
}

function findNearestZoneOption(lat, lng) {
  var sel = document.getElementById('zoneSelect');
  if (!sel) return null;

  var nearest = null;
  for (var i = 0; i < sel.options.length; i++) {
    var opt = sel.options[i];
    var zLat = parseFloat(opt.dataset.lat || '');
    var zLng = parseFloat(opt.dataset.lng || '');
    if (!opt.value || !Number.isFinite(zLat) || !Number.isFinite(zLng)) continue;

    var distance = haversine(lat, lng, zLat, zLng);
    if (distance <= COVERAGE_RADIUS && (!nearest || distance < nearest.distance)) {
      nearest = { index: i, option: opt, distance: distance };
    }
  }
  return nearest;
}

function selectDetectedZone(index, sourceText) {
  var sel = document.getElementById('zoneSelect');
  if (!sel || index < 0) return;

  sel.selectedIndex = index;
  updateFee();
  updateZoneBadge(sel.options[index]);
  var lat = parseFloat(document.getElementById('lat')?.value || '');
  var lng = parseFloat(document.getElementById('lng')?.value || '');
  if (Number.isFinite(lat) && Number.isFinite(lng)) updateCoverageStatus(lat, lng, sel.options[index]);
  cakeToast('Delivery zone detected: ' + sel.options[index].value + (sourceText || ''), 'success');
}

function updatePriceSummary() {
  var qty       = parseInt(document.querySelector('[name=quantity]')?.value || 1);
  var size      = document.querySelector('[name=size]')?.value || '';
  var layer     = document.querySelector('[name=layers]')?.value || '';
  var isDelivery= document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';
  var svcCharge = parseFloat(document.getElementById('serviceChargeInput')?.value || 0);
  var sizeSurcharge = SIZE_PRICES[size] ?? 0;
  var layerSurcharge = LAYER_PRICES[layer] ?? 0;

  var addonTotal = 0, addonDetails = [];
  document.querySelectorAll('.addon-check:checked').forEach(chk => {
    var card  = chk.closest('.addon-card');
    var price = parseFloat(card.dataset.price) || 0;
    var name  = card.querySelector('.fw-semibold.small').textContent.trim();
    addonTotal += price; addonDetails.push({ name, price });
  });

  var unitPrice = BASE_CUSTOM + sizeSurcharge + layerSurcharge;
  var subtotal  = unitPrice * qty;
  var total     = subtotal + addonTotal + (isDelivery ? deliveryFee + svcCharge : 0);

  var sizeSurRow = document.getElementById('sizeSurchargeRow');
  if (sizeSurRow) { sizeSurRow.style.display = sizeSurcharge > 0 ? 'flex' : 'none'; }
  var sizeSurDisp = document.getElementById('sizeSurchargeDisplay');
  if (sizeSurDisp && sizeSurcharge > 0) sizeSurDisp.textContent = '+₱' + sizeSurcharge.toFixed(2);

  var layerSurRow = document.getElementById('layerSurchargeRow');
  if (layerSurRow) { layerSurRow.style.display = layerSurcharge > 0 ? 'flex' : 'none'; }
  var layerSurDisp = document.getElementById('layerSurchargeDisplay');
  if (layerSurDisp && layerSurcharge > 0) layerSurDisp.textContent = '+\u20b1' + layerSurcharge.toFixed(2);

  document.getElementById('addonSummary').innerHTML = addonDetails.map(d =>
    '<div class="d-flex justify-content-between small mb-1 text-muted">'
    + '<span><i class="bi bi-check2 me-1" style="color:var(--primary)"></i>' + d.name + '</span>'
    + '<span>' + (d.price > 0 ? '+₱'+d.price.toFixed(2) : 'FREE') + '</span></div>'
  ).join('');

  var qtyRow = document.getElementById('qtyRow');
  if (qtyRow) qtyRow.style.display = qty > 1 ? 'flex' : 'none';
  var qtyDisp = document.getElementById('qtyDisplay');
  if (qtyDisp) qtyDisp.textContent = qty;

  document.getElementById('totalDisplay').textContent =
    '₱' + total.toLocaleString('en-PH', {minimumFractionDigits:2});
}

function highlightAddonCard(input) {
  var card = input.closest('.addon-card');
  card.style.borderColor = input.checked ? 'var(--primary)' : '#e9ecef';
  card.style.background  = input.checked ? 'var(--primary-light)' : '';
}

function toggleOptionalAddons() {
  var panel = document.getElementById('optionalAddonsPanel');
  var btn = document.getElementById('addonToggleBtn');
  if (!panel || !btn) return;
  var willOpen = panel.style.display === 'none';
  panel.style.display = willOpen ? 'block' : 'none';
  btn.querySelector('i').className = willOpen ? 'bi bi-dash-circle me-1' : 'bi bi-plus-circle me-1';
  var label = document.getElementById('addonToggleLabel');
  if (label) label.textContent = willOpen ? 'Hide optional add-ons' : 'Add optional add-ons';
}

function updateAddonPanelState() {
  var count = document.querySelectorAll('.addon-check:checked').length;
  var badge = document.getElementById('addonSelectedCount');
  if (!badge) return;
  badge.style.display = count ? 'inline-block' : 'none';
  badge.textContent = count + ' selected';
}

function toggleDelivery() {
  var isDelivery = document.querySelector('[name=fulfillment_type]:checked').value === 'Delivery';
  document.getElementById('deliverySection').style.display = isDelivery ? 'block' : 'none';
  if (isDelivery && !map) initMap();
  updateCashPaymentCopy();
  updateFee();
}

function clearDetectedDeliveryZone(message = '') {
  var sel = document.getElementById('zoneSelect');
  if (sel) sel.selectedIndex = 0;
  deliveryFee = 0;

  var feeInput = document.getElementById('deliveryFeeInput');
  if (feeInput) feeInput.value = 0;

  var feePreview = document.getElementById('feePreview');
  if (feePreview) feePreview.style.display = 'none';

  var zoneBadge = document.getElementById('zoneBadge');
  if (zoneBadge) zoneBadge.innerHTML = '';

  var etaDisplay = document.getElementById('etaDisplay');
  if (etaDisplay) etaDisplay.style.display = 'none';

  var oocNotice = document.getElementById('oocNotice');
  if (oocNotice) oocNotice.style.display = 'none';

  var feeRow = document.getElementById('feeRow');
  if (feeRow) feeRow.style.display = 'none';

  var msg = document.getElementById('msgZone');
  if (msg) {
    msg.className = message ? 'cv-msg cv-err' : 'cv-msg';
    msg.textContent = message;
  }

  var lat = parseFloat(document.getElementById('lat')?.value || '');
  var lng = parseFloat(document.getElementById('lng')?.value || '');
  if (message && Number.isFinite(lat) && Number.isFinite(lng)) {
    updateCoverageStatus(lat, lng);
  } else if (!message) {
    deliveryCoverageBlocked = false;
  }

  updatePriceSummary();
}

function updateCashPaymentCopy() {
  var isDelivery = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';
  var codLabel = document.getElementById('codLabelText');
  var codHelp  = document.getElementById('codHelpText');
  if (codLabel) {
    codLabel.textContent = isDelivery ? 'Cash on Delivery (COD)' : 'Cash on Pickup (COP)';
  }
  if (codHelp) {
    codHelp.textContent = isDelivery
      ? 'Pay cash when your order arrives.'
      : 'Pay cash when you pick up your order.';
  }
  updateCustomPaymentTransparency();
}

function updateCustomPaymentTransparency() {
  var isDelivery = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';
  var method = document.querySelector('[name=payment_method]:checked')?.value || 'COD';
  var titleEl = document.getElementById('paymentTransparencyTitle');
  var textEl = document.getElementById('paymentTransparencyText');
  var breakdownEl = document.getElementById('paymentTransparencyBreakdown');
  if (!titleEl || !textEl || !breakdownEl) return;

  if (method === 'GCash') {
    titleEl.innerHTML = '<i class="bi bi-shield-check me-1"></i>Online Payment via PayMongo selected';
    textEl.textContent = 'Your custom cake deposit will be processed through PayMongo. Test mode will not charge real money; live mode will charge customers normally.';
    breakdownEl.textContent = 'After the baker confirms the final price, PayMongo will collect the required down payment before the order is confirmed.';
    return;
  }

  titleEl.innerHTML = '<i class="bi bi-info-circle me-1"></i>' + (isDelivery ? 'Cash on Delivery selected' : 'Cash on Pickup selected');
  textEl.textContent = isDelivery
    ? 'GCash down payment is required first. The remaining balance will be paid in cash on delivery.'
    : 'GCash down payment is required first. The remaining balance will be paid in cash on pickup.';
  breakdownEl.textContent = 'For custom cakes, cash payment applies only to the remaining balance after the PayMongo down payment.';
}

// ── Map ───────────────────────────────────────────────────────────────
async function reverseGeocode(lat, lng) {
  var field = document.getElementById('addressField');
  var ind   = document.getElementById('addressLoading');
  if (ind) ind.style.display = 'inline';
  try {
    var res  = await fetch(`/api/geocode/reverse?lat=${lat}&lng=${lng}`);
    var data = await res.json();
    if (data && data.display_name) {
      var a = data.address || {};
      var parts = [
        a.house_number ? (a.house_number + ' ' + (a.road||'')) : a.road,
        a.suburb||a.village||a.neighbourhood,
        a.city_district||a.county,
        a.city||a.town||a.municipality, a.state
      ].filter(Boolean);
      field.value = parts.length > 0 ? parts.join(', ') : data.display_name;
    }
  } catch(e) {} finally { if (ind) ind.style.display = 'none'; }
}

function setMarkerAt(latlng) {
  clearDetectedDeliveryZone();
  if (marker) marker.setLatLng(latlng);
  else {
    marker = L.marker(latlng, { draggable:true }).addTo(map);
    marker.on('dragend', e => {
      var ll = e.target.getLatLng();
      document.getElementById('lat').value = ll.lat;
      document.getElementById('lng').value = ll.lng;
      reverseGeocode(ll.lat, ll.lng);
      autoSelectBarangayFromCoords(ll.lat, ll.lng);
    });
  }
  document.getElementById('lat').value = latlng.lat;
  document.getElementById('lng').value = latlng.lng;
  reverseGeocode(latlng.lat, latlng.lng);
  autoSelectBarangayFromCoords(latlng.lat, latlng.lng);
  setTimeout(cvValidateMap, 300);
}

function drawCoverageAreas() {
  var bounds = [];
  var coverageFeatures = [];
  var sel = document.getElementById('zoneSelect');
  if (!sel) return;
  for (var i = 0; i < sel.options.length; i++) {
    var opt = sel.options[i];
    var zLat = parseFloat(opt.dataset.lat || '');
    var zLng = parseFloat(opt.dataset.lng || '');
    if (!opt.value || !Number.isFinite(zLat) || !Number.isFinite(zLng)) continue;

    if (window.turf) {
      coverageFeatures.push(turf.circle([zLng, zLat], COVERAGE_RADIUS / 1000, {
        steps: 72,
        units: 'kilometers',
        properties: { name: opt.value }
      }));
    } else {
      L.circle([zLat, zLng], {
        radius: COVERAGE_RADIUS,
        color: '#16a34a',
        weight: 1.5,
        fillColor: '#22c55e',
        fillOpacity: .09,
        dashArray: '6 4',
        interactive: false
      }).addTo(map);
    }
    L.circleMarker([zLat, zLng], {
      radius: 4,
      color: '#15803d',
      weight: 1.5,
      fillColor: '#22c55e',
      fillOpacity: .75,
      interactive: true
    }).addTo(map).bindTooltip(opt.value, { direction: 'top' });
    bounds.push([zLat, zLng]);
  }

  if (coverageFeatures.length) {
    var merged = coverageFeatures[0];
    for (var j = 1; j < coverageFeatures.length; j++) {
      try { merged = turf.union(merged, coverageFeatures[j]) || merged; }
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

  if (bounds.length > 1) map.fitBounds(bounds, { padding: [28, 28], maxZoom: 14 });
}

function initMap() {
  var defaultLat = {{ (float)($shopLat ?? 15.8107127) }};
  var defaultLng = {{ (float)($shopLng ?? 120.4716710) }};
  map = L.map('map').setView([defaultLat, defaultLng], 14);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
  drawCoverageAreas();
  map.on('click', e => setMarkerAt(e.latlng));
}

function useMyLocation() {
  var btn = document.getElementById('useMyLocationBtn');
  if (!window.berryBaseHasLocationSupport?.()) { cakeToast('Your browser does not support GPS location.','error'); return; }
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Getting location…';
  window.berryBaseGetCurrentPosition(
    async pos => {
      var lat = pos.coords.latitude, lng = pos.coords.longitude;
      map.setView([lat, lng], 16);
      setMarkerAt({ lat, lng });
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-geo-alt-fill me-1"></i>📍 Use My Current Location';
      cakeToast('📍 Location found! Map updated.','success');
    },
    err => {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-geo-alt-fill me-1"></i>📍 Use My Current Location';
      var msgs = {1:'Location permission denied.',2:'Could not get your location.',3:'Location request timed out.'};
      cakeToast(msgs[err.code]||'Could not get location.','error');
    },
    { enableHighAccuracy:false, timeout:10000, maximumAge:30000 }
  );
}

async function autoSelectBarangayFromCoords(lat, lng) {
  clearDetectedDeliveryZone();
  try {
    var res  = await fetch(`/api/geocode/reverse?lat=${lat}&lng=${lng}`);
    var data = await res.json();
    if (!data || !data.address) {
      var nearest = findNearestZoneOption(lat, lng);
      if (nearest) selectDetectedZone(nearest.index, ' (nearest coverage)');
      else clearDetectedDeliveryZone('Delivery is not available at this pinned location. Please move the pin or choose pickup.');
      return;
    }
    var a = data.address;
    var suburb = (a.suburb||a.village||a.neighbourhood||a.hamlet||'').toLowerCase();
    var sel = document.getElementById('zoneSelect');
    if (!sel) return;
    var matched = false;
    for (var i = 0; i < sel.options.length; i++) {
      var optVal = sel.options[i].value.toLowerCase();
      if (optVal && suburb && (suburb.includes(optVal) || optVal.includes(suburb))) {
        sel.selectedIndex = i; matched = true; updateFee();
        updateZoneBadge(sel.options[i]);
        cakeToast('📍 Delivery zone detected: ' + sel.options[i].value,'success'); break;
      }
    }
    if (!matched) {
      var nearest = findNearestZoneOption(lat, lng);
      if (nearest) selectDetectedZone(nearest.index, ' (nearest coverage)');
      else clearDetectedDeliveryZone('Delivery is not available at this pinned location. Please move the pin or choose pickup.');
    }
  } catch(e) {
    var nearest = findNearestZoneOption(lat, lng);
    if (nearest) selectDetectedZone(nearest.index, ' (nearest coverage)');
    else clearDetectedDeliveryZone('Delivery is not available at this pinned location. Please move the pin or choose pickup.');
  }
}

function updateZoneBadge(opt) {
  var el = document.getElementById('zoneBadge');
  if (!el || !opt) return;
  var col = opt.dataset.color || '#888';
  var lbl = opt.dataset.label || '';
  el.innerHTML = '<span style="display:inline-flex;align-items:center;gap:4px;background:'+col+'22;color:'+col+';border:1.5px solid '+col+'66;border-radius:20px;padding:2px 10px;font-size:.7rem;font-weight:700"><i class="bi bi-circle-fill" style="font-size:.38rem"></i>'+lbl+'</span>';
}

function updateFee() {
  var sel      = document.getElementById('zoneSelect');
  var opt      = sel?.options[sel.selectedIndex];
  var zoneType = opt?.dataset?.type || '';
  var eta      = opt?.dataset?.eta || '';
  deliveryFee  = parseFloat(opt?.dataset?.fee || 0);
  var isDelivery = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';

  var oocNotice = document.getElementById('oocNotice');
  if (oocNotice) oocNotice.style.display = (zoneType === 'ooc') ? 'block' : 'none';

  var etaDisplay = document.getElementById('etaDisplay');
  var etaText    = document.getElementById('etaText');
  if (etaDisplay && eta && opt && opt.value && zoneType !== 'ooc') {
    etaDisplay.style.display = 'flex'; if (etaText) etaText.textContent = eta;
  } else if (etaDisplay) { etaDisplay.style.display = 'none'; }

  var feePreview = document.getElementById('feePreview');
  var feeAmt     = document.getElementById('feePreviewAmount');
  if (feePreview && opt && opt.value) {
    feePreview.style.display = 'block';
    feePreview.style.background = deliveryFee === 0 ? '#f0fdf4' : '#eff6ff';
    feePreview.style.borderColor = deliveryFee === 0 ? '#d1fae5' : '#bfdbfe';
    if (feeAmt) {
      feeAmt.style.color = deliveryFee === 0 ? '#166534' : '#1e40af';
      feeAmt.textContent = deliveryFee === 0 ? 'FREE' : (zoneType==='ooc' ? '₱250+ (to be confirmed)' : '₱'+deliveryFee.toFixed(2));
    }
  } else if (feePreview) { feePreview.style.display = 'none'; }

  var feeRow = document.getElementById('feeRow');
  if (isDelivery && deliveryFee > 0) {
    if (feeRow) { feeRow.style.display='flex'; document.getElementById('feeDisplay').textContent = zoneType==='ooc' ? '₱250+' : '₱'+deliveryFee.toFixed(2); }
  } else { if (feeRow) feeRow.style.display='none'; }
  if (document.getElementById('deliveryFeeInput')) document.getElementById('deliveryFeeInput').value = deliveryFee;
  updatePriceSummary();
}

// ── Live Validation ───────────────────────────────────────────────────
var cvRules = {
  name:  { pattern:/^[A-Za-zÀ-ÿ\s.\-']+$/, min:2, errChar:'Letters and spaces only.' },
  phone: { pattern:/^[0-9+]+$/, errChar:'Numbers only.' },
  otp:   { pattern:/^[0-9]+$/, errChar:'Numbers only.' },
};

function showOrderValidationWarning(issues, focusEl) {
  var uniqueIssues = Array.from(new Set(issues.filter(Boolean)));
  if (!uniqueIssues.length) uniqueIssues.push('Please complete all required details.');
  var message = 'Please fix these before placing your order:\n\n' + uniqueIssues.map(function(issue, index) {
    return (index + 1) + '. ' + issue;
  }).join('\n');
  var focusTarget = focusEl;
  var onConfirm = function() {
    if (focusTarget && typeof focusTarget.scrollIntoView === 'function') {
      focusTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
      setTimeout(function() {
        if (typeof focusTarget.focus === 'function') focusTarget.focus({ preventScroll: true });
      }, 350);
    }
  };
  if (typeof _csDlgBuild === 'function') {
    _csDlgBuild({
      title: 'Complete Missing Details',
      message: message,
      icon: 'bi-exclamation-triangle-fill',
      iconBg: '#fff7ed',
      iconColor: '#f59e0b',
      okLabel: 'Review Details',
      okColor: '#f59e0b',
      showCancel: false,
      onConfirm: onConfirm
    });
    return;
  }
  alert(message);
  onConfirm();
}

function cvSetState(input, msgId, state, text) {
  input.classList.remove('cv-valid','cv-invalid');
  var msg = document.getElementById(msgId);
  if (msg) { msg.className='cv-msg'; msg.textContent=''; }
  if (state==='ok')  { input.classList.add('cv-valid');   if(msg){msg.classList.add('cv-ok');  msg.textContent=text||'';} }
  if (state==='err') { input.classList.add('cv-invalid'); if(msg){msg.classList.add('cv-err'); msg.textContent=text||'';} }
}
function cvShake(el) {
  el.classList.remove('cv-shake'); void el.offsetWidth; el.classList.add('cv-shake');
  if (navigator.vibrate) navigator.vibrate([60,30,60]);
  setTimeout(() => el.classList.remove('cv-shake'), 400);
}
function cvBlockChar(event, ruleKey) {
  var rule = cvRules[ruleKey];
  if (!rule || event.key.length > 1) return;
  if (!rule.pattern.test(event.key)) { event.preventDefault(); cvShake(event.target); }
}
function cvValidate(input) {
  var val = input.value.trim();
  var id  = input.id;
  var msgId = '', ruleKey = '';
  if (id==='fieldName')   { msgId='msgName';  ruleKey='name'; }
  if (id==='coGuestPhone'){ msgId='msgPhone'; ruleKey='phone'; }
  var rule = cvRules[ruleKey]; if (!rule) return;
  if (!val) { cvSetState(input,msgId,'err','This field is required.'); return; }
  if (!rule.pattern.test(val)) { cvShake(input); cvSetState(input,msgId,'err',rule.errChar); return; }
  if (val.length < (rule.min||0)) { cvSetState(input,msgId,'err','Too short.'); return; }
  if (ruleKey==='phone') {
    var digits = val.replace(/\D/g,'');
    if (val.startsWith('+63') && digits.length!==12) { cvSetState(input,msgId,'err','Must be +63 followed by 10 digits.'); return; }
    if (val.startsWith('09')  && digits.length!==11) { cvSetState(input,msgId,'err','Must be 11 digits starting with 09.'); return; }
    if (!val.startsWith('09') && !val.startsWith('+63')) { cvSetState(input,msgId,'err','Phone must start with 09 or +63.'); return; }
    cvSetState(input,msgId,'ok','✓ Valid phone number.'); return;
  }
  cvSetState(input,msgId,'ok','✓ Looks good!');
}
function cvValidateOtp(input) {
  var val = input.value.replace(/\D/g,''); input.value = val;
  var msg = document.getElementById('msgOtp');
  input.classList.remove('cv-valid','cv-invalid');
  if (msg) { msg.className='cv-msg'; msg.textContent=''; }
  if (!val) return;
  if (val.length < 6) {
    input.classList.add('cv-invalid');
    if (msg) { msg.classList.add('cv-err'); msg.textContent=val.length+'/6 digits entered.'; }
  } else {
    input.classList.add('cv-valid');
    if (msg) { msg.classList.add('cv-ok'); msg.textContent='✓ OTP complete.'; }
    var badge = document.getElementById('otpStatusBadge');
    if (badge) { badge.style.background='#dcfce7'; badge.style.color='#166534'; badge.innerHTML='<i class="bi bi-shield-check-fill me-1"></i>Identity verified'; }
  }
}
function cvValidateZone() {
  var sel=document.getElementById('zoneSelect'), msg=document.getElementById('msgZone');
  if(!sel||!msg) return;
  msg.className='cv-msg';
  if (!sel.value) { msg.classList.add('cv-err'); msg.textContent='Please pin your delivery location on the map above.'; cvShake(document.getElementById('map')); }
  else if (deliveryCoverageBlocked) { msg.classList.add('cv-err'); msg.textContent='This pinned location is outside the seller delivery area. Move the pin or choose pickup.'; cvShake(document.getElementById('map')); }
  else { msg.classList.add('cv-ok'); msg.textContent='✓ Delivery zone detected.'; }
}
function cvValidateMap() {
  var lat=document.getElementById('lat')?.value, lng=document.getElementById('lng')?.value;
  var mapEl=document.getElementById('map'), msg=document.getElementById('msgMap');
  if(!mapEl||!msg) return true;
  if(!lat||!lng) {
    mapEl.style.borderColor='#ef4444'; mapEl.style.boxShadow='0 0 0 3px rgba(239,68,68,.15)';
    msg.className='cv-msg cv-err'; msg.textContent='Please pin your exact location on the map.'; return false;
  }
  if(deliveryCoverageBlocked) {
    mapEl.style.borderColor='#f97316'; mapEl.style.boxShadow='0 0 0 3px rgba(249,115,22,.2)';
    msg.className='cv-msg cv-err'; msg.textContent='This pinned location is outside the seller delivery area. Move the pin inside the green coverage area or choose pickup.'; return false;
  }
  mapEl.style.borderColor='#16a34a'; mapEl.style.boxShadow='0 0 0 3px rgba(22,163,74,.15)';
  msg.className='cv-msg cv-ok'; msg.textContent='✓ Location pinned.'; return true;
}
function cvValidateDate(input) {
  var val=input.value, msg=document.getElementById('msgDate');
  input.classList.remove('cv-valid','cv-invalid');
  if(msg){msg.className='cv-msg';msg.textContent='';}
  if(!val) return;
  var selected=new Date(val+'T00:00:00'), today=new Date(); today.setHours(0,0,0,0);
  var tomorrow=new Date(today); tomorrow.setDate(tomorrow.getDate()+1);
  if(isNaN(selected.getTime())) { input.classList.add('cv-invalid'); cvShake(input); if(msg){msg.classList.add('cv-err');msg.textContent='Invalid date.';} return; }
  if(selected < tomorrow) { input.classList.add('cv-invalid'); cvShake(input); if(msg){msg.classList.add('cv-err');msg.textContent='Please select a date starting from tomorrow. Same-day orders are not accepted.';} return; }
  var days=Math.round((selected-today)/(1000*60*60*24));
  input.classList.add('cv-valid');
  if(msg){ msg.classList.add('cv-ok'); msg.textContent='✓ '+days+' day'+(days>1?'s':'')+' from today.'; }
}
var coGuestAvailabilityIssue = '';
var coGuestAvailabilityPending = false;

function checkCoGuestAvailability() {
  const date   = document.getElementById('fieldDate')?.value;
  const shopId = '{{ $targetShop->id ?? '' }}';
  const qty    = parseInt(document.querySelector('[name=quantity]')?.value || '1', 10);
  const el     = document.getElementById('coGuestAvailability');
  coGuestAvailabilityIssue = '';
  coGuestAvailabilityPending = false;
  if (!date || !el) return;
  coGuestAvailabilityPending = true;
  el.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split me-1"></i>Checking availability…</span>';
  const url = '/catalog/availability?date=' + encodeURIComponent(date) + (shopId ? '&shop_id=' + encodeURIComponent(shopId) : '');
  fetch(url)
    .then(r => r.json())
    .then(data => {
      coGuestAvailabilityPending = false;
      if (data.status === 'invalid') {
        coGuestAvailabilityIssue = data.message || 'Selected date is not available.';
        el.innerHTML = '<span class="text-danger fw-semibold"><i class="bi bi-x-circle-fill me-1"></i>' + (data.message || 'Date not available.') + '</span>';
      } else if (data.status === 'full') {
        coGuestAvailabilityIssue = (data.message || 'This date is fully booked.') + ' Please choose another date.';
        el.innerHTML = '<span class="text-danger fw-semibold"><i class="bi bi-x-circle-fill me-1"></i>' + data.message + ' — please choose another date.</span>';
      } else if (typeof data.remaining === 'number' && qty > data.remaining) {
        coGuestAvailabilityIssue = 'Only ' + data.remaining + ' slot' + (data.remaining !== 1 ? 's' : '') + ' available on this date. Please choose another date or reduce quantity.';
        el.innerHTML = '<span class="text-danger fw-semibold"><i class="bi bi-x-circle-fill me-1"></i>Only ' + data.remaining + ' slot' + (data.remaining !== 1 ? 's' : '') + ' available on this date. Please choose another date or reduce quantity.</span>';
      } else if (data.status === 'almost') {
        coGuestAvailabilityIssue = '';
        el.innerHTML = '<span class="text-warning fw-semibold"><i class="bi bi-exclamation-triangle-fill me-1"></i>' + data.message + '</span>';
      } else if (data.status === 'available') {
        coGuestAvailabilityIssue = '';
        el.innerHTML = '<span class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>' + data.message + '</span>';
      } else {
        coGuestAvailabilityIssue = '';
        el.innerHTML = '';
      }
    })
    .catch(() => { coGuestAvailabilityPending = false; coGuestAvailabilityIssue = ''; el.innerHTML = ''; });
}
function cvClearErr(el, msgId) {
  el.classList.remove('cv-invalid');
  var m = document.getElementById(msgId);
  if (m) { m.className = 'cv-msg'; m.textContent = ''; }
}

function cvRequireText(el, msgId, label) {
  if (!el) return true;
  var val = el.value.trim();
  if (!val) {
    cvSetState(el, msgId, 'err', label + ' is required.');
    cvShake(el);
    return false;
  }
  cvSetState(el, msgId, 'ok', '');
  return true;
}

function cvRequireSelect(el, msgId, label) {
  if (!el) return true;
  if (!el.value) {
    el.classList.remove('cv-valid'); el.classList.add('cv-invalid');
    var m = document.getElementById(msgId);
    if (m) { m.className = 'cv-msg cv-err'; m.textContent = 'Please select a ' + label + '.'; }
    cvShake(el);
    return false;
  }
  el.classList.remove('cv-invalid'); el.classList.add('cv-valid');
  var m = document.getElementById(msgId);
  if (m) { m.className = 'cv-msg'; m.textContent = ''; }
  return true;
}

function cvValidateAllCustom() {
  var isDelivery = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';
  var ok = true, firstErr = null;
  var issues = [];

  // ── 1. Cake name ───────────────────────────────────────────────────────
  var cakeNameEl = document.getElementById('fieldCakeName');
  if (!cvRequireText(cakeNameEl, 'msgCakeName', 'Cake name')) { issues.push('Cake name is required.'); ok = false; firstErr = firstErr || cakeNameEl; }

  // ── 2. Flavor ──────────────────────────────────────────────────────────
  var flavorEl = document.getElementById('fieldFlavor');
  if (!cvRequireSelect(flavorEl, 'msgFlavor', 'flavor')) { issues.push('Please select a flavor.'); ok = false; firstErr = firstErr || flavorEl; }

  // ── 3. Size ────────────────────────────────────────────────────────────
  var sizeEl = document.getElementById('fieldSize');
  if (!cvRequireSelect(sizeEl, 'msgSize', 'cake size')) { issues.push('Please select a cake size.'); ok = false; firstErr = firstErr || sizeEl; }

  // ── 4. Delivery location ───────────────────────────────────────────────
  if (isDelivery) {
    var zoneEl = document.getElementById('zoneSelect');
    if (!cvValidateMap()) {
      issues.push(deliveryCoverageBlocked ? 'Pinned location is outside the seller delivery area.' : 'Please pin your exact delivery location on the map.');
      ok = false; firstErr = firstErr || document.getElementById('map');
    }
    cvValidateZone();
    if (!zoneEl?.value) {
      issues.push('Please select a delivery area.');
      ok = false; firstErr = firstErr || document.getElementById('map');
    }
    if (deliveryCoverageBlocked) {
      ok = false; firstErr = firstErr || document.getElementById('map');
    }
  }

  // ── 5. Guest identity (name, phone, OTP) ──────────────────────────────
  var nameEl = document.getElementById('fieldName');
  if (nameEl) {
    cvValidate(nameEl);
    if (nameEl.classList.contains('cv-invalid') || !nameEl.value.trim()) {
      issues.push(nameEl.value.trim() ? 'Full name has invalid characters.' : 'Full name is required.');
      cvSetState(nameEl, 'msgName', 'err', 'Full name is required.'); cvShake(nameEl); ok = false; firstErr = firstErr || nameEl;
    }
  }

  var phoneEl = document.getElementById('coGuestPhone');
  if (phoneEl) {
    cvValidate(phoneEl);
    if (phoneEl.classList.contains('cv-invalid') || !phoneEl.value.trim()) {
      issues.push(phoneEl.value.trim() ? 'Phone number format is invalid.' : 'Phone number is required.');
      cvSetState(phoneEl, 'msgPhone', 'err', 'Phone number is required.'); cvShake(phoneEl); ok = false; firstErr = firstErr || phoneEl;
    }
  }

  if (!otpSent) {
    var msgPhone = document.getElementById('msgPhone');
    if (msgPhone) { msgPhone.className = 'cv-msg cv-err'; msgPhone.textContent = 'Please send and verify your OTP first.'; }
    if (phoneEl) { phoneEl.classList.add('cv-invalid'); cvShake(phoneEl); }
    issues.push('Please tap "Send OTP" to verify your phone number.');
    ok = false; firstErr = firstErr || phoneEl;
  } else {
    var otpEl = document.getElementById('fieldOtp');
    if (otpEl && otpEl.value.length !== 6) {
      otpEl.classList.add('cv-invalid');
      var m = document.getElementById('msgOtp');
      if (m) { m.className = 'cv-msg cv-err'; m.textContent = 'Please enter the complete 6-digit OTP.'; }
      issues.push('Please enter the complete 6-digit OTP.');
      cvShake(otpEl); ok = false; firstErr = firstErr || otpEl;
    }
  }

  var dateEl = document.getElementById('fieldDate');
  if (dateEl) {
    if (!dateEl.value) {
      cvSetState(dateEl, 'msgDate', 'err', 'Please select your preferred date.');
      cvShake(dateEl);
      issues.push('Preferred date is required.');
      ok = false; firstErr = firstErr || dateEl;
    } else {
      cvValidateDate(dateEl);
      if (dateEl.classList.contains('cv-invalid')) {
        issues.push('Preferred date is invalid or too soon for custom cakes.');
        ok = false; firstErr = firstErr || dateEl;
      }
      if (coGuestAvailabilityPending) {
        issues.push('Please wait for the date availability check to finish.');
        ok = false; firstErr = firstErr || dateEl;
      } else if (coGuestAvailabilityIssue) {
        issues.push(coGuestAvailabilityIssue);
        ok = false; firstErr = firstErr || dateEl;
      }
    }
  }

  var timeEl = document.querySelector('[name=time_slot]');
  if (timeEl && !timeEl.value) {
    timeEl.classList.add('cv-invalid');
    issues.push('Preferred time slot is required.');
    ok = false; firstErr = firstErr || timeEl;
  } else if (timeEl) {
    timeEl.classList.remove('cv-invalid');
  }

  if (!ok) showOrderValidationWarning(issues, firstErr);
  return ok;
}

function confirmCustomOrder(btn) {
  if (!cvValidateAllCustom()) return false;
  var isDelivery = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';
  if (isDelivery) {
    var lat = document.getElementById('lat')?.value, lng = document.getElementById('lng')?.value;
    if (!lat || !lng) { cakeToast('Please pin your delivery location on the map first.','error'); return false; }
  }
  var total = document.getElementById('totalDisplay').textContent;
  cakeConfirm({ title:'Review Custom Order', message:'Estimated Total: '+total+'\n\nNote: This is a base estimate only. The final price will be confirmed by our baker after reviewing your design reference.', icon:'bi-clipboard-check', okLabel:'Submit Custom Order',
    onConfirm:() => { csSubmitOnce(document.getElementById('customOrderForm'), btn, '<span class="spinner-border spinner-border-sm me-2"></span>Submitting Order...'); }
  });
  return false;
}

document.addEventListener('DOMContentLoaded', () => { updateCashPaymentCopy(); updatePriceSummary(); checkCoGuestAvailability(); });

// ── Reference image multi-select ──────────────────────────────────────
var otpSent = false;

var refFiles = [];
var MAX_REF  = 5;
function addRefImages(input) {
  var newFiles  = Array.from(input.files);
  var remaining = MAX_REF - refFiles.length;
  refFiles = [...refFiles, ...newFiles.slice(0, remaining)];
  input.value = ''; renderRefPreviews();
}
function renderRefPreviews() {
  var strip = document.getElementById('refImgPreviewStrip');
  var count = document.getElementById('refImgCount');
  var btn   = document.getElementById('refImgBtn');
  strip.innerHTML = '';
  count.textContent = refFiles.length + ' / ' + MAX_REF + ' selected';
  btn.style.display = refFiles.length >= MAX_REF ? 'none' : '';
  refFiles.forEach((file, idx) => {
    var wrap = document.createElement('div'); wrap.style='position:relative;display:inline-block';
    var img  = document.createElement('img');
    img.style='width:72px;height:72px;object-fit:cover;border-radius:.5rem;border:2px solid var(--primary)';
    var reader = new FileReader(); reader.onload = e => img.src=e.target.result; reader.readAsDataURL(file);
    var rm = document.createElement('button');
    rm.type='button'; rm.innerHTML='&times;';
    rm.style='position:absolute;top:-5px;right:-5px;width:18px;height:18px;border-radius:50%;background:#ef4444;border:none;color:white;font-size:.6rem;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0';
    rm.onclick = () => { refFiles.splice(idx,1); renderRefPreviews(); syncRefInput(); };
    wrap.appendChild(img); wrap.appendChild(rm); strip.appendChild(wrap);
  });
  syncRefInput();
}
function syncRefInput() {
  var input = document.getElementById('refImgInput');
  var dt    = new DataTransfer();
  refFiles.forEach(f => dt.items.add(f));
  input.files = dt.files;
}

// ── OTP ───────────────────────────────────────────────────────────────
function sendCoGuestOtp() {
  var phone = document.getElementById('coGuestPhone').value.trim();
  if (!phone) { cakeToast('Please enter your phone number first.','error'); return; }
  var phoneEl = document.getElementById('coGuestPhone');
  cvValidate(phoneEl);
  if (phoneEl.classList.contains('cv-invalid')) { cakeToast('Please enter a valid phone number.','error'); return; }
  var btn = document.getElementById('coSendOtpBtn');
  btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Sending...';
  var fd = new FormData();
  fd.append('_token', '{{ csrf_token() }}');
  fd.append('phone', phone);
  fd.append('shop_slug', '{{ $targetShop->shop_slug ?? '' }}');
  fetch('{{ route("guest.custom_order.send_otp") }}', {
    method:'POST',
    body:fd,
    headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' }
  })
    .then(r=>r.json())
    .then(data => {
      if (!data.ok) { cakeToast(data.error||'Failed to send OTP.','error'); btn.innerHTML='<i class="bi bi-phone me-1"></i>Send OTP'; btn.disabled=false; return; }
      document.getElementById('coOtpSection').style.display='block';
      document.querySelector('[name="otp_code"]').required=true;
      otpSent = true;
      document.getElementById('customOrderForm')?.dispatchEvent(new Event('change', { bubbles:true }));
      var badge = document.getElementById('otpStatusBadge');
      if (data.dev) renderCoDevOtpHint(data.dev);
      if (badge) { badge.style.background='#dcfce7'; badge.style.color='#166534'; badge.innerHTML='<i class="bi bi-check-circle-fill me-1"></i>OTP sent — enter code below'; }
      cakeToast(data.dev ? 'Developer mode: OTP is shown below.' : 'OTP sent! Check your SMS.','success');
      btn.innerHTML='<i class="bi bi-arrow-repeat me-1"></i>Resend'; btn.disabled=false;
    })
    .catch(()=>{ btn.innerHTML='<i class="bi bi-phone me-1"></i>Send OTP'; btn.disabled=false; cakeToast('Failed to send OTP. Try again.','error'); });
}

function renderCoDevOtpHint(dev) {
  var wrap = document.getElementById('coDevOtpHint');
  if (!wrap) return;
  var esc = function(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); };
  wrap.innerHTML =
    '<div style="margin-top:1rem;border-radius:14px;overflow:hidden;border:1.5px solid #fde68a;box-shadow:0 4px 18px rgba(245,158,11,.12)">' +
      '<div style="background:linear-gradient(90deg,#78350f,#92400e);padding:.55rem 1rem;display:flex;align-items:center;justify-content:space-between">' +
        '<span style="font-size:.7rem;font-weight:700;color:#fef3c7;letter-spacing:.08em;text-transform:uppercase;display:flex;align-items:center;gap:.4rem">' +
          '<i class="bi bi-bug-fill" style="font-size:.75rem"></i> Developer Mode &mdash; SMS Preview' +
        '</span>' +
        '<span style="font-size:.65rem;color:rgba(254,243,199,.65)">' + esc(dev.time) + '</span>' +
      '</div>' +
      '<div style="background:linear-gradient(135deg,#fffbeb,#fefce8);padding:.85rem 1rem">' +
        '<div style="font-size:.72rem;color:#b45309;margin-bottom:.65rem;font-style:italic">This is what the customer receives when SMS is working:</div>' +
        '<div style="background:#fff;border-radius:12px;padding:.75rem .9rem;border:1px solid #fde68a;margin-bottom:.85rem">' +
          '<div style="font-size:.64rem;font-weight:700;color:#92400e;letter-spacing:.05em;margin-bottom:.3rem;text-transform:uppercase"><i class="bi bi-phone-fill" style="font-size:.7rem"></i> SMS from UniSMS</div>' +
          '<div style="font-size:.8rem;color:#1c1917;line-height:1.6;font-family:monospace;word-break:break-word">' + esc(dev.message) + '</div>' +
          '<div style="font-size:.62rem;color:#a8a29e;text-align:right;margin-top:.35rem">' + esc(dev.time) + ' &nbsp;&middot;&nbsp; Delivered</div>' +
        '</div>' +
        '<div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;background:#fff7ed;border:1.5px solid #fed7aa;border-radius:10px;padding:.6rem .9rem">' +
          '<div>' +
            '<div style="font-size:.62rem;color:#c2410c;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px">OTP Code</div>' +
            '<div id="coDevOtpCode" style="font-size:1.7rem;font-weight:800;letter-spacing:.22em;color:#ea580c;font-family:monospace;line-height:1;cursor:pointer" onclick="copyCoDevOtp()" title="Click to copy">' + esc(dev.otp) + '</div>' +
          '</div>' +
          '<button type="button" onclick="copyCoDevOtp()" style="flex-shrink:0;background:linear-gradient(135deg,#ea580c,#c2410c);color:#fff;border:none;border-radius:8px;padding:.45rem .9rem;font-size:.75rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:.35rem">' +
            '<i class="bi bi-copy" id="coDevCopyIcon"></i><span id="coDevCopyLabel">Copy OTP</span>' +
          '</button>' +
        '</div>' +
        '<div style="display:flex;flex-wrap:wrap;gap:.3rem 1.2rem;margin-top:.65rem;font-size:.72rem;color:#92400e">' +
          '<span><i class="bi bi-telephone-fill" style="font-size:.65rem;margin-right:.25rem"></i>+' + esc(dev.phone) + '</span>' +
        '</div>' +
      '</div>' +
    '</div>';
}

function copyCoDevOtp() {
  var el = document.getElementById('coDevOtpCode');
  var label = document.getElementById('coDevCopyLabel');
  var icon  = document.getElementById('coDevCopyIcon');
  if (!el) return;
  var done = function() {
    if (label) label.textContent = 'Copied!';
    if (icon) icon.className = 'bi bi-check-lg';
    setTimeout(function(){
      if (label) label.textContent = 'Copy OTP';
      if (icon) icon.className = 'bi bi-copy';
    }, 2000);
  };
  if (navigator.clipboard) {
    navigator.clipboard.writeText(el.textContent.trim()).then(done).catch(function(){
      var r = document.createRange(); r.selectNodeContents(el);
      window.getSelection().removeAllRanges(); window.getSelection().addRange(r);
      document.execCommand('copy'); window.getSelection().removeAllRanges();
      done();
    });
    return;
  }
  var r = document.createRange(); r.selectNodeContents(el);
  window.getSelection().removeAllRanges(); window.getSelection().addRange(r);
  document.execCommand('copy'); window.getSelection().removeAllRanges();
  done();
}
</script>
@endpush
