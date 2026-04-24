@extends('layouts.app')
@section('content')
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--primary-bg) 0%,var(--primary-light) 100%);padding:2rem 1rem">

  <div style="width:100%;max-width:480px">

    {{-- Brand --}}
    <div style="text-align:center;margin-bottom:2rem">
      <div style="width:64px;height:64px;border-radius:18px;background:var(--primary);display:inline-flex;align-items:center;justify-content:center;margin-bottom:1rem;box-shadow:0 8px 24px rgba(229,57,53,.2)">
        <i class="bi bi-person-plus" style="font-size:1.8rem;color:#fff"></i>
      </div>
      <h1 style="font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;color:var(--gray-900);margin:0 0 .25rem">
        {{ $step == 1 ? 'Create Account' : 'Verify & Set Password' }}
      </h1>
      <p style="color:var(--gray-500);font-size:.875rem;margin:0">
        {{ $step == 1 ? 'Join the platform as a customer' : 'Enter the OTP sent to you' }}
      </p>
    </div>

    {{-- Step Indicator --}}
    <div style="display:flex;align-items:center;justify-content:center;gap:.5rem;margin-bottom:1.75rem">
      @foreach([1 => 'Details', 2 => 'Verify'] as $s => $label)
        <div style="display:flex;align-items:center;gap:.4rem">
          <div style="width:30px;height:30px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;
            background:{{ $step >= $s ? 'var(--primary)' : 'var(--gray-200)' }};
            color:{{ $step >= $s ? '#fff' : 'var(--gray-500)' }};
            box-shadow:{{ $step >= $s ? '0 2px 8px rgba(229,57,53,.3)' : 'none' }};
            transition:all .3s">
            @if($step > $s)
              <i class="bi bi-check" style="font-size:.9rem"></i>
            @else
              {{ $s }}
            @endif
          </div>
          <span style="font-size:.82rem;font-weight:{{ $step == $s ? '600' : '400' }};color:{{ $step == $s ? 'var(--gray-900)' : 'var(--gray-400)' }}">{{ $label }}</span>
        </div>
        @if($s < 2)
          <div style="flex:1;height:2px;background:{{ $step > 1 ? 'var(--primary)' : 'var(--gray-200)' }};max-width:48px;border-radius:2px;transition:background .3s"></div>
        @endif
      @endforeach
    </div>

    {{-- Card --}}
    <div style="background:#fff;border-radius:20px;box-shadow:0 8px 40px rgba(0,0,0,.08);padding:2rem;border:1.5px solid rgba(229,57,53,.08)">

      {{-- Alerts --}}
      @if(session('error'))
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
          <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
          <span>{{ session('error') }}</span>
        </div>
      @endif
      @if(session('msg'))
        <div class="alert alert-info d-flex align-items-center gap-2 mb-4">
          <i class="bi bi-info-circle-fill flex-shrink-0"></i>
          <span>{{ session('msg') }}</span>
        </div>
      @endif

      {{-- STEP 1: Personal Details --}}
      @if($step == 1)
      <form action="{{ route('register.post') }}" method="POST" novalidate id="regForm1">
        @csrf
        <div class="row g-3">

          <div class="col-12">
            <label class="form-label" for="fullname">Full Name <span style="color:var(--danger)">*</span></label>
            <input type="text" class="form-control @error('fullname') is-invalid @enderror"
                   id="fullname" name="fullname"
                   value="{{ old('fullname') }}"
                   placeholder="e.g. Juan dela Cruz"
                   required autofocus
                   oninvalid="this.setCustomValidity('Full name is required')"
                   oninput="this.setCustomValidity('')">
            @error('fullname')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-12">
            <label class="form-label" for="username">Username <span style="color:var(--danger)">*</span></label>
            <input type="text" class="form-control @error('username') is-invalid @enderror"
                   id="username" name="username"
                   value="{{ old('username') }}"
                   placeholder="e.g. juandc123"
                   required
                   pattern="[a-zA-Z0-9_]+"
                   minlength="3"
                   oninvalid="this.setCustomValidity('Username must be at least 3 characters (letters, numbers, underscore only)')"
                   oninput="this.setCustomValidity('')">
            <div class="form-text">Letters, numbers, and underscores only. Min 3 characters.</div>
            @error('username')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-12">
            <label class="form-label" for="email">Email Address <span style="color:var(--danger)">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-envelope" style="color:var(--primary)"></i></span>
              <input type="email" class="form-control @error('email') is-invalid @enderror"
                     id="email" name="email"
                     value="{{ old('email') }}"
                     placeholder="juan@email.com"
                     required
                     oninvalid="this.setCustomValidity('Please enter a valid email address')"
                     oninput="this.setCustomValidity('')">
              @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <div class="col-12">
            <label class="form-label" for="phone">Phone Number <span style="color:var(--danger)">*</span></label>
            <div class="input-group">
              <span class="input-group-text" style="font-weight:600;color:var(--gray-700)">+63</span>
              <input type="text" class="form-control @error('phone') is-invalid @enderror"
                     id="phone" name="phone"
                     value="{{ old('phone') }}"
                     placeholder="9XXXXXXXXX"
                     required
                     maxlength="10"
                     pattern="9[0-9]{9}"
                     oninput="this.value=this.value.replace(/\D/g,'').substring(0,10)"
                     oninvalid="this.setCustomValidity('Enter a valid PH number starting with 9')"
                     onchange="this.setCustomValidity('')">
              @error('phone')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div class="form-text">10 digits starting with 9. e.g. 9171234567</div>
          </div>

          {{-- OTP Channel --}}
          <div class="col-12">
            <label class="form-label">Send OTP via <span style="color:var(--danger)">*</span></label>
            <div style="display:flex;gap:.75rem">
              <label style="flex:1;border:1.5px solid var(--gray-200);border-radius:var(--radius-md);padding:.75rem;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:.6rem" id="labelEmail">
                <input type="radio" name="otp_channel" value="email" id="channelEmail" checked style="accent-color:var(--primary)"
                       onchange="highlightOtpChannel()">
                <div>
                  <div style="font-size:.85rem;font-weight:600">Email</div>
                  <div style="font-size:.75rem;color:var(--gray-500)">Sent to your email</div>
                </div>
              </label>
              <label style="flex:1;border:1.5px solid var(--gray-200);border-radius:var(--radius-md);padding:.75rem;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:.6rem" id="labelSms">
                <input type="radio" name="otp_channel" value="sms" id="channelSms" style="accent-color:var(--primary)"
                       onchange="highlightOtpChannel()">
                <div>
                  <div style="font-size:.85rem;font-weight:600">SMS</div>
                  <div style="font-size:.75rem;color:var(--gray-500)">Sent to your phone</div>
                </div>
              </label>
            </div>
          </div>

        </div>

        <button type="submit" class="btn btn-primary w-100 mt-4" style="padding:.75rem;font-size:.95rem;font-weight:600">
          Send OTP &amp; Continue
        </button>
      </form>
      @endif

      {{-- STEP 2: OTP + Password --}}
      @if($step == 2)
      <form action="{{ route('register.verify.post') }}" method="POST" novalidate id="regForm2">
        @csrf
        <div class="row g-3">

          <div class="col-12" style="text-align:center">
            <p style="font-size:.875rem;color:var(--gray-600);margin-bottom:1.25rem">
              We sent a 6-digit OTP to <strong>{{ session('reg_pending.otp_channel') === 'sms' ? '+63'.session('reg_pending.phone') : session('reg_pending.email') }}</strong>
            </p>
            <input type="text" class="form-control @error('otp') is-invalid @enderror"
                   name="otp"
                   maxlength="6"
                   placeholder="Enter 6-digit OTP"
                   required
                   style="text-align:center;font-size:1.6rem;font-weight:700;letter-spacing:.35em;padding:.875rem"
                   oninput="this.value=this.value.replace(/\D/g,'').substring(0,6)"
                   oninvalid="this.setCustomValidity('Please enter the 6-digit OTP')"
                   onchange="this.setCustomValidity('')"
                   autofocus>
            @error('otp')
              <div class="invalid-feedback" style="text-align:left">{{ $message }}</div>
            @enderror
            @include('components.dev-otp-hint')
          </div>

          <div class="col-12">
            <label class="form-label" for="regPwd">New Password <span style="color:var(--danger)">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-lock" style="color:var(--primary)"></i></span>
              <input type="password" class="form-control @error('password') is-invalid @enderror"
                     id="regPwd" name="password"
                     placeholder="Min 8 characters"
                     required
                     minlength="8"
                     autocomplete="new-password"
                     oninput="checkPwdStrength(this.value)"
                     oninvalid="this.setCustomValidity('Password must be at least 8 characters')"
                     onchange="this.setCustomValidity('')">
              <button type="button" class="btn btn-secondary" onclick="csTogglePwd('regPwd',this)" tabindex="-1"
                      style="border:1.5px solid var(--gray-200);border-left:0;border-radius:0 var(--radius-md) var(--radius-md) 0;background:var(--gray-50);padding:.6rem .875rem">
                <i class="bi bi-eye" style="color:var(--gray-500)"></i>
              </button>
              @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            {{-- Strength bar --}}
            <div style="height:4px;background:var(--gray-200);border-radius:2px;margin-top:.4rem;overflow:hidden">
              <div id="pwdStrengthBar" style="height:100%;width:0%;transition:width .3s,background .3s;border-radius:2px"></div>
            </div>
            <div id="pwdStrengthLabel" class="form-text"></div>
          </div>

          <div class="col-12">
            <label class="form-label" for="regConfirm">Confirm Password <span style="color:var(--danger)">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-lock-fill" style="color:var(--primary)"></i></span>
              <input type="password" class="form-control @error('confirm_password') is-invalid @enderror"
                     id="regConfirm" name="confirm_password"
                     placeholder="Repeat password"
                     required
                     autocomplete="new-password"
                     oninput="checkPwdMatch()"
                     oninvalid="this.setCustomValidity('Please confirm your password')"
                     onchange="this.setCustomValidity('')">
              <button type="button" class="btn btn-secondary" onclick="csTogglePwd('regConfirm',this)" tabindex="-1"
                      style="border:1.5px solid var(--gray-200);border-left:0;border-radius:0 var(--radius-md) var(--radius-md) 0;background:var(--gray-50);padding:.6rem .875rem">
                <i class="bi bi-eye" style="color:var(--gray-500)"></i>
              </button>
              @error('confirm_password')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div id="pwdMatchMsg" class="form-text"></div>
          </div>

        </div>

        <button type="submit" class="btn btn-primary w-100 mt-4" style="padding:.75rem;font-size:.95rem;font-weight:600">
          Create Account
        </button>

        <div style="text-align:center;margin-top:.875rem">
          <a href="{{ route('register.back') }}" style="font-size:.82rem;color:var(--gray-500)">
            <i class="bi bi-arrow-left" style="font-size:.75rem"></i> Go back
          </a>
        </div>
      </form>
      @endif

    </div>

    {{-- Footer --}}
    <div style="text-align:center;margin-top:1.5rem;font-size:.875rem;color:var(--gray-500)">
      Already have an account?
      <a href="{{ route('login') }}" style="color:var(--primary);font-weight:600;margin-left:.25rem">Sign in</a>
    </div>
  </div>
