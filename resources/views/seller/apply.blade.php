@extends('layouts.app')
@section('content')
<div style="min-height:100vh;background:linear-gradient(135deg,var(--primary-bg) 0%,var(--primary-light) 100%);padding:2.5rem 1rem">
<div style="max-width:680px;margin:0 auto">

  {{-- Header --}}
  <div style="text-align:center;margin-bottom:2.5rem">
    <div style="width:72px;height:72px;border-radius:20px;background:var(--primary);display:inline-flex;align-items:center;justify-content:center;margin-bottom:1rem;box-shadow:0 8px 24px rgba(229,57,53,.2)">
      <i class="bi bi-shop" style="font-size:2rem;color:#fff"></i>
    </div>
    <h1 style="font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:700;color:var(--gray-900);margin:0 0 .5rem">
      Become a Seller
    </h1>
    <p style="color:var(--gray-500);font-size:.95rem;margin:0;max-width:420px;margin:0 auto">
      Register your cake shop on our platform and reach more customers online.
    </p>
  </div>

  {{-- Progress Steps --}}
  <div style="display:flex;align-items:center;justify-content:center;gap:.5rem;margin-bottom:2rem">
    @foreach([1=>'Shop Info', 2=>'Verify & Upload'] as $s => $lbl)
      <div style="display:flex;align-items:center;gap:.4rem">
        <div style="width:32px;height:32px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;
          font-size:.8rem;font-weight:700;background:{{ $s==1 ? 'var(--primary)' : 'var(--gray-200)' }};
          color:{{ $s==1 ? '#fff' : 'var(--gray-500)' }};box-shadow:{{ $s==1 ? '0 2px 8px rgba(229,57,53,.3)' : 'none' }}">
          {{ $s }}
        </div>
        <span style="font-size:.83rem;font-weight:{{ $s==1 ? '600' : '400' }};color:{{ $s==1 ? 'var(--gray-900)' : 'var(--gray-400)' }}">{{ $lbl }}</span>
      </div>
      @if($s < 2)
        <div style="height:2px;width:48px;background:var(--gray-200);border-radius:2px"></div>
      @endif
    @endforeach
  </div>

  {{-- Alerts --}}
  @if(session('error'))
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
      <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
      <span>{{ session('error') }}</span>
    </div>
  @endif
  @foreach($errors->all() as $err)
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-2">
      <i class="bi bi-exclamation-circle flex-shrink-0"></i>
      <span>{{ $err }}</span>
    </div>
  @endforeach

  <form action="{{ route('seller.apply.otp.send') }}" method="POST" novalidate id="applyForm">
    @csrf

    {{-- SECTION: Shop Details --}}
    <div style="background:#fff;border-radius:20px;box-shadow:0 4px 24px rgba(0,0,0,.06);border:1.5px solid rgba(229,57,53,.08);padding:1.75rem;margin-bottom:1.25rem">
      <h2 style="font-size:1rem;font-weight:700;color:var(--gray-900);margin:0 0 1.25rem;display:flex;align-items:center;gap:.6rem">
        <span style="width:32px;height:32px;border-radius:10px;background:var(--primary-bg);display:inline-flex;align-items:center;justify-content:center">
          <i class="bi bi-shop" style="color:var(--primary);font-size:.9rem"></i>
        </span>
        Shop Information
      </h2>

      <div class="row g-3">
        <div class="col-12">
          <label class="form-label" for="shop_name">Shop Name <span style="color:var(--danger)">*</span></label>
          <input type="text" class="form-control @error('shop_name') is-invalid @enderror"
                 id="shop_name" name="shop_name"
                 value="{{ old('shop_name') }}"
                 placeholder="e.g. Sweet Dreams Cake Shop"
                 required minlength="3" maxlength="100"
                 oninvalid="this.setCustomValidity('Shop name is required (min 3 characters)')"
                 oninput="this.setCustomValidity('');updateSlugPreview(this.value)">
          <div class="form-text" id="slugPreview" style="color:var(--gray-400)">URL: cakeshop.com/shop/<span id="slugVal">your-shop-name</span></div>
          @error('shop_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
          <label class="form-label" for="description">Shop Description <span style="color:var(--gray-400);font-weight:400">(optional)</span></label>
          <textarea class="form-control" id="description" name="description"
                    rows="3" maxlength="500"
                    placeholder="Tell customers about your cake shop, specialties, and story...">{{ old('description') }}</textarea>
          <div class="form-text"><span id="descCount">0</span>/500 characters</div>
        </div>

        <div class="col-12">
          <label class="form-label" for="city">City / Municipality <span style="color:var(--danger)">*</span></label>
          <input type="text" class="form-control @error('city') is-invalid @enderror"
                 id="city" name="city"
                 value="{{ old('city') }}"
                 placeholder="e.g. Calasiao, Pangasinan"
                 required maxlength="80"
                 oninvalid="this.setCustomValidity('City is required')"
                 oninput="this.setCustomValidity('')">
          @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
          <label class="form-label" for="address">Complete Business Address <span style="color:var(--danger)">*</span></label>
          <input type="text" class="form-control @error('address') is-invalid @enderror"
                 id="address" name="address"
                 value="{{ old('address') }}"
                 placeholder="e.g. 123 Rizal St., Poblacion East, Bautista, Pangasinan"
                 required maxlength="255"
                 oninvalid="this.setCustomValidity('Business address is required')"
                 oninput="this.setCustomValidity('')">
          @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
      </div>
    </div>

    {{-- SECTION: Owner Details --}}
    <div style="background:#fff;border-radius:20px;box-shadow:0 4px 24px rgba(0,0,0,.06);border:1.5px solid rgba(229,57,53,.08);padding:1.75rem;margin-bottom:1.25rem">
      <h2 style="font-size:1rem;font-weight:700;color:var(--gray-900);margin:0 0 1.25rem;display:flex;align-items:center;gap:.6rem">
        <span style="width:32px;height:32px;border-radius:10px;background:var(--primary-bg);display:inline-flex;align-items:center;justify-content:center">
          <i class="bi bi-person" style="color:var(--primary);font-size:.9rem"></i>
        </span>
        Owner / Contact Details
      </h2>

      <div class="row g-3">
        <div class="col-12">
          <label class="form-label" for="full_name">Full Name <span style="color:var(--danger)">*</span></label>
          <input type="text" class="form-control @error('full_name') is-invalid @enderror"
                 id="full_name" name="full_name"
                 value="{{ old('full_name') }}"
                 placeholder="e.g. Maria Santos"
                 required minlength="2" maxlength="100"
                 oninvalid="this.setCustomValidity('Full name is required')"
                 oninput="this.setCustomValidity('')">
          @error('full_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
          <label class="form-label" for="email">Email Address <span style="color:var(--danger)">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope" style="color:var(--primary)"></i></span>
            <input type="email" class="form-control @error('email') is-invalid @enderror"
                   id="email" name="email"
                   value="{{ old('email') }}"
                   placeholder="shop@email.com"
                   required
                   oninvalid="this.setCustomValidity('Please enter a valid email address')"
                   oninput="this.setCustomValidity('')">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="phone">Phone Number <span style="color:var(--danger)">*</span></label>
          <div class="input-group">
            <span class="input-group-text" style="font-weight:600">+63</span>
            <input type="text" class="form-control @error('phone') is-invalid @enderror"
                   id="phone" name="phone"
                   value="{{ old('phone') }}"
                   placeholder="9XXXXXXXXX"
                   required maxlength="10" pattern="9[0-9]{9}"
                   oninput="this.value=this.value.replace(/\D/g,'').substring(0,10)"
                   oninvalid="this.setCustomValidity('Enter 10-digit PH number starting with 9')"
                   onchange="this.setCustomValidity('')">
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="gcash_number">GCash Number (for payouts) <span style="color:var(--danger)">*</span></label>
          <div class="input-group">
            <span class="input-group-text" style="font-weight:600">+63</span>
            <input type="text" class="form-control @error('gcash_number') is-invalid @enderror"
                   id="gcash_number" name="gcash_number"
                   value="{{ old('gcash_number') }}"
                   placeholder="9XXXXXXXXX"
                   required maxlength="10" pattern="9[0-9]{9}"
                   oninput="this.value=this.value.replace(/\D/g,'').substring(0,10)"
                   oninvalid="this.setCustomValidity('Enter a valid GCash number')"
                   onchange="this.setCustomValidity('')">
            @error('gcash_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="form-text">For receiving payments from customers (COD collection, etc.).</div>
        </div>
      </div>
    </div>

    {{-- SECTION: Seller Tier --}}
    <div style="background:#fff;border-radius:20px;box-shadow:0 4px 24px rgba(0,0,0,.06);border:1.5px solid rgba(229,57,53,.08);padding:1.75rem;margin-bottom:1.75rem">
      <h2 style="font-size:1rem;font-weight:700;color:var(--gray-900);margin:0 0 .5rem;display:flex;align-items:center;gap:.6rem">
        <span style="width:32px;height:32px;border-radius:10px;background:var(--primary-bg);display:inline-flex;align-items:center;justify-content:center">
          <i class="bi bi-award" style="color:var(--primary);font-size:.9rem"></i>
        </span>
        Choose Your Seller Tier <span style="color:var(--danger)">*</span>
      </h2>
      <p style="font-size:.83rem;color:var(--gray-500);margin:0 0 1.25rem">Select the tier that best describes your business.</p>

      <div class="row g-3">
        {{-- Basic Tier --}}
        <div class="col-md-6">
          <label id="tierBasicLabel" style="display:block;border:2px solid var(--gray-200);border-radius:var(--radius-lg);padding:1.25rem;cursor:pointer;transition:all .2s;height:100%">
            <input type="radio" name="tier" value="basic" id="tierBasic"
                   {{ old('tier','basic') === 'basic' ? 'checked' : '' }}
                   required style="display:none"
                   onchange="selectTier('basic')">
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.875rem">
              <div style="width:44px;height:44px;border-radius:12px;background:#F5F5F5;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-person-check" style="font-size:1.2rem;color:var(--gray-600)"></i>
              </div>
              <div>
                <div style="font-size:.95rem;font-weight:700;color:var(--gray-900)">Basic Seller</div>
                <div style="font-size:.75rem;color:var(--gray-500)">Free to join — no commission for now</div>
              </div>
            </div>
            <ul style="list-style:none;padding:0;margin:0;font-size:.8rem;color:var(--gray-600)">
              <li style="padding:.2rem 0;display:flex;align-items:center;gap:.5rem"><i class="bi bi-check" style="color:var(--success)"></i>Valid Government ID only</li>
              <li style="padding:.2rem 0;display:flex;align-items:center;gap:.5rem"><i class="bi bi-check" style="color:var(--success)"></i>Up to 20 products</li>
              <li style="padding:.2rem 0;display:flex;align-items:center;gap:.5rem"><i class="bi bi-x" style="color:var(--gray-400)"></i>No custom orders feature</li>
              <li style="padding:.2rem 0;display:flex;align-items:center;gap:.5rem"><i class="bi bi-x" style="color:var(--gray-400)"></i>No verified badge</li>
            </ul>
          </label>
        </div>

        {{-- Verified Tier --}}
        <div class="col-md-6">
          <label id="tierVerifiedLabel" style="display:block;border:2px solid var(--gray-200);border-radius:var(--radius-lg);padding:1.25rem;cursor:pointer;transition:all .2s;height:100%;position:relative">
            <input type="radio" name="tier" value="verified" id="tierVerified"
                   {{ old('tier') === 'verified' ? 'checked' : '' }}
                   style="display:none"
                   onchange="selectTier('verified')">
            <div style="position:absolute;top:-.6rem;right:1rem;background:var(--primary);color:#fff;font-size:.7rem;font-weight:700;padding:.2rem .6rem;border-radius:99px">RECOMMENDED</div>
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.875rem">
              <div style="width:44px;height:44px;border-radius:12px;background:#FFF3E0;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-patch-check-fill" style="font-size:1.2rem;color:#E65100"></i>
              </div>
              <div>
                <div style="font-size:.95rem;font-weight:700;color:var(--gray-900)">Verified Seller</div>
                <div style="font-size:.75rem;color:var(--gray-500)">Free to join — no commission for now</div>
              </div>
            </div>
            <ul style="list-style:none;padding:0;margin:0;font-size:.8rem;color:var(--gray-600)">
              <li style="padding:.2rem 0;display:flex;align-items:center;gap:.5rem"><i class="bi bi-check" style="color:var(--success)"></i>Valid ID + DTI/Business Permit</li>
              <li style="padding:.2rem 0;display:flex;align-items:center;gap:.5rem"><i class="bi bi-check" style="color:var(--success)"></i>Unlimited products</li>
              <li style="padding:.2rem 0;display:flex;align-items:center;gap:.5rem"><i class="bi bi-check" style="color:var(--success)"></i>Custom orders feature</li>
              <li style="padding:.2rem 0;display:flex;align-items:center;gap:.5rem"><i class="bi bi-check" style="color:var(--success)"></i>Verified badge on shop</li>
            </ul>
          </label>
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary w-100" style="padding:.875rem;font-size:1rem;font-weight:600;border-radius:var(--radius-md)">
      Continue — Send OTP
    </button>

  </form>

  <div style="text-align:center;margin-top:1.5rem;font-size:.875rem;color:var(--gray-500)">
    Already have an account?
    <a href="{{ route('login') }}" style="color:var(--primary);font-weight:600;margin-left:.25rem">Sign in</a>
  </div>

</div>
</div>

<script>
// Slug preview
function updateSlugPreview(val) {
  const slug = val.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
  document.getElementById('slugVal').textContent = slug || 'your-shop-name';
}
updateSlugPreview(document.getElementById('shop_name')?.value || '');

// Description counter
const descEl = document.getElementById('description');
const descCount = document.getElementById('descCount');
if (descEl && descCount) {
  descCount.textContent = descEl.value.length;
  descEl.addEventListener('input', () => descCount.textContent = descEl.value.length);
}

// Tier selector
function selectTier(tier) {
  const basicLabel    = document.getElementById('tierBasicLabel');
  const verifiedLabel = document.getElementById('tierVerifiedLabel');
  if (tier === 'basic') {
    basicLabel.style.borderColor    = 'var(--primary)';
    basicLabel.style.background     = 'var(--primary-bg)';
    verifiedLabel.style.borderColor = 'var(--gray-200)';
    verifiedLabel.style.background  = '#fff';
  } else {
    verifiedLabel.style.borderColor = 'var(--primary)';
    verifiedLabel.style.background  = 'var(--primary-bg)';
    basicLabel.style.borderColor    = 'var(--gray-200)';
    basicLabel.style.background     = '#fff';
  }
}
// Init
const checked = document.querySelector('input[name="tier"]:checked');
if (checked) selectTier(checked.value);

// Form validation
document.getElementById('applyForm').addEventListener('submit', function(e) {
  const tier = document.querySelector('input[name="tier"]:checked');
  if (!tier) {
    e.preventDefault();
    alert('Please select a seller tier.');
    return;
  }
  if (!this.checkValidity()) {
    e.preventDefault();
    e.stopPropagation();
    // Scroll to first invalid
    const first = this.querySelector(':invalid');
    if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
});
</script>
@endsection
