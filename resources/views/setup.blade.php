<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Setup Admin — Cake Shop</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body { background: linear-gradient(135deg,#fff7fb,#ffe3f1); min-height:100vh; font-family:'Segoe UI',system-ui,sans-serif; }
    .card { border:0; border-radius:1.2rem; box-shadow:0 8px 32px rgba(233,30,99,.12); }
    .btn-primary { background:#e91e63; border-color:#e91e63; }
    .btn-primary:hover { background:#c2185b; border-color:#c2185b; }
    .form-control:focus { border-color:#e91e63; box-shadow:0 0 0 .2rem rgba(233,30,99,.15); }
    .step-badge { width:36px;height:36px;border-radius:50%;background:#e91e63;color:#fff;font-weight:700;display:inline-flex;align-items:center;justify-content:center;font-size:.9rem; }
  </style>
</head>
<body>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">

      <div class="text-center mb-4">
        <div style="width:72px;height:72px;border-radius:20px;background:#e91e63;display:inline-flex;align-items:center;justify-content:center;margin-bottom:1rem">
          <i class="bi bi-cake2-fill text-white" style="font-size:2rem"></i>
        </div>
        <h3 class="fw-bold mb-1">Welcome to Cake Shop!</h3>
        <p class="text-muted">First-time setup — create your Super Admin account to get started.</p>
      </div>

      {{-- Step Indicator --}}
      <div class="d-flex justify-content-center align-items-center gap-2 mb-4">
        @foreach(['Account Info','Verify OTP'] as $i => $label)
          <div class="d-flex align-items-center gap-1">
            <span style="width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;
                  background:{{ $step > $i+1 ? '#e91e63' : ($step === $i+1 ? '#e91e63' : '#dee2e6') }};
                  color:{{ $step >= $i+1 ? '#fff' : '#666' }}">
              @if($step > $i+1)<i class="bi bi-check"></i>@else{{ $i+1 }}@endif
            </span>
            <span class="small {{ $step === $i+1 ? 'fw-semibold' : 'text-muted' }}" style="font-size:.8rem">{{ $label }}</span>
          </div>
          @if($i < 1)<div style="flex:1;height:2px;background:{{ $step > $i+1 ? '#e91e63' : '#dee2e6' }};max-width:40px"></div>@endif
        @endforeach
      </div>

      @if(session('error'))
        <div class="alert alert-danger border-0 py-2 mb-3"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
      @endif
      @if(session('msg'))
        <div class="alert alert-info border-0 py-2 mb-3"><i class="bi bi-envelope-check me-2"></i>{{ session('msg') }}</div>
      @endif

      <div class="card">
        <div class="card-body p-4">

          @if($step == 1)
          {{-- Step 1: Account Info --}}
          <div class="d-flex align-items-center gap-3 mb-4 p-3 rounded" style="background:#fff0f5">
            <i class="bi bi-shield-lock-fill" style="color:#e91e63;font-size:1.4rem"></i>
            <div class="small">
              <strong>This page only appears once.</strong><br>
              After creating your super admin account, this setup page will be permanently disabled.
            </div>
          </div>

          <form action="{{ route('setup.store') }}" method="POST">
            @csrf

            <h6 class="fw-bold mb-3 text-muted small text-uppercase">🏪 Shop Info</h6>
            <div class="mb-3">
              <label class="form-label fw-semibold small">Shop / Site Name</label>
              <input type="text" class="form-control" name="site_title" value="{{ old('site_title','My Cake Shop') }}" required placeholder="My Cake Shop">
            </div>

            <hr class="my-3">
            <h6 class="fw-bold mb-3 text-muted small text-uppercase">👤 Super Admin Account</h6>

            <div class="row g-3">
              <div class="col-sm-6">
                <label class="form-label fw-semibold small">Full Name</label>
                <input type="text" class="form-control" name="fullname" value="{{ old('fullname') }}" placeholder="Juan dela Cruz" required>
              </div>
              <div class="col-sm-6">
                <label class="form-label fw-semibold small">Username</label>
                <input type="text" class="form-control" name="username" value="{{ old('username') }}" placeholder="admin" required>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Email Address</label>
                <input type="email" class="form-control" name="email" value="{{ old('email') }}" placeholder="admin@email.com" required>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Phone Number</label>
                <input type="text" class="form-control" name="phone" value="{{ old('phone') }}" placeholder="09XXXXXXXXX" required>
              </div>
              <div class="col-sm-6">
                <label class="form-label fw-semibold small">Password</label>
                <div class="input-group">
                  <input type="password" class="form-control" name="password" id="setupPwd" required minlength="8" placeholder="Min. 8 characters">
                  <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('setupPwd',this)"><i class="bi bi-eye"></i></button>
                </div>
                {{-- Strength bar --}}
                <div id="strength_setupPwd" class="mt-1" style="display:none">
                  <div style="height:5px;border-radius:99px;background:#e9ecef;overflow:hidden;margin-bottom:4px">
                    <div id="strengthBar_setupPwd" style="height:100%;width:0;border-radius:99px;transition:all .3s"></div>
                  </div>
                  <div id="strengthText_setupPwd" class="small fw-semibold"></div>
                </div>
                {{-- Requirements --}}
                <div id="pwdReq_setupPwd" class="mt-2" style="display:none">
                  <div class="small fw-semibold mb-1" style="color:#1d4ed8"><i class="bi bi-shield-check me-1"></i>Password Requirements:</div>
                  <ul class="list-unstyled mb-0">
                    <li id="req_len_setupPwd" class="d-flex align-items-center gap-2 mb-1 small"><span class="req-icon" style="color:#1d4ed8;font-size:.85rem">○</span><span style="color:#1d4ed8">Minimum of 8 characters</span></li>
                    <li id="req_upper_setupPwd" class="d-flex align-items-center gap-2 mb-1 small"><span class="req-icon" style="color:#1d4ed8;font-size:.85rem">○</span><span style="color:#1d4ed8">Must contain at least 1 Uppercase letter</span></li>
                    <li id="req_num_setupPwd" class="d-flex align-items-center gap-2 mb-1 small"><span class="req-icon" style="color:#1d4ed8;font-size:.85rem">○</span><span style="color:#1d4ed8">Must contain at least 1 Number</span></li>
                    <li id="req_special_setupPwd" class="d-flex align-items-center gap-2 mb-1 small"><span class="req-icon" style="color:#1d4ed8;font-size:.85rem">○</span><span style="color:#1d4ed8">Must contain at least 1 Special Character (!@#$%^&*)</span></li>
                  </ul>
                </div>
              </div>
              <div class="col-sm-6">
                <label class="form-label fw-semibold small">Confirm Password</label>
                <div class="input-group">
                  <input type="password" class="form-control" name="confirm_password" id="setupConfirm" required placeholder="Repeat">
                  <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('setupConfirm',this)"><i class="bi bi-eye"></i></button>
                </div>
              </div>
            </div>

            <hr class="my-3">
            <h6 class="fw-bold mb-3 text-muted small text-uppercase">📨 Verify Via</h6>
            <div class="d-flex gap-3 mb-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="otp_channel" value="email" id="setupEmail" checked>
                <label class="form-check-label" for="setupEmail"><i class="bi bi-envelope me-1"></i>Email</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="otp_channel" value="sms" id="setupSms">
                <label class="form-check-label" for="setupSms"><i class="bi bi-phone me-1"></i>SMS</label>
              </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
              <i class="bi bi-send me-1"></i>Send Verification Code
            </button>
          </form>

          @else
          {{-- Step 2: OTP Verification --}}
          <div class="text-center mb-3">
            <i class="bi bi-shield-check" style="font-size:2.5rem;color:#e91e63"></i>
            <p class="text-muted small mt-2">
              Enter the 6-digit OTP sent to you via {{ session('setup_channel') === 'sms' ? 'SMS' : 'Email' }}.
            </p>
          </div>

          <form action="{{ route('setup.verify') }}" method="POST">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold small">OTP Code</label>
              <input type="text" class="form-control text-center fw-bold" name="otp"
                     maxlength="6" placeholder="000000" autofocus required
                     style="font-size:1.5rem;letter-spacing:.5rem"
                     oninput="this.value=this.value.replace(/\D/g,'')">
              <div class="form-text text-center">OTP expires in 10 minutes</div>
              @include('components.dev-otp-hint')
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mb-2">
              <i class="bi bi-check-lg me-1"></i>Verify & Complete Setup
            </button>
          </form>

          {{-- Resend --}}
          <div class="text-center mt-2">
            <span class="small text-muted" id="resendWrap">
              Resend in <span id="resendCountdown" class="fw-semibold" style="color:#e91e63">1:00</span>
            </span>
            <form action="{{ route('setup.store') }}" method="POST" id="resendForm" style="display:none">
              @csrf
              @foreach(session('setup_pending', []) as $key => $val)
                <input type="hidden" name="{{ $key }}" value="{{ $val }}">
              @endforeach
              <input type="hidden" name="otp_channel" value="{{ session('setup_channel','email') }}">
              <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-clockwise me-1"></i>Resend OTP
              </button>
            </form>
          </div>

          {{-- Back --}}
          <div class="text-center mt-2">
            <a href="{{ route('setup.back') }}" class="small text-muted">
              <i class="bi bi-arrow-left me-1"></i>Back
            </a>
          </div>
          @endif

        </div>
      </div>

    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd(id,btn){
  const i=document.getElementById(id);
  if(i.type==='password'){i.type='text';btn.innerHTML='<i class="bi bi-eye-slash"></i>';}
  else{i.type='password';btn.innerHTML='<i class="bi bi-eye"></i>';}
}

function checkPasswordStrength(val, inputId) {
  const wrap = document.getElementById('strength_' + inputId);
  const bar  = document.getElementById('strengthBar_' + inputId);
  const text = document.getElementById('strengthText_' + inputId);
  if (!wrap) return;
  if (!val) { wrap.style.display = 'none'; return; }
  wrap.style.display = 'block';
  let score = 0;
  if (val.length >= 8)  score++;
  if (val.length >= 12) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const levels = [
    { min:0, max:1, label:'🔴 Weak',       color:'#ef4444', width:'20%' },
    { min:2, max:2, label:'🟠 Fair',        color:'#f97316', width:'40%' },
    { min:3, max:3, label:'🟡 Good',        color:'#eab308', width:'65%' },
    { min:4, max:4, label:'🟢 Strong',      color:'#22c55e', width:'85%' },
    { min:5, max:5, label:'💪 Very Strong', color:'#16a34a', width:'100%'},
  ];
  const level = levels.find(l => score >= l.min && score <= l.max) || levels[0];
  bar.style.width = level.width; bar.style.background = level.color;
  text.textContent = level.label; text.style.color = level.color;
}

function checkPwdRequirements(val, inputId) {
  const wrap = document.getElementById('pwdReq_' + inputId);
  if (!wrap) return false;
  wrap.style.display = val.length > 0 ? 'block' : 'none';
  const checks = {
    len:     val.length >= 8,
    upper:   /[A-Z]/.test(val),
    num:     /[0-9]/.test(val),
    special: /[!@#$%^&*()\-_=+\[\]{};':"\\|,.<>\/?'~]/.test(val),
  };
  Object.entries(checks).forEach(([key, passed]) => {
    const li   = document.getElementById('req_' + key + '_' + inputId);
    if (!li) return;
    const icon = li.querySelector('.req-icon');
    const text = li.querySelector('span:last-child');
    if (passed) {
      icon.textContent = '✓'; icon.style.color = '#16a34a';
      text.style.color = '#16a34a'; text.style.textDecoration = 'line-through'; text.style.opacity = '.7';
    } else {
      icon.textContent = '○'; icon.style.color = '#1d4ed8';
      text.style.color = '#1d4ed8'; text.style.textDecoration = 'none'; text.style.opacity = '1';
    }
  });
  return Object.values(checks).every(Boolean);
}

document.addEventListener('DOMContentLoaded', () => {
  const pwdInput = document.getElementById('setupPwd');
  if (pwdInput) {
    pwdInput.addEventListener('input', () => {
      checkPasswordStrength(pwdInput.value, 'setupPwd');
      checkPwdRequirements(pwdInput.value, 'setupPwd');
    });
    pwdInput.addEventListener('focus', () => {
      const wrap = document.getElementById('pwdReq_setupPwd');
      if (wrap && pwdInput.value.length === 0) wrap.style.display = 'block';
    });
  }

  // Resend countdown
  const wrap  = document.getElementById('resendWrap');
  const form  = document.getElementById('resendForm');
  const cdEl  = document.getElementById('resendCountdown');
  if (wrap && form && cdEl) {
    let seconds = 60;
    const timer = setInterval(() => {
      seconds--;
      const m = Math.floor(seconds / 60);
      const s = seconds % 60;
      cdEl.textContent = m + ':' + String(s).padStart(2, '0');
      if (seconds <= 0) {
        clearInterval(timer);
        wrap.style.display = 'none';
        form.style.display = 'block';
      }
    }, 1000);
  }
});
</script>
</body>
</html>
