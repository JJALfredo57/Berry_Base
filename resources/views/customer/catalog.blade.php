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
.catalog-item{ transition: all .3s ease; }
@media (hover: hover) {
  .catalog-card:hover {
    transform: translateY(-8px) scale(1.02) !important;
    box-shadow: 0 20px 40px rgba(233,30,99,.18) !important;
  }
}
@media(max-width:600px){
  .catalog-grid{ grid-template-columns: 1fr; gap:.75rem; }
  .best-seller-grid{ grid-template-columns:1fr; }
  .catalog-img-wrap{ height:200px !important; }
}
.filter-fab{display:none}
.filter-overlay{display:none}
.filter-panel{transition:transform .25s ease, box-shadow .25s ease}
@media(max-width:768px){
  .filter-fab{display:inline-flex;position:fixed;right:14px;bottom:82px;z-index:1041;border-radius:999px;box-shadow:0 12px 28px rgba(15,23,42,.2)}
  .filter-overlay{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1040}
  .filter-overlay.show{display:block}
  .filter-panel{position:fixed;top:0;right:0;bottom:0;width:min(88vw,360px);z-index:1042;overflow:auto;border-radius:0!important;transform:translateX(105%);margin:0!important}
  .filter-panel.show{transform:translateX(0);box-shadow:-18px 0 40px rgba(15,23,42,.2)}
}
.customer-wrap { animation: none !important; transform: none !important; }
.size-choice-btn{
  border-color:var(--primary)!important;
  font-size:.78rem;
  color:#111827;
  transition:background .16s ease,color .16s ease,box-shadow .16s ease,transform .16s ease;
}
.size-choice-btn:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(233,30,99,.12)}
.size-choice-btn.is-selected{background:var(--primary)!important;color:#fff!important;border-color:var(--primary)!important;box-shadow:0 8px 20px rgba(233,30,99,.24)}
.size-choice-btn.is-selected .text-muted{color:rgba(255,255,255,.78)!important}
.size-choice-btn:focus{box-shadow:0 0 0 .16rem rgba(233,30,99,.18)}
.size-view-more-btn{font-size:.78rem;font-weight:700;color:var(--primary);background:#fff;border:1px dashed var(--primary);border-radius:999px;padding:.25rem .75rem}
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
  <div class="mb-5" id="bestSellerSection">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <div>
        <h5 class="fw-bold mb-1" style="color:#9d174d"><i class="bi bi-fire me-2"></i>Best Seller Cakes</h5>
        <p class="text-muted small mb-0">Most ordered favorites from customers</p>
      </div>
      <span class="badge rounded-pill" style="background:#fff1f2;color:#be123c;font-size:.78rem">Live ranking from completed and active orders</span>
    </div>
    <div class="best-seller-grid" id="bestSellerGrid">
      @foreach($bestSellers as $p)
      @php
        $avgRating   = isset($reviewsMap[$p->id]) ? $reviewsMap[$p->id]->avg_rating : null ?? null;
        $reviewCount = isset($reviewsMap[$p->id]) ? $reviewsMap[$p->id]->total : 0 ?? 0;
      @endphp
      <button type="button" class="best-seller-item text-start border-0 p-0"
              data-name="{{ strtolower(trim($p->name . ' ' . ($p->description ?? '') . ' ' . ($p->flavor ?? '') . ' ' . ($p->classification ?? '') . ' ' . ($p->shop_name ?? '') . ' ' . ($p->delivery_barangays_text ?? ''))) }}"
              data-classification="{{ strtolower($p->classification ?? '') }}"
              data-seller="{{ strtolower($p->shop_name ?? '') }}"
              data-barangays="{{ $p->delivery_barangays_filter ?? '||' }}"
              data-bs-toggle="modal" data-bs-target="#detailModal{{ $p->id }}"
              style="background:#fff;border-radius:1.15rem;overflow:hidden;box-shadow:0 12px 30px rgba(15,23,42,.08)">
        <div class="position-relative" style="height:180px">
          <img src="{{ $p->image_path }}" alt="{{ $p->name }}" style="width:100%;height:100%;object-fit:cover"
               onerror="this.src='https://placehold.co/480x320/fce4ec/e91e63?text=Cake'">
          <span class="position-absolute top-0 start-0 m-2 badge best-seller-rank" style="background:#be123c;color:#fff">
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

<button type="button" class="btn btn-primary filter-fab" onclick="toggleCatalogFilters(true)">
  <i class="bi bi-funnel me-1"></i>Filters
</button>
<div id="catalogFilterOverlay" class="filter-overlay" onclick="toggleCatalogFilters(false)"></div>
<div id="catalogFilterPanel" class="card border-0 shadow-sm mb-4 filter-panel" style="border-radius:1.25rem;background:#fff">
  <div class="card-body p-3 p-md-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <div>
        <h5 class="fw-bold mb-1" style="color:var(--primary)"><i class="bi bi-funnel me-2"></i>Smart Product Filters</h5>
        <p class="text-muted small mb-0">Search by cake name, flavor, seller, category, or covered barangay</p>
      </div>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetCatalogFilters()">
          <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm d-md-none" onclick="toggleCatalogFilters(false)">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    </div>
    <div class="row g-2">
      <div class="col-lg-4">
        <input type="text" id="catalogSearch" class="form-control form-control-lg"
          placeholder="Search cakes, flavors, shops, barangays..."
          oninput="filterCatalog()">
      </div>
      <div class="col-sm-6 col-lg-2">
        <input id="catalogClassFilter" list="catalogClassOptions" class="form-control form-control-lg" placeholder="All categories" oninput="filterCatalog()">
        <datalist id="catalogClassOptions">
          @foreach($products->pluck('classification')->filter()->unique()->sort()->values() as $classificationOption)
          <option value="{{ $classificationOption }}"></option>
          @endforeach
        </datalist>
      </div>
      <div class="col-sm-6 col-lg-2">
        <input id="catalogSellerFilter" list="catalogSellerOptions" class="form-control form-control-lg" placeholder="All sellers" oninput="filterCatalog()">
        <datalist id="catalogSellerOptions">
          @foreach($products->pluck('shop_name')->filter()->unique()->sort()->values() as $shopName)
          <option value="{{ $shopName }}"></option>
          @endforeach
        </datalist>
      </div>
      <div class="col-sm-6 col-lg-2">
        <input id="catalogBarangayFilter" list="catalogBarangayOptions" class="form-control form-control-lg" placeholder="All barangays" oninput="filterCatalog()">
        <datalist id="catalogBarangayOptions">
          @foreach(($barangayOptions ?? collect()) as $barangay)
          <option value="{{ $barangay }}"></option>
          @endforeach
        </datalist>
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
      $sizes       = $productSizes[$p->id] ?? collect();
      $avgRating   = $productRatings[$p->id] ?? null;
      $reviews     = $productReviews[$p->id] ?? [];
      $reviewCount = $productReviewCounts[$p->id] ?? 0;
      $isAvailable = (int)($p->is_available ?? 1);
      $isArchived  = !empty($p->archived_at);
      $pricing     = $p->discount_snapshot ?? null;
      $activeDiscount = $p->active_discount ?? null;
      $sweetDealBadge = ($activeDiscount && property_exists($activeDiscount, 'deal_badge_label')) ? trim((string) ($activeDiscount->deal_badge_label ?? '')) : '';
      $sweetDealNote = ($activeDiscount && property_exists($activeDiscount, 'deal_note')) ? trim((string) ($activeDiscount->deal_note ?? '')) : '';
      $sweetDealQty = ($activeDiscount && property_exists($activeDiscount, 'deal_quantity_limit')) ? (int) ($activeDiscount->deal_quantity_limit ?? 0) : 0;
      $bestEnjoyedBy = ($activeDiscount && property_exists($activeDiscount, 'best_enjoyed_by') && !empty($activeDiscount->best_enjoyed_by)) ? \Carbon\Carbon::parse($activeDiscount->best_enjoyed_by) : null;
      $stockTracked = property_exists($p, 'available_quantity') && $p->available_quantity !== null;
      $stockQty = $stockTracked ? max(0, (int) $p->available_quantity) : null;
      $hasSizeOptions = count($sizes) > 0;
      $hasStock = $hasSizeOptions
        ? collect($sizes)->contains(fn($sz) => !property_exists($sz, 'available_quantity') || $sz->available_quantity === null || (int) $sz->available_quantity > 0)
        : (!$stockTracked || $stockQty > 0);
    @endphp
    @php
      $latestReview = $reviews[0] ?? null;
    @endphp
    <div class="catalog-item"
         data-name="{{ strtolower(trim($p->name . ' ' . ($p->description ?? '') . ' ' . ($p->flavor ?? '') . ' ' . ($p->classification ?? '') . ' ' . ($p->shop_name ?? '') . ' ' . ($p->delivery_barangays_text ?? '') . ' ' . ($latestReview->review ?? ''))) }}"
         data-classification="{{ strtolower($p->classification ?? '') }}"
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
          {{-- Classification Badge --}}
          <span class="position-absolute top-0 start-0 m-2 badge"
                style="background:{{ $cls['bg'] }};color:{{ $cls['color'] }};font-size:.72rem">
            <i class="bi {{ $cls['icon'] }} me-1"></i>{{ $classification }}
          </span>
          {{-- Rating Badge --}}
          @if($avgRating)
          <span class="position-absolute top-0 end-0 m-2 badge"
                style="background:rgba(0,0,0,.55);color:#fbbf24;font-size:.72rem">
            ★ {{ number_format($avgRating,1) }}
          </span>
          @endif
          @if(!empty($pricing['has_discount']) && $sweetDealBadge)
          <div class="position-absolute start-0 m-2 d-flex flex-column align-items-start gap-1" style="top:2.35rem;z-index:3;max-width:calc(100% - 1rem)">
            <span class="badge" style="background:linear-gradient(135deg,#fb7185,#f97316);color:#fff;border:1px solid rgba(255,255,255,.65);box-shadow:0 10px 24px rgba(190,18,60,.24);font-size:clamp(.7rem,1.5vw,.78rem);font-weight:800;white-space:normal;text-align:left">
              <i class="bi bi-stars me-1"></i>{{ $sweetDealBadge }}
            </span>
            @if(!empty($pricing['badge_text']))
              <span class="badge" style="background:rgba(255,255,255,.92);color:#be123c;border:1px solid #fecdd3;font-size:clamp(.66rem,1.3vw,.72rem);font-weight:800">{{ $pricing['badge_text'] }}</span>
            @endif
          </div>
          @endif
          @if($isArchived)
          <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
               style="background:rgba(0,0,0,.35);border-radius:1.1rem 1.1rem 0 0">
            <span style="background:rgba(0,0,0,.7);color:#fff;font-size:.82rem;font-weight:700;padding:.4rem 1rem;border-radius:99px"><i class="bi bi-slash-circle me-1"></i>Out of Stock</span>
          </div>
          @elseif(!$isAvailable)
          <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
               style="background:rgba(0,0,0,.45);border-radius:1.1rem 1.1rem 0 0">
            <span class="badge bg-danger px-3 py-2" style="font-size:.9rem">
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
          <div class="mb-2">
            @if($hasSizeOptions)
              <span class="badge rounded-pill" style="background:#f8fafc;color:#475569;border:1px solid #e2e8f0;font-size:.78rem"><i class="bi bi-rulers me-1"></i>Stock varies by size</span>
            @elseif($stockTracked)
              <span class="badge rounded-pill" style="background:{{ $stockQty <= 0 ? '#fef2f2' : ($stockQty <= 3 ? '#fffbeb' : '#ecfdf5') }};color:{{ $stockQty <= 0 ? '#b91c1c' : ($stockQty <= 3 ? '#92400e' : '#047857') }};border:1px solid {{ $stockQty <= 0 ? '#fecaca' : ($stockQty <= 3 ? '#fde68a' : '#a7f3d0') }};font-size:.78rem">
                <i class="bi {{ $stockQty <= 0 ? 'bi-exclamation-circle' : 'bi-box-seam' }} me-1"></i>{{ $stockQty <= 0 ? 'Out of stock' : ($stockQty <= 3 ? 'Only '.$stockQty.' left' : $stockQty.' available') }}
              </span>
            @else
              <span class="badge rounded-pill" style="background:#f8fafc;color:#64748b;border:1px solid #e2e8f0;font-size:.78rem"><i class="bi bi-check-circle me-1"></i>Available</span>
            @endif
          </div>
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

          {{-- Sizes preview --}}
          @if(false && count($sizes) > 0)
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
                <div style="font-size:.8rem;color:#be123c;font-weight:700">{{ $sweetDealBadge ?: $pricing['badge_text'] }}</div>
                @if($sweetDealNote || $sweetDealQty > 0 || $bestEnjoyedBy)
                  <div class="d-flex flex-wrap gap-1 mt-1" style="max-width:220px">
                    @if($sweetDealNote)
                      <span class="badge rounded-pill" style="background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;font-size:.68rem;font-weight:700">{{ $sweetDealNote }}</span>
                    @endif
                    @if($sweetDealQty > 0)
                      <span class="badge rounded-pill" style="background:#f8fafc;color:#475569;border:1px solid #e2e8f0;font-size:.68rem;font-weight:700">{{ $sweetDealQty }} deal pcs</span>
                    @endif
                    @if($bestEnjoyedBy)
                      <span class="badge rounded-pill" style="background:#fff1f2;color:#be123c;border:1px solid #fecdd3;font-size:.68rem;font-weight:700">Fresh until {{ $bestEnjoyedBy->format('M d, g:i A') }}</span>
                    @endif
                  </div>
                @endif
              @else
                <span class="fw-bold fs-5" style="color:var(--primary)">₱{{ number_format($p->price,2) }}</span>
              @endif
              @if(count($sizes) > 0)
                <div class="text-muted" style="font-size:.85rem">Base price</div>
              @endif
            </div>
          </div>
          @if($isArchived)
          <button class="btn w-100 py-2" style="font-size:1rem;background:#f3f4f6;color:#6b7280;border:1.5px solid #e5e7eb;cursor:not-allowed" disabled>
            <i class="bi bi-slash-circle me-2"></i>Out of Stock
          </button>
          @elseif($isAvailable && $hasStock)
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

    {{-- ── PRODUCT DETAIL MODAL ──────────────────────────────────────── --}}
    <div class="modal fade catalog-detail-modal" id="detailModal{{ $p->id }}" tabindex="-1" data-bs-backdrop="false" data-bs-keyboard="true">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content border-0" style="border-radius:1.4rem;overflow:hidden">

          {{-- Sticky Header --}}
          <div class="modal-header border-0 pb-0 px-4 pt-3" style="background:#fff;position:sticky;top:0;z-index:10">
            <div class="d-flex align-items-center gap-2 flex-wrap flex-grow-1">
              <span class="badge" style="background:{{ $cls['bg'] }};color:{{ $cls['color'] }};font-size:.72rem">
                <i class="bi {{ $cls['icon'] }} me-1"></i>{{ $classification }}
              </span>
              @if($isArchived)
                <span class="badge" style="background:#f3f4f6;color:#6b7280;font-size:.72rem"><i class="bi bi-slash-circle me-1"></i>Out of Stock</span>
              @elseif($isAvailable && $hasStock)
                <span class="badge bg-success" style="font-size:.72rem"><i class="bi bi-check-circle me-1"></i>Available</span>
              @else
                <span class="badge bg-danger" style="font-size:.72rem"><i class="bi bi-x-circle me-1"></i>Not Available</span>
              @endif
              <span class="fw-bold ms-1" style="font-size:.95rem">{{ $p->name }}</span>
            </div>
            <button type="button" class="btn-close ms-2" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body p-0">

            {{-- Top: Image (full width, clickable to zoom) --}}
            <div style="position:relative;background:#fafafa">
              <img src="{{ $p->image_path }}" alt="{{ $p->name }}"
                   style="width:100%;max-height:260px;object-fit:cover;display:block;cursor:zoom-in"
                   onerror="this.src='https://placehold.co/800x260/fce4ec/e91e63?text=🎂'"
                   onclick="catLbOpen('{{ $p->image_path }}')">
              <div style="position:absolute;bottom:10px;right:12px;background:rgba(0,0,0,.45);color:#fff;border-radius:20px;padding:3px 10px;font-size:.72rem;pointer-events:none">
                <i class="bi bi-zoom-in me-1"></i>Tap to zoom
              </div>
            </div>

            <div class="px-4 pt-3 pb-4">

              {{-- Name + Rating --}}
              <div class="mb-2">
                <h4 class="fw-bold mb-1">{{ $p->name }}</h4>
                @if($avgRating)
                <div class="d-flex align-items-center gap-2 mb-1">
                  <div class="d-flex gap-1">
                    @for($i=1;$i<=5;$i++)
                      <i class="bi bi-star{{ $i <= round($avgRating) ? '-fill' : '' }}" style="color:#fbbf24;font-size:.85rem"></i>
                    @endfor
                  </div>
                  <span class="fw-bold small" style="color:#fbbf24">{{ number_format($avgRating,1) }}</span>
                  <span class="text-muted small">({{ $reviewCount }} review{{ $reviewCount != 1 ? 's' : '' }})</span>
                </div>
                @endif
              </div>

              {{-- Divider --}}
              <hr class="my-2">

              {{-- Details Grid --}}
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

              @if(false)
              {{-- Sizes --}}
              @if(count($sizes) > 0)
              <div class="mb-3">
                <div class="fw-semibold small mb-2"><i class="bi bi-rulers me-1" style="color:var(--primary)"></i>Available Sizes</div>
                <div class="d-flex flex-wrap gap-2">
                  @foreach($sizes as $sz)
                  @php
                    $sizeStockTracked = property_exists($sz, 'available_quantity') && $sz->available_quantity !== null;
                    $sizeStockQty = $sizeStockTracked ? max(0, (int) $sz->available_quantity) : null;
                    $sizeOut = $sizeStockTracked && $sizeStockQty <= 0;
                  @endphp
                  <button type="button"
                          class="size-choice-btn px-3 py-1 rounded-pill border bg-white {{ $loop->iteration > 4 ? 'd-none is-extra-size' : '' }}"
                          data-product-id="{{ $p->id }}"
                          data-base-price="{{ $p->price }}"
                          data-size-label="{{ $sz->label }}"
                          data-price="{{ $sz->price }}"
                          data-stock-tracked="{{ $sizeStockTracked ? '1' : '0' }}"
                          data-stock-qty="{{ $sizeStockQty ?? '' }}"
                          {{ $sizeOut ? 'disabled' : '' }}
                          onclick="selectModalSize(this)">
                    <span class="fw-semibold">{{ $sz->label }}</span>
                    <span class="text-muted ms-1">- PHP {{ number_format($sz->price,2) }}</span>
                    @if($sizeStockTracked)
                      <span class="text-muted ms-1">{{ $sizeOut ? 'Out' : $sizeStockQty.' left' }}</span>
                    @endif
                  </button>
                @endforeach
                </div>
              </div>
              @endif

              @endif

              {{-- Shop Info --}}
              @if(!empty($p->shop_name))
              <a href="/shop/{{ $p->shop_slug }}" target="_blank"
                 class="d-flex align-items-center gap-2 mb-3 p-2 rounded-2 text-decoration-none"
                 style="background:#fff0f6;border:1px solid #fce7f3">
                @if(!empty($p->shop_logo))
                  <img src="{{ $p->shop_logo }}" style="width:32px;height:32px;border-radius:8px;object-fit:cover;flex-shrink:0">
                @else
                  <div style="width:32px;height:32px;border-radius:8px;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="bi bi-shop-window text-white" style="font-size:.8rem"></i>
                  </div>
                @endif
                <div class="flex-grow-1">
                  <div class="fw-semibold" style="font-size:.82rem;color:#9d174d">{{ $p->shop_name }}</div>
                  <div class="text-muted" style="font-size:.7rem">Tap to view shop &rarr;</div>
                </div>
                <i class="bi bi-chevron-right" style="color:#d1d5db;font-size:.75rem"></i>
              </a>
              @else
              <div class="d-flex align-items-center gap-2 mb-3 p-2 rounded-2" style="background:#fff0f6;border:1px solid #fce7f3">
                @if(!empty($shopSettings['logo_path']))
                  <img src="{{ $shopSettings['logo_path'] }}" style="width:28px;height:28px;border-radius:7px;object-fit:cover;flex-shrink:0">
                @else
                  <div style="width:28px;height:28px;border-radius:7px;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="bi bi-cake2-fill text-white" style="font-size:.75rem"></i>
                  </div>
                @endif
                <div>
                  <div class="fw-semibold" style="font-size:.8rem">{{ $shopSettings['site_title'] ?? 'Cake Shop' }}</div>
                  @if(!empty($shopSettings['tagline']))
                    <div class="text-muted" style="font-size:.7rem">{{ $shopSettings['tagline'] }}</div>
                  @endif
                </div>
              </div>
              @endif

              {{-- Divider --}}
              <hr class="my-3">

              {{-- Order Form --}}
              @if($isArchived)
              <div class="alert border-0 text-center" style="background:#f3f4f6;color:#6b7280">
                <i class="bi bi-slash-circle me-2"></i><strong>Out of Stock</strong> — This cake is temporarily unavailable.
              </div>
              @elseif($isAvailable && $hasStock)
              <form action="{{ route('customer.catalog.order') }}" method="POST" onsubmit="return confirmOrder(this)">
                @csrf
                <input type="hidden" name="product_id" value="{{ $p->id }}">

                {{-- Size Selection --}}
                @if(count($sizes) > 0)
                <div class="mb-3">
                  <div class="fw-semibold small mb-2"><i class="bi bi-rulers me-1" style="color:var(--primary)"></i>Sizes <span class="text-danger">*</span></div>
                  <input type="hidden" name="selected_size" id="selectedSize{{ $p->id }}">
                  <div class="d-flex flex-wrap gap-2" data-size-picker id="sizeOptions{{ $p->id }}"
                       data-discount-type="{{ $pricing['discount_type'] ?? '' }}"
                       data-discount-value="{{ $pricing['discount_value'] ?? 0 }}">
                    @foreach($sizes as $sz)
                      @php
                        $sizeStockTracked = property_exists($sz, 'available_quantity') && $sz->available_quantity !== null;
                        $sizeStockQty = $sizeStockTracked ? max(0, (int) $sz->available_quantity) : null;
                        $sizeOut = $sizeStockTracked && $sizeStockQty <= 0;
                      @endphp
                      <button type="button"
                              class="size-choice-btn px-3 py-1 rounded-pill border bg-white {{ $loop->iteration > 4 ? 'd-none is-extra-size' : '' }}"
                              data-product-id="{{ $p->id }}"
                              data-base-price="{{ $p->price }}"
                              data-size-label="{{ $sz->label }}"
                              data-price="{{ $sz->price }}"
                              data-stock-tracked="{{ $sizeStockTracked ? '1' : '0' }}"
                              data-stock-qty="{{ $sizeStockQty ?? '' }}"
                              {{ $sizeOut ? 'disabled' : '' }}
                              onclick="selectModalSize(this)">
                        <span class="fw-semibold">{{ $sz->label }}</span>
                        <span class="text-muted ms-1">- PHP {{ number_format($sz->price,2) }}</span>
                        @if($sizeStockTracked)
                          <span class="text-muted ms-1">{{ $sizeOut ? 'Out' : $sizeStockQty.' left' }}</span>
                        @endif
                      </button>
                    @endforeach
                    @if(count($sizes) > 4)
                      <button type="button" class="size-view-more-btn" data-size-toggle data-product-id="{{ $p->id }}" onclick="toggleSizeOptions(this)">
                        View more
                      </button>
                    @endif
                  </div>
                  <div class="small text-danger mt-1 d-none" data-size-error>Please select a size.</div>
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
                @if(false)
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
                    <span class="fw-bold" style="color:{{ !empty($pricing['has_discount']) ? '#dc2626' : 'var(--primary)' }};font-size:1.05rem" id="modalPrice{{ $p->id }}">
                      ₱{{ number_format($pricing['final_unit_price'] ?? $p->price,2) }}
                    </span>
                  </div>
                </div>
                @endif
                @endif

                {{-- Quantity --}}
                <div class="mb-3">
                  <label class="form-label fw-semibold small">Quantity</label>
                  <div class="small mb-2 {{ $hasSizeOptions || $stockTracked ? '' : 'd-none' }}" id="stockHint{{ $p->id }}" style="color:{{ $stockTracked && $stockQty <= 3 ? '#92400e' : '#047857' }}">
                    <i class="bi bi-box-seam me-1"></i>{{ $hasSizeOptions ? 'Choose a size to see stock.' : ($stockQty <= 0 ? 'No cakes left for this item.' : $stockQty.' cake'.($stockQty > 1 ? 's' : '').' available for ordering.') }}
                  </div>
                  <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3"
                            onclick="changeQty('{{ $p->id }}', -1)">−</button>
                    <input type="number" class="form-control text-center fw-bold"
                           name="quantity" id="qty{{ $p->id }}"
                           min="1" max="{{ $stockTracked ? min(20, max(1, $stockQty)) : 20 }}" value="1" required style="width:70px">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3"
                            onclick="changeQty('{{ $p->id }}', 1)">+</button>
                  </div>
                </div>

                <div class="alert border-0 py-2 small mb-3" style="background:#fff0f5;border-radius:.7rem">
                  <i class="bi bi-info-circle me-1" style="color:var(--primary)"></i>
                  You'll choose pickup/delivery on the next step.
                </div>

                <div class="form-check d-flex align-items-start gap-2 mb-3 p-3" style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:.75rem">
                  <input class="form-check-input mt-1" type="checkbox" name="stay_on_catalog" value="1" id="stayCatalog{{ $p->id }}" checked>
                  <label class="form-check-label small" for="stayCatalog{{ $p->id }}">
                    <span class="fw-semibold d-block">Stay on catalog after adding to cart</span>
                    <span class="text-muted">Uncheck this if you want to open your cart right after adding.</span>
                  </label>
                </div>

                {{-- ── Date Availability Check ─────────────────────────────── --}}
                <div class="d-grid gap-2">
                  <button type="submit" class="btn btn-primary py-2 fw-semibold" onclick="this.form.dataset.cartSubmit=''">
                    <i class="bi bi-arrow-right-circle me-1"></i>Proceed to Checkout
                  </button>
                  <button type="submit" class="btn btn-outline-primary py-2 fw-semibold"
                          formaction="{{ route('customer.cart.add') }}"
                          onclick="this.form.dataset.cartSubmit='1'">
                    <i class="bi bi-cart-plus me-1"></i>Add to Cart
                  </button>
                </div>
              </form>
              @else
              <div class="alert alert-danger text-center border-0">
                <i class="bi bi-x-circle me-2"></i>{{ $stockTracked && $stockQty <= 0 ? 'This cake is currently out of stock.' : 'This cake is currently not available.' }}
              </div>
              @endif

              {{-- Reviews --}}
              <div class="mt-4">
                <div class="fw-bold mb-3" style="border-bottom:2px solid var(--primary);padding-bottom:.5rem">
                  <i class="bi bi-star-fill me-1" style="color:#fbbf24"></i>
                  Customer Reviews
                  @if($reviewCount > 0)
                    <span class="text-muted fw-normal small ms-1">({{ $reviewCount }})</span>
                  @endif
                </div>

                @if(count($reviews) > 0)
                  <div id="reviewList{{ $p->id }}">
                    @foreach($reviews as $rv)
                    <div class="d-flex gap-3 mb-3 pb-3" style="border-bottom:1px solid #f0f0f0">
                      <div style="flex-shrink:0">
                        @if($rv->profile_photo)
                          <img src="{{ $rv->profile_photo }}" style="width:38px;height:38px;border-radius:50%;object-fit:cover">
                        @else
                          <div style="width:38px;height:38px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.9rem">
                            {{ strtoupper(substr($rv->fullname,0,1)) }}
                          </div>
                        @endif
                      </div>
                      <div class="flex-grow-1">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-1">
                          <span class="fw-semibold small">{{ $rv->fullname }}</span>
                          <span class="text-muted" style="font-size:.72rem">{{ \Carbon\Carbon::parse($rv->created_at)->diffForHumans() }}</span>
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
                  @if($reviewCount > count($reviews))
                    <div class="text-center">
                      <button type="button"
                              class="btn btn-outline-primary btn-sm px-3"
                              id="loadReviewsBtn{{ $p->id }}"
                              data-product-id="{{ $p->id }}"
                              data-offset="{{ count($reviews) }}"
                              data-limit="5"
                              onclick="loadMoreReviews(this)">
                        <i class="bi bi-chat-dots me-1"></i>Load More Reviews
                      </button>
                    </div>
                  @endif
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

    @empty
    <div class="col-12 text-center py-5">
      <i class="bi bi-cake2" style="font-size:3rem;color:#ddd"></i>
      <p class="text-muted mt-3">No products available yet.</p>
    </div>
    @endforelse
  </div>
</div>

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

document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.catalog-detail-modal').forEach(modal => {
    if (modal.parentElement !== document.body) {
      document.body.appendChild(modal);
    }
  });
});

