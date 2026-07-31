@extends('layouts.app')
@section('page_title','Dashboard')
@section('content')

{{-- Page Header --}}
<div class="cs-page-header">
  <div>
    <h4 class="cs-page-title"><i class="bi bi-speedometer2 me-2" style="color:var(--primary)"></i>Dashboard</h4>
    <p class="cs-page-sub">
      {{ $shop->shop_name }}
      @if($shop->tier==='verified')
        &nbsp;<span style="background:#fff3e0;color:#E65100;font-size:.7rem;font-weight:700;padding:.2rem .6rem;border-radius:99px"><i class="bi bi-patch-check-fill me-1"></i>Verified</span>
      @endif
    </p>
  </div>
  <div class="cs-page-actions">
    <a href="{{ route('platform.shop', $shop->shop_slug) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-up-right me-1"></i>My Shop</a>
    <a href="{{ route('seller.products') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Product</a>
  </div>
</div>

@if(session('msg'))
  <div class="alert alert-success d-flex align-items-center gap-2 mb-4 cs-fade-up">
    <i class="bi bi-check-circle-fill flex-shrink-0"></i><span>{{ session('msg') }}</span>
  </div>
@endif

@if(session('err'))
  <div class="alert alert-danger d-flex align-items-center gap-2 mb-4 cs-fade-up">
    <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i><span>{{ session('err') }}</span>
  </div>
@endif

@if($shop->tier !== 'verified')
<div class="p-4 mb-4 rounded-3 cs-fade-up" style="background:linear-gradient(135deg,#fff7ed 0%,#fff 62%);border:1.5px solid #fed7aa">
  <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center gap-3">
    <div style="width:52px;height:52px;border-radius:14px;background:#fff3e0;display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <i class="bi bi-patch-check-fill" style="font-size:1.55rem;color:#E65100"></i>
    </div>
    <div class="flex-grow-1">
      <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
        <h5 class="m-0" style="font-size:1rem;font-weight:800;color:var(--gray-900)">Become a Verified Seller</h5>
        <span style="background:#FFF3E0;color:#E65100;font-size:.7rem;font-weight:800;padding:.2rem .55rem;border-radius:99px">
          <i class="bi bi-patch-check-fill me-1"></i>Verified Badge
        </span>
      </div>
      <p class="m-0 mb-2" style="font-size:.84rem;color:var(--gray-600)">Unlock advanced selling tools and build more trust with customers.</p>
      <div class="d-flex flex-wrap gap-2">
        @foreach(['Custom Cake Orders','Custom Options','Add-ons','Unlimited Product Listings','Priority Visibility','Lower Commission Rate'] as $benefit)
          <span style="display:inline-flex;align-items:center;gap:.35rem;background:#fff;border:1px solid #fed7aa;border-radius:99px;padding:.28rem .65rem;font-size:.75rem;color:#7c2d12;font-weight:600">
            <i class="bi bi-check-circle-fill" style="color:#E65100"></i>{{ $benefit }}
          </span>
        @endforeach
      </div>
      <div class="mt-2" style="font-size:.76rem;color:var(--gray-500)">Submit your Business Permit, DTI Certificate, or Mayor's Permit for review.</div>
    </div>
    <a href="{{ route('seller.settings', ['tab' => 'upgrade']) }}" class="btn btn-sm flex-shrink-0" style="background:#E65100;color:#fff;border:none;font-weight:700">
      <i class="bi bi-arrow-up-circle me-1"></i>Upgrade to Verified
    </a>
  </div>
</div>
@endif

@if($pendingCustom > 0)
<div class="d-flex align-items-center gap-3 p-3 mb-4 rounded-3 cs-fade-up" style="background:#fff3e0;border:1.5px solid #ffcc80">
  <div style="width:40px;height:40px;border-radius:10px;background:#E65100;display:flex;align-items:center;justify-content:center;flex-shrink:0">
    <i class="bi bi-palette-fill text-white"></i>
  </div>
  <div class="flex-grow-1 small"><strong>{{ $pendingCustom }} custom order{{ $pendingCustom > 1 ? 's' : '' }}</strong> awaiting your review and pricing.</div>
  <a href="{{ route('seller.custom_orders') }}" class="btn btn-sm flex-shrink-0" style="background:#E65100;color:#fff;border:none">Review</a>
