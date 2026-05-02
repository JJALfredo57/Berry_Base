@extends('layouts.app')
@section('content')
@php
  // Fallback variables in case of older controller version
  if (!isset($orderAddons))      $orderAddons = [];
  if (!isset($orderReviews))     $orderReviews = [];
  if (!isset($tracking))         $tracking = [];
@endphp
<div class="container-fluid py-4">
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-bag me-2" style="color:var(--primary)"></i>My Orders</h4>
      <p class="text-muted small mb-0" id="custOrdersLabel">Track your cake orders</p>
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-center">
      <div class="cs-search-bar" style="max-width:200px">
        <input type="text" class="form-control form-control-sm" placeholder="Search orders…"
               value="{{ $search ?? '' }}" oninput="pgSearch(this.value)">
      </div>
      <select class="form-select form-select-sm" style="width:auto" onchange="pgFilter('status', this.value)">
        <option value="All" {{ ($status??'All')==='All'?'selected':'' }}>All Status</option>
        @foreach(['Awaiting Deposit','Pending Review','Pending','Confirmed','Preparing','Out for Delivery','Delivered','Picked Up','Cancelled'] as $st)
        <option value="{{ $st }}" {{ ($status??'')===$st?'selected':'' }}>{{ $st }}</option>
        @endforeach
      </select>
      <a href="{{ route('customer.catalog') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus me-1"></i>Order Again
      </a>
    </div>
  </div>

  @if(session('msg'))
    <div class="alert alert-success border-0"><i class="bi bi-check-circle me-2"></i>{{ session('msg') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger border-0"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
  @endif
  @if(session('warn'))
    <div class="alert alert-warning border-0"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('warn') }}</div>
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
      font-size: .7rem;
      font-weight: 700;
      margin-top: .3rem;
    }
    .deposit-error.show {
      display: block;
      animation: depositShake .32s ease;
    }
  </style>

  <div id="custOrdersList">
