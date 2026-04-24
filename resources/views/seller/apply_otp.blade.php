@extends('layouts.app')
@section('content')
@php $apply = session('seller_apply'); @endphp
<div style="min-height:100vh;background:linear-gradient(135deg,var(--primary-bg) 0%,var(--primary-light) 100%);padding:2.5rem 1rem">
<div style="max-width:560px;margin:0 auto">

  {{-- Header --}}
  <div style="text-align:center;margin-bottom:2rem">
    <div style="width:64px;height:64px;border-radius:18px;background:var(--primary);display:inline-flex;align-items:center;justify-content:center;margin-bottom:1rem;box-shadow:0 8px 24px rgba(229,57,53,.2)">
      <i class="bi bi-file-earmark-check" style="font-size:1.8rem;color:#fff"></i>
    </div>
    <h1 style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:700;color:var(--gray-900);margin:0 0 .4rem">
      Verify & Upload Documents
    </h1>
    <p style="color:var(--gray-500);font-size:.875rem;margin:0">
      OTP sent to <strong>+63{{ substr($apply['phone'] ?? '', 0, 3) }}*****{{ substr($apply['phone'] ?? '', -2) }}</strong>
    </p>
  </div>

  {{-- Progress --}}
  <div style="display:flex;align-items:center;justify-content:center;gap:.5rem;margin-bottom:2rem">
    @foreach([1=>'Shop Info', 2=>'Verify & Upload'] as $s => $lbl)
      <div style="display:flex;align-items:center;gap:.4rem">
        <div style="width:32px;height:32px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;
          background:{{ $s==2 ? 'var(--primary)' : 'var(--gray-300)' }};color:{{ $s==2 ? '#fff' : 'var(--gray-600)' }};
          box-shadow:{{ $s==2 ? '0 2px 8px rgba(229,57,53,.3)' : 'none' }}">
          {{ $s == 1 ? '✓' : $s }}
        </div>
        <span style="font-size:.83rem;font-weight:{{ $s==2 ? '600' : '400' }};color:{{ $s==2 ? 'var(--gray-900)' : 'var(--gray-400)' }}">{{ $lbl }}</span>
      </div>
      @if($s < 2)
        <div style="height:2px;width:48px;background:var(--primary);border-radius:2px"></div>
      @endif
    @endforeach
  </div>

  {{-- Alerts --}}
  @if(session('error'))
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
      <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i><span>{{ session('error') }}</span>
    </div>
  @endif
  @foreach($errors->all() as $err)
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-2">
      <i class="bi bi-exclamation-circle flex-shrink-0"></i><span>{{ $err }}</span>
    </div>
  @endforeach

  <form action="{{ route('seller.apply.submit') }}" method="POST" enctype="multipart/form-data" novalidate id="otpForm">
    @csrf

    {{-- OTP --}}
    <div style="background:#fff;border-radius:20px;box-shadow:0 4px 24px rgba(0,0,0,.06);border:1.5px solid rgba(229,57,53,.08);padding:1.75rem;margin-bottom:1.25rem">
      <h2 style="font-size:1rem;font-weight:700;color:var(--gray-900);margin:0 0 1.25rem;display:flex;align-items:center;gap:.6rem">
        <span style="width:32px;height:32px;border-radius:10px;background:var(--primary-bg);display:inline-flex;align-items:center;justify-content:center">
          <i class="bi bi-shield-lock" style="color:var(--primary);font-size:.9rem"></i>
        </span>
        OTP Verification
      </h2>
      <input type="text" class="form-control @error('otp') is-invalid @enderror"
             name="otp" maxlength="6"
             placeholder="Enter 6-digit OTP"
             required autofocus
             style="text-align:center;font-size:1.8rem;font-weight:700;letter-spacing:.4em;padding:.875rem"
             oninput="this.value=this.value.replace(/\D/g,'').substring(0,6)"
             oninvalid="this.setCustomValidity('Please enter the 6-digit OTP')"
             onchange="this.setCustomValidity('')">
      @error('otp')<div class="invalid-feedback" style="text-align:center">{{ $message }}</div>@enderror
      @include('components.dev-otp-hint')
    </div>

    {{-- Password --}}
    <div style="background:#fff;border-radius:20px;box-shadow:0 4px 24px rgba(0,0,0,.06);border:1.5px solid rgba(229,57,53,.08);padding:1.75rem;margin-bottom:1.25rem">
      <h2 style="font-size:1rem;font-weight:700;color:var(--gray-900);margin:0 0 1.25rem;display:flex;align-items:center;gap:.6rem">
        <span style="width:32px;height:32px;border-radius:10px;background:var(--primary-bg);display:inline-flex;align-items:center;justify-content:center">
          <i class="bi bi-lock" style="color:var(--primary);font-size:.9rem"></i>
        </span>
        Set Your Password
      </h2>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label" for="password">Password <span style="color:var(--danger)">*</span></label>
          <div class="input-group">
            <input type="password" class="form-control @error('password') is-invalid @enderror"
                   id="password" name="password"
                   placeholder="Min 8 characters" required minlength="8"
                   autocomplete="new-password"
                   oninput="checkPwdStrength(this.value)"
                   oninvalid="this.setCustomValidity('Password must be at least 8 characters')"
                   onchange="this.setCustomValidity('')">
            <button type="button" class="btn btn-secondary" onclick="csTogglePwd('password',this)" tabindex="-1"
                    style="border:1.5px solid var(--gray-200);border-left:0;border-radius:0 var(--radius-md) var(--radius-md) 0;background:var(--gray-50);padding:.6rem .875rem">
              <i class="bi bi-eye" style="color:var(--gray-500)"></i>
            </button>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div style="height:4px;background:var(--gray-200);border-radius:2px;margin-top:.4rem;overflow:hidden">
            <div id="pwdBar" style="height:100%;width:0%;transition:width .3s,background .3s;border-radius:2px"></div>
          </div>
          <div id="pwdLbl" class="form-text"></div>
        </div>
        <div class="col-12">
          <label class="form-label" for="password_confirmation">Confirm Password <span style="color:var(--danger)">*</span></label>
          <div class="input-group">
            <input type="password" class="form-control"
                   id="password_confirmation" name="password_confirmation"
                   placeholder="Repeat password" required
                   autocomplete="new-password"
                   oninput="checkMatch()"
                   oninvalid="this.setCustomValidity('Please confirm your password')"
                   onchange="this.setCustomValidity('')">
            <button type="button" class="btn btn-secondary" onclick="csTogglePwd('password_confirmation',this)" tabindex="-1"
                    style="border:1.5px solid var(--gray-200);border-left:0;border-radius:0 var(--radius-md) var(--radius-md) 0;background:var(--gray-50);padding:.6rem .875rem">
              <i class="bi bi-eye" style="color:var(--gray-500)"></i>
            </button>
          </div>
          <div id="matchMsg" class="form-text"></div>
        </div>
      </div>
    </div>

    {{-- Documents --}}
    <div style="background:#fff;border-radius:20px;box-shadow:0 4px 24px rgba(0,0,0,.06);border:1.5px solid rgba(229,57,53,.08);padding:1.75rem;margin-bottom:1.25rem">
      <h2 style="font-size:1rem;font-weight:700;color:var(--gray-900);margin:0 0 1.25rem;display:flex;align-items:center;gap:.6rem">
        <span style="width:32px;height:32px;border-radius:10px;background:var(--primary-bg);display:inline-flex;align-items:center;justify-content:center">
          <i class="bi bi-file-earmark-text" style="color:var(--primary);font-size:.9rem"></i>
        </span>
        Upload Documents
      </h2>

      {{-- Valid ID --}}
      <div style="margin-bottom:1.25rem">
        <label class="form-label" for="valid_id">
          Government-Issued ID <span style="color:var(--danger)">*</span>
        </label>
        <div id="idDropZone" style="border:2px dashed var(--gray-300);border-radius:var(--radius-md);padding:1.5rem;text-align:center;cursor:pointer;transition:all .2s;background:var(--gray-50)"
             onclick="document.getElementById('valid_id').click()"
             ondragover="event.preventDefault();this.style.borderColor='var(--primary)'"
             ondragleave="this.style.borderColor='var(--gray-300)'"
             ondrop="handleDrop(event,'valid_id','idPreview','idDropZone')">
          <div id="idPreview" style="display:none;margin-bottom:.75rem">
            <img id="idPreviewImg" style="max-height:120px;border-radius:var(--radius-sm);object-fit:cover">
            <div id="idFileName" style="font-size:.78rem;color:var(--gray-500);margin-top:.4rem"></div>
          </div>
          <i class="bi bi-cloud-upload" style="font-size:1.8rem;color:var(--gray-400);display:block;margin-bottom:.5rem" id="idIcon"></i>
          <div style="font-size:.85rem;font-weight:600;color:var(--gray-700)" id="idText">Click or drag to upload your ID</div>
          <div style="font-size:.75rem;color:var(--gray-400);margin-top:.25rem">JPG, PNG, or PDF. Max 5MB.</div>
          <div style="font-size:.75rem;color:var(--gray-500);margin-top:.4rem">Accepted: SSS, PhilSys, Passport, Driver's License, Voter's ID, PRC</div>
        </div>
        <input type="file" id="valid_id" name="valid_id" accept=".jpg,.jpeg,.png,.pdf"
               required class="d-none"
               onchange="previewFile(this,'idPreview','idPreviewImg','idFileName','idIcon','idText')"
               oninvalid="this.setCustomValidity('Please upload a valid government ID')">
        @error('valid_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
      </div>

      {{-- DTI (only for verified) --}}
      @if(session('seller_apply.tier') === 'verified')
      <div style="margin-bottom:1.25rem">
        <label class="form-label" for="dti_certificate">
          DTI Certificate or Business Permit <span style="color:var(--danger)">*</span>
        </label>
        <div style="background:var(--info-bg);border-radius:var(--radius-md);padding:.875rem;margin-bottom:.875rem;font-size:.82rem;color:var(--info);display:flex;align-items:flex-start;gap:.6rem">
          <i class="bi bi-info-circle-fill flex-shrink-0" style="margin-top:.1rem"></i>
          <span>Your document will be automatically scanned using OCR. Our admin team will then manually verify it against the DTI BNRS portal before approving your application.</span>
        </div>
        <div id="dtiDropZone" style="border:2px dashed var(--gray-300);border-radius:var(--radius-md);padding:1.5rem;text-align:center;cursor:pointer;transition:all .2s;background:var(--gray-50)"
             onclick="document.getElementById('dti_certificate').click()"
             ondragover="event.preventDefault();this.style.borderColor='var(--primary)'"
             ondragleave="this.style.borderColor='var(--gray-300)'"
             ondrop="handleDrop(event,'dti_certificate','dtiPreview','dtiDropZone')">
          <div id="dtiPreview" style="display:none;margin-bottom:.75rem">
            <img id="dtiPreviewImg" style="max-height:120px;border-radius:var(--radius-sm);object-fit:cover">
            <div id="dtiFileName" style="font-size:.78rem;color:var(--gray-500);margin-top:.4rem"></div>
          </div>
          <i class="bi bi-cloud-upload" style="font-size:1.8rem;color:var(--gray-400);display:block;margin-bottom:.5rem" id="dtiIcon"></i>
          <div style="font-size:.85rem;font-weight:600;color:var(--gray-700)" id="dtiText">Click or drag to upload DTI Certificate</div>
          <div style="font-size:.75rem;color:var(--gray-400);margin-top:.25rem">JPG, PNG, or PDF. Max 5MB.</div>
        </div>
        <input type="file" id="dti_certificate" name="dti_certificate" accept=".jpg,.jpeg,.png,.pdf"
               required class="d-none"
               onchange="previewFile(this,'dtiPreview','dtiPreviewImg','dtiFileName','dtiIcon','dtiText')"
               oninvalid="this.setCustomValidity('Please upload your DTI certificate or business permit')">
        @error('dti_certificate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
      </div>
      @endif

      {{-- Shop Assets --}}
      <div class="row g-3">
        <div class="col-6">
          <label class="form-label" for="shop_logo">Shop Logo <span style="color:var(--gray-400);font-weight:400">(optional)</span></label>
          <div style="border:2px dashed var(--gray-300);border-radius:var(--radius-md);padding:1rem;text-align:center;cursor:pointer;aspect-ratio:1;display:flex;flex-direction:column;align-items:center;justify-content:center;transition:all .2s;background:var(--gray-50)"
               onclick="document.getElementById('shop_logo').click()">
            <img id="logoPreviewImg" style="display:none;width:80px;height:80px;border-radius:50%;object-fit:cover;margin-bottom:.5rem">
            <i class="bi bi-image" style="font-size:1.4rem;color:var(--gray-400)" id="logoIcon"></i>
            <div style="font-size:.72rem;color:var(--gray-400);margin-top:.3rem" id="logoText">Upload logo</div>
          </div>
          <input type="file" id="shop_logo" name="shop_logo" accept=".jpg,.jpeg,.png" class="d-none"
                 onchange="previewLogo(this)">
          @error('shop_logo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-6">
          <label class="form-label" for="shop_cover">Cover Photo <span style="color:var(--gray-400);font-weight:400">(optional)</span></label>
          <div style="border:2px dashed var(--gray-300);border-radius:var(--radius-md);padding:1rem;text-align:center;cursor:pointer;aspect-ratio:1;display:flex;flex-direction:column;align-items:center;justify-content:center;transition:all .2s;background:var(--gray-50)"
               onclick="document.getElementById('shop_cover').click()">
            <img id="coverPreviewImg" style="display:none;width:100%;height:80px;object-fit:cover;border-radius:var(--radius-sm);margin-bottom:.5rem">
            <i class="bi bi-card-image" style="font-size:1.4rem;color:var(--gray-400)" id="coverIcon"></i>
            <div style="font-size:.72rem;color:var(--gray-400);margin-top:.3rem" id="coverText">Upload cover</div>
          </div>
          <input type="file" id="shop_cover" name="shop_cover" accept=".jpg,.jpeg,.png" class="d-none"
                 onchange="previewCover(this)">
          @error('shop_cover')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
      </div>

    </div>

    <button type="submit" class="btn btn-primary w-100" style="padding:.875rem;font-size:1rem;font-weight:600;border-radius:var(--radius-md)" id="submitBtn">
      Submit Application
    </button>
    <div style="text-align:center;margin-top:.875rem">
      <a href="{{ route('seller.apply') }}" style="font-size:.82rem;color:var(--gray-500)">
        <i class="bi bi-arrow-left" style="font-size:.75rem"></i> Go back
      </a>
    </div>
  </form>
</div>
</div>

<script>
function csTogglePwd(id, btn) {
  const input = document.getElementById(id);
  const icon  = btn.querySelector('i');
  input.type  = input.type === 'password' ? 'text' : 'password';
  icon.className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
  icon.style.color = 'var(--gray-500)';
}

function checkPwdStrength(val) {
  const bar = document.getElementById('pwdBar');
  const lbl = document.getElementById('pwdLbl');
  if (!bar) return;
  let score = 0;
  if (val.length >= 8)           score++;
  if (/[A-Z]/.test(val))         score++;
  if (/[0-9]/.test(val))         score++;
  if (/[^A-Za-z0-9]/.test(val))  score++;
  const levels = [
    {w:'25%',c:'#EF5350',t:'Weak'},
    {w:'50%',c:'#FF7043',t:'Fair'},
    {w:'75%',c:'#FFA726',t:'Good'},
    {w:'100%',c:'#43A047',t:'Strong'},
  ];
  const lvl = levels[Math.max(0, score-1)];
  bar.style.width      = val.length > 0 ? lvl.w : '0%';
  bar.style.background = lvl.c;
  lbl.textContent      = val.length > 0 ? lvl.t : '';
  lbl.style.color      = lvl.c;
}

function checkMatch() {
  const pwd     = document.getElementById('password');
  const confirm = document.getElementById('password_confirmation');
  const msg     = document.getElementById('matchMsg');
  if (!confirm.value.length) { msg.textContent = ''; return; }
  if (pwd.value === confirm.value) {
    msg.textContent = 'Passwords match'; msg.style.color = 'var(--success)';
    confirm.setCustomValidity('');
  } else {
    msg.textContent = 'Passwords do not match'; msg.style.color = 'var(--danger)';
    confirm.setCustomValidity('Passwords do not match');
  }
}

function previewFile(input, wrapId, imgId, nameId, iconId, textId) {
  const file = input.files[0];
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) {
    alert('File must not exceed 5MB.');
    input.value = '';
    return;
  }
  document.getElementById(wrapId).style.display = 'block';
  document.getElementById(nameId).textContent   = file.name;
  document.getElementById(iconId).style.display = 'none';
  document.getElementById(textId).style.display = 'none';
  if (file.type.startsWith('image/')) {
    const reader = new FileReader();
    reader.onload = e => document.getElementById(imgId).src = e.target.result;
    reader.readAsDataURL(file);
    document.getElementById(imgId).style.display = 'block';
  } else {
    document.getElementById(imgId).style.display = 'none';
    document.getElementById(nameId).textContent   = 'PDF: ' + file.name;
  }
}

function previewLogo(input) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('logoPreviewImg').src = e.target.result;
    document.getElementById('logoPreviewImg').style.display = 'block';
    document.getElementById('logoIcon').style.display = 'none';
    document.getElementById('logoText').textContent = file.name.substring(0,15);
  };
  reader.readAsDataURL(file);
}

