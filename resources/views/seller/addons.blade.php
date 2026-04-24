@extends('layouts.app')
@section('content')
<div>

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-gift me-2" style="color:var(--primary)"></i>Add-ons Manager</h4>
      <p class="text-muted small mb-0">Manage customization options for customer orders</p>
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-center">
      <div class="cs-search-bar" style="max-width:200px">
        
        <input type="text" id="addonSearch" class="form-control form-control-sm" placeholder="Search add-ons…" oninput="filterAddons()">
      </div>
      <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
        <i class="bi bi-folder-plus me-1"></i>New Category
      </button>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAddonModal">
        <i class="bi bi-plus me-1"></i>Add Add-on
      </button>
    </div>
  </div>

  @if(session('msg'))<div class="alert alert-success border-0"><i class="bi bi-check-circle me-2"></i>{{ session('msg') }}</div>@endif
  @if(session('err'))<div class="alert alert-danger border-0"><i class="bi bi-exclamation-circle me-2"></i>{{ session('err') }}</div>@endif

  {{-- Categories + Addons --}}
  @forelse($categories as $cat)
  @php $catAddons = $addons[$cat->id] ?? collect(); @endphp
  <div class="card mb-4">
    {{-- Category Header --}}
    <div class="card-body border-bottom p-3 d-flex align-items-center justify-content-between flex-wrap gap-2"
         style="background:{{ $cat->is_active ? '#fff' : '#f8f9fa' }}">
      <div class="d-flex align-items-center gap-2">
        <i class="bi {{ $cat->icon }} fs-5" style="color:var(--primary)"></i>
        <div>
          <span class="fw-bold">{{ $cat->name }}</span>
          <span class="text-muted small ms-2">({{ $catAddons->count() }} items)</span>
          @if(!$cat->is_active)
            <span class="badge bg-secondary ms-2" style="font-size:.7rem">Hidden</span>
          @endif
        </div>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm"
                data-bs-toggle="modal" data-bs-target="#editCatModal{{ $cat->id }}">
          <i class="bi bi-pencil"></i>
        </button>
        <form action="{{ route('seller.addons.toggle_category', $cat->id) }}" method="POST" class="d-inline">
          @csrf
          <button type="submit" class="btn btn-sm {{ $cat->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}"
                  title="{{ $cat->is_active ? 'Hide category' : 'Show category' }}">
            <i class="bi {{ $cat->is_active ? 'bi-eye-slash' : 'bi-eye' }}"></i>
          </button>
        </form>
        <form action="{{ route('seller.addons.destroy_category', $cat->id) }}" method="POST" class="d-inline"
              onsubmit="return false;" onclick="confirmDelete('Delete this category? All its add-ons must be removed first.', () => this.closest('form').submit())">
          @csrf
          <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
        </form>
      </div>
    </div>

    {{-- Addons Table --}}
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle small">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Add-on Name</th>
            <th>Description</th>
            <th>Price</th>
            <th>Status</th>
            <th class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($catAddons as $a)
          <tr class="addon-row" data-search="{{ strtolower($a->name . ' ' . ($a->description ?? '')) }}" style="{{ !$a->is_active ? 'opacity:.5' : '' }}">
            <td class="ps-3 fw-semibold">{{ $a->name }}</td>
            <td class="text-muted">{{ $a->description ?? '—' }}</td>
            <td>
              @if($a->price > 0)
                <span class="fw-bold" style="color:var(--primary)">+₱{{ number_format($a->price,2) }}</span>
              @else
                <span class="badge bg-success" style="font-size:.7rem">FREE</span>
              @endif
            </td>
            <td>
              @if($a->is_active)
                <span class="badge" style="background:#d1fae5;color:#065f46;font-size:.72rem">Visible</span>
              @else
                <span class="badge bg-secondary" style="font-size:.72rem">Hidden</span>
              @endif
            </td>
            <td class="text-end pe-3">
              <div class="d-flex gap-1 justify-content-end">
                <button class="btn btn-outline-secondary btn-sm py-0 px-2"
                        data-bs-toggle="modal" data-bs-target="#editAddonModal{{ $a->id }}">
                  <i class="bi bi-pencil"></i>
                </button>
                <form action="{{ route('seller.addons.toggle', $a->id) }}" method="POST" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-sm py-0 px-2 {{ $a->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}"
                          title="{{ $a->is_active ? 'Hide' : 'Show' }}">
                    <i class="bi {{ $a->is_active ? 'bi-eye-slash' : 'bi-eye' }}"></i>
                  </button>
                </form>
                <form action="{{ route('seller.addons.destroy', $a->id) }}" method="POST" class="d-inline"
                      onsubmit="return false;" onclick="confirmDelete('Delete {{ $a->name }}? This cannot be undone.', () => this.closest('form').submit())">
                  @csrf
                  <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>

          {{-- Edit Addon Modal --}}
          <div class="modal fade" id="editAddonModal{{ $a->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content border-0" style="border-radius:1.2rem">
                <div class="modal-header border-0">
                  <h5 class="modal-title fw-bold">Edit Add-on</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <form action="{{ route('seller.addons.update', $a->id) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                      <label class="form-label fw-semibold small">Category</label>
                      <select class="form-select" name="category_id" required>
                        @foreach($categories as $c)
                          <option value="{{ $c->id }}" {{ $a->category_id==$c->id?'selected':'' }}>{{ $c->name }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div class="mb-3">
                      <label class="form-label fw-semibold small">Add-on Name</label>
                      <input type="text" class="form-control" name="name" value="{{ $a->name }}" required>
                    </div>
                    <div class="mb-3">
                      <label class="form-label fw-semibold small">Description <span class="text-muted fw-normal">(optional)</span></label>
                      <input type="text" class="form-control" name="description" value="{{ $a->description }}">
                    </div>
                    <div class="mb-3">
                      <label class="form-label fw-semibold small">Additional Price (₱)</label>
                      <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" step="0.01" min="0" class="form-control" name="price" value="{{ $a->price }}">
                      </div>
                      <div class="form-text">Set to 0 for free add-ons</div>
                    </div>
                    <div class="d-flex gap-2 justify-content-end">
                      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>

          @empty
          <tr><td colspan="5" class="text-center text-muted py-3 ps-3">No add-ons in this category yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Edit Category Modal --}}
  <div class="modal fade" id="editCatModal{{ $cat->id }}" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0" style="border-radius:1.2rem">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold">Edit Category</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form action="{{ route('seller.addons.update_category', $cat->id) }}" method="POST">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold small">Category Name</label>
              <input type="text" class="form-control" name="name" value="{{ $cat->name }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold small">Icon <span class="text-muted fw-normal">(Bootstrap Icons class)</span></label>
              <input type="text" class="form-control font-monospace" name="icon" value="{{ $cat->icon }}" placeholder="bi-palette">
              <div class="form-text">Browse icons at <a href="https://icons.getbootstrap.com" target="_blank">icons.getbootstrap.com</a></div>
            </div>
            <div class="d-flex gap-2 justify-content-end">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  @empty
  <div class="card text-center py-5">
    <i class="bi bi-stars" style="font-size:3rem;color:#ddd"></i>
    <p class="text-muted mt-3">No categories yet. Add one to get started.</p>
  </div>
  @endforelse
