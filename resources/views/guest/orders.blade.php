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
      <p class="text-muted small mb-0" id="ordersCountLabel">{{ count($orders) }} total orders
        @if($pendingCancelCount > 0)
          &nbsp;·&nbsp;<span class="badge bg-danger">{{ $pendingCancelCount }} cancel request{{ $pendingCancelCount > 1 ? 's' : '' }}</span>
        @endif
      </p>
    </div>
    <div class="cs-search-bar" style="max-width:240px;width:100%">
      
      <input type="text" id="searchInput" class="form-control form-control-sm"
             placeholder="Search customer, order ID…" oninput="doFilter()">
    </div>
  </div>

  @if(session('msg'))
    <div class="alert alert-success border-0"><i class="bi bi-check-circle me-2"></i>{{ session('msg') }}</div>
  @endif
  @if(session('err'))
    <div class="alert alert-danger border-0"><i class="bi bi-exclamation-circle me-2"></i>{{ session('err') }}</div>
  @endif

  {{-- Status filter tabs --}}
  <div class="d-flex gap-2 flex-wrap mb-3" id="filterTabs">
    @foreach(['All'=>'All','Pending'=>'⏳ Pending','Confirmed'=>'✅ Confirmed','Preparing'=>'🍳 Preparing','Out for Delivery'=>'🚴 Delivery','Delivered'=>'🏠 Delivered','Cancelled'=>'❌ Cancelled','Cancel Requests'=>'🚨 Cancel Requests'] as $val=>$lbl)
    <button class="btn btn-sm {{ $val==='All' ? 'btn-primary' : 'btn-outline-secondary' }}"
            data-filter="{{ $val }}" onclick="setFilter('{{ $val }}',this)">{{ $lbl }}
      @if($val === 'Cancel Requests' && $pendingCancelCount > 0)
        <span class="badge rounded-pill bg-danger ms-1">{{ $pendingCancelCount }}</span>
      @elseif($val !== 'Cancel Requests')
        @php $cnt = $val==='All' ? count($orders) : collect($orders)->where('status',$val)->count(); @endphp
        <span class="badge rounded-pill ms-1" style="background:rgba(0,0,0,.12)">{{ $cnt }}</span>
      @endif
    </button>
    @endforeach
  </div>

  <div class="row g-3" id="ordersList">
    @forelse($orders as $o)
    @php
      $isCancelPending = $o->cancel_requested && $o->cancel_status === 'pending';
    @endphp
    <div class="col-12 order-item"
         data-status="{{ $o->status }}"
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
                    <span class="badge ms-1" style="background:#f0f9ff;color:#0369a1;font-size:.65rem">Guest</span>
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
              <span class="status-badge status-{{ str_replace(' ','-',$o->status) }}">{{ $o->status }}</span>
              <div class="fw-bold mt-1 fs-6">₱{{ number_format($o->total_price,2) }}</div>
              <div class="small text-muted">
                {{ \App\Helpers\CakeshopHelper::displayPaymentMethod($o->payment_method, $o->fulfillment_type) }}
                <span class="badge rounded-pill ms-1"
                      style="font-size:.7rem;background:{{ $o->payment_status==='Paid'?'#d4edda':'#fff3cd' }};color:{{ $o->payment_status==='Paid'?'#155724':'#856404' }}">
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
              <span class="badge me-1" style="background:#fff0f5;color:var(--primary);font-size:.72rem">
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

          {{-- Actions --}}
          <div class="px-3 py-3 d-flex flex-wrap gap-2 align-items-center">

            {{-- Confirm Order button (for Pending / Pending Review) --}}
            @if(in_array($o->status, ['Pending','Pending Review']))

              {{-- Request Deposit button --}}
              @if($o->deposit_status !== 'paid')
              <button type="button" class="btn btn-warning btn-sm text-dark"
                      data-bs-toggle="modal" data-bs-target="#depositModal{{ $o->id }}">
                <i class="bi bi-cash-coin me-1"></i>
                {{ $o->deposit_required ? 'Edit Deposit Request' : 'Request Deposit' }}
              </button>

              {{-- Deposit Modal --}}
              <div class="modal fade" id="depositModal{{ $o->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
                  <div class="modal-content border-0" style="border-radius:1.2rem;overflow:hidden">
                    <div class="modal-header border-0 pt-4 px-4">
                      <h5 class="modal-title fw-bold">💰 Request Deposit — Order #{{ $o->id }}</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="{{ route('admin.orders.request_deposit', $o->id) }}" method="POST">
                      @csrf
                      <div class="modal-body px-4 pt-0 pb-3">
                        <p class="text-muted small mb-3">
                          Customer: <strong>{{ $o->fullname }}</strong> &bull;
                          Total: <strong>₱{{ number_format($o->total_price,2) }}</strong>
                        </p>
                        <div class="mb-3">
                          <label class="form-label fw-semibold small">Deposit Amount (₱) <span class="text-danger">*</span></label>
                          <input type="number" class="form-control" name="deposit_amount"
                                 step="0.01" min="1" max="{{ $o->total_price }}"
                                 value="{{ $o->deposit_amount > 0 ? $o->deposit_amount : round($o->total_price * 0.5, 2) }}"
                                 required>
                          <div class="form-text">Default 50% = ₱{{ number_format($o->total_price * 0.5, 2) }}</div>
                        </div>
                        <div class="mb-3">
                          <label class="form-label fw-semibold small">Message to Customer <span class="text-muted">(optional)</span></label>
                          <textarea class="form-control" name="deposit_message" rows="2"
                                    placeholder="e.g. Hi! Please settle your deposit to proceed with your order.">{{ $o->deposit_message }}</textarea>
                        </div>
                        <div class="alert border-0 py-2 mb-0" style="background:#fff7ed;border-radius:.7rem;font-size:.8rem">
                          <i class="bi bi-phone me-1" style="color:#ea580c"></i>
                          An SMS with the payment link will be sent to <strong>{{ $o->phone }}</strong>.
                        </div>
                      </div>
                      <div class="modal-footer border-0 px-4 pb-4 gap-2">
                        <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning text-dark flex-fill fw-semibold">
                          <i class="bi bi-send me-1"></i>Send Deposit Request
                        </button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
              @endif

              {{-- Confirm Order — locked until deposit paid (if required) --}}
              @if(!$o->deposit_required || $o->deposit_status === 'paid')
              <form action="{{ route('admin.orders.confirm', $o->id) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-success btn-sm"
                        data-cs-confirm="Confirm Order #{{ $o->id }}? SMS will be sent to customer."
                        data-cs-title="Confirm Order"
                        data-cs-ok="Confirm"
                        data-cs-icon="bi-check-circle"
                        data-cs-icon-bg="#dcfce7"
                        data-cs-icon-color="#16a34a">
                  <i class="bi bi-check-circle-fill me-1"></i>Confirm Order
                </button>
              </form>
              @else
              <button class="btn btn-success btn-sm disabled" disabled
                      title="Waiting for deposit payment before you can confirm.">
                <i class="bi bi-lock-fill me-1"></i>Confirm Order
              </button>
              @endif

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
              $nextStatuses = [
                'Pending'          => ['Cancelled'],
                'Pending Review'   => ['Cancelled'],
                'Confirmed'        => ['Preparing','Cancelled'],
                'Preparing'        => ['Out for Delivery','Cancelled'],
                'Out for Delivery' => ['Delivered','Cancelled'],
                'Delivered'        => [],
                'Cancelled'        => [],
              ];
              $btnColors = [
                'Confirmed'        => 'btn-success',
                'Preparing'        => 'btn-warning text-dark',
                'Out for Delivery' => 'btn-info text-dark',
                'Delivered'        => 'btn-primary',
                'Cancelled'        => 'btn-outline-danger',
              ];
              $nexts = $nextStatuses[$o->status] ?? [];
            @endphp
            @foreach($nexts as $nextStatus)
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
                @if($nextStatus === 'Preparing') <i class="bi bi-fire me-1"></i>
                @elseif($nextStatus === 'Out for Delivery') <i class="bi bi-truck me-1"></i>
                @elseif($nextStatus === 'Delivered') <i class="bi bi-house-check me-1"></i>
                @elseif($nextStatus === 'Cancelled') <i class="bi bi-x-circle me-1"></i>
                @endif
                {{ $nextStatus }}
              </button>
            </form>
            @endforeach
            <a href="{{ route('admin.messages.thread', $o->id) }}" class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-chat-dots me-1"></i>Message
            </a>

            {{-- Send to Kitchen --}}
            @if(in_array($o->status, ['Confirmed','Preparing']))
            <form action="{{ route('admin.orders.send_to_kitchen', $o->id) }}" method="POST" class="d-inline">
              @csrf
              <button type="submit" class="btn btn-sm {{ $o->kitchen_sent ? 'btn-success' : 'btn-outline-success' }}"
                      onclick="confirmAction('Send to Kitchen?', 'Order #{{ $o->id }} will be sent to the kitchen.', () => this.closest('form').submit()); return false;">
                <i class="bi bi-fire me-1"></i>{{ $o->kitchen_sent ? '✓ Sent to Kitchen' : 'Send to Kitchen' }}
              </button>
            </form>
            @endif

            {{-- Review badge --}}
            @if(isset($orderReviews[$o->id]))
            <span class="badge" style="background:#fef9c3;color:#92400e;font-size:.75rem;padding:.4rem .7rem">
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
                            style="background:{{ $o->payment_status==='Paid'?'#d4edda':'#fff3cd' }};color:{{ $o->payment_status==='Paid'?'#155724':'#856404' }}">
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
                <div class="text-muted mt-1" style="font-size:.72rem">
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

  {{-- Pagination --}}
  <div class="mt-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div id="ordersList_pager"></div>
  </div>