</div>
@endif

{{-- Stat Cards --}}
<div class="row g-3 mb-4 cs-stagger">
  @foreach([
    ['bi-clock',      'Pending',       $stats['pending'],                  '#E65100', '#fff3e0', route('seller.orders')],
    ['bi-check2-circle','Confirmed',   $stats['confirmed'],                '#1565C0', '#e3f2fd', route('seller.orders')],
    ['bi-fire',       'Preparing',     $stats['preparing'],                '#6A1B9A', '#f3e5f5', route('seller.kitchen')],
    ['bi-bag-check',  'Total Orders',  number_format($stats['total']),     '#424242', '#f5f5f5', route('seller.orders')],
    ['bi-cash-stack', 'Revenue',       '₱'.number_format($stats['revenue'],2), '#2E7D32','#e8f5e9','#'],
    ['bi-wallet2',    'Net Earnings',  '₱'.number_format($netRevenue,2),  'var(--primary)', 'var(--primary-bg)', '#'],
    ['bi-bank',       'Available Payout', '&#8369;'.number_format($payoutSummary['available'] ?? 0,2), '#166534', '#ecfdf5', route('seller.payouts')],
  ] as [$icon, $label, $val, $color, $bg, $link])
  <div class="col-6 col-md-4 col-xl">
    <a href="{{ $link }}" style="text-decoration:none;display:block;height:100%">
      <div class="cs-stat-card h-100">
        <div class="cs-stat-icon" style="background:{{ $bg }}">
          <i class="bi {{ $icon }}" style="color:{{ $color }}"></i>
        </div>
        <div class="cs-stat-body">
          <div class="cs-stat-num" style="color:{{ $color }};font-size:clamp(1rem,2.2vw,1.35rem)">{!! $val !!}</div>
          <div class="cs-stat-label">{{ $label }}</div>
        </div>
      </div>
    </a>
  </div>
  @endforeach
</div>

@if(($payoutSummary['clearing'] ?? 0) > 0 || ($payoutSummary['processing'] ?? 0) > 0)
<div class="alert alert-info d-flex align-items-start gap-2 mb-4 cs-fade-up" style="border:1px solid #bfdbfe;background:#eff6ff;color:#1e40af">
  <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
  <div class="small">
    <strong>Payout reminder:</strong>
    &#8369;{{ number_format($payoutSummary['clearing'] ?? 0, 2) }} is still clearing, and
    &#8369;{{ number_format($payoutSummary['processing'] ?? 0, 2) }} is already requested or processing.
    Open Payouts to review details before sending a request.
  </div>
</div>
@endif

<div class="row g-4">

  {{-- Recent Orders --}}
  <div class="col-lg-12">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-receipt me-2" style="color:var(--primary)"></i>Recent Orders</span>
        <a href="{{ route('seller.orders') }}" class="btn btn-outline-primary btn-sm">View All</a>
      </div>
      @forelse($recentOrders as $o)
      <div class="d-flex align-items-center gap-3 px-4 py-3" style="border-bottom:1px solid var(--gray-100);transition:background .15s" onmouseenter="this.style.background='var(--gray-50)'" onmouseleave="this.style.background=''">
        <div style="flex:1;min-width:0">
          <div class="fw-semibold" style="font-size:.85rem">
            #{{ strtoupper(substr($o->track_code,0,8)) }}
            <span class="text-muted ms-1 fw-normal" style="font-size:.78rem">{{ $o->guest_name ?? $o->fullname ?? 'Guest' }}</span>
          </div>
          <div class="text-muted" style="font-size:.75rem">{{ $o->product_name ?? '—' }} · ₱{{ number_format($o->total_price,2) }}</div>
        </div>
        <span class="status-badge status-{{ str_replace(' ','-',$o->status) }}">{{ $o->status }}</span>
      </div>
      @empty
      <div class="cs-empty">
        <i class="bi bi-bag cs-empty-icon"></i>
        <div class="cs-empty-title">No orders yet</div>
        <div class="cs-empty-sub">Orders from customers will appear here.</div>
      </div>
      @endforelse
    </div>
  </div>


</div>
@endsection
