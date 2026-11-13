@extends('layouts.app')
@section('page_title','Products')
@section('content')
<style>
  .seller-product-item{box-shadow:0 3px 12px rgba(15,23,42,.04);transition:box-shadow .18s ease,transform .18s ease}
  .seller-product-item:hover{box-shadow:0 10px 26px rgba(15,23,42,.08);transform:translateY(-1px)}
  .seller-product-summary{cursor:pointer}
  .seller-product-summary:focus-visible{outline:3px solid color-mix(in srgb,var(--primary) 35%,transparent);outline-offset:-3px}
  .seller-product-summary:hover{background:var(--gray-50)}
  .seller-product-open-icon{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:var(--primary-bg);color:var(--primary);flex:0 0 auto}
  .seller-product-modal{position:fixed;inset:0;z-index:1080;display:none;align-items:center;justify-content:center;padding:14px;background:rgba(15,23,42,.58)}
  .seller-product-modal.is-open{display:flex}
  .seller-product-dialog{width:min(720px,100%);max-height:calc(100dvh - 28px);display:flex;flex-direction:column;background:#fff;border-radius:18px;box-shadow:0 24px 70px rgba(15,23,42,.28);overflow:hidden}
  .seller-product-modal-header,.seller-product-modal-footer{display:flex;align-items:center;gap:.75rem;padding:.8rem 1rem;background:#fff;flex:0 0 auto}
  .seller-product-modal-header{border-bottom:1px solid var(--gray-100);justify-content:space-between}
  .seller-product-modal-footer{border-top:1px solid var(--gray-100);justify-content:space-between;flex-wrap:wrap}
  .seller-product-close{border:0;background:var(--gray-100);width:36px;height:36px;border-radius:50%;font-size:1.2rem;color:var(--gray-700)}
  .seller-product-modal-body{padding:1rem;overflow:hidden;min-height:0}
  .seller-product-modal-page{display:none}.seller-product-modal-page.is-active{display:block}
  .seller-size-entry{display:none}.seller-size-entry.is-current{display:block}
  .seller-product-accordion{display:none!important}
  .seller-product-tabs{display:flex;gap:.4rem;padding:.65rem 1rem 0;background:#fff;flex:0 0 auto}
  .seller-product-tab{border:1px solid var(--gray-200);border-radius:999px;background:#fff;padding:.36rem .75rem;font-size:.78rem;font-weight:700;color:var(--gray-600)}
  .seller-product-tab.is-active{color:#fff;background:var(--primary);border-color:var(--primary)}
  .seller-field-error{display:block;min-height:17px;margin-top:.22rem;color:#b91c1c;font-size:.72rem;font-weight:600}
  .seller-field-hint{display:flex;justify-content:flex-end;min-height:17px;margin-top:.22rem;color:var(--gray-500);font-size:.72rem}
  .seller-invalid{border-color:#dc2626!important;box-shadow:0 0 0 .14rem rgba(220,38,38,.12)!important}
  @media(max-width:700px){
    .seller-product-summary{padding:.75rem!important;gap:.65rem!important;align-items:flex-start!important}
    .seller-product-summary>div:first-child{width:52px!important;height:52px!important}
    .seller-product-dialog{border-radius:14px;max-height:calc(100dvh - 16px)}
    .seller-product-modal{padding:8px}
    .seller-product-modal-body{padding:.75rem}
    .seller-product-modal .row{--bs-gutter-y:.65rem}
    #sellerProductSearch{max-width:none!important;width:100%}
  }
  @media(max-width:430px){
    .seller-product-summary{flex-wrap:wrap!important}
    .seller-product-summary>div:nth-child(2){flex-basis:calc(100% - 64px)}
  }
</style>
<div id="sellerProductsRoot"
     data-product-identities='@json($productIdentities ?? [])'
     data-realtime-url="{{ route('seller.products.realtime_status') }}">

{{-- Header --}}
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
  <div>
    <h1 style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:700;color:var(--gray-900);margin:0 0 .25rem">Products</h1>
    <p style="font-size:.875rem;color:var(--gray-500);margin:0">
      {{ $products->total() }} product{{ $products->total() != 1 ? 's' : '' }} in your shop
      @if($maxProd)
        &mdash; <span style="color:{{ $products->total() >= $maxProd ? 'var(--danger,#C62828)' : 'var(--gray-500)' }}">{{ $products->total() }}/{{ $maxProd }} limit (Basic)</span>
      @endif
    </p>
  </div>
  <button type="button" onclick="toggleAddForm()"
          style="background:var(--primary);color:#fff;border:none;border-radius:var(--radius-md);padding:.6rem 1.25rem;font-size:.875rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:.5rem;box-shadow:0 2px 8px rgba(229,57,53,.25)">
    <i class="bi bi-plus-lg"></i> Add Product
  </button>
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
      <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
        <div style="font-size:.95rem;font-weight:700;color:var(--gray-900)">Products</div>
        <div style="display:flex;gap:.35rem">
          <a href="{{ route('seller.products', ['tab'=>'active','search'=>$search]) }}"
             style="padding:.3rem .85rem;border-radius:2rem;font-size:.78rem;font-weight:600;text-decoration:none;border:1.5px solid {{ $tab==='active' ? 'var(--primary)' : 'var(--gray-200)' }};background:{{ $tab==='active' ? 'var(--primary)' : '#fff' }};color:{{ $tab==='active' ? '#fff' : 'var(--gray-600)' }}">
            Active
          </a>
          @if($hasArchivedCol)
          <a href="{{ route('seller.products', ['tab'=>'archived','search'=>$search]) }}"
             style="padding:.3rem .85rem;border-radius:2rem;font-size:.78rem;font-weight:600;text-decoration:none;border:1.5px solid {{ $tab==='archived' ? '#d97706' : 'var(--gray-200)' }};background:{{ $tab==='archived' ? '#d97706' : '#fff' }};color:{{ $tab==='archived' ? '#fff' : 'var(--gray-600)' }};display:flex;align-items:center;gap:.35rem">
            <i class="bi bi-archive"></i> Archived
            @if($archivedCount > 0)
              <span style="background:{{ $tab==='archived' ? 'rgba(255,255,255,.3)' : '#d97706' }};color:#fff;border-radius:99px;font-size:.65rem;padding:0 .4rem;min-width:1.2rem;text-align:center">{{ $archivedCount }}</span>
            @endif
          </a>
          @endif
        </div>
      </div>
      <div style="font-size:.8rem;color:var(--gray-500)">{{ $tab==='archived' ? 'Archived products are hidden from customers' : 'Search by product name, flavor, or category' }}</div>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
      <input type="text" id="sellerProductSearch" class="form-control" placeholder="Search products..."
             style="flex:1;min-width:0;max-width:280px"
             value="{{ $search ?? '' }}"
             oninput="pgSearch(this.value)">
    </div>
  </div>
  <div id="sellerProductFilterSummary" style="font-size:.78rem;color:var(--gray-500);margin-top:.6rem">Showing {{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }} of {{ $products->total() }} products</div>
</div>

{{-- Add Product Form --}}
<div id="addProductForm" style="display:none;background:#fff;border-radius:var(--radius-lg);border:1.5px solid var(--primary-light);padding:1.5rem;margin-bottom:1.5rem;box-shadow:0 4px 24px rgba(229,57,53,.08)">
  <h2 style="font-size:1rem;font-weight:700;color:var(--gray-900);margin:0 0 1.25rem;display:flex;align-items:center;gap:.6rem">
    <i class="bi bi-plus-circle" style="color:var(--primary)"></i> Add New Product
  </h2>
  <form action="{{ route('seller.products.store') }}" method="POST" enctype="multipart/form-data" novalidate>
    @csrf
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Product Name <span style="color:var(--danger)">*</span></label>
        <input type="text" class="form-control" name="name" value="{{ old('name') }}"
               placeholder="e.g. Chocolate Birthday Cake" required minlength="2" maxlength="100"
               oninvalid="this.setCustomValidity('Product name is required (min 2 chars)')"
               oninput="this.setCustomValidity('')">
      </div>
      <div class="col-md-3">
        <label class="form-label">Base Price (₱) <span style="color:var(--danger)">*</span></label>
        <input type="number" class="form-control" name="price" value="{{ old('price') }}"
               step="0.01" min="1" placeholder="e.g. 599.00" required
               oninvalid="this.setCustomValidity('Price must be at least ₱1')"
               oninput="this.setCustomValidity('')">
      </div>
      <div class="col-md-3">
        <label class="form-label">Classification <span style="color:var(--danger)">*</span></label>
        <select class="form-select" name="classification" required
                oninvalid="this.setCustomValidity('Please select a classification')"
                onchange="this.setCustomValidity('')">
          <option value="">Select...</option>
          @foreach(['Birthday','Wedding','Anniversary','Custom','Cupcakes','Pastries','Seasonal','Standard'] as $cls)
            <option value="{{ $cls }}" {{ old('classification')===$cls ? 'selected' : '' }}>{{ $cls }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Description <span style="color:var(--gray-400);font-weight:400">(optional)</span></label>
        <textarea class="form-control" name="description" rows="2" maxlength="500"
                  placeholder="Describe your product...">{{ old('description') }}</textarea>
        <small class="seller-field-hint" data-character-counter="description" data-character-limit="500"></small>
      </div>
      <div class="col-md-3">
        <label class="form-label">Flavor <span style="color:var(--gray-400);font-weight:400">(optional)</span></label>
        <input type="text" class="form-control" name="flavor" value="{{ old('flavor') }}"
               placeholder="e.g. Chocolate, Ube, Red Velvet" maxlength="100">
        <small class="seller-field-hint" data-character-counter="flavor" data-character-limit="100"></small>
      </div>
      <div class="col-md-3">
        <label class="form-label">Available Cakes <span style="color:var(--gray-400);font-weight:400">(blank = 0)</span></label>
        <input type="number" class="form-control" name="available_quantity" value="{{ old('available_quantity') }}"
               min="0" max="9999" step="1" placeholder="e.g. 12">
        <div style="font-size:.72rem;color:var(--gray-500);margin-top:.25rem">Leave blank to save 0 and show out of stock.</div>
      </div>
      <div class="col-md-3">
        <label class="form-label">Product Photo <span style="color:var(--gray-400);font-weight:400">(optional)</span></label>
        <input type="file" class="form-control" name="image" accept=".jpg,.jpeg,.png,.webp"
               onchange="previewImg(this,'addPreview')">
        <img id="addPreview" style="display:none;max-height:80px;margin-top:.5rem;border-radius:var(--radius-sm);object-fit:cover">
      </div>
    </div>
    <div style="display:flex;gap:.75rem;margin-top:1.25rem">
      <button type="submit" class="btn btn-primary" style="padding:.6rem 1.5rem;font-weight:600">
        Add Product
      </button>
      <button type="button" class="btn btn-secondary" onclick="toggleAddForm()">Cancel</button>
    </div>
  </form>
</div>

{{-- Products Grid --}}
@forelse($products as $p)
@php
  $sizes = collect($productSizes[$p->id] ?? []);
  $discount = $discounts[$p->id] ?? null;
  $discountBadge = $discount ? \App\Helpers\CakeshopHelper::discountBadgeText($discount->discount_type ?? null, $discount->discount_value ?? null) : null;
  $sweetDealBadge = ($discount && property_exists($discount, 'deal_badge_label')) ? trim((string) ($discount->deal_badge_label ?? '')) : '';
  $sweetDealNote = ($discount && property_exists($discount, 'deal_note')) ? trim((string) ($discount->deal_note ?? '')) : '';
  $sweetDealQty = ($discount && property_exists($discount, 'deal_quantity_limit')) ? (int) ($discount->deal_quantity_limit ?? 0) : 0;
  $hasActiveSizes = $sizes->isNotEmpty();
  $stockTracked = $hasActiveSizes || property_exists($p, 'available_quantity');
  $stockQty = $hasActiveSizes
    ? max(0, (int) $sizes->sum(fn ($size) => (int) ($size->available_quantity ?? 0)))
    : max(0, (int) ($p->available_quantity ?? 0));
  $stockLabel = $stockQty <= 0
    ? 'Out of stock'
    : ($hasActiveSizes ? $stockQty . ' available across sizes' : $stockQty . ' available');
  $realtimeVersion = collect($productRealtimeStates ?? [])->firstWhere('id', (string) $p->id)['version'] ?? '';
@endphp
<div class="seller-product-item"
     id="sellerProduct-{{ $p->id }}"
     data-product-id="{{ $p->id }}"
     data-realtime-version="{{ $realtimeVersion }}"
     data-search="{{ strtolower(trim($p->name . ' ' . ($p->description ?? '') . ' ' . ($p->flavor ?? '') . ' ' . ($p->classification ?? ''))) }}"
     data-classification="{{ strtolower($p->classification ?? '') }}"
     data-status="{{ $p->is_available ? 'visible' : 'hidden' }}"
     style="background:#fff;border-radius:var(--radius-lg);border:1.5px solid {{ ($hasArchivedCol && $p->archived_at) ? '#fcd34d' : ($p->is_available ? 'var(--gray-100)' : 'var(--gray-200)') }};margin-bottom:1rem;overflow:hidden;opacity:{{ ($hasArchivedCol && $p->archived_at) ? '.75' : ($p->is_available ? '1' : '.65') }}">
   <div class="seller-product-summary" style="display:flex;align-items:center;gap:1rem;padding:.75rem 1.25rem;flex-wrap:nowrap" role="button" tabindex="0" aria-haspopup="dialog" onclick="openProductModal('{{ $p->id }}','details')" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openProductModal('{{ $p->id }}','details')}">

    {{-- Product Image --}}
    <div style="width:72px;height:72px;border-radius:var(--radius-md);overflow:hidden;flex-shrink:0;background:var(--gray-100)">
      @if($p->image_path)
        <img src="{{ $p->image_path }}" style="width:100%;height:100%;object-fit:cover" alt=""
             onerror="this.parentElement.style.background='var(--primary-bg)'">
      @else
        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center">
          <i class="bi bi-cake2" style="font-size:1.5rem;color:var(--gray-400)"></i>
        </div>
      @endif
    </div>

    {{-- Info --}}
    <div style="flex:1;min-width:0">
      <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
        <span style="font-size:.95rem;font-weight:700;color:var(--gray-900)">{{ $p->name }}</span>
        @if($hasArchivedCol && $p->archived_at)
          <span style="background:#fffbeb;color:#92400e;border:1px solid #fcd34d;font-size:.68rem;font-weight:700;padding:.15rem .5rem;border-radius:99px;display:inline-flex;align-items:center;gap:.25rem"><i class="bi bi-archive-fill"></i> Archived</span>
        @elseif(!$p->is_available)
          <span style="background:var(--gray-200);color:var(--gray-600);font-size:.68rem;font-weight:700;padding:.15rem .5rem;border-radius:99px">Hidden</span>
        @endif
        <span style="background:var(--primary-bg);color:var(--primary);font-size:.68rem;font-weight:600;padding:.15rem .5rem;border-radius:99px">{{ $p->classification }}</span>
        @if($stockTracked)
          <span data-product-stock-badge style="background:{{ $stockQty <= 0 ? '#fef2f2' : ($stockQty <= 3 ? '#fffbeb' : '#ecfdf5') }};color:{{ $stockQty <= 0 ? '#b91c1c' : ($stockQty <= 3 ? '#92400e' : '#047857') }};border:1px solid {{ $stockQty <= 0 ? '#fecaca' : ($stockQty <= 3 ? '#fde68a' : '#a7f3d0') }};font-size:.68rem;font-weight:700;padding:.15rem .5rem;border-radius:99px;display:inline-flex;align-items:center;gap:.25rem">
            <i class="bi {{ $stockQty <= 0 ? 'bi-exclamation-circle' : 'bi-box-seam' }}"></i> {{ $stockLabel }}
          </span>
        @else
          <span style="background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;font-size:.68rem;font-weight:700;padding:.15rem .5rem;border-radius:99px"><i class="bi bi-exclamation-circle"></i> Out of stock</span>
        @endif
      </div>
      <div style="font-size:.875rem;font-weight:700;color:var(--primary);margin:.2rem 0">₱{{ number_format($p->price,2) }} base</div>
      @if($discountBadge)
        <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;margin-bottom:.2rem">
          @if($sweetDealBadge)
            <span style="background:linear-gradient(135deg,#fff7ed,#ffe4e6);color:#9f1239;border:1px solid #fecdd3;font-size:.68rem;font-weight:800;padding:.18rem .55rem;border-radius:99px;box-shadow:0 6px 14px rgba(244,63,94,.12)">
              <i class="bi bi-stars me-1"></i>{{ $sweetDealBadge }}
            </span>
          @endif
          <span style="background:#fff1f2;color:#be123c;font-size:.68rem;font-weight:700;padding:.15rem .5rem;border-radius:99px">
            {{ $discountBadge }}
          </span>
          @if($sweetDealNote)
            <span style="font-size:.7rem;color:#9f1239;font-weight:600">{{ $sweetDealNote }}</span>
          @elseif(!empty($discount->label))
            <span style="font-size:.7rem;color:var(--gray-500)">{{ $discount->label }}</span>
          @endif
          @if($sweetDealQty > 0)
            <span style="font-size:.68rem;color:#64748b;background:#f8fafc;border:1px solid #e2e8f0;padding:.12rem .45rem;border-radius:99px">{{ $sweetDealQty }} deal pcs</span>
          @endif
        </div>
      @elseif(!empty($discount))
        <div style="font-size:.7rem;color:var(--gray-500);margin-bottom:.2rem">Discount saved but currently disabled.</div>
      @endif
      <div style="font-size:.75rem;color:var(--gray-500)">
        @if($p->flavor)<span style="margin-right:.75rem"><i class="bi bi-droplet" style="font-size:.7rem"></i> {{ $p->flavor }}</span>@endif
        @if($sizes->count() > 0)<span style="margin-right:.75rem">{{ $sizes->count() }} size{{ $sizes->count() > 1 ? 's' : '' }}</span>@endif
        @if($hasArchivedCol && $p->archived_at)<span style="color:#d97706"><i class="bi bi-calendar-x me-1" style="font-size:.7rem"></i>Archived {{ \Carbon\Carbon::parse($p->archived_at)->diffForHumans() }}</span>@endif
      </div>
    </div>

    {{-- Open popup --}}
    <div class="seller-product-open-icon" aria-hidden="true">
      <i class="bi bi-chevron-right"></i>
      @if(false && $hasArchivedCol && $p->archived_at)
        {{-- Archived: show Restore only --}}
        <form action="{{ route('seller.products.restore', $p->id) }}" method="POST" class="d-inline archive-action"
              data-cs-confirm="Restore &quot;{{ addslashes($p->name) }}&quot;? Customers can order it again." data-cs-title="Restore Product" data-cs-icon="bi-arrow-counterclockwise" data-cs-icon-bg="#ecfdf5" data-cs-icon-color="#059669" data-cs-ok="Restore" data-cs-ok-color="#059669">
          @csrf
          <button type="submit" style="background:#ecfdf5;color:#059669;border:1.5px solid #6ee7b7;border-radius:var(--radius-md);padding:.35rem .875rem;font-size:.78rem;font-weight:600;cursor:pointer">
            <i class="bi bi-arrow-counterclockwise"></i> Restore
          </button>
        </form>
      @elseif(false)
        {{-- Active: show Archive --}}
        <form action="{{ route('seller.products.archive', $p->id) }}" method="POST" class="d-inline archive-action"
              data-cs-confirm="Archive &quot;{{ addslashes($p->name) }}&quot;? It will show as Out of Stock to customers." data-cs-title="Archive Product" data-cs-icon="bi-archive" data-cs-icon-bg="#fffbeb" data-cs-icon-color="#d97706" data-cs-ok="Archive" data-cs-ok-color="#d97706">
          @csrf
          <button type="submit" style="background:#fffbeb;color:#92400e;border:1.5px solid #fcd34d;border-radius:var(--radius-md);padding:.35rem .875rem;font-size:.78rem;font-weight:600;cursor:pointer">
            <i class="bi bi-archive"></i> Archive
          </button>
        </form>
      @endif
    </div>
  </div>

  <div class="seller-product-accordion" id="productDetails-{{ $p->id }}" hidden>
  <div class="seller-product-accordion-inner">
  {{-- Active Sizes --}}
  @php $archivedSz = collect($archivedSizes[$p->id] ?? []); @endphp
  @if($sizes->count() > 0 || $archivedSz->count() > 0)
  <div style="border-top:1px solid var(--gray-100);padding:.625rem 1.25rem;background:var(--gray-50)">
    @if($sizes->count() > 0)
    <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center">
      <span style="font-size:.72rem;font-weight:700;color:var(--gray-500);margin-right:.25rem">SIZES:</span>
      @foreach($sizes as $sz)
      <span data-size-label="{{ strtolower(trim($sz->label)) }}" style="background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-sm);padding:.2rem .6rem;font-size:.75rem;color:var(--gray-700);display:inline-flex;align-items:center;gap:.4rem">
        <span>{{ $sz->label }} - PHP {{ number_format($sz->price,2) }}</span>
        <span style="color:{{ (int)$sz->available_quantity <= 0 ? '#b91c1c' : ((int)$sz->available_quantity <= 3 ? '#92400e' : '#047857') }}">
          {{ 'Stock: '.max(0, (int)$sz->available_quantity) }}
        </span>
        <form action="{{ route('seller.products.sizes.stock', $sz->id) }}" method="POST" class="d-inline-flex align-items-center gap-1">
          @csrf
          <input type="number" name="available_quantity" value="{{ $sz->available_quantity }}" min="0" max="9999" step="1" placeholder="0" title="Size stock" style="width:72px;border:1px solid var(--gray-200);border-radius:6px;padding:.12rem .35rem;font-size:.72rem">
          <button type="submit" title="Save size stock" style="background:none;border:none;color:var(--primary);cursor:pointer;padding:0;font-size:.78rem;line-height:1"><i class="bi bi-check2-circle"></i></button>
        </form>
        <form action="{{ route('seller.products.sizes.archive', $sz->id) }}" method="POST" class="d-inline"
              onsubmit="return false;" onclick="confirmDelete('Archive size &quot;{{ addslashes($sz->label) }}&quot;? It will be hidden and can be restored anytime.', () => this.closest('form').submit())">
          @csrf
          <button type="submit" title="Archive size" style="background:none;border:none;color:var(--gray-400);cursor:pointer;padding:0;font-size:.75rem;line-height:1" onmouseover="this.style.color='#d97706'" onmouseout="this.style.color='var(--gray-400)'">
            <i class="bi bi-archive"></i>
          </button>
        </form>
      </span>
      @endforeach
    </div>
    @endif
    @if($archivedSz->count() > 0)
    <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin-top:.4rem">
      <span style="font-size:.72rem;font-weight:700;color:#d97706;margin-right:.25rem">ARCHIVED SIZES:</span>
      @foreach($archivedSz as $sz)
      <span data-size-label="{{ strtolower(trim($sz->label)) }}" style="background:#fffbeb;border:1px solid #fcd34d;border-radius:var(--radius-sm);padding:.2rem .6rem;font-size:.75rem;color:#92400e;display:inline-flex;align-items:center;gap:.4rem;opacity:.8">
        <span>{{ $sz->label }} - PHP {{ number_format($sz->price,2) }}</span>
        <span style="color:{{ (int)$sz->available_quantity <= 0 ? '#b91c1c' : ((int)$sz->available_quantity <= 3 ? '#92400e' : '#047857') }}">
          {{ 'Stock: '.max(0, (int)$sz->available_quantity) }}
        </span>
        <form action="{{ route('seller.products.sizes.restore', $sz->id) }}" method="POST" class="d-inline">
          @csrf
          <button type="submit" title="Restore size" style="background:none;border:none;color:#059669;cursor:pointer;padding:0;font-size:.75rem;line-height:1">
            <i class="bi bi-arrow-counterclockwise"></i>
          </button>
        </form>
      </span>
      @endforeach
    </div>
    @endif
  </div>
  @endif
  <div style="padding:.65rem 1.25rem;background:var(--gray-50);font-size:.78rem;color:var(--gray-600);display:flex;justify-content:space-between;gap:.75rem;align-items:center;flex-wrap:wrap">
    <span>Open <strong>Manage</strong> to edit product details, sizes, or stock.</span>
    <button type="button" class="btn btn-sm btn-primary" onclick="openProductModal('{{ $p->id }}','details')"><i class="bi bi-pencil me-1"></i>Manage product</button>
  </div>
  </div>
  </div>

  <div class="seller-product-modal" id="productModal-{{ $p->id }}" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="productModalTitle-{{ $p->id }}" onclick="if(event.target===this) closeProductModal('{{ $p->id }}')">
    <div class="seller-product-dialog">
      <div class="seller-product-modal-header">
        <div style="min-width:0"><div id="productModalTitle-{{ $p->id }}" style="font-weight:800;color:var(--gray-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $p->name }}</div><div style="font-size:.72rem;color:var(--gray-500)">Edit one product at a time</div></div>
        <button type="button" class="seller-product-close" aria-label="Close product editor" onclick="closeProductModal('{{ $p->id }}')">&times;</button>
      </div>
      <div class="seller-product-tabs" role="tablist">
        <button type="button" class="seller-product-tab" data-product-tab="details" onclick="setProductModalPage('{{ $p->id }}','details')">Details</button>
        <button type="button" class="seller-product-tab" data-product-tab="sizes" onclick="setProductModalPage('{{ $p->id }}','sizes')">Sizes &amp; Stock</button>
        <button type="button" class="seller-product-tab" data-product-tab="discount" onclick="setProductModalPage('{{ $p->id }}','discount')">Discount</button>
      </div>
      <div class="seller-product-modal-body">
  <div id="discount-{{ $p->id }}" class="seller-product-modal-page" data-product-page="discount">
    <form action="{{ route('seller.products.discount', $p->id) }}" method="POST" class="seller-discount-form" data-product-price="{{ (float) $p->price }}" data-product-stock="{{ $stockTracked ? $stockQty : '' }}" onsubmit="return validateSellerDiscountForm(this, true)">
      @csrf
      <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
        <div>
          <div style="font-size:.92rem;font-weight:700;color:var(--gray-900)">Manage Product Discount</div>
          <div style="font-size:.75rem;color:var(--gray-500)">Discount applies to the actual checkout unit price, including selected size pricing.</div>
        </div>
        <label style="display:inline-flex;align-items:center;gap:.5rem;font-size:.8rem;font-weight:600;color:var(--gray-700)">
          <input type="checkbox" name="discount_enabled" value="1" {{ ($discount->is_active ?? 0) ? 'checked' : '' }} onchange="validateSellerDiscountForm(this.form, false)">
          Enable discount
        </label>
      </div>

      <div class="row g-3">
        <input type="hidden" name="discount_label" value="">
        <div class="col-md-3">
          <label class="form-label">Discount Type</label>
          <select class="form-select" name="discount_type" onchange="validateSellerDiscountForm(this.form, false)">
            <option value="percent" {{ ($discount->discount_type ?? 'percent') === 'percent' ? 'selected' : '' }}>Percentage</option>
            <option value="fixed" {{ ($discount->discount_type ?? '') === 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Value</label>
          <input type="number" step="0.01" min="0" max="{{ ($discount->discount_type ?? 'percent') === 'fixed' ? (float) $p->price : 100 }}" class="form-control" name="discount_value" value="{{ $discount->discount_value ?? '' }}" placeholder="20" oninput="validateSellerDiscountForm(this.form, false)">
          <div class="small mt-1 discount-value-feedback" style="min-height:16px;color:#dc2626"></div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Start</label>
          <input type="datetime-local" class="form-control" name="discount_starts_at" onchange="validateSellerDiscountForm(this.form, false)" value="{{ !empty($discount->starts_at) ? \Carbon\Carbon::parse($discount->starts_at)->format('Y-m-d\TH:i') : '' }}">
        </div>
        <div class="col-md-3">
          <label class="form-label">End</label>
          <input type="datetime-local" class="form-control" name="discount_ends_at" onchange="validateSellerDiscountForm(this.form, false)" value="{{ !empty($discount->ends_at) ? \Carbon\Carbon::parse($discount->ends_at)->format('Y-m-d\TH:i') : '' }}">
        </div>
      </div>

      @php
        $savedSweetBadge = ($discount && property_exists($discount, 'deal_badge_label')) ? (string) ($discount->deal_badge_label ?? '') : '';
        $sweetBadgeOptions = ["Today's Sweet Deal", "Baker's Pick Deal", "Limited Sweet Deal", "Sweet Saver", "Fresh Pick Promo"];
      @endphp
      <div class="mt-3 p-3 rounded-3" style="background:#fff;border:1px solid #fed7aa;box-shadow:0 10px 22px rgba(154,52,18,.05)">
        <div class="d-flex align-items-start gap-2 mb-3">
          <div style="width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#fb7185,#f97316);color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="bi bi-stars"></i>
          </div>
          <div>
            <div style="font-size:.9rem;font-weight:800;color:#7c2d12">Sweet Deal Display</div>
            <div style="font-size:.74rem;color:var(--gray-500)">Choose the one clear badge customers will see on the catalog and cart.</div>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Customer Badge</label>
            <select class="form-select" name="deal_badge_label">
              <option value="">No special badge</option>
              @foreach($sweetBadgeOptions as $option)
                <option value="{{ $option }}" {{ $savedSweetBadge === $option ? 'selected' : '' }}>{{ $option }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Friendly Note</label>
            <input type="text" class="form-control" name="deal_note" maxlength="120" value="{{ ($discount && property_exists($discount, 'deal_note')) ? ($discount->deal_note ?? '') : '' }}" placeholder="e.g. Fresh today">
          </div>
          <div class="col-md-3">
            <label class="form-label">Fresh Until</label>
            <input type="datetime-local" class="form-control" name="best_enjoyed_by" value="{{ ($discount && property_exists($discount, 'best_enjoyed_by') && !empty($discount->best_enjoyed_by)) ? \Carbon\Carbon::parse($discount->best_enjoyed_by)->format('Y-m-d\TH:i') : '' }}" onchange="validateSellerDiscountForm(this.form, false)">
          </div>
          <div class="col-md-2">
            <label class="form-label">Deal Pcs</label>
            <input type="number" min="0" max="{{ $stockTracked ? $stockQty : 9999 }}" step="1" class="form-control" name="deal_quantity_limit" value="{{ ($discount && property_exists($discount, 'deal_quantity_limit') && $discount->deal_quantity_limit !== null) ? (int) $discount->deal_quantity_limit : '' }}" placeholder="Auto" oninput="validateSellerDiscountForm(this.form, false)">
            <div class="small mt-1 deal-pcs-feedback" style="min-height:16px;color:#dc2626"></div>
          </div>
        </div>
        <div class="small mt-2 discount-date-feedback" style="min-height:16px;color:#dc2626"></div>
        <div class="mt-2" style="font-size:.72rem;color:#64748b">
          <i class="bi bi-info-circle me-1"></i>Pick one customer badge, then add a short friendly note only if needed. The save amount still comes from the discount value.
        </div>
      </div>

      <div style="display:flex;gap:.75rem;margin-top:1rem">
        <button type="submit" class="btn btn-primary" style="padding:.5rem 1.25rem;font-weight:600">Save Discount</button>
        <button type="button" onclick="closeProductModal('{{ $p->id }}')" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
  <div id="edit-{{ $p->id }}" class="seller-product-modal-page" data-product-page="details">
    <form action="{{ route('seller.products.update', $p->id) }}" method="POST" enctype="multipart/form-data" novalidate>
      @csrf
      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label">Name <span style="color:var(--danger)">*</span></label>
          <input type="text" class="form-control" name="name" value="{{ $p->name }}" required minlength="2"
                 oninvalid="this.setCustomValidity('Name is required')" oninput="this.setCustomValidity('')">
        </div>
        <div class="col-md-3">
          <label class="form-label">Price (₱) <span style="color:var(--danger)">*</span></label>
          <input type="number" class="form-control" name="price" value="{{ $p->price }}" step="0.01" min="1" required
                 oninvalid="this.setCustomValidity('Price must be ₱1 or more')" oninput="this.setCustomValidity('')">
        </div>
        <div class="col-md-4">
          <label class="form-label">Classification <span style="color:var(--danger)">*</span></label>
          <select class="form-select" name="classification" required>
            @foreach(['Birthday','Wedding','Anniversary','Custom','Cupcakes','Pastries','Seasonal','Standard'] as $cls)
              <option value="{{ $cls }}" {{ $p->classification===$cls ? 'selected' : '' }}>{{ $cls }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-5">
          <label class="form-label">Description</label>
          <textarea class="form-control" name="description" rows="2" maxlength="500">{{ $p->description }}</textarea>
          <small class="seller-field-hint" data-character-counter="description" data-character-limit="500"></small>
        </div>
        <div class="col-md-3">
          <label class="form-label">Flavor</label>
          <input type="text" class="form-control" name="flavor" value="{{ $p->flavor }}" maxlength="100">
          <small class="seller-field-hint" data-character-counter="flavor" data-character-limit="100"></small>
        </div>
        <div class="col-md-3">
          <label class="form-label">Available Cakes</label>
          <input type="number" class="form-control" name="available_quantity" value="{{ $p->available_quantity }}" min="0" max="9999" step="1" placeholder="0">
          <div style="font-size:.72rem;color:var(--gray-500);margin-top:.25rem">Set 0 to show out of stock.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Replace Photo</label>
          <input type="file" class="form-control" name="image" accept=".jpg,.jpeg,.png,.webp"
                 onchange="previewImg(this,'editPreview{{ $p->id }}')">
          @if($p->image_path)
            <img id="editPreview{{ $p->id }}" src="{{ $p->image_path }}"
                 style="max-height:60px;margin-top:.4rem;border-radius:var(--radius-sm);object-fit:cover">
          @else
            <img id="editPreview{{ $p->id }}" style="display:none;max-height:60px;margin-top:.4rem;border-radius:var(--radius-sm)">
          @endif
        </div>
      </div>

      <div style="display:flex;gap:.75rem;margin-top:1rem">
        <button type="submit" class="btn btn-primary" style="padding:.5rem 1.25rem;font-weight:600">Save Changes</button>
        <button type="button" onclick="closeProductModal('{{ $p->id }}')" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
   </div>
   <div class="seller-product-modal-page" data-product-page="sizes" data-size-editor id="sizeEditor-{{ $p->id }}">
     <div style="font-size:.92rem;font-weight:800;color:var(--gray-900);margin-bottom:.2rem">Sizes &amp; stock</div>
     <p style="font-size:.74rem;color:var(--gray-500);margin-bottom:.75rem">Edit one size at a time. Use the arrows to move between sizes.</p>
     @forelse($sizes as $sz)
       <div class="seller-size-entry" data-size-entry>
         <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.75rem;border:1px solid var(--gray-200);border-radius:12px;background:#fafafa">
           <div><div style="font-weight:800;color:var(--gray-900)">{{ $sz->label }}</div><div data-size-price-display="{{ $sz->id }}" style="font-size:.76rem;color:var(--gray-500)">PHP {{ number_format($sz->price,2) }}</div></div>
           <form action="{{ route('seller.products.sizes.update', $sz->id) }}" method="POST" data-size-stock-form data-size-autosave data-size-id="{{ $sz->id }}" style="display:flex;align-items:end;gap:.45rem;flex-wrap:wrap">@csrf<div><label class="form-label" style="font-size:.7rem;margin-bottom:.2rem">Price</label><input class="form-control" type="number" name="price" value="{{ $sz->price }}" min="1" max="99999999.99" step="0.01" inputmode="decimal" style="width:108px"><small class="seller-field-error" data-field-feedback="price"></small></div><div><label class="form-label" style="font-size:.7rem;margin-bottom:.2rem">Stock</label><input class="form-control" type="number" name="available_quantity" value="{{ $sz->available_quantity }}" min="0" max="9999" step="1" style="width:94px"><small class="seller-field-error" data-field-feedback="available_quantity"></small></div><span data-autosave-status class="small text-muted align-self-center" role="status" aria-live="polite"></span></form>
         </div>
         <form action="{{ route('seller.products.sizes.archive', $sz->id) }}" method="POST" data-cs-confirm="Archive size &quot;{{ addslashes($sz->label) }}&quot;? It can be restored later." data-cs-title="Archive Size" style="margin-top:.6rem;text-align:right">@csrf<button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-archive me-1"></i>Archive size</button></form>
       </div>
     @empty
       <div class="seller-size-entry" data-size-entry><div class="alert alert-light border mb-0">No sizes yet. Add the first size below.</div></div>
     @endforelse
     <div style="display:flex;justify-content:space-between;align-items:center;margin:.7rem 0"><button type="button" class="btn btn-sm btn-secondary" onclick="moveSizeEntry('{{ $p->id }}',-1)"><i class="bi bi-chevron-left"></i> Previous</button><span class="seller-size-position" style="font-size:.74rem;color:var(--gray-500)"></span><button type="button" class="btn btn-sm btn-secondary" onclick="moveSizeEntry('{{ $p->id }}',1)">Next <i class="bi bi-chevron-right"></i></button></div>
     <div style="padding-top:.75rem;border-top:1px dashed var(--gray-300)"><div style="font-size:.8rem;font-weight:800;margin-bottom:.45rem">Add a size</div><div class="row g-2"><div class="col-5"><input type="text" class="form-control" name="new_size_label" maxlength="50" placeholder="e.g. 6-inch"><small class="seller-field-error" data-field-feedback="new_size_label"></small></div><div class="col-4"><input type="number" class="form-control" name="new_size_price" placeholder="Price" step="0.01" min="1"><small class="seller-field-error" data-field-feedback="new_size_price"></small></div><div class="col-3"><input type="number" class="form-control" name="new_size_available_quantity" placeholder="Stock" min="0" max="9999" step="1"><small class="seller-field-error" data-field-feedback="new_size_available_quantity"></small></div></div><button type="button" class="btn btn-primary btn-sm mt-2" data-add-size-button onclick="submitSize('{{ $p->id }}')"><i class="bi bi-plus-lg me-1"></i>Add Size</button></div>
   </div>
       </div>
      <div class="seller-product-modal-footer">
        <small style="color:var(--gray-500)"><i class="bi bi-info-circle me-1"></i>Changes are validated before saving.</small>
        @if($hasArchivedCol && $p->archived_at)
          <form action="{{ route('seller.products.restore', $p->id) }}" method="POST" data-cs-confirm="Restore &quot;{{ addslashes($p->name) }}&quot;? Customers can order it again." data-cs-title="Restore Product" data-cs-ok="Restore">@csrf<button type="submit" class="btn btn-sm" style="background:#ecfdf5;color:#047857;border:1px solid #6ee7b7"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore</button></form>
        @else
          <form action="{{ route('seller.products.archive', $p->id) }}" method="POST" data-cs-confirm="Archive &quot;{{ addslashes($p->name) }}&quot;? It will be hidden from customers." data-cs-title="Archive Product" data-cs-ok="Archive" data-cs-ok-color="#d97706">@csrf<button type="submit" class="btn btn-sm" style="background:#fffbeb;color:#92400e;border:1px solid #fcd34d"><i class="bi bi-archive me-1"></i>Archive</button></form>
        @endif
      </div>
    </div>
  </div>
</div>
@empty
<div style="background:#fff;border-radius:var(--radius-lg);border:1.5px dashed var(--gray-300);padding:4rem;text-align:center">
  @if($tab === 'archived')
    <i class="bi bi-archive" style="font-size:2.5rem;color:#fcd34d;display:block;margin-bottom:1rem"></i>
    <h3 style="font-size:1rem;font-weight:700;color:var(--gray-900);margin:0 0 .5rem">No archived products</h3>
    <p style="font-size:.875rem;color:var(--gray-500);margin:0">Products you archive will appear here.</p>
  @else
    <i class="bi bi-cake2" style="font-size:2.5rem;color:var(--gray-300);display:block;margin-bottom:1rem"></i>
    <h3 style="font-size:1rem;font-weight:700;color:var(--gray-900);margin:0 0 .5rem">No products yet</h3>
    <p style="font-size:.875rem;color:var(--gray-500);margin:0 0 1.5rem">Add your first product to start receiving orders.</p>
    <button onclick="toggleAddForm()" class="btn btn-primary" style="padding:.6rem 1.5rem;font-weight:600">
      <i class="bi bi-plus-lg me-1"></i> Add Product
    </button>
  @endif
</div>
@endforelse

{{ $products->links('vendor.pagination.custom') }}

</div>
<script>
function toggleAddForm() {
  const f = document.getElementById('addProductForm');
  f.style.display = f.style.display === 'none' ? 'block' : 'none';
  if (f.style.display === 'block') f.scrollIntoView({behavior:'smooth',block:'start'});
}
let activeSellerProductModal = null;
let sellerProductModalTrigger = null;

function toggleProductAccordion(id, event) {
  if (event?.target?.closest('button,input,select,textarea,form,a')) return;
  const item = document.getElementById('sellerProduct-' + id);
  const panel = document.getElementById('productDetails-' + id);
  if (!item || !panel) return;
  const opening = !item.classList.contains('is-open');
  document.querySelectorAll('.seller-product-item.is-open').forEach(other => {
    if (other === item) return;
    other.classList.remove('is-open');
    other.querySelector('.seller-product-summary')?.setAttribute('aria-expanded', 'false');
    const otherPanel = other.querySelector('.seller-product-accordion');
    if (otherPanel) { otherPanel.classList.remove('is-open'); setTimeout(() => { if (!other.classList.contains('is-open')) otherPanel.hidden = true; }, 220); }
  });
  panel.hidden = false;
  requestAnimationFrame(() => panel.classList.toggle('is-open', opening));
  item.classList.toggle('is-open', opening);
  item.querySelector('.seller-product-summary')?.setAttribute('aria-expanded', String(opening));
  if (!opening) setTimeout(() => { if (!item.classList.contains('is-open')) panel.hidden = true; }, 220);
}

function openProductModal(id, page = 'details') {
  const modal = document.getElementById('productModal-' + id);
  if (!modal) return;
  sellerProductModalTrigger = document.activeElement;
  if (modal.parentElement !== document.body) document.body.appendChild(modal);
  activeSellerProductModal = modal;
  modal.classList.add('is-open');
  modal.setAttribute('aria-hidden', 'false');
  document.documentElement.style.overflow = 'hidden';
  document.body.style.overflow = 'hidden';
  setProductModalPage(id, page);
  setTimeout(() => modal.querySelector('.seller-product-close')?.focus(), 0);
}

function closeProductModal(id) {
  const modal = document.getElementById('productModal-' + id);
  if (!modal) return;
  modal.classList.remove('is-open');
  modal.setAttribute('aria-hidden', 'true');
  if (activeSellerProductModal === modal) {
    activeSellerProductModal = null;
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
  }
  sellerProductModalTrigger?.focus?.();
}

function setProductModalPage(id, page) {
  const modal = document.getElementById('productModal-' + id);
  if (!modal) return;
  modal.querySelectorAll('[data-product-page]').forEach(panel => panel.classList.toggle('is-active', panel.dataset.productPage === page));
  modal.querySelectorAll('[data-product-tab]').forEach(tab => {
    const selected = tab.dataset.productTab === page;
    tab.classList.toggle('is-active', selected);
    tab.setAttribute('aria-selected', String(selected));
  });
  if (page === 'sizes') showSizeEntry(modal, 0);
}

function showSizeEntry(modal, requestedIndex) {
  const entries = [...modal.querySelectorAll('[data-size-entry]')];
  if (!entries.length) return;
  const current = Number(modal.dataset.sizeIndex || 0);
  const index = Math.max(0, Math.min(entries.length - 1, Number.isFinite(requestedIndex) ? requestedIndex : current));
  modal.dataset.sizeIndex = String(index);
  entries.forEach((entry, itemIndex) => entry.classList.toggle('is-current', itemIndex === index));
  const position = modal.querySelector('.seller-size-position');
  if (position) position.textContent = `${index + 1} of ${entries.length}`;
}

function moveSizeEntry(productId, direction) {
  const modal = document.getElementById('productModal-' + productId);
  if (!modal) return;
  showSizeEntry(modal, Number(modal.dataset.sizeIndex || 0) + direction);
}

document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && activeSellerProductModal) closeProductModal(activeSellerProductModal.id.replace('productModal-', ''));
});
function setSellerFieldFeedback(input, message) {
  if (!input) return;
  input.classList.toggle('seller-invalid', Boolean(message));
  const container = input.closest('.col-md-2,.col-md-3,.col-md-4,.col-md-5,.col-md-6,div') || input.parentElement;
  const feedback = container.querySelector(`[data-field-feedback="${input.name}"]`) || input.parentElement.querySelector(`[data-field-feedback="${input.name}"]`);
  if (feedback) feedback.textContent = message || '';
}
function sellerNumberError(value, { min = 0, max = 9999, required = false, label = 'Value', integer = false } = {}) {
  if (value === '') return required ? `${label} is required.` : '';
  const number = Number(value);
  if (!Number.isFinite(number) || (integer && !Number.isInteger(number))) return `${label} must be ${integer ? 'a whole number' : 'a valid number'}.`;
  if (number < min || number > max) return integer ? `${label} must be a whole number from ${min} to ${max}.` : `${label} must be from ${min} to ${max}.`;
  return '';
}
function validateSellerDiscountForm(form, showAlert = false) {
  if (!form) return true;
  const enabled = form.querySelector('[name="discount_enabled"]')?.checked;
  const type = form.querySelector('[name="discount_type"]')?.value || 'percent';
  const valueInput = form.querySelector('[name="discount_value"]');
  const startInput = form.querySelector('[name="discount_starts_at"]');
  const endInput = form.querySelector('[name="discount_ends_at"]');
  const bestInput = form.querySelector('[name="best_enjoyed_by"]');
  const dealInput = form.querySelector('[name="deal_quantity_limit"]');
  const saveBtn = form.querySelector('button[type="submit"]');
  const price = Number(form.dataset.productPrice || 0);
  const stockRaw = form.dataset.productStock || '';
  const stock = stockRaw === '' ? null : Number(stockRaw);
  const value = Number(valueInput?.value || 0);
  const dealValue = dealInput?.value === '' ? null : Number(dealInput?.value || 0);
  const valueFeedback = form.querySelector('.discount-value-feedback');
  const dateFeedback = form.querySelector('.discount-date-feedback');
  const dealFeedback = form.querySelector('.deal-pcs-feedback');
  let valid = true;
  const messages = [];

  if (valueInput) {
    valueInput.max = type === 'percent' ? '100' : String(Math.max(0, price));
    valueInput.step = '0.01';
  }
  if (dealInput) {
    dealInput.max = stock === null ? '9999' : String(Math.max(0, stock));
  }

  if (valueFeedback) valueFeedback.textContent = '';
  if (dateFeedback) dateFeedback.textContent = '';
  if (dealFeedback) dealFeedback.textContent = '';

  if (Number.isNaN(value) || value < 0) {
    valid = false;
    messages.push('Discount value cannot be negative.');
  } else if (enabled && value <= 0) {
    valid = false;
    messages.push('Discount value is required when discount is enabled.');
  } else if (type === 'percent' && value > 100) {
    valid = false;
    messages.push('Percentage discount cannot exceed 100%.');
  } else if (type === 'fixed' && price > 0 && value > price) {
    valid = false;
    messages.push('Fixed discount cannot be higher than product price.');
  }

  if (valueFeedback && messages.length) valueFeedback.textContent = messages[0];
  valueInput?.classList.toggle('seller-invalid', messages.length > 0);

  const start = startInput?.value ? new Date(startInput.value) : null;
  const end = endInput?.value ? new Date(endInput.value) : null;
  const best = bestInput?.value ? new Date(bestInput.value) : null;
  const dateMessages = [];
  if (start && end && end < start) {
    valid = false;
    dateMessages.push('End date cannot be earlier than Start date.');
  }
  if (best && start && best < start) {
    valid = false;
    dateMessages.push('Fresh Until cannot be earlier than Start date.');
  }
  if (best && end && best > end) {
    valid = false;
    dateMessages.push('Fresh Until cannot be later than End date.');
  }
  if (dateFeedback) dateFeedback.textContent = dateMessages[0] || '';
  [startInput, endInput, bestInput].forEach(input => input?.classList.toggle('seller-invalid', dateMessages.length > 0));

  if (dealValue !== null) {
    if (Number.isNaN(dealValue) || !Number.isInteger(dealValue) || dealValue < 0) {
      valid = false;
      if (dealFeedback) dealFeedback.textContent = 'Deal Pcs must be a whole number that is not negative.';
    } else if (stock !== null && dealValue > stock) {
      valid = false;
      if (dealFeedback) dealFeedback.textContent = 'Deal Pcs cannot be higher than available cakes.';
    } else if (dealFeedback) {
      dealFeedback.textContent = '';
    }
  }
  dealInput?.classList.toggle('seller-invalid', Boolean(dealFeedback?.textContent));

  if (saveBtn) {
    saveBtn.disabled = !valid;
    saveBtn.style.opacity = valid ? '1' : '.65';
    saveBtn.style.cursor = valid ? 'pointer' : 'not-allowed';
  }
  if (!valid && showAlert) {
    const firstMessage = messages[0] || dateMessages[0] || dealFeedback?.textContent || 'Please fix discount details before saving.';
    if (typeof csAlert === 'function') csAlert(firstMessage);
  }
  return valid;
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.seller-discount-form').forEach(form => bindSellerDiscountValidation(form));
  document.querySelectorAll('form[action*="/products"]').forEach(form => {
    if (form.querySelector('[name="name"]') && form.querySelector('[name="classification"]')) bindSellerProductValidation(form);
  });
  document.querySelectorAll('[name="new_size_label"]').forEach(input => {
    ['input','change','blur'].forEach(type => input.addEventListener(type, () => validateSizeDraft(input.closest('[data-size-editor]'), type === 'blur')));
    const editor = input.closest('[data-size-editor]');
    editor?.querySelectorAll('[name="new_size_price"],[name="new_size_available_quantity"]').forEach(field => ['input','change','blur'].forEach(type => field.addEventListener(type, () => validateSizeDraft(editor, type === 'blur'))));
    validateSizeDraft(editor);
  });
  document.querySelectorAll('input[type="file"][name="image"]').forEach(input => input.addEventListener('change', () => validateProductImage(input)));
  document.querySelectorAll('[data-size-stock-form]').forEach(form => bindSizeStockValidation(form));
  document.querySelectorAll('[data-character-counter]').forEach(counter => bindCharacterCounter(counter));
});
function sellerKey(value) { return (value || '').trim().replace(/\s+/g, ' ').toLocaleLowerCase(); }
function validateProductIdentity(form) {
  const name = sellerKey(form.querySelector('[name="name"]')?.value);
  const classification = sellerKey(form.querySelector('[name="classification"]')?.value);
  const currentId = form.closest('.seller-product-modal')?.id?.replace('productModal-', '') || '';
  const identities = JSON.parse(document.getElementById('sellerProductsRoot')?.dataset.productIdentities || '[]');
  const duplicate = name && classification && identities.some(item => item.id !== currentId && item.name === name && item.classification === classification);
  const input = form.querySelector('[name="name"]');
  let feedback = input?.parentElement.querySelector('.seller-duplicate-feedback');
  if (input && !feedback) { feedback = document.createElement('small'); feedback.className = 'seller-field-error seller-duplicate-feedback'; input.parentElement.appendChild(feedback); }
  if (feedback) feedback.textContent = duplicate ? 'A product with this name and classification already exists.' : '';
  input?.classList.toggle('seller-invalid', duplicate);
  return !duplicate;
}
function previewImg(input, previewId) {
  const img = document.getElementById(previewId);
  if (!img) return;
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => { img.src = e.target.result; img.style.display = 'block'; };
  reader.readAsDataURL(file);
}
function validateProductImage(input) {
  const file = input.files?.[0];
  const allowed = ['image/jpeg','image/png','image/webp'];
  const message = file && (!allowed.includes(file.type) || file.size > 5 * 1024 * 1024) ? 'Use a JPG, PNG, or WebP image no larger than 5MB.' : '';
  input.classList.toggle('seller-invalid', Boolean(message));
  let feedback = input.parentElement.querySelector('.seller-image-feedback');
  if (!feedback) { feedback = document.createElement('small'); feedback.className = 'seller-field-error seller-image-feedback'; input.parentElement.appendChild(feedback); }
  feedback.textContent = message;
  return !message;
}
function submitSize(productId) {
  const editor = document.getElementById('sizeEditor-' + productId);
  const labelInput = editor?.querySelector('input[name="new_size_label"]');
  const priceInput = editor?.querySelector('input[name="new_size_price"]');
  const stockInput = editor?.querySelector('input[name="new_size_available_quantity"]');
  const label = labelInput?.value.trim() || '';
  const price = priceInput?.value.trim() || '';
  const stock = stockInput?.value.trim() || '';
  const validation = validateSizeDraft(editor, true);
  if (!validation.valid) {
    validation.firstInvalid?.focus();
    return;
  }
  const button = editor?.querySelector('[data-add-size-button]');
  if (button) { button.disabled = true; button.textContent = 'Adding...'; }
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = '{{ url("/seller/products") }}/' + productId + '/sizes';
  form.innerHTML = '@csrf';
  [['label',label],['price',price],['available_quantity',stock]].forEach(([name,value]) => { const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; form.appendChild(input); });
  document.body.appendChild(form);
  form.submit();
}

function validateSizeDraft(editor, showEmpty = false) {
  if (!editor) return { valid: false, firstInvalid: null };
  const productId = editor.id.replace('sizeEditor-', '');
  const labelInput = editor.querySelector('input[name="new_size_label"]');
  const priceInput = editor.querySelector('input[name="new_size_price"]');
  const stockInput = editor.querySelector('input[name="new_size_available_quantity"]');
  const label = sellerKey(labelInput?.value);
  const price = priceInput?.value.trim() || '';
  const stock = stockInput?.value.trim() || '';
  const existingLabels = [...document.querySelectorAll(`#sellerProduct-${productId} [data-size-label]`)].map(el => sellerKey(el.dataset.sizeLabel));
  const errors = {
    new_size_label: !label ? (showEmpty ? 'Size label is required.' : '') : label.length > 50 ? 'Size label must be 50 characters or less.' : existingLabels.includes(label) ? 'This size already exists for this product.' : '',
    new_size_price: sellerNumberError(price, { min: 1, max: 99999999.99, required: showEmpty, label: 'Size price' }),
    new_size_available_quantity: sellerNumberError(stock, { min: 0, max: 9999, label: 'Size stock', integer: true })
  };
  setSellerFieldFeedback(labelInput, errors.new_size_label);
  setSellerFieldFeedback(priceInput, errors.new_size_price);
  setSellerFieldFeedback(stockInput, errors.new_size_available_quantity);
  const firstInvalid = [[labelInput, errors.new_size_label], [priceInput, errors.new_size_price], [stockInput, errors.new_size_available_quantity]].find(([, message]) => message)?.[0] || null;
  const valid = Boolean(label) && Boolean(price) && !firstInvalid;
  const button = editor.querySelector('[data-add-size-button]');
  if (button) button.disabled = !valid;
  return { valid, firstInvalid };
}

function bindSizeStockValidation(form) {
  const input = form.querySelector('[name="available_quantity"]');
  const priceInput = form.querySelector('[name="price"]');
  const button = form.querySelector('button[type="submit"]');
  const isAutosave = form.dataset.sizeAutosave !== undefined;
  const status = form.querySelector('[data-autosave-status]');
  const validate = () => {
    const stockMessage = sellerNumberError(input?.value ?? '', { min: 0, max: 9999, required: true, label: 'Stock', integer: true });
    const priceMessage = priceInput ? sellerNumberError(priceInput.value ?? '', { min: 1, max: 99999999.99, required: true, label: 'Price' }) : '';
    setSellerFieldFeedback(input, stockMessage);
    if (priceInput) setSellerFieldFeedback(priceInput, priceMessage);
    if (button) button.disabled = Boolean(stockMessage || priceMessage);
    return !stockMessage && !priceMessage;
  };
  ['input', 'change', 'blur'].forEach(type => input?.addEventListener(type, validate));
  ['input', 'change', 'blur'].forEach(type => priceInput?.addEventListener(type, validate));

  if (isAutosave) {
    let timer = null;
    let changeVersion = 0;
    let saving = false;
    const setStatus = (message, tone = 'muted') => {
      if (!status) return;
      status.textContent = message;
      status.className = `small text-${tone} align-self-center`;
    };
    const renderProductStock = state => {
      const card = document.querySelector(`.seller-product-item[data-product-id="${state?.id || ''}"]`);
      if (!card || !state) return;
      card.dataset.realtimeVersion = String(state.version || card.dataset.realtimeVersion || '');
      const quantity = Math.max(0, Number(state.stock_quantity || 0));
      const badge = card.querySelector('[data-product-stock-badge]');
      if (!badge) return;
      const label = quantity <= 0 ? 'Out of stock' : (state.has_active_sizes ? `${quantity} available across sizes` : `${quantity} available`);
      const tone = quantity <= 0
        ? { bg: '#fef2f2', color: '#b91c1c', border: '#fecaca', icon: 'bi-exclamation-circle' }
        : quantity <= 3
          ? { bg: '#fffbeb', color: '#92400e', border: '#fde68a', icon: 'bi-box-seam' }
          : { bg: '#ecfdf5', color: '#047857', border: '#a7f3d0', icon: 'bi-box-seam' };
      badge.style.background = tone.bg;
      badge.style.color = tone.color;
      badge.style.borderColor = tone.border;
      badge.innerHTML = `<i class="bi ${tone.icon}"></i> ${label}`;
    };
    const save = async () => {
      if (!validate() || saving) return;
      const versionAtStart = changeVersion;
      saving = true;
      setStatus('Saving…', 'primary');
      try {
        const response = await fetch(form.action, {
          method: 'POST',
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: new FormData(form),
          credentials: 'same-origin',
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload.ok) {
          const errors = payload.errors || {};
          setSellerFieldFeedback(priceInput, errors.price?.[0] || '');
          setSellerFieldFeedback(input, errors.available_quantity?.[0] || '');
          throw new Error(payload.message || 'Could not save changes.');
        }
        if (payload.size) {
          priceInput.value = payload.size.price;
          input.value = payload.size.available_quantity;
          document.querySelectorAll(`[data-size-price-display="${payload.size.id}"]`).forEach(el => {
            el.textContent = `PHP ${Number(payload.size.price).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
          });
        }
        renderProductStock(payload.product);
        setStatus('Saved', 'success');
      } catch (error) {
        setStatus(error.message || 'Save failed', 'danger');
      } finally {
        saving = false;
        if (changeVersion !== versionAtStart) scheduleSave();
      }
    };
    const scheduleSave = () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(save, 650);
    };
    [input, priceInput].filter(Boolean).forEach(field => {
      field.addEventListener('input', () => { changeVersion += 1; if (validate()) scheduleSave(); });
      field.addEventListener('blur', () => { changeVersion += 1; window.clearTimeout(timer); save(); });
    });
    form.addEventListener('submit', event => { event.preventDefault(); save(); });
    validate();
    return;
  }

  form.addEventListener('submit', event => {
    if (!validate()) { event.preventDefault(); input?.focus(); return; }
    if (button) { button.disabled = true; button.textContent = 'Saving...'; }
  });
  validate();
}

function bindCharacterCounter(counter) {
  const input = counter.parentElement.querySelector(`[name="${counter.dataset.characterCounter}"]`);
  const limit = Number(counter.dataset.characterLimit || 0);
  const update = () => { counter.textContent = `${input?.value.length || 0}/${limit}`; };
  input?.addEventListener('input', update);
  update();
}

function bindSellerProductValidation(form) {
  const rules = {
    name: value => value.trim().length < 2 ? 'Product name must contain at least 2 characters.' : value.trim().length > 100 ? 'Product name must be 100 characters or less.' : '',
    price: value => !value || Number(value) < 1 ? 'Price must be at least PHP 1.' : Number(value) > 99999999.99 ? 'Price is too high.' : '',
    classification: value => !value ? 'Choose a classification.' : '',
    available_quantity: value => value !== '' && (!Number.isInteger(Number(value)) || Number(value) < 0 || Number(value) > 9999) ? 'Stock must be a whole number from 0 to 9999.' : ''
  };
  const fieldMessage = input => rules[input.name]?.(input.value) || '';
  const show = input => {
    const message = fieldMessage(input);
    input.classList.toggle('seller-invalid', Boolean(message));
    let feedback = input.parentElement.querySelector('.seller-field-error');
    if (!feedback) { feedback = document.createElement('small'); feedback.className = 'seller-field-error'; input.parentElement.appendChild(feedback); }
    feedback.textContent = message;
    updateSaveState();
    return !message;
  };
  const updateSaveState = () => {
    const fieldsValid = Object.keys(rules).every(name => {
      const input = form.querySelector(`[name="${name}"]`);
      return !input || !fieldMessage(input);
    });
    const valid = fieldsValid && validateProductIdentity(form) && validateProductImage(form.querySelector('input[type="file"][name="image"]'));
    const button = form.querySelector('button[type="submit"]');
    if (button) button.disabled = !valid;
    return valid;
  };
  Object.keys(rules).forEach(name => form.querySelector(`[name="${name}"]`)?.addEventListener('input', event => show(event.target)));
  form.querySelector('[name="classification"]')?.addEventListener('change', event => show(event.target));
  form.querySelector('[name="name"]')?.addEventListener('input', updateSaveState);
  form.querySelector('[name="classification"]')?.addEventListener('change', updateSaveState);
  form.querySelector('input[type="file"][name="image"]')?.addEventListener('change', updateSaveState);
  form.addEventListener('submit', event => {
    const firstInvalid = Object.keys(rules).map(name => form.querySelector(`[name="${name}"]`)).find(input => input && !show(input));
    if (firstInvalid) { event.preventDefault(); firstInvalid.focus(); return; }
    if (!validateProductIdentity(form)) { event.preventDefault(); form.querySelector('[name="name"]')?.focus(); return; }
    const imageInput = form.querySelector('input[type="file"][name="image"]');
    if (imageInput && !validateProductImage(imageInput)) { event.preventDefault(); imageInput.focus(); return; }
    const button = form.querySelector('button[type="submit"]');
    if (button) { button.disabled = true; button.textContent = 'Saving...'; }
  });
  updateSaveState();
}

function bindSellerDiscountValidation(form) {
  const fields = form.querySelectorAll('[name="discount_enabled"],[name="discount_type"],[name="discount_value"],[name="discount_starts_at"],[name="discount_ends_at"],[name="best_enjoyed_by"],[name="deal_quantity_limit"]');
  fields.forEach(field => ['input', 'change', 'blur'].forEach(type => field.addEventListener(type, () => validateSellerDiscountForm(form, false))));
  form.addEventListener('submit', event => {
    if (!validateSellerDiscountForm(form, false)) { event.preventDefault(); form.querySelector('.seller-invalid')?.focus(); return; }
    const button = form.querySelector('button[type="submit"]');
    if (button) { button.disabled = true; button.textContent = 'Saving...'; }
  });
  validateSellerDiscountForm(form, false);
}

function filterSellerProducts() {
  const search = (document.getElementById('sellerProductSearch')?.value || '').toLowerCase().trim();
  const classification = (document.getElementById('sellerProductClassFilter')?.value || '').toLowerCase();
  const status = (document.getElementById('sellerProductStatusFilter')?.value || '').toLowerCase();
  let visibleCount = 0;

  document.querySelectorAll('.seller-product-item').forEach(el => {
    const matchesSearch = !search || (el.dataset.search || '').includes(search);
    const matchesClass = !classification || (el.dataset.classification || '') === classification;
    const matchesStatus = !status || (el.dataset.status || '') === status;
    const matches = matchesSearch && matchesClass && matchesStatus;
    el.style.display = matches ? '' : 'none';
    if (matches) visibleCount++;
  });

  const summary = document.getElementById('sellerProductFilterSummary');
  if (summary) summary.textContent = 'Showing ' + visibleCount + ' of ' + document.querySelectorAll('.seller-product-item').length + ' products';

  const empty = document.getElementById('sellerProductsEmpty');
  if (empty) empty.style.display = visibleCount === 0 ? 'block' : 'none';
}

(function startSellerProductRealtime() {
  const root = document.getElementById('sellerProductsRoot');
  const statusUrl = root?.dataset.realtimeUrl;
  if (!root || !statusUrl) return;

  let reloading = false;
  let requestInFlight = false;
  const checkForUpdates = async () => {
    if (document.hidden || reloading || requestInFlight) return;
    const cards = Array.from(root.querySelectorAll('.seller-product-item[data-product-id]'));
    const ids = cards.map(card => card.dataset.productId).filter(Boolean);
    if (!ids.length) return;

    requestInFlight = true;
    try {
      const url = new URL(statusUrl, window.location.origin);
      url.searchParams.set('ids', ids.join(','));
      const response = await fetch(url.toString(), {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        cache: 'no-store',
        credentials: 'same-origin',
      });
      if (!response.ok) return;
      const payload = await response.json();
      if (!payload?.ok) return;
      const versions = new Map((payload.items || []).map(item => [String(item.id), String(item.version || '')]));
      const changed = cards.some(card => {
        const next = versions.get(String(card.dataset.productId));
        return next && next !== String(card.dataset.realtimeVersion || '');
      });
      if (changed) {
        reloading = true;
        window.location.reload();
      }
    } catch (error) {
      // A future poll will retry; do not disrupt the seller's current work.
    } finally {
      requestInFlight = false;
    }
  };

  checkForUpdates();
  const timer = window.setInterval(checkForUpdates, 8000);
  window.addEventListener('beforeunload', () => window.clearInterval(timer));
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) checkForUpdates();
  });
})();
</script>
@endsection