</div>
@endsection
@push('scripts')
<script>
let _activeFilter = 'All';

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

  // Mark visibility on each item based on filter + search
  items.forEach(el => {
    const ms = _activeFilter === 'All'
      ? true
      : _activeFilter === 'Cancel Requests'
        ? el.dataset.cancel === 'yes'
        : el.dataset.status === _activeFilter;
    const mq = !search || (el.dataset.search || '').includes(search);
    // Temporarily tag so csPagination can see it
    if (ms && mq) { el.dataset.visible = '1'; }
    else          { el.dataset.visible = '0'; }
  });

  // Re-run pagination filtering using data-visible
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
    html += '<button class="cs-page-btn" onclick="ordersGoPage(' + cur-1 + ')" ' + cur===1?'disabled':'' + '><i class="bi bi-chevron-left"></i></button>';
    buildRange(cur, total).forEach(p => {
      if (p === '...') html += '<button class="cs-page-btn dots">…</button>';
      else html += '<button class="cs-page-btn ' + p===cur?'active':'' + '" onclick="ordersGoPage(' + p + ')">' + p + '</button>';
    });
    html += '<button class="cs-page-btn" onclick="ordersGoPage(' + cur+1 + ')" ' + cur===total?'disabled':'' + '><i class="bi bi-chevron-right"></i></button>';
    html += '<span class="ms-1 text-muted" style="font-size:.78rem">' + active.length + ' item' + active.length!==1?'s':'' + '</span></div>';
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
