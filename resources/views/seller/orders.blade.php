@extends('layouts.app')
@section('page_title','Orders')
@section('content')
@php
  $sellerStatuses = ['Cancel Requests','Awaiting Deposit','Pending','Pending Review','Confirmed','Preparing','Pickup','Ready for Rider','Out for Delivery','Delivered','Picked Up','No Show','Cancelled'];
  $sellerStatusLabelMap = ['Pickup' => 'Ready for Pickup', 'No Show' => 'Unclaimed'];
@endphp
<style>
  .seller-order-item.action-needed {
    border-color:#f9a8d4!important;
    box-shadow:0 12px 34px rgba(219,39,119,.12);
  }
  .seller-action-pill {
    display:inline-flex;
    align-items:center;
    gap:.35rem;
    background:#fdf2f8;
    color:#be185d;
    border:1px solid #fbcfe8;
    font-size:.68rem;
    font-weight:800;
    padding:.2rem .55rem;
    border-radius:99px;
    white-space:nowrap;
  }
  .seller-remit-pill {
    display:inline-flex;
    align-items:center;
    gap:.35rem;
    border:1px solid #bae6fd;
    background:#f0f9ff;
    color:#075985;
    font-size:.68rem;
    font-weight:800;
    padding:.2rem .55rem;
    border-radius:99px;
    white-space:nowrap;
  }
  @media (max-width:575px) {
    .seller-action-pill,
    .seller-remit-pill {
      white-space:normal;
      line-height:1.2;
    }
  }
  .seller-order-tools {
    display:flex;
    gap:.5rem;
    flex-wrap:nowrap;
    align-items:center;
    width:100%;
    min-width:0;
  }
  .seller-order-search {
    flex:1 1 200px;
    min-width:90px;
    max-width:280px;
  }
  .status-filter-wrap {
    position: relative;
    flex: 0 0 168px;
    width: 168px;
    min-width: 124px;
    max-width: 168px;
  }
  .status-filter-toggle {
    width: 100%;
    border: 1px solid #d1d5db;
    background: #fff;
    border-radius: .45rem;
    min-height: 38px;
    padding: .38rem .62rem;
    font-size: .84rem;
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    justify-content: space-between;
  }
  .status-filter-label {
    font-weight: 600;
    color: #1f2937;
    flex: 1 1 auto;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    text-align: left;
  }
  .status-filter-toggle .status-filter-badge {
    flex: 0 0 auto;
    margin-left: auto;
  }
  .status-filter-chevron { font-size: .72rem; color: #6b7280; transition: transform .16s ease; }
  .status-filter-wrap.is-open .status-filter-chevron { transform: rotate(180deg); }
  .status-filter-menu {
    position: absolute;
    inset: calc(100% + 6px) 0 auto;
    z-index: 35;
    border: 1px solid #e5e7eb;
    border-radius: .6rem;
    background: #fff;
    box-shadow: 0 16px 40px rgba(15,23,42,.14);
    padding: .4rem;
    max-height: min(65vh, 360px);
    overflow: auto;
    display: none;
  }
  .status-filter-wrap.is-open .status-filter-menu { display: block; }
  .status-filter-item {
    width: 100%;
    border: 0;
    background: transparent;
    border-radius: .5rem;
    padding: .48rem .56rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .55rem;
    font-size: .8rem;
    color: #1f2937;
    text-align: left;
    opacity: 0;
    transform: translateY(-4px);
    transition: background-color .12s ease, color .12s ease;
    animation: statusMenuDrop .17s ease forwards;
    animation-delay: calc(var(--stagger, 0) * 20ms);
  }
  .status-filter-wrap:not(.is-open) .status-filter-item { animation: none; }
  .status-filter-item:hover,
  .status-filter-item:focus-visible { background: #f8fafc; outline: none; }
  .status-filter-item.active { background: #f1f5f9; color: #0f172a; font-weight: 700; }
  .status-filter-item-label {
    flex: 1 1 auto;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .status-filter-item .status-filter-badge,
  .status-filter-chevron {
    flex: 0 0 auto;
  }
  .status-filter-badge {
    min-width: 1.45rem;
    height: 1.45rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 .28rem;
    font-size: .68rem;
    line-height: 1;
    font-weight: 800;
    border: 1px solid transparent;
    white-space: nowrap;
  }
  .status-dot-all { background: #f3f4f6; color: #374151; border-color: #d1d5db; }
  .status-dot-Cancel-Requests,
  .status-dot-Pending,
  .status-dot-Pending-Review,
  .status-dot-Pickup { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
  .status-dot-Awaiting-Deposit { background: #fce7f3; color: #9d174d; border-color: #fbcfe8; }
  .status-dot-Confirmed,
  .status-dot-Preparing,
  .status-dot-Ready-for-Rider,
  .status-dot-Out-for-Delivery { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
  .status-dot-Delivered,
  .status-dot-Picked-Up { background: #ecfdf5; color: #166534; border-color: #bbf7d0; }
  .status-dot-No-Show,
  .status-dot-Cancelled { background: #fff7ed; color: #9a3412; border-color: #fed7aa; }
  @keyframes statusMenuDrop {
    to { opacity: 1; transform: translateY(0); }
  }
  @media (max-width:575px) {
    .seller-order-tools {
      gap:.35rem;
    }
    .seller-order-search {
      flex:1 1 0;
      min-width:74px;
      font-size:.76rem;
    }
    .status-filter-wrap {
      flex:0 0 106px;
      width:106px;
      min-width:100px;
      max-width:106px;
    }
    .status-filter-toggle {
      font-size:.72rem;
      min-height:34px;
      padding:.18rem .34rem;
    }
  }
  @media (max-width:420px) {
    .seller-order-search {
      min-width:86px;
    }
    .status-filter-wrap {
      flex:0 0 98px;
      width:98px;
      min-width:94px;
      max-width:98px;
    }
    .status-filter-toggle {
      font-size:.69rem;
      padding:.16rem .3rem;
    }
  }
</style>
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
        <div style="font-size:.8rem;color:var(--gray-500)">Search by customer, product, payment, or order status</div>
      </div>
      <div class="seller-order-tools">
        <input type="text" id="sellerOrderSearch" class="form-control seller-order-search" placeholder="Search orders..."
               value="{{ $search ?? '' }}"
               oninput="pgSearch(this.value)">
        <div class="status-filter-wrap" id="sellerStatusFilter"
             data-counts-url="{{ route('seller.orders.status_counts') }}"
             data-selected="{{ $status ?? 'All' }}">
          <button type="button" class="status-filter-toggle" id="sellerStatusToggle" aria-expanded="false" aria-haspopup="listbox">
            <span class="status-filter-label" id="sellerStatusLabel" title="{{ $sellerStatusLabelMap[$status ?? 'All'] ?? ($status ?? 'All status') }}">{{ $sellerStatusLabelMap[$status ?? 'All'] ?? ($status ?? 'All status') }}</span>
            <span class="status-filter-badge {{ ($status ?? 'All') === 'All' ? 'status-dot-all' : 'status-dot-' . str_replace(' ', '-', ($status ?? 'All')) }}" data-status-badge="{{ $status ?? 'All' }}" hidden></span>
            <i class="bi bi-chevron-down status-filter-chevron"></i>
          </button>
          <div class="status-filter-menu" id="sellerStatusMenu" role="listbox" tabindex="-1">
            <button type="button" class="status-filter-item{{ ($status ?? 'All') === 'All' ? ' active' : '' }}" data-status-value="All" style="--stagger:0">
              <span class="status-filter-item-label" title="All status">All status</span>
              <span class="status-filter-badge status-dot-all" data-status-badge="All" hidden></span>
            </button>
            @foreach($sellerStatuses as $st)
            <button type="button" class="status-filter-item{{ ($status ?? '') === $st ? ' active' : '' }}" data-status-value="{{ $st }}" style="--stagger:{{ $loop->iteration }}">
              <span class="status-filter-item-label" title="{{ $sellerStatusLabelMap[$st] ?? $st }}">{{ $sellerStatusLabelMap[$st] ?? $st }}</span>
              <span class="status-filter-badge status-dot-{{ str_replace(' ', '-', $st) }}" data-status-badge="{{ $st }}" hidden></span>
            </button>
            @endforeach
          </div>
          <input type="hidden" id="sellerOrderStatusFilter" value="{{ strtolower($status ?? 'All') }}">
        </div>
      </div>
    </div>
    <div id="sellerOrderFilterSummary" style="font-size:.78rem;color:var(--gray-500);margin-top:.6rem">Showing {{ $orders->firstItem() ?? 0 }} - {{ $orders->lastItem() ?? 0 }} of {{ $orders->total() }} orders</div>
  </div>

  @forelse($orders as $o)
  @php
    $custom = $customData[$o->id] ?? null;
    $isCustomOrder = ($o->order_type ?? '') === 'custom' || (bool) $custom;
    $isRequestBakeOrder = !empty($o->order_request_id);
    $requiresKitchen = $isCustomOrder || $isRequestBakeOrder;
    $isRegularOrder = !$requiresKitchen;
    $addons = $orderAddons[$o->id] ?? [];
    $items = $orderItems[$o->id] ?? [];
    $groupOrders = !empty($o->checkout_group_id) ? collect($checkoutGroupOrders[$o->checkout_group_id] ?? [])->where('id', '!=', $o->id)->values() : collect();
    $refund = $orderRefunds[$o->id] ?? null;
    $receipts = $paymentReceipts[$o->id] ?? [];
    $remittance = $riderRemittances[$o->id] ?? null;
    $hasPaidAmount = ($o->payment_status ?? '') === 'Paid'
      || ($o->deposit_status ?? '') === 'paid'
      || ($o->payment_status ?? '') === 'Partial Payment';
    $hasPaidCancelRequest = $refund && ($refund->status ?? '') === 'pending';
    $sellerCancelAwaiting = ($o->seller_cancel_state ?? '') === 'awaiting_customer'
      && !empty($o->seller_cancel_expires_at)
      && \Carbon\Carbon::parse($o->seller_cancel_expires_at)->isFuture();
    $isNoShowRefund = $refund && str_starts_with((string) ($refund->cancel_reason ?? ''), 'No-show balance refund:');
    $isCancelledOrder = ($o->status ?? '') === 'Cancelled' || ($o->cancel_status ?? '') === 'accepted';
    $needsRemittanceAction = $remittance && in_array(($remittance->status ?? ''), ['pending', 'submitted', 'rejected'], true);
    $needsPickupAction = ($o->status ?? '') === 'Pickup' || $hasPaidCancelRequest || $needsRemittanceAction;
    $orderStatusLabel = ($o->status ?? '') === 'Pickup' ? 'Ready for Pickup' : (($o->status ?? '') === 'No Show' ? 'Unclaimed' : $o->status);
    $sc = match($o->status) {
      'Awaiting Deposit'         => 'background:#FCE4EC;color:#880E4F',
      'Pending','Pending Review' => 'background:#FFF3E0;color:#E65100',
      'Confirmed'                => 'background:#E3F2FD;color:#1565C0',
      'Preparing'                => 'background:#F3E5F5;color:#6A1B9A',
      'Pickup'                   => 'background:#EDE9FE;color:#5B21B6',
      'Ready for Rider'          => 'background:#E0F2FE;color:#0369A1',
      'Out for Delivery'         => 'background:#E8F5E9;color:#2E7D32',
      'Delivered','Picked Up'    => 'background:#E8F5E9;color:#1B5E20',
      'No Show'                  => 'background:#FFF7ED;color:#9A3412',
      'Cancelled'                => 'background:#FFEBEE;color:#C62828',
      default                    => 'background:#F5F5F5;color:#616161',
    };
  @endphp

  <div class="seller-order-item {{ $needsPickupAction ? 'action-needed' : '' }}"
       data-search="{{ strtolower(trim(($o->order_customer_name ?? 'customer') . ' ' . ($o->product_name ?? ($custom->cake_name ?? 'custom cake')) . ' ' . ($o->payment_status ?? '') . ' ' . ($o->payment_method ?? '') . ' ' . ($o->status ?? '') . ' ' . ($remittance->status ?? ''))) }}"
       data-status="{{ strtolower($o->status ?? '') }}"
       data-fulfillment="{{ strtolower($o->fulfillment_type ?? 'pickup') }}"
       data-order-id="{{ $o->id }}"
       data-payment-method="{{ $o->payment_method ?? '' }}"
       data-payment-status="{{ $o->payment_status ?? '' }}"
       data-realtime-url="{{ route('seller.orders.realtime_status', $o->id) }}"
       style="background:#fff;border-radius:var(--radius-lg);border:1.5px solid var(--gray-100);margin-bottom:1rem;overflow:hidden">

    {{-- Order Header --}}
    @php
      $customRefs = [];
      if ($custom) {
          $decodedRefs = json_decode($custom->reference_images ?? '[]', true);
          $customRefs = is_array($decodedRefs) ? array_values(array_filter($decodedRefs)) : [];
      }
      $thumb = $custom ? ($customRefs[0] ?? null) : ($o->product_image_path ?? null);
    @endphp
    <div style="padding:1rem 1.25rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;background:{{ $needsPickupAction ? '#fff7fb' : (in_array($o->status,['Pending','Pending Review']) ? '#FFFBF5' : '#fff') }}">
      {{-- Thumbnail --}}
      @if($thumb)
        <div data-lightbox-gallery="order-thumb-{{ $o->id }}" style="display:flex">
        @if($custom && count($customRefs) > 0)
          @foreach($customRefs as $idx => $refImg)
            <img src="{{ $refImg }}" data-src="{{ $refImg }}" class="chat-img" onclick="openLightbox(this)"
                 style="width:58px;height:58px;border-radius:10px;object-fit:cover;flex-shrink:0;cursor:zoom-in;border:1.5px solid var(--gray-100);{{ $idx === 0 ? '' : 'display:none' }}">
          @endforeach
        @else
        <img src="{{ $thumb }}" data-src="{{ $thumb }}" class="chat-img" onclick="openLightbox(this)"
             style="width:58px;height:58px;border-radius:10px;object-fit:cover;flex-shrink:0;cursor:zoom-in;border:1.5px solid var(--gray-100)">
        @endif
        </div>
      @else
        <div style="width:58px;height:58px;border-radius:10px;background:#fdf2f8;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1.5px solid var(--gray-100)">
          <i class="bi bi-{{ $custom ? 'stars' : 'bag' }}" style="font-size:1.4rem;color:#be185d"></i>
        </div>
      @endif
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.25rem">
          <span style="{{ $sc }};font-size:.7rem;font-weight:700;padding:.2rem .65rem;border-radius:99px">{{ $orderStatusLabel }}</span>
          @if($needsPickupAction)
            <span class="seller-action-pill"><i class="bi bi-exclamation-circle-fill"></i>Action needed</span>
          @endif
          @if($remittance)
            @php
              $remitLabel = match($remittance->status ?? 'pending') {
                'submitted' => 'COD remittance for review',
                'confirmed' => 'COD remitted',
                'rejected' => 'COD remittance rejected',
                'awaiting_payment' => 'GCash QR awaiting payment',
                'qr_expired' => 'GCash QR expired',
                default => 'COD remittance pending',
              };
            @endphp
            <span class="seller-remit-pill"><i class="bi bi-cash-stack"></i>{{ $remitLabel }}</span>
          @endif
          @if($isCustomOrder)
            <span style="background:var(--primary-bg);color:var(--primary);font-size:.68rem;font-weight:700;padding:.2rem .5rem;border-radius:99px">Custom</span>
          @elseif($isRequestBakeOrder)
            <span style="background:#fff7ed;color:#c2410c;font-size:.68rem;font-weight:700;padding:.2rem .5rem;border-radius:99px"><i class="bi bi-fire me-1"></i>Request to Bake</span>
          @else
            <span style="background:#ecfdf5;color:#047857;font-size:.68rem;font-weight:700;padding:.2rem .5rem;border-radius:99px">Ready-made</span>
          @endif
        </div>
        <div style="font-size:.8rem;color:var(--gray-700);font-weight:600">{{ $o->order_customer_name ?? 'Customer' }}</div>
        <div style="font-size:.75rem;color:var(--gray-500)">
          {{ $o->product_name ?? ($custom->cake_name ?? 'Custom Cake') }}
          &bull; {{ $o->fulfillment_type ?? 'Pickup' }} @if(!empty($o->is_surprise_delivery)) <span class="badge" style="background:#fff7ed;color:#9a3412;border:1px solid #fed7aa"><i class="bi bi-gift me-1"></i>Surprise</span> @endif
          @if($o->schedule_date) &bull; {{ \Carbon\Carbon::parse($o->schedule_date)->format('M d, Y') }} @endif
        </div>
        @if($groupOrders->isNotEmpty())
          <div class="mt-1 small" style="color:#0369a1;font-weight:700">
            <i class="bi bi-diagram-3 me-1"></i>Same checkout: {{ $groupOrders->count() }} linked order{{ $groupOrders->count() > 1 ? 's' : '' }} @if(!empty($o->processing_mode)) - {{ $o->processing_mode === 'together' ? 'Process together' : 'Process separately' }} @endif
          </div>
          <div class="small text-muted">
            @foreach($groupOrders->take(3) as $linkedOrder)
              <div>#{{ $linkedOrder->id }} - {{ $linkedOrder->product_name }} ({{ $linkedOrder->status }})</div>
            @endforeach
          </div>
        @endif
        @if(count($items) > 1)
          <div class="small mt-1" style="color:var(--primary);font-weight:700">
            <i class="bi bi-link-45deg me-1"></i>{{ count($items) }} cakes in this seller checkout
          </div>
        @endif
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.4rem;flex-shrink:0">
        <div style="font-size:1rem;font-weight:700;color:var(--primary)">{{ number_format((float)($o->total_price ?? 0),2) }}</div>
        <div data-payment-status-label="{{ $o->id }}" style="font-size:.72rem;color:{{ $o->payment_status==='Paid' ? 'var(--success,#2E7D32)' : ($o->payment_status==='Partial Payment' ? '#E65100' : 'var(--gray-500)') }};font-weight:600">
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
        if ($custom) {
          $allRefs = $customRefs;
        } elseif ($o->product_image_path) {
          $allRefs[] = $o->product_image_path;
        }
      @endphp

      {{-- Images row --}}
      @if(count($allRefs) || $o->delivery_photo || $o->issue_photo || ($custom && ($custom->progress_image ?? null)))
      <div style="margin-bottom:1rem">
        <div style="font-size:.72rem;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.5rem">Photos</div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap" data-lightbox-gallery="order-detail-{{ $o->id }}">
          @foreach($allRefs as $img)
            <img src="{{ $img }}" data-src="{{ $img }}" class="chat-img" onclick="openLightbox(this)"
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
          @if($custom && ($custom->progress_image ?? null))
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
      @if($isCancelledOrder)
      <div style="margin-bottom:1rem;background:#FEF2F2;border-radius:10px;padding:.75rem 1rem;display:flex;align-items:center;gap:.75rem">
        <i class="bi bi-x-circle-fill" style="color:#B91C1C;font-size:1.1rem;flex-shrink:0"></i>
        <div>
          <div style="font-size:.78rem;font-weight:800;color:#991B1B;letter-spacing:.04em">ORDER CANCELLED</div>
          <div style="font-size:.8rem;color:#991B1B;margin-top:.1rem">
            @if($refund && $refund->status === 'refunded')
              Refund sent. Reference: {{ $refund->reference_number ?: 'receipt uploaded' }}.
            @elseif($refund && $refund->status === 'pending')
              Paid cancellation/refund request is waiting for review.
            @elseif($o->deposit_status === 'paid' || in_array(($o->payment_status ?? ''), ['Partial Payment','Paid']))
              Cancelled with payment recorded. Check refund status before payout.
            @else
              Cancelled before payment. No deposit is needed.
            @endif
          </div>
        </div>
      </div>
      @elseif($o->deposit_required && ($o->deposit_status ?? '') !== 'paid')
      <div style="margin-bottom:1rem;background:#FCE4EC;border-radius:10px;padding:.75rem 1rem;display:flex;align-items:center;gap:.75rem">
        <i class="bi bi-lock-fill" style="color:#880E4F;font-size:1.1rem;flex-shrink:0"></i>
        <div>
          <div style="font-size:.78rem;font-weight:800;color:#880E4F;letter-spacing:.04em">AWAITING DEPOSIT</div>
          <div style="font-size:.8rem;color:#880E4F;margin-top:.1rem">
            Customer must pay {{ number_format((float)($o->deposit_amount ?? 0), 2) }} deposit via GCash before this order enters the queue.
          </div>
        </div>
      </div>
      @elseif($o->deposit_required && ($o->deposit_status ?? '') === 'paid')
      <div style="margin-bottom:1rem;background:#E8F5E9;border-radius:10px;padding:.75rem 1rem;display:flex;align-items:center;gap:.75rem">
        <i class="bi bi-check-circle-fill" style="color:#2E7D32;font-size:1.1rem;flex-shrink:0"></i>
        <div>
          <div style="font-size:.78rem;font-weight:800;color:#2E7D32;letter-spacing:.04em">DEPOSIT RECEIVED</div>
          <div style="font-size:.8rem;color:#2E7D32;margin-top:.1rem">
            {{ number_format((float)($o->deposit_amount ?? 0), 2) }} deposit paid. Remaining {{ number_format((float)($o->total_price ?? 0) - (float)($o->deposit_amount ?? 0), 2) }} to be collected on {{ $o->fulfillment_type === 'Delivery' ? 'delivery' : 'pickup' }}.
          </div>
        </div>
      </div>
      @endif

      {{-- Info grid --}}
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem 1.25rem;font-size:.82rem">
        <div><span style="color:var(--gray-500)">Status</span><br><strong>{{ $orderStatusLabel }}</strong></div>
        <div><span style="color:var(--gray-500)">Customer</span><br><strong>{{ $o->order_customer_name ?? 'Customer' }}</strong></div>
        <div>
          <span style="color:var(--gray-500)">Phone</span><br>
          <strong>{{ $o->order_customer_phone ?? '-' }}</strong>
          @include('shared.customer_risk_badge', ['risk' => $customerRiskMap[$o->id] ?? null, 'compact' => true])
        </div>
        <div><span style="color:var(--gray-500)">Product</span><br><strong>{{ $o->product_name ?? ($custom->cake_name ?? 'Custom Cake') }}</strong></div>
        @if($groupOrders->isNotEmpty())
          <div class="mt-1 small" style="color:#0369a1;font-weight:700">
            <i class="bi bi-diagram-3 me-1"></i>Same checkout: {{ $groupOrders->count() }} linked order{{ $groupOrders->count() > 1 ? 's' : '' }} @if(!empty($o->processing_mode)) - {{ $o->processing_mode === 'together' ? 'Process together' : 'Process separately' }} @endif
          </div>
          <div class="small text-muted">
            @foreach($groupOrders->take(3) as $linkedOrder)
              <div>#{{ $linkedOrder->id }} - {{ $linkedOrder->product_name }} ({{ $linkedOrder->status }})</div>
            @endforeach
          </div>
        @endif
        @if(count($items) > 1)
          <div style="grid-column:1/-1">
            <span style="color:var(--gray-500)">Grouped Items</span><br>
            @foreach($items as $item)
              <div class="small">&bull; <strong>{{ $item->product_name }}</strong> x{{ $item->quantity }} @if($item->selected_size) ({{ $item->selected_size }}) @endif</div>
            @endforeach
          </div>
        @endif
        <div><span style="color:var(--gray-500)">Qty / Size</span><br><strong>{{ $o->quantity ?? 1 }}x {{ $o->selected_size ?? ($custom->size_label ?? '-') }}</strong></div>
        <div><span style="color:var(--gray-500)">Fulfillment</span><br><strong>{{ $o->fulfillment_type ?? 'Pickup' }}</strong></div>
        <div><span style="color:var(--gray-500)">Schedule</span><br><strong>{{ $o->schedule_date ? \Carbon\Carbon::parse($o->schedule_date)->format('M d, Y') : '-' }}{{ $o->schedule_time ? ' '.$o->schedule_time : '' }}</strong></div>
        @if($o->fulfillment_type === 'Delivery')
        <div style="grid-column:1/-1"><span style="color:var(--gray-500)">Delivery Address</span><br><strong>{{ $o->delivery_address ?? '-' }}</strong></div>
        @endif
        <div><span style="color:var(--gray-500)">Payment</span><br><strong>{{ $o->payment_method ?? '-' }}</strong></div>
        <div><span style="color:var(--gray-500)">Payment Status</span><br>
          <strong style="color:{{ $o->payment_status==='Paid' ? '#16a34a' : ($o->payment_status==='Partial Payment' ? '#d97706' : '#6b7280') }}">
            {{ $o->payment_status ?? 'Unpaid' }}
          </strong>
        </div>
        <div><span style="color:var(--gray-500)">Total</span><br><strong style="color:var(--primary)">{{ number_format((float)($o->total_price ?? 0),2) }}</strong></div>
        @if($o->delivery_fee)
        <div><span style="color:var(--gray-500)">Delivery Fee</span><br><strong>{{ number_format((float)($o->delivery_fee ?? 0),2) }}</strong></div>
        @endif
        @if($o->deposit_required)
        <div><span style="color:var(--gray-500)">Deposit</span><br>
          <strong style="color:{{ $isCancelledOrder ? '#991b1b' : (($o->deposit_status??'') === 'paid' ? '#16a34a' : '#880E4F') }}">
            @if($isCancelledOrder)
              {{ ($o->deposit_status??'') === 'paid' || in_array(($o->payment_status ?? ''), ['Partial Payment','Paid']) ? 'Cancelled - refund status shown below' : 'Cancelled before payment' }}
            @else PHP {{ number_format((float)($o->deposit_amount ?? 0),2) }} - {{ ($o->deposit_status??'') === 'paid' ? 'Paid' : 'Pending' }}
            @endif
          </strong>
        </div>
        @endif
      </div>

      {{-- Payment receipts --}}
      <div style="margin-top:.95rem;background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:.75rem .9rem">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;margin-bottom:.5rem">
          <div style="font-size:.72rem;font-weight:800;color:#334155;text-transform:uppercase;letter-spacing:.06em">
            <i class="bi bi-receipt me-1"></i>Payment Receipts
          </div>
          <span class="badge text-bg-light">{{ count($receipts) }} transaction{{ count($receipts) === 1 ? '' : 's' }}</span>
        </div>
        @forelse($receipts as $receipt)
          @php
            $receiptLabel = \App\Helpers\PaymentTransactionHelper::typeLabel($receipt->type ?? null);
            $receiptDate = $receipt->paid_at ?? $receipt->created_at ?? null;
          @endphp
          <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;padding:.5rem 0;border-top:{{ $loop->first ? '0' : '1px solid #e5e7eb' }}">
            <div style="min-width:190px">
              <div style="font-size:.82rem;font-weight:700;color:#111827">{{ $receiptLabel }}</div>
              <div style="font-size:.74rem;color:#64748b">
                {{ $receipt->method ?? 'Payment' }}{{ $receipt->provider_reference ? ' / Ref: '.$receipt->provider_reference : '' }}
                @if($receiptDate) &bull; {{ \Carbon\Carbon::parse($receiptDate)->format('M d, Y g:i A') }} @endif
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-left:auto">
              <strong style="font-size:.85rem;color:#16a34a">PHP {{ number_format((float)($receipt->amount ?? 0), 2) }}</strong>
              <button type="button" class="btn btn-outline-primary btn-sm"
                      data-receipt-url="{{ route('seller.orders.receipt', ['id' => $o->id, 'transactionId' => $receipt->id], false) }}"
                      data-receipt-title="{{ $receiptLabel }}">
                <i class="bi bi-eye me-1"></i>View
              </button>
            </div>
          </div>
        @empty
          <div style="font-size:.8rem;color:#64748b">No payment receipt has been recorded yet.</div>
        @endforelse
      </div>

      @if($remittance)
      @php
        $remitStatus = $remittance->status ?? 'pending';
        $remitBg = match($remitStatus) {
          'confirmed' => '#ecfdf5',
          'submitted' => '#eff6ff',
          'awaiting_payment' => '#eff6ff',
          'rejected' => '#fef2f2',
          'qr_expired' => '#f8fafc',
          default => '#fffbeb',
        };
        $remitBorder = match($remitStatus) {
          'confirmed' => '#bbf7d0',
          'submitted' => '#bfdbfe',
          'awaiting_payment' => '#bfdbfe',
          'rejected' => '#fecaca',
          'qr_expired' => '#e5e7eb',
          default => '#fde68a',
        };
      @endphp
      <div style="margin-top:.75rem;background:{{ $remitBg }};border:1px solid {{ $remitBorder }};border-radius:10px;padding:.75rem .9rem;font-size:.81rem;color:#111827">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap">
          <div>
            <div style="font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#334155;margin-bottom:.25rem">Rider COD Remittance</div>
            <div><strong>Amount collected:</strong> PHP {{ number_format((float)$remittance->amount, 2) }}</div>
            <div><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $remitStatus)) }}</div>
            @if($remittance->remittance_method)<div><strong>Method:</strong> {{ $remittance->remittance_method === 'gcash_paymongo' ? 'GCash QR via PayMongo' : ($remittance->remittance_method === 'gcash' ? 'GCash transfer' : 'Cash handover') }}</div>@endif
            @if(!empty($remittance->batch_id))<div><strong>Batch:</strong> Bulk remittance #{{ $remittance->batch_id }}</div>@endif
            @if($remittance->reference_number)<div><strong>Reference:</strong> {{ $remittance->reference_number }}</div>@endif
            @if(!empty($remittance->paymongo_payment_intent_id))<div><strong>PayMongo ID:</strong> {{ $remittance->paymongo_payment_intent_id }}</div>@endif
            @if(!empty($remittance->paymongo_paid_at))<div><strong>Verified at:</strong> {{ \Carbon\Carbon::parse($remittance->paymongo_paid_at)->format('M d, Y g:i A') }}</div>@endif
            @if($remittance->rider_note)<div><strong>Rider note:</strong> {{ $remittance->rider_note }}</div>@endif
            @if($remittance->seller_note)<div><strong>Seller note:</strong> {{ $remittance->seller_note }}</div>@endif
          </div>
          @if($remittance->receipt_path)
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.open(@js($remittance->receipt_path), '_blank', 'noopener')">
              <i class="bi bi-image me-1"></i>Proof
            </button>
          @endif
        </div>
      </div>
      @endif

      {{-- Add-ons --}}
      @if(!empty($addons))
      <div style="margin-top:.85rem">
        <div style="font-size:.72rem;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.4rem">Add-ons</div>
        @foreach($addons as $a)
        <div style="font-size:.81rem;color:var(--gray-700)">{{ $a->addon_name ?? '-' }} @if($a->addon_price ?? 0) PHP {{ number_format((float)($a->addon_price ?? 0),2) }} @endif</div>
        @endforeach
      </div>
      @endif

      {{-- Special notes --}}
      @if($o->special_notes || ($custom && ($custom->special_notes ?? null)))
      <div style="margin-top:.85rem;background:#fffbeb;border-radius:8px;padding:.65rem .85rem;font-size:.81rem;color:#92400e">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.25rem">Special Notes</div>
        {{ $o->special_notes ?? ($custom->special_notes ?? '') }}
      </div>
      @endif

      {{-- Custom order description --}}
      @if($custom && ($custom->description ?? null))
      <div style="margin-top:.65rem;background:#f0fdf4;border-radius:8px;padding:.65rem .85rem;font-size:.81rem;color:#166534">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.25rem">Custom Cake Description</div>
        {{ $custom->description ?? '' }}
      </div>
      @endif

      {{-- Cancellation note --}}
      @if($o->cancel_reason)
      <div style="margin-top:.65rem;background:#fef2f2;border-radius:8px;padding:.65rem .85rem;font-size:.81rem;color:#991b1b">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.25rem">Cancellation Reason</div>
        {{ $o->cancel_reason }}
      </div>
      @endif

      @if($refund)
      <div style="margin-top:.65rem;background:{{ $refund->status === 'pending' ? '#fff7ed' : ($refund->status === 'refunded' ? '#ecfdf5' : '#f8fafc') }};border-radius:8px;padding:.75rem .9rem;font-size:.81rem;color:#111827;border:1px solid {{ $refund->status === 'pending' ? '#fed7aa' : ($refund->status === 'refunded' ? '#bbf7d0' : '#e5e7eb') }}">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap">
          <div>
            <div style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#92400e;margin-bottom:.25rem">
              {{ $refund->status === 'pending' ? 'Paid Cancellation / Refund Request' : 'Refund Status' }}
            </div>
            <div><strong>Refund amount:</strong> {{ number_format((float)$refund->refund_amount, 2) }}</div>
            <div><strong>GCash:</strong> {{ $refund->refund_gcash_name }} / {{ $refund->refund_gcash_number }}</div>
            @if($refund->review_note)<div><strong>Note:</strong> {{ $refund->review_note }}</div>@endif
            @if($refund->reference_number)<div><strong>Reference:</strong> {{ $refund->reference_number }}</div>@endif
          </div>
          <span class="badge text-bg-{{ $refund->status === 'refunded' ? 'success' : ($refund->status === 'rejected' ? 'danger' : 'warning') }}">
            {{ ucfirst($refund->status) }}
          </span>
        </div>
      </div>
      @endif
    </div>

    @if($needsRemittanceAction)
    <div style="border-top:1px solid var(--gray-100);padding:.95rem 1.25rem;background:#eff6ff">
      <div style="display:flex;align-items:flex-start;gap:.75rem;flex-wrap:wrap">
        <div style="width:2.4rem;height:2.4rem;border-radius:14px;background:#dbeafe;color:#1d4ed8;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="bi bi-cash-stack"></i>
        </div>
        <div style="flex:1;min-width:260px">
          <div style="font-size:.78rem;font-weight:800;color:#111827;letter-spacing:.04em">COD REMITTANCE REVIEW</div>
          <div style="font-size:.82rem;color:#1e40af;line-height:1.5;font-weight:600">
            @if(($remittance->status ?? '') === 'submitted')
              Rider submitted PHP {{ number_format((float)$remittance->amount, 2) }}. Confirm only after you received the GCash transfer or cash handover.
            @elseif(($remittance->status ?? '') === 'rejected')
              This remittance was rejected. You can confirm if the rider has already corrected it, or reject again with a clearer note.
            @else
              Rider collected PHP {{ number_format((float)$remittance->amount, 2) }} COD. Confirm if the cash was already handed over to you, or reject to require rider submission/correction.
            @endif
          </div>
        </div>
      </div>
      <div class="row g-3 mt-1">
        <div class="col-lg-6">
          <form action="{{ route('seller.orders.remittance_confirm', ['id' => $o->id, 'remittanceId' => $remittance->id]) }}" method="POST" data-prevent-double-submit>
            @csrf
            <label class="form-label small fw-bold">Confirmation note</label>
            <input name="seller_note" class="form-control form-control-sm mb-2" maxlength="500" placeholder="Optional note">
            <button class="btn btn-success btn-sm" type="submit"
                    data-cs-confirm="Confirm that this COD money was received by the seller?"
                    data-cs-title="Confirm COD Remittance"
                    data-cs-ok="Confirm Received"
                    data-cs-icon="bi-check2-circle"
                    data-cs-icon-bg="#dcfce7"
                    data-cs-icon-color="#16a34a">
              <i class="bi bi-check2-circle me-1"></i>Confirm Received
            </button>
          </form>
        </div>
        <div class="col-lg-6">
          <form action="{{ route('seller.orders.remittance_reject', ['id' => $o->id, 'remittanceId' => $remittance->id]) }}" method="POST" data-prevent-double-submit>
            @csrf
            <label class="form-label small fw-bold">Correction reason <span class="text-danger">*</span></label>
            <input name="seller_note" class="form-control form-control-sm mb-2" minlength="5" maxlength="500" required placeholder="e.g. amount/reference does not match">
            <button class="btn btn-outline-danger btn-sm" type="submit">
              <i class="bi bi-x-circle me-1"></i>Reject
            </button>
          </form>
        </div>
      </div>
    </div>
    @endif

    @if($sellerCancelAwaiting)
    <div style="border-top:1px solid var(--gray-100);padding:.95rem 1.25rem;background:#eff6ff" data-seller-cancel-countdown="{{ \Carbon\Carbon::parse($o->seller_cancel_expires_at)->toIso8601String() }}">
      <div style="display:flex;align-items:flex-start;gap:.75rem;flex-wrap:wrap">
        <div style="width:2.4rem;height:2.4rem;border-radius:14px;background:#dbeafe;color:#1d4ed8;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="bi bi-hourglass-split"></i></div>
        <div style="flex:1;min-width:220px">
          <div style="font-size:.78rem;font-weight:800;color:#111827;letter-spacing:.04em">WAITING FOR CUSTOMER RESPONSE</div>
          <div style="font-size:.82rem;color:#1d4ed8;line-height:1.5;font-weight:600">Customer must accept the cancellation and provide refund details. If there is no response, the order continues processing.</div>
        </div>
        <span class="seller-cancel-countdown" style="background:#dbeafe;color:#1d4ed8;border-radius:999px;padding:.35rem .65rem;font-weight:800;font-variant-numeric:tabular-nums">10:00</span>
      </div>
    </div>
    @endif

    @if($hasPaidCancelRequest)
    <div style="border-top:1px solid var(--gray-100);padding:.95rem 1.25rem;background:#fff7ed">
      <div style="display:flex;align-items:flex-start;gap:.75rem;flex-wrap:wrap">
        <div style="width:2.4rem;height:2.4rem;border-radius:14px;background:#fed7aa;color:#c2410c;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="bi bi-exclamation-triangle-fill"></i>
        </div>
        <div style="flex:1;min-width:260px">
          <div style="font-size:.78rem;font-weight:800;color:#111827;letter-spacing:.04em">SELLER REFUND ACTION NEEDED</div>
          <div style="font-size:.82rem;color:#92400e;line-height:1.5;font-weight:600">
            @if($isNoShowRefund)
              Customer unclaimed pickup: retain the 50% deposit and refund the excess PHP {{ number_format((float)$refund->refund_amount, 2) }} only.
            @else
              Refund the paid amount of PHP {{ number_format((float)$refund->refund_amount, 2) }} before approving cancellation.
            @endif
          </div>
        </div>
      </div>
      <div class="row g-3 mt-1">
        <div class="col-lg-7">
          <form action="{{ route('seller.orders.accept_cancel', $o->id) }}" method="POST" enctype="multipart/form-data" data-prevent-double-submit>
            @csrf
            <label class="form-label small fw-bold">Refund receipt screenshot <span class="text-danger">*</span></label>
            <input type="file" name="refund_receipt" class="form-control form-control-sm mb-2" accept="image/*" required>
            <div class="row g-2">
              <div class="col-md-6">
                <input name="refund_gcash_name" class="form-control form-control-sm" maxlength="160" placeholder="GCash account name" value="{{ $refund->refund_gcash_name ?? '' }}" required>
              </div>
              <div class="col-md-6">
                <input name="refund_gcash_number" class="form-control form-control-sm" maxlength="80" inputmode="tel" placeholder="09XXXXXXXXX or +639XXXXXXXXX" value="{{ $refund->refund_gcash_number ?? '' }}" required>
              </div>
              <div class="col-md-5">
                <input name="reference_number" class="form-control form-control-sm" placeholder="GCash ref no.">
              </div>
              <div class="col-md-7">
                <input name="admin_note" class="form-control form-control-sm" placeholder="Note to customer" value="Cancellation approved and refund sent.">
              </div>
            </div>
            <button class="btn btn-success btn-sm mt-2" type="submit"
                    data-cs-confirm="Confirm the GCash refund was sent to the listed account and the uploaded receipt is correct?"
                    data-cs-title="Confirm Refund Sent"
                    data-cs-ok="Save Receipt"
                    data-cs-icon="bi-cash-coin"
                    data-cs-icon-bg="#dcfce7"
                    data-cs-icon-color="#16a34a">
              <i class="bi bi-check2-circle me-1"></i>Record Refund Sent
            </button>
          </form>
        </div>
        <div class="col-lg-5">
          <form action="{{ route('seller.orders.reject_cancel', $o->id) }}" method="POST" data-prevent-double-submit>
            @csrf
            <label class="form-label small fw-bold">Reject reason</label>
            <input name="admin_note" class="form-control form-control-sm mb-2" required placeholder="Reason shown to customer">
            <button class="btn btn-outline-danger btn-sm" type="submit">
              <i class="bi bi-x-circle me-1"></i>Reject Request
            </button>
          </form>
        </div>
      </div>
    </div>
    @endif

    {{-- Pickup Ready: Picked Up button or payment warning --}}
    @if($o->status === 'Pickup')
      @php
        // COP orders store payment_method='COP'; legacy orders may have 'COD' for pickup.
        $isCashPickup = ($o->fulfillment_type ?? 'Pickup') === 'Pickup'
            && in_array(strtoupper((string)($o->payment_method ?? '')), ['COP', 'COD']);
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
      <div data-pickup-payment-wait="{{ $o->id }}" style="border-top:1px solid var(--gray-100);padding:.9rem 1.25rem;background:linear-gradient(135deg,#fffbeb 0%,#f8fafc 100%);display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
        <div style="display:flex;align-items:flex-start;gap:.75rem;flex:1;min-width:260px">
          <div style="width:2.4rem;height:2.4rem;border-radius:14px;background:#fef3c7;color:#d97706;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="bi bi-clock-history"></i>
          </div>
          <div>
            <div style="font-size:.78rem;font-weight:800;color:#111827;letter-spacing:.04em">AWAITING PAYMENT</div>
            <div style="font-size:.82rem;color:#d97706;line-height:1.5;font-weight:600">
              Order is ready but customer still has an unpaid balance
              @if($o->payment_method === 'GCash') (GCash PHP {{ number_format((float)($o->total_price ?? 0) - (float)($o->deposit_amount ?? 0), 2) }} remaining)@endif.
              The <strong>Mark as Picked Up</strong> button will appear once GCash payment is completed.
            </div>
          </div>
        </div>
        <span style="background:#FEF3C7;color:#92400E;border:1.5px solid #FDE68A;border-radius:var(--radius-md);padding:.35rem .875rem;font-size:.78rem;font-weight:700;margin-left:auto">
          <i class="bi bi-lock"></i> Locked - Unpaid
        </span>
      </div>
      @endif
      <div style="border-top:1px solid #fed7aa;padding:.85rem 1.25rem;background:#fff7ed">
        @if($isRequestBakeOrder)
        @if(!empty($o->unclaimed_grace_expires_at))
          <div class="small fw-semibold" style="color:#9a3412"><i class="bi bi-hourglass-split me-1"></i>Three-day unclaimed window ends {{ \Carbon\Carbon::parse($o->unclaimed_grace_expires_at)->format('M d, Y g:i A') }}. Customer has been notified.</div>
        @else
        <form action="{{ route('seller.orders.start_unclaimed_window', $o->id) }}" method="POST" enctype="multipart/form-data" class="row g-2 align-items-end">
          @csrf
          <div class="col-md-4"><label class="form-label small fw-bold mb-1">Contact attempts</label><input name="contact_note" class="form-control form-control-sm" minlength="10" maxlength="500" required placeholder="Call/message date and result"></div>
          <div class="col-md-2"><label class="form-label small fw-bold mb-1">Resale price</label><input name="resale_price" type="number" min="1" step=".01" value="{{ max(1, (float)$o->total_price - (float)$o->deposit_amount) }}" class="form-control form-control-sm" required></div>
          <div class="col-md-3"><label class="form-label small fw-bold mb-1">Actual cake photo</label><input name="finished_cake_photo" type="file" accept="image/*" class="form-control form-control-sm" required></div>
          <div class="col-md-3"><label class="form-label small fw-bold mb-1">Safe until</label><input name="safe_until" type="datetime-local" class="form-control form-control-sm"><div class="form-check mt-1"><input class="form-check-input" name="safe_and_generic" value="1" type="checkbox" required id="safeCake{{ $o->id }}"><label class="form-check-label small" for="safeCake{{ $o->id }}">Safe and not personalized</label></div></div>
          <div class="col-12"><button type="submit" class="btn btn-outline-danger btn-sm" data-cs-confirm="Start the three-day claim period? After it expires, the confirmed safe cake will be posted as a pickup-only ready cake." data-cs-title="Start Unclaimed Window"><i class="bi bi-clock-history me-1"></i>Start 3-Day Unclaimed Window</button></div>
        </form>
        @endif
        @else
        <form action="{{ route('seller.orders.start_unclaimed_window', $o->id) }}" method="POST" enctype="multipart/form-data" class="row g-2 align-items-end">
          @csrf
          <div class="flex-grow-1">
            <label class="form-label small fw-bold mb-1" for="pickupNoShowNote{{ $o->id }}">Contact attempts</label>
            <input id="pickupNoShowNote{{ $o->id }}" name="contact_note" class="form-control form-control-sm" minlength="10" maxlength="500" required
                   placeholder="Record pickup time and contact attempts">
            <div class="small text-muted mt-1">Record only after the agreed pickup time and contact attempts. The 50% deposit is retained.</div>
          </div>
          <input name="resale_price" type="number" min="1" step=".01" value="{{ max(1, (float)$o->total_price - (float)$o->deposit_amount) }}" class="form-control form-control-sm" required>
          <input name="finished_cake_photo" type="file" accept="image/*" class="form-control form-control-sm" required>
          <label class="small"><input name="safe_and_generic" value="1" type="checkbox" required> Safe and not personalized</label>
          <button type="submit" class="btn btn-outline-danger btn-sm flex-shrink-0"
                  data-cs-confirm="Start the 3-day claim period? After expiry, the safe cake will be posted as a pickup-only ready cake."
                  data-cs-title="Start Unclaimed Window"
                  data-cs-ok="Start 3 Days"
                  data-cs-ok-color="#b91c1c"
                  data-cs-icon="bi-person-x"
                  data-cs-icon-bg="#ffedd5"
                  data-cs-icon-color="#c2410c">
            <i class="bi bi-person-x me-1"></i>Record Unclaimed
          </button>
        </form>
        @endif
      </div>
    @endif

    @if($o->status === 'Returned to Shop' && !$isCustomOrder)
    <div style="border-top:1px solid #bfdbfe;padding:.85rem 1.25rem;background:#eff6ff">
      <div class="small fw-bold mb-2" style="color:#1d4ed8"><i class="bi bi-arrow-return-left me-1"></i>Failed delivery returned to shop — confirm safe return, then start the 3-day customer claim window.</div>
      @if(!empty($o->unclaimed_grace_expires_at))
        <div class="small" style="color:#1e40af">Claim window ends {{ \Carbon\Carbon::parse($o->unclaimed_grace_expires_at)->format('M d, Y g:i A') }}.</div>
      @else
      <form action="{{ route('seller.orders.start_unclaimed_window', $o->id) }}" method="POST" enctype="multipart/form-data" class="row g-2 align-items-end">@csrf
        <div class="col-md-4"><input name="contact_note" class="form-control form-control-sm" minlength="10" required placeholder="Return confirmation and contact attempts"></div>
        <div class="col-md-2"><input name="resale_price" type="number" min="1" step=".01" value="{{ max(1, (float)$o->total_price - (float)$o->deposit_amount) }}" class="form-control form-control-sm" required></div>
        <div class="col-md-3"><input name="finished_cake_photo" type="file" accept="image/*" class="form-control form-control-sm" required></div>
        <div class="col-md-3"><input name="safe_until" type="datetime-local" class="form-control form-control-sm"><div class="form-check mt-1"><input class="form-check-input" name="safe_and_generic" value="1" type="checkbox" required id="safeReturnedCake{{ $o->id }}"><label class="form-check-label small" for="safeReturnedCake{{ $o->id }}">Safe and not personalized</label></div></div>
        <div class="col-12"><button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-clock-history me-1"></i>Confirm Return & Start 3 Days</button></div>
      </form>
      @endif
    </div>
    @endif

    {{-- Awaiting Deposit - seller info only, no actions until paid --}}
    @if($o->status === 'Awaiting Deposit')
    <div style="border-top:1px solid var(--gray-100);padding:.9rem 1.25rem;background:linear-gradient(135deg,#fce4ec 0%,#f8fafc 100%);display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
      <div style="display:flex;align-items:flex-start;gap:.75rem;flex:1;min-width:260px">
        <div style="width:2.4rem;height:2.4rem;border-radius:14px;background:#f8bbd0;color:#880E4F;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="bi bi-lock"></i>
        </div>
        <div>
          <div style="font-size:.78rem;font-weight:800;color:#111827;letter-spacing:.04em">WAITING FOR DEPOSIT</div>
          <div style="font-size:.82rem;color:#880E4F;line-height:1.5;font-weight:600">
            Customer needs to pay the 50% deposit ({{ number_format((float)($o->deposit_amount ?? 0),2) }}) via GCash. The order will appear in your queue once payment is confirmed.
          </div>
        </div>
      </div>
      <span style="background:#FCE4EC;color:#880E4F;border:1.5px solid #F48FB1;border-radius:var(--radius-md);padding:.35rem .875rem;font-size:.78rem;font-weight:700;margin-left:auto">
        <i class="bi bi-hourglass-split"></i> Awaiting Customer Payment
      </span>
    </div>
    @endif

    {{-- Rider declined / did not respond: seller must pick another rider --}}
    @if(($o->fulfillment_type ?? 'Pickup') === 'Delivery')
      @php $raAlert = app(\App\Services\RiderAssignmentService::class)->alertInfo((string) $o->id); @endphp
      @if($raAlert)
      <div style="border-top:1px solid var(--gray-100);padding:.75rem 1.25rem">
        @include('partials.rider_assignment_alert', ['alert' => $raAlert, 'orderId' => $o->id])
        @if(($o->status ?? '') === 'Ready for Rider')
        <form action="{{ route('seller.orders.assign_rider', $o->id) }}" method="POST" class="d-flex gap-2 flex-wrap align-items-center mt-2 mb-0">
          @csrf
          <select name="rider_id" class="form-select form-select-sm" style="width:180px" required>
            <option value="">Choose rider</option>
            @foreach($riders as $rider)
              <option value="{{ $rider->id }}">{{ $rider->name }}</option>
            @endforeach
          </select>
          <button type="submit" class="btn btn-primary btn-sm fw-semibold"
                  data-cs-confirm="Assign this rider for Order #{{ $o->id }}?"
                  data-cs-title="Assign Rider"
                  data-cs-ok="Assign Rider">
            <i class="bi bi-bicycle me-1"></i>Assign rider
          </button>
        </form>
        @endif
      </div>
      @endif
    @endif

    {{-- Status Actions (non-final, non-pickup orders) --}}
    @if(!in_array($o->status, ['Awaiting Deposit','Delivered','Picked Up','No Show','Cancelled','Pickup']))
    <div style="border-top:1px solid var(--gray-100);padding:.9rem 1.25rem;background:linear-gradient(135deg,#fff8fb 0%,#f8fafc 100%);display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
      <div style="display:flex;align-items:flex-start;gap:.75rem;flex:1;min-width:260px">
        <div style="width:2.4rem;height:2.4rem;border-radius:14px;background:#fdf2f8;color:#be185d;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="bi bi-stars"></i>
        </div>
        <div>
          <div style="font-size:.78rem;font-weight:800;color:#111827;letter-spacing:.04em">ORDER PROGRESS</div>
          <div style="font-size:.82rem;color:#6b7280;line-height:1.5">
            @if($isRequestBakeOrder)
              This is an out-of-stock cake requested by the customer. Confirm it to send it to Kitchen.
            @elseif($isRegularOrder)
              Stock ready-made orders skip Kitchen. Confirm the order, then prepare handoff for pickup or delivery.
            @else
              Custom orders continue through seller review and kitchen preparation.
            @endif
          </div>
        </div>
      </div>

      @if($isRequestBakeOrder && $o->status === 'Pending')
        <form action="{{ route('seller.orders.status', $o->id) }}" method="POST" class="m-0">
          @csrf
          <input type="hidden" name="status" value="Confirmed">
          <button type="submit" class="btn btn-primary btn-sm fw-semibold"
                  data-cs-confirm="Confirm this request-to-bake order and send it to Kitchen?"
                  data-cs-title="Confirm Kitchen Order"
                  data-cs-ok="Confirm & Send">
            <i class="bi bi-fire me-1"></i>Confirm & send to kitchen
          </button>
        </form>
      @elseif($isRegularOrder && $o->status === 'Pending')
        <form action="{{ route('seller.orders.status', $o->id) }}" method="POST" class="m-0">
          @csrf
          <input type="hidden" name="status" value="Confirmed">
          <button type="submit" class="btn btn-primary btn-sm fw-semibold"
                  data-cs-confirm="Confirm this stock ready-made order?"
                  data-cs-title="Confirm Ready-made Order"
                  data-cs-ok="Confirm Order">
            <i class="bi bi-check2-circle me-1"></i>Confirm order
          </button>
        </form>
      @elseif($isRegularOrder && $o->status === 'Confirmed' && ($o->fulfillment_type ?? 'Pickup') === 'Pickup')
        <form action="{{ route('seller.orders.status', $o->id) }}" method="POST" class="m-0">
          @csrf
          <input type="hidden" name="status" value="Pickup">
          <button type="submit" class="btn btn-success btn-sm fw-semibold"
                  data-cs-confirm="Mark this ready-made order as ready for pickup?"
                  data-cs-title="Ready for Pickup"
                  data-cs-ok="Mark Ready">
            <i class="bi bi-bag-check me-1"></i>Ready for pickup
          </button>
        </form>
      @elseif($isRegularOrder && $o->status === 'Confirmed' && ($o->fulfillment_type ?? 'Pickup') === 'Delivery')
        <form action="{{ route('seller.orders.status', $o->id) }}" method="POST" class="m-0">
          @csrf
          <input type="hidden" name="status" value="Ready for Rider">
          <button type="submit" class="btn btn-info btn-sm fw-semibold text-dark"
                  data-cs-confirm="Mark this ready-made order as ready for rider handoff?"
                  data-cs-title="Ready for Rider"
                  data-cs-ok="Mark Ready">
            <i class="bi bi-box-seam me-1"></i>Ready for rider
          </button>
        </form>
      @elseif(($o->status ?? '') === 'Ready for Rider' && ($o->fulfillment_type ?? 'Pickup') === 'Delivery')
        @php $raLock = app(\App\Services\RiderAssignmentService::class)->lockInfo((string) $o->id); @endphp
        @if($raLock)
          @include('partials.rider_assignment_card', ['lock' => $raLock, 'cancelRoute' => route('seller.orders.cancel_rider_assignment', $o->id)])
        @else
        <form action="{{ route('seller.orders.assign_rider', $o->id) }}" method="POST" class="d-flex gap-2 flex-wrap align-items-center m-0">
          @csrf
          <select name="rider_id" class="form-select form-select-sm" style="width:180px" required>
            <option value="">Choose rider</option>
            @foreach($riders as $rider)
              <option value="{{ $rider->id }}">{{ $rider->name }}</option>
            @endforeach
          </select>
          <button type="submit" class="btn btn-primary btn-sm fw-semibold"
                  data-cs-confirm="Assign this rider for Order #{{ $o->id }}? The order will move out for delivery after the rider accepts."
                  data-cs-title="Assign Rider"
                  data-cs-ok="Assign Rider">
            <i class="bi bi-bicycle me-1"></i>Assign rider
          </button>
          @if($riders->isEmpty())
            <span class="small text-danger fw-semibold">No active riders.</span>
          @endif
        </form>
        @endif
      @elseif($isCustomOrder && $o->status === 'Pending Review')
        <a href="{{ route('seller.custom_orders') }}" class="btn btn-outline-primary btn-sm fw-semibold">
          <i class="bi bi-palette me-1"></i>Review custom order
        </a>
      @elseif($requiresKitchen && in_array($o->status, ['Confirmed','Preparing'], true))
        <a href="{{ route('seller.kitchen') }}" class="btn btn-outline-primary btn-sm fw-semibold">
          <i class="bi bi-fire me-1"></i>Open kitchen
        </a>
      @endif
      {{-- Cancel --}}
      @if(!$sellerCancelAwaiting && !$hasPaidCancelRequest)
      <button type="button" onclick="toggleCancel('cancel-{{ $o->id }}')"
              style="background:#FFEBEE;color:#C62828;border:1.5px solid #FFCDD2;border-radius:var(--radius-md);padding:.35rem .875rem;font-size:.78rem;font-weight:600;cursor:pointer;margin-left:auto">
        <i class="bi bi-x-circle"></i> Cancel
      </button>
      @endif
    </div>

    {{-- Cancel Reason --}}
    <div id="cancel-{{ $o->id }}" style="display:none;border-top:1px solid var(--gray-100);padding:.875rem 1.25rem;background:#FFEBEE">
      <form action="{{ $hasPaidAmount ? route('seller.orders.request_cancellation', $o->id) : route('seller.orders.status', $o->id) }}" method="POST" novalidate>
        @csrf
        @if(!$hasPaidAmount)<input type="hidden" name="status" value="Cancelled">@endif
        <div style="display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap">
          <div style="flex:1;min-width:200px">
            <label class="form-label" style="font-size:.78rem;color:#C62828;font-weight:700">Cancellation Reason <span style="color:var(--danger)">*</span></label>
            <input type="text" class="form-control" name="cancel_reason" required minlength="5"
                   placeholder="e.g. Unavailable ingredients, customer request..."
                   oninvalid="this.setCustomValidity('Please enter a reason (min 5 chars)')"
                   oninput="this.setCustomValidity('')">
          </div>
          @if($hasPaidAmount)
          <div class="small text-primary w-100"><i class="bi bi-info-circle me-1"></i>The customer will receive a 10-minute request. They must accept and provide their GCash refund details before you send the refund.</div>
          @endif
          <button type="submit" style="background:#C62828;color:#fff;border:none;border-radius:var(--radius-md);padding:.5rem 1rem;font-size:.8rem;font-weight:600;cursor:pointer"
                  data-cs-confirm="{{ $hasPaidAmount ? 'Send this cancellation request to the customer? They have 10 minutes to accept or keep the order.' : 'Cancel this unpaid order?' }}"
                  data-cs-title="Confirm Cancellation"
                  data-cs-ok="{{ $hasPaidAmount ? 'Send Request' : 'Cancel Order' }}"
                  data-cs-ok-color="#C62828"
                  data-cs-icon="bi-x-octagon"
                  data-cs-icon-bg="#fee2e2"
                  data-cs-icon-color="#C62828">
            {{ $hasPaidAmount ? 'Request Customer Confirmation' : 'Confirm Cancel' }}
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

<script>
const sellerStatusLabelMap = @json($sellerStatusLabelMap);

function statusBadgeValue(count) {
  const numeric = Number(count || 0);
  if (!Number.isFinite(numeric) || numeric <= 0) return '';
  return numeric > 99 ? '99+' : String(Math.floor(numeric));
}

function setStatusBadgeCount(root, status, count) {
  if (!root) return;
  const display = statusBadgeValue(count);
  root.querySelectorAll('[data-status-badge="' + status + '"]').forEach((badge) => {
    if (!display) {
      badge.hidden = true;
      badge.textContent = '';
      return;
    }
    badge.hidden = false;
    badge.textContent = display;
  });
}

function initSellerStatusFilter() {
  const wrap = document.getElementById('sellerStatusFilter');
  const toggle = document.getElementById('sellerStatusToggle');
  const menu = document.getElementById('sellerStatusMenu');
  if (!wrap || !toggle || !menu) return null;

  const selected = String(wrap.dataset.selected || 'All');
  const label = document.getElementById('sellerStatusLabel');
  if (label) {
    const labelText = sellerStatusLabelMap[selected] || (selected === 'All' ? 'All status' : selected);
    label.textContent = labelText;
    label.title = labelText;
  }

  toggle.addEventListener('click', () => {
    const open = wrap.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) menu.focus();
  });

  menu.querySelectorAll('.status-filter-item[data-status-value]').forEach((item) => {
    item.addEventListener('click', () => {
      const value = item.dataset.statusValue || 'All';
      pgFilter('status', value);
    });
  });

  document.addEventListener('click', (event) => {
    if (!wrap.contains(event.target)) {
      wrap.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      wrap.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
    }
  });

  return wrap;
}

async function refreshSellerStatusCounts() {
  const wrap = document.getElementById('sellerStatusFilter');
  if (!wrap) return;
  const countsUrl = wrap.dataset.countsUrl || '';
  if (!countsUrl) return;

  try {
    const response = await fetch(countsUrl, {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      cache: 'no-store'
    });
    if (!response.ok) return;
    const data = await response.json();
    if (!data?.ok || !data.counts) return;
    Object.entries(data.counts).forEach(([status, count]) => setStatusBadgeCount(wrap, status, count));
  } catch (error) {}
}

let sellerStatusCountTimer = null;
function scheduleSellerStatusCountPoll() {
  if (sellerStatusCountTimer) clearInterval(sellerStatusCountTimer);
  sellerStatusCountTimer = setInterval(() => {
    if (!document.hidden) refreshSellerStatusCounts();
  }, 9000);
}

function pgFilter(key, value) {
  const url = new URL(window.location.href);
  url.searchParams.set(key, value || 'All');
  url.searchParams.set('page', '1');
  window.location.href = url.toString();
}

function pgSearch(value) {
  const url = new URL(window.location.href);
  url.searchParams.set('search', value || '');
  url.searchParams.set('page', '1');
  clearTimeout(window.__sellerOrderSearchTimer);
  window.__sellerOrderSearchTimer = setTimeout(() => {
    window.location.href = url.toString();
  }, 450);
}

function showOrderDetail(id) {
  const src = document.getElementById('order-detail-' + id);
  sellerReceiptOrderContent = null;
  document.getElementById('orderDetailModalTitle').innerHTML = '<i class="bi bi-receipt me-1 text-primary"></i>Order Details';
  document.getElementById('orderDetailBody').innerHTML = src ? src.innerHTML : '<p class="text-muted">No details found.</p>';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('orderDetailModal')).show();
}

let sellerReceiptOrderContent = null;

function showSellerReceipt(button) {
  const url = button?.dataset?.receiptUrl;
  if (!url) return;

  const modalElement = document.getElementById('orderDetailModal');
  const titleElement = document.getElementById('orderDetailModalTitle');
  const bodyElement = document.getElementById('orderDetailBody');
  if (!modalElement || !titleElement || !bodyElement || !window.bootstrap) return;

  if (!sellerReceiptOrderContent) {
    sellerReceiptOrderContent = { body: bodyElement.innerHTML, title: titleElement.innerHTML };
  }
  titleElement.textContent = button.dataset.receiptTitle || 'Payment Receipt';
  bodyElement.innerHTML = '<div class="seller-receipt-loading" role="status"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Loading receipt…</span></div>';

  bootstrap.Modal.getOrCreateInstance(modalElement).show();

  fetch(url, { headers: { Accept: 'text/html' }, credentials: 'same-origin' })
    .then(response => {
      if (!response.ok) throw new Error('Receipt could not be loaded.');
      return response.text();
    })
    .then(html => {
      const documentFragment = new DOMParser().parseFromString(html, 'text/html');
      const receipt = documentFragment.querySelector('.container');
      if (!receipt) throw new Error('Receipt content is unavailable.');
      receipt.querySelectorAll('.no-print').forEach(element => element.remove());
      bodyElement.replaceChildren(receipt);
      receipt.classList.add('seller-receipt-content');
    })
    .catch(() => {
      bodyElement.innerHTML = '<div class="seller-receipt-error"><i class="bi bi-exclamation-circle"></i><strong>Unable to load this receipt.</strong><span>Please close the window and try again.</span></div>';
    });
}

document.addEventListener('click', event => {
  const button = event.target.closest('[data-receipt-url]');
  if (button) showSellerReceipt(button);
  const backButton = event.target.closest('#orderDetailModalBack');
  if (!backButton) return;
  if (!sellerReceiptOrderContent) {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('orderDetailModal')).hide();
    return;
  }
  document.getElementById('orderDetailBody').innerHTML = sellerReceiptOrderContent.body;
  document.getElementById('orderDetailModalTitle').innerHTML = sellerReceiptOrderContent.title;
  sellerReceiptOrderContent = null;
});

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

document.addEventListener('DOMContentLoaded', () => {
  initSellerStatusFilter();
  refreshSellerStatusCounts();
  scheduleSellerStatusCountPoll();
});

document.addEventListener('visibilitychange', () => {
  if (!document.hidden) refreshSellerStatusCounts();
});

(function startGcashOrderRealtime() {
  const watchedOrders = Array.from(document.querySelectorAll('.seller-order-item'))
    .filter(el => (el.dataset.paymentMethod || '').toLowerCase() === 'gcash')
    .filter(el => (el.dataset.paymentStatus || '') !== 'Paid')
    .filter(el => el.dataset.realtimeUrl);

  if (!watchedOrders.length) return;

  let reloading = false;
  const pollOrder = async (el) => {
    if (reloading) return;
    try {
      const res = await fetch(el.dataset.realtimeUrl, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        cache: 'no-store'
      });
      if (!res.ok) return;
      const data = await res.json();
      if (!data.ok) return;

      const currentStatus = el.dataset.status || '';
      const currentPayment = el.dataset.paymentStatus || '';
      const incomingStatus = (data.status || '').toLowerCase();
      const incomingPayment = data.payment_status || '';

      const label = Array.from(document.querySelectorAll('[data-payment-status-label]'))
        .find(node => node.dataset.paymentStatusLabel === String(data.id));
      if (label && incomingPayment && incomingPayment !== currentPayment) {
        label.textContent = incomingPayment;
        label.style.color = incomingPayment === 'Paid' ? 'var(--success,#2E7D32)' : (incomingPayment === 'Partial Payment' ? '#E65100' : 'var(--gray-500)');
      }

      if (incomingPayment === 'Paid' || incomingStatus !== currentStatus || data.can_confirm_pickup) {
        reloading = true;
        window.location.reload();
      }
    } catch (error) {}
  };

  watchedOrders.forEach(el => pollOrder(el));
  const timer = window.setInterval(() => {
    watchedOrders.forEach(el => pollOrder(el));
  }, 6000);
  window.addEventListener('beforeunload', () => window.clearInterval(timer));
})();
</script>
@endsection

@push('modals')
<div class="modal fade" id="orderDetailModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0" style="border-radius:16px">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-bold" id="orderDetailModalTitle"><i class="bi bi-receipt me-1 text-primary"></i>Order Details</h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-2" id="orderDetailBody"></div>
      <div class="modal-footer border-0 pt-0">
        <button class="btn btn-secondary" id="orderDetailModalBack" type="button" style="font-weight:600">
          <i class="bi bi-arrow-left me-1"></i>Back
        </button>
      </div>
    </div>
  </div>
</div>

<style>
  #sellerReceiptModal .modal-dialog {
    width:min(920px, calc(100vw - 24px));
    max-width:min(920px, calc(100vw - 24px));
    margin:12px auto;
    transform:translateY(18px) scale(.96);
    transition:transform .26s cubic-bezier(.2,.8,.2,1);
  }
  #sellerReceiptModal.show .modal-dialog { transform:translateY(0) scale(1); }
  #sellerReceiptModal .modal-content { max-height:calc(100dvh - 24px); border:0; border-radius:18px; overflow:hidden; box-shadow:0 24px 65px rgba(15,23,42,.25); }
  #sellerReceiptModal .modal-body { min-height:190px; overflow:auto; padding:0; background:#f8fafc; }
  #sellerReceiptModal .seller-receipt-content { max-width:860px; padding:20px 14px; }
  .seller-receipt-loading, .seller-receipt-error { min-height:190px; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:.65rem; color:#64748b; font-size:.9rem; }
  .seller-receipt-loading .spinner-border { width:1.8rem; height:1.8rem; color:var(--primary); }
  .seller-receipt-error i { color:#dc2626; font-size:1.7rem; }
  .seller-receipt-error strong { color:#334155; }
  .seller-receipt-error span { font-size:.8rem; }
  @media (max-width:575px) {
    #sellerReceiptModal .modal-dialog { width:calc(100vw - 16px); max-width:calc(100vw - 16px); margin:8px auto; }
    #sellerReceiptModal .modal-content { max-height:calc(100dvh - 16px); border-radius:14px; }
    #sellerReceiptModal .seller-receipt-content { padding:12px 8px; }
  }
</style>

<div class="modal fade" id="sellerReceiptModal" tabindex="-1" aria-labelledby="sellerReceiptModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header border-0 pb-2">
        <h6 class="modal-title fw-bold" id="sellerReceiptModalTitle"><i class="bi bi-receipt me-1 text-primary"></i>Payment Receipt</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="sellerReceiptModalBody"></div>
      <div class="modal-footer border-0 pt-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-arrow-left me-1"></i>Back to Order</button>
      </div>
    </div>
  </div>
</div>
<script>
document.querySelectorAll('[data-seller-cancel-countdown]').forEach(function (card) {
  const target = new Date(card.dataset.sellerCancelCountdown).getTime();
  const label = card.querySelector('.seller-cancel-countdown');
  const tick = function () {
    const seconds = Math.max(0, Math.ceil((target - Date.now()) / 1000));
    if (label) label.textContent = String(Math.floor(seconds / 60)).padStart(2, '0') + ':' + String(seconds % 60).padStart(2, '0');
    if (seconds === 0) window.location.reload();
  };
  tick(); window.setInterval(tick, 1000);
});
</script>
@endpush
