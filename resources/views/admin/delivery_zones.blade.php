@extends('layouts.app')
@section('breadcrumb')<strong>Delivery Zones</strong>@endsection
@section('content')
@php
  $zoneTypes = [
    'free' => ['label'=>'Free',   'color'=>'#d1fae5','text'=>'#065f46'],
    'near' => ['label'=>'Near',   'color'=>'#dbeafe','text'=>'#1e40af'],
    'mid'  => ['label'=>'Mid',    'color'=>'#fef3c7','text'=>'#92400e'],
    'far'  => ['label'=>'Far',    'color'=>'#ffe4e6','text'=>'#9f1239'],
    'ooc'  => ['label'=>'OOC',    'color'=>'#f3e8ff','text'=>'#6b21a8'],
  ];
@endphp
<div>
  {{-- Header --}}
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0 page-title"><i class="bi bi-geo-alt me-2" style="color:var(--primary)"></i>Delivery Zones</h4>
      <p class="page-subtitle">Manage barangay delivery coverage and fees</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addZoneModal">
      <i class="bi bi-plus me-1"></i>Add Zone
    </button>
  </div>

  {{-- Filter bar --}}
  <div class="d-flex gap-2 flex-wrap mb-3 align-items-center">
    <div class="cs-search-bar flex-grow-1" style="max-width:280px">
      
      <input type="text" id="zoneSearch" class="form-control form-control-sm" placeholder="Search barangay…" oninput="filterZones()">
    </div>
    <select id="zoneTypeFilter" class="form-select form-select-sm" style="width:auto" onchange="filterZones()">
      <option value="">All Types</option>
      <option value="free">Free (Poblacion)</option>
      <option value="near">Near (₱50)</option>
      <option value="mid">Mid (₱80)</option>
      <option value="far">Far (₱120)</option>
      <option value="ooc">Out of Coverage</option>
    </select>
    <select id="zoneStatusFilter" class="form-select form-select-sm" style="width:auto" onchange="filterZones()">
      <option value="">All Status</option>
      <option value="active">Active</option>
      <option value="inactive">Inactive</option>
    </select>
  </div>

  {{-- Zone cards grid --}}
  <div class="row g-3" id="zonesList">
    @forelse($zones as $z)
    @php $zt = $zoneTypes[$z->zone_type] ?? $zoneTypes['near']; @endphp
    <div class="col-sm-6 col-lg-4 zone-item"
         data-search="{{ strtolower($z->barangay) }}"
         data-filter="{{ $z->zone_type }}"
         data-status="{{ $z->is_active ? 'active' : 'inactive' }}">
      <div class="card h-100 {{ !$z->is_active ? 'opacity-60' : '' }}" style="{{ !$z->is_active ? 'opacity:.6' : '' }}">
        <div class="card-body p-3">
          <div class="d-flex align-items-start justify-content-between gap-2">
            <div class="flex-grow-1">
              <div class="fw-bold" style="font-size:clamp(.82rem,1.8vw,.95rem)">{{ $z->barangay }}</div>
              <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                <span class="badge" style="background:{{ $zt['color'] }};color:{{ $zt['text'] }};font-size:clamp(.66rem,1.3vw,.7rem)">
                  {{ $zt['label'] }}
                </span>
                @if(!$z->is_active)
                  <span class="badge bg-secondary" style="font-size:.68rem">Hidden</span>
                @endif
              </div>
              <div class="mt-2 fw-bold" style="color:var(--primary);font-size:1rem">
                {{ $z->fee == 0 ? 'FREE' : '₱'.number_format($z->fee,0) }}
              </div>
              @if(!empty($z->estimated_time))
              <div class="mt-1 small text-muted">
                <i class="bi bi-clock me-1"></i>~{{ $z->estimated_time }}
              </div>
              @endif
            </div>
            <div class="dropdown">
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" style="padding:3px 7px">
                <i class="bi bi-three-dots-vertical"></i>
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:.8rem;min-width:150px">
                <li>
                  <button class="dropdown-item small py-2" onclick="openEditZone({{ $z->id }}, '{{ addslashes($z->barangay) }}', {{ $z->fee }}, '{{ $z->zone_type }}', '{{ addslashes($z->estimated_time ?? '') }}')">
                    <i class="bi bi-pencil me-2"></i>Edit Zone
                  </button>
                </li>
                <li>
                  <form action="{{ route('admin.delivery_zones.toggle', $z->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="dropdown-item small py-2">
                      <i class="bi {{ $z->is_active ? 'bi-eye-slash' : 'bi-eye' }} me-2"></i>{{ $z->is_active ? 'Hide' : 'Show' }}
                    </button>
                  </form>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                  <button class="dropdown-item small py-2 text-danger" onclick="confirmDelete('Delete {{ addslashes($z->barangay) }}? This cannot be undone.', () => document.getElementById('del-zone-{{ $z->id }}').submit())">
                    <i class="bi bi-trash me-2"></i>Delete
                  </button>
                  <form id="del-zone-{{ $z->id }}" action="{{ route('admin.delivery_zones.destroy', $z->id) }}" method="POST" style="display:none">@csrf</form>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
    @empty
    <div class="col-12 text-center py-5 text-muted">
      <i class="bi bi-geo-alt" style="font-size:3rem;color:#ddd;display:block;margin-bottom:10px"></i>
      No delivery zones yet.
    </div>
    @endforelse
  </div>

  {{-- Pagination --}}
  <div class="mt-3" id="zonesList_pager"></div>
