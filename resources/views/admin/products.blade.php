@extends('layouts.app')
@section('content')
<div>
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-cake2 me-2" style="color:var(--primary)"></i>Products</h4>
      <p class="text-muted small mb-0" id="productsCountLabel">{{ count($products) }} products</p>
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-center">
      <div class="cs-search-bar" style="max-width:220px">
        
        <input type="text" id="productSearch" class="form-control form-control-sm"
               placeholder="Search products…" oninput="filterProducts()">
      </div>
      <select id="productClassFilter" class="form-select form-select-sm" style="width:auto" onchange="filterProducts()">
        <option value="">All Types</option>
        <option value="Standard">Standard</option>
        <option value="Fondant">Fondant</option>
        <option value="Perishable">Perishable</option>
      </select>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus me-1"></i>Add Product
      </button>
    </div>
  </div>

  @if(session('msg'))<div class="alert alert-success border-0" style="border-radius:.9rem"><i class="bi bi-check-circle me-2"></i>{{ session('msg') }}</div>@endif
  @if(session('err'))<div class="alert alert-danger border-0" style="border-radius:.9rem"><i class="bi bi-exclamation-circle me-2"></i>{{ session('err') }}</div>@endif

  <div class="row g-3" id="productsList">
    @forelse($products as $p)
    @php
      $sizes = $productSizes[$p->id] ?? collect();
      $classColors = [
        'Standard'   => ['bg'=>'#dbeafe','color'=>'#1e40af'],
        'Fondant'    => ['bg'=>'#fce7f3','color'=>'#9d174d'],
        'Perishable' => ['bg'=>'#d1fae5','color'=>'#065f46'],
      ];
      $cls = $classColors[$p->classification ?? 'Standard'] ?? $classColors['Standard'];
    @endphp
    <div class="col-md-6 col-lg-4 product-item"
         data-search="{{ strtolower($p->name . ' ' . ($p->flavor ?? '') . ' ' . ($p->classification ?? '')) }}"
         data-filter="{{ $p->classification ?? 'Standard' }}">
      <div class="card h-100">
        <div class="position-relative">
          <img src="{{ $p->image_path }}" alt="{{ $p->name }}" class="img-cover"
               style="width:100%;height:180px;object-fit:cover;border-radius:1.1rem 1.1rem 0 0"
               onerror="this.src='https://placehold.co/400x180/fce4ec/e91e63?text=🎂'">
          <span class="position-absolute top-0 start-0 m-2 badge"
                style="background:{{ $cls['bg'] }};color:{{ $cls['color'] }};font-size:clamp(.68rem,1.3vw,.72rem)">
            {{ $p->classification ?? 'Standard' }}
          </span>
          @if((int)($p->is_available ?? 1))
            <span class="position-absolute top-0 end-0 m-2 badge bg-success" style="font-size:clamp(.66rem,1.3vw,.7rem)">Available</span>
          @else
            <span class="position-absolute top-0 end-0 m-2 badge bg-danger" style="font-size:clamp(.66rem,1.3vw,.7rem)">Not Available</span>
          @endif
        </div>
        <div class="card-body p-3">
          <h6 class="fw-bold mb-1">{{ $p->name }}</h6>
          @if($p->flavor)
            <div class="text-muted small mb-1"><i class="bi bi-droplet me-1"></i>{{ $p->flavor }}</div>
          @endif
          <p class="text-muted small mb-2">{{ Str::limit($p->description, 60) }}</p>

          {{-- Sizes list --}}
          @if($sizes->count() > 0)
          <div class="mb-2">
            <div class="text-muted" style="font-size:clamp(.68rem,1.3vw,.72rem);font-weight:600">SIZES</div>
            <div class="d-flex flex-wrap gap-1 mt-1">
              @foreach($sizes as $sz)
              <span class="badge" style="background:var(--primary-light);color:var(--primary);font-size:clamp(.66rem,1.3vw,.7rem)">
                {{ $sz->label }} — ₱{{ number_format($sz->price,2) }}
              </span>
              @endforeach
            </div>
          </div>
          @endif

          <div class="d-flex align-items-center justify-content-between mt-2">
            <div>
              <span class="fw-bold" style="color:var(--primary)">₱{{ number_format($p->price,2) }}
                @if($sizes->count() > 0)<span class="text-muted fw-normal" style="font-size:clamp(.68rem,1.3vw,.72rem)"> base</span>@endif
              </span>

            </div>
            <div class="d-flex gap-1">
              {{-- Sizes Button --}}
              <button class="btn btn-outline-secondary btn-sm" title="Manage Sizes"
                      data-bs-toggle="modal" data-bs-target="#sizesModal{{ $p->id }}">
                <i class="bi bi-rulers"></i>
              </button>
              {{-- Toggle Available --}}
              <form action="{{ route('admin.products.toggle_available', $p->id) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm {{ (int)($p->is_available ?? 1) ? 'btn-outline-success' : 'btn-outline-danger' }}"
                        title="{{ (int)($p->is_available ?? 1) ? 'Mark as Not Available' : 'Mark as Available' }}">
                  <i class="bi {{ (int)($p->is_available ?? 1) ? 'bi-eye' : 'bi-eye-slash' }}"></i>
                </button>
              </form>
              {{-- Edit Button --}}
              <button class="btn btn-outline-secondary btn-sm"
                      data-bs-toggle="modal" data-bs-target="#editModal{{ $p->id }}">
                <i class="bi bi-pencil"></i>
              </button>
              {{-- Delete Button --}}
              <form action="{{ route('admin.products.destroy',$p->id) }}" method="POST"
                    onsubmit="return false;" onclick="confirmDelete('Delete {{ addslashes($p->name) }}? This cannot be undone.', () => this.closest('form').submit())">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ── SIZES MODAL ─────────────────────────────────────────────────── --}}
    <div class="modal fade" id="sizesModal{{ $p->id }}" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius:1.2rem">
          <div class="modal-header border-0 pb-0">
            <div>
              <h5 class="modal-title fw-bold"><i class="bi bi-rulers me-2" style="color:var(--primary)"></i>Manage Sizes</h5>
              <div class="text-muted small">{{ $p->name }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">

            {{-- Existing Sizes --}}
            @if($sizes->count() > 0)
            <div class="mb-4">
              <div class="fw-semibold small mb-2">Current Sizes</div>
              <div class="d-flex flex-column gap-2">
                @foreach($sizes as $sz)
                <div class="d-flex align-items-center justify-content-between p-2 rounded"
                     style="background:#f8f9fa;border:1px solid #e9ecef">
                  <div>
                    <span class="fw-semibold small">{{ $sz->label }}</span>
                    <span class="text-muted small ms-2">₱{{ number_format($sz->price,2) }}</span>
                  </div>
                  <form action="{{ route('admin.products.sizes.destroy',$sz->id) }}" method="POST"
                        onsubmit="return false;" onclick="confirmDelete('Delete size \'{{ addslashes($sz->label) }}\'?', () => this.closest('form').submit())">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2">
                      <i class="bi bi-x"></i>
                    </button>
                  </form>
                </div>
                @endforeach
              </div>
            </div>
            @else
            <div class="text-center text-muted small py-3 mb-3">
              <i class="bi bi-rulers" style="font-size:2rem;opacity:.3"></i>
              <div class="mt-2">No sizes added yet.</div>
            </div>
            @endif

            {{-- Add New Size --}}
            <div class="border-top pt-3">
              <div class="fw-semibold small mb-2">Add New Size</div>
              <form action="{{ route('admin.products.sizes.store',$p->id) }}" method="POST">
                @csrf
                <div class="row g-2">
                  <div class="col-6">
                    <label class="form-label fw-semibold small">Label <span class="text-muted fw-normal">(e.g. 6", 8x3)</span></label>
                    <input type="text" class="form-control" name="label" placeholder='e.g. 6"' required maxlength="40">
                  </div>
                  <div class="col-6">
                    <label class="form-label fw-semibold small">Price (₱)</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="price" placeholder="500.00" required>
                  </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm w-100 mt-3">
                  <i class="bi bi-plus me-1"></i>Add Size
                </button>
              </form>
            </div>

          </div>
        </div>
      </div>
    </div>

    {{-- ── EDIT MODAL ───────────────────────────────────────────────────── --}}
    <div class="modal fade" id="editModal{{ $p->id }}" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius:1.2rem">
          <div class="modal-header border-0">
            <h5 class="modal-title fw-bold">Edit Product</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <form action="{{ route('admin.products.update',$p->id) }}" method="POST" enctype="multipart/form-data">
              @csrf
              <div class="mb-3">
                <label class="form-label fw-semibold small">Product Name</label>
                <input type="text" class="form-control" name="name" value="{{ $p->name }}" required>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold small">Description</label>
                <textarea class="form-control" name="description" rows="2">{{ $p->description }}</textarea>
              </div>
              <div class="row g-2 mb-3">
                <div class="col-sm-6">
                  <label class="form-label fw-semibold small">Base Price (₱)</label>
                  <input type="number" step="0.01" class="form-control" name="price" value="{{ $p->price }}" required>
                  <div class="form-text">Used when no size is selected</div>
                </div>
                <div class="col-sm-6">
                  <label class="form-label fw-semibold small">Classification</label>
                  <select class="form-select" name="classification">
                    <option value="Standard"   {{ ($p->classification ?? 'Standard') == 'Standard'   ? 'selected' : '' }}>Standard</option>
                    <option value="Fondant"    {{ ($p->classification ?? '') == 'Fondant'    ? 'selected' : '' }}>Fondant</option>
                    <option value="Perishable" {{ ($p->classification ?? '') == 'Perishable' ? 'selected' : '' }}>Perishable (Ice Cream)</option>
                  </select>
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold small">Flavor <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" class="form-control" name="flavor" value="{{ $p->flavor }}" placeholder="e.g. Chocolate, Red Velvet, Ube">
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold small">Image <span class="text-muted fw-normal">(leave blank to keep current)</span></label>
                <input type="file" class="form-control" name="image" accept="image/*">
                @if($p->image_path)
                  <img src="{{ $p->image_path }}" style="width:60px;height:60px;object-fit:cover;border-radius:.5rem;margin-top:.5rem"
                       onerror="this.style.display='none'">
                @endif
              </div>
              <button type="submit" class="btn btn-primary w-100">Save Changes</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    @empty
    <div class="col-12 text-center py-5 text-muted">No products yet.</div>
    @endforelse
  </div>

  {{-- Pagination --}}
  <div class="mt-3" id="productsList_pager"></div>
</div>

{{-- ── ADD MODAL ──────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0" style="border-radius:1.2rem">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold">Add Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form action="{{ route('admin.products.store') }}" method="POST" enctype="multipart/form-data">
          @csrf
          <div class="mb-3">
            <label class="form-label fw-semibold small">Product Name</label>
            <input type="text" class="form-control" name="name" placeholder="e.g. Chocolate Moist Cake" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Description</label>
            <textarea class="form-control" name="description" rows="2" placeholder="Describe the product…"></textarea>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-sm-6">
              <label class="form-label fw-semibold small">Base Price (₱)</label>
              <input type="number" step="0.01" class="form-control" name="price" placeholder="850.00" required>
              <div class="form-text">Used when no size is selected</div>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold small">Classification</label>
              <select class="form-select" name="classification">
                <option value="Standard">Standard</option>
                <option value="Fondant">Fondant</option>
                <option value="Perishable">Perishable (Ice Cream)</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Flavor <span class="text-muted fw-normal">(optional)</span></label>
            <input type="text" class="form-control" name="flavor" placeholder="e.g. Chocolate, Red Velvet, Ube">
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold small">Image</label>
            <input type="file" class="form-control" name="image" accept="image/*">
          </div>
          <div class="alert border-0 py-2 small" style="background:#fff0f5">
            <i class="bi bi-info-circle me-1" style="color:var(--primary)"></i>
            After adding, click the <strong><i class="bi bi-rulers"></i> ruler icon</strong> on the product card to add size options.
          </div>
          <button type="submit" class="btn btn-primary w-100">Add Product</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  csPagination('productsList', '.product-item', {
    perPage: 12,
    updateCount(n) {
      const lbl = document.getElementById('productsCountLabel');
      if (lbl) lbl.textContent = n + ' product' + (n !== 1 ? 's' : '');
    }
  });
});

function filterProducts() {
  const search = (document.getElementById('productSearch')?.value || '').toLowerCase();
  const cls    = document.getElementById('productClassFilter')?.value || '';
  if (window.csPagers?.['productsList']) {
    window.csPagers['productsList'].filter(search, cls || 'All');
  }
}
</script>
@endpush