@forelse($orders as $o)
  @php
    $hasDepositLock = ($o->deposit_status ?? null) === 'paid' || in_array(($o->payment_status ?? ''), ['Partial Payment','Paid']);
    $canCancel   = in_array($o->status, ['Pending','Confirmed']) && !$hasDepositLock;
    $notAllowed  = in_array($o->status, ['Preparing','Out for Delivery','Delivered','Cancelled']);
    $hasPending  = $o->cancel_requested && $o->cancel_status === 'pending';
    $wasAccepted = $o->cancel_status === 'accepted';
    $wasRejected = $o->cancel_status === 'rejected';
    $co          = $customOrderData[$o->id] ?? null;  // custom order record if exists
    $isCustom    = !is_null($co);
  @endphp

  <div class="cust-order-item" data-status="{{ $o->status }}" data-search="{{ strtolower($o->product_name . ' ' . $o->id) }}">
  <div class="card mb-3">
    <div class="card-body p-0">

      {{-- Header --}}
      <div class="d-flex flex-wrap align-items-center justify-content-between p-3 border-bottom">
        <div class="d-flex align-items-center gap-3">
          <img src="{{ $o->image_path }}" alt="{{ $o->product_name }}"
               style="width:56px;height:56px;object-fit:cover;border-radius:.7rem"
               onerror="this.src='https://placehold.co/56x56/fce4ec/e91e63?text=🎂'">
          <div>
            <div class="fw-bold">{{ $o->product_name }}</div>
            <div class="text-muted small">Order #{{ $o->id }} &bull; {{ \Carbon\Carbon::parse($o->created_at)->format('M d, Y') }}</div>
          </div>
        </div>
        <div class="text-end mt-2 mt-sm-0">
          <span class="status-badge status-{{ str_replace(' ','-',$o->status) }}">{{ $o->status }}</span>
          <div class="fw-bold mt-1">₱{{ number_format($o->total_price,2) }}</div>
        </div>
      </div>

      {{-- Cancel Request Status Banner --}}
      @if($hasPending)
      <div class="px-3 py-2 d-flex align-items-center gap-2" style="background:#fff3cd">
        <i class="bi bi-hourglass-split text-warning"></i>
        <span class="small fw-semibold text-warning">Cancel request pending — waiting for admin approval.</span>
      </div>
      @elseif($wasAccepted)
      <div class="px-3 py-2 d-flex align-items-center gap-2" style="background:#d1fae5">
        <i class="bi bi-check-circle text-success"></i>
        <span class="small fw-semibold text-success">Cancel request approved.
          @if($o->cancel_admin_note) <span class="fw-normal">{{ $o->cancel_admin_note }}</span> @endif
        </span>
      </div>
      @elseif($wasRejected)
      <div class="px-3 py-2 d-flex align-items-center gap-2" style="background:#fee2e2">
        <i class="bi bi-x-circle text-danger"></i>
        <span class="small fw-semibold text-danger">Cancel request rejected.
          @if($o->cancel_admin_note) <span class="fw-normal">Reason: {{ $o->cancel_admin_note }}</span> @endif
        </span>
      </div>
      @elseif($hasDepositLock && $o->status !== 'Cancelled')
      <div class="px-3 py-2 d-flex align-items-center gap-2" style="background:#eff6ff">
        <i class="bi bi-shield-lock text-primary"></i>
        <span class="small fw-semibold text-primary">Cancellation locked — deposit has already been paid.</span>
      </div>
      @endif

      {{-- Order Details --}}
      <div class="px-3 py-2 border-bottom bg-light small text-muted">
        <div class="row g-2">
          <div class="col-6 col-md-3"><i class="bi bi-box me-1"></i>Qty: <strong class="text-dark">{{ $o->quantity }}</strong></div>
          <div class="col-6 col-md-3"><i class="bi bi-truck me-1"></i>{{ $o->fulfillment_type }}</div>
          @if(!empty($o->discount_type) && (float)($o->discount_amount ?? 0) > 0)
          <div class="col-6 col-md-3">
            <i class="bi bi-tags me-1"></i>{{ \App\Helpers\CakeshopHelper::discountBadgeText($o->discount_type, $o->discount_value) ?? 'Product Discount' }}
          </div>
          @endif
          <div class="col-6 col-md-3">
            <i class="bi bi-credit-card me-1"></i>{{ \App\Helpers\CakeshopHelper::displayPaymentMethod($o->payment_method, $o->fulfillment_type) }}
            <span class="badge rounded-pill ms-1"
                  style="font-size:.7rem;background:{{ $o->payment_status==='Paid'?'#d4edda':($o->payment_status==='Partial Payment'?'#fff3cd':'#fff3cd') }};color:{{ $o->payment_status==='Paid'?'#155724':'#856404' }}">
              {{ $o->payment_status }}
            </span>
          </div>
          @if(($o->deposit_required ?? false) && ($o->deposit_status ?? '') === 'paid')
          <div class="col-12">
            <i class="bi bi-cash-stack me-1" style="color:#16a34a"></i>
            <span style="color:#16a34a;font-weight:600">Deposit paid: ₱{{ number_format($o->deposit_amount,2) }}</span>
            <span class="ms-2" style="color:#d97706;font-weight:600">Balance due on {{ $o->fulfillment_type === 'Delivery' ? 'delivery' : 'pickup' }}: ₱{{ number_format($o->total_price - $o->deposit_amount,2) }}</span>
          </div>
          @elseif(($o->deposit_required ?? false) && ($o->deposit_status ?? '') === 'pending')
          <div class="col-12">
            <i class="bi bi-exclamation-circle me-1" style="color:#880E4F"></i>
            <span style="color:#880E4F;font-weight:600">Deposit required: ₱{{ number_format($o->deposit_amount,2) }} — not yet paid</span>
          </div>
          @endif
          @if($o->schedule_date)
          <div class="col-6 col-md-3"><i class="bi bi-calendar me-1"></i>{{ \Carbon\Carbon::parse($o->schedule_date)->format('M d') }}</div>
          @endif
        </div>
        @if($o->custom_note)
          <div class="mt-1"><i class="bi bi-chat-left-text me-1"></i><em>{{ $o->custom_note }}</em></div>
        @endif
        {{-- Add-ons --}}
        @if(isset($orderAddons[$o->id]) && count($orderAddons[$o->id]) > 0)
        <div class="mt-2 pt-2" style="border-top:1px dashed #dee2e6">
          <div class="text-muted mb-1" style="font-size:.72rem;font-weight:600">🎨 ADD-ONS SELECTED:</div>
          <div class="d-flex flex-wrap gap-1">
            @foreach($orderAddons[$o->id] as $oa)
              <span class="badge" style="background:#fff0f5;color:var(--primary);font-size:.72rem;font-weight:500">
                {{ $oa->addon_name }}
                @if($oa->addon_price > 0) +₱{{ number_format($oa->addon_price,2) }} @else FREE @endif
              </span>
            @endforeach
          </div>
        </div>
        @endif
      </div>

      {{-- ── CUSTOM ORDER BANNER (if this is a custom order) ──────────── --}}
      @if(isset($customOrderData[$o->id]))
      @php
        $co = $customOrderData[$o->id];
        $coRefImgs = [];
        if ($co->reference_images) {
          $dec = json_decode($co->reference_images, true);
          $coRefImgs = is_array($dec) ? $dec : [$co->reference_images];
        }
        $coStatusMap = [
          'pending'  => ['bg'=>'#fff3cd','color'=>'#856404','icon'=>'bi-hourglass-split','label'=>'Awaiting Admin Review'],
          'approved' => ['bg'=>'#d1fae5','color'=>'#065f46','icon'=>'bi-check-circle','label'=>'Approved'],
          'rejected' => ['bg'=>'#fee2e2','color'=>'#991b1b','icon'=>'bi-x-circle','label'=>'Not Approved'],
        ];
        $coSc = $coStatusMap[$co->review_status] ?? $coStatusMap['pending'];
      @endphp
      <div class="px-3 py-3 border-top" style="background:#fffbf5">
        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
          <div class="fw-semibold small" style="color:var(--primary)">
            <i class="bi bi-palette me-1"></i>Custom Cake Order
          </div>
          <span class="badge px-2 py-1" style="background:{{ $coSc['bg'] }};color:{{ $coSc['color'] }};font-size:.72rem">
            <i class="bi {{ $coSc['icon'] }} me-1"></i>{{ $coSc['label'] }}
          </span>
        </div>

        {{-- Reference images --}}
        @if(count($coRefImgs) > 0)
        <div class="d-flex gap-2 flex-wrap mb-2">
          @foreach($coRefImgs as $rImg)
          <img src="{{ $rImg }}"
               class="chat-img" data-src="{{ $rImg }}"
               style="width:60px;height:60px;object-fit:cover;border-radius:.5rem;cursor:zoom-in;border:2px solid #fce7f3"
               onclick="openLightbox(this)" onerror="this.style.display='none'">
          @endforeach
        </div>
        @endif

        {{-- Quick details --}}
        <div class="d-flex flex-wrap gap-2 mb-2" style="font-size:.78rem">
          @if($co->cake_name)<span class="badge bg-light text-dark">🎂 {{ $co->cake_name }}</span>@endif
          @if($co->flavor)<span class="badge bg-light text-dark">🍫 {{ $co->flavor }}</span>@endif
          @if($co->size_label)<span class="badge bg-light text-dark">📏 {{ $co->size_label }}</span>@endif
          @if($co->layers)<span class="badge bg-light text-dark">🔢 {{ $co->layers }}</span>@endif
          @if($co->design_complexity)<span class="badge bg-light text-dark">✨ {{ $co->design_complexity }}</span>@endif
        </div>

        {{-- Admin approved price --}}
        @if($co->review_status === 'approved' && $co->admin_price)
        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
          <span class="text-muted small">Final Price:</span>
          <span class="fw-bold" style="color:var(--primary)">₱{{ number_format($co->admin_price,2) }}</span>
          @if(isset($co->price_confirmed))
            @if($co->price_confirmed === 'pending')
              <span class="badge" style="background:#fff3cd;color:#856404;font-size:.7rem">⏳ Awaiting your confirmation</span>
            @elseif($co->price_confirmed === 'accepted')
              <span class="badge bg-success" style="font-size:.7rem">✅ You accepted</span>
            @endif
          @endif
        </div>

        {{-- ── DEPOSIT — one-click payment card ───────────────────── --}}
        @if(isset($co->review_status) && $co->review_status === 'approved'
            && isset($co->admin_price) && $co->admin_price > 0
            && isset($co->price_confirmed) && $co->price_confirmed === 'accepted'
            && isset($o) && $o->payment_status === 'Unpaid'
            && $o->deposit_status !== 'paid'
            && in_array($o->status, ['Pending','Pending Review','Confirmed']))
        @php
          $coTotal = (float)($co->admin_price ?? $o->total_price);
          $minDep  = round($coTotal * 0.5, 2);
        @endphp
        @if($o->payment_method === 'GCash')
        <div class="mt-2" style="border-radius:.85rem;overflow:hidden;border:1.5px solid #d1fae5">
          <div style="background:linear-gradient(90deg,#059669,#0284c7);padding:.6rem 1rem;display:flex;align-items:center;gap:.5rem">
            <i class="bi bi-shield-lock-fill" style="color:#fff;font-size:.9rem"></i>
            <span style="color:#fff;font-weight:700;font-size:.82rem;flex:1">Secure Your Order — Pay via GCash</span>
            @php $pmMode = \App\Helpers\CakeshopHelper::getPaymongoMode(); @endphp
            @if($pmMode === 'test')
              <span style="background:rgba(255,255,255,.22);color:#fef9c3;border-radius:20px;padding:1px 8px;font-size:.62rem;font-weight:700">TEST</span>
            @else
              <span style="background:rgba(255,255,255,.22);color:#d1fae5;border-radius:20px;padding:1px 8px;font-size:.62rem;font-weight:700">LIVE</span>
            @endif
          </div>
          <div style="background:#f8fffe;padding:.85rem">
            <div style="background:#fff;border-radius:.6rem;padding:.45rem .8rem;margin-bottom:.7rem;display:flex;align-items:center;justify-content:space-between;border:1px solid #e5e7eb">
              <span style="font-size:.7rem;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Final Price</span>
              <span style="font-weight:800;color:#111827;font-size:.95rem">₱{{ number_format($coTotal, 2) }}</span>
            </div>
            <div class="d-flex flex-column gap-2">
              <form action="{{ route('customer.custom_orders.set_deposit', $co->id) }}" method="POST"
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
                <div style="font-size:.66rem;color:#6b7280;margin-top:.25rem">
                  Enter at least ₱{{ number_format($minDep, 2) }}. You may pay more up to ₱{{ number_format($coTotal, 2) }}.
                </div>
                <button type="submit" class="btn w-100 fw-bold py-2"
                        style="background:linear-gradient(135deg,#059669,#047857);color:#fff;border:none;border-radius:.7rem;font-size:.88rem"
                        data-cs-confirm="Pay 50% deposit of ₱{{ number_format($minDep,2) }} via GCash?\n\nYou'll be redirected to PayMongo. GCash is pre-selected and your phone number is pre-filled."
                        data-cs-title="Pay Deposit — ₱{{ number_format($minDep,2) }}"
                        data-cs-ok="Pay Now"
                        data-cs-icon="bi-phone-fill"
                        data-cs-icon-bg="#d1fae5"
                        data-cs-icon-color="#059669">
                  <i class="bi bi-phone-fill me-1"></i>Pay Deposit via GCash
                </button>
                <div style="font-size:.66rem;color:#6b7280;text-align:center;margin-top:.25rem">
                  Remaining: ₱{{ number_format($coTotal - $minDep, 2) }} (paid on {{ $o->fulfillment_type === 'Delivery' ? 'delivery' : 'pickup' }})
                </div>
              </form>
              <form action="{{ route('customer.custom_orders.set_deposit', $co->id) }}" method="POST">
                @csrf
                <input type="hidden" name="deposit_amount" value="{{ $coTotal }}">
                <button type="submit" class="btn w-100 fw-semibold py-1"
                        style="background:#fff;color:#059669;border:1.5px solid #059669;border-radius:.7rem;font-size:.82rem"
                        data-cs-confirm="Pay full amount of ₱{{ number_format($coTotal,2) }} via GCash?\n\nYou'll be redirected to PayMongo."
                        data-cs-title="Pay in Full — ₱{{ number_format($coTotal,2) }}"
                        data-cs-ok="Pay in Full"
                        data-cs-icon="bi-wallet2"
                        data-cs-icon-bg="#d1fae5"
                        data-cs-icon-color="#059669">
                  <i class="bi bi-wallet2 me-1"></i>Pay in Full — ₱{{ number_format($coTotal, 2) }}
                </button>
              </form>
            </div>
            <div style="margin-top:.6rem;font-size:.65rem;color:#9ca3af;text-align:center">
              <i class="bi bi-shield-check me-1" style="color:#22c55e"></i>Secured by PayMongo &nbsp;·&nbsp; GCash only &nbsp;·&nbsp; Phone pre-filled
            </div>
          </div>
        </div>
        @else
        <div class="mt-2 p-2 rounded-3" style="background:#fffbeb;border:1.5px solid #fbbf24">
          <div class="fw-semibold small mb-1"><i class="bi bi-cash me-1" style="color:#d97706"></i>COD Deposit — ₱{{ number_format($minDep,2) }}</div>
          <form action="{{ route('customer.custom_orders.set_deposit', $co->id) }}" method="POST">
            @csrf
            <input type="hidden" name="deposit_amount" value="{{ $minDep }}">
            <button type="submit" class="btn w-100 fw-semibold btn-warning btn-sm text-dark"
                    data-cs-confirm="Acknowledge COD deposit of ₱{{ number_format($minDep,2) }}?"
                    data-cs-title="Confirm COD Deposit"
                    data-cs-ok="Acknowledge"
                    data-cs-icon="bi-cash-stack"
                    data-cs-icon-bg="#fef3c7"
                    data-cs-icon-color="#b45309">
              <i class="bi bi-cash me-1"></i>Acknowledge COD Deposit &amp; Confirm
            </button>
          </form>
        </div>
        @endif
        @endif

        {{-- Price confirmation action (if pending) --}}
        @if(isset($co->price_confirmed) && $co->price_confirmed === 'pending')
        <div class="p-2 rounded-2 mb-2" style="background:#fffbeb;border:1.5px solid #fbbf24;border-radius:.7rem">
          <div class="fw-semibold small mb-1" style="color:#d97706">
            <i class="bi bi-exclamation-circle me-1"></i>Price Update — Please Respond
          </div>
          <div class="small text-muted mb-2">
            The baker has set a final price of <strong style="color:var(--primary)">₱{{ number_format($co->admin_price,2) }}</strong>
            for your custom cake. Please accept or cancel.
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <form action="{{ route('customer.custom_orders.accept_price', $co->id) }}" method="POST" class="d-inline">
              @csrf
              <button type="submit" class="btn btn-success btn-sm"
                      data-cs-confirm="Accept ₱{{ number_format($co->admin_price,2) }} as the final price?"
                      data-cs-title="Accept Final Price"
                      data-cs-ok="Accept Price"
                      data-cs-icon="bi-check-circle"
                      data-cs-icon-bg="#dcfce7"
                      data-cs-icon-color="#16a34a">
                <i class="bi bi-check-circle me-1"></i>✅ Accept Price
              </button>
            </form>
            <form action="{{ route('customer.custom_orders.cancel_price', $co->id) }}" method="POST" class="d-inline">
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
        @endif

        {{-- Admin comment (approve or reject message) --}}
        @if($co->admin_comment)
        <div class="p-2 rounded-2 small mb-2"
             style="background:{{ $co->review_status==='approved' ? '#f0fdf4' : '#fef2f2' }};border-left:3px solid {{ $co->review_status==='approved' ? '#22c55e' : '#ef4444' }}">
          <span class="fw-semibold">{{ $co->review_status==='approved' ? '✅ Baker:' : '❌ Reason:' }}</span>
          {{ $co->admin_comment }}
        </div>
        @endif

        {{-- Progress photo from admin --}}
        @if($co->progress_image || $co->progress_message)
        <div class="p-2 rounded-2 mb-2" style="background:#f0f4ff;border-left:3px solid #6366f1">
          <div class="fw-semibold small mb-1" style="color:#4f46e5">📸 Progress Update from Baker</div>
          @if($co->progress_image)
          <img src="{{ $co->progress_image }}"
               class="chat-img" data-src="{{ $co->progress_image }}"
               style="max-height:120px;border-radius:.5rem;cursor:zoom-in;display:block;margin-bottom:4px"
               onclick="openLightbox(this)">
          @endif
          @if($co->progress_message)
          <div class="small text-muted">{{ str_replace('[custom_order:'.$co->id.']', '', $co->progress_message) }}</div>
          @endif
          {{-- Button to view full custom order details --}}
          <button class="btn btn-sm btn-outline-primary mt-2"
                  data-bs-toggle="modal" data-bs-target="#coDetailModal{{ $co->id }}">
            <i class="bi bi-eye me-1"></i>View My Custom Order Details
          </button>
        </div>
        @endif

        {{-- Follow-up button for rejected orders --}}
        @if($co->review_status === 'rejected')
        <div class="d-flex gap-2 flex-wrap mt-2">
          <button class="btn btn-sm btn-outline-primary"
                  onclick="sendFollowUp({{ $o->id }}, '{{ addslashes($co->cake_name) }}', {{ $co->id }})">
            <i class="bi bi-chat-dots me-1"></i>Follow Up with Admin
          </button>
          <button class="btn btn-sm btn-outline-secondary"
                  onclick="copyFollowUp({{ $o->id }}, '{{ addslashes($co->cake_name) }}', {{ $co->id }})">
            <i class="bi bi-clipboard me-1"></i>Copy Message
          </button>
        </div>
        @endif

        {{-- View full details button (always visible for custom orders) --}}
        <button class="btn btn-sm btn-link px-0 mt-1" style="color:var(--primary);font-size:.78rem"
                data-bs-toggle="modal" data-bs-target="#coDetailModal{{ $co->id }}">
          <i class="bi bi-info-circle me-1"></i>View Full Custom Order Details
        </button>
      </div>

      {{-- Custom Order Detail Modal --}}
      <div class="modal fade" id="coDetailModal{{ $co->id }}" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
          <div class="modal-content border-0" style="border-radius:1.2rem">
            <div class="modal-header border-0 pb-0">
              <h5 class="modal-title fw-bold">
                <i class="bi bi-palette me-2" style="color:var(--primary)"></i>My Custom Order #{{ $o->id }}
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

              {{-- Status --}}
              <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge px-3 py-2" style="background:{{ $coSc['bg'] }};color:{{ $coSc['color'] }}">
                  <i class="bi {{ $coSc['icon'] }} me-1"></i>{{ $coSc['label'] }}
                </span>
                @if($co->admin_price)
                <span class="fw-bold" style="color:var(--primary)">₱{{ number_format($co->admin_price,2) }}</span>
                @if(isset($co->price_confirmed) && $co->price_confirmed === 'pending')
                  <span class="badge" style="background:#fff3cd;color:#856404;font-size:.7rem">⏳ Needs your response</span>
                @endif
                @endif
              </div>

              {{-- Price confirmation in modal --}}
              @if($co->review_status === 'approved' && isset($co->price_confirmed) && $co->price_confirmed === 'pending')
              <div class="p-3 rounded-2 mb-3" style="background:#fffbeb;border:1.5px solid #fbbf24">
                <div class="fw-semibold small mb-2" style="color:#d97706">
                  <i class="bi bi-exclamation-circle me-1"></i>Please confirm the final price
                </div>
                <div class="small text-muted mb-3">
                  The baker set a final price of <strong style="color:var(--primary)">₱{{ number_format($co->admin_price,2) }}</strong>.
                  Accept to proceed or cancel the order.
                </div>
                <div class="d-flex gap-2 flex-wrap">
                  <form action="{{ route('customer.custom_orders.accept_price', $co->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm"
                            data-cs-confirm="Accept ₱{{ number_format($co->admin_price,2) }} as final price?"
                            data-cs-title="Accept Final Price"
                            data-cs-ok="Accept Price"
                            data-cs-icon="bi-check-circle"
                            data-cs-icon-bg="#dcfce7"
                            data-cs-icon-color="#16a34a">
                      <i class="bi bi-check-circle me-1"></i>✅ Accept Price
                    </button>
                  </form>
                  <form action="{{ route('customer.custom_orders.cancel_price', $co->id) }}" method="POST" class="d-inline">
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

              {{-- Reference Images --}}
              @if(count($coRefImgs) > 0)
              <div class="mb-3">
                <div class="fw-semibold small mb-2" style="color:var(--primary)">
                  <i class="bi bi-images me-1"></i>My Reference Images
                </div>
                <div class="d-flex flex-wrap gap-2">
                  @foreach($coRefImgs as $rImg)
                  <img src="{{ $rImg }}"
                       class="chat-img" data-src="{{ $rImg }}"
                       style="width:100px;height:100px;object-fit:cover;border-radius:.6rem;cursor:zoom-in;border:2px solid #fce7f3"
                       onclick="openLightbox(this)" onerror="this.style.display='none'">
                  @endforeach
                </div>
              </div>
              @endif

              <hr class="my-3">

              {{-- Details --}}
              <div class="row g-2 mb-3">
                <div class="col-6"><div class="p-2 rounded-2" style="background:#f8f9fa"><div class="text-muted" style="font-size:.68rem;text-transform:uppercase">Cake Name</div><div class="fw-semibold small">{{ $co->cake_name }}</div></div></div>
                @if($co->flavor)<div class="col-6"><div class="p-2 rounded-2" style="background:#f8f9fa"><div class="text-muted" style="font-size:.68rem;text-transform:uppercase">Flavor</div><div class="fw-semibold small">{{ $co->flavor }}</div></div></div>@endif
                @if($co->size_label)<div class="col-6"><div class="p-2 rounded-2" style="background:#f8f9fa"><div class="text-muted" style="font-size:.68rem;text-transform:uppercase">Size</div><div class="fw-semibold small">{{ $co->size_label }}</div></div></div>@endif
                @if($co->layers)<div class="col-6"><div class="p-2 rounded-2" style="background:#f8f9fa"><div class="text-muted" style="font-size:.68rem;text-transform:uppercase">Layers</div><div class="fw-semibold small">{{ $co->layers }}</div></div></div>@endif
                @if($co->design_complexity)<div class="col-6"><div class="p-2 rounded-2" style="background:#f8f9fa"><div class="text-muted" style="font-size:.68rem;text-transform:uppercase">Design</div><div class="fw-semibold small">{{ $co->design_complexity }}</div></div></div>@endif
                @if($co->time_slot)<div class="col-6"><div class="p-2 rounded-2" style="background:#f8f9fa"><div class="text-muted" style="font-size:.68rem;text-transform:uppercase">Time Slot</div><div class="fw-semibold small">{{ $co->time_slot }}</div></div></div>@endif
                @if($co->dedication)<div class="col-12"><div class="p-2 rounded-2" style="background:#f8f9fa"><div class="text-muted" style="font-size:.68rem;text-transform:uppercase">Dedication</div><div class="fw-semibold small">"{{ $co->dedication }}"</div></div></div>@endif
                @if($co->custom_note)<div class="col-12"><div class="p-2 rounded-2" style="background:#f8f9fa"><div class="text-muted" style="font-size:.68rem;text-transform:uppercase">My Notes</div><div class="small text-muted">{{ $co->custom_note }}</div></div></div>@endif
              </div>

              {{-- Add-ons --}}
              @if(isset($orderAddons[$o->id]) && count($orderAddons[$o->id]) > 0)
              <div class="mb-3">
                <div class="fw-semibold small mb-1" style="color:var(--primary)"><i class="bi bi-gift me-1"></i>Add-ons</div>
                <div class="d-flex flex-wrap gap-1">
                  @foreach($orderAddons[$o->id] as $oa)
                  <span class="badge" style="background:#f0fdf4;color:#166534;font-size:.75rem">
                    {{ $oa->addon_name }}@if($oa->addon_price > 0) +₱{{ number_format($oa->addon_price,2) }}@endif
                  </span>
                  @endforeach
                </div>
              </div>
              @endif

              {{-- Admin comment --}}
              @if($co->admin_comment)
              <div class="p-2 rounded-2 mb-3"
                   style="background:{{ $co->review_status==='approved' ? '#f0fdf4' : '#fef2f2' }};border-left:3px solid {{ $co->review_status==='approved' ? '#22c55e' : '#ef4444' }}">
                <div class="fw-semibold small mb-1">{{ $co->review_status==='approved' ? '✅ Message from Baker:' : '❌ Reason for Rejection:' }}</div>
                <div class="small text-muted">{{ $co->admin_comment }}</div>
              </div>
              @endif

              {{-- Progress photo --}}
              @if($co->progress_image || $co->progress_message)
              <div class="p-2 rounded-2 mb-3" style="background:#f0f4ff;border-left:3px solid #6366f1">
                <div class="fw-semibold small mb-2" style="color:#4f46e5">📸 Progress from Baker</div>
                @if($co->progress_image)
                <img src="{{ $co->progress_image }}"
                     class="chat-img" data-src="{{ $co->progress_image }}"
                     style="max-width:100%;border-radius:.6rem;cursor:zoom-in;display:block;margin-bottom:6px"
                     onclick="openLightbox(this)">
                @endif
                @if($co->progress_message)
                <div class="small text-muted">{{ str_replace('[custom_order:'.$co->id.']', '', $co->progress_message) }}</div>
                @endif
              </div>
              @endif

              {{-- Follow up buttons if rejected --}}
              @if($co->review_status === 'rejected')
              <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-primary btn-sm"
                        onclick="sendFollowUp({{ $o->id }}, '{{ addslashes($co->cake_name) }}', {{ $co->id }});bootstrap.Modal.getInstance(document.getElementById('coDetailModal{{ $co->id }}')).hide()">
                  <i class="bi bi-chat-dots me-1"></i>Send Follow-up to Admin
                </button>
                <button class="btn btn-outline-secondary btn-sm"
                        onclick="copyFollowUp({{ $o->id }}, '{{ addslashes($co->cake_name) }}', {{ $co->id }})">
                  <i class="bi bi-clipboard me-1"></i>Copy Message
                </button>
              </div>
              @endif

            </div>
          </div>
        </div>
      </div>
      @endif{{-- end custom order banner --}}
      @php $steps = ['Pending','Confirmed','Preparing','Out for Delivery','Delivered']; @endphp
      @if($o->status === 'Awaiting Deposit')
      <div class="px-3 py-3">
        <div style="border-radius:.85rem;overflow:hidden;border:1.5px solid #fed7aa">
          <div style="background:linear-gradient(90deg,#d97706,#ea580c);padding:.6rem 1rem;display:flex;align-items:center;gap:.5rem">
            <i class="bi bi-shield-lock-fill" style="color:#fff;font-size:.9rem"></i>
            <span style="color:#fff;font-weight:700;font-size:.82rem;flex:1">Deposit Required — Activate Your Order</span>
            @php $pmMode = \App\Helpers\CakeshopHelper::getPaymongoMode(); @endphp
            @if($pmMode === 'test')
              <span style="background:rgba(255,255,255,.22);color:#fef9c3;border-radius:20px;padding:1px 8px;font-size:.62rem;font-weight:700">TEST</span>
            @else
              <span style="background:rgba(255,255,255,.22);color:#fef3c7;border-radius:20px;padding:1px 8px;font-size:.62rem;font-weight:700">LIVE</span>
            @endif
          </div>
          <div style="background:#fffbeb;padding:.85rem">
            <div style="background:#fff;border-radius:.6rem;padding:.45rem .8rem;margin-bottom:.5rem;display:flex;align-items:center;justify-content:space-between;border:1px solid #fde68a">
              <span style="font-size:.7rem;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Deposit to Pay</span>
              <span style="font-weight:800;color:#111827;font-size:.95rem">₱{{ number_format($o->deposit_amount, 2) }}</span>
            </div>
            @if($o->deposit_amount < $o->total_price)
            <div style="font-size:.7rem;color:#9a3412;margin-bottom:.6rem;text-align:right">
              Remaining after payment: ₱{{ number_format($o->total_price - $o->deposit_amount, 2) }} (on {{ $o->fulfillment_type === 'Delivery' ? 'delivery' : 'pickup' }})
            </div>
            @endif
            <a href="{{ route('customer.pay_deposit', $o->id) }}"
               class="btn w-100 fw-bold py-2"
               style="background:linear-gradient(135deg,#d97706,#b45309);color:#fff;border:none;border-radius:.7rem;font-size:.88rem"
               data-cs-confirm="Pay deposit of ₱{{ number_format($o->deposit_amount,2) }} via GCash?\n\nYou'll be redirected to PayMongo. GCash is pre-selected."
               data-cs-title="Pay Deposit — ₱{{ number_format($o->deposit_amount,2) }}"
               data-cs-ok="Pay Now"
               data-cs-icon="bi-phone-fill"
               data-cs-icon-bg="#fef3c7"
               data-cs-icon-color="#d97706">
              <i class="bi bi-phone-fill me-2"></i>Pay Deposit — ₱{{ number_format($o->deposit_amount, 2) }} via GCash
            </a>
            <div style="margin-top:.5rem;font-size:.65rem;color:#9ca3af;text-align:center">
              <i class="bi bi-shield-check me-1" style="color:#22c55e"></i>Secured by PayMongo &nbsp;·&nbsp; GCash only
            </div>
          </div>
        </div>
      </div>
      @elseif($o->status !== 'Cancelled')
      {{-- Deposit paid — show balance reminder --}}
      @if(($o->deposit_required ?? false) && ($o->deposit_status ?? '') === 'paid')
      <div class="px-3 pt-3">
        <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background:#E8F5E9;border:1.5px solid #A5D6A7">
          <i class="bi bi-check-circle-fill" style="font-size:1.4rem;color:#2E7D32;flex-shrink:0"></i>
          <div class="small">
            <div class="fw-bold" style="color:#2E7D32">Deposit Paid — ₱{{ number_format($o->deposit_amount,2) }}</div>
            <div style="color:#388E3C">Remaining balance of <strong>₱{{ number_format($o->total_price - $o->deposit_amount,2) }}</strong> is due upon {{ $o->fulfillment_type === 'Delivery' ? 'delivery (Cash on Delivery)' : 'pickup (Cash on Pickup)' }}.</div>
          </div>
        </div>
      </div>
      @endif
      <div class="px-3 py-3">
        <p class="small fw-semibold text-muted mb-2"><i class="bi bi-geo-alt me-1"></i>Order Tracking</p>
        <div class="d-flex align-items-start overflow-auto pb-1">
          @php
            $statusOrder = ['Pending'=>0,'Confirmed'=>1,'Preparing'=>2,'Out for Delivery'=>3,'Delivered'=>4];
            $currentIdx  = $statusOrder[$o->status] ?? 0;
            $icons = ['bi-clock','bi-check-circle','bi-egg-fried','bi-bicycle','bi-house-check'];
          @endphp
          @foreach($steps as $si => $step)
          @php $done = $si <= $currentIdx; $active = $si === $currentIdx; @endphp
          <div class="text-center flex-shrink-0" style="min-width:80px">
            <div style="width:36px;height:36px;border-radius:50%;margin:0 auto 4px;display:flex;align-items:center;justify-content:center;
              background:{{ $done ? 'var(--primary)' : '#e9ecef' }};border:2px solid {{ $done ? 'var(--primary)' : '#dee2e6' }}">
              <i class="bi {{ $icons[$si] }} {{ $done ? 'text-white' : 'text-muted' }}" style="font-size:.8rem"></i>
            </div>
            <div class="small fw-{{ $active ? 'bold' : 'normal' }}"
                 style="color:{{ $done ? 'var(--primary)' : '#aaa' }};font-size:.68rem;line-height:1.2">{{ $step }}</div>
            @if(isset($tracking[$o->id]))
              @foreach($tracking[$o->id] as $t)
                @if($t->status === $step)
                  <div class="text-muted" style="font-size:.6rem">{{ \Carbon\Carbon::parse($t->created_at)->format('M d H:i') }}</div>
                @endif
              @endforeach
            @endif
          </div>
          @if($si < count($steps)-1)
          <div class="flex-grow-1" style="height:2px;background:{{ $si < $currentIdx ? 'var(--primary)' : '#dee2e6' }};margin-top:17px;min-width:20px"></div>
          @endif
          @endforeach
        </div>
      </div>
      @else
      <div class="px-3 py-2">
        <span class="badge status-Cancelled px-3 py-2"><i class="bi bi-x-circle me-1"></i>Order Cancelled</span>
      </div>
      @endif

      {{-- ⭐ Review Section (only for Delivered orders) --}}
      @if($o->status === 'Delivered')
      @php
        $hasReview = isset($orderReviews[$o->id]);
      @endphp
      @if(!$hasReview)
      <div class="px-3 py-3 border-top" style="background:#fffbeb">
        <div class="d-flex align-items-center gap-2 mb-2">
          <i class="bi bi-star-fill text-warning"></i>
          <span class="small fw-semibold">Rate this order</span>
        </div>
        <form action="{{ route('customer.orders.review', $o->id) }}" method="POST" enctype="multipart/form-data">
          @csrf
          <div class="d-flex gap-1 mb-2" id="stars_{{ $o->id }}">
            @for($s=1;$s<=5;$s++)
            <label class="star-label" style="cursor:pointer;font-size:1.5rem;color:#d1d5db"
                   onmouseover="highlightStars({{ $o->id }},{{ $s }})"
                   onmouseout="resetStars({{ $o->id }})">
              <input type="radio" name="rating" value="{{ $s }}" class="d-none"
                     onchange="selectStar({{ $o->id }},{{ $s }})">
              ★
            </label>
            @endfor
          </div>
          {{-- Rider Rating (only for Delivery orders) --}}
          @if($o->fulfillment_type === 'Delivery')
          <div class="mb-2">
            <div class="small fw-semibold mb-1"><i class="bi bi-bicycle me-1" style="color:var(--primary)"></i>Rate your Rider <span class="text-muted fw-normal">(optional)</span></div>
            <div class="d-flex gap-1" id="rider_stars_{{ $o->id }}">
              @for($s=1;$s<=5;$s++)
              <label class="star-label" style="cursor:pointer;font-size:1.3rem;color:#d1d5db"
                     onmouseover="highlightStars('rider_{{ $o->id }}',{{ $s }})"
                     onmouseout="resetStars('rider_{{ $o->id }}')">
                <input type="radio" name="rider_rating" value="{{ $s }}" class="d-none"
                       onchange="selectStar('rider_{{ $o->id }}',{{ $s }})">
                ★
              </label>
              @endfor
            </div>
          </div>
          @endif
          <textarea class="form-control form-control-sm mb-2" name="review" rows="2"
                    placeholder="Share your experience (optional)…"></textarea>
          {{-- Photo upload --}}
          <div class="mb-2">
            <label class="form-label small fw-semibold mb-1">
              <i class="bi bi-camera me-1" style="color:var(--primary)"></i>Add Photo <span class="text-muted fw-normal">(optional)</span>
            </label>
            <input type="file" class="form-control form-control-sm" name="review_image"
                   accept="image/jpeg,image/png,image/webp"
                   onchange="previewReviewImage(this, {{ $o->id }})">
            <img id="reviewPreview{{ $o->id }}" src="" alt=""
                 style="display:none;width:70px;height:70px;object-fit:cover;border-radius:.5rem;margin-top:6px;border:2px solid #fce7f3">
          </div>
          <button type="submit" class="btn btn-warning btn-sm">
            <i class="bi bi-star me-1"></i>Submit Review
          </button>
        </form>
      </div>
      @else
      <div class="px-3 py-2 border-top d-flex align-items-center gap-2" style="background:#fffbeb">
        <div class="d-flex gap-1">
          @for($s=1;$s<=5;$s++)
            <span style="color:{{ $s <= $orderReviews[$o->id]->rating ? '#f59e0b' : '#d1d5db' }};font-size:1.1rem">★</span>
          @endfor
        </div>
        <span class="small text-muted">
          You rated this {{ $orderReviews[$o->id]->rating }}/5
          @if($orderReviews[$o->id]->review) — "{{ Str::limit($orderReviews[$o->id]->review, 60) }}" @endif
        </span>
      </div>
      @endif
      @endif

      {{-- Actions --}}
      <div class="px-3 pb-3 d-flex gap-2 flex-wrap align-items-center">
        <a href="{{ route('customer.messages.thread', $o->id) }}" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-chat-dots me-1"></i>Message Admin
        </a>

        @if($o->payment_method === 'GCash' && $o->payment_status === 'Unpaid' && $o->status !== 'Cancelled')
          <a href="{{ route('customer.pay_gcash', ['order_id'=>$o->id]) }}" class="btn btn-warning btn-sm">
            <i class="bi bi-phone me-1"></i>Pay via GCash
          </a>
        @endif

        @if(($o->deposit_required ?? false) && ($o->deposit_status ?? '') === 'pending' && $o->status === 'Awaiting Deposit')
          <a href="{{ route('customer.pay_deposit', $o->id) }}"
             class="btn btn-sm fw-bold"
             style="background:linear-gradient(135deg,#d97706,#b45309);color:#fff;border:none;border-radius:.6rem">
            <i class="bi bi-phone-fill me-1"></i>Pay Deposit — ₱{{ number_format($o->deposit_amount, 2) }}
          </a>
        @endif

        {{-- CANCEL REQUEST BUTTON --}}
        @if($canCancel && !$hasPending && !$wasAccepted)
          <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cancelModal{{ $o->id }}">
            <i class="bi bi-x-circle me-1"></i>Request Cancel
          </button>
        @elseif($hasDepositLock && $o->status !== 'Cancelled')
          <span class="text-muted small"><i class="bi bi-lock me-1"></i>Cannot cancel — deposit already paid</span>
        @elseif($notAllowed && $o->status !== 'Cancelled')
          <span class="text-muted small"><i class="bi bi-lock me-1"></i>Cannot cancel — order is {{ $o->status }}</span>
        @elseif($hasPending)
          <span class="badge" style="background:#fff3cd;color:#856404;font-size:.78rem;padding:.4rem .8rem">
            <i class="bi bi-hourglass-split me-1"></i>Cancel Pending
          </span>
        @endif
      </div>
    </div>
  </div>

  {{-- Cancel Request Modal --}}
  @if($canCancel && !$hasPending && !$wasAccepted)
  <div class="modal fade" id="cancelModal{{ $o->id }}" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0" style="border-radius:1.2rem">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold">
            <i class="bi bi-x-circle me-2 text-danger"></i>Request Cancellation
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          {{-- Order Summary --}}
          <div class="d-flex align-items-center gap-3 p-3 rounded mb-3" style="background:#f8f9fa">
            <img src="{{ $o->image_path }}" style="width:48px;height:48px;object-fit:cover;border-radius:.6rem"
                 onerror="this.src='https://placehold.co/48x48/fce4ec/e91e63?text=🎂'">
            <div>
              <div class="fw-semibold small">{{ $o->product_name }}</div>
              <div class="text-muted small">Order #{{ $o->id }} &bull; ₱{{ number_format($o->total_price,2) }} &bull; Qty: {{ $o->quantity }}</div>
              <div class="text-muted small">{{ $o->fulfillment_type }} &bull; {{ \App\Helpers\CakeshopHelper::displayPaymentMethod($o->payment_method, $o->fulfillment_type) }}</div>
            </div>
          </div>

          <div class="alert border-0 py-2 small" style="background:#fff3cd">
            <i class="bi bi-exclamation-triangle me-1 text-warning"></i>
            <strong>Note:</strong> Cancel requests cannot be submitted once the order is <strong>Preparing</strong> or beyond.
          </div>

          <form action="{{ route('customer.orders.cancel_request', $o->id) }}" method="POST">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold small">Reason for Cancellation <span class="text-danger">*</span></label>
              <textarea class="form-control" name="cancel_reason" rows="3" required
                        placeholder="Please explain why you want to cancel this order…"></textarea>
            </div>
            <div class="d-flex gap-2 justify-content-end">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Never mind</button>
              <button type="submit" class="btn btn-danger">
                <i class="bi bi-x-circle me-1"></i>Submit Cancel Request
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  @endif

  </div>{{-- /cust-order-item --}}
  @empty
  <div class="card text-center py-5">
    <div class="card-body">
      <i class="bi bi-bag-x" style="font-size:3rem;color:#ddd"></i>
      <p class="text-muted mt-3 mb-3">No orders yet.</p>
      <a href="{{ route('customer.catalog') }}" class="btn btn-primary">Browse Cakes</a>
    </div>
  </div>
  @endforelse
