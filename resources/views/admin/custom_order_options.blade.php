@extends('layouts.app')
@section('content')
<div>

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0">
        <i class="bi bi-palette me-2" style="color:var(--primary)"></i>Custom Order Settings
      </h4>
      <p class="text-muted small mb-0">Manage the options available to customers when placing a custom cake order.</p>
    </div>
  </div>

  @if(session('msg'))
    <div class="alert alert-success border-0" style="border-radius:.9rem">
      <i class="bi bi-check-circle me-2"></i>{{ session('msg') }}
    </div>
  @endif
  @if(session('err'))
    <div class="alert alert-danger border-0" style="border-radius:.9rem">
      <i class="bi bi-exclamation-circle me-2"></i>{{ session('err') }}
    </div>
  @endif

  @php
    $typeInfo = [
      'flavor'     => ['label'=>'Flavors',           'icon'=>'bi-droplet',  'has_price'=>false, 'has_desc'=>false, 'price_note'=>'',                         'color'=>'#6366f1'],
      'size'       => ['label'=>'Sizes / Diameter',  'icon'=>'bi-rulers',   'has_price'=>true,  'has_desc'=>true,  'price_note'=>'Surcharge added to base',  'color'=>'#0ea5e9'],
      'layer'      => ['label'=>'Number of Layers',  'icon'=>'bi-layers',   'has_price'=>false, 'has_desc'=>false, 'price_note'=>'',                         'color'=>'#f59e0b'],
      'complexity' => ['label'=>'Design Complexity', 'icon'=>'bi-magic',    'has_price'=>true,  'has_desc'=>true,  'price_note'=>'Surcharge added to base',  'color'=>'#ec4899'],
      'time_slot'  => ['label'=>'Time Slots',        'icon'=>'bi-clock',    'has_price'=>false, 'has_desc'=>false, 'price_note'=>'',                         'color'=>'#10b981'],
    ];
  @endphp

  <div class="row g-4">
    @foreach($typeInfo as $typeKey => $info)
    @php $opts = $optionsByType[$typeKey] ?? collect(); @endphp
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-body p-4">

          {{-- Section Header --}}
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="d-flex align-items-center gap-2">
              <div style="width:36px;height:36px;border-radius:10px;background:{{ $info['color'] }}22;display:flex;align-items:center;justify-content:center">
                <i class="bi {{ $info['icon'] }}" style="color:{{ $info['color'] }};font-size:1.1rem"></i>
              </div>
              <div>
                <div class="fw-bold">{{ $info['label'] }}</div>
                <div class="text-muted" style="font-size:.72rem">{{ $opts->count() }} option(s)</div>
              </div>
            </div>
            <button class="btn btn-sm btn-outline-primary"
                    data-bs-toggle="modal" data-bs-target="#addModal_{{ $typeKey }}">
              <i class="bi bi-plus me-1"></i>Add
            </button>
          </div>

          {{-- Options List --}}
          @if($opts->count() > 0)
          <div class="d-flex flex-column gap-2">
            @foreach($opts as $opt)
            <div class="d-flex align-items-center gap-2 p-2 rounded {{ $opt->is_active ? '' : 'opacity-50' }}"
                 style="border:1.5px solid {{ $opt->is_active ? '#e9ecef' : '#e9ecef' }};background:{{ $opt->is_active ? '#fff' : '#f8f9fa' }}">

              {{-- Sort buttons --}}
              <div class="d-flex flex-column gap-0" style="font-size:.65rem">
                <form action="{{ route('admin.custom_options.sort_up', $opt->id) }}" method="POST" class="m-0">
                  @csrf
                  <button type="submit" class="btn btn-link p-0 text-muted" style="line-height:1;font-size:.7rem" title="Move up">▲</button>
                </form>
                <form action="{{ route('admin.custom_options.sort_down', $opt->id) }}" method="POST" class="m-0">
                  @csrf
                  <button type="submit" class="btn btn-link p-0 text-muted" style="line-height:1;font-size:.7rem" title="Move down">▼</button>
                </form>
              </div>

              {{-- Label & desc --}}
              <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold small text-truncate">{{ $opt->label }}</div>
                @if($opt->description)
                  <div class="text-muted" style="font-size:.7rem">{{ $opt->description }}</div>
                @endif
              </div>

              {{-- Price badge --}}
              @if($info['has_price'])
                <span class="badge flex-shrink-0"
                      style="background:{{ $info['color'] }}22;color:{{ $info['color'] }};font-size:.7rem;white-space:nowrap">
                  {{ $opt->price > 0 ? '+₱'.number_format($opt->price,2) : 'Free' }}
                </span>
              @endif

              {{-- Actions --}}
              <div class="d-flex gap-1 flex-shrink-0">
                {{-- Edit --}}
                <button class="btn btn-outline-secondary btn-sm py-0 px-2"
                        data-bs-toggle="modal" data-bs-target="#editModal_{{ $opt->id }}"
                        title="Edit">
                  <i class="bi bi-pencil" style="font-size:.75rem"></i>
                </button>
                {{-- Toggle --}}
                <form action="{{ route('admin.custom_options.toggle', $opt->id) }}" method="POST" class="m-0">
                  @csrf
                  <button type="submit"
                          class="btn btn-sm py-0 px-2 {{ $opt->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}"
                          title="{{ $opt->is_active ? 'Hide' : 'Show' }}">
                    <i class="bi {{ $opt->is_active ? 'bi-eye-slash' : 'bi-eye' }}" style="font-size:.75rem"></i>
                  </button>
                </form>
                {{-- Delete --}}
                <form action="{{ route('admin.custom_options.destroy', $opt->id) }}" method="POST" class="m-0"
                      onsubmit="return false;" onclick="confirmDelete('Delete \'{{ addslashes($opt->label) }}\'? This cannot be undone.', () => this.closest('form').submit())">
                  @csrf
                  <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Delete">
                    <i class="bi bi-trash" style="font-size:.75rem"></i>
                  </button>
                </form>
              </div>
            </div>

            {{-- Edit Modal per option --}}
            <div class="modal fade" id="editModal_{{ $opt->id }}" tabindex="-1">
              <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content border-0" style="border-radius:1.2rem">
                  <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title fw-bold">Edit {{ $info['label'] }} Option</h6>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <form action="{{ route('admin.custom_options.update', $opt->id) }}" method="POST">
                      @csrf
                      <div class="mb-3">
                        <label class="form-label fw-semibold small">Label</label>
                        <input type="text" class="form-control form-control-sm" name="label"
                               value="{{ $opt->label }}" required maxlength="120">
                      </div>
                      @if($info['has_price'])
                      <div class="mb-3">
                        <label class="form-label fw-semibold small">
                          Surcharge (₱)
                          <span class="text-muted fw-normal">{{ $info['price_note'] }}</span>
                        </label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                               name="price" value="{{ $opt->price }}">
                      </div>
                      @else
                        <input type="hidden" name="price" value="0">
                      @endif
                      @if($info['has_desc'])
                      <div class="mb-3">
                        <label class="form-label fw-semibold small">Description <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" class="form-control form-control-sm" name="description"
                               value="{{ $opt->description }}" maxlength="255">
                      </div>
                      @else
                        <input type="hidden" name="description" value="">
                      @endif
                      <button type="submit" class="btn btn-primary btn-sm w-100">Save</button>
                    </form>
                  </div>
                </div>
              </div>
            </div>

            @endforeach
          </div>
          @else
          <div class="text-center py-4 text-muted small">
            <i class="bi {{ $info['icon'] }}" style="font-size:2rem;opacity:.25"></i>
            <div class="mt-2">No options yet. Click <strong>+ Add</strong> to start.</div>
          </div>
          @endif

        </div>
      </div>
    </div>

    {{-- Add Modal per type --}}
    <div class="modal fade" id="addModal_{{ $typeKey }}" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0" style="border-radius:1.2rem">
          <div class="modal-header border-0 pb-0">
            <div>
              <h6 class="modal-title fw-bold">Add {{ $info['label'] }} Option</h6>
            </div>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <form action="{{ route('admin.custom_options.store') }}" method="POST">
              @csrf
              <input type="hidden" name="type" value="{{ $typeKey }}">
              <div class="mb-3">
                <label class="form-label fw-semibold small">Label</label>
                <input type="text" class="form-control form-control-sm" name="label" required maxlength="120"
                  placeholder="@if($typeKey=='flavor') e.g. Ube Cheese
                  @elseif($typeKey=='size') e.g. 14&quot;
                  @elseif($typeKey=='layer') e.g. 5 Layers
                  @elseif($typeKey=='complexity') e.g. Premium
                  @else e.g. 7:00 PM – 9:00 PM @endif">
              </div>
              @if($info['has_price'])
              <div class="mb-3">
                <label class="form-label fw-semibold small">
                  Surcharge (₱)
                  <span class="text-muted fw-normal small">{{ $info['price_note'] }}</span>
                </label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="price" value="0">
              </div>
              @else
                <input type="hidden" name="price" value="0">
              @endif
              @if($info['has_desc'])
              <div class="mb-3">
                <label class="form-label fw-semibold small">Description <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" class="form-control form-control-sm" name="description" maxlength="255"
                       placeholder="Short note for customers">
              </div>
              @else
                <input type="hidden" name="description" value="">
              @endif
              <button type="submit" class="btn btn-primary btn-sm w-100">
                <i class="bi bi-plus me-1"></i>Add Option
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

    @endforeach
  </div>
</div>
@endsection