window.addEventListener('pageshow', function(e) {
  if (e.persisted) forceCleanModals();
});
document.addEventListener('hidden.bs.modal', forceCleanModals);

function validateSizeSelection(form) {
  const input = form.querySelector('input[name="selected_size"]');
  const selected = form.querySelector('.size-choice-btn.is-selected');
  if (input && !input.value && selected) {
    input.value = selected.dataset.sizeLabel || '';
  }
  if (!input || input.value) return true;

  const error = form.querySelector('[data-size-error]');
  const picker = form.querySelector('[data-size-picker]');
  if (error) error.classList.remove('d-none');
  if (picker) picker.scrollIntoView({ behavior: 'smooth', block: 'center' });
  return false;
}

function selectModalSize(button) {
  const productId = button.dataset.productId || '';
  const basePrice = parseFloat(button.dataset.basePrice || '0');
  const form = button.closest('form');
  const picker = button.closest('[data-size-picker]');
  const input = form ? form.querySelector('input[name="selected_size"]') : null;
  const error = form ? form.querySelector('[data-size-error]') : null;

  if (input) input.value = button.dataset.sizeLabel || '';
  if (error) error.classList.add('d-none');
  if (picker) {
    picker.querySelectorAll('.size-choice-btn').forEach(btn => {
      btn.classList.remove('is-selected');
      btn.setAttribute('aria-pressed', 'false');
    });
  }
  button.classList.add('is-selected');
  button.setAttribute('aria-pressed', 'true');
  const qtyInput = document.getElementById('qty' + productId);
  const hint = document.getElementById('stockHint' + productId);
  const tracked = button.dataset.stockTracked === '1';
  const stockQty = parseInt(button.dataset.stockQty || '0', 10) || 0;
  if (qtyInput) {
    const max = tracked ? Math.max(1, Math.min(20, stockQty)) : 20;
    qtyInput.setAttribute('max', String(max));
    if ((parseInt(qtyInput.value || '1', 10) || 1) > max) qtyInput.value = max;
  }
  if (hint) {
    hint.classList.remove('d-none');
    hint.style.color = tracked && stockQty <= 3 ? '#92400e' : '#047857';
    hint.innerHTML = '<i class="bi bi-box-seam me-1"></i>' + (tracked ? stockQty + ' cake' + (stockQty === 1 ? '' : 's') + ' available for this size.' : 'This size is available.');
  }
  updateModalPrice(productId, basePrice, button);
}

