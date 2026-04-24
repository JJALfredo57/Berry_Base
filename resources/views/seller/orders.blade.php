@extends('layouts.app')
@section('page_title','Orders')
@section('content')
<div>
  <div style="margin-bottom:2rem">
    <h1 style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:700;color:var(--gray-900);margin:0 0 .25rem">Orders</h1>
    <p style="font-size:.875rem;color:var(--gray-500);margin:0">{{ $orders->count() }} total orders for {{ $shop->shop_name }}</p>
  </div>

  @if(session('msg'))
    <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
      <i class="bi bi-check-circle-fill flex-shrink-0"></i><span>{{ session('msg') }}</span>
    </div>
  @endif
  @if(session('err'))
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
      <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i><span>{{ session('err') }}</span>
    </div>
  @endif
  @foreach($errors->all() as $e)
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-2">
      <i class="bi bi-exclamation-circle flex-shrink-0"></i><span>{{ $e }}</span>
    </div>
  @endforeach

  <div style="background:#fff;border-radius:var(--radius-lg);border:1.5px solid var(--gray-100);padding:1rem 1.25rem;margin-bottom:1.25rem">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
      <div>
        <div style="font-size:.95rem;font-weight:700;color:var(--gray-900)">Smart Order Filter</div>
        <div style="font-size:.8rem;color:var(--gray-500)">Search by customer, product, tracking code, payment, or order status</div>
      </div>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;width:100%">
        <input type="text" id="sellerOrderSearch" class="form-control" placeholder="Search orders..." style="flex:1;min-width:0;max-width:280px" oninput="filterSellerOrders()">
        <select id="sellerOrderStatusFilter" class="form-select" style="flex:1;min-width:0;max-width:160px" onchange="filterSellerOrders()">
          <option value="">All status</option>
          @foreach($orders->pluck('status')->filter()->unique()->sort()->values() as $statusOption)
          <option value="{{ strtolower($statusOption) }}">{{ $statusOption }}</option>
          @endforeach
        </select>
        <select id="sellerOrderFulfillmentFilter" class="form-select" style="flex:1;min-width:0;max-width:160px" onchange="filterSellerOrders()">
          <option value="">All fulfillment</option>
          <option value="pickup">Pickup</option>
          <option value="delivery">Delivery</option>
        </select>
      </div>
    </div>
    <div id="sellerOrderFilterSummary" style="font-size:.78rem;color:var(--gray-500);margin-top:.6rem">Showing {{ $orders->count() }} orders</div>
  </div>

  @forelse($orders as $o)
  @php
    $custom = $customData[$o->id] ?? null;
    $addons = $orderAddons[$o->id] ?? [];
    $sc = match($o->status) {
      'Pending','Pending Review' => 'background:#FFF3E0;color:#E65100',
      'Confirmed'                => 'background:#E3F2FD;color:#1565C0',
      'Preparing'                => 'background:#F3E5F5;color:#6A1B9A',
      'Out for Delivery'         => 'background:#E8F5E9;color:#2E7D32',
      'Delivered','Picked Up'    => 'background:#E8F5E9;color:#1B5E20',
      'Cancelled'                => 'background:#FFEBEE;color:#C62828',
      default                    => 'background:#F5F5F5;color:#616161',
    };
  @endphp

  <div class="seller-order-item"
       data-search="{{ strtolower(trim(($o->track_code ?? '') . ' ' . ($o->fullname ?? 'customer') . ' ' . ($o->product_name ?? ($custom->cake_name ?? 'custom cake')) . ' ' . ($o->payment_status ?? '') . ' ' . ($o->payment_method ?? '') . ' ' . ($o->status ?? ''))) }}"
       data-status="{{ strtolower($o->status ?? '') }}"
       data-fulfillment="{{ strtolower($o->fulfillment_type ?? 'pickup') }}"
       style="background:#fff;border-radius:var(--radius-lg);border:1.5px solid var(--gray-100);margin-bottom:1rem;overflow:hidden">

    {{-- Order Header --}}
    <div style="padding:1rem 1.25rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;background:{{ in_array($o->status,['Pending','Pending Review']) ? '#FFFBF5' : '#fff' }}">
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.25rem">
          <span style="font-size:.875rem;font-weight:700;color:var(--gray-900);font-family:monospace">{{ strtoupper($o->track_code) }}</span>
          <span style="{{ $sc }};font-size:.7rem;font-weight:700;padding:.2rem .65rem;border-radius:99px">{{ $o->status }}</span>
          @if($custom)
            <span style="background:var(--primary-bg);color:var(--primary);font-size:.68rem;font-weight:700;padding:.2rem .5rem;border-radius:99px">Custom</span>
          @endif
        </div>
        <div style="font-size:.8rem;color:var(--gray-700);font-weight:600">{{ $o->fullname ?? 'Customer' }}</div>
        <div style="font-size:.75rem;color:var(--gray-500)">
          {{ $o->product_name ?? ($custom->cake_name ?? 'Custom Cake') }}
          &bull; {{ $o->fulfillment_type ?? 'Pickup' }}
          @if($o->schedule_date) &bull; {{ \Carbon\Carbon::parse($o->schedule_date)->format('M d, Y') }} @endif
        </div>
      </div>
      <div style="text-align:right;flex-shrink:0">
        <div style="font-size:1rem;font-weight:700;color:var(--primary)">₱{{ number_format($o->total_price,2) }}</div>
        <div style="font-size:.72rem;color:{{ $o->payment_status==='Paid' ? 'var(--success,#2E7D32)' : ($o->payment_status==='Partial Payment' ? '#E65100' : 'var(--gray-500)') }};font-weight:600">
          {{ $o->payment_status ?? 'Unpaid' }}
        </div>
      </div>
    </div>

    {{-- Pickup Ready: Picked Up button or payment warning --}}
    @if($o->status === 'Pickup')
      @if($o->payment_status === 'Paid')
      <div style="border-top:1px solid var(--gray-100);padding:.9rem 1.25rem;background:linear-gradient(135deg,#f0fdf4 0%,#f8fafc 100%);display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
        <div style="display:flex;align-items:flex-start;gap:.75rem;flex:1;min-width:260px">
          <div style="width:2.4rem;height:2.4rem;border-radius:14px;background:#dcfce7;color:#16a34a;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="bi bi-bag-check"></i>
          </div>
          <div>
            <div style="font-size:.78rem;font-weight:800;color:#111827;letter-spacing:.04em">READY FOR PICKUP</div>
            <div style="font-size:.82rem;color:#16a34a;line-height:1.5;font-weight:600">
              Payment complete. Confirm once the customer has picked up their order.
            </div>
          </div>
        </div>
        <form action="{{ route('seller.orders.status', $o->id) }}" method="POST" style="margin-left:auto">
          @csrf
          <input type="hidden" name="status" value="Picked Up">
          <button type="submit"
                  style="background:#16a34a;color:#fff;border:none;border-radius:var(--radius-md);padding:.45rem 1.1rem;font-size:.82rem;font-weight:700;cursor:pointer"
                  data-cs-confirm="Mark this order as Picked Up?"
                  data-cs-title="Confirm Pickup"
                  data-cs-ok="Yes, Picked Up"
                  data-cs-ok-color="#16a34a"
                  data-cs-icon="bi-bag-check"
                  data-cs-icon-bg="#dcfce7"
                  data-cs-icon-color="#16a34a">
            <i class="bi bi-bag-check"></i> Mark as Picked Up
          </button>
        </form>
      </div>
      @else
      <div style="border-top:1px solid var(--gray-100);padding:.9rem 1.25rem;background:linear-gradient(135deg,#fffbeb 0%,#f8fafc 100%);display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
        <div style="display:flex;align-items:flex-start;gap:.75rem;flex:1;min-width:260px">
          <div style="width:2.4rem;height:2.4rem;border-radius:14px;background:#fef3c7;color:#d97706;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="bi bi-clock-history"></i>
          </div>
          <div>
            <div style="font-size:.78rem;font-weight:800;color:#111827;letter-spacing:.04em">AWAITING PAYMENT</div>
            <div style="font-size:.82rem;color:#d97706;line-height:1.5;font-weight:600">
              Order is ready but customer still has an unpaid balance
              @if($o->payment_method === 'GCash') (GCash — ₱{{ number_format($o->total_price - ($o->deposit_amount ?? 0), 2) }} remaining)@endif.
              The <strong>Picked Up</strong> button will appear once payment is completed.
            </div>
          </div>
        </div>
        <span style="background:#FEF3C7;color:#92400E;border:1.5px solid #FDE68A;border-radius:var(--radius-md);padding:.35rem .875rem;font-size:.78rem;font-weight:700;margin-left:auto">
          <i class="bi bi-lock"></i> Locked — Unpaid
        </span>
      </div>
      @endif
    @endif

    {{-- Status Actions (non-final, non-Pickup orders) --}}
    @if(!in_array($o->status, ['Delivered','Picked Up','Cancelled','Pickup']))
    <div style="border-top:1px solid var(--gray-100);padding:.9rem 1.25rem;background:linear-gradient(135deg,#fff8fb 0%,#f8fafc 100%);display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
      <div style="display:flex;align-items:flex-start;gap:.75rem;flex:1;min-width:260px">
        <div style="width:2.4rem;height:2.4rem;border-radius:14px;background:#fdf2f8;color:#be185d;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="bi bi-stars"></i>
        </div>
        <div>
          <div style="font-size:.78rem;font-weight:800;color:#111827;letter-spacing:.04em">ORDER PROGRESS</div>
          <div style="font-size:.82rem;color:#6b7280;line-height:1.5">
            Progress moves automatically through the Kitchen workflow.
          </div>
        </div>
      </div>

      {{-- Cancel --}}
      <button type="button" onclick="toggleCancel('cancel-{{ $o->id }}')"
              style="background:#FFEBEE;color:#C62828;border:1.5px solid #FFCDD2;border-radius:var(--radius-md);padding:.35rem .875rem;font-size:.78rem;font-weight:600;cursor:pointer;margin-left:auto">
        <i class="bi bi-x-circle"></i> Cancel
      </button>
    </div>

    {{-- Cancel Reason --}}
    <div id="cancel-{{ $o->id }}" style="display:none;border-top:1px solid var(--gray-100);padding:.875rem 1.25rem;background:#FFEBEE">
      <form action="{{ route('seller.orders.status', $o->id) }}" method="POST" novalidate>
        @csrf
        <input type="hidden" name="status" value="Cancelled">
        <div style="display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap">
          <div style="flex:1;min-width:200px">
            <label class="form-label" style="font-size:.78rem;color:#C62828;font-weight:700">Cancellation Reason <span style="color:var(--danger)">*</span></label>
            <input type="text" class="form-control" name="cancel_reason" required minlength="5"
                   placeholder="e.g. Unavailable ingredients, customer request..."
                   oninvalid="this.setCustomValidity('Please enter a reason (min 5 chars)')"
                   oninput="this.setCustomValidity('')">
          </div>
          <button type="submit" style="background:#C62828;color:#fff;border:none;border-radius:var(--radius-md);padding:.5rem 1rem;font-size:.8rem;font-weight:600;cursor:pointer"
                  data-cs-confirm="Cancel this order?"
                  data-cs-title="Confirm Cancellation"
                  data-cs-ok="Cancel Order"
                  data-cs-ok-color="#C62828"
                  data-cs-icon="bi-x-octagon"
                  data-cs-icon-bg="#fee2e2"
                  data-cs-icon-color="#C62828">
            Confirm Cancel
          </button>
          <button type="button" onclick="toggleCancel('cancel-{{ $o->id }}')"
                  style="background:var(--gray-100);color:var(--gray-700);border:1.5px solid var(--gray-200);border-radius:var(--radius-md);padding:.5rem 1rem;font-size:.8rem;font-weight:600;cursor:pointer">
            Back
          </button>
        </div>
      </form>
    </div>
    @endif

  </div>
  @empty
  <div style="background:#fff;border-radius:var(--radius-lg);border:1.5px dashed var(--gray-300);padding:4rem;text-align:center">
    <i class="bi bi-bag" style="font-size:2.5rem;color:var(--gray-300);display:block;margin-bottom:1rem"></i>
    <h3 style="font-size:1rem;font-weight:700;color:var(--gray-900);margin:0 0 .5rem">No orders yet</h3>
    <p style="font-size:.875rem;color:var(--gray-500);margin:0">Orders will appear here once customers start placing them.</p>
  </div>
  @endforelse

  <div id="sellerOrdersEmpty" style="display:none;background:#fff;border-radius:var(--radius-lg);border:1.5px dashed var(--gray-300);padding:2.5rem;text-align:center">
    <i class="bi bi-search" style="font-size:2rem;color:var(--gray-300);display:block;margin-bottom:.75rem"></i>
    <h3 style="font-size:1rem;font-weight:700;color:var(--gray-900);margin:0 0 .35rem">No matching orders</h3>
    <p style="font-size:.82rem;color:var(--gray-500);margin:0">Try a different search term or filter.</p>
  </div>
