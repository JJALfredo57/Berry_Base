@extends('layouts.app')
@section('content')

{{-- Page Header --}}
<div class="cs-page-header">
  <div>
    <h4 class="cs-page-title"><i class="bi bi-speedometer2 me-2" style="color:var(--primary)"></i>Platform Dashboard</h4>
    <p class="cs-page-sub">{{ $platform->platform_name ?? 'Cake Shop Platform' }} — Super Admin Overview</p>
  </div>
  <div class="cs-page-actions">
    <a href="{{ route('superadmin.commissions') }}" class="btn btn-outline-success btn-sm"><i class="bi bi-graph-up-arrow me-1"></i>Commission Graph</a>
    <a href="{{ route('superadmin.sellers') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-shop me-1"></i>Sellers</a>
    <a href="{{ route('superadmin.settings') }}" class="btn btn-primary btn-sm"><i class="bi bi-sliders me-1"></i>Settings</a>
  </div>
</div>

@if(session('msg'))
  <div class="alert alert-success d-flex align-items-center gap-2 mb-4 cs-fade-up">
    <i class="bi bi-check-circle-fill flex-shrink-0"></i><span>{{ session('msg') }}</span>
  </div>
@endif

{{-- Stat Cards --}}
<div class="row g-3 mb-4 cs-stagger">
  @foreach([
    ['bi-shop',         'Approved Shops',        $stats['total_shops'],                     'var(--primary)',   'var(--primary-bg)', null],
    ['bi-hourglass',    'Pending Applications',   $stats['pending_apps'],                    '#E65100',          '#fff3e0', null],
    ['bi-bag-check',    'Total Orders',           number_format($stats['total_orders']),     '#1565C0',          '#e3f2fd', null],
    ['bi-people',       'Customers',              number_format($stats['total_customers']),  '#2E7D32',          '#e8f5e9', null],
    ['bi-cash-stack',   'Total Commission',       '₱'.number_format($stats['total_commission'],2), '#6A1B9A',      '#f3e5f5', route('superadmin.commissions')],
    ['bi-calendar2-check','This Month Commission','₱'.number_format($commissionMonth,2),      '#00695C',         '#e0f2f1', route('superadmin.commissions')],
  ] as [$icon, $label, $val, $color, $bg, $href])
  <div class="col-6 col-md-4 col-lg-2">
    @if($href)
    <a href="{{ $href }}" class="cs-stat-card h-100 text-decoration-none d-block" style="cursor:pointer">
    @else
    <div class="cs-stat-card h-100">
    @endif
      <div class="cs-stat-icon" style="background:{{ $bg }}">
        <i class="bi {{ $icon }}" style="color:{{ $color }}"></i>
      </div>
      <div class="cs-stat-body">
        <div class="cs-stat-num" style="color:{{ $color }};font-size:clamp(1.1rem,2.5vw,1.4rem)">{{ $val }}</div>
        <div class="cs-stat-label">{{ $label }}</div>
      </div>
    @if($href)
    </a>
    @else
    </div>
    @endif
  </div>
  @endforeach
</div>