</div>
{{ $orders->links('vendor.pagination.custom') }}
</div>
@push('scripts')
<script>
// ── Custom Order Follow-up ─────────────────────────────────────────────
function buildFollowUpMsg(orderId, cakeName, coId) {
  return 'Hi! I'm following up on my rejected Custom Cake Order #' + orderId + ' — "' + cakeName + '" (Custom Order #' + coId + '). '
       + 'Can we discuss the details and work something out? I'd love to revise my request. Thank you!';
}

async function sendFollowUp(orderId, cakeName, coId) {
  const msg = buildFollowUpMsg(orderId, cakeName, coId);
  try {
    const fd = new FormData();
    fd.append('message', msg);
    fd.append('order_id', orderId);
    fd.append('_token', '{{ csrf_token() }}');
    await fetch('{{ route("customer.messages.popup_send") }}', { method: 'POST', body: fd });
    cakeToast('✅ Follow-up sent to admin!', 'success');
  } catch(e) {
    cakeToast('Failed to send. Please try again.', 'error');
  }
}

function copyFollowUp(orderId, cakeName, coId) {
  const msg = buildFollowUpMsg(orderId, cakeName, coId);
  navigator.clipboard.writeText(msg).then(() => {
    cakeToast('📋 Message copied to clipboard!', 'success');
  }).catch(() => {
    prompt('Copy this message:', msg);
  });
}

