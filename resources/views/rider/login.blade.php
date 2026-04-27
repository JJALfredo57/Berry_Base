<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rider Portal — {{ config('app.name', 'Cake Shop') }}</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root { --primary: #e91e8c; --primary-dark: #c2185b; }
    * { box-sizing: border-box; }
    body {
      min-height: 100dvh;
      background: linear-gradient(135deg, #fce4ec 0%, #f8bbd0 40%, #fce4ec 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    }

    .portal-card {
      width: 100%;
      max-width: 400px;
      background: #fff;
      border-radius: 1.5rem;
      box-shadow: 0 20px 60px rgba(233,30,140,.15), 0 4px 20px rgba(0,0,0,.08);
      overflow: hidden;
    }

    .portal-header {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
      padding: 2rem 1.75rem 1.75rem;
      text-align: center;
      color: #fff;
    }
    .portal-header .icon-wrap {
      width: 68px; height: 68px;
      background: rgba(255,255,255,.2);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 1rem;
      font-size: 2rem;
    }
    .portal-header h1 { font-size: 1.35rem; font-weight: 700; margin: 0 0 .25rem; }
    .portal-header p  { font-size: .85rem; margin: 0; opacity: .85; }

    .portal-body { padding: 1.75rem; }

    .form-label {
      font-size: .8rem;
      font-weight: 600;
      color: #374151;
      text-transform: uppercase;
      letter-spacing: .04em;
      margin-bottom: .4rem;
    }

    .input-wrap {
      position: relative;
      margin-bottom: 1.1rem;
    }
    .input-wrap .input-icon {
      position: absolute;
      left: .85rem; top: 50%; transform: translateY(-50%);
      color: #9ca3af; font-size: 1rem; pointer-events: none;
    }
    .input-wrap input {
      width: 100%;
      padding: .75rem .85rem .75rem 2.5rem;
      border: 1.5px solid #e5e7eb;
      border-radius: .75rem;
      font-size: .95rem;
      color: #111827;
      transition: border-color .15s, box-shadow .15s;
      outline: none;
      background: #fafafa;
    }
    .input-wrap input:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(233,30,140,.12);
      background: #fff;
    }
    .input-wrap input.pin-input {
      letter-spacing: .35em;
      font-size: 1.5rem;
      font-weight: 700;
      text-align: center;
      padding-left: .85rem;
      color: var(--primary);
    }

    .btn-login {
      width: 100%;
      padding: .85rem;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
      color: #fff;
      border: none;
      border-radius: .75rem;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      transition: opacity .15s, transform .1s;
      display: flex; align-items: center; justify-content: center; gap: .5rem;
    }
    .btn-login:hover  { opacity: .92; }
    .btn-login:active { transform: scale(.98); }

    .alert-err {
      background: #fff1f2;
      border: 1.5px solid #fecdd3;
      border-radius: .65rem;
      padding: .7rem .9rem;
      color: #9f1239;
      font-size: .85rem;
      margin-bottom: 1.1rem;
      display: flex; align-items: flex-start; gap: .5rem;
    }

    .portal-footer {
      text-align: center;
      padding: .85rem 1.75rem 1.5rem;
      font-size: .75rem;
      color: #9ca3af;
    }
    .portal-footer strong { color: var(--primary); }

    .pin-hint {
      text-align: center;
      font-size: .78rem;
      color: #6b7280;
      margin-top: -.5rem;
      margin-bottom: 1.1rem;
    }

    @keyframes fadeSlide {
      from { opacity:0; transform: translateY(12px); }
      to   { opacity:1; transform: translateY(0); }
    }
    .portal-card { animation: fadeSlide .35s ease; }
  </style>
</head>
<body>

<div class="portal-card">

  {{-- Header --}}
  <div class="portal-header">
    <div class="icon-wrap"><i class="bi bi-bicycle"></i></div>
    <h1>Rider Portal</h1>
    <p>Enter your phone number and delivery PIN to continue</p>
  </div>

  {{-- Body --}}
  <div class="portal-body">

    @if(session('err'))
    <div class="alert-err">
      <i class="bi bi-exclamation-circle-fill flex-shrink-0 mt-1"></i>
      <span>{{ session('err') }}</span>
    </div>
    @endif

    <form action="{{ route('rider.login.verify') }}" method="POST" id="loginForm" autocomplete="off">
      @csrf

      {{-- Phone --}}
      <label class="form-label">Phone Number</label>
      <div class="input-wrap">
        <i class="bi bi-phone input-icon"></i>
        <input type="tel" name="phone" id="phoneInput"
               value="{{ old('phone') }}"
               placeholder="e.g. 09104587030"
               inputmode="tel"
               maxlength="20"
               required>
      </div>

      {{-- PIN --}}
      <label class="form-label">Delivery PIN</label>
      <div class="input-wrap">
        <input type="text" name="pin" id="pinInput"
               class="pin-input"
               placeholder="● ● ● ● ● ●"
               inputmode="numeric"
               pattern="[0-9]{6}"
               maxlength="6"
               autocomplete="one-time-code"
               required>
      </div>
      <p class="pin-hint">
        <i class="bi bi-info-circle me-1"></i>6-digit PIN from your assignment SMS
      </p>

      <button type="submit" class="btn-login" id="submitBtn">
        <i class="bi bi-box-arrow-in-right"></i>
        Access My Delivery
      </button>
    </form>
  </div>

  {{-- Footer --}}
  <div class="portal-footer">
    <i class="bi bi-shield-check me-1"></i>
    Secured by <strong>{{ config('app.name', 'Cake Shop') }}</strong>
    &nbsp;&bull;&nbsp;PIN is single-use per delivery
  </div>

</div>

<script>
// Auto-format PIN input: digits only, max 6
document.getElementById('pinInput').addEventListener('input', function () {
  this.value = this.value.replace(/\D/g, '').slice(0, 6);
});

// Loading state on submit
document.getElementById('loginForm').addEventListener('submit', function () {
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" style="width:.9rem;height:.9rem"></span>Verifying…';
});

// Auto-focus phone on load
document.addEventListener('DOMContentLoaded', function () {
  const phone = document.getElementById('phoneInput');
  const pin   = document.getElementById('pinInput');
  (phone.value ? pin : phone).focus();
});
</script>

</body>
</html>
