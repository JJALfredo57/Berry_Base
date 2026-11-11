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

  {{-- ── Archived Section ───────────────────────────────── --}}
  @if($archivedCategories->total() > 0 || $archivedAddons->total() > 0)
  <div class="mb-4">
    <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-2 mb-3"
            type="button" data-bs-toggle="modal" data-bs-target="#archivedAddonsModal">
      <i class="bi bi-archive"></i>
      Archived
      <span class="badge bg-secondary">{{ $archivedCategories->total() + $archivedAddons->total() }}</span>
    </button>
    <div class="modal fade" id="archivedAddonsModal" tabindex="-1" aria-labelledby="archivedAddonsModalTitle" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content border-0" style="border-radius:1.2rem;overflow:hidden">
          <div class="modal-header border-0 pb-0 px-4 pt-4">
            <div>
              <h5 class="modal-title fw-bold" id="archivedAddonsModalTitle"><i class="bi bi-archive me-2" style="color:var(--primary)"></i>Archived Add-ons</h5>
              <div class="small text-muted">{{ $archivedCategories->total() + $archivedAddons->total() }} archived record(s)</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-0 mt-3">

          {{-- Archived Categories --}}
          @foreach($archivedCategories as $arc)
          <div class="archived-row archived-category-row border-bottom" style="background:#f8f9fa">
            <div class="archived-actions">
              <i class="bi bi-folder2 text-muted"></i>
              <div>
                <span class="fw-semibold text-muted">{{ $arc->name }}</span>
                <span class="badge bg-secondary ms-2" style="font-size:.68rem">Category</span>
              </div>
            </div>
            <div class="d-flex align-items-center gap-2">
              <span class="text-muted" style="font-size:.72rem">Archived {{ \Carbon\Carbon::parse($arc->archived_at)->diffForHumans() }}</span>
              <form action="{{ route('seller.addons.restore_category', $arc->id) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-success" title="Restore">
                  <i class="bi bi-arrow-counterclockwise me-1"></i>Restore
                </button>
              </form>
            </div>
          </div>
          @endforeach
          @if($archivedCategories->hasPages())
          <div class="p-3 border-bottom">
            {{ $archivedCategories->links('vendor.pagination.custom') }}
          </div>
          @endif

          {{-- Archived Add-ons --}}
          @foreach($archivedAddons as $ara)
          <div class="archived-row border-bottom" style="background:#fff">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-gift text-muted"></i>
              <div>
                <span class="fw-semibold text-muted">{{ $ara->name }}</span>
                <span class="text-muted small ms-1">— {{ $ara->category_name }}</span>
                @if($ara->price > 0)
                  <span class="badge ms-1 archived-price" style="background:#f3f4f6;color:#6b7280;font-size:.68rem">+₱{{ rtrim(rtrim(number_format($ara->price, 2), '0'), '.') }}</span>
                @endif
              </div>
            </div>
            <div class="archived-actions">
              <span class="text-muted" style="font-size:.72rem">Archived {{ \Carbon\Carbon::parse($ara->archived_at)->diffForHumans() }}</span>
              <form action="{{ route('seller.addons.restore', $ara->id) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-success" title="Restore">
                  <i class="bi bi-arrow-counterclockwise me-1"></i>Restore
                </button>
              </form>
            </div>
          </div>
          @endforeach
          @if($archivedAddons->hasPages())
          <div class="p-3">
            {{ $archivedAddons->links('vendor.pagination.custom') }}
          </div>
          @endif

          </div>
        </div>
      </div>
    </div>
  </div>
  @endif

  {{-- Categories + Addons --}}
  @forelse($categories as $cat)
  @php $catAddons = $addons[$cat->id] ?? collect(); @endphp
  <div class="card mb-4">
    {{-- Category Header --}}
    <div class="card-body border-bottom p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
        <i class="bi {{ $cat->icon }} fs-5" style="color:var(--primary)"></i>
        <div>
          <span class="fw-bold">{{ $cat->name }}</span>
          <span class="text-muted small ms-2">({{ $catAddons->count() }} items)</span>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm"
                data-bs-toggle="modal" data-bs-target="#editCatModal{{ $cat->id }}">
          <i class="bi bi-pencil"></i>
        </button>
        <form action="{{ route('seller.addons.archive_category', $cat->id) }}" method="POST" class="d-inline"
              onsubmit="return false;" onclick="confirmDelete('Archive &quot;{{ addslashes($cat->name) }}&quot;? This category and all its add-ons will be archived. You can restore them anytime.', () => this.closest('form').submit())">
          @csrf
          <button type="submit" class="btn btn-outline-secondary btn-sm" title="Archive category"><i class="bi bi-archive"></i></button>
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
            <th class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($catAddons as $a)
          <tr class="addon-row" data-search="{{ strtolower($a->name . ' ' . ($a->description ?? '')) }}">
            <td class="ps-3 fw-semibold">{{ $a->name }}</td>
            <td class="text-muted">{{ $a->description ?? '—' }}</td>
            <td>
              @if($a->price > 0)
                <span class="fw-bold addon-price" style="color:var(--primary)">+₱{{ rtrim(rtrim(number_format($a->price, 2), '0'), '.') }}</span>
              @else
                <span class="badge bg-success" style="font-size:.7rem">FREE</span>
              @endif
            </td>
            <td class="text-end pe-3">
              <div class="d-flex gap-1 justify-content-end">
                <button class="btn btn-outline-secondary btn-sm py-0 px-2"
                        data-bs-toggle="modal" data-bs-target="#editAddonModal{{ $a->id }}">
                  <i class="bi bi-pencil"></i>
                </button>
                <form action="{{ route('seller.addons.archive', $a->id) }}" method="POST" class="d-inline"
                      onsubmit="return false;" onclick="confirmDelete('Archive &quot;{{ addslashes($a->name) }}&quot;? It will be hidden and can be restored anytime.', () => this.closest('form').submit())">
                  @csrf
                  <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-2" title="Archive">
                    <i class="bi bi-archive"></i>
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
                      <input type="text" class="form-control" name="description" value="{{ $a->description ?? '' }}">
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
          <tr><td colspan="4" class="text-center text-muted py-3 ps-3">No add-ons in this category yet.</td></tr>
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
<style>
.archived-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.75rem;align-items:center;padding:.85rem 1.5rem}.archived-row>div:first-child{min-width:0}.archived-row .text-muted.small{white-space:nowrap}.archived-price,.addon-price{white-space:nowrap;display:inline-block;overflow-wrap:normal}.archived-actions{display:flex;align-items:center;gap:.5rem;white-space:nowrap}.archived-actions form,.archived-actions .btn{flex:0 0 auto}.archived-actions .btn{min-width:78px;white-space:nowrap}@media(max-width:575.98px){.archived-row{padding:.7rem .85rem;gap:.55rem;grid-template-columns:minmax(0,1fr) auto}.archived-row .d-flex.gap-2{gap:.45rem!important}.archived-row .fw-semibold{font-size:.82rem}.archived-row .text-muted.small{font-size:.68rem!important}.archived-actions{align-self:center}.archived-actions>span{display:none}.archived-actions .btn{min-width:68px;padding:.35rem .45rem;font-size:.72rem}.archived-price{margin-top:.25rem!important}}
@media(max-width:575.98px){#archivedAddonsModal .modal-body{padding-bottom:5rem!important}.archived-row{grid-template-columns:minmax(0,1fr) auto!important;padding:.75rem 1rem!important}.archived-row>div:first-child{min-width:0;overflow:hidden}.archived-row>div:first-child>div{min-width:0;overflow:hidden}.archived-row>div:first-child .fw-semibold,.archived-row>div:first-child .text-muted.small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.archived-row>div:first-child .badge{display:inline-block;max-width:100%;white-space:nowrap}.archived-actions{min-width:68px;justify-content:flex-end}.archived-actions>span{display:none!important}.table-responsive{overflow:visible}.table-responsive thead{display:none}.table-responsive tbody tr.addon-row{display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:center;column-gap:.5rem;min-height:52px;border-bottom:1px solid #eee}.table-responsive tbody tr.addon-row td{border:0!important;padding:.45rem .3rem!important;min-width:0}.table-responsive tbody tr.addon-row td:nth-child(1){grid-column:1;font-size:.78rem}.table-responsive tbody tr.addon-row td:nth-child(2){grid-column:1;grid-row:2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:.7rem;padding-top:0!important}.table-responsive tbody tr.addon-row td:nth-child(3){grid-column:2;grid-row:1/3;white-space:nowrap}.table-responsive tbody tr.addon-row td:nth-child(4){grid-column:3;grid-row:1/3;padding-right:.4rem!important}.table-responsive tbody tr.addon-row td:nth-child(4) .btn{width:28px;height:28px;padding:0!important;display:inline-flex;align-items:center;justify-content:center}.table-responsive tbody tr.addon-row td:nth-child(4) .d-flex{gap:.25rem!important}.addon-price{font-size:.76rem}}
</style>
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

document.addEventListener('DOMContentLoaded', () => {
  const archivedModal = document.getElementById('archivedAddonsModal');
  if (!archivedModal) return;
  if (archivedModal.parentElement !== document.body) document.body.append(archivedModal);
  const params = new URLSearchParams(window.location.search);
  if (params.get('archived_modal') === '1') bootstrap.Modal.getOrCreateInstance(archivedModal).show();
  archivedModal.addEventListener('shown.bs.modal', () => archivedModal.querySelectorAll('a[href*="archived_"]').forEach(link => {
    const url = new URL(link.href); url.searchParams.set('archived_modal', '1'); link.href = url.toString();
  }));
  document.querySelectorAll('#addCategoryModal form, #addAddonModal form').forEach(form => {
    form.addEventListener('input', () => form.querySelectorAll('input[required], select[required]').forEach(field => {
      const valid = field.checkValidity() && (field.name !== 'price' || /^\d+(\.\d{1,2})?$/.test(field.value));
      field.classList.toggle('is-invalid', !valid); field.classList.toggle('is-valid', valid);
    }));
    form.addEventListener('submit', event => { if (!form.checkValidity()) { event.preventDefault(); form.querySelector(':invalid')?.focus(); } });
  });
  document.querySelectorAll('#sellerMain .modal').forEach(modal => { if (modal.parentElement !== document.body) document.body.append(modal); });
  const iconChoices = [['bi-stars','⭐ Stars'],['bi-gift','🎁 Gift'],['bi-cake2','🎂 Cake'],['bi-heart-fill','❤️ Heart'],['bi-flower1','🌸 Flower'],['bi-balloon-fill','🎈 Balloon'],['bi-basket2-fill','🧺 Basket']];
  document.querySelectorAll('input[name="icon"]').forEach(input => {
    const select = document.createElement('select'); select.name = 'icon'; select.className = 'form-select';
    iconChoices.forEach(([value, label]) => { const option = new Option(label, value, false, value === input.value); select.add(option); });
    input.replaceWith(select);
  });
});
</script>
@endpush
