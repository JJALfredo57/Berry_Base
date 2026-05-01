@extends('layouts.app')
@section('page_title','Products')
@section('content')
<div>

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
          <a href="{{ route('seller.products', ['tab'=>'archived','search'=>$search]) }}"
             style="padding:.3rem .85rem;border-radius:2rem;font-size:.78rem;font-weight:600;text-decoration:none;border:1.5px solid {{ $tab==='archived' ? '#d97706' : 'var(--gray-200)' }};background:{{ $tab==='archived' ? '#d97706' : '#fff' }};color:{{ $tab==='archived' ? '#fff' : 'var(--gray-600)' }};display:flex;align-items:center;gap:.35rem">
            <i class="bi bi-archive"></i> Archived
            @if($archivedCount > 0)
              <span style="background:{{ $tab==='archived' ? 'rgba(255,255,255,.3)' : '#d97706' }};color:#fff;border-radius:99px;font-size:.65rem;padding:0 .4rem;min-width:1.2rem;text-align:center">{{ $archivedCount }}</span>
            @endif
          </a>
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
      </div>
      <div class="col-md-3">
        <label class="form-label">Flavor <span style="color:var(--gray-400);font-weight:400">(optional)</span></label>
        <input type="text" class="form-control" name="flavor" value="{{ old('flavor') }}"
               placeholder="e.g. Chocolate, Ube, Red Velvet" maxlength="100">
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
@endphp
<div class="seller-product-item"
     data-search="{{ strtolower(trim($p->name . ' ' . ($p->description ?? '') . ' ' . ($p->flavor ?? '') . ' ' . ($p->classification ?? ''))) }}"
     data-classification="{{ strtolower($p->classification ?? '') }}"
     data-status="{{ $p->is_available ? 'visible' : 'hidden' }}"
     style="background:#fff;border-radius:var(--radius-lg);border:1.5px solid {{ $p->archived_at ? '#fcd34d' : ($p->is_available ? 'var(--gray-100)' : 'var(--gray-200)') }};margin-bottom:1rem;overflow:hidden;opacity:{{ $p->archived_at ? '.75' : ($p->is_available ? '1' : '.65') }}">
  <div style="display:flex;align-items:center;gap:1rem;padding:1rem 1.25rem;flex-wrap:wrap">

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
        @if($p->archived_at)
          <span style="background:#fffbeb;color:#92400e;border:1px solid #fcd34d;font-size:.68rem;font-weight:700;padding:.15rem .5rem;border-radius:99px;display:inline-flex;align-items:center;gap:.25rem"><i class="bi bi-archive-fill"></i> Archived</span>
        @elseif(!$p->is_available)
          <span style="background:var(--gray-200);color:var(--gray-600);font-size:.68rem;font-weight:700;padding:.15rem .5rem;border-radius:99px">Hidden</span>
        @endif
        <span style="background:var(--primary-bg);color:var(--primary);font-size:.68rem;font-weight:600;padding:.15rem .5rem;border-radius:99px">{{ $p->classification }}</span>
      </div>
      <div style="font-size:.875rem;font-weight:700;color:var(--primary);margin:.2rem 0">₱{{ number_format($p->price,2) }} base</div>
      @if($discountBadge)
        <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;margin-bottom:.2rem">
          <span style="background:#fff1f2;color:#be123c;font-size:.68rem;font-weight:700;padding:.15rem .5rem;border-radius:99px">
            {{ $discountBadge }}
          </span>
          @if(!empty($discount->label))
            <span style="font-size:.7rem;color:var(--gray-500)">{{ $discount->label }}</span>
          @endif
        </div>
      @elseif(!empty($discount))
        <div style="font-size:.7rem;color:var(--gray-500);margin-bottom:.2rem">Discount saved but currently disabled.</div>
      @endif
      <div style="font-size:.75rem;color:var(--gray-500)">
        @if($p->flavor)<span style="margin-right:.75rem"><i class="bi bi-droplet" style="font-size:.7rem"></i> {{ $p->flavor }}</span>@endif
        @if($sizes->count() > 0)<span style="margin-right:.75rem">{{ $sizes->count() }} size{{ $sizes->count() > 1 ? 's' : '' }}</span>@endif
        @if($p->archived_at)<span style="color:#d97706"><i class="bi bi-calendar-x me-1" style="font-size:.7rem"></i>Archived {{ \Carbon\Carbon::parse($p->archived_at)->diffForHumans() }}</span>@endif
      </div>
    </div>

    {{-- Actions --}}
    <div style="display:flex;gap:.5rem;flex-shrink:0;flex-wrap:wrap">
      @if($p->archived_at)
        {{-- Archived product: show only Restore --}}
        <form action="{{ route('seller.products.restore', $p->id) }}" method="POST" class="d-inline"
              data-cs-confirm="Restore &quot;{{ addslashes($p->name) }}&quot;? It will be visible to customers again." data-cs-title="Restore Product" data-cs-icon="bi-arrow-counterclockwise" data-cs-icon-bg="#ecfdf5" data-cs-icon-color="#059669" data-cs-ok="Restore" data-cs-ok-color="#059669">
          @csrf
          <button type="submit" style="background:#ecfdf5;color:#059669;border:1.5px solid #6ee7b7;border-radius:var(--radius-md);padding:.35rem .875rem;font-size:.78rem;font-weight:600;cursor:pointer">
            <i class="bi bi-arrow-counterclockwise"></i> Restore
          </button>
        </form>
      @else
        {{-- Active product: show Hide/Show, Edit, Discount, Archive --}}
        <form action="{{ route('seller.products.toggle', $p->id) }}" method="POST" class="d-inline">
          @csrf
          <button type="submit" style="background:{{ $p->is_available ? '#FFF3E0' : '#E8F5E9' }};color:{{ $p->is_available ? '#E65100' : '#2E7D32' }};border:1.5px solid {{ $p->is_available ? '#FFCC80' : '#A5D6A7' }};border-radius:var(--radius-md);padding:.35rem .875rem;font-size:.78rem;font-weight:600;cursor:pointer">
            <i class="bi bi-{{ $p->is_available ? 'eye-slash' : 'eye' }}"></i>
            {{ $p->is_available ? 'Hide' : 'Show' }}
          </button>
        </form>
        <button type="button" onclick="toggleEditForm('edit-{{ $p->id }}')"
                style="background:var(--info-bg,#E3F2FD);color:#1565C0;border:1.5px solid #90CAF9;border-radius:var(--radius-md);padding:.35rem .875rem;font-size:.78rem;font-weight:600;cursor:pointer">
          <i class="bi bi-pencil"></i> Edit
        </button>
        <button type="button" onclick="toggleDiscountForm('discount-{{ $p->id }}')"
                style="background:#fff7ed;color:#c2410c;border:1.5px solid #fdba74;border-radius:var(--radius-md);padding:.35rem .875rem;font-size:.78rem;font-weight:600;cursor:pointer">
          <i class="bi bi-tags"></i> Discount
        </button>
        <form action="{{ route('seller.products.archive', $p->id) }}" method="POST" class="d-inline"
              data-cs-confirm="Archive &quot;{{ addslashes($p->name) }}&quot;? It will be hidden from customers until restored." data-cs-title="Archive Product" data-cs-icon="bi-archive" data-cs-icon-bg="#fffbeb" data-cs-icon-color="#d97706" data-cs-ok="Archive" data-cs-ok-color="#d97706">
          @csrf
          <button type="submit" style="background:#fffbeb;color:#92400e;border:1.5px solid #fcd34d;border-radius:var(--radius-md);padding:.35rem .875rem;font-size:.78rem;font-weight:600;cursor:pointer">
            <i class="bi bi-archive"></i> Archive
          </button>
        </form>
      @endif
    </div>
  </div>

  {{-- Sizes (hidden for archived products) --}}
  @if($sizes->count() > 0 && !$p->archived_at)
  <div style="border-top:1px solid var(--gray-100);padding:.625rem 1.25rem;background:var(--gray-50);display:flex;flex-wrap:wrap;gap:.5rem;align-items:center">
    <span style="font-size:.72rem;font-weight:700;color:var(--gray-500);margin-right:.25rem">SIZES:</span>
    @foreach($sizes as $sz)
    <span style="background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-sm);padding:.2rem .6rem;font-size:.75rem;color:var(--gray-700);display:inline-flex;align-items:center;gap:.4rem">
      {{ $sz->label }} — ₱{{ number_format($sz->price,2) }}
      <form action="{{ route('seller.products.sizes.destroy', $sz->id) }}" method="POST" class="d-inline"
            data-cs-confirm="Remove size {{ addslashes($sz->label) }}?" data-cs-title="Remove Size" data-cs-icon="bi-trash" data-cs-icon-bg="#fff1f2" data-cs-icon-color="#ef4444" data-cs-ok="Remove" data-cs-ok-color="#ef4444">
        @csrf
        <button type="submit" style="background:none;border:none;color:var(--gray-400);cursor:pointer;padding:0;font-size:.7rem;line-height:1">&times;</button>
      </form>
    </span>
    @endforeach
  </div>
  @endif

  {{-- Edit Form --}}
  <div id="discount-{{ $p->id }}" style="display:none;border-top:1.5px solid #fed7aa;padding:1.25rem;background:#fffaf3">
    <form action="{{ route('seller.products.discount', $p->id) }}" method="POST">
      @csrf
      <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
        <div>
          <div style="font-size:.92rem;font-weight:700;color:var(--gray-900)">Manage Product Discount</div>
          <div style="font-size:.75rem;color:var(--gray-500)">Discount applies to the actual checkout unit price, including selected size pricing.</div>
        </div>
        <label style="display:inline-flex;align-items:center;gap:.5rem;font-size:.8rem;font-weight:600;color:var(--gray-700)">
          <input type="checkbox" name="discount_enabled" value="1" {{ ($discount->is_active ?? 0) ? 'checked' : '' }}>
          Enable discount
        </label>
      </div>

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Promo Label</label>
          <input type="text" class="form-control" name="discount_label" value="{{ $discount->label ?? '' }}" placeholder="e.g. Summer Sale">
        </div>
        <div class="col-md-3">
          <label class="form-label">Discount Type</label>
          <select class="form-select" name="discount_type">
            <option value="percent" {{ ($discount->discount_type ?? 'percent') === 'percent' ? 'selected' : '' }}>Percentage</option>
            <option value="fixed" {{ ($discount->discount_type ?? '') === 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Value</label>
          <input type="number" step="0.01" min="0" class="form-control" name="discount_value" value="{{ $discount->discount_value ?? '' }}" placeholder="20">
        </div>
        <div class="col-md-3">
          <label class="form-label">Start</label>
          <input type="datetime-local" class="form-control" name="discount_starts_at" value="{{ !empty($discount->starts_at) ? \Carbon\Carbon::parse($discount->starts_at)->format('Y-m-d\TH:i') : '' }}">
        </div>
        <div class="col-md-3">
          <label class="form-label">End</label>
          <input type="datetime-local" class="form-control" name="discount_ends_at" value="{{ !empty($discount->ends_at) ? \Carbon\Carbon::parse($discount->ends_at)->format('Y-m-d\TH:i') : '' }}">
        </div>
      </div>

      <div style="display:flex;gap:.75rem;margin-top:1rem">
        <button type="submit" class="btn btn-primary" style="padding:.5rem 1.25rem;font-weight:600">Save Discount</button>
        <button type="button" onclick="toggleDiscountForm('discount-{{ $p->id }}')" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
  <div id="edit-{{ $p->id }}" style="display:none;border-top:1.5px solid var(--primary-light);padding:1.25rem;background:#FFF8F8">
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
          <textarea class="form-control" name="description" rows="2">{{ $p->description }}</textarea>
        </div>
        <div class="col-md-3">
          <label class="form-label">Flavor</label>
          <input type="text" class="form-control" name="flavor" value="{{ $p->flavor }}">
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

      {{-- Add Size --}}
      <div style="margin-top:1rem;padding-top:1rem;border-top:1px dashed var(--gray-300)">
        <div style="font-size:.8rem;font-weight:700;color:var(--gray-700);margin-bottom:.6rem">Add Size Option</div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
          <div>
            <input type="text" class="form-control" name="new_size_label" placeholder="e.g. 6-inch" style="width:140px">
          </div>
          <div>
            <input type="number" class="form-control" name="new_size_price" placeholder="₱" step="0.01" min="1" style="width:120px">
          </div>
          <a href="{{ route('seller.products.sizes.store', $p->id) }}" style="display:none" id="sizeFormAction-{{ $p->id }}"></a>
          <button type="button" onclick="submitSize('{{ $p->id }}')"
                  style="background:var(--gray-100);color:var(--gray-700);border:1.5px solid var(--gray-200);border-radius:var(--radius-md);padding:.5rem 1rem;font-size:.8rem;font-weight:600;cursor:pointer">
            <i class="bi bi-plus-lg"></i> Add Size
          </button>
        </div>
      </div>

      <div style="display:flex;gap:.75rem;margin-top:1rem">
        <button type="submit" class="btn btn-primary" style="padding:.5rem 1.25rem;font-weight:600">Save Changes</button>
        <button type="button" onclick="toggleEditForm('edit-{{ $p->id }}')" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
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
function toggleEditForm(id) {
  const f = document.getElementById(id);
  f.style.display = f.style.display === 'none' ? 'block' : 'none';
}
function toggleDiscountForm(id) {
  const f = document.getElementById(id);
  f.style.display = f.style.display === 'none' ? 'block' : 'none';
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
function submitSize(productId) {
  const label = document.querySelector(`#edit-${productId} input[name="new_size_label"]`).value.trim();
  const price = document.querySelector(`#edit-${productId} input[name="new_size_price"]`).value.trim();
  if (!label || !price) { csAlert('Please enter size label and price.'); return; }
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = '{{ url("/seller/products") }}/' + productId + '/sizes';
  form.innerHTML = `@csrf<input name="label" value="${label}"><input name="price" value="${price}">`;
  document.body.appendChild(form);
  form.submit();
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
</script>
@endsection
