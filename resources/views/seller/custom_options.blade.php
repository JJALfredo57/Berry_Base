@extends('layouts.app')
@section('content')
<div>

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0">
        <i class="bi bi-palette me-2" style="color:var(--primary)"></i>Custom Order Settings
      </h4>
      <p class="text-muted small mb-0">Manage all options shown in the Customized Cake Order form</p>
    </div>
  </div>

  @if(session('msg'))
    <div class="alert alert-success border-0"><i class="bi bi-check-circle me-2"></i>{{ session('msg') }}</div>
  @endif
  @if(session('err'))
    <div class="alert alert-danger border-0"><i class="bi bi-exclamation-circle me-2"></i>{{ session('err') }}</div>
  @endif

  {{-- Tab Navigation --}}
  <ul class="nav nav-pills mb-4 flex-wrap gap-1" id="optionTabs">
    @foreach($types as $typeKey => $typeMeta)
    @php $isActiveTab = $activeTab === $typeKey; @endphp
    <li class="nav-item">
      <a class="nav-link {{ $isActiveTab ? 'active' : '' }}"
         href="#tab-{{ $typeKey }}"
         data-tab="{{ $typeKey }}"
         data-bs-toggle="pill"
         style="{{ $isActiveTab ? 'background:var(--primary);color:#fff' : '' }}">
        <i class="bi {{ $typeMeta['icon'] }} me-1"></i>{{ $typeMeta['label'] }}
        <span class="badge ms-1" style="background:rgba(255,255,255,.3);color:inherit;font-size:.68rem">
          {{ ($allOptions[$typeKey] ?? collect())->count() }}
        </span>
      </a>
    </li>
    @endforeach
  </ul>

  {{-- Tab Content --}}
  <div class="tab-content">
    @foreach($types as $typeKey => $typeMeta)
    @php
      $options = $allOptions[$typeKey] ?? collect();
      $archivedPager = $archivedOptionsByType[$typeKey];
      $archivedTotal = $archivedTotalsByType[$typeKey] ?? 0;
    @endphp
    <div class="tab-pane fade {{ $activeTab === $typeKey ? 'show active' : '' }}" id="tab-{{ $typeKey }}">

      <div class="row g-4">

        {{-- LEFT: Current options list --}}
        <div class="col-lg-8">
          <div class="card">
            <div class="card-body p-0">
              <div class="p-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="fw-semibold">
                  <i class="bi {{ $typeMeta['icon'] }} me-2" style="color:var(--primary)"></i>
                  {{ $typeMeta['label'] }}
                  <span class="text-muted fw-normal small ms-1">({{ $options->count() }} total)</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                  @if($typeMeta['has_price'])
                    <span class="badge" style="background:#fff0f5;color:var(--primary);font-size:.72rem">
                      <i class="bi bi-tag me-1"></i>Has price surcharge
                    </span>
                  @endif
                  <button type="button"
                          class="btn btn-sm btn-primary"
                          data-bs-toggle="modal"
                          data-bs-target="#addModal_{{ $typeKey }}"
                          title="Add new {{ strtolower($typeMeta['label']) }}">
                    <i class="bi bi-plus-lg"></i>
                  </button>
                </div>
              </div>

              @if($options->count() > 0)
              <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle small">
                  <thead class="table-light">
                    <tr>
                      <th class="ps-3" style="width:40px">Order</th>
                      <th>Label</th>
                      @if($typeMeta['has_price'])<th>Surcharge</th>@endif
                      <th>Description</th>
                      <th class="text-end pe-3">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($options as $opt)
                    <tr>
                      <td class="ps-3">
                        <div class="d-flex flex-column gap-0">
                          <form action="{{ route('seller.custom_options.sort_up', ['id' => $opt->id, 'tab' => $typeKey]) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-link btn-sm p-0 text-muted" style="line-height:1;font-size:.8rem" title="Move up">&uarr;</button>
                          </form>
                          <form action="{{ route('seller.custom_options.sort_down', ['id' => $opt->id, 'tab' => $typeKey]) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-link btn-sm p-0 text-muted" style="line-height:1;font-size:.8rem" title="Move down">&darr;</button>
                          </form>
                        </div>
                      </td>
                      <td class="fw-semibold">{{ $opt->label }}</td>
                      @if($typeMeta['has_price'])
                      <td>
                        @if($opt->price > 0)
                          <span class="fw-bold" style="color:var(--primary)">+PHP {{ number_format($opt->price,2) }}</span>
                        @else
                          <span class="text-muted">-</span>
                        @endif
                      </td>
                      @endif
                      <td class="text-muted">{{ $opt->description ?? '-' }}</td>
                      <td class="text-end pe-3">
                        <div class="d-flex gap-1 justify-content-end">
                          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal{{ $opt->id }}" title="Edit">
                            <i class="bi bi-pencil"></i>
                          </button>
                          <form action="{{ route('seller.custom_options.archive', ['id' => $opt->id, 'tab' => $typeKey]) }}" method="POST" onsubmit="return false;" onclick="confirmArchive('Archive &quot;{{ addslashes($opt->label) }}&quot;? It will be hidden from customers and can be restored anytime.', () => this.closest('form').submit())">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Archive">
                              <i class="bi bi-archive"></i>
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
              @else
              <div class="text-center text-muted py-5">
                <i class="bi {{ $typeMeta['icon'] }}" style="font-size:2.5rem;opacity:.25"></i>
                <div class="mt-2 small">No options yet. Click + to add one.</div>
              </div>
              @endif
            </div>
          </div>
        </div>

        {{-- RIGHT: Archived options --}}
        <div class="col-lg-4">
          <div class="card h-100">
            <div class="card-body p-0 d-flex flex-column">
              <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
                <div class="fw-semibold">
                  <i class="bi bi-archive me-2" style="color:var(--primary)"></i>
                  Archived Options
                </div>
                <span class="badge bg-secondary">{{ $archivedTotal }}</span>
              </div>

              @if($archivedTotal > 0)
                <div class="flex-grow-1">
                  @foreach($archivedPager as $ao)
                  <div class="d-flex align-items-start justify-content-between px-3 py-3 border-bottom" style="background:#f8f9fa">
                    <div class="pe-2">
                      <div class="fw-semibold text-muted">{{ $ao->label }}</div>
                      @if($ao->price > 0)
                        <span class="badge mt-1" style="background:#f3f4f6;color:#6b7280;font-size:.68rem">+PHP {{ number_format($ao->price,2) }}</span>
                      @endif
                      @if($ao->description)
                        <div class="text-muted small mt-1">{{ $ao->description }}</div>
                      @endif
                      <div class="text-muted mt-1" style="font-size:.72rem">Archived {{ \Carbon\Carbon::parse($ao->archived_at)->diffForHumans() }}</div>
                    </div>
                    <form action="{{ route('seller.custom_options.restore', ['id' => $ao->id, 'tab' => $typeKey]) }}" method="POST" class="d-inline">
                      @csrf
                      <input type="hidden" name="archived_{{ $typeKey }}_page" value="{{ $archivedPager->currentPage() }}">
                      <button type="submit" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-arrow-counterclockwise"></i>
                      </button>
                    </form>
                  </div>
                  @endforeach
                </div>
                @if($archivedPager->hasPages())
                  <div class="p-3 border-top">
                    {{ $archivedPager->appends(request()->query())->links('vendor.pagination.custom') }}
                  </div>
                @endif
              @else
                <div class="text-center text-muted py-5 px-3">
                  <i class="bi bi-archive" style="font-size:2rem;opacity:.25"></i>
                  <div class="mt-2 small">No archived options for this tab.</div>
                </div>
              @endif
            </div>
          </div>
        </div>

      </div>
    </div>
    @endforeach
  </div>

