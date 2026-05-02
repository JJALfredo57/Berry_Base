@extends('layouts.app')
@section('content')
<div class="container-fluid py-4" style="padding-left:clamp(12px,3vw,32px);padding-right:clamp(12px,3vw,32px)">
<style>
.catalog-grid{
  display:grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap:1.5rem;
}
.best-seller-grid{
  display:grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap:1rem;
}
@media(max-width:600px){
  .catalog-grid{ grid-template-columns: 1fr; gap:.75rem; }
  .best-seller-grid{ grid-template-columns:1fr; }
  .catalog-img-wrap{ height:200px !important; }
}
.catalog-item{ transition: all .3s ease; }
@media (hover: hover) {
  .catalog-card:hover {
    transform: translateY(-8px) scale(1.02) !important;
    box-shadow: 0 20px 40px rgba(233,30,99,.18) !important;
  }
}
/* Prevent customer-wrap animation from creating a CSS stacking context
   that traps Bootstrap modals (position:fixed) inside the wrapper */
.customer-wrap { animation: none !important; transform: none !important; }
</style>

  <div class="text-center mb-5">
    <h3 class="fw-bold" style="color:var(--primary)">
      <i class="bi bi-cake2 me-2"></i>{{ $settings['tagline'] ?? 'Our Cakes' }}
    </h3>
    <p class="text-muted">Choose your cake and place your order</p>
  </div>

  @if(session('msg'))
    <div class="alert alert-success border-0"><i class="bi bi-check-circle me-2"></i>{{ session('msg') }}</div>
  @endif

  @if(($bestSellers ?? collect())->count() > 0)
  <div class="mb-5">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <div>
        <h5 class="fw-bold mb-1" style="color:#9d174d"><i class="bi bi-fire me-2"></i>Best Seller Cakes</h5>
        <p class="text-muted small mb-0">Most ordered favorites from customers</p>
      </div>
      <span class="badge rounded-pill" style="background:#fff1f2;color:#be123c;font-size:.78rem">Live ranking from completed and active orders</span>
    </div>
    <div class="best-seller-grid">
      @foreach($bestSellers as $p)
      @php
        $avgRating   = isset($reviewsMap[$p->id]) ? $reviewsMap[$p->id]->avg_rating : null ?? null;
        $reviewCount = isset($reviewsMap[$p->id]) ? $reviewsMap[$p->id]->total : 0 ?? 0;
      @endphp
      <button type="button" class="text-start border-0 p-0" data-bs-toggle="modal" data-bs-target="#detailModal{{ $p->id }}"
              style="background:#fff;border-radius:1.15rem;overflow:hidden;box-shadow:0 12px 30px rgba(15,23,42,.08)">
        <div class="position-relative" style="height:180px">
          <img src="{{ $p->image_path }}" alt="{{ $p->name }}" style="width:100%;height:100%;object-fit:cover"
               onerror="this.src='https://placehold.co/480x320/fce4ec/e91e63?text=Cake'">
          <span class="position-absolute top-0 start-0 m-2 badge" style="background:#be123c;color:#fff">
            <i class="bi bi-trophy-fill me-1"></i>Top {{ $loop->iteration }}
          </span>
        </div>
        <div class="p-3">
          <div class="fw-bold mb-1">{{ $p->name }}</div>
          <div class="small text-muted mb-2">{{ $p->shop_name ?? 'Cake Shop' }}</div>
          <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
            <div class="small" style="color:#f59e0b">
              <i class="bi bi-star-fill me-1"></i>{{ $avgRating ? number_format($avgRating, 1) : 'New' }}
              <span class="text-muted ms-1">({{ $reviewCount }} review{{ $reviewCount != 1 ? 's' : '' }})</span>
            </div>
            <div class="small fw-semibold" style="color:#be123c">{{ number_format($p->total_sold) }} sold</div>
          </div>
        </div>
      </button>
      @endforeach
    </div>
  </div>
  @endif

