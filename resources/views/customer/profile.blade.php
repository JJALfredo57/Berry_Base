@extends('layouts.app')
@section('content')
<div class="container-fluid py-4">
  <div class="row justify-content-center">
    <div class="col-lg-8">

      <h4 class="fw-bold mb-4"><i class="bi bi-person-circle me-2" style="color:var(--primary)"></i>My Profile</h4>

      {{-- Stats cards --}}
      <div class="row g-3 mb-4">
        <div class="col-6">
          <div class="card text-center p-3">
            <div class="fw-bold" style="font-size:1.8rem;color:var(--primary)">{{ $orderCount }}</div>
            <div class="text-muted small">Total Orders</div>
          </div>
        </div>
        <div class="col-6">
          <div class="card text-center p-3">
            <div class="fw-bold" style="font-size:1.8rem;color:#f59e0b">{{ $pendingCount }}</div>
            <div class="text-muted small">Pending Orders</div>
          </div>
        </div>
      </div>

      {{-- Profile Photo + Info --}}
      <div class="card mb-4">
        <div class="card-body p-4">
          <h6 class="fw-bold mb-4"><i class="bi bi-person me-2" style="color:var(--primary)"></i>Personal Information</h6>

          @if(session('msg'))<div class="alert alert-success border-0 py-2"><i class="bi bi-check-circle me-2"></i>{{ session('msg') }}</div>@endif
          @if(session('error'))<div class="alert alert-danger border-0 py-2"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>@endif

          <form action="{{ route('customer.profile.update') }}" method="POST" enctype="multipart/form-data">
            @csrf

            {{-- Profile photo upload --}}
            <div class="mb-4 d-flex align-items-center gap-4">
              <div class="position-relative">
                @if(!empty($user->profile_photo))
                  <img src="{{ $user->profile_photo }}" id="photoPreview" alt="Photo"
                       style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid var(--primary)"
                       onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($user->fullname) }}&background=e91e63&color=fff&size=80'">
                @else
                  <div id="photoPreview" style="width:80px;height:80px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;border:3px solid var(--primary)">
                    <span style="color:#fff;font-size:2rem;font-weight:700">{{ strtoupper(substr($user->fullname,0,1)) }}</span>
                  </div>
                @endif
                <label for="photoInput" style="position:absolute;bottom:0;right:0;width:26px;height:26px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid #fff">
                  <i class="bi bi-camera-fill text-white" style="font-size:.65rem"></i>
                </label>
                <input type="file" id="photoInput" name="profile_photo" accept="image/*" class="d-none" onchange="previewPhoto(this)">
              </div>
              <div>
                <div class="fw-semibold">{{ $user->fullname }}</div>
                <div class="text-muted small">@<span>{{ $user->username }}</span></div>
                <div class="text-muted small mt-1">Click the camera icon to change photo</div>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-sm-6">
                <label class="form-label fw-semibold small">Full Name</label>
                <input type="text" class="form-control" name="fullname" value="{{ $user->fullname }}" required>
              </div>
              <div class="col-sm-6">
                <label class="form-label fw-semibold small">Username</label>
                <input type="text" class="form-control bg-light" value="{{ $user->username }}" disabled>
                <div class="form-text">Username cannot be changed.</div>
              </div>
              <div class="col-sm-6">
                <label class="form-label fw-semibold small">Email Address</label>
                <input type="email" class="form-control" name="email" value="{{ $user->email }}" required>
              </div>
              <div class="col-sm-6">
                <label class="form-label fw-semibold small">Phone Number</label>
                <input type="text" class="form-control" name="phone" value="{{ $user->phone }}" required>
              </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-save me-1"></i>Save Changes</button>
          </form>
        </div>
      </div>

      {{-- Change Password --}}
      <div class="card">
        <div class="card-body p-4">
          <h6 class="fw-bold mb-3"><i class="bi bi-lock me-2" style="color:var(--primary)"></i>Change Password</h6>

          @php $cpStep = $step ?? session('cp_step', 1); @endphp

          {{-- Step 1: Choose OTP channel --}}
          @if($cpStep == 1)
          <p class="text-muted small">To change your password, we'll send a verification code first.</p>
          <form action="{{ route('customer.profile.password.send_otp') }}" method="POST">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold small">Send Verification Code Via</label>
              <div class="d-flex gap-3">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="otp_channel" value="email" id="cpEmail" checked>
                  <label class="form-check-label" for="cpEmail"><i class="bi bi-envelope me-1"></i>Email</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="otp_channel" value="sms" id="cpSms">
                  <label class="form-check-label" for="cpSms"><i class="bi bi-phone me-1"></i>SMS</label>
                </div>
              </div>
            </div>
            <button type="submit" class="btn btn-outline-danger">
              <i class="bi bi-send me-1"></i>Send OTP Code
            </button>
          </form>

          {{-- Step 2: Enter OTP --}}
          @elseif($cpStep == 2)
          <form action="{{ route('customer.profile.password.verify_otp') }}" method="POST">
            @csrf
            <p class="text-muted small mb-3">
              <i class="bi bi-shield-check me-1" style="color:var(--primary)"></i>
              Enter the 6-digit OTP code sent to you.
            </p>
            <div class="mb-3">
              <label class="form-label fw-semibold small">OTP Code</label>
              <input type="text" class="form-control text-center fw-bold" name="otp"
                     maxlength="6" placeholder="000000" autofocus required
                     style="font-size:1.4rem;letter-spacing:.4rem"
                     oninput="this.value=this.value.replace(/\D/g,'')">
              <div class="form-text text-center">OTP expires in 10 minutes</div>
              @include('components.dev-otp-hint')
            </div>
            <button type="submit" class="btn btn-outline-danger mb-2">
              <i class="bi bi-check-lg me-1"></i>Verify OTP
            </button>
          </form>
          {{-- Resend --}}
          <div class="mt-1">
            <span class="small text-muted" id="cpResendWrap">
              Resend in <span id="cpResendCountdown" class="fw-semibold" style="color:var(--primary)">1:00</span>
            </span>
            <form action="{{ route('customer.profile.password.send_otp') }}" method="POST" id="cpResendForm" style="display:none">
              @csrf
              <input type="hidden" name="otp_channel" value="{{ session('cp_channel','email') }}">
              <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-clockwise me-1"></i>Resend OTP
              </button>
            </form>
          </div>
          {{-- Back --}}
          <div class="mt-2">
            <a href="{{ route('customer.profile.password.back') }}" class="small text-muted">
              <i class="bi bi-arrow-left me-1"></i>Back
            </a>
          </div>

          {{-- Step 3: New Password form --}}
          @else
          <form action="{{ route('customer.profile.password') }}" method="POST">
            @csrf
            <div class="row g-3">
              <div class="col-sm-6">
                <label class="form-label fw-semibold small">New Password</label>
                <div class="input-group">
                  <input type="password" class="form-control" name="new_password" id="newPwd" data-strength="newPwd" data-pwdreq="newPwd" required minlength="8">
                  <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('newPwd',this)"><i class="bi bi-eye"></i></button>
                </div>
                @include('layouts.password_strength', ['inputId'=>'newPwd'])
                @include('layouts.password_requirements', ['inputId'=>'newPwd'])
              </div>
              <div class="col-sm-6">
                <label class="form-label fw-semibold small">Confirm New Password</label>
                <div class="input-group">
                  <input type="password" class="form-control" name="confirm_password" id="confirmPwd" required>
                  <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('confirmPwd',this)"><i class="bi bi-eye"></i></button>
                </div>
              </div>
            </div>
            <button type="submit" class="btn btn-outline-danger mt-3"><i class="bi bi-shield-lock me-1"></i>Update Password</button>
          </form>
          @endif

        </div>
      </div>

    </div>
  </div>
</div>
@push('scripts')
<script>
function previewPhoto(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const prev = document.getElementById('photoPreview');
      if (prev.tagName === 'IMG') {
        prev.src = e.target.result;
      } else {
        const img = document.createElement('img');
        img.src = e.target.result;
        img.id = 'photoPreview';
        img.style = 'width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid var(--primary)';
        prev.replaceWith(img);
      }
    };
    reader.readAsDataURL(input.files[0]);
  }
}
function togglePwd(id, btn) {
  const input = document.getElementById(id);
  if (input.type === 'password') { input.type = 'text'; btn.innerHTML = '<i class="bi bi-eye-slash"></i>'; }
  else { input.type = 'password'; btn.innerHTML = '<i class="bi bi-eye"></i>'; }
}
@if(session('cp_step') == 2)
(function() {
  const wrap  = document.getElementById('cpResendWrap');
  const form  = document.getElementById('cpResendForm');
  const cdEl  = document.getElementById('cpResendCountdown');
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