</div>

{{-- Add Zone Modal --}}
<div class="modal fade" id="addZoneModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
    <div class="modal-content border-0" style="border-radius:1.2rem">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold">Add Delivery Zone</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form action="{{ route('admin.delivery_zones.store') }}" method="POST">
          @csrf
          <div class="mb-3">
            <label class="form-label fw-semibold small">Barangay Name</label>
            <input type="text" class="form-control" name="barangay" placeholder="e.g. Artacho" required>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label fw-semibold small">Delivery Fee (₱)</label>
              <input type="number" step="0.01" class="form-control" name="fee" placeholder="50.00" min="0" value="50">
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold small">Zone Type</label>
              <select class="form-select" name="zone_type">
                <option value="free">Free (Poblacion)</option>
                <option value="near" selected>Near (₱50)</option>
                <option value="mid">Mid (₱80)</option>
                <option value="far">Far (₱120)</option>
                <option value="ooc">Out of Coverage</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small"><i class="bi bi-clock me-1"></i>Estimated Delivery Time</label>
            <input type="text" class="form-control" name="estimated_time" placeholder="e.g. 20-30 mins" value="30-45 mins">
            <div class="form-text">Shown to customer at checkout</div>
          </div>
          <button type="submit" class="btn btn-primary w-100 fw-semibold">Add Zone</button>
        </form>
      </div>
    </div>
  </div>
</div>

{{-- Edit Zone Modal --}}
<div class="modal fade" id="editZoneModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
    <div class="modal-content border-0" style="border-radius:1.2rem">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold">Edit Delivery Zone</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="editZoneForm" method="POST">
          @csrf
          <div class="mb-3">
            <label class="form-label fw-semibold small">Barangay Name</label>
            <input type="text" class="form-control" id="editBarangay" name="barangay" required>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label fw-semibold small">Delivery Fee (₱)</label>
              <input type="number" step="0.01" class="form-control" id="editFee" name="fee" min="0">
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold small">Zone Type</label>
              <select class="form-select" id="editZoneType" name="zone_type">
                <option value="free">Free (Poblacion)</option>
                <option value="near">Near (₱50)</option>
                <option value="mid">Mid (₱80)</option>
                <option value="far">Far (₱120)</option>
                <option value="ooc">Out of Coverage</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small"><i class="bi bi-clock me-1"></i>Estimated Delivery Time</label>
            <input type="text" class="form-control" id="editEstimatedTime" name="estimated_time" placeholder="e.g. 20-30 mins">
            <div class="form-text">Shown to customer at checkout</div>
          </div>
          <button type="submit" class="btn btn-primary w-100 fw-semibold">Save Changes</button>
        </form>
      </div>
    </div>
  </div>
</div>

@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  csPagination('zonesList', '.zone-item', { perPage: 18 });
});

function filterZones() {
  const search = (document.getElementById('zoneSearch')?.value || '').toLowerCase();
  const type   = document.getElementById('zoneTypeFilter')?.value || '';
  const status = document.getElementById('zoneStatusFilter')?.value || '';
  const items  = document.querySelectorAll('.zone-item');
  items.forEach(el => {
    const ms = !search || (el.dataset.search || '').includes(search);
    const mt = !type   || el.dataset.filter === type;
    const mst= !status || el.dataset.status === status;
    el._csSearchMatch = ms && mt && mst;
  });
  if (window.csPagers?.['zonesList']) {
    window.csPagers['zonesList'].filter(search, type || 'All');
  }
}

function openEditZone(id, barangay, fee, type, eta) {
  document.getElementById('editZoneForm').action      = '/admin/delivery-zones/' + id + '/update';
  document.getElementById('editBarangay').value       = barangay;
  document.getElementById('editFee').value            = fee;
  document.getElementById('editZoneType').value       = type;
  document.getElementById('editEstimatedTime').value  = eta || '';
  new bootstrap.Modal(document.getElementById('editZoneModal')).show();
}
</script>
@endpush
