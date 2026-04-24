@extends('layouts.app')
@section('page_title','Shop Settings')
@section('content')
<div>
  <div style="margin-bottom:2rem">
    <h1 style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:700;color:var(--gray-900);margin:0 0 .25rem">Shop Settings</h1>
    <p style="font-size:.875rem;color:var(--gray-500);margin:0">Manage your shop profile and account</p>
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

  {{-- Seller Tier Info --}}
  <div style="background:{{ $shop->tier==='verified' ? '#FFF3E0' : '#F5F5F5' }};border:1.5px solid {{ $shop->tier==='verified' ? '#FFCC80' : '#E0E0E0' }};border-radius:var(--radius-lg);padding:1rem 1.25rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
    <i class="bi bi-{{ $shop->tier==='verified' ? 'patch-check-fill' : 'person-check' }}" style="font-size:1.5rem;color:{{ $shop->tier==='verified' ? '#E65100' : 'var(--gray-500)' }}"></i>
    <div>
      <div style="font-size:.9rem;font-weight:700;color:var(--gray-900)">{{ ucfirst($shop->tier) }} Seller</div>
      <div style="font-size:.78rem;color:var(--gray-600)">
        Commission: Free (0%) — Platform currently not collecting commission
        @if($shop->tier === 'basic') &mdash; Upgrade to Verified for lower fees & more features @endif
      </div>
    </div>
  </div>

  <div class="row g-4">

    {{-- Shop Profile --}}
    <div class="col-lg-7">
      <div style="background:#fff;border-radius:var(--radius-lg);border:1.5px solid var(--gray-100);overflow:hidden">
        <div style="padding:1.1rem 1.5rem;border-bottom:1.5px solid var(--gray-100)">
          <span style="font-size:.95rem;font-weight:700;color:var(--gray-900);display:flex;align-items:center;gap:.6rem">
            <i class="bi bi-shop" style="color:var(--primary)"></i> Shop Profile
          </span>
        </div>
        <form action="{{ route('seller.settings.shop') }}" method="POST" enctype="multipart/form-data" novalidate style="padding:1.5rem">
          @csrf
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Shop Name <span style="color:var(--danger)">*</span></label>
              <input type="text" class="form-control" name="shop_name" value="{{ $shop->shop_name }}"
                     required minlength="3" maxlength="100"
                     oninvalid="this.setCustomValidity('Shop name is required (min 3 chars)')"
                     oninput="this.setCustomValidity('')">
            </div>
            <div class="col-12">
              <label class="form-label">Description <span style="color:var(--gray-400);font-weight:400">(optional)</span></label>
              <textarea class="form-control" name="description" rows="3" maxlength="500">{{ $shop->description }}</textarea>
              <div class="form-text">Max 500 characters</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">City / Municipality <span style="color:var(--danger)">*</span></label>
              <input type="text" class="form-control" name="city" value="{{ $shop->city }}"
                     required maxlength="80"
                     oninvalid="this.setCustomValidity('City is required')"
                     oninput="this.setCustomValidity('')">
            </div>
            <div class="col-md-6">
              <label class="form-label">GCash Number <span style="color:var(--danger)">*</span></label>
              <input type="text" class="form-control" name="gcash_number" value="{{ $shop->gcash_number }}"
                     required pattern="(\+63)?9[0-9]{9}"
                     oninvalid="this.setCustomValidity('Enter a valid GCash number')"
                     oninput="this.setCustomValidity('')">
            </div>
            <div class="col-12">
              <label class="form-label">Business Address <span style="color:var(--danger)">*</span></label>
              <input type="text" class="form-control" name="address" value="{{ $shop->address }}"
                     required maxlength="255"
                     oninvalid="this.setCustomValidity('Address is required')"
                     oninput="this.setCustomValidity('')">
            </div>
            <div class="col-md-6">
              <label class="form-label">Shop Logo <span style="color:var(--gray-400);font-weight:400">(optional)</span></label>
              @if($shop->shop_logo)
                <img src="{{ $shop->shop_logo }}" style="display:block;width:72px;height:72px;border-radius:14px;object-fit:cover;margin-bottom:.5rem;border:2px solid var(--gray-200)">
              @endif
              <input type="file" class="form-control" name="shop_logo" accept=".jpg,.jpeg,.png"
                     onchange="previewFile(this,'logoPreview')" style="font-size:.8rem">
              <img id="logoPreview" style="display:none;max-height:72px;margin-top:.4rem;border-radius:14px;object-fit:cover">
              <div class="form-text">JPG or PNG. Max 3MB.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Cover Photo <span style="color:var(--gray-400);font-weight:400">(optional)</span></label>
              @if($shop->shop_cover)
                <img src="{{ $shop->shop_cover }}" style="display:block;width:100%;height:60px;border-radius:var(--radius-sm);object-fit:cover;margin-bottom:.5rem;border:1.5px solid var(--gray-200)">
              @endif
              <input type="file" class="form-control" name="shop_cover" accept=".jpg,.jpeg,.png"
                     onchange="previewFile(this,'coverPreview')" style="font-size:.8rem">
              <img id="coverPreview" style="display:none;max-height:60px;width:100%;margin-top:.4rem;border-radius:var(--radius-sm);object-fit:cover">
              <div class="form-text">JPG or PNG. Max 5MB.</div>
            </div>
            <div class="col-12">
              <label class="form-label">Shop Theme Color <span style="color:var(--gray-400);font-weight:400">(optional)</span></label>
              <div style="display:flex;align-items:center;gap:.75rem">
                <input type="color" name="theme_color" id="themeColorPicker"
                       value="{{ $shop->theme_color ?? '#E53935' }}"
                       style="width:48px;height:40px;padding:2px;border:1.5px solid var(--gray-200);border-radius:var(--radius-sm);cursor:pointer;background:#fff">
                <div id="colorPreviewBox"
                     style="height:40px;flex:1;border-radius:var(--radius-sm);border:1.5px solid var(--gray-200);background:{{ $shop->theme_color ?? '#E53935' }};transition:background .15s"></div>
              </div>
              <div class="form-text">Customers will see this as your shop's accent color.</div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary mt-4" style="padding:.65rem 2rem;font-weight:600">
            Save Shop Profile
          </button>
        </form>
      </div>
    </div>

    {{-- Daily Capacity --}}
    <div class="col-12">
      <div style="background:#fff;border-radius:var(--radius-lg);border:1.5px solid var(--gray-100);overflow:hidden">
        <div style="padding:1.1rem 1.5rem;border-bottom:1.5px solid var(--gray-100)">
          <span style="font-size:.95rem;font-weight:700;color:var(--gray-900);display:flex;align-items:center;gap:.6rem">
            <i class="bi bi-calendar-check" style="color:var(--primary)"></i> Daily Order Capacity
          </span>
          <p style="font-size:.8rem;color:var(--gray-500);margin:.25rem 0 0">Set how many cake orders you can accept per day. Set to 0 for unlimited.</p>
        </div>
        <form action="{{ route('seller.settings.daily_capacity') }}" method="POST" style="padding:1.5rem">
          @csrf
          <div class="row g-3">
            <div class="col-md-6 col-lg-3">
              <label class="form-label fw-semibold">Max Orders Per Day</label>
              <input type="number" min="0" class="form-control" name="daily_max_cakes"
                     value="{{ $shopSettings->daily_max_cakes ?? 0 }}"
                     oninput="updateCapacityPreview()">
              <div class="form-text">0 = unlimited</div>
            </div>
            <div class="col-md-6 col-lg-3">
              <label class="form-label fw-semibold">Tomorrow (1-day lead)</label>
              <input type="number" min="0" class="form-control" name="lead_1day_max"
                     value="{{ $shopSettings->lead_1day_max ?? 0 }}"
                     oninput="updateCapacityPreview()">
              <div class="form-text">0 = use default</div>
            </div>
            <div class="col-md-6 col-lg-3">
              <label class="form-label fw-semibold">2-Day Lead</label>
              <input type="number" min="0" class="form-control" name="lead_2day_max"
                     value="{{ $shopSettings->lead_2day_max ?? 0 }}"
                     oninput="updateCapacityPreview()">
              <div class="form-text">0 = use default</div>
            </div>
            <div class="col-md-6 col-lg-3">
              <label class="form-label fw-semibold">3+ Day Lead</label>
              <input type="number" min="0" class="form-control" name="lead_3day_plus_max"
                     value="{{ $shopSettings->lead_3day_plus_max ?? 0 }}"
                     oninput="updateCapacityPreview()">
              <div class="form-text">0 = use default</div>
            </div>
            <div class="col-12">
              <div id="capacityPreview" style="font-size:.82rem;color:var(--gray-600);background:var(--gray-50);border-radius:var(--radius-sm);padding:.6rem 1rem"></div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary mt-4" style="padding:.65rem 2rem;font-weight:600">
            Save Capacity Settings
          </button>
        </form>
      </div>
    </div>

    {{-- Change Password --}}
    <div class="col-lg-5">
      <div style="background:#fff;border-radius:var(--radius-lg);border:1.5px solid var(--gray-100);overflow:hidden">
        <div style="padding:1.1rem 1.5rem;border-bottom:1.5px solid var(--gray-100)">
          <span style="font-size:.95rem;font-weight:700;color:var(--gray-900);display:flex;align-items:center;gap:.6rem">
            <i class="bi bi-lock" style="color:var(--primary)"></i> Change Password
          </span>
        </div>
        <form action="{{ route('seller.settings.password') }}" method="POST" novalidate style="padding:1.5rem">
          @csrf
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Current Password <span style="color:var(--danger)">*</span></label>
              <div class="input-group">
                <input type="password" class="form-control" name="current_password" id="curPwd" required
                       oninvalid="this.setCustomValidity('Current password is required')"
                       oninput="this.setCustomValidity('')">
                <button type="button" class="btn btn-secondary" onclick="togglePwd('curPwd',this)"
                        style="border:1.5px solid var(--gray-200);border-left:0;background:var(--gray-50);padding:.6rem .875rem">
                  <i class="bi bi-eye" style="color:var(--gray-500)"></i>
                </button>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">New Password <span style="color:var(--danger)">*</span></label>
              <div class="input-group">
                <input type="password" class="form-control" name="password" id="newPwd"
                       required minlength="8"
                       oninvalid="this.setCustomValidity('Min 8 characters')"
                       oninput="this.setCustomValidity('');checkMatch()">
                <button type="button" class="btn btn-secondary" onclick="togglePwd('newPwd',this)"
                        style="border:1.5px solid var(--gray-200);border-left:0;background:var(--gray-50);padding:.6rem .875rem">
                  <i class="bi bi-eye" style="color:var(--gray-500)"></i>
                </button>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Confirm Password <span style="color:var(--danger)">*</span></label>
              <div class="input-group">
                <input type="password" class="form-control" name="password_confirmation" id="confPwd"
                       required oninput="checkMatch()"
                       oninvalid="this.setCustomValidity('Please confirm your password')"
                       onchange="this.setCustomValidity('')">
                <button type="button" class="btn btn-secondary" onclick="togglePwd('confPwd',this)"
                        style="border:1.5px solid var(--gray-200);border-left:0;background:var(--gray-50);padding:.6rem .875rem">
                  <i class="bi bi-eye" style="color:var(--gray-500)"></i>
                </button>
              </div>
              <div id="matchMsg" class="form-text"></div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary mt-4" style="padding:.65rem 2rem;font-weight:600">
            Update Password
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
function updateCapacityPreview() {
  const daily = parseInt(document.querySelector('[name="daily_max_cakes"]')?.value) || 0;
  const d1    = parseInt(document.querySelector('[name="lead_1day_max"]')?.value) || 0;
  const d2    = parseInt(document.querySelector('[name="lead_2day_max"]')?.value) || 0;
  const d3    = parseInt(document.querySelector('[name="lead_3day_plus_max"]')?.value) || 0;
  const el    = document.getElementById('capacityPreview');
  if (!el) return;
  if (daily === 0) { el.textContent = 'Unlimited orders per day.'; return; }
  el.textContent = `Default: ${daily} pcs/day`
    + (d1 > 0 ? ` | Tomorrow: ${d1} pcs` : '')
    + (d2 > 0 ? ` | 2-day: ${d2} pcs` : '')
    + (d3 > 0 ? ` | 3+ days: ${d3} pcs` : '');
}
updateCapacityPreview();
document.querySelectorAll('[name="daily_max_cakes"],[name="lead_1day_max"],[name="lead_2day_max"],[name="lead_3day_plus_max"]')
  .forEach(el => el.addEventListener('input', updateCapacityPreview));

document.getElementById('themeColorPicker').addEventListener('input', function() {
  document.getElementById('colorPreviewBox').style.background = this.value;
});
function previewFile(input, id) {
  const img = document.getElementById(id);
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => { img.src = e.target.result; img.style.display = 'block'; };
  reader.readAsDataURL(file);
}
function togglePwd(id, btn) {
  const input = document.getElementById(id);
  const icon  = btn.querySelector('i');
  input.type  = input.type === 'password' ? 'text' : 'password';
  icon.className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
  icon.style.color = 'var(--gray-500)';
}
function checkMatch() {
  const p = document.getElementById('newPwd');
  const c = document.getElementById('confPwd');
  const m = document.getElementById('matchMsg');
  if (!c.value) { m.textContent = ''; return; }
  if (p.value === c.value) {
    m.textContent = 'Passwords match'; m.style.color = 'var(--success,#2E7D32)';
    c.setCustomValidity('');
  } else {
    m.textContent = 'Passwords do not match'; m.style.color = 'var(--danger,#C62828)';
    c.setCustomValidity('Passwords do not match');
  }
}
</script>
@endsection
