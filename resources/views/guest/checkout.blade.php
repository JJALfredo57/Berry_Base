@extends('layouts.app')
@section('content')
@php
  $originalSubtotal = $pricing['original_unit_price'] * $checkout['quantity'];
  $discountedSubtotal = $pricing['final_unit_price'] * $checkout['quantity'];
  $productDiscountTotal = $pricing['discount_amount'] * $checkout['quantity'];
@endphp
<script>
// Remove any stuck modal backdrop from catalog page
document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
document.body.classList.remove('modal-open');
document.body.style.overflow = '';
document.body.style.paddingRight = '';
</script>
<div class="py-4" style="padding-left:clamp(12px,3vw,32px);padding-right:clamp(12px,3vw,32px)">
      <h4 class="fw-bold mb-4 text-center"><i class="bi bi-bag-check me-2" style="color:var(--primary)"></i>Checkout</h4>

      @if(session('error'))
        <div class="alert alert-danger border-0"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
      @endif

      <div class="row g-4">
        {{-- LEFT: Form --}}
        <div class="col-lg-8 col-xl-9">
          <form action="{{ route('guest.checkout.place') }}" method="POST" id="checkoutForm">
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

            {{-- Guest Info + OTP --}}
            <div class="card mb-3">
              <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-person me-2" style="color:var(--primary)"></i>Your Information</h6>

                @if(session('error'))
                  <div class="alert alert-danger border-0 py-2">{{ session('error') }}</div>
                @endif

                <div class="row g-3">
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control cv-field" name="guest_name" id="fieldName"
                           value="{{ old('guest_name') }}"
                           placeholder="e.g. Maria Santos" required maxlength="120"
                           data-cv-rule="name"
                           oninput="cvValidate(this)" onkeypress="cvBlockChar(event,'name')">
                    <div class="cv-msg" id="msgName"></div>
                    <div class="form-text"><i class="bi bi-info-circle me-1"></i>Enter your full name as it will appear on your order.</div>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small">
                      Phone Number <span class="text-danger">*</span>
                      <span class="text-muted fw-normal">(for OTP verification)</span>
                    </label>
                    <div class="input-group">
                      <input type="tel" class="form-control cv-field" id="guestPhone" name="phone"
                             value="{{ old('phone', session('guest_phone')) }}"
                             placeholder="09XXXXXXXXX" required
                             data-cv-rule="phone"
                             oninput="cvValidate(this)" onkeypress="cvBlockChar(event,'phone')">
                      <button type="button" class="btn btn-outline-primary" id="sendOtpBtn"
                              onclick="sendGuestOtp()">
                        <i class="bi bi-phone me-1"></i>Send OTP
                      </button>
                    </div>
                    <div class="cv-msg" id="msgPhone"></div>
                    <div class="form-text"><i class="bi bi-info-circle me-1"></i>We'll send your OTP and order updates to this number.</div>
                  </div>
                </div>

                {{-- OTP Field --}}
                <div class="mt-3" id="otpSection" style="display:none">
                  <div class="p-3 rounded-3" style="background:#f0fdf4;border:1.5px solid #bbf7d0">
                    <div class="fw-semibold small mb-1" style="color:#15803d">
                      <i class="bi bi-check-circle me-1"></i>
                      OTP sent to your phone
                    </div>
                    <label class="form-label fw-semibold small mt-2">Enter 6-digit OTP <span class="text-danger">*</span></label>
                    <input type="text" class="form-control cv-field" name="otp_code" id="fieldOtp"
                           placeholder="000000" maxlength="6"
                           style="letter-spacing:.3em;font-size:clamp(.95rem,2.5vw,1.2rem);width:160px"
                           oninput="cvValidateOtp(this)" onkeypress="cvBlockChar(event,'otp')">
                    <div class="cv-msg" id="msgOtp"></div>
                    <div class="form-text mt-1"><i class="bi bi-info-circle me-1"></i>Enter the 6-digit code sent to your phone via SMS. Valid for 10 minutes.</div>
                    <div id="devOtpHintAjax"></div>
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
                    <div class="text-muted" style="font-size:clamp(.68rem,1.3vw,.72rem)">Pick up your order at our shop — no delivery fee.</div>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="fulfillment_type" value="Delivery" id="delivery" onchange="toggleDelivery()">
                    <label class="form-check-label fw-semibold" for="delivery"><i class="bi bi-bicycle me-1"></i>Delivery</label>
                    <div class="text-muted" style="font-size:clamp(.68rem,1.3vw,.72rem)">Your cake will be delivered to your address.</div>
                  </div>
                </div>

                <div id="deliverySection" style="display:none">

                  {{-- ── Perishable Warning ───────────────────────────────── --}}
                  @if(($product->classification ?? '') === 'Perishable')
                  <div class="alert border-0 mb-3" style="background:#fff7ed;border-left:4px solid #f59e0b!important;border-radius:.7rem">
                    <div class="d-flex align-items-start gap-2">
                      <i class="bi bi-thermometer-high mt-1" style="color:#f59e0b;font-size:clamp(.9rem,2.2vw,1.1rem)"></i>
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
                    <div class="form-text mb-1"><i class="bi bi-info-circle me-1"></i>Select your barangay to see the delivery fee and estimated travel time.</div>
                    <select class="form-select cv-field" name="delivery_zone" id="zoneSelect" onchange="updateFee();cvValidateZone();onBarangayChange(this.value)">
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
                                  data-type="{{ $z->zone_type }}"
                                  data-eta="{{ $z->estimated_time ?? '30-45 mins' }}">
                            {{ $z->barangay }}
                            @if($z->fee == 0) (Free)
                            @else — ₱{{ number_format($z->fee, 2) }}
                            @endif
                            — ~{{ $z->estimated_time ?? '30-45 mins' }}
                          </option>
                          @endforeach
                        </optgroup>
                        @endif
                      @endforeach
                    </select>
                    <input type="hidden" name="delivery_fee" id="deliveryFeeInput" value="0">
                    <div class="cv-msg" id="msgZone"></div>

                    {{-- ETA Display --}}
                    <div id="etaDisplay" style="display:none;margin-top:6px" class="d-flex align-items-start gap-2 flex-column">
                      <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-clock" style="color:#0369a1;font-size:clamp(.8rem,1.7vw,.9rem)"></i>
                        <span class="small" style="color:#0369a1">Estimated delivery time: <strong id="etaText"></strong></span>
                      </div>
                      <div class="small text-muted" style="font-size:clamp(.68rem,1.3vw,.72rem);padding-left:1.3rem">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        Note: This is the approximate <strong>travel time</strong> from our shop to your location — not the cake preparation time. This only applies once your order is out for delivery.
                      </div>
                    </div>
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
                    <label class="form-label fw-semibold small">
                      Pin Your Exact Location <span class="text-danger">*</span>
                      <span id="pinBadge" style="background:#fff7ed;color:#b45309;border:1px solid #fed7aa;border-radius:99px;font-size:.68rem;font-weight:700;padding:.1rem .5rem;margin-left:.4rem">
                        <i class="bi bi-cursor-fill me-1"></i>Pin required
                      </span>
                      <span id="pinDoneBadge" style="display:none;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;border-radius:99px;font-size:.68rem;font-weight:700;padding:.1rem .5rem;margin-left:.4rem">
                        <i class="bi bi-check-circle-fill me-1"></i>Pinned!
                      </span>
                    </label>
                    <div class="form-text mb-2"><i class="bi bi-info-circle me-1"></i>Hindi mo alam kung saan ka sa mapa? Pindutin ang <strong>"Hanapin ang Aking Lokasyon"</strong> para awtomatikong ma-pin ang iyong lugar.</div>
                    <div id="mapWrapper" style="position:relative">
                      <div id="map" style="height:300px;border-radius:.9rem;border:2px dashed #f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.15)"></div>

                      {{-- Overlay: shown before pinning --}}
                      <div id="mapOverlay" style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:999;border-radius:.9rem;background:rgba(0,0,0,.18)">
                        <div style="background:#fff;border:2px solid #f59e0b;border-radius:1rem;padding:1rem 1.4rem;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.18);max-width:240px">
                          <div style="font-size:2rem;line-height:1;margin-bottom:.4rem">📍</div>
                          <div style="font-size:.82rem;font-weight:700;color:#92400e;margin-bottom:.6rem">I-pin ang iyong eksaktong lokasyon</div>
                          <button type="button" onclick="useMyLocation()" id="overlayLocBtn"
                                  style="background:#2563eb;color:#fff;border:none;border-radius:.7rem;padding:.55rem 1rem;font-size:.82rem;font-weight:700;cursor:pointer;width:100%;margin-bottom:.5rem;display:flex;align-items:center;justify-content:center;gap:.4rem">
                            <i class="bi bi-geo-alt-fill"></i> Hanapin ang Aking Lokasyon
                          </button>
                          <div style="font-size:.7rem;color:#6b7280">o i-click ang mapa para mag-pin ng mano-mano</div>
                        </div>
                      </div>

                      {{-- Floating GPS button inside map (bottom-right) --}}
                      <button type="button" id="floatGpsBtn" onclick="useMyLocation()"
                              title="Hanapin ang Aking Lokasyon"
                              style="position:absolute;bottom:12px;right:12px;z-index:1000;background:#fff;border:2px solid #2563eb;border-radius:50%;width:42px;height:42px;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.25);transition:background .2s"
                              onmouseover="this.style.background='#eff6ff'" onmouseout="this.style.background='#fff'">
                        <i class="bi bi-crosshair2" style="font-size:1.1rem;color:#2563eb"></i>
                      </button>
                    </div>
                    <div class="cv-msg" id="msgMap"></div>
                    <input type="hidden" name="latitude"  id="lat">
                    <input type="hidden" name="longitude" id="lng">
                  </div>
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
                           min="{{ date('Y-m-d') }}" onchange="cvValidateDate(this);checkCheckoutAvailability()" oninput="cvValidateDate(this)">
                    <div class="cv-msg" id="msgDate"></div>
                    <div class="form-text"><i class="bi bi-info-circle me-1"></i>Minimum 1 day ahead for preparation. You can order for today or any future date.</div>
                    <div id="checkoutAvailability" class="mt-1" style="font-size:.8rem;min-height:18px"></div>
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
                    <div class="form-text"><i class="bi bi-info-circle me-1"></i>Choose your preferred time slot. Please make sure someone is available to receive the order.</div>
                  </div>
                </div>
              </div>
            </div>

            {{-- Add-ons --}}
            @if($addonCategories->count() > 0)
            <div class="card mb-3">
              <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-gift me-2" style="color:var(--primary)"></i>Add-ons <span class="fw-normal text-muted small">(optional)</span></h6>
                @foreach($addonCategories as $cat)
                  @php $catAddons = $addonsByCategory[$cat->id] ?? collect(); @endphp
                  @if($catAddons->count() > 0)
                  <div class="mb-3">
                    <div class="fw-semibold small mb-2" style="color:var(--primary)">
                      @if($cat->icon)<i class="{{ $cat->icon }} me-1"></i>@endif{{ $cat->name }}
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                      @foreach($catAddons as $addon)
                      <label class="addon-card d-flex align-items-center gap-2 px-3 py-2 rounded-3 border"
                             data-price="{{ $addon->price }}"
                             style="cursor:pointer;font-size:.83rem;border-color:#e9ecef!important;transition:all .15s;background:#fff"
                             onmouseover="this.style.borderColor='var(--primary)'"
                             onmouseout="if(!this.querySelector('input').checked)this.style.borderColor='#e9ecef'">
                        <input type="checkbox" class="addon-check form-check-input m-0"
                               name="addons[]" value="{{ $addon->id }}"
                               onchange="onAddonChange(this)">
                        <span>{{ $addon->name }}</span>
                        <span class="fw-bold ms-1" style="color:var(--primary)">+₱{{ number_format($addon->price,2) }}</span>
                      </label>
                      @endforeach
                    </div>
                  </div>
                  @endif
                @endforeach
              </div>
            </div>
            @endif

            {{-- Payment --}}
            <div class="card mb-3">
              <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-credit-card me-2" style="color:var(--primary)"></i>Payment Method</h6>
                <div class="d-flex gap-3 flex-wrap">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="payment_method" value="COD" id="cod" checked onchange="updatePaymentTransparency()">
                    <label class="form-check-label fw-semibold" for="cod">
                      <i class="bi bi-cash-coin me-1"></i><span id="codLabelText">Cash on Pickup (COP)</span>
                    </label>
                    <div class="text-muted" id="codHelpText" style="font-size:clamp(.68rem,1.3vw,.72rem)">Pay cash when you pick up your order.</div>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="payment_method" value="GCash" id="gcash" onchange="updatePaymentTransparency()">
                    <label class="form-check-label fw-semibold" for="gcash">
                      <i class="bi bi-phone me-1"></i>Online Payment via PayMongo
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
              </div>
            </div>

            <input type="hidden" name="service_charge" id="serviceChargeInput" value="0">
            <div id="paymentTransparencyBox" class="mb-3 p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0">
              <div class="small fw-semibold mb-1" id="paymentTransparencyTitle" style="color:#0f172a">
                <i class="bi bi-info-circle me-1"></i>Cash on Delivery selected
              </div>
              <div class="small text-muted mb-1" id="paymentTransparencyText">No online processor fee preview is needed for COD.</div>
              <div class="small" id="paymentTransparencyBreakdown" style="color:#475569"></div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-3 fw-semibold fs-5" id="placeOrderBtn"
                    onclick="if(!cvValidateAll()) return false; const btn=this; const form=this.form; cakeConfirm({title:'Confirm Your Order',message:'Make sure all details are correct before placing.',icon:'bi-bag-check',iconBg:'#dbeafe',iconColor:'#2563eb',okLabel:'Place Order',okColor:'#2563eb',onConfirm:function(){ disablePlaceOrder(btn); form.submit(); }}); return false;">
              <i class="bi bi-bag-check me-2"></i>Place Order
            </button>
          </form>
        </div>

        {{-- RIGHT: Order Summary --}}
        <div class="col-lg-4 col-xl-3">
          <div class="card sticky-top" style="top:80px">
            <div class="card-body p-4">
              <h6 class="fw-bold mb-3">📋 Order Summary</h6>

              {{-- Base price --}}
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
                <span id="totalDisplay" style="color:var(--primary);font-size:clamp(.9rem,2.2vw,1.1rem)">
                  ₱{{ number_format($discountedSubtotal,2) }}
                </span>
              </div>

              {{-- Selected addons list --}}
              <div id="selectedAddonsList" class="mt-3" style="display:none">
                <div class="text-muted small fw-semibold mb-1">Selected Add-ons:</div>
                <div id="selectedAddonsDetail" class="small"></div>
              </div>

              <div class="mt-3 p-2 rounded small text-muted" style="background:#f8f9fa;font-size:clamp(.7rem,1.4vw,.75rem)">
                <i class="bi bi-info-circle me-1"></i>
                Final price includes all selected add-ons and delivery fee (if applicable).
              </div>
              <div class="mt-2 p-2 rounded small" id="summaryPaymentFeeNote" style="background:#fff7ed;color:#9a3412;font-size:clamp(.7rem,1.4vw,.75rem);display:none">
                <i class="bi bi-receipt-cutoff me-1"></i>Estimated processor fee preview: around 3% when paying online.
              </div>
            </div>
          </div>
        </div>

      </div>
