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
    <li class="nav-item">
      <a class="nav-link {{ $loop->first ? 'active' : '' }}"
         href="#tab-{{ $typeKey }}" data-bs-toggle="pill"
         style="{{ $loop->first ? 'background:var(--primary);color:#fff' : '' }}">
        <i class="bi {{ $typeMeta['icon'] }} me-1"></i>{{ $typeMeta['label'] }}
        <span class="badge ms-1"
              style="background:rgba(255,255,255,.3);color:inherit;font-size:.68rem">
          {{ ($allOptions[$typeKey] ?? collect())->where('is_active',1)->count() }}
        </span>
      </a>
    </li>
    @endforeach
  </ul>

  {{-- Tab Content --}}
  <div class="tab-content">
    @foreach($types as $typeKey => $typeMeta)
    @php $options = $allOptions[$typeKey] ?? collect(); @endphp
    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="tab-{{ $typeKey }}">

      <div class="row g-4">

        {{-- LEFT: Current options list --}}
        <div class="col-lg-8">
          <div class="card">
            <div class="card-body p-0">
              <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
                <div class="fw-semibold">
                  <i class="bi {{ $typeMeta['icon'] }} me-2" style="color:var(--primary)"></i>
                  {{ $typeMeta['label'] }}
                  <span class="text-muted fw-normal small ms-1">({{ $options->count() }} total)</span>
                </div>
                @if($typeMeta['has_price'])
                  <span class="badge" style="background:#fff0f5;color:var(--primary);font-size:.72rem">
                    <i class="bi bi-tag me-1"></i>Has price surcharge
                  </span>
                @endif
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
                      <th>Status</th>
                      <th class="text-end pe-3">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($options as $opt)
                    <tr style="{{ !$opt->is_active ? 'opacity:.45' : '' }}">
                      {{-- Sort buttons --}}
                      <td class="ps-3">
                        <div class="d-flex flex-column gap-0">
                          <form action="{{ route('seller.custom_options.sort_up', $opt->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-link btn-sm p-0 text-muted"
                                    style="line-height:1;font-size:.8rem" title="Move up">▲</button>
                          </form>
                          <form action="{{ route('seller.custom_options.sort_down', $opt->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-link btn-sm p-0 text-muted"
                                    style="line-height:1;font-size:.8rem" title="Move down">▼</button>
                          </form>
                        </div>
                      </td>
                      <td class="fw-semibold">{{ $opt->label }}</td>
                      @if($typeMeta['has_price'])
                      <td>
                        @if($opt->price > 0)
                          <span class="fw-bold" style="color:var(--primary)">+₱{{ number_format($opt->price,2) }}</span>
                        @else
                          <span class="text-muted">—</span>
                        @endif
                      </td>
                      @endif
                      <td class="text-muted">{{ $opt->description ?? '—' }}</td>
                      <td>
                        @if($opt->is_active)
                          <span class="badge" style="background:#d1fae5;color:#065f46;font-size:.7rem">Visible</span>
                        @else
                          <span class="badge bg-secondary" style="font-size:.7rem">Hidden</span>
                        @endif
                      </td>
                      <td class="text-end pe-3">
                        <div class="d-flex gap-1 justify-content-end">
                          {{-- Edit --}}
                          <button class="btn btn-outline-secondary btn-sm"
                                  data-bs-toggle="modal"
                                  data-bs-target="#editModal{{ $opt->id }}"
                                  title="Edit">
                            <i class="bi bi-pencil"></i>
                          </button>
                          {{-- Toggle --}}
                          <form action="{{ route('seller.custom_options.toggle', $opt->id) }}" method="POST">
                            @csrf
                            <button type="submit"
                                    class="btn btn-sm {{ $opt->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}"
                                    title="{{ $opt->is_active ? 'Hide' : 'Show' }}">
                              <i class="bi {{ $opt->is_active ? 'bi-eye-slash' : 'bi-eye' }}"></i>
                            </button>
                          </form>
                          {{-- Delete --}}
                          <form action="{{ route('admin.custom_options.destroy', $opt->id) }}" method="POST"
                                onsubmit="return false;" onclick="confirmDelete('Delete \'{{ addslashes($opt->label) }}\'? This cannot be undone.', () => this.closest('form').submit())">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete">
                              <i class="bi bi-trash"></i>
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>

                    {{-- Edit Modal --}}
                    <div class="modal fade" id="editModal{{ $opt->id }}" tabindex="-1">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0" style="border-radius:1.2rem">
                          <div class="modal-header border-0">
                            <h5 class="modal-title fw-bold">Edit Option</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                          </div>
                          <div class="modal-body">
                            <form action="{{ route('seller.custom_options.update', $opt->id) }}" method="POST">
                              @csrf
                              <div class="mb-3">
                                <label class="form-label fw-semibold small">Label <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="label"
                                       value="{{ $opt->label }}" required maxlength="120">
                              </div>
                              @if($typeMeta['has_price'])
                              <div class="mb-3">
                                <label class="form-label fw-semibold small">Price Surcharge (₱)</label>
                                <input type="number" step="0.01" min="0" class="form-control"
                                       name="price" value="{{ $opt->price }}">
                                <div class="form-text">Idadagdag sa base price ng custom order</div>
                              </div>
                              @else
                              <input type="hidden" name="price" value="0">
                              @endif
                              <div class="mb-3">
                                <label class="form-label fw-semibold small">Description <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="text" class="form-control" name="description"
                                       value="{{ $opt->description }}"
                                       placeholder="Short note shown to customer" maxlength="255">
                              </div>
                              <button type="submit" class="btn btn-primary w-100">Save Changes</button>
                            </form>
                          </div>
                        </div>
                      </div>
                    </div>

                    @endforeach
                  </tbody>
                </table>
              </div>
              @else
              <div class="text-center text-muted py-5">
                <i class="bi {{ $typeMeta['icon'] }}" style="font-size:2.5rem;opacity:.25"></i>
                <div class="mt-2 small">No options yet. Add one on the right.</div>
              </div>
              @endif
            </div>
          </div>
        </div>

        {{-- RIGHT: Add new option --}}
        <div class="col-lg-4">
          <div class="card">
            <div class="card-body p-4">
              <h6 class="fw-bold mb-3">
                <i class="bi bi-plus-circle me-2" style="color:var(--primary)"></i>
                Add New {{ $typeMeta['label'] }}
              </h6>
              <form action="{{ route('seller.custom_options.store') }}" method="POST">
                @csrf
                <input type="hidden" name="type" value="{{ $typeKey }}">

                <div class="mb-3">
                  <label class="form-label fw-semibold small">Label <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="label" required maxlength="120"
                         placeholder="@if($typeKey==='flavor') e.g. Ube Cheese
