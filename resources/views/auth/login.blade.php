@extends('layouts.app')
@section('content')
@push('styles')
<style>
html:has(.login-wrap),
body:has(.login-wrap) {
  height:100%;
  overflow:hidden !important;
  overscroll-behavior:none;
}
body.seller-login-page {
  height:100%;
  overflow:hidden !important;
  overscroll-behavior:none;
}
body:has(.login-wrap) footer,
body:has(.login-wrap) .cust-topbar,
body:has(.login-wrap) .cust-sidebar,
body:has(.login-wrap) .csb-overlay,
body.seller-login-page footer,
body.seller-login-page .cust-topbar,
body.seller-login-page .cust-sidebar,
body.seller-login-page .csb-overlay {
  display:none !important;
}
body:has(.login-wrap) .customer-wrap {
  max-width:none !important;
  width:100% !important;
  min-height:0 !important;
  height:100dvh !important;
  padding:0 !important;
  margin:0 !important;
  animation:none !important;
  transform:none !important;
}
body.seller-login-page .customer-wrap {
  max-width:none !important;
  width:100% !important;
  min-height:0 !important;
  height:100dvh !important;
  padding:0 !important;
  margin:0 !important;
  animation:none !important;
  transform:none !important;
}
.login-wrap {
  position:fixed;
  inset:0;
  z-index:20000;
  height:100dvh;
  display:flex;
  align-items:center;
  justify-content:center;
  padding:clamp(.75rem,2vw,1.25rem);
  overflow:hidden;
  isolation:isolate;
  background:#1c0903;
}
.login-wrap::before {
  content:'';
  position:absolute;
  inset:0;
  background:
    linear-gradient(115deg, rgba(26,8,2,.90) 0%, rgba(58,19,6,.58) 42%, rgba(10,4,2,.70) 100%),
    url('/background_system_opening.png') center/cover no-repeat,
    linear-gradient(135deg,#2b1206 0%,#fff1e8 100%);
  z-index:-1;
}
.login-wrap::after {
  content:'';
  position:absolute;
  inset:0;
  background:
    radial-gradient(circle at 18% 16%, rgba(255,255,255,.24), transparent 24%),
    radial-gradient(circle at 78% 70%, rgba(251,191,36,.18), transparent 26%),
    linear-gradient(90deg, rgba(255,255,255,.07), transparent 48%, rgba(0,0,0,.24));
  pointer-events:none;
  z-index:-1;
}
.login-box  {
  width:min(100%,420px);
  max-height:calc(100dvh - clamp(1.5rem,4vw,2.5rem));
  overflow:hidden;
  animation:csSlideUp .45s cubic-bezier(.34,1.56,.64,1) both;
}
.login-card {
  background:rgba(255,255,255,.88);
  backdrop-filter:blur(16px) saturate(1.12);
  -webkit-backdrop-filter:blur(16px) saturate(1.12);
  border-radius:18px;
  box-shadow:0 22px 70px rgba(12,4,1,.34),0 4px 16px rgba(25,10,4,.14);
  padding:clamp(1.25rem,3vh,1.85rem);
  border:1px solid rgba(255,255,255,.58);
}
.login-card .form-control:focus { transform:none; }
.login-brand { text-align:center; margin-bottom:clamp(.9rem,2.4vh,1.35rem); color:#fff; text-shadow:0 8px 28px rgba(0,0,0,.32); }
.login-logo  { width:64px; height:64px; border-radius:18px; object-fit:cover; box-shadow:0 12px 34px rgba(0,0,0,.28); margin-bottom:.75rem; background:#fff; }
.login-logo-icon { width:64px; height:64px; border-radius:18px; background:linear-gradient(135deg,var(--primary),var(--primary-dark)); display:inline-flex; align-items:center; justify-content:center; margin-bottom:.75rem; box-shadow:0 12px 34px rgba(0,0,0,.28); }
.login-title { font-family:'Playfair Display',serif; font-size:clamp(1.45rem,4vh,1.85rem); font-weight:700; color:#fff; margin:0 0 .15rem; }
.login-sub   { font-size:.86rem; color:rgba(255,255,255,.86); margin:0; }
.login-accent-bar { height:3px; border-radius:99px; background:linear-gradient(90deg,var(--primary),#f59e0b,#fbbf24); margin-bottom:1.25rem; }
.login-btn { padding:.8rem; font-size:.95rem; font-weight:600; border-radius:var(--radius-md); letter-spacing:.02em; }
.login-footer { text-align:center; margin-top:.8rem; font-size:.85rem; color:rgba(255,255,255,.86); text-shadow:0 8px 22px rgba(0,0,0,.32); }
.login-footer a { color:#fff; font-weight:700; text-decoration:none; }
.login-footer a:hover { text-decoration:underline; }
@media (max-width:480px) {
  .login-wrap { padding:.8rem; align-items:center; }
  .login-box { width:100%; }
  .login-brand { margin-bottom:.8rem; }
  .login-card { border-radius:16px; }
}
@media (max-height:720px) {
  .login-brand { margin-bottom:.75rem; }
  .login-logo,
  .login-logo-icon { width:54px;height:54px;border-radius:15px;margin-bottom:.5rem; }
  .login-card { padding:1rem 1.1rem; }
  .login-accent-bar { margin-bottom:.9rem; }
  .login-card .mb-3 { margin-bottom:.65rem !important; }
  .login-card .mb-4 { margin-bottom:.9rem !important; }
  .login-footer { margin-top:.55rem; }
}
@media (max-height:560px) {
  .login-logo,
  .login-logo-icon { display:none !important; }
  .login-title { font-size:1.25rem; }
  .login-sub { font-size:.8rem; }
  .login-card { padding:.85rem 1rem; }
  .login-card label { margin-bottom:.25rem; }
}
</style>
@endpush

<script>
document.documentElement.classList.add('seller-login-root');
document.body.classList.add('seller-login-page');
</script>
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
document.body.classList.add('seller-login-page');
function csTogglePwd(id, btn) {
  const input = document.getElementById(id);
  const icon  = btn.querySelector('i');
  input.type  = input.type === 'password' ? 'text' : 'password';
  icon.className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
  icon.style.color = 'var(--gray-500)';
}
</script>
@endsection