function toggleSizeOptions(button) {
  const productId = button.dataset.productId || '';
  const picker = document.getElementById('sizeOptions' + productId);
  if (!picker) return;

  const expanded = picker.dataset.expanded === '1';
  picker.querySelectorAll('.is-extra-size').forEach(btn => btn.classList.toggle('d-none', expanded));
  picker.dataset.expanded = expanded ? '0' : '1';
  button.textContent = expanded ? 'View more' : 'View less';
}

function confirmOrder(form) {
  if (!validateSizeSelection(form)) {
    form.dataset.cartSubmit = '';
    return false;
  }
  if (form.dataset.cartSubmit === '1') {
    form.dataset.cartSubmit = '';
    return true;
  }
  const openModal = document.querySelector('.modal.show');
  if (openModal && typeof bootstrap !== 'undefined') {
    const bsModal = bootstrap.Modal.getInstance(openModal);
    if (bsModal) {
      openModal.addEventListener('hidden.bs.modal', () => form.submit(), { once: true });
      bsModal.hide();
      return false;
    }
  }
  form.submit();
  return false;
}

function updateModalPrice(productId, basePrice, priceSource) {
  const selectedOption = priceSource && priceSource.options ? priceSource.options[priceSource.selectedIndex] : null;
  const priceDataset = priceSource && priceSource.dataset ? priceSource.dataset.price : null;
  const optionDataset = selectedOption && selectedOption.dataset ? selectedOption.dataset.price : null;
  const price = priceDataset ? parseFloat(priceDataset) : (optionDataset ? parseFloat(optionDataset) : basePrice);
  const picker = priceSource && priceSource.closest ? priceSource.closest('[data-size-picker]') : null;
  const priceEl = document.getElementById('modalPrice' + productId);
  const discountType = (picker && picker.dataset.discountType) || (priceEl && priceEl.dataset.discountType) || (priceSource && priceSource.dataset ? priceSource.dataset.discountType : '') || '';
  const discountValue = parseFloat((picker && picker.dataset.discountValue) || (priceEl && priceEl.dataset.discountValue) || (priceSource && priceSource.dataset ? priceSource.dataset.discountValue : '0') || '0');
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
  const max = parseInt(input.getAttribute('max') || '20', 10);
  if (val > max) val = max;
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

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, ch => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  }[ch]));
}