@elseif($typeKey==='size') e.g. 14&quot;
@elseif($typeKey==='layer') e.g. 5 Layers
@elseif($typeKey==='complexity') e.g. Premium
@elseif($typeKey==='time_slot') e.g. 7:00 PM – 9:00 PM
@endif">
                </div>

                @if($typeMeta['has_price'])
                <div class="mb-3">
                  <label class="form-label fw-semibold small">Price Surcharge (₱)</label>
                  <input type="number" step="0.01" min="0" class="form-control"
                         name="price" placeholder="0.00">
                  <div class="form-text">
                    @if($typeKey==='size') Dagdag sa base price base sa sukat. @endif
                    @if($typeKey==='complexity') Dagdag sa base price base sa kahirapan ng design. @endif
                  </div>
                </div>
                @else
                <input type="hidden" name="price" value="0">
                @endif

                <div class="mb-3">
                  <label class="form-label fw-semibold small">Description <span class="text-muted fw-normal">(optional)</span></label>
                  <input type="text" class="form-control" name="description"
                         placeholder="Short note for the customer" maxlength="255">
                </div>

                <button type="submit" class="btn btn-primary w-100">
                  <i class="bi bi-plus me-1"></i>Add Option
                </button>
              </form>

              {{-- Guide --}}
              <div class="mt-3 p-3 rounded small" style="background:#f8f9fa">
                <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1" style="color:var(--primary)"></i>Tips</div>
                @if($typeKey==='flavor')
                  <div class="text-muted">Mga lasa na available sa custom cakes. Pwedeng mag-add ng seasonal flavors.</div>
                @elseif($typeKey==='size')
                  <div class="text-muted">Diameter ng cake. Ang price surcharge ay idadagdag sa ₱1,200 base price.</div>
                @elseif($typeKey==='layer')
                  <div class="text-muted">Bilang ng layers ng cake.</div>
                @elseif($typeKey==='complexity')
                  <div class="text-muted">Kahirapan ng design. Mas mataas ang complexity, mas mataas ang surcharge.</div>
                @elseif($typeKey==='time_slot')
                  <div class="text-muted">Mga available na oras ng delivery/pickup. Format: "9:00 AM – 11:00 AM"</div>
                @endif
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
    @endforeach
  </div>

</div>
@push('scripts')
<script>
// Keep active tab styled
document.querySelectorAll('#optionTabs .nav-link').forEach(link => {
  link.addEventListener('click', function () {
    document.querySelectorAll('#optionTabs .nav-link').forEach(l => {
      l.style.background = '';
      l.style.color = '';
    });
    this.style.background = 'var(--primary)';
    this.style.color = '#fff';
  });
});
</script>
@endpush
@endsection
