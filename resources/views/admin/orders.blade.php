@extends('layouts.app')
@section('content')
@php
  if (!isset($pendingCancelCount)) $pendingCancelCount = 0;
  if (!isset($orderAddons))      $orderAddons = [];
  if (!isset($orderReviews))     $orderReviews = [];
@endphp
<div>
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-bag-check me-2" style="color:var(--primary)"></i>Orders</h4>
      <p class="text-muted small mb-0" id="ordersCountLabel">{{ $orders->total() }} total orders
        @if($pendingCancelCount > 0)
          &nbsp;·&nbsp;<span class="badge bg-danger">{{ $pendingCancelCount }} cancel request{{ $pendingCancelCount > 1 ? 's' : '' }}</span>
        @endif
      </p>
    </div>
    <div class="cs-search-bar" style="flex:1;min-width:0;max-width:280px">
      <input type="text" id="searchInput" class="form-control form-control-sm"
             placeholder="Search customer, order ID…"
             value="{{ $search }}"
             oninput="pgSearch(this.value)">
    </div>
  </div>

  @if(session('msg'))
    <div class="alert alert-success border-0"><i class="bi bi-check-circle me-2"></i>{{ session('msg') }}</div>
  @endif
  @if(session('err'))
    <div class="alert alert-danger border-0"><i class="bi bi-exclamation-circle me-2"></i>{{ session('err') }}</div>
  @endif

  {{-- Fulfillment filter --}}
  <div class="d-flex gap-2 flex-wrap mb-2">
    <span class="text-muted small align-self-center">Filter by type:</span>
    <button class="btn btn-sm btn-primary" id="ftAll" onclick="setFulfillment('all',this)">
      <i class="bi bi-list me-1"></i>All Orders
      <span class="badge rounded-pill ms-1" style="background:rgba(0,0,0,.12)">{{ count($orders) }}</span>
    </button>
    <button class="btn btn-sm btn-outline-info" id="ftDelivery" onclick="setFulfillment('Delivery',this)">
      <i class="bi bi-truck me-1"></i>Delivery
      <span class="badge rounded-pill ms-1" style="background:rgba(0,0,0,.12)">{{ collect($orders)->where('fulfillment_type','Delivery')->count() }}</span>
    </button>
    <button class="btn btn-sm btn-outline-purple" id="ftPickup" onclick="setFulfillment('Pickup',this)" style="border-color:#7c3aed;color:#7c3aed">
      <i class="bi bi-shop me-1"></i>Pickup
      <span class="badge rounded-pill ms-1" style="background:rgba(0,0,0,.12)">{{ collect($orders)->where('fulfillment_type','Pickup')->count() }}</span>
    </button>
  </div>

  {{-- Status filter tabs --}}
  <div class="d-flex gap-2 flex-wrap mb-3" id="filterTabs">
    @foreach(['All'=>'All','Pending'=>'⏳ Pending','Confirmed'=>'✅ Confirmed','Preparing'=>'🍳 Preparing','Out for Delivery'=>'🚴 Out for Delivery','Pickup'=>'🏪 Ready for Pickup','Delivered'=>'🏠 Delivered','Picked Up'=>'🎂 Picked Up','Cancelled'=>'❌ Cancelled','Cancel Requests'=>'🚨 Cancel Requests'] as $val=>$lbl)
    @php $isActive = ($status === $val) || ($val === 'All' && $status === 'All'); @endphp
    <a href="{{ url()->current() }}?status={{ urlencode($val) }}&search={{ urlencode($search) }}"
       class="btn btn-sm {{ $isActive ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $lbl }}
      @if($val === 'Cancel Requests' && $pendingCancelCount > 0)
        <span class="badge rounded-pill bg-danger ms-1">{{ $pendingCancelCount }}</span>
      @endif
    </a>
    @endforeach
  </div>

  <div class="row g-3" id="ordersList">
    @forelse($orders as $o)
    @php
      $isCancelPending = $o->cancel_requested && $o->cancel_status === 'pending';
    @endphp
    <div class="col-12 order-item"
         data-status="{{ $o->status }}"
         data-fulfillment="{{ $o->fulfillment_type }}"
         data-cancel="{{ $isCancelPending ? 'yes' : 'no' }}"
         data-search="{{ strtolower($o->fullname . ' ' . $o->username . ' ' . $o->phone . ' ' . $o->id) }}">
      <div class="card" style="{{ $isCancelPending ? 'border-left:4px solid #ef4444 !important' : '' }}">
        <div class="card-body p-0">

          {{-- Cancel Request Alert Banner --}}
          @if($isCancelPending)
          <div class="px-3 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2"
               style="background:#fee2e2;border-radius:1.1rem 1.1rem 0 0">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-exclamation-triangle-fill text-danger"></i>
              <strong class="small text-danger">Cancel Request — needs your action!</strong>
            </div>
            <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cancelReviewModal{{ $o->id }}">
              <i class="bi bi-eye me-1"></i>Review Request
            </button>
          </div>
          @elseif($o->cancel_status === 'accepted')
          <div class="px-3 py-2 d-flex align-items-center gap-2" style="background:#d1fae5;border-radius:1.1rem 1.1rem 0 0">
            <i class="bi bi-check-circle text-success"></i>
            <span class="small text-success fw-semibold">Cancel request was accepted.</span>
          </div>
          @elseif($o->cancel_status === 'rejected')
          <div class="px-3 py-2 d-flex align-items-center gap-2" style="background:#f3f4f6;border-radius:1.1rem 1.1rem 0 0">
            <i class="bi bi-x-circle text-secondary"></i>
            <span class="small text-secondary fw-semibold">Cancel request was rejected. Reason: {{ $o->cancel_admin_note }}</span>
          </div>
          @endif

          {{-- Order Header --}}
          <div class="d-flex flex-wrap align-items-center justify-content-between p-3 border-bottom">
            <div class="d-flex align-items-center gap-3">
              <img src="{{ $o->image_path }}" style="width:52px;height:52px;object-fit:cover;border-radius:.7rem"
                   onerror="this.src='https://placehold.co/52x52/fce4ec/e91e63?text=🎂'">
              <div>
                <div class="fw-bold">{{ $o->product_name }}
                  <span class="text-muted fw-normal small">×{{ $o->quantity }}</span>
                </div>
                <div class="small text-muted">
                  <i class="bi bi-person me-1"></i><strong>{{ $o->fullname }}</strong>
                  @if($o->username === 'Guest')
                    <span class="badge ms-1" style="background:#f0f9ff;color:#0369a1;font-size:clamp(.62rem,1.2vw,.65rem)">Guest</span>
                  @else
                    (@<span>{{ $o->username }}</span>)
                  @endif
                  &nbsp;·&nbsp;
                  <i class="bi bi-phone me-1"></i>
                  @if($o->phone)
                    <a href="tel:{{ $o->phone }}" class="text-decoration-none text-muted">{{ $o->phone }}</a>
                  @else <span class="text-muted fst-italic">no phone</span> @endif
                </div>
                <div class="small text-muted">
                  <i class="bi bi-hash"></i>{{ $o->id }}
                  &nbsp;·&nbsp;{{ \Carbon\Carbon::parse($o->created_at)->format('M d, Y g:i A') }}
                </div>
              </div>
            </div>
            <div class="text-end mt-2 mt-sm-0">
              {{-- Fulfillment badge --}}
              <span class="badge mb-1" style="{{ $o->fulfillment_type === 'Pickup' ? 'background:#ede9fe;color:#5b21b6' : 'background:#dbeafe;color:#1e40af' }}">
                <i class="bi bi-{{ $o->fulfillment_type === 'Pickup' ? 'shop' : 'truck' }} me-1"></i>{{ $o->fulfillment_type }}
              </span><br>
              <span class="status-badge status-{{ str_replace(' ','-',$o->status) }}">{{ $o->status }}</span>
              <div class="fw-bold mt-1 fs-6">₱{{ number_format($o->total_price,2) }}</div>
              @if($o->deposit_required && $o->deposit_status === 'paid')
              <div class="small" style="color:#16a34a">
                <i class="bi bi-check-circle-fill me-1"></i>Deposit: ₱{{ number_format($o->deposit_amount,2) }}
              </div>
              <div class="small" style="color:#9a3412">
                Remaining: ₱{{ number_format($o->total_price - $o->deposit_amount,2) }}
              </div>
              @endif
              <div class="small text-muted">
                {{ \App\Helpers\CakeshopHelper::displayPaymentMethod($o->payment_method, $o->fulfillment_type) }}
                <span class="badge rounded-pill ms-1"
                      style="font-size:clamp(.66rem,1.3vw,.7rem);background:{{ $o->payment_status==='Paid'?'#d4edda':($o->payment_status==='Partial Payment'?'#dbeafe':'#fff3cd') }};color:{{ $o->payment_status==='Paid'?'#155724':($o->payment_status==='Partial Payment'?'#1e40af':'#856404') }}">
                  {{ $o->payment_status }}
                </span>
              </div>
            </div>
          </div>

          {{-- Add-ons --}}
          @if(isset($orderAddons[$o->id]) && count($orderAddons[$o->id]) > 0)
          <div class="px-3 py-2 small" style="background:#fff5f8;border-top:1px dashed #f9a8d4">
            <span class="fw-semibold me-2" style="color:var(--primary)">🎨 Add-ons:</span>
            @foreach($orderAddons[$o->id] as $oa)
              <span class="badge me-1" style="background:#fff0f5;color:var(--primary);font-size:clamp(.68rem,1.3vw,.72rem)">
                {{ $oa->addon_name }}{{ $oa->addon_price > 0 ? ' +₱'.number_format($oa->addon_price,2) : ' FREE' }}
              </span>
            @endforeach
          </div>
          @endif

          @if(!empty($o->discount_type) && (float)($o->discount_amount ?? 0) > 0)
          <div class="px-3 py-2 small" style="background:#fff7ed;border-top:1px dashed #fdba74">
            <span class="fw-semibold me-2" style="color:#c2410c"><i class="bi bi-tags me-1"></i>Discount:</span>
            <span style="color:#9a3412">{{ \App\Helpers\CakeshopHelper::discountBadgeText($o->discount_type, $o->discount_value) ?? 'Product Discount' }}</span>
            @if(!empty($o->discount_label))
              <span class="text-muted ms-1">({{ $o->discount_label }})</span>
            @endif
          </div>
          @endif

          {{-- Details --}}
          <div class="px-3 py-2 bg-light small text-muted d-flex flex-wrap gap-3">
            <span><i class="bi bi-truck me-1"></i>{{ $o->fulfillment_type }}</span>
            @if($o->delivery_address)<span><i class="bi bi-geo-alt me-1"></i>{{ Str::limit($o->delivery_address,50) }}</span>@endif
            @if($o->schedule_date)
              <span><i class="bi bi-calendar me-1"></i>{{ \Carbon\Carbon::parse($o->schedule_date)->format('M d, Y') }}
                {{ $o->schedule_time ? \Carbon\Carbon::parse($o->schedule_time)->format('g:i A') : '' }}</span>
            @endif
            @if($o->custom_note)<span><i class="bi bi-chat-left-text me-1"></i><em>{{ $o->custom_note }}</em></span>@endif
          </div>

          {{-- Rider Assignment (Delivery orders only) --}}
          @if($o->fulfillment_type === 'Delivery' && in_array($o->status, ['Confirmed','Preparing','Out for Delivery']))
          @php $riders = \Illuminate\Support\Facades\DB::table('riders')->where('is_active',1)->orderBy('name')->get(); @endphp
          <div class="px-3 py-2 d-flex align-items-center gap-2 flex-wrap" style="background:#f0f9ff;border-top:1px dashed #bae6fd">
            <i class="bi bi-bicycle" style="color:#0369a1"></i>
            <span class="small fw-semibold" style="color:#0369a1">Rider:</span>
            @if($o->rider_id)
              @php $assignedRider = $riders->firstWhere('id',$o->rider_id); @endphp
              <span class="badge" style="background:#0369a1;color:#fff">
                {{ $assignedRider->name ?? 'Unknown' }}
              </span>
            @else
              <span class="badge bg-warning text-dark">Not Assigned</span>
            @endif
            <form action="{{ route('admin.orders.assign_rider', $o->id) }}" method="POST" class="d-flex gap-1 ms-auto">
              @csrf
              <select name="rider_id" class="form-select form-select-sm" style="min-width:140px">
                <option value="">— Unassign —</option>
                @foreach($riders as $r)
                <option value="{{ $r->id }}" {{ $o->rider_id == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                @endforeach
              </select>
              <button type="submit" class="btn btn-sm btn-outline-primary">Assign</button>
            </form>
          </div>
          @endif

          {{-- Issue Report (if any) --}}
          @if($o->issue_type)
          <div class="px-3 py-3" style="background:#fff5f5;border-top:2px solid #fecaca">
            <div class="d-flex align-items-center gap-2 mb-2">
              <span style="font-size:clamp(.95rem,2.5vw,1.2rem)">
                @if($o->issue_type==='damaged') 🎂💔
                @elseif($o->issue_type==='not_home') 🏠❌
                @else ⚠️ @endif
              </span>
              <strong class="text-danger small">
                @if($o->issue_type==='damaged') Damaged Cake
                @elseif($o->issue_type==='not_home') Customer Not Home
                @else Other Issue @endif
              </strong>
              @if($o->issue_status === 'pending')
                <span class="badge bg-danger ms-auto">Needs Action</span>
              @elseif($o->issue_status === 'rider_liable')
                <span class="badge bg-warning text-dark ms-auto">Rider Liable ₱{{ number_format($o->issue_amount,2) }}</span>
              @elseif($o->issue_status === 'shop_liable')
                <span class="badge bg-info text-dark ms-auto">Shop Liable</span>
              @elseif($o->issue_status === 'settled')
                <span class="badge bg-success ms-auto">Settled ✓</span>
              @endif
            </div>
            @if($o->issue_note)<p class="small text-muted mb-2">{{ $o->issue_note }}</p>@endif
            @if($o->issue_photo)
            <img src="{{ $o->issue_photo }}" class="rounded mb-2" style="max-height:120px;object-fit:cover;cursor:pointer" onclick="window.open('{{ $o->issue_photo }}','_blank')">
            @endif
            @if($o->resolution_type)
            <div class="small mb-2 p-2 rounded" style="background:#f0fdf4;border:1px solid #bbf7d0">
              <strong>Resolution:</strong> {{ ucfirst(str_replace('_',' ',$o->resolution_type)) }}
              @if($o->resolution_note) — {{ $o->resolution_note }}@endif
            </div>
            @endif
            @if(in_array($o->issue_status, ['pending','rider_liable','shop_liable']))
            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#resolveModal{{ $o->id }}">
              <i class="bi bi-tools me-1"></i>Resolve Issue
            </button>
            @if($o->issue_status === 'rider_liable')
            <form action="{{ route('admin.orders.mark_settled', $o->id) }}" method="POST" class="d-inline ms-1">
              @csrf
              <button type="submit" class="btn btn-sm btn-outline-success">Mark Settlement Paid</button>
            </form>
            @endif
            @endif
          </div>

          {{-- Resolve Modal --}}
          <div class="modal fade" id="resolveModal{{ $o->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width:440px">
              <div class="modal-content border-0" style="border-radius:1.2rem;overflow:hidden">
                <div class="modal-header border-0 pt-4 px-4">
                  <h5 class="modal-title fw-bold">Resolve Issue — Order #{{ $o->id }}</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('admin.orders.resolve_issue', $o->id) }}" method="POST">
                  @csrf
                  <div class="modal-body px-4 pt-0 pb-3">
                    <div class="mb-3">
                      <label class="form-label fw-semibold small">Who is liable? *</label>
                      <div class="d-flex gap-2">
                        <div class="form-check">
                          <input class="form-check-input" type="radio" name="liability" value="rider_liable" id="rl{{ $o->id }}" required>
                          <label class="form-check-label" for="rl{{ $o->id }}">🚴 Rider's Fault</label>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input" type="radio" name="liability" value="shop_liable" id="sl{{ $o->id }}">
                          <label class="form-check-label" for="sl{{ $o->id }}">🏪 Shop's Fault</label>
                        </div>
                      </div>
                    </div>
                    <div class="mb-3" id="amountRow{{ $o->id }}">
                      <label class="form-label fw-semibold small">Rider Liable Amount (₱)</label>
                      <input type="number" class="form-control" name="issue_amount" step="0.01" min="0" max="{{ $o->total_price }}" value="{{ $o->total_price }}">
                      <div class="form-text">Total order: ₱{{ number_format($o->total_price,2) }}</div>
                    </div>
                    <div class="mb-3">
                      <label class="form-label fw-semibold small">Resolution *</label>
                      <select class="form-select" name="resolution_type" required>
                        <option value="">— Select —</option>
                        <option value="replace">🔄 Replace (bake new cake)</option>
                        <option value="refund">💸 Refund (GCash or personal)</option>
                        <option value="discount">💰 Discount on next order</option>
                        <option value="no_refund">❌ No Refund (customer fault)</option>
                      </select>
                    </div>
                    <div class="mb-0">
                      <label class="form-label fw-semibold small">Note to customer</label>
                      <textarea class="form-control" name="resolution_note" rows="2" placeholder="e.g. We will bake a replacement cake. Please message us for schedule."></textarea>
                    </div>
                  </div>
                  <div class="modal-footer border-0 px-4 pb-4 gap-2">
                    <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger flex-fill fw-semibold">Resolve Issue</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
          @endif

          {{-- Actions --}}
          <div class="px-3 py-3 d-flex flex-wrap gap-2 align-items-center">

            {{-- Confirm Order button (for Pending / Pending Review) --}}
            @if(in_array($o->status, ['Pending','Pending Review']))

              {{-- Deposit request removed — customer sets own deposit --}}

              {{-- Confirm Order button removed — auto-confirmed after payment --}}

              {{-- Deposit status badge --}}
              @if($o->deposit_required)
                @if($o->deposit_status === 'paid')
                <span class="badge bg-success">💰 Deposit Paid ₱{{ number_format($o->deposit_amount,2) }}</span>
                @elseif($o->deposit_status === 'pending')
                <span class="badge bg-warning text-dark">⏳ Awaiting Deposit ₱{{ number_format($o->deposit_amount,2) }}</span>
                @endif
              @endif

            @endif



            {{-- Waterfall status buttons --}}
            @php
              $isPickup = $o->fulfillment_type === 'Pickup';
              $nextStatuses = [
                'Pending'          => ['Cancelled'],
                'Pending Review'   => ['Cancelled'],
                'Confirmed'        => ['Cancelled'],      // Kitchen ang mag-uupdate sa Preparing
                'Preparing'        => ['Cancelled'],      // Kitchen ang mag-uupdate sa Out for Delivery/Pickup
                'Out for Delivery' => $isPickup ? ['Pickup','Picked Up','Cancelled'] : ['Cancelled'],
                'Pickup'           => ['Picked Up','Cancelled'],
                'Delivered'        => [],
                'Picked Up'        => [],
                'Cancelled'        => [],
              ];
              $btnColors = [
                'Pickup'           => 'btn-outline-primary',
                'Picked Up'        => 'btn-primary',
                'Cancelled'        => 'btn-outline-danger',
              ];
              $nexts       = $nextStatuses[$o->status] ?? [];
              $finalStatuses = ['Picked Up'];
              $gcashUnpaid = $o->payment_method === 'GCash' && $o->payment_status !== 'Paid';
            @endphp

            {{-- Kitchen status info for Confirmed/Preparing --}}
            @if(in_array($o->status, ['Confirmed','Preparing']))
            <span class="badge px-3 py-2" style="background:#fef3c7;color:#92400e;font-size:.78rem">
              <i class="bi bi-fire me-1"></i>
              {{ $o->status === 'Confirmed' ? 'Waiting for kitchen to start' : 'Kitchen is preparing this order' }}
            </span>
            @endif

            {{-- Delivery orders at Out for Delivery: rider na ang bahala --}}
            @if(!$isPickup && $o->status === 'Out for Delivery')
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <span class="badge px-3 py-2" style="background:#dbeafe;color:#1e40af;font-size:.78rem">
                <i class="bi bi-bicycle me-1"></i>
                Rider is handling this delivery
                @if($o->rider_id)
                  @php $riderName = \Illuminate\Support\Facades\DB::table('riders')->where('id',$o->rider_id)->value('name'); @endphp
                  — <strong>{{ $riderName ?? 'Assigned' }}</strong>
                @endif
              </span>
              @if($o->delivery_photo)
              <a href="{{ $o->delivery_photo }}" target="_blank" class="btn btn-sm btn-outline-success">
                <i class="bi bi-image me-1"></i>View Proof
              </a>
              @endif
            </div>
            @endif

            @foreach($nexts as $nextStatus)
              @if(in_array($nextStatus, $finalStatuses) && $gcashUnpaid)
              <button class="btn btn-sm btn-outline-secondary" disabled
                      title="GCash payment must be completed before marking as {{ $nextStatus }}.">
                <i class="bi bi-lock-fill me-1"></i>{{ $nextStatus }}
                <span class="badge bg-danger ms-1" style="font-size:clamp(.62rem,1.2vw,.65rem)">GCash Unpaid</span>
              </button>
              @else
              <form action="{{ route('admin.orders.update_status', $o->id) }}" method="POST" class="d-inline">
                @csrf
                <input type="hidden" name="status" value="{{ $nextStatus }}">
                <button type="submit" class="btn btn-sm {{ $btnColors[$nextStatus] ?? 'btn-outline-secondary' }}"
                        data-cs-confirm="Move Order #{{ $o->id }} to {{ $nextStatus }}?"
                        data-cs-title="Update Order Status"
                        data-cs-ok="Update Status"
                        data-cs-icon="bi-arrow-repeat"
                        data-cs-icon-bg="#ede9fe"
                        data-cs-icon-color="#7c3aed">
                  @if($nextStatus === 'Pickup') <i class="bi bi-shop me-1"></i>
                  @elseif($nextStatus === 'Picked Up') <i class="bi bi-bag-check me-1"></i>
                  @elseif($nextStatus === 'Cancelled') <i class="bi bi-x-circle me-1"></i>
                  @endif
                  {{ $nextStatus }}
                </button>
              </form>
              @endif
            @endforeach
            <a href="{{ route('admin.messages.thread', $o->id) }}" class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-chat-dots me-1"></i>Message
            </a>

            {{-- Review badge --}}
            @if(isset($orderReviews[$o->id]))
            <span class="badge" style="background:#fef9c3;color:#92400e;font-size:clamp(.7rem,1.4vw,.75rem);padding:.4rem .7rem">
              @for($s=1;$s<=5;$s++)<span style="color:{{ $s<=$orderReviews[$o->id]->rating?'#f59e0b':'#d1d5db' }}">★</span>@endfor
              {{ $orderReviews[$o->id]->rating }}/5
            </span>
            @endif

          </div>

        </div>
      </div>
    </div>

    {{-- Cancel Review Modal --}}
    @if($isCancelPending)
    <div class="modal fade" id="cancelReviewModal{{ $o->id }}" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0" style="border-radius:1.2rem">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-bold">
              <i class="bi bi-x-circle me-2 text-danger"></i>Review Cancel Request — Order #{{ $o->id }}
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">

            {{-- Full Order Details --}}
            <div class="card mb-3" style="border:1px solid #e5e7eb !important">
              <div class="card-body p-3">
                <h6 class="fw-bold mb-3 small text-muted text-uppercase">📦 Order Details</h6>
                <div class="d-flex align-items-center gap-3 mb-3">
                  <img src="{{ $o->image_path }}" style="width:60px;height:60px;object-fit:cover;border-radius:.7rem"
                       onerror="this.src='https://placehold.co/60x60/fce4ec/e91e63?text=🎂'">
                  <div>
                    <div class="fw-bold">{{ $o->product_name }}</div>
                    <div class="text-muted small">Qty: {{ $o->quantity }} &bull; ₱{{ number_format($o->total_price,2) }}</div>
                  </div>
                </div>
                <div class="row g-2 small">
                  <div class="col-sm-6">
                    <div class="text-muted">Customer</div>
                    <div class="fw-semibold">{{ $o->fullname }} ({{ $o->username }})</div>
                  </div>
                  <div class="col-sm-6">
                    <div class="text-muted">Phone / Email</div>
                    <div class="fw-semibold">{{ $o->phone }} &bull; {{ $o->email }}</div>
                  </div>
                  <div class="col-sm-6">
                    <div class="text-muted">Fulfillment</div>
                    <div class="fw-semibold">{{ $o->fulfillment_type }}
                      @if($o->delivery_zone) — {{ $o->delivery_zone }} @endif
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <div class="text-muted">Payment</div>
                    <div class="fw-semibold">{{ \App\Helpers\CakeshopHelper::displayPaymentMethod($o->payment_method, $o->fulfillment_type) }}
                      <span class="badge ms-1"
                            style="background:{{ $o->payment_status==='Paid'?'#d4edda':($o->payment_status==='Partial Payment'?'#dbeafe':'#fff3cd') }};color:{{ $o->payment_status==='Paid'?'#155724':($o->payment_status==='Partial Payment'?'#1e40af':'#856404') }}">
                        {{ $o->payment_status }}
                      </span>
                    </div>
                  </div>
                  @if($o->delivery_address)
                  <div class="col-12">
                    <div class="text-muted">Delivery Address</div>
                    <div class="fw-semibold">{{ $o->delivery_address }}</div>
                  </div>
                  @endif
                  @if($o->schedule_date)
                  <div class="col-sm-6">
                    <div class="text-muted">Schedule</div>
                    <div class="fw-semibold">{{ \Carbon\Carbon::parse($o->schedule_date)->format('M d, Y') }}
                      {{ $o->schedule_time ? \Carbon\Carbon::parse($o->schedule_time)->format('g:i A') : '' }}
                    </div>
                  </div>
                  @endif
                  @if($o->custom_note)
                  <div class="col-12">
                    <div class="text-muted">Special Note</div>
                    <div class="fw-semibold">{{ $o->custom_note }}</div>
                  </div>
                  @endif
                  <div class="col-sm-6">
                    <div class="text-muted">Order Date</div>
                    <div class="fw-semibold">{{ \Carbon\Carbon::parse($o->created_at)->format('M d, Y g:i A') }}</div>
                  </div>
                  <div class="col-sm-6">
                    <div class="text-muted">Current Status</div>
                    <div><span class="status-badge status-{{ str_replace(' ','-',$o->status) }}">{{ $o->status }}</span></div>
                  </div>
                </div>
              </div>
            </div>

            {{-- Customer's Cancel Reason --}}
            <div class="p-3 rounded mb-3" style="background:#fff3cd;border-left:4px solid #f59e0b">
              <div class="fw-semibold small mb-1"><i class="bi bi-chat-quote me-1"></i>Customer's Reason for Cancellation:</div>
              <div class="small">{{ $o->cancel_reason }}</div>
              @if($o->cancel_requested_at)
                <div class="text-muted mt-1" style="font-size:clamp(.68rem,1.3vw,.72rem)">
                  Submitted: {{ \Carbon\Carbon::parse($o->cancel_requested_at)->format('M d, Y g:i A') }}
                </div>
              @endif
            </div>

            {{-- Accept / Reject forms --}}
            <div class="row g-3">
              {{-- ACCEPT --}}
              <div class="col-sm-6">
                <div class="card h-100" style="border:2px solid #d1fae5 !important">
                  <div class="card-body p-3">
                    <h6 class="fw-bold text-success mb-2"><i class="bi bi-check-circle me-1"></i>Accept Cancel</h6>
                    <p class="small text-muted mb-2">Order will be marked as <strong>Cancelled</strong> and customer will be notified.</p>
                    <form action="{{ route('admin.orders.accept_cancel', $o->id) }}" method="POST">
                      @csrf
                      <div class="mb-2">
                        <input type="text" class="form-control form-control-sm" name="admin_note"
                               placeholder="Note to customer (optional)"
                               value="Your cancellation request has been approved.">
                      </div>
                      <button type="submit" class="btn btn-success btn-sm w-100"
                              onclick="confirmAction('Accept Cancel Request?', 'The order will be marked as Cancelled.', () => this.closest('form').submit()); return false;">
                        <i class="bi bi-check-lg me-1"></i>Accept & Cancel Order
                      </button>
                    </form>
                  </div>
                </div>
              </div>
              {{-- REJECT --}}
              <div class="col-sm-6">
                <div class="card h-100" style="border:2px solid #fee2e2 !important">
                  <div class="card-body p-3">
                    <h6 class="fw-bold text-danger mb-2"><i class="bi bi-x-circle me-1"></i>Reject Cancel</h6>
                    <p class="small text-muted mb-2">Order stays as <strong>{{ $o->status }}</strong>. Customer will be told why.</p>
                    <form action="{{ route('admin.orders.reject_cancel', $o->id) }}" method="POST">
                      @csrf
                      <div class="mb-2">
                        <input type="text" class="form-control form-control-sm" name="admin_note"
                               placeholder="Reason for rejection (required)" required>
                      </div>
                      <button type="submit" class="btn btn-danger btn-sm w-100">
                        <i class="bi bi-x-lg me-1"></i>Reject Request
                      </button>
                    </form>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
    @endif

    @empty
    <div class="col-12">
      <div class="card text-center py-5">
        <i class="bi bi-bag-x" style="font-size:3rem;color:#ddd"></i>
        <p class="text-muted mt-3 mb-0">No orders yet.</p>
      </div>
    </div>
    @endforelse
  </div>

  {{ $orders->links('vendor.pagination.custom') }}
</div>
@endsection
@push('scripts')
<script>
let _activeFilter = 'All';
let _activeFulfillment = 'all';

function setFulfillment(type, btn) {
  _activeFulfillment = type;
  document.querySelectorAll('[id^="ft"]').forEach(b => {
    b.classList.remove('btn-primary','btn-info','btn-outline-info');
    b.style.borderColor = '';
    b.style.color = '';
    b.classList.add('btn-outline-secondary');
  });
  btn.classList.remove('btn-outline-secondary','btn-outline-info');
  if (type === 'all') btn.classList.add('btn-primary');
  else if (type === 'Delivery') btn.classList.add('btn-info','text-dark');
  else { btn.style.background='#7c3aed'; btn.style.color='#fff'; btn.style.borderColor='#7c3aed'; }
  doOrderFilter();
}

document.addEventListener('DOMContentLoaded', () => {
  csPagination('ordersList', '.order-item', {
    perPage: 8,
    updateCount(n) {
      const lbl = document.getElementById('ordersCountLabel');
      if (lbl) lbl.firstChild.textContent = n + ' order' + (n !== 1 ? 's' : '');
    }
  });
});

function setFilter(status, btn) {
  _activeFilter = status;
  document.querySelectorAll('#filterTabs button').forEach(b => {
    b.classList.remove('btn-primary');
    b.classList.add('btn-outline-secondary');
  });
  btn.classList.remove('btn-outline-secondary');
  btn.classList.add('btn-primary');
  doOrderFilter();
}

function doOrderFilter() {
  const search = (document.getElementById('searchInput')?.value || '').toLowerCase();
  const items  = [...document.querySelectorAll('#ordersList .order-item')];

  items.forEach(el => {
    const ms = _activeFilter === 'All'
      ? true
      : _activeFilter === 'Cancel Requests'
        ? el.dataset.cancel === 'yes'
        : el.dataset.status === _activeFilter;
    const mf = _activeFulfillment === 'all'
      ? true
      : el.dataset.fulfillment === _activeFulfillment;
    const mq = !search || (el.dataset.search || '').includes(search);
    el.dataset.visible = (ms && mf && mq) ? '1' : '0';
  });

  rerunOrdersPager(search);
}

function rerunOrdersPager(search) {
  // We'll directly manipulate csPagers internals by re-filtering
  const items = [...document.querySelectorAll('#ordersList .order-item')];
  const pager = window.csPagers?.['ordersList'];
  if (!pager) return;

  // Override activeItems via filter function
  // custom filter: use data-visible flag
  const active = items.filter(el => el.dataset.visible !== '0');
  
  // Hide all, show only active page
  const perPage = 8;
  let cur = 1;
  
  function renderPage() {
    const total = Math.ceil(active.length / perPage);
    const s = (cur - 1) * perPage;
    items.forEach(el => el.style.display = 'none');
    active.forEach((el, i) => { el.style.display = (i >= s && i < s + perPage) ? '' : 'none'; });
    
    // Update count label
    const lbl = document.getElementById('ordersCountLabel');
    if (lbl) lbl.firstChild.textContent = active.length + ' order' + (active.length !== 1 ? 's' : '');
    
    // Render pager
    const pagerEl = document.getElementById('ordersList_pager');
    if (!pagerEl) return;
    if (total <= 1) { pagerEl.innerHTML = ''; return; }
    
    let html = '<div class="cs-pagination">';
    html += '<button class="cs-page-btn" onclick="ordersGoPage(' + (cur-1) + ')" ' + (cur===1 ? 'disabled' : '') + '><i class="bi bi-chevron-left"></i></button>';
    buildRange(cur, total).forEach(p => {
      if (p === '...') html += '<button class="cs-page-btn dots">…</button>';
      else html += '<button class="cs-page-btn ' + (p===cur ? 'active' : '') + '" onclick="ordersGoPage(' + p + ')">' + p + '</button>';
    });
    html += '<button class="cs-page-btn" onclick="ordersGoPage(' + (cur+1) + ')" ' + (cur===total ? 'disabled' : '') + '><i class="bi bi-chevron-right"></i></button>';
    html += '<span class="ms-1 text-muted" style="font-size:.78rem">' + active.length + ' item' + (active.length!==1 ? 's' : '') + '</span></div>';
    pagerEl.innerHTML = html;
    
    window._ordersActive = active;
    window._ordersCur    = cur;
    window._ordersTotal  = total;
  }
  
  window.ordersGoPage = function(p) {
    const t = window._ordersTotal || 1;
    if (p < 1 || p > t) return;
    cur = p; renderPage();
    document.getElementById('ordersList')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };
  
  renderPage();
}

function buildRange(cur, total) {
  if (total <= 7) return Array.from({length: total}, (_, i) => i + 1);
  if (cur <= 4)       return [1,2,3,4,5,'...',total];
  if (cur >= total-3) return [1,'...',total-4,total-3,total-2,total-1,total];
  return [1,'...',cur-1,cur,cur+1,'...',total];
}

// Initialize visible flags
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('#ordersList .order-item').forEach(el => el.dataset.visible = '1');
});
</script>
@endpush