function reviewStars(rating) {
  let html = '';
  const score = parseInt(rating || 0, 10);
  for (let i = 1; i <= 5; i++) {
    html += '<i class="bi bi-star' + (i <= score ? '-fill' : '') + '" style="color:#fbbf24;font-size:.78rem"></i>';
  }
  return html;
}

function reviewAvatar(review) {
  if (review.profile_photo) {
    return '<img src="' + escapeHtml(review.profile_photo) + '" style="width:38px;height:38px;border-radius:50%;object-fit:cover">';
  }
  return '<div style="width:38px;height:38px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:clamp(.8rem,1.7vw,.9rem)">' +
    escapeHtml(review.initial || 'C') +
    '</div>';
}

function reviewCardHtml(review) {
  const text = review.review
    ? '<p class="small mb-1 text-muted" style="line-height:1.55">' + escapeHtml(review.review) + '</p>'
    : '';
  const photo = review.image_path
    ? '<img src="' + escapeHtml(review.image_path) + '" data-review-image="' + escapeHtml(review.image_path) + '" alt="Review photo" style="width:80px;height:80px;object-fit:cover;border-radius:.5rem;cursor:pointer;border:2px solid #fce7f3">'
    : '';

  return '<div class="d-flex gap-3 mb-3 pb-3" style="border-bottom:1px solid #f0f0f0">' +
    '<div style="flex-shrink:0">' + reviewAvatar(review) + '</div>' +
    '<div class="flex-grow-1">' +
      '<div class="d-flex align-items-center justify-content-between flex-wrap gap-1">' +
        '<span class="fw-semibold small">' + escapeHtml(review.fullname || 'Customer') + '</span>' +
        '<span class="text-muted" style="font-size:clamp(.68rem,1.3vw,.72rem)">' + escapeHtml(review.created_at || '') + '</span>' +
      '</div>' +
      '<div class="d-flex gap-1 my-1">' + reviewStars(review.rating) + '</div>' +
      text + photo +
    '</div>' +
  '</div>';
}