const starSelections = {};
function previewReviewImage(input, orderId) {
  const preview = document.getElementById('reviewPreview' + orderId);
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
    reader.readAsDataURL(input.files[0]);
  }
}

function highlightStars(orderId, count) {
  // supports both 'stars_X' (cake) and 'rider_stars_X' (rider) container IDs
  const isRider = String(orderId).startsWith('rider_');
  const containerId = isRider ? 'rider_stars_' + String(orderId).replace('rider_','') : 'stars_' + orderId;
  const labels = document.querySelectorAll('#' + containerId + ' .star-label');
  const sel = starSelections[orderId] || 0;
  labels.forEach((l, i) => {
    l.style.color = i < count ? '#f59e0b' : (i < sel ? '#f59e0b' : '#d1d5db');
  });
}
function resetStars(orderId) {
  const isRider = String(orderId).startsWith('rider_');
  const containerId = isRider ? 'rider_stars_' + String(orderId).replace('rider_','') : 'stars_' + orderId;
  const labels = document.querySelectorAll('#' + containerId + ' .star-label');
  const sel = starSelections[orderId] || 0;
  labels.forEach((l, i) => { l.style.color = i < sel ? '#f59e0b' : '#d1d5db'; });
}
function selectStar(orderId, count) {
  starSelections[orderId] = count;
  resetStars(orderId);
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
        button.innerHTML = '<i class="bi bi-phone-fill me-1"></i>Pay Deposit via GCash';
        button.dataset.csConfirm = 'Pay deposit of ₱' + (amount || min).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' via GCash?\\n\\nYou will be redirected to PayMongo.';
        button.dataset.csTitle = 'Pay Deposit';
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
</script>
@endpush


@endsection