</div>

{{-- Add Category Modal --}}
<div class="modal fade" id="addCategoryModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0" style="border-radius:1.2rem">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-folder-plus me-2"></i>New Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form action="{{ route('seller.addons.store_category') }}" method="POST">
          @csrf
          <div class="mb-3">
            <label class="form-label fw-semibold small">Category Name</label>
            <input type="text" class="form-control" name="name" placeholder="e.g. 🎨 Design / Decorations" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Icon</label>
            <input type="text" class="form-control font-monospace" name="icon" value="bi-stars" placeholder="bi-stars">
            <div class="form-text">Bootstrap Icons class — see <a href="https://icons.getbootstrap.com" target="_blank">icons.getbootstrap.com</a></div>
          </div>
          <div class="d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-plus me-1"></i>Add Category</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

{{-- Add Addon Modal --}}
<div class="modal fade" id="addAddonModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0" style="border-radius:1.2rem">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Add New Add-on</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form action="{{ route('seller.addons.store') }}" method="POST">
          @csrf
          <div class="mb-3">
            <label class="form-label fw-semibold small">Category</label>
            <select class="form-select" name="category_id" required>
              <option value="">-- Select Category --</option>
              @foreach($categories as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Add-on Name</label>
            <input type="text" class="form-control" name="name" placeholder="e.g. Fresh Strawberries" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Description <span class="text-muted fw-normal">(optional)</span></label>
            <input type="text" class="form-control" name="description" placeholder="Short description">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Additional Price (₱)</label>
            <div class="input-group">
              <span class="input-group-text">₱</span>
              <input type="number" step="0.01" min="0" class="form-control" name="price" value="0">
            </div>
            <div class="form-text">Set to 0 for free add-ons</div>
          </div>
          <div class="d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-plus me-1"></i>Add</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
@push('scripts')
<script>
function filterAddons() {
  const q = (document.getElementById('addonSearch')?.value || '').toLowerCase();
  document.querySelectorAll('.addon-row').forEach(row => {
    const match = !q || (row.dataset.search || '').includes(q);
    row.style.display = match ? '' : 'none';
  });
  // Show/hide empty tbody placeholders
  document.querySelectorAll('tbody').forEach(tb => {
    const visible = [...tb.querySelectorAll('.addon-row')].filter(r => r.style.display !== 'none');
    const empty   = tb.querySelector('.addon-empty');
    if (empty) empty.style.display = visible.length === 0 ? '' : 'none';
  });
}
</script>
@endpush