function previewCover(input) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('coverPreviewImg').src = e.target.result;
    document.getElementById('coverPreviewImg').style.display = 'block';
    document.getElementById('coverIcon').style.display = 'none';
    document.getElementById('coverText').textContent = file.name.substring(0,15);
  };
  reader.readAsDataURL(file);
}

function handleDrop(e, inputId, wrapId, zoneId) {
  e.preventDefault();
  document.getElementById(zoneId).style.borderColor = 'var(--gray-300)';
  const file = e.dataTransfer.files[0];
  if (!file) return;
  const input = document.getElementById(inputId);
  const dt = new DataTransfer();
  dt.items.add(file);
  input.files = dt.files;
  input.dispatchEvent(new Event('change'));
}

// Submit validation with clear error messages
document.getElementById('otpForm').addEventListener('submit', function(e) {
  let errors = [];

  // Check OTP
  const otp = this.querySelector('[name="otp"]');
  if (!otp.value || otp.value.length < 6) errors.push('Please enter the 6-digit OTP sent to your phone.');

  // Check password
  const pwd = document.getElementById('password');
  const pwdConfirm = document.getElementById('password_confirmation');
  if (!pwd.value || pwd.value.length < 8) errors.push('Password must be at least 8 characters.');
  if (pwd.value !== pwdConfirm.value) errors.push('Passwords do not match.');

  // Check valid ID
  const validId = document.getElementById('valid_id');
  if (!validId.files || !validId.files.length) errors.push('Please upload your government-issued ID.');

  // Check DTI if verified tier
  const dtiInput = document.getElementById('dti_certificate');
  if (dtiInput && (!dtiInput.files || !dtiInput.files.length)) errors.push('Please upload your DTI Certificate or Business Permit.');

  if (errors.length > 0) {
    e.preventDefault(); e.stopPropagation();
    // Show errors in a clear alert
    let errBox = document.getElementById('jsErrorBox');
    if (!errBox) {
      errBox = document.createElement('div');
      errBox.id = 'jsErrorBox';
      errBox.style.cssText = 'background:#fef2f2;border:1.5px solid #fca5a5;border-radius:12px;padding:1rem 1.25rem;margin-bottom:1rem;font-size:.875rem;color:#dc2626';
      document.getElementById('submitBtn').before(errBox);
    }
    errBox.innerHTML = '<strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Please fix the following:</strong><ul style="margin:.5rem 0 0 1rem;padding:0">' +
      errors.map(e => `<li>${e}</li>`).join('') + '</ul>';
    errBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return;
  }

  // All good — show loading
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
});
</script>
@endsection
