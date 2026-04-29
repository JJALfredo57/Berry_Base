@extends('layouts.app')
@section('content')
@push('styles')
<style>
.login-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:2rem 1rem; background:linear-gradient(135deg,var(--primary-bg) 0%,var(--primary-light) 100%); }
.login-box  { width:100%; max-width:440px; animation:csSlideUp .45s cubic-bezier(.34,1.56,.64,1) both; }
.login-card { background:#fff; border-radius:24px; box-shadow:0 12px 48px rgba(0,0,0,.1),0 2px 8px rgba(0,0,0,.06); padding:2.25rem; border:1.5px solid color-mix(in srgb,var(--primary) 10%,transparent); }
.login-card .form-control:focus { transform:none; }
.login-brand { text-align:center; margin-bottom:2rem; }
.login-logo  { width:76px; height:76px; border-radius:22px; object-fit:cover; box-shadow:0 8px 28px color-mix(in srgb,var(--primary) 30%,transparent); margin-bottom:1rem; }
.login-logo-icon { width:76px; height:76px; border-radius:22px; background:var(--primary); display:inline-flex; align-items:center; justify-content:center; margin-bottom:1rem; box-shadow:0 8px 28px color-mix(in srgb,var(--primary) 30%,transparent); }
.login-title { font-family:'Playfair Display',serif; font-size:1.6rem; font-weight:700; color:var(--gray-900); margin:0 0 .3rem; }
.login-sub   { font-size:.88rem; color:var(--gray-500); margin:0; }
.login-accent-bar { height:3px; border-radius:99px; background:linear-gradient(90deg,var(--primary),var(--primary-light)); margin-bottom:2rem; }
.login-btn { padding:.8rem; font-size:.95rem; font-weight:600; border-radius:var(--radius-md); letter-spacing:.02em; }
.login-footer { text-align:center; margin-top:1.5rem; font-size:.875rem; color:var(--gray-500); }
.login-footer a { color:var(--primary); font-weight:600; }
</style>
@endpush

<div class="login-wrap">
  <div class="login-box">

    {{-- Brand --}}
    <div class="login-brand">
      @if(!empty($settings['logo_path']))
        <img src="{{ $settings['logo_path'] }}" alt="{{ $settings['site_title'] ?? 'Cake Shop' }}" class="login-logo"
             onerror="this.style.display='none';document.getElementById('loginLogoFallback').style.display='inline-flex'">
        <div id="loginLogoFallback" class="login-logo-icon" style="display:none">
          <i class="bi bi-shop" style="font-size:2rem;color:#fff"></i>
        </div>
      @else
        <div class="login-logo-icon">
          <i class="bi bi-shop" style="font-size:2rem;color:#fff"></i>
        </div>
      @endif
      <h1 class="login-title">Seller Sign In</h1>
      <p class="login-sub">Sign in to manage your cake shop</p>
    </div>

    {{-- Card --}}
    <div class="login-card">
      <div class="login-accent-bar"></div>

      @if(session('error'))
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4 cs-scale-in">
          <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i><span>{{ session('error') }}</span>
        </div>
      @endif
      @if(session('msg'))
        <div class="alert alert-success d-flex align-items-center gap-2 mb-4 cs-scale-in">
          <i class="bi bi-check-circle-fill flex-shrink-0"></i><span>{{ session('msg') }}</span>
        </div>
      @endif

      <form action="{{ route('login.post') }}" method="POST" novalidate>
        @csrf

        <div class="mb-3">
          <label class="form-label" for="username">Username or Email</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person" style="color:var(--primary)"></i></span>
            <input type="text" class="form-control @error('username') is-invalid @enderror"
                   id="username" name="username" value="{{ old('username') }}"
                   placeholder="Enter your username or email" required autofocus autocomplete="username">
            @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label d-flex justify-content-between align-items-center" for="loginPwd">
            <span>Password</span>
            <a href="{{ route('forgot.show') }}" style="font-size:.8rem;font-weight:400;color:var(--primary)">Forgot password?</a>
          </label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock" style="color:var(--primary)"></i></span>
            <input type="password" class="form-control @error('password') is-invalid @enderror"
                   id="loginPwd" name="password" placeholder="Enter your password"
                   required autocomplete="current-password">
            <button type="button" class="btn btn-secondary" onclick="csTogglePwd('loginPwd',this)" tabindex="-1"
                    style="border:1.5px solid var(--gray-200);border-left:0;border-radius:0 var(--radius-md) var(--radius-md) 0;background:var(--gray-50);padding:.6rem .875rem">
              <i class="bi bi-eye" style="color:var(--gray-500)"></i>
            </button>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 login-btn">
          <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>
      </form>
    </div>

    <div class="login-footer mt-3">
      New seller? <a href="{{ route('seller.apply') }}">Apply as Seller</a>
    </div>
    <div class="login-footer mt-2">
      <a href="{{ route('platform.home') }}" style="color:var(--gray-400)"><i class="bi bi-arrow-left me-1"></i>Back to Platform</a>
    </div>
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
</script>
@endsection