<div class="row g-4">

  {{-- Pending Applications --}}
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span>
          <i class="bi bi-hourglass-split me-2" style="color:#E65100"></i>Pending Applications
          @if($stats['pending_apps'] > 0)
            <span class="ms-1" style="background:#E65100;color:#fff;font-size:.68rem;font-weight:700;padding:.15rem .55rem;border-radius:99px">{{ $stats['pending_apps'] }}</span>
          @endif
        </span>
        <a href="{{ route('superadmin.sellers') }}" class="btn btn-sm btn-outline-primary">View All</a>
      </div>

      @if($pendingApps->count() > 0)
        <div>
          @foreach($pendingApps as $app)
          <div class="d-flex align-items-center gap-3 px-4 py-3" style="border-bottom:1px solid var(--gray-100);transition:background .15s" onmouseenter="this.style.background='var(--gray-50)'" onmouseleave="this.style.background=''">
            <div style="width:44px;height:44px;border-radius:12px;background:var(--primary-bg);display:flex;align-items:center;justify-content:center;font-size:1rem;font-weight:700;color:var(--primary);flex-shrink:0">
              {{ strtoupper(substr($app->shop_name,0,1)) }}
            </div>
            <div class="flex-grow-1 min-width-0">
              <div class="fw-semibold" style="font-size:.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                {{ $app->shop_name }}
                @if($app->tier === 'verified')
                  <span class="ms-1" style="background:#fff3e0;color:#E65100;font-size:.65rem;font-weight:700;padding:.15rem .4rem;border-radius:99px">Verified</span>
                @endif
              </div>
              <div class="text-muted" style="font-size:.75rem">{{ $app->fullname }} · {{ $app->city }}</div>
              <div style="font-size:.72rem;color:var(--gray-400)">{{ \Carbon\Carbon::parse($app->created_at)->diffForHumans() }}</div>
            </div>
            <div class="d-flex gap-2 flex-shrink-0">
              <form action="{{ route('superadmin.sellers.approve',$app->id) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm" style="background:#ecfdf5;color:#059669;border:1.5px solid #a7f3d0;font-size:.78rem"
                        data-cs-confirm="Approve {{ addslashes($app->shop_name) }}?" data-cs-title="Approve Shop" data-cs-icon="bi-check-circle" data-cs-icon-bg="#ecfdf5" data-cs-icon-color="#059669" data-cs-ok="Approve" data-cs-ok-color="#059669">
                  <i class="bi bi-check-lg"></i> Approve
                </button>
              </form>
              <a href="{{ route('superadmin.sellers') }}" class="btn btn-sm btn-outline-secondary" style="font-size:.78rem">
                Review
              </a>
            </div>
          </div>
          @endforeach
        </div>
      @else
        <div class="cs-empty">
          <i class="bi bi-check-circle-fill cs-empty-icon" style="color:var(--success);opacity:.6"></i>
          <div class="cs-empty-title">No pending applications</div>
          <div class="cs-empty-sub">All seller applications have been reviewed.</div>
        </div>
      @endif
    </div>
  </div>

  {{-- Recently Approved Shops --}}
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">
        <i class="bi bi-shop me-2" style="color:var(--primary)"></i>Recently Approved Shops
      </div>
      @foreach($recentShops as $shop)
      <div class="d-flex align-items-center gap-3 px-4 py-3" style="border-bottom:1px solid var(--gray-100);transition:background .15s" onmouseenter="this.style.background='var(--gray-50)'" onmouseleave="this.style.background=''">
        @if($shop->shop_logo)
          <img src="{{ $shop->shop_logo }}" style="width:40px;height:40px;border-radius:10px;object-fit:cover;flex-shrink:0" alt="">
        @else
          <div style="width:40px;height:40px;border-radius:10px;background:var(--primary-bg);display:flex;align-items:center;justify-content:center;font-size:.9rem;font-weight:700;color:var(--primary);flex-shrink:0">
            {{ strtoupper(substr($shop->shop_name,0,1)) }}
          </div>
        @endif
        <div class="flex-grow-1" style="min-width:0">
          <div class="fw-semibold" style="font-size:.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            {{ $shop->shop_name }}
            @if($shop->tier === 'verified')
              <i class="bi bi-patch-check-fill ms-1" style="color:#E65100;font-size:.75rem"></i>
            @endif
          </div>
          <div class="text-muted" style="font-size:.73rem">{{ $shop->city }}</div>
        </div>
        <a href="{{ route('platform.shop', $shop->shop_slug) }}" target="_blank"
           class="btn btn-sm" style="background:var(--primary-bg);color:var(--primary);border:none;font-size:.75rem;flex-shrink:0">
          View <i class="bi bi-box-arrow-up-right ms-1" style="font-size:.65rem"></i>
        </a>
      </div>
      @endforeach
      @if($recentShops->isEmpty())
        <div class="cs-empty">
          <i class="bi bi-shop cs-empty-icon"></i>
          <div class="cs-empty-title">No shops yet</div>
        </div>
      @endif
    </div>
  </div>

</div>
@endsection