async function loadMoreReviews(button) {
  const productId = button?.dataset?.productId;
  const list = document.getElementById('reviewList' + productId);
  if (!productId || !list || button.disabled) return;

  const offset = parseInt(button.dataset.offset || '0', 10);
  const limit = parseInt(button.dataset.limit || '5', 10);
  const originalHtml = button.innerHTML;
  button.disabled = true;
  button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Loading';

  try {
    const res = await fetch('/catalog/products/' + encodeURIComponent(productId) + '/reviews?offset=' + offset + '&limit=' + limit, {
      headers: { 'Accept': 'application/json' },
      cache: 'no-store'
    });
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error(data.message || 'Unable to load reviews.');

    list.insertAdjacentHTML('beforeend', (data.reviews || []).map(reviewCardHtml).join(''));
    button.dataset.offset = String(data.next_offset || offset + (data.reviews || []).length);
    if (!data.has_more) button.closest('.text-center')?.remove();
  } catch (e) {
    button.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Try Again';
    button.disabled = false;
    return;
  }

  button.disabled = false;
  button.innerHTML = originalHtml;
}

document.addEventListener('click', function(e) {
  const img = e.target.closest('[data-review-image]');
  if (img) catLbOpen(img.dataset.reviewImage || img.src);
});

</script>

