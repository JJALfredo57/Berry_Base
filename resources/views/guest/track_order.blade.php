@extends('layouts.app')
@section('content')
<div class="container-fluid py-4" style="padding-left:clamp(12px,3vw,32px);padding-right:clamp(12px,3vw,32px)">

  @if(session('msg'))
    <div class="alert alert-success border-0"><i class="bi bi-check-circle me-2"></i>{{ session('msg') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger border-0"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
  @endif
  <style>
    @keyframes depositShake {
      0%, 100% { transform: translateX(0); }
      20%, 60% { transform: translateX(-6px); }
      40%, 80% { transform: translateX(6px); }
    }
    .deposit-amount-input.is-invalid {
      border-color: #dc2626 !important;
      box-shadow: 0 0 0 .18rem rgba(220, 38, 38, .12) !important;
    }
    .deposit-error {
      display: none;
      color: #dc2626;
      font-size: .72rem;
      font-weight: 700;
      margin-top: .35rem;
    }
    .deposit-error.show {
      display: block;
      animation: depositShake .32s ease;
    }
  </style>

  {{-- Header --}}
  <div class="text-center mb-4">
    <div style="font-size:3rem">🎂</div>
    <h4 class="fw-bold mb-1" style="color:var(--primary)">Order Tracking</h4>
    <div class="text-muted small">Order #{{ $order->id }}</div>
  </div>

  {{-- Status Badge --}}
  @php
    $isPickup = $order->fulfillment_type === 'Pickup';
    $hasDepositLock = ($order->deposit_status ?? null) === 'paid' || in_array(($order->payment_status ?? ''), ['Partial Payment', 'Paid']);
    $canRequestCancel = in_array($order->status, ['Pending', 'Pending Review', 'Confirmed']) && !$hasDepositLock;
    $hasPendingCancel = ($order->cancel_requested ?? 0) && ($order->cancel_status ?? '') === 'pending';
    $cancelApproved = ($order->cancel_status ?? '') === 'accepted';
    $cancelRejected = ($order->cancel_status ?? '') === 'rejected';
    $statusColors = [
      'Pending'          => ['bg'=>'#fff3cd','color'=>'#856404','icon'=>'bi-hourglass-split'],
      'Pending Review'   => ['bg'=>'#fff3cd','color'=>'#856404','icon'=>'bi-hourglass-split'],
      'Confirmed'        => ['bg'=>'#d1fae5','color'=>'#065f46','icon'=>'bi-check-circle-fill'],
      'Preparing'        => ['bg'=>'#fef3c7','color'=>'#92400e','icon'=>'bi-fire'],
      'Out for Delivery' => ['bg'=>'#dbeafe','color'=>'#1e40af','icon'=>'bi-truck'],
      'Pickup'           => ['bg'=>'#ede9fe','color'=>'#5b21b6','icon'=>'bi-shop'],
      'Delivered'        => ['bg'=>'#d1fae5','color'=>'#065f46','icon'=>'bi-house-check-fill'],
      'Picked Up'        => ['bg'=>'#d1fae5','color'=>'#065f46','icon'=>'bi-bag-check-fill'],
      'Cancelled'        => ['bg'=>'#fee2e2','color'=>'#991b1b','icon'=>'bi-x-circle-fill'],
    ];
    $sc = $statusColors[$order->status] ?? $statusColors['Pending'];
  @endphp

  <div class="text-center mb-4">
    <span class="badge px-4 py-3" style="background:{{ $sc['bg'] }};color:{{ $sc['color'] }};font-size:1rem;border-radius:2rem">
      <i class="bi {{ $sc['icon'] }} me-2"></i>{{ $order->status }}
    </span>
    @if($order->status === 'Out for Delivery')
    @php
      $zone = DB::table('delivery_zones')->where('barangay', $order->delivery_zone)->first();
      $eta  = $zone->estimated_time ?? null;
    @endphp
    @if($eta)
    <div class="mt-2">
      <span class="badge px-3 py-2" style="background:#dbeafe;color:#1e40af;border-radius:1rem;font-size:clamp(.74rem,1.5vw,.8rem)">
        <i class="bi bi-clock me-1"></i>Estimated arrival: ~{{ $eta }}
      </span>
    </div>
    @endif
    @endif
    @if($isPickup && $order->status === 'Out for Delivery')
    <div class="mt-2">
      <span class="badge px-3 py-2" style="background:#fef9c3;color:#854d0e;border-radius:1rem;font-size:clamp(.74rem,1.5vw,.8rem)">
        <i class="bi bi-info-circle me-1"></i>Your order is ready — please wait for the shop to contact you for pickup details.
      </span>
    </div>
    @endif
  </div>

  @if($hasPendingCancel)
  <div class="alert border-0 mb-4" style="background:#fff3cd;color:#854d0e">
    <i class="bi bi-hourglass-split me-2"></i>Cancellation request pending. Waiting for admin approval.
  </div>
  @elseif($cancelApproved)
  <div class="alert border-0 mb-4" style="background:#dcfce7;color:#166534">
    <i class="bi bi-check-circle me-2"></i>Cancellation request approved.
    @if($order->cancel_admin_note) {{ $order->cancel_admin_note }} @endif
  </div>
  @elseif($cancelRejected)
  <div class="alert border-0 mb-4" style="background:#fee2e2;color:#991b1b">
    <i class="bi bi-x-circle me-2"></i>Cancellation request rejected.
    @if($order->cancel_admin_note) Reason: {{ $order->cancel_admin_note }} @endif
  </div>
  @elseif($hasDepositLock && $order->status !== 'Cancelled')
  <div class="alert border-0 mb-4" style="background:#eff6ff;color:#1d4ed8">
    <i class="bi bi-shield-lock me-2"></i>Cancellation locked because the deposit has already been paid.
  </div>
  @endif

  {{-- Progress Bar (non-cancelled) --}}
  @if($order->status !== 'Cancelled')
  @php
    if ($isPickup) {
      $steps = ['Pending'=>'Received','Confirmed'=>'Confirmed','Preparing'=>'Baking','Pickup'=>'Ready','Picked Up'=>'Picked Up'];
    } else {
      $steps = ['Pending'=>'Received','Confirmed'=>'Confirmed','Preparing'=>'Baking','Out for Delivery'=>'On the Way','Delivered'=>'Delivered'];
    }
    $stepKeys = array_keys($steps);

    // Map mismatched statuses — e.g. Pickup order that was wrongly set to "Out for Delivery"
    $statusForProgress = $order->status;
    if ($isPickup && $statusForProgress === 'Out for Delivery') $statusForProgress = 'Preparing';
    if (!$isPickup && $statusForProgress === 'Pickup')         $statusForProgress = 'Preparing';
    if (!$isPickup && $statusForProgress === 'Picked Up')      $statusForProgress = 'Delivered';

    $current = array_search($statusForProgress, $stepKeys);
    if ($current === false) $current = 0;
    $n = count($steps);
    $progressWidth = max(0, round(($current * 100 / $n) + (50 / $n) - 8));
  @endphp
  <div class="card mb-4">
    <div class="card-body p-3">
      <div class="d-flex justify-content-between position-relative" style="padding:0 8px">
        {{-- Line --}}
        <div style="position:absolute;top:16px;left:8%;right:8%;height:3px;background:#e9ecef;z-index:0"></div>
        <div style="position:absolute;top:16px;left:8%;height:3px;background:var(--primary);z-index:1;width:{{ $progressWidth }}%;transition:width .6s ease"></div>
        @foreach($steps as $key => $label)
        @php $idx = array_search($key, $stepKeys); $done = $idx <= $current; @endphp
        <div class="text-center" style="z-index:2;flex:1">
          <div class="mx-auto d-flex align-items-center justify-content-center"
               style="width:32px;height:32px;border-radius:50%;border:3px solid {{ $done ? 'var(--primary)' : '#e9ecef' }};background:{{ $done ? 'var(--primary)' : '#fff' }};transition:all .3s">
            @if($done)
              <i class="bi bi-check text-white" style="font-size:clamp(.8rem,1.7vw,.9rem)"></i>
            @else
              <span style="width:8px;height:8px;border-radius:50%;background:#dee2e6;display:block"></span>
            @endif
          </div>
          <div class="mt-1" style="font-size:clamp(.62rem,1.2vw,.65rem);color:{{ $done ? 'var(--primary)' : '#9ca3af' }};font-weight:{{ $done ? '600' : '400' }}">
            {{ $label }}
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>
  @endif

  {{-- Order Details --}}
  <div class="card mb-3">
    <div class="card-body p-4">
      <h6 class="fw-bold mb-3"><i class="bi bi-receipt me-2" style="color:var(--primary)"></i>Order Details</h6>
      <div class="d-flex align-items-center gap-3 mb-3">
        <img src="{{ $order->image_path ?? '/storage/uploads/products/default.png' }}"
             style="width:64px;height:64px;object-fit:cover;border-radius:.7rem"
             onerror="this.src='https://placehold.co/64x64/fce4ec/e91e63?text=🎂'">
        <div>
          <div class="fw-bold">{{ $order->product_name }}</div>
          <div class="text-muted small">Qty: {{ $order->quantity }}
            @if($order->selected_size) &bull; {{ $order->selected_size }} @endif
          </div>
          @if(!empty($order->discount_type) && (float)($order->discount_amount ?? 0) > 0)
            <div class="small mt-1" style="color:#c2410c">
              <i class="bi bi-tags me-1"></i>{{ \App\Helpers\CakeshopHelper::discountBadgeText($order->discount_type, $order->discount_value) ?? 'Product Discount' }}
              @if(!empty($order->discount_label))
                <span class="text-muted">({{ $order->discount_label }})</span>
              @endif
            </div>
          @endif
          @if($order->custom_note)
            <div class="small text-muted mt-1"><i class="bi bi-chat-left-text me-1"></i>{{ $order->custom_note }}</div>
          @endif
        </div>
      </div>

      {{-- Add-ons --}}
      @if(count($addons) > 0)
      <div class="mb-3">
        <div class="small fw-semibold mb-1">Add-ons:</div>
        <div class="d-flex flex-wrap gap-1">
          @foreach($addons as $a)
            <span class="badge" style="background:#f0fdf4;color:#166534;font-size:clamp(.68rem,1.3vw,.72rem)">{{ $a->addon_name }}</span>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Info Grid --}}
      <div class="row g-2 small">
        <div class="col-sm-6">
          <div class="p-2 rounded-2" style="background:#f8f9fa">
            <div class="text-muted" style="font-size:.68rem;text-transform:uppercase">Name</div>
            <div class="fw-semibold">{{ $order->guest_name }}</div>
          </div>
        </div>
        <div class="col-sm-6">
          <div class="p-2 rounded-2" style="background:#f8f9fa">
            <div class="text-muted" style="font-size:.68rem;text-transform:uppercase">Fulfillment</div>
            <div class="fw-semibold">
              <i class="bi bi-{{ $order->fulfillment_type === 'Delivery' ? 'truck' : 'shop' }} me-1"></i>
              {{ $order->fulfillment_type }}
            </div>
          </div>
        </div>
        @if($order->schedule_date)
        <div class="col-sm-6">
          <div class="p-2 rounded-2" style="background:#f8f9fa">
            <div class="text-muted" style="font-size:.68rem;text-transform:uppercase">Schedule</div>
            <div class="fw-semibold">{{ \Carbon\Carbon::parse($order->schedule_date)->format('M d, Y') }}</div>
          </div>
        </div>
        @endif
        <div class="col-sm-6">
          <div class="p-2 rounded-2" style="background:#f8f9fa">
            <div class="text-muted" style="font-size:.68rem;text-transform:uppercase">Payment</div>
            <div class="fw-semibold">{{ \App\Helpers\CakeshopHelper::displayPaymentMethod($order->payment_method, $order->fulfillment_type) }}
              <span class="badge ms-1 {{ $order->payment_status === 'Paid' ? 'bg-success' : ($order->payment_status === 'Partial Payment' ? 'bg-primary' : 'bg-warning text-dark') }}">
                {{ $order->payment_status }}
              </span>
            </div>
          </div>
        </div>
        <div class="col-12">
          <div class="p-2 rounded-2" style="background:#fff0f5">
            <div class="text-muted" style="font-size:.68rem;text-transform:uppercase">Total Amount</div>
            <div class="fw-bold" style="color:var(--primary);font-size:clamp(.9rem,2.2vw,1.1rem)">₱{{ number_format($order->total_price, 2) }}</div>
            @if($order->deposit_required && $order->deposit_status === 'paid')
            <div class="mt-1" style="font-size:clamp(.7rem,1.4vw,.75rem)">
              <span style="color:#16a34a"><i class="bi bi-check-circle-fill me-1"></i>Deposit paid: ₱{{ number_format($order->deposit_amount, 2) }}</span>
              <span class="ms-2" style="color:#9a3412">Remaining: <strong>₱{{ number_format($order->total_price - $order->deposit_amount, 2) }}</strong></span>
            </div>
            @elseif($order->deposit_required && $order->deposit_status === 'pending')
            <div class="mt-1" style="font-size:clamp(.7rem,1.4vw,.75rem);color:#9a3412">
              <i class="bi bi-clock me-1"></i>Deposit of ₱{{ number_format($order->deposit_amount, 2) }} pending
            </div>
            @endif
          </div>
        </div>

        {{-- GCash Pay Button — only at correct status per fulfillment type --}}
        @if($order->payment_method === 'GCash' && $order->payment_status !== 'Paid')
          @php
            $showPayBtn  = ($isPickup && $order->status === 'Pickup')
                        || (!$isPickup && $order->status === 'Out for Delivery');
            $depositPaid = $order->deposit_status === 'paid';
            $remainingAmt = $depositPaid
              ? max(0, (float)$order->total_price - (float)$order->deposit_amount)
              : (float)$order->total_price;
            if ($isPickup) {
              $btnLabel = $depositPaid
                ? 'Pay Remaining Balance ₱' . number_format($remainingAmt, 2) . ' via GCash'
                : 'Pay ₱' . number_format($remainingAmt, 2) . ' via GCash — Ready for Pickup!';
            } else {
              $btnLabel = $depositPaid
                ? 'Pay Remaining Balance ₱' . number_format($remainingAmt, 2) . ' via GCash'
                : 'Pay Full Amount ₱' . number_format($remainingAmt, 2) . ' via GCash';
            }
          @endphp
          @if($showPayBtn)
          <div class="col-12 mt-2">
            <a href="{{ route('guest.pay_remaining', $order->track_code) }}"
               class="btn w-100 fw-semibold py-3"
               style="background:#007AFF;border-color:#007AFF;color:#fff;font-size:1rem"
               data-cs-confirm="You will be redirected to GCash payment via PayMongo.\n\nAmount: {{ $btnLabel }}\n\nProceed?"
               data-cs-title="Proceed to Payment"
               data-cs-ok="Continue"
               data-cs-icon="bi-wallet2"
               data-cs-icon-bg="#dbeafe"
               data-cs-icon-color="#2563eb">
              <i class="bi bi-phone-fill me-2"></i>{{ $btnLabel }}
            </a>
            @if($depositPaid)
            <div class="text-muted text-center mt-1" style="font-size:clamp(.7rem,1.4vw,.75rem)">
              <i class="bi bi-check-circle-fill me-1" style="color:#16a34a"></i>
              Deposit of ₱{{ number_format($order->deposit_amount, 2) }} already paid ✓
            </div>
            @endif
            <div class="text-muted text-center mt-1" style="font-size:clamp(.7rem,1.4vw,.75rem)">
              <i class="bi bi-shield-check me-1" style="color:#22c55e"></i>Secured by PayMongo
            </div>
          </div>
          @endif
        @endif

        {{-- Payment Fully Paid Badge --}}
        @if($order->payment_status === 'Paid' && in_array($order->status, ['Out for Delivery','Pickup','Delivered','Picked Up']))
        <div class="col-12 mt-2">
          <div class="p-2 rounded-2 text-center" style="background:#f0fdf4;border:1px solid #bbf7d0">
            <i class="bi bi-check-circle-fill me-1" style="color:#16a34a"></i>
            <span style="color:#166534;font-size:.83rem;font-weight:600">
              ✓ Fully Paid via GCash — {{ $isPickup ? 'Ready for pickup!' : 'Admin can now mark as Delivered.' }}
            </span>
          </div>
        </div>
        @endif

        {{-- ─── GCash Deposit Card — one-click payment ──────────────── --}}
        @if($order->payment_method === 'GCash' && $order->payment_status === 'Unpaid' && in_array($order->status, ['Pending','Pending Review']) && $order->deposit_status !== 'paid' && !$customOrder)
        @php $minDeposit = round($order->total_price * 0.5, 2); @endphp
        <div class="col-12 mt-3">
          <div style="border-radius:1rem;overflow:hidden;border:1.5px solid #d1fae5">
            <div style="background:linear-gradient(90deg,#059669,#0284c7);padding:.7rem 1.1rem;display:flex;align-items:center;gap:.6rem">
              <i class="bi bi-shield-lock-fill" style="color:#fff;font-size:1rem"></i>
              <span style="color:#fff;font-weight:700;font-size:.88rem;flex:1">Secure Your Order — Pay via GCash</span>
              @php $pmMode = \App\Helpers\CakeshopHelper::getPaymongoMode(); @endphp
              @if($pmMode === 'test')
                <span style="background:rgba(255,255,255,.22);color:#fef9c3;border-radius:20px;padding:2px 9px;font-size:.65rem;font-weight:700">TEST MODE</span>
              @else
                <span style="background:rgba(255,255,255,.22);color:#d1fae5;border-radius:20px;padding:2px 9px;font-size:.65rem;font-weight:700">LIVE</span>
              @endif
            </div>
            <div style="background:#f8fffe;padding:1rem">
              <div style="font-size:.8rem;color:#374151;margin-bottom:.8rem">
                <i class="bi bi-info-circle me-1" style="color:#0284c7"></i>Pay a deposit to confirm your order. Your cake will be auto-confirmed after payment.
              </div>
              <div style="background:#fff;border-radius:.65rem;padding:.55rem .9rem;margin-bottom:.85rem;display:flex;align-items:center;justify-content:space-between;border:1px solid #e5e7eb">
                <span style="font-size:.75rem;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Order Total</span>
                <span style="font-weight:800;color:#111827;font-size:1rem">₱{{ number_format($order->total_price, 2) }}</span>
              </div>
              <div class="d-flex flex-column gap-2">
                {{-- Primary: 50% deposit --}}
                <form action="{{ route('guest.set_deposit', $order->track_code) }}" method="POST"
                      class="deposit-amount-form"
                      data-min="{{ $minDeposit }}"
                      data-max="{{ $order->total_price }}">
                  @csrf
                  <label class="form-label fw-semibold small mb-1" style="color:#374151">Amount to pay now</label>
                  <div class="input-group">
                    <span class="input-group-text" style="font-weight:800;color:#059669;background:#ecfdf5;border-color:#bbf7d0">₱</span>
                    <input type="text"
                           name="deposit_amount"
                           class="form-control deposit-amount-input"
                           value="{{ number_format($minDeposit, 2, '.', '') }}"
                           inputmode="decimal"
                           autocomplete="off"
                           data-min="{{ $minDeposit }}"
                           data-max="{{ $order->total_price }}"
                           style="font-weight:800;color:#111827;border-color:#bbf7d0">
                  </div>
                  <div class="deposit-error">Minimum payment is 50%: ₱{{ number_format($minDeposit, 2) }}.</div>
                  <div style="font-size:.7rem;color:#6b7280;margin-top:.3rem">
                    Enter at least ₱{{ number_format($minDeposit, 2) }}. You may pay more up to ₱{{ number_format($order->total_price, 2) }}.
                  </div>
                  <button type="submit" class="btn w-100 fw-bold py-3"
                          style="margin-top:.65rem;background:linear-gradient(135deg,#059669,#047857);color:#fff;border:none;border-radius:.75rem;font-size:.95rem;letter-spacing:.01em"
                          data-cs-confirm="Pay 50% deposit of ₱{{ number_format($minDeposit,2) }} via GCash?\n\nYou'll be redirected to PayMongo. GCash is the only option — your phone number will be pre-filled."
                          data-cs-title="Confirm Deposit — ₱{{ number_format($minDeposit,2) }}"
                          data-cs-ok="Pay Now"
                          data-cs-icon="bi-phone-fill"
                          data-cs-icon-bg="#d1fae5"
                          data-cs-icon-color="#059669">
                    <i class="bi bi-phone-fill me-2"></i>Pay Deposit via GCash
                  </button>
                  <div style="font-size:.7rem;color:#6b7280;text-align:center;margin-top:.3rem">
                    Remaining balance: ₱{{ number_format($order->total_price - $minDeposit, 2) }} (paid on delivery)
                  </div>
                </form>
                {{-- Secondary: full payment --}}
                <form action="{{ route('guest.set_deposit', $order->track_code) }}" method="POST">
                  @csrf
                  <input type="hidden" name="deposit_amount" value="{{ $order->total_price }}">
                  <button type="submit" class="btn w-100 fw-semibold py-2"
                          style="background:#fff;color:#059669;border:1.5px solid #059669;border-radius:.75rem;font-size:.88rem"
                          data-cs-confirm="Pay full amount of ₱{{ number_format($order->total_price,2) }} via GCash?\n\nYou'll be redirected to PayMongo."
                          data-cs-title="Confirm Full Payment — ₱{{ number_format($order->total_price,2) }}"
                          data-cs-ok="Pay in Full"
                          data-cs-icon="bi-wallet2"
                          data-cs-icon-bg="#d1fae5"
                          data-cs-icon-color="#059669">
                    <i class="bi bi-wallet2 me-2"></i>Pay in Full — ₱{{ number_format($order->total_price, 2) }}
                  </button>
                </form>
              </div>
              <div style="margin-top:.75rem;font-size:.68rem;color:#9ca3af;text-align:center">
                <i class="bi bi-shield-check me-1" style="color:#22c55e"></i>Secured by PayMongo &nbsp;·&nbsp; GCash only &nbsp;·&nbsp; Your phone number is pre-filled
              </div>
            </div>
          </div>
        </div>

        {{-- Already initiated → resume payment --}}
        @elseif($order->deposit_required && $order->deposit_status === 'pending')
        <div class="col-12 mt-3">
          <div style="border-radius:1rem;overflow:hidden;border:1.5px solid #fed7aa">
            <div style="background:linear-gradient(90deg,#d97706,#ea580c);padding:.7rem 1.1rem;display:flex;align-items:center;gap:.6rem">
              <i class="bi bi-clock-fill" style="color:#fff;font-size:.9rem"></i>
              <span style="color:#fff;font-weight:700;font-size:.88rem">Payment Pending — Complete Your Payment</span>
            </div>
            <div style="background:#fffbeb;padding:1rem">
              <div style="background:#fff;border-radius:.65rem;padding:.55rem .9rem;margin-bottom:.5rem;display:flex;align-items:center;justify-content:space-between;border:1px solid #fde68a">
                <span style="font-size:.75rem;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Amount to Pay</span>
                <span style="font-weight:800;color:#111827;font-size:1rem">₱{{ number_format($order->deposit_amount, 2) }}</span>
              </div>
              @if($order->deposit_amount < $order->total_price)
              <div style="font-size:.72rem;color:#9a3412;text-align:right;margin-bottom:.7rem">
                Remaining after payment: ₱{{ number_format($order->total_price - $order->deposit_amount, 2) }}
              </div>
              @endif
              <a href="{{ route('guest.pay_deposit', $order->track_code) }}"
                 class="btn w-100 fw-bold py-3"
                 style="background:linear-gradient(135deg,#d97706,#b45309);color:#fff;border:none;border-radius:.75rem;font-size:.95rem"
                 data-cs-confirm="Continue GCash payment of ₱{{ number_format($order->deposit_amount,2) }}?\n\nYou'll be redirected to PayMongo."
                 data-cs-title="Resume Payment"
                 data-cs-ok="Continue"
                 data-cs-icon="bi-phone-fill"
                 data-cs-icon-bg="#fef3c7"
                 data-cs-icon-color="#d97706">
                <i class="bi bi-phone-fill me-2"></i>Continue Payment — ₱{{ number_format($order->deposit_amount, 2) }}
              </a>
              <div style="margin-top:.6rem;font-size:.68rem;color:#9ca3af;text-align:center">
                <i class="bi bi-shield-check me-1" style="color:#22c55e"></i>Secured by PayMongo &nbsp;·&nbsp; GCash only
              </div>
            </div>
          </div>
        </div>
        @endif

        {{-- Deposit Paid Badge --}}
        @if($order->deposit_status === 'paid')
        <div class="col-12 mt-2">
          <div class="p-2 rounded-2 text-center" style="background:#f0fdf4;border:1px solid #bbf7d0">
            <i class="bi bi-check-circle-fill me-1" style="color:#16a34a"></i>
            <span style="color:#166534;font-size:.83rem;font-weight:600">
              Deposit of ₱{{ number_format($order->deposit_amount, 2) }} paid ✓
              — Remaining balance: ₱{{ number_format($order->total_price - $order->deposit_amount, 2) }}
            </span>
          </div>
        </div>
        @endif
      </div>
    </div>
  </div>

  @if($canRequestCancel && !$hasPendingCancel && !$cancelApproved)
  <div class="card mb-3">
    <div class="card-body p-4">
      <h6 class="fw-bold mb-3"><i class="bi bi-x-circle me-2" style="color:#dc2626"></i>Request Cancellation</h6>
      <div class="small text-muted mb-3">
        You may still request cancellation because no paid deposit has been recorded for this order.
      </div>
      <form action="{{ route('guest.cancel_request', $order->track_code) }}" method="POST">
        @csrf
        <div class="mb-3">
          <label class="form-label fw-semibold small">Reason for Cancellation <span class="text-danger">*</span></label>
          <textarea class="form-control" name="cancel_reason" rows="3" required placeholder="Please explain why you want to cancel this order."></textarea>
        </div>
        <button type="submit"
                class="btn btn-outline-danger"
                data-cs-confirm="Submit a cancellation request for this order?"
                data-cs-title="Request Cancellation"
                data-cs-ok="Submit Request"
                data-cs-ok-color="#dc2626"
                data-cs-icon="bi-x-octagon"
                data-cs-icon-bg="#fee2e2"
                data-cs-icon-color="#dc2626">
          <i class="bi bi-send me-1"></i>Submit Cancel Request
        </button>
      </form>
    </div>
  </div>
  @endif

  {{-- Custom Order Info --}}
  @if($customOrder)
  <div class="card mb-3">
    <div class="card-body p-4">
      <h6 class="fw-bold mb-3"><i class="bi bi-palette me-2" style="color:var(--primary)"></i>Custom Order Details</h6>
      @php
        $coStatus = [
          'pending'  => ['bg'=>'#fff3cd','color'=>'#856404','label'=>'⏳ Awaiting Review'],
          'approved' => ['bg'=>'#d1fae5','color'=>'#065f46','label'=>'✅ Approved'],
          'rejected' => ['bg'=>'#fee2e2','color'=>'#991b1b','label'=>'❌ Not Approved'],
        ];
        $cos = $coStatus[$customOrder->review_status] ?? $coStatus['pending'];
      @endphp
      <span class="badge mb-3 px-3 py-2" style="background:{{ $cos['bg'] }};color:{{ $cos['color'] }}">
        {{ $cos['label'] }}
      </span>
      @if($customOrder->admin_price)
        <div class="mb-2 small"><span class="text-muted">Final Price:</span>
          <strong class="ms-1" style="color:var(--primary)">₱{{ number_format($customOrder->admin_price, 2) }}</strong>
        </div>
      @endif
      @if($customOrder->admin_comment)
        <div class="p-2 rounded-2 small" style="background:{{ $customOrder->review_status === 'approved' ? '#f0fdf4' : '#fef2f2' }};border-left:3px solid {{ $customOrder->review_status === 'approved' ? '#22c55e' : '#ef4444' }}">
          <span class="fw-semibold">{{ $customOrder->review_status === 'approved' ? '✅ Baker:' : '❌ Reason:' }}</span>
          {{ $customOrder->admin_comment }}
        </div>
      @endif
      {{-- ── CUSTOM ORDER DEPOSIT — one-click payment card ─────────── --}}
      @if($customOrder->review_status === 'approved'
          && $customOrder->admin_price > 0
          && $customOrder->price_confirmed === 'accepted'
          && $order->payment_status === 'Unpaid'
          && $order->deposit_status !== 'paid'
          && in_array($order->status, ['Pending','Pending Review','Confirmed']))
      @php
        $coTotal = (float)$customOrder->admin_price;
        $minDep  = round($coTotal * 0.5, 2);
      @endphp
      @if($order->payment_method === 'GCash')
      <div class="mt-3" style="border-radius:1rem;overflow:hidden;border:1.5px solid #d1fae5">
        <div style="background:linear-gradient(90deg,#059669,#0284c7);padding:.7rem 1.1rem;display:flex;align-items:center;gap:.6rem">
          <i class="bi bi-shield-lock-fill" style="color:#fff;font-size:1rem"></i>
          <span style="color:#fff;font-weight:700;font-size:.88rem;flex:1">Secure Your Custom Order — Pay via GCash</span>
          @php $pmMode = \App\Helpers\CakeshopHelper::getPaymongoMode(); @endphp
          @if($pmMode === 'test')
            <span style="background:rgba(255,255,255,.22);color:#fef9c3;border-radius:20px;padding:2px 9px;font-size:.65rem;font-weight:700">TEST MODE</span>
          @else
            <span style="background:rgba(255,255,255,.22);color:#d1fae5;border-radius:20px;padding:2px 9px;font-size:.65rem;font-weight:700">LIVE</span>
          @endif
        </div>
        <div style="background:#f8fffe;padding:1rem">
          <div style="background:#fff;border-radius:.65rem;padding:.55rem .9rem;margin-bottom:.85rem;display:flex;align-items:center;justify-content:space-between;border:1px solid #e5e7eb">
            <span style="font-size:.75rem;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Final Price</span>
            <span style="font-weight:800;color:#111827;font-size:1rem">₱{{ number_format($coTotal, 2) }}</span>
          </div>
          <div class="d-flex flex-column gap-2">
            <form action="{{ route('guest.set_deposit', $order->track_code) }}" method="POST"
                  class="deposit-amount-form"
                  data-min="{{ $minDep }}"
                  data-max="{{ $coTotal }}">
              @csrf
              <label class="form-label fw-semibold small mb-1" style="color:#374151">Amount to pay now</label>
              <div class="input-group">
                <span class="input-group-text" style="font-weight:800;color:#059669;background:#ecfdf5;border-color:#bbf7d0">₱</span>
                <input type="text"
                       name="deposit_amount"
                       class="form-control deposit-amount-input"
                       value="{{ number_format($minDep, 2, '.', '') }}"
                       inputmode="decimal"
                       autocomplete="off"
                       data-min="{{ $minDep }}"
                       data-max="{{ $coTotal }}"
                       style="font-weight:800;color:#111827;border-color:#bbf7d0">
              </div>
              <div class="deposit-error">Minimum payment is 50%: ₱{{ number_format($minDep, 2) }}.</div>
              <div style="font-size:.7rem;color:#6b7280;margin-top:.3rem">
                Enter at least ₱{{ number_format($minDep, 2) }}. You may pay more up to ₱{{ number_format($coTotal, 2) }}.
              </div>
              <button type="submit" class="btn w-100 fw-bold py-3"
                      style="background:linear-gradient(135deg,#059669,#047857);color:#fff;border:none;border-radius:.75rem;font-size:.95rem"
                      data-cs-confirm="Pay 50% deposit of ₱{{ number_format($minDep,2) }} via GCash?\n\nYou'll be redirected to PayMongo. GCash is pre-selected."
                      data-cs-title="Confirm Deposit — ₱{{ number_format($minDep,2) }}"
                      data-cs-ok="Pay Now"
                      data-cs-icon="bi-phone-fill"
                      data-cs-icon-bg="#d1fae5"
                      data-cs-icon-color="#059669">
                <i class="bi bi-phone-fill me-2"></i>Pay Deposit via GCash
              </button>
              <div style="font-size:.7rem;color:#6b7280;text-align:center;margin-top:.3rem">
                Remaining: ₱{{ number_format($coTotal - $minDep, 2) }} (paid on delivery)
              </div>
            </form>
            <form action="{{ route('guest.set_deposit', $order->track_code) }}" method="POST">
              @csrf
              <input type="hidden" name="deposit_amount" value="{{ $coTotal }}">
              <button type="submit" class="btn w-100 fw-semibold py-2"
                      style="background:#fff;color:#059669;border:1.5px solid #059669;border-radius:.75rem;font-size:.88rem"
                      data-cs-confirm="Pay full amount of ₱{{ number_format($coTotal,2) }} via GCash?\n\nYou'll be redirected to PayMongo."
                      data-cs-title="Confirm Full Payment — ₱{{ number_format($coTotal,2) }}"
                      data-cs-ok="Pay in Full"
                      data-cs-icon="bi-wallet2"
                      data-cs-icon-bg="#d1fae5"
                      data-cs-icon-color="#059669">
                <i class="bi bi-wallet2 me-2"></i>Pay in Full — ₱{{ number_format($coTotal, 2) }}
              </button>
            </form>
          </div>
          <div style="margin-top:.75rem;font-size:.68rem;color:#9ca3af;text-align:center">
            <i class="bi bi-shield-check me-1" style="color:#22c55e"></i>Secured by PayMongo &nbsp;·&nbsp; GCash only &nbsp;·&nbsp; Phone pre-filled
          </div>
        </div>
      </div>
      @else
      <div class="mt-3 p-3 rounded-3" style="background:#fffbeb;border:1.5px solid #fbbf24">
        <div class="fw-semibold small mb-2"><i class="bi bi-cash me-1" style="color:#d97706"></i>COD Deposit — ₱{{ number_format($minDep,2) }}</div>
        <form action="{{ route('guest.set_deposit', $order->track_code) }}" method="POST">
          @csrf
          <input type="hidden" name="deposit_amount" value="{{ $minDep }}">
          <button type="submit" class="btn w-100 fw-semibold py-2 btn-warning text-dark"
                  data-cs-confirm="Acknowledge 50% COD deposit of ₱{{ number_format($minDep,2) }}?"
                  data-cs-title="Confirm COD Deposit"
                  data-cs-ok="Acknowledge"
                  data-cs-icon="bi-cash-stack"
                  data-cs-icon-bg="#fef3c7"
                  data-cs-icon-color="#b45309">
            <i class="bi bi-cash me-1"></i>Acknowledge COD Deposit &amp; Confirm Order
          </button>
        </form>
      </div>
      @endif
      @endif

      {{-- Price acceptance buttons (only if admin set price but customer hasn't responded yet) --}}
      @if($customOrder->review_status === 'approved'
          && $customOrder->admin_price > 0
          && $customOrder->price_confirmed === 'pending')
      <div class="mt-3 p-3 rounded-3" style="background:#fffbeb;border:1.5px solid #fbbf24">
        <div class="fw-semibold small mb-1" style="color:#d97706">
          <i class="bi bi-exclamation-circle me-1"></i>Final Price Set — Please Respond
        </div>
        <div class="small text-muted mb-3">
          The baker has set a final price of
          <strong style="color:var(--primary)">₱{{ number_format($customOrder->admin_price,2) }}</strong>
          for your custom cake. Please accept or cancel.
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <form action="{{ route('guest.custom_order.accept_price', $customOrder->id) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-success btn-sm"
                    data-cs-confirm="Accept ₱{{ number_format($customOrder->admin_price,2) }} as final price?"
                    data-cs-title="Accept Final Price"
                    data-cs-ok="Accept Price"
                    data-cs-icon="bi-check-circle"
                    data-cs-icon-bg="#dcfce7"
                    data-cs-icon-color="#16a34a">
              <i class="bi bi-check-circle me-1"></i>✅ Accept Price
            </button>
          </form>
          <form action="{{ route('guest.custom_order.cancel_price', $customOrder->id) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-danger btn-sm"
                    data-cs-confirm="Cancel this custom order?"
                    data-cs-title="Cancel Custom Order"
                    data-cs-ok="Cancel Order"
                    data-cs-ok-color="#dc2626"
                    data-cs-icon="bi-x-octagon"
                    data-cs-icon-bg="#fee2e2"
                    data-cs-icon-color="#dc2626">
              <i class="bi bi-x-circle me-1"></i>❌ Cancel Order
            </button>
          </form>
        </div>
      </div>
      @endif

      @if($customOrder->progress_image || $customOrder->progress_message)
        <div class="p-2 rounded-2 mt-2" style="background:#f0f4ff;border-left:3px solid #6366f1">
          <div class="fw-semibold small mb-1" style="color:#4f46e5">📸 Progress Update from Baker</div>
          @if($customOrder->progress_image)
            <img src="{{ $customOrder->progress_image }}" class="chat-img" data-src="{{ $customOrder->progress_image }}"
                 style="max-height:160px;border-radius:.5rem;cursor:zoom-in;display:block;margin-bottom:6px"
                 onclick="openLightbox(this)">
          @endif
          @if($customOrder->progress_message)
            <div class="small text-muted">{{ str_replace('[custom_order:'.$customOrder->id.']','', $customOrder->progress_message) }}</div>
          @endif
        </div>
      @endif
    </div>
  </div>
  @endif

  {{-- Order Timeline --}}
  <div class="card mb-4">
    <div class="card-body p-4">
      <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2" style="color:var(--primary)"></i>Order Timeline</h6>
      @foreach($tracking->sortByDesc('created_at') as $t)
      <div class="d-flex gap-3 mb-3">
        <div style="width:10px;height:10px;border-radius:50%;background:var(--primary);margin-top:5px;flex-shrink:0"></div>
        <div>
          <div class="fw-semibold small">{{ $t->status }}</div>
          @if($t->notes)<div class="text-muted small">{{ $t->notes }}</div>@endif
          <div class="text-muted" style="font-size:clamp(.68rem,1.3vw,.72rem)">{{ \Carbon\Carbon::parse($t->created_at)->format('M d, Y g:i A') }}</div>
        </div>
      </div>
      @endforeach
    </div>
  </div>

  {{-- Back to Catalog --}}
  <div class="text-center mb-4">
    <a href="{{ route('catalog') }}" class="btn btn-outline-primary px-4">
      <i class="bi bi-cake2 me-2"></i>Browse More Cakes
    </a>
  </div>

  {{-- ── REVIEW SECTION (only if Delivered) ─────────────────────────────── --}}
  @if($order->status === 'Delivered')
  @php $existingReview = \Illuminate\Support\Facades\DB::table('order_reviews')->where('order_id', $order->id)->first(); @endphp
  <div class="card mb-4">
    <div class="card-body p-4">
      <h6 class="fw-bold mb-3"><i class="bi bi-star-fill me-2" style="color:#f59e0b"></i>Rate Your Cake</h6>
      @if(session('msg'))
        <div class="alert alert-success border-0 py-2">{{ session('msg') }}</div>
      @endif
      @if($existingReview)
        <div class="text-center py-3">
          <div style="font-size:clamp(1.1rem,3vw,1.5rem);color:#f59e0b">
            @for($i=1;$i<=5;$i++)<i class="bi bi-star{{ $i<=$existingReview->rating ? '-fill' : '' }}"></i>@endfor
          </div>
          <div class="text-muted small mt-1">You already reviewed this order. Thank you! 🎂</div>
          @if($existingReview->review)
            <div class="mt-2 small fst-italic">"{{ $existingReview->review }}"</div>
          @endif
        </div>
      @else
        <form action="{{ route('guest.review.store', $order->track_code) }}" method="POST" enctype="multipart/form-data">
          @csrf
          <div class="mb-3 text-center">
            <div class="fw-semibold small mb-2">How was your cake?</div>
            <div class="d-flex justify-content-center gap-2">
              @for($i=1;$i<=5;$i++)
                <i class="bi bi-star review-star" data-val="{{ $i }}"
                   style="font-size:2rem;cursor:pointer;color:#d1d5db;transition:color .15s"
                   onclick="setRating({{ $i }})" onmouseover="hoverRating({{ $i }})" onmouseout="unhoverRating()"></i>
              @endfor
            </div>
            <input type="hidden" name="rating" id="ratingInput" value="5">
          </div>
          {{-- Rider Rating --}}
          @if(!empty($order->rider_id))
          <div class="mb-3">
            <div class="fw-semibold small mb-2"><i class="bi bi-bicycle me-1" style="color:var(--primary)"></i>Rate your Rider <span class="text-muted fw-normal">(optional)</span></div>
            <div class="d-flex justify-content-center gap-2">
              @for($i=1;$i<=5;$i++)
                <i class="bi bi-star rider-review-star" data-val="{{ $i }}"
                   style="font-size:1.6rem;cursor:pointer;color:#d1d5db;transition:color .15s"
                   onclick="setRiderRating({{ $i }})" onmouseover="hoverRiderRating({{ $i }})" onmouseout="unhoverRiderRating()"></i>
              @endfor
            </div>
            <input type="hidden" name="rider_rating" id="riderRatingInput" value="">
          </div>
          @endif

          <div class="mb-3">
            <textarea class="form-control" name="review" rows="3"
                      placeholder="Tell us about your experience... (optional)"></textarea>
          </div>
          <button type="submit" class="btn w-100" style="background:var(--primary);color:#fff">
            <i class="bi bi-send me-1"></i>Submit Review
          </button>
        </form>
      @endif
    </div>
  </div>
  @endif

  {{-- ── CHAT SECTION ────────────────────────────────────────────────────── --}}
  <div class="card mb-4">
    <div class="card-body p-4">
      <h6 class="fw-bold mb-3">
        <i class="bi bi-chat-dots me-2" style="color:var(--primary)"></i>Message the Owner
      </h6>
      <div id="msgThread"
           style="max-height:320px;overflow-y:auto;display:flex;flex-direction:column;gap:10px;padding:4px 2px;margin-bottom:12px;min-height:60px">
        <div class="text-muted text-center small py-2" id="msgEmpty">No messages yet. Ask us anything! 😊</div>
      </div>
      @if(!in_array($order->status, ['Cancelled','Delivered']))
      <div class="d-flex gap-2 align-items-end">
        <textarea id="msgInput" class="form-control" rows="2"
                  placeholder="Type a message..." style="resize:none;flex:1"></textarea>
        <button class="btn btn-primary px-3" onclick="sendGuestMsg()" id="msgSendBtn">
          <i class="bi bi-send"></i>
        </button>
      </div>
      @else
      <div class="text-muted small text-center pt-1">
        <i class="bi bi-lock me-1"></i>Messaging closed for {{ strtolower($order->status) }} orders.
      </div>
      @endif
    </div>
  </div>

</div>

<script>
const TRACK_CODE = '{{ $order->track_code }}';
const GUEST_NAME = '{{ addslashes($order->guest_name ?? "You") }}';

// ── Star Rating ──────────────────────────────────────────────────────────
let selectedRating = 5;
function setRating(val) {
  selectedRating = val;
  const ri = document.getElementById('ratingInput');
  if (ri) ri.value = val;
  document.querySelectorAll('.review-star').forEach((s, i) => {
    s.className = 'bi review-star ' + (i < val ? 'bi-star-fill' : 'bi-star');
    s.style.color = i < val ? '#f59e0b' : '#d1d5db';
  });
}
function hoverRating(val) {
  document.querySelectorAll('.review-star').forEach((s, i) => {
    s.style.color = i < val ? '#f59e0b' : '#d1d5db';
  });
}
function unhoverRating() { setRating(selectedRating); }

// ── Rider Rating ─────────────────────────────────────────────────────────
let selectedRiderRating = 0;
function setRiderRating(val) {
  selectedRiderRating = val;
  const rri = document.getElementById('riderRatingInput');
  if (rri) rri.value = val;
  document.querySelectorAll('.rider-review-star').forEach((s, i) => {
    s.className = 'bi rider-review-star ' + (i < val ? 'bi-star-fill' : 'bi-star');
    s.style.color = i < val ? '#f59e0b' : '#d1d5db';
  });
}
function hoverRiderRating(val) {
  document.querySelectorAll('.rider-review-star').forEach((s, i) => {
    s.style.color = i < val ? '#f59e0b' : '#d1d5db';
  });
}
function unhoverRiderRating() {
  document.querySelectorAll('.rider-review-star').forEach((s, i) => {
    s.style.color = i < selectedRiderRating ? '#f59e0b' : '#d1d5db';
  });
}

// ── Messaging ────────────────────────────────────────────────────────────
let rendered = [];

function renderMessages(msgs) {
  const thread = document.getElementById('msgThread');
  const empty  = document.getElementById('msgEmpty');
  if (!msgs.length) return;
  empty && (empty.style.display = 'none');

  // Only add new messages
  const newMsgs = msgs.filter(m => !rendered.includes(m.id));
  newMsgs.forEach(m => {
    rendered.push(m.id);
    const isAdmin = m.is_admin;
    const wrap = document.createElement('div');
    wrap.style.cssText = 'display:flex;flex-direction:column;align-items:' + (isAdmin ? 'flex-start' : 'flex-end');

    let html = '<div style="font-size:clamp(.66rem,1.3vw,.7rem);color:#9ca3af;margin-bottom:2px">' + m.name + ' · ' + m.created_at + '</div>';

    if (m.message) {
      html += '<div style="max-width:75%;padding:8px 12px;border-radius:' + (isAdmin ? '0 12px 12px 12px' : '12px 0 12px 12px') + ';background:' + (isAdmin ? 'var(--primary)' : '#f3f4f6') + ';color:' + (isAdmin ? '#fff' : '#111') + ';font-size:.88rem;word-break:break-word">' + escapeHtml(m.message) + '</div>';
    }
    if (m.image_path) {
      html += '<img src="' + m.image_path + '" class="chat-img" data-src="' + m.image_path + '" onclick="openLightbox(this)" style="max-height:160px;border-radius:.6rem;margin-top:4px;cursor:zoom-in">';
    }
    wrap.innerHTML = html;
    thread.appendChild(wrap);
  });

  // Scroll to bottom
  thread.scrollTop = thread.scrollHeight;
}

async function pollMessages() {
  try {
    const r = await fetch('/track/' + TRACK_CODE + '/messages');
    const d = await r.json();
    renderMessages(d.messages || []);
  } catch(e) {}
}

async function sendGuestMsg() {
  const input = document.getElementById('msgInput');
  const msg   = input.value.trim();
  if (!msg) return;

  const btn = document.getElementById('msgSendBtn');
  btn.disabled = true;
  input.disabled = true;

  const fd = new FormData();
  fd.append('_token', '{{ csrf_token() }}');
  fd.append('message', msg);

  try {
    const r = await fetch('/track/' + TRACK_CODE + '/messages', { method:'POST', body:fd });
    const d = await r.json();
    if (d.ok) {
      input.value = '';
      await pollMessages();
    } else {
      cakeToast(d.error || 'Failed to send.', 'error');
    }
  } catch(e) {
    cakeToast('Failed to send. Try again.', 'error');
  }
  btn.disabled = false;
  input.disabled = false;
  input.focus();
}

function escapeHtml(s) {
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
}

function setupDepositAmountForms() {
  document.querySelectorAll('.deposit-amount-form').forEach(form => {
    const input = form.querySelector('.deposit-amount-input');
    const error = form.querySelector('.deposit-error');
    const button = form.querySelector('button[type="submit"]');
    const min = parseFloat(form.dataset.min || input?.dataset.min || '0');
    const max = parseFloat(form.dataset.max || input?.dataset.max || '0');

    if (!input || !error) return;

    const setButtonCopy = () => {
      const amount = parseFloat(input.value || '0');
      if (button) {
        button.innerHTML = '<i class="bi bi-phone-fill me-2"></i>Pay Deposit via GCash';
        button.dataset.csConfirm = 'Pay deposit of ₱' + (amount || min).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' via GCash?\\n\\nYou will be redirected to PayMongo.';
        button.dataset.csTitle = 'Confirm Deposit';
        button.dataset.csOk = 'Pay Now';
        button.dataset.csIcon = 'bi-phone-fill';
        button.dataset.csIconBg = '#d1fae5';
        button.dataset.csIconColor = '#059669';
      }
    };

    const showError = message => {
      input.classList.add('is-invalid');
      error.textContent = message;
      error.classList.remove('show');
      void error.offsetWidth;
      error.classList.add('show');
      if (navigator.vibrate) navigator.vibrate(120);
    };

    const clearError = () => {
      input.classList.remove('is-invalid');
      error.classList.remove('show');
    };

    input.addEventListener('input', () => {
      let value = input.value.replace(/[^\d.]/g, '');
      const firstDot = value.indexOf('.');
      if (firstDot !== -1) {
        value = value.slice(0, firstDot + 1) + value.slice(firstDot + 1).replace(/\./g, '');
      }
      input.value = value;
      clearError();
      setButtonCopy();
    });

    input.addEventListener('blur', () => {
      const amount = parseFloat(input.value || '0');
      if (!amount) input.value = min.toFixed(2);
      else input.value = Math.min(amount, max).toFixed(2);
      setButtonCopy();
    });

    button?.addEventListener('click', event => {
      const amount = parseFloat(input.value || '0');
      if (!amount || amount < min) {
        event.preventDefault();
        event.stopImmediatePropagation();
        showError('Minimum payment is 50%: ₱' + min.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '.');
        input.focus();
      }
    });

    form.addEventListener('submit', event => {
      const amount = parseFloat(input.value || '0');
      if (!amount || amount < min) {
        event.preventDefault();
        event.stopImmediatePropagation();
        showError('Minimum payment is 50%: ₱' + min.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '.');
        input.focus();
        return false;
      }
      if (max && amount > max) {
        event.preventDefault();
        event.stopImmediatePropagation();
        showError('Payment cannot exceed the order total: ₱' + max.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '.');
        input.focus();
        return false;
      }
      input.value = amount.toFixed(2);
      setButtonCopy();
      return true;
    }, true);

    setButtonCopy();
  });
}

setupDepositAmountForms();

// Allow Enter to send (Shift+Enter for newline)
document.getElementById('msgInput')?.addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendGuestMsg(); }
});

// Initial load + poll every 8 seconds
pollMessages();
setInterval(pollMessages, 8000);

// Auto-highlight stars on load
setRating(5);
</script>

@push('scripts')
@endpush


@endsection