</div>

<script>
function checkCheckoutAvailability() {
  const date      = document.getElementById('fieldDate')?.value;
  const shopId    = '{{ $product->shop_id ?? '' }}';
  const resultEl  = document.getElementById('checkoutAvailability');
  if (!date || !resultEl) return;
  resultEl.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split me-1"></i>Checking availability...</span>';
  fetch('/catalog/availability?date=' + date + (shopId ? '&shop_id=' + shopId : ''))
    .then(r => r.json())
    .then(data => {
      if (data.status === 'available') {
        resultEl.innerHTML = '<span class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>' + data.message + '</span>';
      } else if (data.status === 'almost') {
        resultEl.innerHTML = '<span class="text-warning fw-semibold"><i class="bi bi-exclamation-triangle-fill me-1"></i>' + data.message + '</span>';
      } else if (data.status === 'full') {
        resultEl.innerHTML = '<span class="text-danger fw-semibold"><i class="bi bi-x-circle-fill me-1"></i>' + data.message + ' — please choose another date.</span>';
      } else if (data.status === 'invalid') {
        resultEl.innerHTML = '<span class="text-danger small"><i class="bi bi-x-circle me-1"></i>' + data.message + '</span>';
      } else {
        resultEl.innerHTML = '';
      }
    })
    .catch(function() { resultEl.innerHTML = ''; });
}
</script>

