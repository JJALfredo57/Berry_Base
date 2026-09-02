<!DOCTYPE html>
<html lang="en">
<head>
  @php
    $platformBrand = null;
    try { $platformBrand = \Illuminate\Support\Facades\DB::table('platform_settings')->first(); } catch (\Throwable $e) {}
    $rawPrimary = $platformBrand->platform_primary_color ?? '#7B3A0F';
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $rawPrimary)) $rawPrimary = '#7B3A0F';
    $pbgColor = $platformBrand->platform_bg_color ?? '#FFF8F8';
  @endphp
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="{{ $rawPrimary }}">
  <title>Create Rider Password</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    * { box-sizing:border-box; }
    body { margin:0; min-height:100dvh; background:{{ $pbgColor }}; color:#111827; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; display:grid; place-items:center; padding:18px; }
    .card { width:min(100%,420px); background:#fff; border:1px solid #e5e7eb; border-radius:8px; box-shadow:0 16px 46px rgba(17,24,39,.12); overflow:hidden; }
    .head { background:{{ $rawPrimary }}; color:#fff; padding:18px; }
    .head h1 { margin:0; font-size:22px; line-height:1.15; }
    .head p { margin:5px 0 0; opacity:.9; font-size:14px; }
    .body { padding:18px; }
    .notice { border-radius:8px; padding:11px 12px; margin-bottom:12px; font-size:14px; font-weight:650; }
    .err { background:#fff1f2; color:#9f1239; border:1px solid #fecdd3; }
    label { display:block; font-size:13px; font-weight:800; margin:0 0 6px; color:#374151; }
    .field { margin-bottom:13px; }
    .input-wrap { display:flex; align-items:stretch; border:1px solid #d1d5db; border-radius:8px; overflow:hidden; background:#fff; }
    .input-wrap input { width:100%; border:0; outline:0; padding:12px; font:inherit; min-width:0; }
    .toggle { border:0; border-left:1px solid #e5e7eb; width:44px; background:#f9fafb; cursor:pointer; color:#6b7280; }
    .rules { margin:2px 0 15px; padding-left:18px; color:#6b7280; font-size:13px; line-height:1.45; }
    .btn { width:100%; min-height:44px; border:0; border-radius:8px; background:{{ $rawPrimary }}; color:#fff; font-weight:850; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; font-size:15px; }
    .rider { background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:10px 12px; margin-bottom:14px; font-size:14px; color:#374151; }
  </style>
</head>
<body>
  <main class="card">
    <header class="head">
      <h1>Create Rider Password</h1>
      <p>Your seller gave you a temporary PIN. Create your own password before opening the dashboard.</p>
    </header>
    <section class="body">
      @if(session('err') || $errors->any())
        <div class="notice err"><i class="bi bi-exclamation-circle"></i> {{ session('err') ?: $errors->first() }}</div>
      @endif

      <div class="rider">
        <strong>{{ $rider->name }}</strong><br>
        {{ $rider->phone ?: 'No phone on record' }}
      </div>

      <form method="POST" action="{{ route('rider.password.setup.update') }}">
        @csrf
        <div class="field">
          <label for="password">New Password</label>
          <div class="input-wrap">
            <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password" autofocus>
            <button class="toggle" type="button" onclick="togglePwd('password', this)"><i class="bi bi-eye"></i></button>
          </div>
        </div>
        <ul class="rules">
          <li>At least 8 characters</li>
          <li>Has uppercase letter, number, and special character</li>
        </ul>
        <div class="field">
          <label for="confirm_password">Confirm Password</label>
          <div class="input-wrap">
            <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
            <button class="toggle" type="button" onclick="togglePwd('confirm_password', this)"><i class="bi bi-eye"></i></button>
          </div>
        </div>
        <button class="btn" type="submit"><i class="bi bi-shield-check"></i> Save Password</button>
      </form>
    </section>
  </main>

  <script>
    function togglePwd(id, btn) {
      const input = document.getElementById(id);
      const icon = btn.querySelector('i');
      input.type = input.type === 'password' ? 'text' : 'password';
      icon.className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    }
  </script>
</body>
</html>