@push('scripts')
@endpush

<script>
function filterCatalog(){
  const q = (document.getElementById('catalogSearch')?.value || '').toLowerCase().trim();
  const classification = (document.getElementById('catalogClassFilter')?.value || '').toLowerCase();
  const seller = (document.getElementById('catalogSellerFilter')?.value || '').toLowerCase();
  const barangay = (document.getElementById('catalogBarangayFilter')?.value || '').toLowerCase();
  let visibleCount = 0;
  let visibleBestSellerCount = 0;

  const matchesCatalogFilters = (el) => {
    const haystack = (el.getAttribute('data-name') || '').toLowerCase();
    const elClass = (el.getAttribute('data-classification') || '').toLowerCase();
    const elSeller = (el.getAttribute('data-seller') || '').toLowerCase();
    const elBarangays = (el.getAttribute('data-barangays') || '').toLowerCase();

    return (!q || haystack.includes(q))
      && (!classification || elClass.includes(classification))
      && (!seller || elSeller.includes(seller))
      && (!barangay || elBarangays.includes(barangay));
  };

  document.querySelectorAll('.catalog-item').forEach(el => {
    const matches = matchesCatalogFilters(el);
    el.style.display = matches ? '' : 'none';
    if (matches) visibleCount++;
  });

  document.querySelectorAll('.best-seller-item').forEach(el => {
    const matches = matchesCatalogFilters(el);
    el.style.display = matches ? '' : 'none';
    if (matches) {
      visibleBestSellerCount++;
      const rank = el.querySelector('.best-seller-rank');
      if (rank) rank.innerHTML = '<i class="bi bi-trophy-fill me-1"></i>Top ' + visibleBestSellerCount;
    }
  });

  const bestSellerSection = document.getElementById('bestSellerSection');
  if (bestSellerSection) {
    bestSellerSection.style.display = visibleBestSellerCount === 0 ? 'none' : '';
  }

  const emptyState = document.getElementById('catalogEmptyState');
  if (emptyState) emptyState.style.display = visibleCount === 0 ? 'block' : 'none';

  const summary = document.getElementById('catalogFilterSummary');
  if (summary) {
    const suffixParts = [];
    if (seller) suffixParts.push('seller "' + document.getElementById('catalogSellerFilter').value + '"');
    if (barangay) suffixParts.push('barangay "' + document.getElementById('catalogBarangayFilter').value + '"');
    const suffix = suffixParts.length ? ' for ' + suffixParts.join(' and ') : '';
    summary.textContent = 'Showing ' + visibleCount + ' of ' + document.querySelectorAll('.catalog-item').length + ' cake options' + suffix;
  }
}

function resetCatalogFilters() {
  ['catalogSearch','catalogClassFilter','catalogSellerFilter','catalogBarangayFilter'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  filterCatalog();
}

function toggleCatalogFilters(open) {
  document.getElementById('catalogFilterPanel')?.classList.toggle('show', open);
  document.getElementById('catalogFilterOverlay')?.classList.toggle('show', open);
  document.body.style.overflow = open ? 'hidden' : '';
}

window.filterCatalog = filterCatalog;
window.resetCatalogFilters = resetCatalogFilters;
</script>


@endsection