@endsection
@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
/* ── Live Validation Styles ─────────────────────────── */
.cv-field { transition: border-color .2s, box-shadow .2s; }
.cv-field.cv-valid   { border-color:#16a34a !important; box-shadow:0 0 0 3px rgba(22,163,74,.15) !important; }
.cv-field.cv-invalid { border-color:#ef4444 !important; box-shadow:0 0 0 3px rgba(239,68,68,.15) !important; }
@keyframes cvShake {
  0%,100%{transform:translateX(0)}
  20%{transform:translateX(-5px)}
  40%{transform:translateX(5px)}
  60%{transform:translateX(-3px)}
  80%{transform:translateX(3px)}
}
.cv-shake { animation: cvShake .35s ease; }
.cv-msg { font-size:.74rem; margin-top:4px; min-height:16px; }
.cv-msg.cv-ok  { color:#16a34a; }
.cv-msg.cv-err { color:#ef4444; }
</style>
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const BASE_PRICE  = {{ $pricing['final_unit_price'] * $checkout['quantity'] }};
const HAS_PRODUCT_DISCOUNT = {{ !empty($pricing['has_discount']) ? 'true' : 'false' }};
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
    summaryEl.innerHTML += '<div class="d-flex justify-content-between small mb-1 text-muted">'
      + '<span><i class="bi bi-check2 me-1" style="color:var(--primary)"></i>' + d.name + '</span>'
      + '<span>' + (d.price > 0 ? '+₱' + d.price.toFixed(2) : 'FREE') + '</span>'
      + '</div>';
  });

  // Update selected addons list
  const listEl = document.getElementById('selectedAddonsList');
  const detailEl = document.getElementById('selectedAddonsDetail');
  if (details.length > 0) {
    listEl.style.display = 'block';
    detailEl.innerHTML = details.map(d =>
      '<div class="d-flex justify-content-between">'
       + '<span>• ' + d.name + '</span>'
       + '<span class="fw-semibold" style="color:var(--primary)">' + (d.price > 0 ? '+₱'+d.price.toFixed(2) : 'FREE') + '</span>'
       + '</div>'
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

function onAddonChange(input) {
  highlightCard(input);
  updateAddonTotal();
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
  updateCashPaymentCopy();
  updateFee();
}

// ── Reverse Geocoding via OpenStreetMap Nominatim ─────────────
async function reverseGeocode(lat, lng) {
  const field = document.getElementById('addressField');
  const indicator = document.getElementById('addressLoading');
  if (indicator) indicator.style.display = 'inline';
  try {
    const res  = await fetch(`/api/geocode/reverse?lat=${lat}&lng=${lng}`);
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

function markPinned() {
  document.getElementById('mapOverlay').style.display = 'none';
  document.getElementById('pinBadge').style.display   = 'none';
  document.getElementById('pinDoneBadge').style.display = 'inline';
  const mapEl = document.getElementById('map');
  mapEl.style.border    = '2px solid #16a34a';
  mapEl.style.boxShadow = '0 0 0 3px rgba(22,163,74,.15)';
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
      autoSelectBarangayFromCoords(ll.lat, ll.lng);
    });
  }
  document.getElementById('lat').value = latlng.lat;
  document.getElementById('lng').value = latlng.lng;
  markPinned();
  reverseGeocode(latlng.lat, latlng.lng);
  autoSelectBarangayFromCoords(latlng.lat, latlng.lng);
}

function initMap() {
  const defaultLat = {{ (float)($shopLat ?? 15.8107127) }};
  const defaultLng = {{ (float)($shopLng ?? 120.4716710) }};
  map = L.map('map').setView([defaultLat, defaultLng], 14);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
  map.on('click', e => setMarkerAt(e.latlng));
}

// ── When barangay selected: pan map to that area ───────────────
async function onBarangayChange(barangayName) {
  if (!barangayName) return;
  if (!map) initMap();
  // Build geocode query: e.g. "Baluyot, Bautista, Pangasinan, Philippines"
  const query = barangayName.replace(/\(.*?\)/g,'').trim() + ', Pangasinan, Philippines';
  try {
    const res  = await fetch('/api/geocode/search?q=' + encodeURIComponent(query));
    const data = await res.json();
    if (data && data[0]) {
      const lat = parseFloat(data[0].lat);
      const lng = parseFloat(data[0].lon);
      map.setView([lat, lng], 15);
      // Pulse overlay to tell user to click
      const overlay = document.getElementById('mapOverlay');
      if (!document.getElementById('lat').value) {
        overlay.style.display = 'flex';
        cakeToast('📍 Map moved to ' + barangayName + ' — now click your exact house location!', 'success');
      }
    }
  } catch (e) {
    // Silent fail — map stays at current view
  }
}

// ── Use My Current Location ─────────────────────────────────────────────
function useMyLocation() {
  if (!map) initMap();

  if (!navigator.geolocation) {
    cakeToast('Hindi sinusuportahan ng browser mo ang GPS. I-click ang mapa para mag-pin ng mano-mano.', 'error');
    return;
  }

  // Disable all GPS buttons and show loading state
  const overlayBtn = document.getElementById('overlayLocBtn');
  const floatBtn   = document.getElementById('floatGpsBtn');
  if (overlayBtn) { overlayBtn.disabled = true; overlayBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Hinahanap…'; }
  if (floatBtn)   { floatBtn.disabled = true; floatBtn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:.9rem;height:.9rem;border-width:2px"></span>'; }

  navigator.geolocation.getCurrentPosition(
    async (pos) => {
      const lat = pos.coords.latitude;
      const lng = pos.coords.longitude;

      map.setView([lat, lng], 17);
      setMarkerAt({ lat, lng });
      // autoSelectBarangayFromCoords is called inside setMarkerAt

      if (overlayBtn) { overlayBtn.disabled = false; overlayBtn.innerHTML = '<i class="bi bi-geo-alt-fill"></i> Hanapin ang Aking Lokasyon'; }
      if (floatBtn)   { floatBtn.disabled = false; floatBtn.innerHTML = '<i class="bi bi-crosshair2" style="font-size:1.1rem;color:#2563eb"></i>'; }
      cakeToast('📍 Nahanap! Ang iyong lokasyon ay na-pin na sa mapa.', 'success');
    },
    (err) => {
      if (overlayBtn) { overlayBtn.disabled = false; overlayBtn.innerHTML = '<i class="bi bi-geo-alt-fill"></i> Hanapin ang Aking Lokasyon'; }
      if (floatBtn)   { floatBtn.disabled = false; floatBtn.innerHTML = '<i class="bi bi-crosshair2" style="font-size:1.1rem;color:#2563eb"></i>'; }
      const msgs = {
        1: 'Hindi pinahintulutan ang lokasyon. Pakibigyan ng access ang browser, o i-click ang mapa para mag-pin ng mano-mano.',
        2: 'Hindi makuha ang iyong lokasyon. Subukan ulit.',
        3: 'Nag-time out ang paghahanap ng lokasyon. Subukan ulit.',
      };
      cakeToast(msgs[err.code] || 'Hindi makuha ang lokasyon. I-click ang mapa para mag-pin.', 'error');
    },
    { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
  );
}

// ── Auto-select barangay from pinned coords via Nominatim ──────────────
async function autoSelectBarangayFromCoords(lat, lng) {
  try {
    const res  = await fetch(`/api/geocode/reverse?lat=${lat}&lng=${lng}`);
    const data = await res.json();
    if (!data || !data.address) return;

    const a = data.address;
    // Collect all candidate name fields Nominatim might return for a barangay
    const candidates = [
      a.suburb, a.village, a.neighbourhood, a.hamlet, a.quarter,
      a.city_district, a.county, a.city, a.town, a.municipality,
    ].filter(Boolean).map(s => s.toLowerCase().trim());

    const sel = document.getElementById('zoneSelect');
    if (!sel) return;

    let bestIdx   = -1;
    let bestScore = 0;

    for (let i = 0; i < sel.options.length; i++) {
      const raw = sel.options[i].value;
      if (!raw) continue;
      // Strip municipality suffix for comparison ("Baluyot, Bautista" → "baluyot")
      const optCore = raw.split(',')[0].replace(/\(.*?\)/g,'').toLowerCase().trim();

      for (const cand of candidates) {
        if (!cand) continue;
        // Score: exact contains = high, word overlap = medium
        let score = 0;
        if (optCore === cand) score = 1.0;
        else if (optCore.includes(cand) || cand.includes(optCore)) {
          score = Math.min(optCore.length, cand.length) / Math.max(optCore.length, cand.length);
        } else {
          // Word overlap
          const optWords  = optCore.split(/\s+/);
          const candWords = cand.split(/\s+/);
          const shared    = optWords.filter(w => candWords.includes(w) && w.length > 2);
          if (shared.length > 0) score = shared.length / Math.max(optWords.length, candWords.length) * 0.7;
        }
        if (score > bestScore) { bestScore = score; bestIdx = i; }
      }
    }

    if (bestIdx >= 0 && bestScore >= 0.45) {
      sel.selectedIndex = bestIdx;
      updateFee();
      cvValidateZone();
      cakeToast('✅ Barangay detected: ' + sel.options[bestIdx].value, 'success');
    } else {
      cakeToast('⚠️ Could not detect barangay — please select it from the list above.', 'warn');
    }
  } catch (e) {
    // Silent fail — user selects manually
  }
}

function updateFee() {
  const sel      = document.getElementById('zoneSelect');
  const opt      = sel?.options[sel.selectedIndex];
  const zoneType = opt?.dataset?.type || '';
  deliveryFee    = parseFloat(opt?.dataset?.fee || 0);
  const isDelivery = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';

  // ETA display
  const eta = opt?.dataset?.eta || '';
  const etaEl = document.getElementById('etaDisplay');
  const etaTxt = document.getElementById('etaText');
  if (etaEl && eta && opt && opt.value) {
    etaTxt.textContent = eta;
    etaEl.style.display = 'flex';
  } else if (etaEl) {
    etaEl.style.display = 'none';
  }

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

// ── Guest OTP ───────────────────────────────────────────────────────────
function sendGuestOtp() {
  const phone = document.getElementById('guestPhone').value.trim();
  if (!phone) { cakeToast('Please enter your phone number first.','error'); return; }

  const btn = document.getElementById('sendOtpBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending...';

  const fd = new FormData();
  fd.append('_token', '{{ csrf_token() }}');
  fd.append('phone', phone);

  fetch('{{ route("guest.checkout.send_otp") }}', { method:'POST', body:fd })
    .then(r => r.text())
    .then(text => {
      let data;
      try { data = JSON.parse(text); } catch(e) {
        cakeToast('Server error — check Laravel log.', 'error');
        console.error('Non-JSON response:', text.substring(0, 500));
        btn.innerHTML = '<i class="bi bi-phone me-1"></i>Send OTP';
        btn.disabled = false;
        return;
      }
      if (!data.ok) {
        cakeToast(data.error || 'Failed to send OTP.', 'error');
        btn.innerHTML = '<i class="bi bi-phone me-1"></i>Send OTP';
        btn.disabled = false;
        return;
      }
      document.getElementById('otpSection').style.display = 'block';
      document.querySelector('[name="otp_code"]').required = true;
      cakeToast('✅ OTP sent! Check your SMS.', 'success');
      btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Resend';
      btn.disabled = false;
      if (data.dev) renderDevOtpHint(data.dev);
    })
    .catch(err => {
      console.error('Fetch error:', err);
      btn.innerHTML = '<i class="bi bi-phone me-1"></i>Send OTP';
      btn.disabled = false;
      cakeToast('Network error. Try again.', 'error');
    });
}

function renderDevOtpHint(dev) {
  var wrap = document.getElementById('devOtpHintAjax');
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
            '<div id="devOtpCodeAjax" style="font-size:1.7rem;font-weight:800;letter-spacing:.22em;color:#ea580c;font-family:monospace;line-height:1;cursor:pointer" onclick="devCopyOtpAjax()" title="Click to copy">' + esc(dev.otp) + '</div>' +
          '</div>' +
          '<button onclick="devCopyOtpAjax()" style="flex-shrink:0;background:linear-gradient(135deg,#ea580c,#c2410c);color:#fff;border:none;border-radius:8px;padding:.45rem .9rem;font-size:.75rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:.35rem">' +
            '<i class="bi bi-copy" id="devCopyIconAjax"></i><span id="devCopyLabelAjax">Copy OTP</span>' +
          '</button>' +
        '</div>' +
        '<div style="display:flex;flex-wrap:wrap;gap:.3rem 1.2rem;margin-top:.65rem;font-size:.72rem;color:#92400e">' +
          '<span><i class="bi bi-telephone-fill" style="font-size:.65rem;margin-right:.25rem"></i>+' + esc(dev.phone) + '</span>' +
        '</div>' +
      '</div>' +
    '</div>';
}

function devCopyOtpAjax() {
  var code = document.getElementById('devOtpCodeAjax');
  var label = document.getElementById('devCopyLabelAjax');
  var icon  = document.getElementById('devCopyIconAjax');
  if (!code) return;
  navigator.clipboard.writeText(code.textContent.trim()).then(function() {
    label.textContent = 'Copied!'; icon.className = 'bi bi-check-lg';
    setTimeout(function(){ label.textContent = 'Copy OTP'; icon.className = 'bi bi-copy'; }, 2000);
  }).catch(function(){
    var r = document.createRange(); r.selectNodeContents(code);
    window.getSelection().removeAllRanges(); window.getSelection().addRange(r);
    document.execCommand('copy'); window.getSelection().removeAllRanges();
    label.textContent = 'Copied!'; icon.className = 'bi bi-check-lg';
    setTimeout(function(){ label.textContent = 'Copy OTP'; icon.className = 'bi bi-copy'; }, 2000);
  });
}
</script>
<script>
// ══════════════════════════════════════════════════════
// LIVE CHECKOUT VALIDATION
// ══════════════════════════════════════════════════════

const cvRules = {
  name:  { pattern: /^[A-Za-zÀ-ÿ\s.\-']+$/, min: 2, max: 120, errChar: 'Letters and spaces only — no numbers or symbols.' },
  phone: { pattern: /^[0-9+]+$/,             min: 0, max: 15,  errChar: 'Numbers only.' },
  otp:   { pattern: /^[0-9]+$/,              min: 0, max: 6,   errChar: 'Numbers only.' },
};

// ── Set input visual state ────────────────────────────
function cvSetState(input, msgId, state, text = '') {
  input.classList.remove('cv-valid','cv-invalid');
  const msg = document.getElementById(msgId);
  if (msg) { msg.className = 'cv-msg'; msg.textContent = ''; }
  if (state === 'ok') {
    input.classList.add('cv-valid');
    if (msg) { msg.classList.add('cv-ok'); msg.textContent = text; }
  } else if (state === 'err') {
    input.classList.add('cv-invalid');
    if (msg) { msg.classList.add('cv-err'); msg.textContent = text; }
  }
}

// ── Shake animation ───────────────────────────────────
function cvShake(el) {
  el.classList.remove('cv-shake');
  void el.offsetWidth;
  el.classList.add('cv-shake');
  // Also vibrate on mobile
  if (navigator.vibrate) navigator.vibrate([60, 30, 60]);
  setTimeout(() => el.classList.remove('cv-shake'), 400);
}

// ── Block invalid keypress ────────────────────────────
function cvBlockChar(event, ruleKey) {
  const rule = cvRules[ruleKey];
  if (!rule || event.key.length > 1) return;
  if (!rule.pattern.test(event.key)) {
    event.preventDefault();
    cvShake(event.target);
    const msgId = 'msg' + event.target.id.replace('field','').replace('guestPhone','Phone');
    const msg = document.getElementById(msgId) || event.target.nextElementSibling;
    if (msg) { msg.className = 'cv-msg cv-err'; msg.textContent = rule.errChar; }
    setTimeout(() => cvValidate(event.target), 700);
  }
}

// ── Block paste of invalid content ───────────────────
document.addEventListener('paste', function(e) {
  const input = e.target;
  const ruleKey = input.dataset.cvRule;
  if (!ruleKey) return;
  const rule = cvRules[ruleKey];
  if (!rule) return;
  const pasted = (e.clipboardData || window.clipboardData).getData('text');
  if (!rule.pattern.test(pasted.trim())) {
    e.preventDefault();
    cvShake(input);
  }
});

// ── Validate Full Name ────────────────────────────────
function cvValidate(input) {
  const val = input.value.trim();
  const id  = input.id;
  let msgId = '';
  let ruleKey = '';

  if (id === 'fieldName')  { msgId = 'msgName';  ruleKey = 'name'; }
  if (id === 'guestPhone') { msgId = 'msgPhone'; ruleKey = 'phone'; }

  const rule = cvRules[ruleKey];
  if (!rule) return;

  if (!val) {
    cvSetState(input, msgId, 'err', input.required ? 'This field is required.' : '');
    return;
  }
  if (!rule.pattern.test(val)) {
    cvShake(input);
    cvSetState(input, msgId, 'err', rule.errChar);
    return;
  }
  if (val.length < rule.min) {
    cvSetState(input, msgId, 'err', `Too short — minimum ${rule.min} characters.`);
    return;
  }

  // Phone-specific format check
  if (ruleKey === 'phone') {
    const digits = val.replace(/\D/g,'');
    if (val.startsWith('+63') && digits.length !== 12) {
      cvSetState(input, msgId, 'err', 'Must be +63 followed by 10 digits (e.g. +639XXXXXXXXX).');
      return;
    }
    if (val.startsWith('09') && digits.length !== 11) {
      cvSetState(input, msgId, 'err', 'Must be 11 digits starting with 09 (e.g. 09XXXXXXXXX).');
      return;
    }
    if (!val.startsWith('09') && !val.startsWith('+63')) {
      cvSetState(input, msgId, 'err', 'Phone must start with 09 or +63.');
      return;
    }
    cvSetState(input, msgId, 'ok', '✓ Valid phone number.');
    return;
  }

  cvSetState(input, msgId, 'ok', '✓ Looks good!');
}

// ── Validate OTP (with counter) ───────────────────────
function cvValidateOtp(input) {
  const val = input.value.replace(/\D/g,'');
  input.value = val; // strip non-numbers
  const msgEl = document.getElementById('msgOtp');
  input.classList.remove('cv-valid','cv-invalid');
  if (msgEl) { msgEl.className = 'cv-msg'; msgEl.textContent = ''; }

  if (!val) return;
  if (val.length < 6) {
    input.classList.add('cv-invalid');
    if (msgEl) { msgEl.classList.add('cv-err'); msgEl.textContent = `${val.length}/6 digits entered.`; }
  } else {
    input.classList.add('cv-valid');
    if (msgEl) { msgEl.classList.add('cv-ok'); msgEl.textContent = '✓ OTP complete.'; }
  }
}

// ── Validate Barangay ─────────────────────────────────
function cvValidateZone() {
  const sel = document.getElementById('zoneSelect');
  const msg = document.getElementById('msgZone');
  if (!sel || !msg) return;
  sel.classList.remove('cv-valid','cv-invalid');
  msg.className = 'cv-msg';

  if (!sel.value) {
    sel.classList.add('cv-invalid');
    msg.classList.add('cv-err');
    msg.textContent = 'Please select your barangay.';
    cvShake(sel);
  } else {
    sel.classList.add('cv-valid');
    msg.classList.add('cv-ok');
    msg.textContent = '✓ Barangay selected.';
  }
}

// ── Validate Date ─────────────────────────────────────
function cvValidateDate(input) {
  const val   = input.value;
  const msg   = document.getElementById('msgDate');
  input.classList.remove('cv-valid','cv-invalid');
  if (msg) { msg.className = 'cv-msg'; msg.textContent = ''; }

  if (!val) {
    // Optional field — just clear state
    return;
  }

  const selected = new Date(val);
  const today    = new Date();
  today.setHours(0,0,0,0);

  if (isNaN(selected.getTime())) {
    input.classList.add('cv-invalid');
    cvShake(input);
    if (msg) { msg.classList.add('cv-err'); msg.textContent = 'Invalid date. Please select a valid date.'; }
    return;
  }

  if (selected < today) {
    input.classList.add('cv-invalid');
    cvShake(input);
    if (msg) { msg.classList.add('cv-err'); msg.textContent = 'Cannot select a past date. Please choose today or a future date.'; }
    return;
  }

  // Recommended: at least 1 day ahead
  const tomorrow = new Date(today);
  tomorrow.setDate(today.getDate() + 1);

  if (selected < tomorrow) {
    // Today is selected — warn but allow
    input.classList.add('cv-valid');
    if (msg) { msg.classList.add('cv-ok'); msg.textContent = '✓ Date set. Note: Same-day orders depend on availability.'; }
    return;
  }

  input.classList.add('cv-valid');
  if (msg) {
    msg.classList.add('cv-ok');
    const days = Math.round((selected - today) / (1000*60*60*24));
    msg.textContent = `✓ ${days} day${days > 1 ? 's' : ''} from today.`;
  }
}

// ── Validate Map Pin ──────────────────────────────────
function cvValidateMap() {
  const lat   = document.getElementById('lat')?.value;
  const lng   = document.getElementById('lng')?.value;
  const mapEl = document.getElementById('map');
  const msg   = document.getElementById('msgMap');
  if (!mapEl || !msg) return true;

  if (!lat || !lng || lat === '' || lng === '') {
    mapEl.style.border    = '2px solid #ef4444';
    mapEl.style.boxShadow = '0 0 0 3px rgba(239,68,68,.2)';
    // Show overlay again as reminder
    const overlay = document.getElementById('mapOverlay');
    if (overlay) overlay.style.display = 'flex';
    msg.className   = 'cv-msg cv-err';
    msg.textContent = 'You must pin your exact location on the map before placing the order.';
    return false;
  }
  mapEl.style.border    = '2px solid #16a34a';
  mapEl.style.boxShadow = '0 0 0 3px rgba(22,163,74,.15)';
  msg.className   = 'cv-msg cv-ok';
  msg.textContent = '✓ Location pinned.';
  return true;
}

// ── Full form validation before submit ────────────────
function cvValidateAll() {
  const isDelivery = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';
  let ok = true;
  let firstErr = null;

  // Name
  const nameEl = document.getElementById('fieldName');
  if (nameEl) {
    cvValidate(nameEl);
    if (nameEl.classList.contains('cv-invalid') || !nameEl.value.trim()) {
      cvSetState(nameEl, 'msgName', 'err', 'Full name is required.');
      cvShake(nameEl);
      ok = false; firstErr = firstErr || nameEl;
    }
  }

  // Phone
  const phoneEl = document.getElementById('guestPhone');
  if (phoneEl) {
    cvValidate(phoneEl);
    if (phoneEl.classList.contains('cv-invalid') || !phoneEl.value.trim()) {
      cvSetState(phoneEl, 'msgPhone', 'err', 'Phone number is required.');
      cvShake(phoneEl);
      ok = false; firstErr = firstErr || phoneEl;
    }
  }

  // OTP (if shown)
  const otpSection = document.getElementById('otpSection');
  const otpEl = document.getElementById('fieldOtp');
  if (otpSection && otpSection.style.display !== 'none' && otpEl) {
    if (otpEl.value.length !== 6) {
      otpEl.classList.add('cv-invalid');
      const m = document.getElementById('msgOtp');
      if (m) { m.className='cv-msg cv-err'; m.textContent='Please enter the complete 6-digit OTP.'; }
      cvShake(otpEl);
      ok = false; firstErr = firstErr || otpEl;
    }
  }

  if (isDelivery) {
    // Barangay
    const zoneEl = document.getElementById('zoneSelect');
    if (zoneEl && !zoneEl.value) {
      cvValidateZone();
      ok = false; firstErr = firstErr || zoneEl;
    }
    // Map pin
    if (!cvValidateMap()) {
      ok = false;
      const mapEl = document.getElementById('map');
      firstErr = firstErr || mapEl;
    }
  }

  if (!ok && firstErr) {
    firstErr.scrollIntoView({ behavior:'smooth', block:'center' });
  }

  return ok;
}

// Update map pin validation when marker set
const _origSetMarkerAt = setMarkerAt;
window.setMarkerAt = function(latlng) {
  _origSetMarkerAt(latlng);
  setTimeout(cvValidateMap, 300);
};
</script>
<script>
function updatePaymentTransparency(currentTotal) {
  const total = typeof currentTotal === 'number'
    ? currentTotal
    : (() => {
        const isDelivery = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';
        const serviceCharge = parseFloat(document.getElementById('serviceChargeInput')?.value || 0);
        return BASE_PRICE + getCurrentAddonTotal() + (isDelivery ? deliveryFee + serviceCharge : 0);
      })();
  const method = document.querySelector('[name="payment_method"]:checked')?.value || 'COD';
  const titleEl = document.getElementById('paymentTransparencyTitle');
  const textEl = document.getElementById('paymentTransparencyText');
  const breakdownEl = document.getElementById('paymentTransparencyBreakdown');
  const summaryNote = document.getElementById('summaryPaymentFeeNote');
  updateCashPaymentCopy();
  if (!titleEl || !textEl || !breakdownEl) return;

  if (method === 'GCash') {
    const estimatedFee = total * 0.03;
    const estimatedNet = total - estimatedFee;
    titleEl.innerHTML = '<i class="bi bi-shield-check me-1"></i>PayMongo transparency preview';
    titleEl.style.color = '#166534';
    textEl.textContent = 'To keep the payment transparent, we are showing the estimated 3% processor deduction before you continue.';
    breakdownEl.innerHTML = 'Customer pays <strong>PHP ' + total.toFixed(2) + '</strong> &bull; Estimated processor fee <strong>~ PHP ' + estimatedFee.toFixed(2) + '</strong> &bull; Estimated net after fee <strong>~ PHP ' + estimatedNet.toFixed(2) + '</strong>';
    if (summaryNote) summaryNote.style.display = 'block';
  } else {
    const isDelivery = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';
    titleEl.innerHTML = '<i class="bi bi-info-circle me-1"></i>' + (isDelivery ? 'Cash on Delivery selected' : 'Cash on Pickup selected');
    titleEl.style.color = '#0f172a';
    textEl.textContent = isDelivery
      ? 'No online processor fee preview is needed for Cash on Delivery.'
      : 'No online processor fee preview is needed for Cash on Pickup.';
    breakdownEl.textContent = '';
    if (summaryNote) summaryNote.style.display = 'none';
  }
}

function updateCashPaymentCopy() {
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

const _origUpdateTotal = updateTotal;
window.updateTotal = function(addonTotal) {
  _origUpdateTotal(addonTotal);
  const isDelivery = document.querySelector('[name=fulfillment_type]:checked')?.value === 'Delivery';
  const serviceCharge = parseFloat(document.getElementById('serviceChargeInput')?.value || 0);
  const total = BASE_PRICE + (addonTotal ?? getCurrentAddonTotal()) + (isDelivery ? deliveryFee + serviceCharge : 0);
  updatePaymentTransparency(total);
};

document.addEventListener('DOMContentLoaded', function() {
  if (HAS_PRODUCT_DISCOUNT) {
    const basePriceEl = document.getElementById('basePrice');
    if (basePriceEl) basePriceEl.style.color = '#dc2626';
  }
  updatePaymentTransparency();
});
</script>