</div>

<script>
function csTogglePwd(id, btn) {
  const input = document.getElementById(id);
  const icon  = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'bi bi-eye';
  }
}

function highlightOtpChannel() {
  const emailChecked = document.getElementById('channelEmail').checked;
  document.getElementById('labelEmail').style.borderColor = emailChecked ? 'var(--primary)' : 'var(--gray-200)';
  document.getElementById('labelEmail').style.background  = emailChecked ? 'var(--primary-bg)' : '#fff';
  document.getElementById('labelSms').style.borderColor   = !emailChecked ? 'var(--primary)' : 'var(--gray-200)';
  document.getElementById('labelSms').style.background    = !emailChecked ? 'var(--primary-bg)' : '#fff';
}
highlightOtpChannel();

function checkPwdStrength(val) {
  const bar   = document.getElementById('pwdStrengthBar');
  const label = document.getElementById('pwdStrengthLabel');
  if (!bar) return;
  let score = 0;
  if (val.length >= 8) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const levels = [
    { w: '25%', c: '#EF5350', t: 'Weak' },
    { w: '50%', c: '#FF7043', t: 'Fair' },
    { w: '75%', c: '#FFA726', t: 'Good' },
    { w: '100%',c: '#43A047', t: 'Strong' },
  ];
  const lvl = levels[Math.max(0, score - 1)] || levels[0];
  bar.style.width      = val.length > 0 ? lvl.w : '0%';
  bar.style.background = lvl.c;
  label.textContent    = val.length > 0 ? lvl.t : '';
  label.style.color    = lvl.c;
}

function checkPwdMatch() {
  const pwd     = document.getElementById('regPwd');
  const confirm = document.getElementById('regConfirm');
  const msg     = document.getElementById('pwdMatchMsg');
  if (!pwd || !confirm || !msg) return;
  if (confirm.value.length === 0) { msg.textContent = ''; return; }
  if (pwd.value === confirm.value) {
    msg.textContent = 'Passwords match';
    msg.style.color = 'var(--success)';
    confirm.setCustomValidity('');
  } else {
    msg.textContent = 'Passwords do not match';
    msg.style.color = 'var(--danger)';
    confirm.setCustomValidity('Passwords do not match');
  }
}

document.getElementById('regForm2')?.addEventListener('submit', function(e) {
  checkPwdMatch();
  if (!this.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
});
</script>
@endsection
