@extends('layouts.app')
@section('page_title', 'Customer Reviews')
@section('content')
<style>
.rev-tab{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem 1.1rem;border-radius:99px;font-size:.82rem;font-weight:600;text-decoration:none;border:1.5px solid transparent;transition:all .18s}
.rev-tab.active{background:var(--primary);color:#fff;border-color:var(--primary)}
.rev-tab:not(.active){background:#fff;color:var(--gray-600);border-color:var(--gray-200)}
.rev-tab:not(.active):hover{border-color:var(--primary);color:var(--primary)}
.rev-card{background:#fff;border-radius:14px;border:1.5px solid var(--gray-100);padding:1.25rem 1.4rem;margin-bottom:.85rem;transition:box-shadow .15s}
.rev-card:hover{box-shadow:0 4px 18px rgba(0,0,0,.07)}
.rev-avatar{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1rem;flex-shrink:0;color:#fff}
.star-filled{color:#f59e0b}
.star-empty{color:#e5e7eb}
.badge-custom{background:#fdf4ff;color:#7e22ce;border:1px solid #e9d5ff;font-size:.72rem;font-weight:700;padding:3px 9px;border-radius:99px}
.badge-regular{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;font-size:.72rem;font-weight:700;padding:3px 9px;border-radius:99px}
.rev-note{background:#fafafa;border-left:3px solid #e9d5ff;border-radius:0 8px 8px 0;padding:.5rem .75rem;font-size:.78rem;color:#6b7280;margin-top:.5rem}
.rev-photo{width:72px;height:72px;object-fit:cover;border-radius:10px;border:2px solid #f3f4f6;cursor:pointer;transition:transform .15s}
.rev-photo:hover{transform:scale(1.05)}
</style>

<div style="max-width:820px;margin:0 auto">

  {{-- ── Header ───────────────────────────────────────── --}}
  <div style="margin-bottom:1.5rem">
    <h1 style="font-size:1.35rem;font-weight:800;margin:0 0 .2rem">Customer Reviews</h1>
    <p style="font-size:.85rem;color:var(--gray-500);margin:0">{{ $shop->shop_name }}</p>
  </div>

  @if(session('msg'))<div class="alert alert-success border-0 py-2 mb-3"><i class="bi bi-check-circle-fill me-2"></i>{{ session('msg') }}</div>@endif

  {{-- ── Rating Summary ─────────────────────────────────── --}}
  <div style="background:#fff;border-radius:18px;padding:1.5rem 1.75rem;border:1.5px solid var(--gray-100);margin-bottom:1.5rem;display:flex;align-items:center;gap:2rem;flex-wrap:wrap">
    <div style="text-align:center;min-width:90px">
      <div style="font-size:3.2rem;font-weight:900;color:var(--primary);line-height:1">
        {{ $totalReviews > 0 ? number_format($avgRating, 1) : '—' }}
      </div>
      <div style="font-size:1rem;margin:.3rem 0">
        @for($i=1;$i<=5;$i++)
          <i class="bi bi-star{{ $i <= round($avgRating) ? '-fill star-filled' : ' star-empty' }}"></i>
        @endfor
      </div>
      <div style="font-size:.78rem;color:var(--gray-500)">{{ $totalReviews }} review{{ $totalReviews != 1 ? 's' : '' }}</div>
    </div>
    <div style="flex:1;min-width:180px">
      @foreach([5,4,3,2,1] as $star)
        @php $cnt = $starCounts[$star] ?? 0; $pct = $totalReviews > 0 ? ($cnt / $totalReviews) * 100 : 0; @endphp
        <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.4rem">
          <span style="font-size:.76rem;color:var(--gray-600);width:10px;text-align:right">{{ $star }}</span>
          <i class="bi bi-star-fill" style="color:#f59e0b;font-size:.68rem"></i>
          <div style="flex:1;height:7px;background:var(--gray-100);border-radius:99px;overflow:hidden">
            <div style="height:100%;width:{{ $pct }}%;background:linear-gradient(90deg,#f59e0b,#fbbf24);border-radius:99px;transition:width .4s"></div>
          </div>
          <span style="font-size:.75rem;color:var(--gray-400);width:22px;text-align:right">{{ $cnt }}</span>
        </div>
      @endforeach
    </div>
    <div style="display:flex;flex-direction:column;gap:.5rem;min-width:140px">
      <div style="background:#fdf4ff;border-radius:12px;padding:.65rem 1rem;text-align:center">
        <div style="font-size:1.5rem;font-weight:800;color:#7e22ce">{{ $customCount }}</div>
        <div style="font-size:.72rem;color:#7e22ce;font-weight:600">Custom Order Reviews</div>
      </div>
      <div style="background:#eff6ff;border-radius:12px;padding:.65rem 1rem;text-align:center">
        <div style="font-size:1.5rem;font-weight:800;color:#1d4ed8">{{ $regularCount }}</div>
        <div style="font-size:.72rem;color:#1d4ed8;font-weight:600">Regular Order Reviews</div>
      </div>
    </div>
  </div>

  {{-- ── Filter Tabs ──────────────────────────────────── --}}
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.25rem">
    <a href="{{ request()->fullUrlWithQuery(['type' => 'all']) }}"
       class="rev-tab {{ $filter === 'all' ? 'active' : '' }}">
      <i class="bi bi-grid-3x3-gap"></i> All
      <span style="background:rgba(255,255,255,.3);border-radius:99px;padding:0 7px;font-size:.7rem">{{ $totalReviews }}</span>
    </a>
    <a href="{{ request()->fullUrlWithQuery(['type' => 'custom']) }}"
       class="rev-tab {{ $filter === 'custom' ? 'active' : '' }}"
       style="{{ $filter === 'custom' ? 'background:#7e22ce;border-color:#7e22ce' : '' }}">
      <i class="bi bi-magic"></i> Custom Orders
      <span style="background:rgba(255,255,255,.25);border-radius:99px;padding:0 7px;font-size:.7rem">{{ $customCount }}</span>
    </a>
    <a href="{{ request()->fullUrlWithQuery(['type' => 'regular']) }}"
       class="rev-tab {{ $filter === 'regular' ? 'active' : '' }}"
       style="{{ $filter === 'regular' ? 'background:#1d4ed8;border-color:#1d4ed8' : '' }}">
      <i class="bi bi-bag-heart"></i> Regular Orders
      <span style="background:rgba(255,255,255,.25);border-radius:99px;padding:0 7px;font-size:.7rem">{{ $regularCount }}</span>
    </a>
  </div>

  {{-- ── Review List ──────────────────────────────────── --}}
  @forelse($reviews as $rev)
  @php
    $isCustom   = (bool) $rev->is_custom;
    $name       = $rev->reviewer_name ?? 'Customer';
    $initial    = strtoupper(substr($name, 0, 1));
    $colors     = ['#7c3aed','#db2777','#0284c7','#059669','#d97706','#dc2626'];
    $avatarBg   = $colors[crc32($name) % count($colors)];
    $cakeLabel  = $isCustom ? ($rev->cake_name ?: 'Custom Cake') : ($rev->product_name ?: 'Cake');
    // Extract first meaningful line from custom_note as subtitle
    $noteSnippet = null;
    if ($isCustom && $rev->custom_note) {
      $parts = explode(' | ', $rev->custom_note);
      $noteSnippet = implode(' · ', array_slice($parts, 0, 3));
    }
  @endphp
  <div class="rev-card">
    <div style="display:flex;align-items:flex-start;gap:.9rem">

      {{-- Avatar --}}
      @if(!empty($rev->profile_photo))
        <img src="{{ asset('storage/' . ltrim($rev->profile_photo, '/storage/')) }}"
             alt="{{ $name }}"
             style="width:44px;height:44px;border-radius:50%;object-fit:cover;flex-shrink:0">
      @else
        <div class="rev-avatar" style="background:{{ $avatarBg }}">{{ $initial }}</div>
      @endif

      <div style="flex:1;min-width:0">

        {{-- Top row: name + badge + date --}}
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.4rem;margin-bottom:.35rem">
          <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
            <span style="font-weight:700;font-size:.92rem">{{ $name }}</span>
            @if($isCustom)
              <span class="badge-custom"><i class="bi bi-magic me-1"></i>Custom Cake</span>
            @else
              <span class="badge-regular"><i class="bi bi-bag-heart me-1"></i>Regular Order</span>
            @endif
            @if($rev->fulfillment_type)
              <span style="font-size:.7rem;color:var(--gray-400)">
                <i class="bi bi-{{ $rev->fulfillment_type === 'Delivery' ? 'truck' : 'bag-check' }} me-1"></i>{{ $rev->fulfillment_type }}
              </span>
            @endif
          </div>
          <span style="font-size:.74rem;color:var(--gray-400);white-space:nowrap">
            {{ \Carbon\Carbon::parse($rev->created_at)->format('M j, Y') }}
          </span>
        </div>

        {{-- What they ordered --}}
        <div style="font-size:.78rem;color:var(--gray-500);margin-bottom:.4rem">
          <i class="bi bi-cake2 me-1" style="color:{{ $isCustom ? '#7e22ce' : '#1d4ed8' }}"></i>{{ $cakeLabel }}
          @if($noteSnippet)
            <span style="color:var(--gray-400)"> · {{ $noteSnippet }}</span>
          @endif
        </div>

        {{-- Stars --}}
        <div style="display:flex;align-items:center;gap:.4rem;margin-bottom:.45rem">
          <div>
            @for($i=1;$i<=5;$i++)
              <i class="bi bi-star{{ $i <= $rev->rating ? '-fill star-filled' : ' star-empty' }}"
                 style="font-size:.9rem"></i>
            @endfor
          </div>
          <span style="font-size:.78rem;font-weight:700;color:#f59e0b">{{ $rev->rating }}/5</span>
          @if($rev->rider_rating)
            <span style="font-size:.72rem;color:var(--gray-400);margin-left:.5rem">
              <i class="bi bi-bicycle me-1" style="color:var(--primary)"></i>Rider:
              @for($i=1;$i<=5;$i++)
                <i class="bi bi-star{{ $i <= $rev->rider_rating ? '-fill' : '' }}"
                   style="font-size:.7rem;color:{{ $i <= $rev->rider_rating ? '#f59e0b' : '#e5e7eb' }}"></i>
              @endfor
            </span>
          @endif
        </div>

        {{-- Review text --}}
        @if($rev->review)
          <p style="font-size:.875rem;color:var(--gray-700);margin:0 0 .4rem;line-height:1.65;font-style:italic">
            "{{ $rev->review }}"
          </p>
        @endif

        {{-- Custom order note snippet --}}
        @if($isCustom && $noteSnippet)
          <div class="rev-note">
            <i class="bi bi-clipboard-check me-1" style="color:#7e22ce"></i>{{ $noteSnippet }}
          </div>
        @endif

        {{-- Review photo --}}
        @if($rev->image_path)
          <div style="margin-top:.6rem">
            <img src="{{ asset('storage/' . ltrim($rev->image_path, '/storage/')) }}"
                 alt="Review photo"
                 class="rev-photo"
                 onclick="openRevPhoto(this.src)">
          </div>
        @endif

      </div>
    </div>
  </div>
  @empty
  <div style="text-align:center;padding:5rem 1rem;background:#fff;border-radius:18px;border:1.5px solid var(--gray-100)">
    <i class="bi bi-star" style="font-size:2.8rem;color:var(--gray-200);display:block;margin-bottom:1rem"></i>
    <h3 style="font-size:1rem;font-weight:700;margin:0 0 .4rem">
      @if($filter === 'custom') No custom order reviews yet
      @elseif($filter === 'regular') No regular order reviews yet
      @else No reviews yet
      @endif
    </h3>
    <p style="font-size:.875rem;color:var(--gray-400);margin:0">
      @if($filter !== 'all') <a href="{{ request()->fullUrlWithQuery(['type' => 'all']) }}" style="color:var(--primary)">View all reviews</a> @else Reviews will appear here once customers complete their orders. @endif
    </p>
  </div>
  @endforelse

  {{-- ── Pagination ──────────────────────────────────── --}}
  @if($reviews->hasPages())
  <div style="margin-top:1rem">
    {{ $reviews->links() }}
  </div>
  @endif

</div>

{{-- ── Photo Lightbox ──────────────────────────────── --}}
<div id="revPhotoOverlay" onclick="closeRevPhoto()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.82);z-index:9999;align-items:center;justify-content:center;cursor:zoom-out">
  <img id="revPhotoImg" src="" alt=""
       style="max-width:90vw;max-height:90vh;border-radius:12px;object-fit:contain;box-shadow:0 20px 60px rgba(0,0,0,.5)">
</div>

<script>
function openRevPhoto(src){
  const o = document.getElementById('revPhotoOverlay');
  document.getElementById('revPhotoImg').src = src;
  o.style.display = 'flex';
}
function closeRevPhoto(){
  document.getElementById('revPhotoOverlay').style.display = 'none';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeRevPhoto(); });
</script>
@endsection
