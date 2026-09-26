@extends('layouts.app')

@section('content')
@php
  $tabs = [
    'active' => ['label' => 'Active', 'icon' => 'bi-activity'],
    'offers' => ['label' => 'Offers', 'icon' => 'bi-check2-circle'],
    'closed' => ['label' => 'Closed', 'icon' => 'bi-archive'],
    'all' => ['label' => 'All', 'icon' => 'bi-grid'],
  ];
@endphp

<style>
  .request-page { max-width: 1180px; margin: 0 auto; }
  .request-hero {
    background: linear-gradient(135deg, rgba(236, 37, 99, .12), rgba(255, 166, 77, .14));
    border: 1px solid rgba(236, 37, 99, .14);
    border-radius: 18px;
    padding: 1.1rem;
  }
  .request-tabs { display:flex; gap:.55rem; overflow-x:auto; padding:.2rem .05rem .45rem; scrollbar-width: thin; }
  .request-tab {
    display:inline-flex; align-items:center; gap:.45rem; white-space:nowrap;
    padding:.65rem .9rem; border-radius:999px; text-decoration:none; font-weight:700;
    color:#6b7280; border:1px solid #e5e7eb; background:#fff;
    box-shadow:0 8px 20px rgba(15,23,42,.05);
  }
  .request-tab.active { color:#fff; background:var(--primary); border-color:var(--primary); }
  .request-tab .count { font-size:.75rem; padding:.12rem .45rem; border-radius:999px; background:rgba(15,23,42,.08); }
  .request-tab.active .count { background:rgba(255,255,255,.22); }
  .request-card {
    background:#fff; border:1px solid #edf0f4; border-radius:16px; overflow:hidden;
    box-shadow:0 10px 26px rgba(15,23,42,.07); transition:transform .18s ease, box-shadow .18s ease;
  }
  .request-card:hover { transform:translateY(-2px); box-shadow:0 16px 34px rgba(15,23,42,.1); }
  .request-img { width:92px; height:92px; object-fit:cover; border-radius:14px; background:#f8fafc; flex:0 0 auto; }
  .request-status { display:inline-flex; align-items:center; gap:.35rem; border-radius:999px; padding:.32rem .62rem; font-size:.76rem; font-weight:800; }
  .request-status.success { color:#047857; background:#d1fae5; }
  .request-status.primary { color:#1d4ed8; background:#dbeafe; }
  .request-status.warning { color:#92400e; background:#fef3c7; }
  .request-status.danger { color:#b91c1c; background:#fee2e2; }
  .request-status.muted { color:#64748b; background:#f1f5f9; }
  .request-status.pending { color:#9f1239; background:#ffe4ec; }
  .request-chip { display:inline-flex; align-items:center; gap:.35rem; border-radius:999px; padding:.28rem .58rem; background:#f8fafc; color:#64748b; font-size:.78rem; font-weight:700; border:1px solid #e5e7eb; }
  .request-chip.rush { color:#be123c; background:#fff1f2; border-color:#fecdd3; }
  .request-note { background:#f8fafc; border:1px solid #edf0f4; border-radius:12px; padding:.7rem .8rem; color:#64748b; font-size:.86rem; }
  .request-price { color:var(--primary); font-size:1.25rem; font-weight:900; }
  .request-actions { display:flex; gap:.55rem; flex-wrap:wrap; justify-content:flex-end; }
  .request-empty { border:1px dashed #e5e7eb; border-radius:18px; padding:3rem 1rem; background:#fff; }
  @media (max-width: 575.98px) {
    .request-hero { border-radius:14px; padding:1rem; }
    .request-card { border-radius:14px; }
    .request-img { width:76px; height:76px; border-radius:12px; }
    .request-actions { justify-content:stretch; }
    .request-actions .btn { width:100%; }
  }
</style>

<div class="container-fluid py-4 request-page">
  <div class="request-hero mb-3">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
      <div>
        <div class="text-uppercase fw-bold small mb-1" style="color:var(--primary);letter-spacing:.04em">My Requests</div>
        <h4 class="fw-bold mb-1">Kitchen requests and seller offers</h4>
        <p class="text-muted mb-0 small">Track request-to-bake items here, including rush kitchen requests, seller accepted offers, declined requests, and requests converted to orders.</p>
      </div>
      @if(($counts['offers'] ?? 0) > 0)
        <a href="{{ route('customer.order_requests.index', ['filter' => 'offers']) }}" class="btn btn-primary fw-semibold">
          <i class="bi bi-check2-circle me-1"></i> Review {{ $counts['offers'] }} offer{{ ($counts['offers'] ?? 0) > 1 ? 's' : '' }}
        </a>
      @endif
    </div>
  </div>

  <div class="request-tabs mb-3">
    @foreach($tabs as $key => $tab)
      <a href="{{ route('customer.order_requests.index', ['filter' => $key]) }}" class="request-tab {{ $filter === $key ? 'active' : '' }}">
        <i class="bi {{ $tab['icon'] }}"></i>{{ $tab['label'] }} <span class="count">{{ $counts[$key] ?? 0 }}</span>
      </a>
    @endforeach
  </div>

  @forelse($requests as $requestItem)
    @php
      $meta = $requestItem->status_meta;
      $statusTone = $meta['tone'] ?? 'pending';
      $acceptedPrice = $requestItem->accepted_price ?? $requestItem->seller_price ?? null;
      $requestedPrice = $requestItem->requested_price ?? $requestItem->base_price ?? null;
    @endphp
    <div class="request-card mb-3">
      <div class="p-3 p-sm-4">
        <div class="d-flex gap-3 align-items-start">
          <img class="request-img" src="{{ $requestItem->display_image ?: 'https://placehold.co/160x160/f8fafc/94a3b8?text=Cake' }}" alt="{{ $requestItem->display_name }}" onerror="this.src='https://placehold.co/160x160/f8fafc/94a3b8?text=Cake'">
          <div class="flex-grow-1 min-width-0">
            <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
              <div>
                <h6 class="fw-bold mb-1">{{ $requestItem->display_name }}</h6>
                <div class="text-muted small">
                  <i class="bi bi-shop me-1"></i>{{ $requestItem->shop_name ?? 'Cake shop' }}
                  <span class="mx-1">.</span>
                  Requested {{ \Carbon\Carbon::parse($requestItem->created_at)->format('M d, Y') }}
                </div>
              </div>
              <span class="request-status {{ $statusTone }}">
                <i class="bi {{ in_array($requestItem->status, ['accepted','customer_accepted','converted'], true) ? 'bi-check-circle-fill' : ($statusTone === 'danger' ? 'bi-x-circle-fill' : 'bi-clock-fill') }}"></i>
                {{ $meta['label'] ?? 'Waiting for seller' }}
              </span>
            </div>

            <div class="d-flex gap-2 flex-wrap my-3">
              @if($requestItem->is_rush)
                <span class="request-chip rush"><i class="bi bi-lightning-charge-fill"></i>Rush request</span>
              @endif
              @if(($requestItem->request_reason ?? '') === 'size_out_of_stock')
                <span class="request-chip"><i class="bi bi-rulers"></i>Size request</span>
              @elseif(($requestItem->request_reason ?? '') === 'out_of_stock')
                <span class="request-chip"><i class="bi bi-box-seam"></i>Request to bake</span>
              @endif
              @if($requestItem->product_flavor)
                <span class="request-chip"><i class="bi bi-droplet"></i>{{ $requestItem->product_flavor }}</span>
              @endif
              @if($requestItem->selected_size ?? $requestItem->size_label ?? $requestItem->size ?? null)
                <span class="request-chip"><i class="bi bi-rulers"></i>{{ $requestItem->selected_size ?? $requestItem->size_label ?? $requestItem->size }}</span>
              @endif
              @if($requestItem->preferred_label)
                <span class="request-chip"><i class="bi bi-calendar-event"></i>{{ $requestItem->preferred_label }}</span>
              @endif
            </div>

            <div class="row g-2 align-items-stretch">
              <div class="col-md-7">
                <div class="request-note h-100">
                  <div class="fw-semibold text-dark mb-1">{{ $meta['hint'] ?? 'Request status updated.' }}</div>
                  @if(!empty($requestItem->seller_response))
                    <div><span class="fw-semibold">Seller note:</span> {{ $requestItem->seller_response }}</div>
                  @elseif(!empty($requestItem->customer_note))
                    <div><span class="fw-semibold">Your note:</span> {{ $requestItem->customer_note }}</div>
                  @else
                    <div>No extra note yet.</div>
                  @endif
                  @if(!empty($requestItem->alternative_product_name))
                    <div class="mt-1"><span class="fw-semibold">Alternative:</span> {{ $requestItem->alternative_product_name }}</div>
                  @endif
                </div>
              </div>
              <div class="col-md-5">
                <div class="request-note h-100 bg-white">
                  @if($acceptedPrice)
                    <div class="text-muted small fw-semibold">Seller offer</div>
                    <div class="request-price">PHP {{ number_format((float) $acceptedPrice, 2) }}</div>
                    @if($requestItem->accepted_label)
                      <div class="small text-muted"><i class="bi bi-calendar-check me-1"></i>{{ $requestItem->accepted_label }}</div>
                    @endif
                  @elseif($requestedPrice)
                    <div class="text-muted small fw-semibold">Requested price</div>
                    <div class="request-price">PHP {{ number_format((float) $requestedPrice, 2) }}</div>
                  @else
                    <div class="text-muted small">Seller price will appear here after review.</div>
                  @endif
                </div>
              </div>
            </div>

            <div class="request-actions mt-3">
              @if(in_array($requestItem->status, ['accepted', 'customer_accepted'], true))
                <a href="{{ route('customer.order_requests.show', $requestItem->id) }}" class="btn btn-primary fw-semibold">
                  <i class="bi bi-box-arrow-up-right me-1"></i>{{ $requestItem->status === 'customer_accepted' ? 'Continue Checkout' : 'View Offer' }}
                </a>
              @elseif($requestItem->status === 'converted' && $requestItem->converted_track_code)
                <a href="{{ route('track.order', $requestItem->converted_track_code) }}" class="btn btn-outline-primary fw-semibold">
                  <i class="bi bi-truck me-1"></i>Track Order
                </a>
              @elseif($requestItem->status === 'pending')
                <button class="btn btn-light fw-semibold" disabled><i class="bi bi-hourglass-split me-1"></i>Waiting for seller</button>
              @else
                <a href="{{ route('customer.catalog') }}" class="btn btn-outline-primary fw-semibold">
                  <i class="bi bi-shop me-1"></i>Browse Cakes
                </a>
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>
  @empty
    <div class="request-empty text-center">
      <i class="bi bi-send" style="font-size:2.5rem;color:#cbd5e1"></i>
      <h6 class="fw-bold mt-3 mb-1">No requests here yet</h6>
      <p class="text-muted small mb-3">Accepted seller offers, rush kitchen requests, and pending request-to-bake items will show in this account page.</p>
      <a href="{{ route('customer.catalog') }}" class="btn btn-primary fw-semibold"><i class="bi bi-shop me-1"></i>Browse Cakes</a>
    </div>
  @endforelse

  @if(method_exists($requests, 'links'))
    <div class="mt-3">{{ $requests->links() }}</div>
  @endif
</div>
@endsection