</div>

@push('modals')
@foreach($types as $typeKey => $typeMeta)
  {{-- Add modal --}}
  <div class="modal fade" id="addModal_{{ $typeKey }}" tabindex="-1" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0" style="border-radius:1.2rem">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold">Add New {{ $typeMeta['label'] }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body pt-3">
          <form action="{{ route('seller.custom_options.store', ['tab' => $typeKey]) }}" method="POST">
            @csrf
            <input type="hidden" name="type" value="{{ $typeKey }}">

            <div class="mb-3">
              <label class="form-label fw-semibold small">Label <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="label" required maxlength="120"
                     placeholder="@if($typeKey==='flavor') e.g. Ube Cheese
@elseif($typeKey==='size') e.g. 14&quot;
@elseif($typeKey==='layer') e.g. 5 Layers
@elseif($typeKey==='time_slot') e.g. 7:00 PM - 9:00 PM
@endif">
            </div>

            @if($typeMeta['has_price'])
            <div class="mb-3">
              <label class="form-label fw-semibold small">Price Surcharge (PHP)</label>
              <input type="number" step="0.01" min="0" class="form-control" name="price" placeholder="0.00">
              <div class="form-text">
                @if($typeKey==='size') Dagdag sa base price base sa sukat. @endif
                @if($typeKey==='layer') Dagdag sa base price base sa bilang ng layers. @endif
              </div>
            </div>
            @else
            <input type="hidden" name="price" value="0">
            @endif

            <div class="mb-3">
              <label class="form-label fw-semibold small">Description <span class="text-muted fw-normal">(optional)</span></label>
              <input type="text" class="form-control" name="description" placeholder="Short note for the customer" maxlength="255">
            </div>

            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-plus me-1"></i>Add Option
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- Edit modals --}}
  @foreach(($allOptions[$typeKey] ?? collect()) as $opt)
  <div class="modal fade" id="editModal{{ $opt->id }}" tabindex="-1" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0" style="border-radius:1.2rem">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold">Edit Option</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body pt-3">
          <form action="{{ route('seller.custom_options.update', ['id' => $opt->id, 'tab' => $typeKey]) }}" method="POST">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold small">Label <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="label" value="{{ $opt->label }}" required maxlength="120">
            </div>
            @if($typeMeta['has_price'])
            <div class="mb-3">
              <label class="form-label fw-semibold small">Price Surcharge (PHP)</label>
              <input type="number" step="0.01" min="0" class="form-control" name="price" value="{{ $opt->price }}">
              <div class="form-text">Idadagdag sa base price ng custom order</div>
            </div>
            @else
            <input type="hidden" name="price" value="0">
            @endif
            <div class="mb-3">
              <label class="form-label fw-semibold small">Description <span class="text-muted fw-normal">(optional)</span></label>
              <input type="text" class="form-control" name="description" value="{{ $opt->description }}" placeholder="Short note shown to customer" maxlength="255">
            </div>
            <button type="submit" class="btn btn-primary w-100">Save Changes</button>
          </form>
        </div>
      </div>
    </div>
  </div>
  @endforeach
@endforeach
@endpush

@push('scripts')
<script>
(function () {
  const setActiveStyles = (activeLink) => {
    document.querySelectorAll('#optionTabs .nav-link').forEach((link) => {
      link.style.background = '';
      link.style.color = '';
    });
    if (activeLink) {
      activeLink.style.background = 'var(--primary)';
      activeLink.style.color = '#fff';
    }
  };

  const updateUrlTab = (tabKey) => {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tabKey);
    history.replaceState({}, '', `${url.pathname}${url.search}#tab-${tabKey}`);
  };

  document.querySelectorAll('#optionTabs .nav-link').forEach((link) => {
    link.addEventListener('click', function () {
      const tabKey = this.getAttribute('data-tab');
      if (tabKey) updateUrlTab(tabKey);
      setActiveStyles(this);
    });
  });

  const initialActive = document.querySelector('#optionTabs .nav-link.active');
  setActiveStyles(initialActive);
})();
</script>
@endpush

@endsection
