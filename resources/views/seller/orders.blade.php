@extends('layouts.app')
@section('page_title','Orders')
@section('content')
<div>
  <div style="margin-bottom:2rem">
    <h1 style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:700;color:var(--gray-900);margin:0 0 .25rem">Orders</h1>
    <p style="font-size:.875rem;color:var(--gray-500);margin:0">{{ $orders->total() }} total orders for {{ $shop->shop_name }}</p>
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
        <input type="text" id="sellerOrderSearch" class="form-control" placeholder="Search orders..."
               style="flex:1;min-width:0;max-width:280px"
               value="{{ $search ?? '' }}"
               oninput="pgSearch(this.value)">
        <select id="sellerOrderStatusFilter" class="form-select" style="flex:1;min-width:0;max-width:160px"
                onchange="pgFilter('status', this.value)">
          <option value="All" {{ ($status??'All')==='All'?'selected':'' }}>All status</option>
          @foreach(['Awaiting Deposit','Pending','Pending Review','Confirmed','Preparing','Ready for Pickup','Out for Delivery','Delivered','Picked Up','Cancelled'] as $st)
          <option value="{{ $st }}" {{ ($status??'')===$st?'selected':'' }}>{{ $st }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div id="sellerOrderFilterSummary" style="font-size:.78rem;color:var(--gray-500);margin-top:.6rem">Showing {{ $orders->firstItem() ?? 0 }}–{{ $orders->lastItem() ?? 0 }} of {{ $orders->total() }} orders</div>
  </div>

  @forelse($orders as $o)
  @php
    $custom = $customData[$o->id] ?? null;
    $addons = $orderAddons[$o->id] ?? [];
    $sc = match($o->status) {
      'Awaiting Deposit'         => 'background:#FCE4EC;color:#880E4F',
      'Pending','Pending Review' => 'background:#FFF3E0;color:#E65100',
      'Confirmed'                => 'background:#E3F2FD;color:#1565C0',
      'Preparing'                => 'background:#F3E5F5;color:#6A1B9A',
      'Ready for Pickup'         => 'background:#EDE9FE;color:#5B21B6',
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
    @php
      $thumb = $o->image_path ?? null;
      if (!$thumb && $custom) {
          $refs  = json_decode($custom->reference_images ?? '[]', true);
          $thumb = is_array($refs) ? ($refs[0] ?? null) : null;
      }
    @endphp
    <div style="padding:1rem 1.25rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;background:{{ in_array($o->status,['Pending','Pending Review']) ? '#FFFBF5' : '#fff' }}">
      {{-- Thumbnail --}}
      @if($thumb)
        <img src="{{ $thumb }}" onclick="openLightbox(this)"
             style="width:58px;height:58px;border-radius:10px;object-fit:cover;flex-shrink:0;cursor:zoom-in;border:1.5px solid var(--gray-100)">
      @else
        <div style="width:58px;height:58px;border-radius:10px;background:#fdf2f8;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1.5px solid var(--gray-100)">
          <i class="bi bi-{{ $custom ? 'stars' : 'bag' }}" style="font-size:1.4rem;color:#be185d"></i>
        </div>
      @endif
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
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.4rem;flex-shrink:0">
        <div style="font-size:1rem;font-weight:700;color:var(--primary)">₱{{ number_format((float)($o->total_price ?? 0),2) }}</div>
        <div style="font-size:.72rem;color:{{ $o->payment_status==='Paid' ? 'var(--success,#2E7D32)' : ($o->payment_status==='Partial Payment' ? '#E65100' : 'var(--gray-500)') }};font-weight:600">
          {{ $o->payment_status ?? 'Unpaid' }}
        </div>
        <button onclick="showOrderDetail('{{ $o->id }}')"
                style="background:var(--primary,#e91e63);color:#fff;border:none;border-radius:8px;padding:.25rem .7rem;font-size:.72rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:.3rem">
          <i class="bi bi-eye"></i> View
        </button>
      </div>
    </div>

    {{-- Hidden detail panel for modal --}}
    <div id="order-detail-{{ $o->id }}" style="display:none">
      @php
        $allRefs = [];
        if ($o->image_path) $allRefs[] = $o->image_path;
        if ($custom) {
          $refs2 = json_decode($custom->reference_images ?? '[]', true);
          if (is_array($refs2)) $allRefs = array_merge($allRefs, $refs2);
        }
      @endphp

      {{-- Images row --}}
      @if(count($allRefs) || $o->delivery_photo || $o->issue_photo || ($custom && $custom->progress_image))
      <div style="margin-bottom:1rem">
        <div style="font-size:.72rem;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.5rem">Photos</div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
          @foreach($allRefs as $img)
            <img src="{{ $img }}" onclick="openLightbox(this)"
                 style="width:90px;height:90px;border-radius:10px;object-fit:cover;cursor:zoom-in;border:1.5px solid var(--gray-100)">
          @endforeach
          @if($o->delivery_photo)
            <div style="position:relative">
              <img src="{{ $o->delivery_photo }}" onclick="openLightbox(this)"
                   style="width:90px;height:90px;border-radius:10px;object-fit:cover;cursor:zoom-in;border:1.5px solid #bbf7d0">
              <span style="position:absolute;bottom:3px;left:3px;background:rgba(0,0,0,.55);color:#fff;font-size:.55rem;padding:1px 5px;border-radius:4px">Delivery</span>
            </div>
          @endif
          @if($o->issue_photo)
            <div style="position:relative">
              <img src="{{ $o->issue_photo }}" onclick="openLightbox(this)"
                   style="width:90px;height:90px;border-radius:10px;object-fit:cover;cursor:zoom-in;border:1.5px solid #fecaca">
              <span style="position:absolute;bottom:3px;left:3px;background:rgba(0,0,0,.55);color:#fff;font-size:.55rem;padding:1px 5px;border-radius:4px">Issue</span>
            </div>
          @endif
          @if($custom && $custom->progress_image)
            <div style="position:relative">
              <img src="{{ $custom->progress_image }}" onclick="openLightbox(this)"
                   style="width:90px;height:90px;border-radius:10px;object-fit:cover;cursor:zoom-in;border:1.5px solid #ddd6fe">
              <span style="position:absolute;bottom:3px;left:3px;background:rgba(0,0,0,.55);color:#fff;font-size:.55rem;padding:1px 5px;border-radius:4px">Progress</span>
            </div>
          @endif
        </div>
      </div>
      @endif

      {{-- Deposit Alert --}}
      @if($o->deposit_required && ($o->deposit_status ?? '') !== 'paid')
      <div style="margin-bottom:1rem;background:#FCE4EC;border-radius:10px;padding:.75rem 1rem;display:flex;align-items:center;gap:.75rem">
        <i class="bi bi-lock-fill" style="color:#880E4F;font-size:1.1rem;flex-shrink:0"></i>
        <div>
          <div style="font-size:.78rem;font-weight:800;color:#880E4F;letter-spacing:.04em">AWAITING DEPOSIT</div>
          <div style="font-size:.8rem;color:#880E4F;margin-top:.1rem">
            Customer must pay ₱{{ number_format((float)($o->deposit_amount ?? 0), 2) }} deposit via GCash before this order enters the queue.
          </div>
        </div>
      </div>
      @elseif($o->deposit_required && ($o->deposit_status ?? '') === 'paid')
      <div style="margin-bottom:1rem;background:#E8F5E9;border-radius:10px;padding:.75rem 1rem;display:flex;align-items:center;gap:.75rem">
        <i class="bi bi-check-circle-fill" style="color:#2E7D32;font-size:1.1rem;flex-shrink:0"></i>
        <div>
          <div style="font-size:.78rem;font-weight:800;color:#2E7D32;letter-spacing:.04em">DEPOSIT RECEIVED</div>
          <div style="font-size:.8rem;color:#2E7D32;margin-top:.1rem">
            ₱{{ number_format((float)($o->deposit_amount ?? 0), 2) }} deposit paid. Remaining ₱{{ number_format((float)($o->total_price ?? 0) - (float)($o->deposit_amount ?? 0), 2) }} to be collected on {{ $o->fulfillment_type === 'Delivery' ? 'delivery' : 'pickup' }}.
          </div>
        </div>
      </div>
      @endif

      {{-- Info grid --}}
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem 1.25rem;font-size:.82rem">
        <div><span style="color:var(--gray-500)">Track Code</span><br><strong style="font-family:monospace">{{ strtoupper($o->track_code) }}</strong></div>
        <div><span style="color:var(--gray-500)">Status</span><br><strong>{{ $o->status }}</strong></div>
        <div><span style="color:var(--gray-500)">Customer</span><br><strong>{{ $o->fullname ?? 'Customer' }}</strong></div>
        <div><span style="color:var(--gray-500)">Phone</span><br><strong>{{ $o->phone ?? '—' }}</strong></div>
        <div><span style="color:var(--gray-500)">Product</span><br><strong>{{ $o->product_name ?? ($custom->cake_name ?? 'Custom Cake') }}</strong></div>
        <div><span style="color:var(--gray-500)">Qty / Size</span><br><strong>{{ $o->quantity ?? 1 }}x {{ $o->selected_size ?? ($custom->size_label ?? '—') }}</strong></div>
        <div><span style="color:var(--gray-500)">Fulfillment</span><br><strong>{{ $o->fulfillment_type ?? 'Pickup' }}</strong></div>
        <div><span style="color:var(--gray-500)">Schedule</span><br><strong>{{ $o->schedule_date ? \Carbon\Carbon::parse($o->schedule_date)->format('M d, Y') : '—' }}{{ $o->schedule_time ? ' '.$o->schedule_time : '' }}</strong></div>
        @if($o->fulfillment_type === 'Delivery')
        <div style="grid-column:1/-1"><span style="color:var(--gray-500)">Delivery Address</span><br><strong>{{ $o->delivery_address ?? '—' }}</strong></div>
        @endif
        <div><span style="color:var(--gray-500)">Payment</span><br><strong>{{ $o->payment_method ?? '—' }}</strong></div>
        <div><span style="color:var(--gray-500)">Payment Status</span><br>
          <strong style="color:{{ $o->payment_status==='Paid' ? '#16a34a' : ($o->payment_status==='Partial Payment' ? '#d97706' : '#6b7280') }}">
            {{ $o->payment_status ?? 'Unpaid' }}
          </strong>
        </div>
        <div><span style="color:var(--gray-500)">Total</span><br><strong style="color:var(--primary)">₱{{ number_format((float)($o->total_price ?? 0),2) }}</strong></div>
        @if($o->delivery_fee)
        <div><span style="color:var(--gray-500)">Delivery Fee</span><br><strong>₱{{ number_format((float)($o->delivery_fee ?? 0),2) }}</strong></div>
        @endif
        @if($o->deposit_required)
        <div><span style="color:var(--gray-500)">Deposit</span><br>
          <strong style="color:{{ ($o->deposit_status??'') === 'paid' ? '#16a34a' : '#880E4F' }}">
            ₱{{ number_format((float)($o->deposit_amount ?? 0),2) }} — {{ ($o->deposit_status??'') === 'paid' ? 'Paid' : 'Pending' }}
          </strong>
        </div>
        @endif
      </div>

      {{-- Add-ons --}}
      @if(!empty($addons))
      <div style="margin-top:.85rem">
        <div style="font-size:.72rem;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.4rem">Add-ons</div>
        @foreach($addons as $a)
        <div style="font-size:.81rem;color:var(--gray-700)">• {{ $a->addon_name ?? $a->name ?? '—' }} @if($a->price) — ₱{{ number_format((float)$a->price,2) }} @endif</div>
        @endforeach
      </div>
      @endif

      {{-- Special notes --}}
      @if($o->special_notes || ($custom && $custom->special_notes))
      <div style="margin-top:.85rem;background:#fffbeb;border-radius:8px;padding:.65rem .85rem;font-size:.81rem;color:#92400e">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.25rem">Special Notes</div>
        {{ $o->special_notes ?? $custom->special_notes }}
      </div>
      @endif

      {{-- Custom order description --}}
      @if($custom && $custom->description)
      <div style="margin-top:.65rem;background:#f0fdf4;border-radius:8px;padding:.65rem .85rem;font-size:.81rem;color:#166534">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.25rem">Custom Cake Description</div>
        {{ $custom->description }}
      </div>
      @endif

      {{-- Cancellation note --}}
      @if($o->cancel_reason)
      <div style="margin-top:.65rem;background:#fef2f2;border-radius:8px;padding:.65rem .85rem;font-size:.81rem;color:#991b1b">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.25rem">Cancellation Reason</div>
        {{ $o->cancel_reason }}
      </div>
      @endif
    </div>

    {{-- Pickup Ready: Picked Up button or payment warning --}}
    @if($o->status === 'Ready for Pickup')
      @php
        $isCashPickup = ($o->fulfillment_type ?? 'Pickup') === 'Pickup' && strtoupper((string)($o->payment_method ?? '')) === 'COD';
        $canConfirmPickup = $o->payment_status === 'Paid' || $isCashPickup;
      @endphp
      @if($canConfirmPickup)
      <div style="border-top:1px solid var(--gray-100);padding:.9rem 1.25rem;background:linear-gradient(135deg,#f0fdf4 0%,#f8fafc 100%);display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
        <div style="display:flex;align-items:flex-start;gap:.75rem;flex:1;min-width:260px">
          <div style="width:2.4rem;height:2.4rem;border-radius:14px;background:#dcfce7;color:#16a34a;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="bi bi-bag-check"></i>
          </div>
          <div>
            <div style="font-size:.78rem;font-weight:800;color:#111827;letter-spacing:.04em">READY FOR PICKUP</div>
            <div style="font-size:.82rem;color:#16a34a;line-height:1.5;font-weight:600">
              @if($isCashPickup && $o->payment_status !== 'Paid')
                Collect the cash payment at the counter, then confirm that the customer has picked up the order.
              @else
                Payment complete. Confirm once the customer has picked up their order.
              @endif
            </div>
          </div>
        </div>
        <form action="{{ route('seller.orders.status', $o->id) }}" method="POST" style="margin-left:auto">
          @csrf
          <input type="hidden" name="status" value="Picked Up">
          <button type="submit"
                  style="background:#16a34a;color:#fff;border:none;border-radius:var(--radius-md);padding:.45rem 1.1rem;font-size:.82rem;font-weight:700;cursor:pointer"
                  data-cs-confirm="Mark this order as Picked Up?"
                  data-cs-title="{{ $isCashPickup && $o->payment_status !== 'Paid' ? 'Collect Cash and Confirm Pickup' : 'Confirm Pickup' }}"
                  data-cs-ok="{{ $isCashPickup && $o->payment_status !== 'Paid' ? 'Confirm Paid Pickup' : 'Yes, Picked Up' }}"
                  data-cs-ok-color="#16a34a"
                  data-cs-icon="bi-bag-check"
                  data-cs-icon-bg="#dcfce7"
                  data-cs-icon-color="#16a34a">
            <i class="bi bi-bag-check"></i> {{ $isCashPickup && $o->payment_status !== 'Paid' ? 'Collect Cash & Mark Picked Up' : 'Mark as Picked Up' }}
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
              @if($o->payment_method === 'GCash') (GCash — ₱{{ number_format((float)($o->total_price ?? 0) - (float)($o->deposit_amount ?? 0), 2) }} remaining)@endif.
              The <strong>Mark as Picked Up</strong> button will appear once GCash payment is completed.
            </div>
          </div>
        </div>
        <span style="background:#FEF3C7;color:#92400E;border:1.5px solid #FDE68A;border-radius:var(--radius-md);padding:.35rem .875rem;font-size:.78rem;font-weight:700;margin-left:auto">
          <i class="bi bi-lock"></i> Locked — Unpaid
        </span>
      </div>
      @endif
    @endif

    {{-- Awaiting Deposit — seller info only, no actions until paid --}}
    @if($o->status === 'Awaiting Deposit')
    <div style="border-top:1px solid var(--gray-100);padding:.9rem 1.25rem;background:linear-gradient(135deg,#fce4ec 0%,#f8fafc 100%);display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
      <div style="display:flex;align-items:flex-start;gap:.75rem;flex:1;min-width:260px">
        <div style="width:2.4rem;height:2.4rem;border-radius:14px;background:#f8bbd0;color:#880E4F;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="bi bi-lock"></i>
        </div>
        <div>
          <div style="font-size:.78rem;font-weight:800;color:#111827;letter-spacing:.04em">WAITING FOR DEPOSIT</div>
          <div style="font-size:.82rem;color:#880E4F;line-height:1.5;font-weight:600">
            Customer needs to pay the 50% deposit (₱{{ number_format((float)($o->deposit_amount ?? 0),2) }}) via GCash. The order will appear in your queue once payment is confirmed.
          </div>
        </div>
      </div>
      <span style="background:#FCE4EC;color:#880E4F;border:1.5px solid #F48FB1;border-radius:var(--radius-md);padding:.35rem .875rem;font-size:.78rem;font-weight:700;margin-left:auto">
        <i class="bi bi-hourglass-split"></i> Awaiting Customer Payment
      </span>
    </div>
    @endif

    {{-- Status Actions (non-final, non-pickup orders) --}}
    @if(!in_array($o->status, ['Awaiting Deposit','Delivered','Picked Up','Cancelled','Ready for Pickup']))
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

  {{ $orders->links('vendor.pagination.custom') }}
</div>

{{-- Order Detail Modal --}}
<div class="modal fade" id="orderDetailModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0" style="border-radius:16px">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-bold"><i class="bi bi-receipt me-1 text-primary"></i>Order Details</h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-2" id="orderDetailBody"></div>
      <div class="modal-footer border-0 pt-0">
        <button class="btn btn-secondary" data-bs-dismiss="modal" style="font-weight:600">
          <i class="bi bi-arrow-left me-1"></i>Back
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function showOrderDetail(id) {
  const src = document.getElementById('order-detail-' + id);
  document.getElementById('orderDetailBody').innerHTML = src ? src.innerHTML : '<p class="text-muted">No details found.</p>';
  new bootstrap.Modal(document.getElementById('orderDetailModal')).show();
}

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
