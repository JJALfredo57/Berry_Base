@extends('layouts.app')
@section('content')
<div class="container-fluid py-4" style="padding-left:clamp(12px,3vw,32px);padding-right:clamp(12px,3vw,32px)">

  @if(session('msg'))
    <div class="alert alert-success border-0"><i class="bi bi-check-circle me-2"></i>{{ session('msg') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger border-0"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
  @endif

  {{-- Header --}}
  <div class="text-center mb-4">
    <div style="font-size:3rem">🎂</div>
    <h4 class="fw-bold mb-1" style="color:var(--primary)">Order Tracking</h4>
    <div class="text-muted small">Order #{{ $order->id }}</div>
  </div>

  {{-- Status Badge --}}
  @php
    $isPickup = $order->fulfillment_type === 'Pickup';
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
            <div class="fw-semibold">{{ $order->payment_method }}
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

        {{-- Customer sets deposit amount (only for Pending GCash regular orders — not custom orders) --}}
        @if($order->payment_method === 'GCash' && $order->payment_status === 'Unpaid' && in_array($order->status, ['Pending','Pending Review']) && $order->deposit_status !== 'paid' && !$customOrder)
        <div class="col-12 mt-2">
          <div class="p-3 rounded-3" style="background:#fff7ed;border:1px solid #fed7aa">
            <div class="fw-semibold small mb-2"><i class="bi bi-cash-coin me-1" style="color:#ea580c"></i>Choose Payment Amount</div>
            <p class="text-muted small mb-3">You can pay a deposit (minimum 50%) or pay the full amount now. Your order will be auto-confirmed after payment.</p>
            @php
              $minDeposit   = round($order->total_price * 0.5, 2);
              $currentDeposit = (!empty($order->deposit_amount) && $order->deposit_amount > 0) ? (float)$order->deposit_amount : $minDeposit;
            @endphp
            <form action="{{ route('guest.set_deposit', $order->track_code) }}" method="POST">
              @csrf
              <div class="mb-3">
                <label class="form-label fw-semibold small">Amount to Pay (₱)</label>
                <input type="number"
                       class="form-control"
                       name="deposit_amount"
                       step="0.01"
                       min="{{ $minDeposit }}"
                       max="{{ $order->total_price }}"
                       value="{{ $currentDeposit }}"
                       id="depositInput{{ $order->id }}"
                       oninput="updateDepositHint('{{ $order->id }}', {{ $order->total_price }}, {{ $minDeposit }})"
                       required>
                <div class="small mt-2" id="depositFeePreview{{ $order->id }}" style="color:#9a3412"></div>
                <div class="form-text" id="depositHint{{ $order->id }}">
                  Minimum: ₱{{ number_format($minDeposit, 2) }} (50%) &bull; Full amount: ₱{{ number_format($order->total_price, 2) }}
                </div>
              </div>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm"
                        onclick="document.getElementById('depositInput{{ $order->id }}').value={{ $minDeposit }};updateDepositHint('{{ $order->id }}',{{ $order->total_price }},{{ $minDeposit }})">
                  50% = ₱{{ number_format($minDeposit, 2) }}
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm"
                        onclick="document.getElementById('depositInput{{ $order->id }}').value={{ $order->total_price }};updateDepositHint('{{ $order->id }}',{{ $order->total_price }},{{ $minDeposit }})">
                  Full = ₱{{ number_format($order->total_price, 2) }}
                </button>
              </div>
              <button type="submit" class="btn w-100 fw-semibold mt-3 py-2"
                      style="background:#16a34a;color:#fff"
                      data-cs-confirm="You will be redirected to GCash payment via PayMongo.\n\nMake sure the amount is correct before proceeding."
                      data-cs-title="Proceed to Payment"
                      data-cs-ok="Continue"
                      data-cs-icon="bi-wallet2"
                      data-cs-icon-bg="#dbeafe"
                      data-cs-icon-color="#2563eb">
                <i class="bi bi-phone-fill me-2"></i>Proceed to Pay via PayMongo
              </button>
            </form>
          </div>
        </div>

        {{-- Already set deposit — show pay button --}}
        @elseif($order->deposit_required && $order->deposit_status === 'pending')
        <div class="col-12 mt-2">
          <a href="{{ route('guest.pay_deposit', $order->track_code) }}"
             class="btn w-100 fw-semibold py-3"
             style="background:#16a34a;border-color:#16a34a;color:#fff;font-size:1rem">
            <i class="bi bi-phone-fill me-2"></i>Pay ₱{{ number_format($order->deposit_amount, 2) }} via GCash
          </a>
          <div class="text-muted text-center mt-2" style="font-size:clamp(.7rem,1.4vw,.75rem)">
            @if($order->deposit_amount < $order->total_price)
              Remaining after payment: ₱{{ number_format($order->total_price - $order->deposit_amount, 2) }}
            @else
              Full payment
            @endif
            <br><i class="bi bi-shield-check me-1" style="color:#22c55e"></i>Secured by PayMongo
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
      {{-- ── CUSTOM ORDER DEPOSIT SETTER ──────────────────────────────── --}}
      {{-- Show if: approved + admin set price + customer accepted price + not yet paid --}}
      @if($customOrder->review_status === 'approved'
          && $customOrder->admin_price > 0
          && $customOrder->price_confirmed === 'accepted'
          && $order->payment_status === 'Unpaid'
          && $order->deposit_status !== 'paid'
          && in_array($order->status, ['Pending','Pending Review','Confirmed']))
      @php
        $coTotal  = (float)$customOrder->admin_price;
        $minDep   = round($coTotal * 0.5, 2);
        $curDep   = ($order->deposit_amount > 0) ? (float)$order->deposit_amount : $minDep;
      @endphp
      <div class="mt-3 p-3 rounded-3" style="background:#fff7ed;border:1.5px solid #fed7aa">
        <div class="fw-semibold small mb-1">
          <i class="bi bi-cash-coin me-1" style="color:#ea580c"></i>Choose Payment Amount
        </div>
        <p class="text-muted small mb-3">
          Minimum 50% deposit required. You can also pay in full. Your order will be auto-confirmed after payment.
        </p>
        <form action="{{ route('guest.set_deposit', $order->track_code) }}" method="POST">
          @csrf
          <div class="mb-2">
            <label class="form-label fw-semibold small">Amount to Pay (₱)</label>
            <input type="number"
                   class="form-control"
                   name="deposit_amount"
                   step="0.01"
                   min="{{ $minDep }}"
                   max="{{ $coTotal }}"
                   value="{{ $curDep }}"
                   id="coDepInput{{ $order->id }}"
                   oninput="updateDepositHint('{{ $order->id }}', {{ $coTotal }}, {{ $minDep }})"
                   required>
            <div class="form-text" id="depositHint{{ $order->id }}">
              Minimum: ₱{{ number_format($minDep,2) }} (50%) &bull; Full: ₱{{ number_format($coTotal,2) }}
            </div>
          </div>
          <div class="d-flex gap-2 mb-3">
            <button type="button" class="btn btn-outline-secondary btn-sm"
                    onclick="document.getElementById('coDepInput{{ $order->id }}').value={{ $minDep }};updateDepositHint('{{ $order->id }}',{{ $coTotal }},{{ $minDep }})">
              50% = ₱{{ number_format($minDep,2) }}
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm"
                    onclick="document.getElementById('coDepInput{{ $order->id }}').value={{ $coTotal }};updateDepositHint('{{ $order->id }}',{{ $coTotal }},{{ $minDep }})">
              Full = ₱{{ number_format($coTotal,2) }}
            </button>
          </div>
          @if($order->payment_method === 'GCash')
          <button type="submit" class="btn w-100 fw-semibold py-2" style="background:#16a34a;color:#fff"
                  data-cs-confirm="You will be redirected to GCash payment via PayMongo.\n\nMake sure the deposit amount is correct before proceeding."
                  data-cs-title="Proceed to Payment"
                  data-cs-ok="Continue"
                  data-cs-icon="bi-wallet2"
                  data-cs-icon-bg="#dbeafe"
                  data-cs-icon-color="#2563eb">
            <i class="bi bi-phone-fill me-2"></i>Proceed to Pay via PayMongo
          </button>
          @else
          <button type="submit" class="btn w-100 fw-semibold py-2 btn-warning text-dark"
                  data-cs-confirm="Acknowledge COD deposit?"
                  data-cs-title="Confirm COD Deposit"
                  data-cs-ok="Acknowledge"
                  data-cs-icon="bi-cash-stack"
                  data-cs-icon-bg="#fef3c7"
                  data-cs-icon-color="#b45309">
            <i class="bi bi-cash me-1"></i>Acknowledge COD Deposit &amp; Confirm Order
          </button>
          @endif
        </form>
      </div>
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

<script>
function updateDepositHint(orderId, total, minDeposit) {
  var input = document.getElementById('depositInput' + orderId);
  var hint  = document.getElementById('depositHint' + orderId);
  if (!input || !hint) return;
  var val = parseFloat(input.value) || 0;
  if (val < minDeposit) {
    hint.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>Minimum is ₱' + minDeposit.toFixed(2) + ' (50%)</span>';
    input.setCustomValidity('Minimum deposit is 50%');
  } else if (val >= total) {
    hint.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Full payment — order will be fully paid ✓</span>';
    input.setCustomValidity('');
  } else {
    var remaining = (total - val).toFixed(2);
    hint.innerHTML = '<span class="text-muted">Remaining balance after payment: ₱' + remaining + '</span>';
    input.setCustomValidity('');
  }
}
</script>
<script>
const _origUpdateDepositHint = updateDepositHint;
window.updateDepositHint = function(orderId, total, minDeposit) {
  _origUpdateDepositHint(orderId, total, minDeposit);
  var input = document.getElementById('depositInput' + orderId) || document.getElementById('coDepInput' + orderId);
  var feePreview = document.getElementById('depositFeePreview' + orderId);
  if (!input || !feePreview) return;
  var val = parseFloat(input.value) || 0;
  var estimatedFee = val * 0.03;
  var estimatedNet = Math.max(0, val - estimatedFee);
  feePreview.innerHTML = '<i class="bi bi-receipt-cutoff me-1"></i>Transparency preview: customer pays PHP ' + val.toFixed(2) + ', estimated processor fee ~ PHP ' + estimatedFee.toFixed(2) + ', net after fee ~ PHP ' + estimatedNet.toFixed(2);
};

document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('[id^="depositInput"]').forEach(function(input) {
    var orderId = input.id.replace('depositInput', '');
    var min = parseFloat(input.min || '0');
    var max = parseFloat(input.max || '0');
    if (orderId && max > 0) updateDepositHint(orderId, max, min);
  });
});
</script>

@endsection
