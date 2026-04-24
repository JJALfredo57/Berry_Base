@extends('layouts.app')
@section('content')
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
      <div class="text-center mb-4">
        <div style="width:60px;height:60px;border-radius:16px;background:var(--primary);display:inline-flex;align-items:center;justify-content:center;margin-bottom:1rem">
          <i class="bi bi-key-fill text-white" style="font-size:1.6rem"></i>
        </div>
        <h4 class="fw-bold mb-1">Forgot Password</h4>
        <p class="text-muted small">
          @if($step==1) Enter your registered email address
          @elseif($step==2) Enter the 6-digit OTP sent to you
          @else Create a new secure password
          @endif
        </p>
      </div>

      {{-- Step indicator --}}
      <div class="d-flex justify-content-center align-items-center gap-2 mb-4">
        @foreach(['Email','OTP','New Password'] as $i=>$label)
          <div class="d-flex align-items-center gap-1">
            <span style="width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;
                  background:{{ $step > $i ? 'var(--primary)' : ($step === $i+1 ? 'var(--primary)' : '#dee2e6') }};
                  color:{{ $step > $i || $step === $i+1 ? '#fff' : '#666' }}">
              @if($step > $i+1)<i class="bi bi-check"></i>@else {{ $i+1 }}@endif
            </span>
            <span class="small {{ $step === $i+1 ? 'fw-semibold' : 'text-muted' }}" style="font-size:.8rem">{{ $label }}</span>
          </div>
          @if($i < 2)<div style="flex:1;height:2px;background:{{ $step > $i+1 ? 'var(--primary)' : '#dee2e6' }};max-width:30px"></div>@endif
        @endforeach
      </div>

      <div class="card">
        <div class="card-body p-4">
          @if(session('error'))
            <div class="alert alert-danger border-0 py-2"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
          @endif
          @if(session('msg'))
            <div class="alert alert-info border-0 py-2"><i class="bi bi-envelope-check me-2"></i>{{ session('msg') }}</div>
          @endif

          @if($step == 1)
          <form action="{{ route('forgot.send_otp') }}" method="POST">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold small">Email Address</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input type="email" class="form-control" name="email" value="{{ old('email') }}"
                       placeholder="your@email.com" required autofocus>
              </div>
            </div>
            {{-- OTP Channel --}}
            <div class="mb-3">
              <label class="form-label fw-semibold small">Send OTP Via</label>
              <div class="d-flex gap-3">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="otp_channel" value="email" id="fpEmail" checked>
                  <label class="form-check-label" for="fpEmail"><i class="bi bi-envelope me-1"></i>Email</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="otp_channel" value="sms" id="fpSms">
                  <label class="form-check-label" for="fpSms"><i class="bi bi-phone me-1"></i>SMS</label>
                </div>
              </div>
            </div>
            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-send me-1"></i>Send OTP Code
            </button>
          </form>

          @elseif($step == 2)
          <form action="{{ route('forgot.verify_otp') }}" method="POST">
            @csrf
            <p class="text-muted small mb-3">
              <i class="bi bi-shield-check me-1" style="color:var(--primary)"></i>
              Check your email or SMS for the 6-digit OTP code.
            </p>
            <div class="mb-3">
              <label class="form-label fw-semibold small">Enter OTP Code</label>
              <input type="text" class="form-control text-center fw-bold"
                     name="otp" maxlength="6" placeholder="000000"
                     style="font-size:1.5rem;letter-spacing:.5rem" autofocus required
                     oninput="this.value=this.value.replace(/\D/g,'')">
              <div class="form-text text-center">OTP expires in 10 minutes</div>
              @include('components.dev-otp-hint')
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-2">
              <i class="bi bi-check-lg me-1"></i>Verify OTP
            </button>
          </form>
          {{-- Resend OTP --}}
          <div class="text-center mt-2">
            <span class="small text-muted" id="resendWrap">
              Resend OTP in <span id="resendCountdown" class="fw-semibold" style="color:var(--primary)">1:00</span>
            </span>
            <form action="{{ route('forgot.send_otp') }}" method="POST" id="resendForm" style="display:none">
              @csrf
              <input type="hidden" name="email" value="{{ session('fp_email') }}">
              <input type="hidden" name="otp_channel" value="{{ session('fp_channel','email') }}">
              <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-clockwise me-1"></i>Resend OTP
              </button>
            </form>
          </div>
          {{-- Back button --}}
          <div class="text-center mt-2">
            <a href="{{ route('forgot.back') }}" class="btn btn-link btn-sm text-muted p-0">
              <i class="bi bi-arrow-left me-1"></i>Back
            </a>
          </div>

          @else
          <form action="{{ route('forgot.reset') }}" method="POST">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold small">New Password</label>
              <div class="input-group">
                <input type="password" class="form-control" name="password" id="newPwd"
                       required minlength="8" placeholder="Min. 8 characters"
                       data-pwdreq="newPwd">
                <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('newPwd',this)"><i class="bi bi-eye"></i></button>
              </div>
              @include('layouts.password_requirements', ['inputId' => 'newPwd'])
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold small">Confirm New Password</label>
              <div class="input-group">
                <input type="password" class="form-control" name="confirm_password" id="confirmPwd" required placeholder="Repeat password">
                <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('confirmPwd',this)"><i class="bi bi-eye"></i></button>
              </div>
            </div>
            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-shield-check me-1"></i>Reset Password
            </button>
          </form>
          @endif
        </div>
      </div>
      <div class="text-center mt-3">
        <a href="{{ route('login') }}" class="small text-muted"><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
      </div>
    </div>
  </div>
</div>
@push('scripts')
<script>
function togglePwd(id, btn) {
  const input = document.getElementById(id);
  if (input.type === 'password') { input.type = 'text'; btn.innerHTML = '<i class="bi bi-eye-slash"></i>'; }
  else { input.type = 'password'; btn.innerHTML = '<i class="bi bi-eye"></i>'; }
}

// ── Resend OTP Countdown (1 minute) ──────────────────────────
@if($step == 2)
(function() {
  const wrap     = document.getElementById('resendWrap');
  const form     = document.getElementById('resendForm');
  const cdEl     = document.getElementById('resendCountdown');
  if (!wrap || !form || !cdEl) return;

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
})();
@endif
</script>
@endpush
@endsection