</div>
<script>
function toggleCancel(id) {
  const el = document.getElementById(id);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

function filterSellerOrders() {
  const search = (document.getElementById('sellerOrderSearch')?.value || '').toLowerCase().trim();
  const status = (document.getElementById('sellerOrderStatusFilter')?.value || '').toLowerCase();
  const fulfillment = (document.getElementById('sellerOrderFulfillmentFilter')?.value || '').toLowerCase();
  let visibleCount = 0;

  document.querySelectorAll('.seller-order-item').forEach(el => {
    const matchesSearch = !search || (el.dataset.search || '').includes(search);
    const matchesStatus = !status || (el.dataset.status || '') === status;
    const matchesFulfillment = !fulfillment || (el.dataset.fulfillment || '') === fulfillment;
    const matches = matchesSearch && matchesStatus && matchesFulfillment;
    el.style.display = matches ? '' : 'none';
    if (matches) visibleCount++;
  });

  const summary = document.getElementById('sellerOrderFilterSummary');
  if (summary) summary.textContent = 'Showing ' + visibleCount + ' of ' + document.querySelectorAll('.seller-order-item').length + ' orders';

  const empty = document.getElementById('sellerOrdersEmpty');
  if (empty) empty.style.display = visibleCount === 0 ? 'block' : 'none';
}
</script>
@endsection