<div class="card border-0 shadow-sm mb-4" style="border-radius:1.25rem;background:linear-gradient(135deg,#fff7fb,#fff)">
  <div class="card-body p-3 p-md-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <div>
        <h5 class="fw-bold mb-1" style="color:var(--primary)"><i class="bi bi-funnel me-2"></i>Smart Product Filters</h5>
        <p class="text-muted small mb-0">Search by cake name, flavor, seller, category, rating, or covered barangay</p>
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetCatalogFilters()">
        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
      </button>
    </div>
    <div class="row g-2">
      <div class="col-lg-4">
        <input type="text" id="catalogSearch" class="form-control form-control-lg"
          placeholder="Search cakes, flavors, shops, barangays..."
          oninput="filterCatalog()">
      </div>
      <div class="col-sm-6 col-lg-2">
        <select id="catalogClassFilter" class="form-select form-select-lg" onchange="filterCatalog()">
          <option value="">All categories</option>
          @foreach($products->pluck('classification')->filter()->unique()->sort()->values() as $classificationOption)
          <option value="{{ strtolower($classificationOption) }}">{{ $classificationOption }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-sm-6 col-lg-2">
        <select id="catalogRatingFilter" class="form-select form-select-lg" onchange="filterCatalog()">
          <option value="">Any rating</option>
          <option value="4">4 stars & up</option>
          <option value="3">3 stars & up</option>
          <option value="1">With reviews</option>
        </select>
      </div>
      <div class="col-sm-6 col-lg-2">
        <select id="catalogSellerFilter" class="form-select form-select-lg" onchange="filterCatalog()">
          <option value="">All sellers</option>
          @foreach($products->pluck('shop_name')->filter()->unique()->sort()->values() as $shopName)
          <option value="{{ strtolower($shopName) }}">{{ $shopName }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-sm-6 col-lg-2">
        <select id="catalogBarangayFilter" class="form-select form-select-lg" onchange="filterCatalog()">
          <option value="">All barangays</option>
          @foreach(($barangayOptions ?? collect()) as $barangay)
          <option value="{{ strtolower(trim($barangay)) }}">{{ $barangay }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="small text-muted mt-2" id="catalogFilterSummary">Showing {{ $products->count() }} cake options</div>
  </div>
</div>

<div id="catalogEmptyState" class="text-center py-5" style="display:none">
  <i class="bi bi-search" style="font-size:2.5rem;color:#d1d5db"></i>
  <p class="text-muted mt-3 mb-1">No cakes match your filters.</p>
  <button type="button" class="btn btn-outline-primary btn-sm" onclick="resetCatalogFilters()">Clear filters</button>
</div>

<div class="catalog-grid" id="catalogGrid">
    @forelse($products as $p)
    @php
      $classification = $p->classification ?? 'Standard';
      $classColors = [
        'Standard'   => ['bg'=>'#dbeafe','color'=>'#1e40af','icon'=>'bi-cake2'],
        'Fondant'    => ['bg'=>'#fce7f3','color'=>'#9d174d','icon'=>'bi-stars'],
        'Perishable' => ['bg'=>'#d1fae5','color'=>'#065f46','icon'=>'bi-snow'],
      ];
      $cls         = $classColors[$classification] ?? $classColors['Standard'];
      $sizes       = $sizesMap[$p->id] ?? collect();
      $avgRating   = isset($reviewsMap[$p->id]) ? $reviewsMap[$p->id]->avg_rating : null ?? null;
      $reviews     = $productReviews[$p->id] ?? [];
      $reviewCount = isset($reviewsMap[$p->id]) ? $reviewsMap[$p->id]->total : 0 ?? 0;
      $isAvailable = (int)($p->is_available ?? 1);
      $isArchived  = !empty($p->archived_at);
      $latestReview = $reviews[0] ?? null;
      $pricing = $p->discount_snapshot ?? null;
    @endphp
    <div class="catalog-item"
         data-name="{{ strtolower(trim($p->name . ' ' . ($p->description ?? '') . ' ' . ($p->flavor ?? '') . ' ' . ($p->classification ?? '') . ' ' . ($p->shop_name ?? '') . ' ' . ($p->delivery_barangays_text ?? '') . ' ' . ($latestReview->review ?? ''))) }}"
         data-classification="{{ strtolower($classification) }}"
         data-rating="{{ $avgRating ? number_format($avgRating, 1, '.', '') : 0 }}"
         data-reviewed="{{ $reviewCount > 0 ? 1 : 0 }}"
         data-seller="{{ strtolower($p->shop_name ?? '') }}"
         data-barangays="{{ $p->delivery_barangays_filter ?? '||' }}">
      <div class="catalog-card card h-100" style="{{ ($isArchived || !$isAvailable) ? 'opacity:.72' : '' }};transition:transform .3s cubic-bezier(.34,1.56,.64,1),box-shadow .3s ease">

        {{-- Image --}}
        <div class="catalog-img-wrap img-zoom-wrap position-relative overflow-hidden" style="border-radius:1.1rem 1.1rem 0 0;height:260px">
          <img src="{{ $p->image_path }}" alt="{{ $p->name }}"
               class="img-zoom-target"
               style="width:100%;height:100%;object-fit:cover;transition:transform .4s ease;cursor:zoom-in;user-select:none;-webkit-user-drag:none"
               onerror="this.src='https://placehold.co/400x220/fce4ec/e91e63?text=🎂'"
               onmouseover="this.style.transform='scale(1.12)'"
               onmouseout="this.style.transform='scale(1)'"
               data-src="{{ $p->image_path }}"
               onmousedown="startLongPress(event,this)" onmouseup="cancelLongPress()" onmouseleave="cancelLongPress()"
               ontouchstart="startLongPress(event,this)" ontouchend="cancelLongPress()" ontouchcancel="cancelLongPress()">
          @if($isArchived)
          <div style="position:absolute;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;z-index:2;border-radius:1.1rem 1.1rem 0 0">
            <span style="background:rgba(0,0,0,.7);color:#fff;font-size:.82rem;font-weight:700;padding:.4rem 1rem;border-radius:99px;letter-spacing:.03em"><i class="bi bi-slash-circle me-1"></i>Out of Stock</span>
          </div>
          @endif
          {{-- Classification Badge --}}
          <span class="position-absolute top-0 start-0 m-2 badge"
                style="background:{{ $cls['bg'] }};color:{{ $cls['color'] }};font-size:clamp(.68rem,1.3vw,.72rem)">
            <i class="bi {{ $cls['icon'] }} me-1"></i>{{ $classification }}
          </span>
          {{-- Rating Badge --}}
          @if($avgRating)
          <span class="position-absolute top-0 end-0 m-2 badge"
                style="background:rgba(0,0,0,.55);color:#fbbf24;font-size:clamp(.68rem,1.3vw,.72rem)">
            ★ {{ number_format($avgRating,1) }}
          </span>
          @endif
          {{-- Not Available overlay --}}
          @if(!$isAvailable)
          <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
               style="background:rgba(0,0,0,.45);border-radius:1.1rem 1.1rem 0 0">
            <span class="badge bg-danger px-3 py-2" style="font-size:clamp(.8rem,1.7vw,.9rem)">
              <i class="bi bi-x-circle me-1"></i>Not Available
            </span>
          </div>
          @endif
        </div>

        <div class="card-body d-flex flex-column p-4">
          <h5 class="fw-bold mb-1">{{ $p->name }}</h5>
          @if(!empty($p->shop_name))
          <a href="/shop/{{ $p->shop_slug }}" target="_blank"
             class="d-inline-flex align-items-center gap-1 text-decoration-none mb-1"
             style="font-size:.88rem;color:var(--primary)" onclick="event.stopPropagation()">
            @if(!empty($p->shop_logo))
              <img src="{{ $p->shop_logo }}" style="width:14px;height:14px;border-radius:3px;object-fit:cover;flex-shrink:0">
            @else
              <i class="bi bi-shop" style="font-size:.65rem"></i>
            @endif
            {{ $p->shop_name }}
          </a>
          @endif
          @if($p->flavor)
            <div class="text-muted small mb-1"><i class="bi bi-droplet me-1"></i>{{ $p->flavor }}</div>
          @endif
          <p class="text-muted small flex-grow-1 mb-2">{{ Str::limit($p->description, 80) }}</p>

          {{-- Rating summary --}}
          @if($avgRating)
          <div class="d-flex align-items-center gap-1 mb-2">
            @for($i=1;$i<=5;$i++)
              <i class="bi bi-star{{ $i <= round($avgRating) ? '-fill' : '' }}" style="color:#fbbf24;font-size:.95rem"></i>
            @endfor
            <span class="text-muted small ms-1">{{ number_format($avgRating,1) }} ({{ $reviewCount }} review{{ $reviewCount != 1 ? 's' : '' }})</span>
          </div>
          @endif

          @if($latestReview && !empty($latestReview->review))
          <div class="mb-3 p-2 rounded-3" style="background:#fff7ed;border:1px solid #fed7aa">
            <div class="small fw-semibold" style="color:#b45309"><i class="bi bi-chat-quote me-1"></i>Latest review</div>
            <div class="small text-muted mt-1">"{{ Str::limit($latestReview->review, 70) }}"</div>
          </div>
          @endif

          {{-- Sizes preview --}}
          @if(count($sizes) > 0)
          <div class="mb-3">
            <div class="text-muted small mb-1">Available Sizes:</div>
            <div class="d-flex flex-wrap gap-1">
              @foreach($sizes as $sz)
              <span class="badge" style="background:var(--primary-light);color:var(--primary);font-size:.85rem">
                {{ $sz->label }} — ₱{{ number_format($sz->price,2) }}
              </span>
              @endforeach
            </div>
          </div>
          @endif

          <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
              @if(!empty($pricing['has_discount']))
                <div class="text-muted text-decoration-line-through" style="font-size:.85rem">₱{{ number_format($pricing['original_unit_price'],2) }}</div>
                <span class="fw-bold fs-5" style="color:#dc2626">₱{{ number_format($pricing['final_unit_price'],2) }}</span>
                <div style="font-size:.8rem;color:#be123c;font-weight:700">{{ $pricing['badge_text'] }}</div>
              @else
                <span class="fw-bold fs-5" style="color:var(--primary)">₱{{ number_format($p->price,2) }}</span>
              @endif
              @if(count($sizes) > 0)
                <div class="text-muted" style="font-size:.85rem">Base price</div>
              @endif
            </div>
            @if($p->total_sold > 0)
            <span class="badge rounded-pill" style="background:#fff1f2;color:#be123c;font-size:.82rem">
              <i class="bi bi-fire me-1"></i>{{ number_format($p->total_sold) }} sold
            </span>
            @endif
          </div>
          @if($isArchived)
          <button class="btn w-100 py-2" style="font-size:1rem;background:#f3f4f6;color:#6b7280;border:1.5px solid #e5e7eb;cursor:not-allowed" disabled>
            <i class="bi bi-slash-circle me-2"></i>Out of Stock
          </button>
          @elseif($isAvailable)
          <button class="btn btn-primary w-100 py-2" style="font-size:1rem;font-weight:600" data-bs-toggle="modal" data-bs-target="#detailModal{{ $p->id }}">
            <i class="bi bi-cart-plus me-2"></i>Order Now
          </button>
          @else
          <button class="btn btn-secondary w-100 py-2" style="font-size:1rem" disabled>
            <i class="bi bi-x-circle me-2"></i>Not Available
          </button>
          @endif
        </div>
      </div>
    </div>

    @empty
    <div class="col-12 text-center py-5">
      <i class="bi bi-cake2" style="font-size:3rem;color:#ddd"></i>
      <p class="text-muted mt-3">No products available yet.</p>
    </div>
    @endforelse
  </div>
</div>

{{-- ── PRODUCT DETAIL MODALS (outside grid to avoid transform stacking context bug) --}}
@foreach($products as $p)
@php
  $classification = $p->classification ?? 'Standard';
  $classColors = [
    'Standard'   => ['bg'=>'#dbeafe','color'=>'#1e40af','icon'=>'bi-cake2'],
    'Fondant'    => ['bg'=>'#fce7f3','color'=>'#9d174d','icon'=>'bi-stars'],
    'Perishable' => ['bg'=>'#d1fae5','color'=>'#065f46','icon'=>'bi-snow'],
  ];
  $cls         = $classColors[$classification] ?? $classColors['Standard'];
  $sizes       = $sizesMap[$p->id] ?? collect();
  $avgRating   = isset($reviewsMap[$p->id]) ? $reviewsMap[$p->id]->avg_rating : null ?? null;
  $reviews     = $productReviews[$p->id] ?? [];
  $reviewCount = isset($reviewsMap[$p->id]) ? $reviewsMap[$p->id]->total : 0 ?? 0;
  $isAvailable = (int)($p->is_available ?? 1);
  $isArchived  = !empty($p->archived_at);
  $pricing     = $p->discount_snapshot ?? null;
@endphp
<div class="modal fade" id="detailModal{{ $p->id }}" tabindex="-1" data-bs-backdrop="false" data-bs-keyboard="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content border-0" style="border-radius:1.4rem;overflow:hidden">

      <div class="modal-header border-0 pb-0 px-4 pt-3" style="background:#fff;position:sticky;top:0;z-index:10">
        <div class="d-flex align-items-center gap-2 flex-wrap flex-grow-1">
          <span class="badge" style="background:{{ $cls['bg'] }};color:{{ $cls['color'] }};font-size:clamp(.68rem,1.3vw,.72rem)">
            <i class="bi {{ $cls['icon'] }} me-1"></i>{{ $classification }}
          </span>
          @if($isAvailable)
            <span class="badge bg-success" style="font-size:clamp(.68rem,1.3vw,.72rem)"><i class="bi bi-check-circle me-1"></i>Available</span>
          @else
            <span class="badge bg-danger" style="font-size:clamp(.68rem,1.3vw,.72rem)"><i class="bi bi-x-circle me-1"></i>Not Available</span>
          @endif
          <span class="fw-bold ms-1" style="font-size:clamp(.82rem,1.8vw,.95rem)">{{ $p->name }}</span>
        </div>
        <button type="button" class="btn-close ms-2" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body p-0">

        <div style="position:relative;background:#fafafa">
          <img src="{{ $p->image_path }}" alt="{{ $p->name }}"
               style="width:100%;max-height:260px;object-fit:cover;display:block;cursor:zoom-in"
               onerror="this.src='https://placehold.co/800x260/fce4ec/e91e63?text=🎂'"
               onclick="catLbOpen('{{ $p->image_path }}')">
          <div style="position:absolute;bottom:10px;right:12px;background:rgba(0,0,0,.45);color:#fff;border-radius:20px;padding:3px 10px;font-size:clamp(.68rem,1.3vw,.72rem);pointer-events:none">
            <i class="bi bi-zoom-in me-1"></i>Tap to zoom
          </div>
        </div>

        <div class="px-4 pt-3 pb-4">

          <div class="mb-2">
            <h4 class="fw-bold mb-1">{{ $p->name }}</h4>
            @if($avgRating)
            <div class="d-flex align-items-center gap-2 mb-1">
              <div class="d-flex gap-1">
                @for($i=1;$i<=5;$i++)
                  <i class="bi bi-star{{ $i <= round($avgRating) ? '-fill' : '' }}" style="color:#fbbf24;font-size:clamp(.78rem,1.6vw,.85rem)"></i>
                @endfor
              </div>
              <span class="fw-bold small" style="color:#fbbf24">{{ number_format($avgRating,1) }}</span>
              <span class="text-muted small">({{ $reviewCount }} review{{ $reviewCount != 1 ? 's' : '' }})</span>
            </div>
            @endif
          </div>

          <hr class="my-2">

          <div class="row g-2 mb-3">
            <div class="col-6">
              <div class="p-2 rounded-2" style="background:#f8f9fa">
                <div class="text-muted" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em">Price</div>
                @if(!empty($pricing['has_discount']))
                  <div class="text-muted text-decoration-line-through" style="font-size:.72rem">₱{{ number_format($pricing['original_unit_price'],2) }}</div>
                  <div class="fw-bold" style="color:#dc2626;font-size:1.15rem">₱{{ number_format($pricing['final_unit_price'],2) }}</div>
                  <div style="font-size:.68rem;color:#be123c;font-weight:700">{{ $pricing['badge_text'] }}</div>
                @else
                  <div class="fw-bold" style="color:var(--primary);font-size:1.15rem">₱{{ number_format($p->price,2) }}</div>
                @endif
                @if(count($sizes) > 0)
                  <div class="text-muted" style="font-size:.68rem">Base price</div>
                @endif
              </div>
            </div>
            <div class="col-6">
              <div class="p-2 rounded-2" style="background:#f8f9fa">
                <div class="text-muted" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em">Type</div>
                <div class="fw-semibold small" style="color:{{ $cls['color'] }}">
                  <i class="bi {{ $cls['icon'] }} me-1"></i>{{ $classification }}
                </div>
              </div>
            </div>
            @if($p->flavor)
            <div class="col-12">
              <div class="p-2 rounded-2" style="background:#f8f9fa">
                <div class="text-muted" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em">Flavor</div>
                <div class="fw-semibold small"><i class="bi bi-droplet me-1" style="color:var(--primary)"></i>{{ $p->flavor }}</div>
              </div>
            </div>
            @endif
            @if($p->description)
            <div class="col-12">
              <div class="p-2 rounded-2" style="background:#f8f9fa">
                <div class="text-muted" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em">Description</div>
                <div class="small text-muted mt-1">{{ $p->description }}</div>
              </div>
            </div>
            @endif
          </div>

          @if(count($sizes) > 0)
          <div class="mb-3">
            <div class="fw-semibold small mb-2"><i class="bi bi-rulers me-1" style="color:var(--primary)"></i>Available Sizes</div>
            <div class="d-flex flex-wrap gap-2">
              @foreach($sizes as $sz)
              <div class="px-3 py-1 rounded-pill border" style="border-color:var(--primary)!important;font-size:.78rem">
                <span class="fw-semibold">{{ $sz->label }}</span>
                <span class="text-muted ms-1">— ₱{{ number_format($sz->price,2) }}</span>
              </div>
              @endforeach
            </div>
          </div>
          @endif

          @if(!empty($p->shop_name))
          <a href="/shop/{{ $p->shop_slug }}" target="_blank"
             class="d-flex align-items-center gap-2 mb-3 p-2 rounded-2 text-decoration-none"
             style="background:#fff0f6;border:1px solid #fce7f3">
            @if(!empty($p->shop_logo))
              <img src="{{ $p->shop_logo }}" style="width:36px;height:36px;border-radius:8px;object-fit:cover;flex-shrink:0">
            @else
              <div style="width:36px;height:36px;border-radius:8px;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-shop-window text-white" style="font-size:.85rem"></i>
              </div>
            @endif
            <div class="flex-grow-1">
              <div class="fw-semibold" style="font-size:clamp(.74rem,1.5vw,.82rem);color:#9d174d">{{ $p->shop_name }}</div>
              <div class="text-muted" style="font-size:clamp(.66rem,1.3vw,.7rem)">Tap to view shop &rarr;</div>
            </div>
            <i class="bi bi-chevron-right" style="color:#d1d5db;font-size:.75rem"></i>
          </a>
          @else
          <div class="d-flex align-items-center gap-2 mb-3 p-2 rounded-2" style="background:#fff0f6;border:1px solid #fce7f3">
            <div style="width:28px;height:28px;border-radius:7px;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <i class="bi bi-cake2-fill text-white" style="font-size:clamp(.7rem,1.4vw,.75rem)"></i>
            </div>
            <div>
              <div class="fw-semibold" style="font-size:clamp(.74rem,1.5vw,.8rem)">{{ $shopSettings['site_title'] ?? 'Cake Shop' }}</div>
            </div>
          </div>
          @endif

          <hr class="my-3">

          @if($isArchived)
          <div class="alert border-0 text-center" style="background:#f3f4f6;color:#6b7280">
            <i class="bi bi-slash-circle me-2"></i><strong>Out of Stock</strong> — This cake is temporarily unavailable.
          </div>
          @elseif($isAvailable)
          <form action="{{ route('catalog.select') }}" method="POST">
            @csrf
            <input type="hidden" name="product_id" value="{{ $p->id }}">

            @if(count($sizes) > 0)
            <div class="mb-3">
              <label class="form-label fw-semibold small">Select Size <span class="text-danger">*</span></label>
              <select class="form-select" name="selected_size"
                      onchange="updateModalPrice('{{ $p->id }}', {{ $p->price }}, this)" required
                      data-discount-type="{{ $pricing['discount_type'] ?? '' }}"
                      data-discount-value="{{ $pricing['discount_value'] ?? 0 }}">
                <option value="">-- Choose a size --</option>
                @foreach($sizes as $sz)
                  <option value="{{ $sz->label }}" data-price="{{ $sz->price }}">
                    {{ $sz->label }} — ₱{{ number_format($sz->price,2) }}
                  </option>
                @endforeach
              </select>
              <div class="mt-2 p-2 rounded-2 d-flex align-items-center justify-content-between" style="background:#fff0f5">
                <span class="small text-muted">Total Price:</span>
                <span class="fw-bold" style="color:{{ !empty($pricing['has_discount']) ? '#dc2626' : 'var(--primary)' }};font-size:1.05rem" id="modalPrice{{ $p->id }}"
                      data-base-price="{{ $p->price }}"
                      data-discount-type="{{ $pricing['discount_type'] ?? '' }}"
                      data-discount-value="{{ $pricing['discount_value'] ?? 0 }}">
                  ₱{{ number_format($pricing['final_unit_price'] ?? $p->price,2) }}
                </span>
              </div>
            </div>
            @endif

            <div class="mb-3">
              <label class="form-label fw-semibold small">Quantity</label>
              <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3"
                        onclick="changeQty('{{ $p->id }}', -1)">−</button>
                <input type="number" class="form-control text-center fw-bold"
                       name="quantity" id="qty{{ $p->id }}"
                       min="1" max="20" value="1" required style="width:70px">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3"
                        onclick="changeQty('{{ $p->id }}', 1)">+</button>
              </div>
            </div>

            <div class="alert border-0 py-2 small mb-3" style="background:#fff0f5;border-radius:.7rem">
              <i class="bi bi-info-circle me-1" style="color:var(--primary)"></i>
              You'll choose pickup/delivery on the next step.
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
              <i class="bi bi-arrow-right-circle me-1"></i>Proceed to Checkout
            </button>
          </form>
          @else
          <div class="alert alert-danger text-center border-0">
            <i class="bi bi-x-circle me-2"></i>This cake is currently not available.
          </div>
          @endif

          <div class="mt-4">
            <div class="fw-bold mb-3" style="border-bottom:2px solid var(--primary);padding-bottom:.5rem">
              <i class="bi bi-star-fill me-1" style="color:#fbbf24"></i>
              Customer Reviews
              @if($reviewCount > 0)
                <span class="text-muted fw-normal small ms-1">({{ $reviewCount }})</span>
              @endif
            </div>
            @if(count($reviews) > 0)
              <div>
                @foreach($reviews as $rv)
                <div class="d-flex gap-3 mb-3 pb-3" style="border-bottom:1px solid #f0f0f0">
                  <div style="flex-shrink:0">
                    @if($rv->profile_photo)
                      <img src="{{ $rv->profile_photo }}" style="width:38px;height:38px;border-radius:50%;object-fit:cover">
                    @else
                      <div style="width:38px;height:38px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:clamp(.8rem,1.7vw,.9rem)">
                        {{ strtoupper(substr($rv->fullname,0,1)) }}
                      </div>
                    @endif
                  </div>
                  <div class="flex-grow-1">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-1">
                      <span class="fw-semibold small">{{ $rv->fullname }}</span>
                      <span class="text-muted" style="font-size:clamp(.68rem,1.3vw,.72rem)">{{ \Carbon\Carbon::parse($rv->created_at)->diffForHumans() }}</span>
                    </div>
                    <div class="d-flex gap-1 my-1">
                      @for($i=1;$i<=5;$i++)
                        <i class="bi bi-star{{ $i <= $rv->rating ? '-fill' : '' }}" style="color:#fbbf24;font-size:.78rem"></i>
                      @endfor
                    </div>
                    @if($rv->review)
                      <p class="small mb-1 text-muted">{{ $rv->review }}</p>
                    @endif
                    @if(!empty($rv->image_path))
                      <img src="{{ $rv->image_path }}" alt="Review photo"
                           style="width:80px;height:80px;object-fit:cover;border-radius:.5rem;cursor:pointer;border:2px solid #fce7f3"
                           onclick="catLbOpen('{{ $rv->image_path }}')">
                    @endif
                  </div>
                </div>
                @endforeach
              </div>
            @else
              <div class="text-center py-4 text-muted">
                <i class="bi bi-chat-square-text" style="font-size:2rem;opacity:.3"></i>
                <p class="small mt-2">No reviews yet. Be the first to review!</p>
              </div>
            @endif
          </div>

        </div>{{-- end px-4 --}}
      </div>{{-- end modal-body --}}
    </div>{{-- end modal-content --}}
  </div>{{-- end modal-dialog --}}
</div>{{-- end modal --}}
@endforeach

<script>
function forceCleanModals() {
  document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
  document.querySelectorAll('.modal.show').forEach(el => {
    el.classList.remove('show');
    el.style.display = 'none';
  });
  document.body.classList.remove('modal-open');
  document.body.style.overflow = '';
  document.body.style.paddingRight = '';
}

function updateModalPrice(productId, basePrice, select) {
  const opt   = select.options[select.selectedIndex];
  const price = opt.dataset.price ? parseFloat(opt.dataset.price) : basePrice;
  const discountType = select.dataset.discountType || '';
  const discountValue = parseFloat(select.dataset.discountValue || '0');
  let finalPrice = price;

  if (discountType === 'percent' && discountValue > 0) {
    finalPrice = price - (price * (discountValue / 100));
  } else if (discountType === 'fixed' && discountValue > 0) {
    finalPrice = price - discountValue;
  }

  finalPrice = Math.max(0, finalPrice);
  const el    = document.getElementById('modalPrice' + productId);
  if (el) el.textContent = '₱' + finalPrice.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
}

function changeQty(productId, delta) {
  const input = document.getElementById('qty' + productId);
  if (!input) return;
  let val = parseInt(input.value) + delta;
  if (val < 1) val = 1;
  if (val > 20) val = 20;
  input.value = val;
}

// ── Long press to open lightbox ──────────────────────────────
let lpTimer = null;
let lpFired  = false;

function startLongPress(e, img) {
  lpFired = false;
  lpTimer = setTimeout(() => {
    lpFired = true;
    catLbOpen(img.dataset.src || img.src);
  }, 600);
}
function cancelLongPress() {
  if (lpTimer) { clearTimeout(lpTimer); lpTimer = null; }
}


// Clean on back-button restore
window.addEventListener('pageshow', function(e) { if (e.persisted) forceCleanModals(); });
// Clean after modal closes
document.addEventListener('hidden.bs.modal', forceCleanModals);

// The .customer-wrap animation leaves transform:translateY(0) which creates a
// CSS stacking context — this traps Bootstrap's position:fixed modal behind the
// backdrop. Clear the transform as soon as the animation ends.
(function() {
  var wrap = document.querySelector('.customer-wrap');
  if (!wrap) return;
  function clearTransform() { wrap.style.transform = 'none'; }
  wrap.addEventListener('animationend', clearTransform, { once: true });
  setTimeout(clearTransform, 500); // fallback in case animationend doesn't fire
})();
</script>

@push('scripts')
@endpush

<script>
function filterCatalog(){
  const q = (document.getElementById('catalogSearch')?.value || '').toLowerCase().trim();
  const classification = (document.getElementById('catalogClassFilter')?.value || '').toLowerCase();
  const rating = parseFloat(document.getElementById('catalogRatingFilter')?.value || '0');
  const seller = (document.getElementById('catalogSellerFilter')?.value || '').toLowerCase();
  const barangay = (document.getElementById('catalogBarangayFilter')?.value || '').toLowerCase();
  let visibleCount = 0;

  document.querySelectorAll('.catalog-item').forEach(el => {
    const haystack = (el.getAttribute('data-name') || '').toLowerCase();
    const elClass = (el.getAttribute('data-classification') || '').toLowerCase();
    const elSeller = (el.getAttribute('data-seller') || '').toLowerCase();
    const elBarangays = (el.getAttribute('data-barangays') || '').toLowerCase();
    const elRating = parseFloat(el.getAttribute('data-rating') || '0');
    const reviewed = (el.getAttribute('data-reviewed') || '0') === '1';

    const matchesSearch = !q || haystack.includes(q);
    const matchesClass = !classification || elClass === classification;
    const matchesSeller = !seller || elSeller === seller;
    const matchesBarangay = !barangay || elBarangays.includes('|' + barangay + '|');
    const matchesRating = !rating || (rating === 1 ? reviewed : elRating >= rating);
    const matches = matchesSearch && matchesClass && matchesSeller && matchesBarangay && matchesRating;

    el.style.display = matches ? '' : 'none';
    if (matches) visibleCount++;
  });

  const emptyState = document.getElementById('catalogEmptyState');
  if (emptyState) emptyState.style.display = visibleCount === 0 ? 'block' : 'none';

  const summary = document.getElementById('catalogFilterSummary');
  if (summary) {
    const barangayLabel = document.getElementById('catalogBarangayFilter')?.selectedOptions?.[0]?.text || '';
    const suffix = barangay ? ' for ' + barangayLabel : '';
    summary.textContent = 'Showing ' + visibleCount + ' of ' + document.querySelectorAll('.catalog-item').length + ' cake options' + suffix;
  }
}

function resetCatalogFilters() {
  ['catalogSearch','catalogClassFilter','catalogRatingFilter','catalogSellerFilter','catalogBarangayFilter'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  filterCatalog();
}
</script>


@endsection
