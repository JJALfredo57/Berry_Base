@extends('layouts.app')
@section('content')
<style>
.input-validated { transition: border-color .2s, box-shadow .2s; }
.input-validated.is-valid   { border-color:#16a34a !important; box-shadow:0 0 0 3px rgba(22,163,74,.15) !important; }
.input-validated.is-invalid { border-color:#ef4444 !important; box-shadow:0 0 0 3px rgba(239,68,68,.15) !important; }
@keyframes shake {
  0%,100%{transform:translateX(0)}
  20%{transform:translateX(-6px)}
  40%{transform:translateX(6px)}
  60%{transform:translateX(-4px)}
  80%{transform:translateX(4px)}
}
.shake { animation: shake .4s ease; }
.field-msg { font-size:clamp(.7rem,1.4vw,.75rem); margin-top:4px; min-height:18px; transition:color .2s; }
.field-msg.ok  { color:#16a34a; }
.field-msg.err { color:#ef4444; }
</style>

<div>
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-bicycle me-2" style="color:var(--primary)"></i>Riders</h4>
      <p class="text-muted small mb-0">Manage delivery riders</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRiderModal">
      <i class="bi bi-plus-lg me-1"></i>Add Rider
    </button>
  </div>

  @if(session('msg'))<div class="alert alert-success border-0"><i class="bi bi-check-circle me-2"></i>{{ session('msg') }}</div>@endif
  @if(session('err'))<div class="alert alert-danger border-0"><i class="bi bi-exclamation-circle me-2"></i>{{ session('err') }}</div>@endif

  @if($riders->isEmpty())
  <div class="card"><div class="card-body text-center py-5 text-muted">
    <i class="bi bi-bicycle" style="font-size:3rem;opacity:.3"></i>
    <div class="mt-2">No riders yet. Add your first rider above.</div>
  </div></div>
  @else
  <div class="row g-3">
    @foreach($riders as $r)
    <div class="col-md-6 col-lg-4">
      <div class="card h-100" style="{{ !$r->is_active ? 'opacity:.65;filter:grayscale(.3)' : '' }}">
        <div class="card-body p-4">

          {{-- Header --}}
          <div class="d-flex align-items-center gap-3 mb-3">
            <div style="width:52px;height:52px;border-radius:50%;background:{{ $r->is_active ? 'var(--primary)' : '#9ca3af' }};display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.3rem;font-weight:700;flex-shrink:0">
              {{ strtoupper(substr($r->name,0,1)) }}
            </div>
            <div class="flex-grow-1 min-width-0">
              <div class="fw-bold">{{ $r->name }}
                @if($r->nickname) <span class="text-muted fw-normal small">({{ $r->nickname }})</span>@endif
              </div>
              <div class="small text-muted">
                @if(!empty($r->phone))
                  <a href="tel:{{ $r->phone }}" class="text-decoration-none text-muted">{{ $r->phone }}</a>
                @else
                  <span>No phone number</span>
                @endif
              </div>
              <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                <span class="badge {{ $r->is_active ? 'bg-success' : 'bg-secondary' }}" style="font-size:clamp(.62rem,1.2vw,.65rem)">
                  {{ $r->is_active ? 'Active' : 'Inactive' }}
                </span>
                @if($r->vehicle_type)
                <span class="badge" style="background:#f0f9ff;color:#0369a1;font-size:clamp(.62rem,1.2vw,.65rem)">
                  {{ $r->vehicle_type === 'Motorcycle' ? '🏍️' : ($r->vehicle_type === 'Bicycle' ? '🚲' : '🛺') }}
                  {{ $r->vehicle_type }}
                  @if($r->license_plate) — {{ $r->license_plate }}@endif
                </span>
                @endif
              </div>
            </div>
          </div>

          {{-- Stats --}}
          <div class="row g-2 mb-3 text-center">
            <div class="col-4">
              <div style="background:#f9fafb;border-radius:8px;padding:8px">
                <div class="fw-bold" style="color:var(--primary)">{{ $deliveries[$r->id] ?? 0 }}</div>
                <div class="text-muted" style="font-size:clamp(.66rem,1.3vw,.7rem)">Deliveries</div>
              </div>
            </div>
            <div class="col-4">
              <div style="background:#f9fafb;border-radius:8px;padding:8px">
                <div class="fw-bold {{ ($incidents[$r->id] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">{{ $incidents[$r->id] ?? 0 }}</div>
                <div class="text-muted" style="font-size:clamp(.66rem,1.3vw,.7rem)">Incidents</div>
              </div>
            </div>
            <div class="col-4">
              <div style="background:#f9fafb;border-radius:8px;padding:8px">
                <div class="fw-bold text-warning">{{ ($r->rating_avg ?? 0) > 0 ? number_format($r->rating_avg,1) : '—' }}</div>
                <div class="text-muted" style="font-size:clamp(.66rem,1.3vw,.7rem)">Rating</div>
              </div>
            </div>
          </div>

          {{-- Emergency Contact --}}
          @if($r->emergency_contact_name)
          <div class="mb-3 p-2 rounded-2" style="background:#fff7ed;font-size:.78rem">
            <i class="bi bi-person-heart me-1" style="color:#ea580c"></i>
            <strong>Emergency:</strong> {{ $r->emergency_contact_name }}
          </div>
          @endif

          {{-- Buttons --}}
          <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm flex-fill" data-bs-toggle="modal" data-bs-target="#editRiderModal{{ $r->id }}">
              <i class="bi bi-pencil me-1"></i>Edit
            </button>
            <form action="{{ route('admin.riders.toggle', $r->id) }}" method="POST" class="flex-fill">
              @csrf
              <button type="submit" class="btn btn-sm w-100 {{ $r->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                <i class="bi bi-{{ $r->is_active ? 'pause-circle' : 'play-circle' }} me-1"></i>
                {{ $r->is_active ? 'Deactivate' : 'Activate' }}
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

    {{-- Edit Modal --}}
    <div class="modal fade" id="editRiderModal{{ $r->id }}" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:480px">
        <div class="modal-content border-0" style="border-radius:1.2rem;overflow:hidden">
          <div class="modal-header border-0 pt-4 px-4">
            <h5 class="modal-title fw-bold">✏️ Edit Rider — {{ $r->name }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form action="{{ route('admin.riders.update', $r->id) }}" method="POST" onsubmit="return validateRiderForm(this)">
            @csrf
            <div class="modal-body px-4 pt-0 pb-3">
              <div class="row g-3">
                <div class="col-sm-6">
                  <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control input-validated" name="name" value="{{ $r->name }}" required
                         data-rule="name" data-label="Full name" oninput="liveValidate(this)">
                  <div class="field-msg"></div>
                </div>
                <div class="col-sm-6">
                  <label class="form-label fw-semibold small">Nickname</label>
                  <input type="text" class="form-control input-validated" name="nickname" value="{{ $r->nickname }}"
                         data-rule="name" data-label="Nickname" oninput="liveValidate(this)">
                  <div class="field-msg"></div>
                </div>
                <div class="col-sm-6">
                  <label class="form-label fw-semibold small">Phone <span class="text-muted fw-normal">(optional)</span></label>
                  <input type="text" class="form-control input-validated" name="phone" value="{{ $r->phone }}"
                         data-rule="phone" data-label="Phone" oninput="liveValidate(this)">
                  <div class="field-msg"></div>
                </div>
                <div class="col-sm-6">
                  <label class="form-label fw-semibold small">Vehicle Type</label>
                  <select class="form-select" name="vehicle_type">
                    <option value="Motorcycle" {{ $r->vehicle_type==='Motorcycle'?'selected':'' }}>🏍️ Motorcycle</option>
                    <option value="Bicycle"    {{ $r->vehicle_type==='Bicycle'?'selected':'' }}>🚲 Bicycle</option>
                    <option value="Tricycle"   {{ $r->vehicle_type==='Tricycle'?'selected':'' }}>🛺 Tricycle</option>
                  </select>
                </div>
                <div class="col-sm-6">
                  <label class="form-label fw-semibold small">License Plate</label>
                  <input type="text" class="form-control input-validated" name="license_plate" value="{{ $r->license_plate }}"
                         placeholder="e.g. ABC 1234" data-rule="plate" data-label="License plate" oninput="liveValidate(this)">
                  <div class="field-msg"></div>
                </div>
                <div class="col-12"><hr class="my-1"><div class="small text-muted fw-semibold">Emergency Contact</div></div>
                <div class="col-sm-6">
                  <label class="form-label fw-semibold small">Name</label>
                  <input type="text" class="form-control input-validated" name="emergency_contact_name" value="{{ $r->emergency_contact_name }}"
                         placeholder="e.g. Maria Santos" data-rule="name" data-label="Emergency contact name" oninput="liveValidate(this)">
                  <div class="field-msg"></div>
                </div>
              </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 gap-2">
              <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary flex-fill fw-semibold">Save Changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    @endforeach
  </div>
  @endif
</div>

{{-- Add Rider Modal --}}
<div class="modal fade" id="addRiderModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:480px">
    <div class="modal-content border-0" style="border-radius:1.2rem;overflow:hidden">
      <div class="modal-header border-0 pt-4 px-4">
        <h5 class="modal-title fw-bold"><i class="bi bi-bicycle me-2" style="color:var(--primary)"></i>Add New Rider</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form action="{{ route('admin.riders.store') }}" method="POST" onsubmit="return validateRiderForm(this)">
        @csrf
        <div class="modal-body px-4 pt-0 pb-3">
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control input-validated" name="name" placeholder="e.g. Juan Dela Cruz" required
                     data-rule="name" data-label="Full name" oninput="liveValidate(this)">
              <div class="field-msg"></div>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold small">Nickname</label>
              <input type="text" class="form-control input-validated" name="nickname" placeholder="e.g. Jun"
                     data-rule="name" data-label="Nickname" oninput="liveValidate(this)">
              <div class="field-msg"></div>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold small">Phone Number <span class="text-muted fw-normal">(optional)</span></label>
              <input type="text" class="form-control input-validated" name="phone" placeholder="09XXXXXXXXX"
                     data-rule="phone" data-label="Phone" oninput="liveValidate(this)">
              <div class="field-msg"></div>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold small">Vehicle Type</label>
              <select class="form-select" name="vehicle_type">
                <option value="Motorcycle">🏍️ Motorcycle</option>
                <option value="Bicycle">🚲 Bicycle</option>
                <option value="Tricycle">🛺 Tricycle</option>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold small">License Plate</label>
              <input type="text" class="form-control input-validated" name="license_plate" placeholder="e.g. ABC 1234"
                     data-rule="plate" data-label="License plate" oninput="liveValidate(this)">
              <div class="field-msg"></div>
            </div>
            <div class="col-12"><hr class="my-1"><div class="small text-muted fw-semibold">Emergency Contact <span class="fw-normal">(optional)</span></div></div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold small">Name</label>
              <input type="text" class="form-control input-validated" name="emergency_contact_name" placeholder="e.g. Maria Santos"
                     data-rule="name" data-label="Emergency contact name" oninput="liveValidate(this)">
              <div class="field-msg"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4 gap-2">
          <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary flex-fill fw-semibold">
            <i class="bi bi-plus-lg me-1"></i>Add Rider
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
// ── Validation Rules ───────────────────────────────────────────────────────
const rules = {
  name: {
    pattern: /^[A-Za-zÀ-ÿ\s.\-']+$/,
    minLen:  2,
    maxLen:  120,
    hint:    'Letters and spaces only',
    errChar: 'Only letters and spaces are allowed — no numbers or special characters.',
  },
  phone: {
    pattern: /^\+?[0-9]{7,15}$/,
    minLen:  7,
    maxLen:  15,
    hint:    'Numbers only (e.g. 09123456789)',
    errChar: 'Phone must contain numbers only.',
  },
  plate: {
    pattern: /^[A-Za-z0-9\s\-]+$/,
    minLen:  2,
    maxLen:  20,
    hint:    'Letters and numbers only (e.g. ABC 1234)',
    errChar: 'License plate: letters and numbers only.',
  },
};

function liveValidate(input) {
  const rule  = rules[input.dataset.rule];
  const label = input.dataset.label || 'This field';
  const val   = input.value.trim();
  const msg   = input.nextElementSibling; // .field-msg div

  // Empty optional field — clear state
  if (!val && !input.required) {
    setInputState(input, msg, 'neutral');
    return true;
  }

  // Required empty
  if (!val && input.required) {
    setInputState(input, msg, 'err', `${label} is required.`);
    return false;
  }

  if (!rule) return true;

  // Character check
  if (!rule.pattern.test(val)) {
    triggerShake(input);
    setInputState(input, msg, 'err', rule.errChar);
    return false;
  }

  // Min length
  if (val.length < rule.minLen) {
    setInputState(input, msg, 'err', `${label} is too short (minimum ${rule.minLen} characters).`);
    return false;
  }

  // Max length
  if (val.length > rule.maxLen) {
    setInputState(input, msg, 'err', `${label} is too long (maximum ${rule.maxLen} characters).`);
    return false;
  }

  // All good
  setInputState(input, msg, 'ok', `✓ ${label} looks good!`);
  return true;
}

function setInputState(input, msgEl, state, text = '') {
  input.classList.remove('is-valid','is-invalid');
  if (msgEl) { msgEl.className = 'field-msg'; msgEl.textContent = ''; }

  if (state === 'ok') {
    input.classList.add('is-valid');
    if (msgEl) { msgEl.classList.add('ok'); msgEl.textContent = text; }
  } else if (state === 'err') {
    input.classList.add('is-invalid');
    if (msgEl) { msgEl.classList.add('err'); msgEl.textContent = text; }
  }
}

function triggerShake(input) {
  input.classList.remove('shake');
  void input.offsetWidth; // reflow to restart animation
  input.classList.add('shake');
  setTimeout(() => input.classList.remove('shake'), 500);
}

// Block invalid character from even being typed
document.addEventListener('keypress', function(e) {
  const input = e.target;
  const rule  = rules[input.dataset?.rule];
  if (!rule) return;

  const char = e.key;
  // Allow control keys
  if (char.length > 1) return;

  // Test if this char would be valid in the pattern
  if (!rule.pattern.test(char) && char !== ' ') {
    e.preventDefault();
    triggerShake(input);
    const msg = input.nextElementSibling;
    setInputState(input, msg, 'err', rule.errChar);
    setTimeout(() => liveValidate(input), 600);
  }
});

// Also block paste of invalid content
document.addEventListener('paste', function(e) {
  const input = e.target;
  const rule  = rules[input.dataset?.rule];
  if (!rule) return;
  const pasted = (e.clipboardData || window.clipboardData).getData('text');
  if (!rule.pattern.test(pasted.trim())) {
    e.preventDefault();
    triggerShake(input);
    const msg = input.nextElementSibling;
    setInputState(input, msg, 'err', rule.errChar);
  }
});

// Validate all fields before submit
function validateRiderForm(form) {
  const inputs = form.querySelectorAll('.input-validated');
  let allValid = true;
  inputs.forEach(input => {
    if (!liveValidate(input)) allValid = false;
  });
  if (!allValid) {
    // Scroll to first error
    const first = form.querySelector('.is-invalid');
    if (first) first.scrollIntoView({ behavior:'smooth', block:'center' });
  }
  return allValid;
}
</script>
@endpush
@endsection
