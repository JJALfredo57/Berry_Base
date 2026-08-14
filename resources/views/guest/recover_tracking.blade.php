@extends('layouts.app')
@section('content')
@php
  $step = $recovery['step'] ?? 'find';
  $verified = !empty($recovery['verified']);
  $recoveredCode = $recovery['recovered_code'] ?? null;
  $selectedOrder = $recoveredCode ? $orders->firstWhere('id', $recovery['selected_order_id'] ?? '') : null;
@endphp

<div class="recover-page">
  <div class="recover-shell">
    <div class="recover-topbar">
      <div>
        <div class="recover-kicker">Guest Order Tracking</div>
        <h3 class="recover-title">Recover Tracking Code</h3>
        <div class="recover-subtitle">Verify the phone number used for the order, then recover only the specific tracking code you need.</div>
      </div>
      <a href="{{ route('catalog') }}" class="recover-back-btn">
        <i class="bi bi-arrow-left me-1"></i>Back to Cakes
      </a>
    </div>

  @if(session('msg'))
    <div class="alert alert-success border-0"><i class="bi bi-check-circle me-2"></i>{{ session('msg') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger border-0"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger border-0">
      <i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}
    </div>
  @endif

  @include('components.dev-otp-hint')

  <style>
    .recover-page{
      width:100%;
      min-height:calc(100vh - 92px);
      padding:clamp(12px,2.4vw,30px);
      background:transparent;
    }
    .recover-shell{width:100%;max-width:none;margin:0}
    .recover-topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:16px}
    .recover-kicker{display:inline-flex;align-items:center;gap:8px;color:var(--primary);font-size:.72rem;font-weight:900;letter-spacing:.08em;text-transform:uppercase}
    .recover-kicker:before{content:'';width:8px;height:8px;border-radius:50%;background:var(--primary);box-shadow:0 0 0 5px color-mix(in srgb,var(--primary) 12%,transparent)}
    .recover-title{font-size:clamp(1.45rem,3vw,2.15rem);font-weight:900;color:#111827;margin:.35rem 0 .2rem;line-height:1.12}
    .recover-subtitle{max-width:760px;color:#4b5563;font-size:clamp(.86rem,1.6vw,.95rem);line-height:1.5;font-weight:600}
    .recover-back-btn{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:.45rem .9rem;border-radius:8px;border:1px solid color-mix(in srgb,var(--primary) 22%,rgba(255,255,255,.5));background:rgba(255,255,255,.42);color:#374151;text-decoration:none;font-size:.82rem;font-weight:800;backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);transition:background .16s,border-color .16s,color .16s,transform .16s}
    .recover-back-btn:hover{background:color-mix(in srgb,var(--primary-bg) 74%,rgba(255,255,255,.52));border-color:color-mix(in srgb,var(--primary) 42%,transparent);color:var(--primary);transform:translateY(-1px)}
    .recover-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:18px 0}
    .recover-step{border:1px solid color-mix(in srgb,var(--primary) 13%,rgba(255,255,255,.65));background:rgba(255,255,255,.44);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);border-radius:8px;padding:11px 12px;display:flex;align-items:center;gap:9px;min-width:0;box-shadow:0 10px 26px rgba(15,23,42,.05)}
    .recover-step.is-active{border-color:color-mix(in srgb,var(--primary) 46%,rgba(255,255,255,.7));background:color-mix(in srgb,var(--primary-bg) 72%,rgba(255,255,255,.48));box-shadow:0 12px 30px color-mix(in srgb,var(--primary) 14%,transparent)}
    .recover-step.is-done{background:rgba(240,253,244,.58);border-color:rgba(34,197,94,.32)}
    .recover-step-num{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.68);color:#374151;font-size:.78rem;font-weight:900;flex:0 0 auto;border:1px solid rgba(255,255,255,.6)}
    .recover-step.is-active .recover-step-num{background:var(--primary);color:#fff}
    .recover-step.is-done .recover-step-num{background:#16a34a;color:#fff}
    .recover-step-label{font-size:.82rem;font-weight:800;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .recover-card{background:rgba(255,255,255,.55);border:1px solid color-mix(in srgb,var(--primary) 16%,rgba(255,255,255,.68));border-radius:10px;box-shadow:0 22px 58px rgba(15,23,42,.08);backdrop-filter:blur(22px);-webkit-backdrop-filter:blur(22px);overflow:hidden}
    .recover-card-head{padding:18px 20px;border-bottom:1px solid color-mix(in srgb,var(--primary) 10%,rgba(255,255,255,.5));display:flex;align-items:flex-start;justify-content:space-between;gap:14px;background:linear-gradient(135deg,rgba(255,255,255,.42),color-mix(in srgb,var(--primary-bg) 55%,transparent))}
    .recover-card-body{padding:20px}
    .recover-card .form-label{color:#1f2937}
    .recover-card .form-control,.recover-card .form-select{background:rgba(255,255,255,.64);border-color:color-mix(in srgb,var(--primary) 14%,rgba(255,255,255,.7));backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}
    .recover-card .form-control:focus,.recover-card .form-select:focus{background:rgba(255,255,255,.78);border-color:var(--primary);box-shadow:0 0 0 .2rem color-mix(in srgb,var(--primary) 14%,transparent)}
    .recover-soft{background:color-mix(in srgb,var(--primary-bg) 60%,rgba(255,255,255,.48));border:1px solid color-mix(in srgb,var(--primary) 14%,rgba(255,255,255,.6));border-radius:8px;padding:14px;backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px)}
    .recover-order{position:relative;border:1px solid color-mix(in srgb,var(--primary) 13%,rgba(255,255,255,.65));border-radius:8px;padding:14px;background:rgba(255,255,255,.48);cursor:pointer;display:block;transition:border-color .15s,box-shadow .15s,background .15s,transform .15s;backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);min-height:100%}
    .recover-order:hover{border-color:color-mix(in srgb,var(--primary) 48%,transparent);box-shadow:0 10px 28px color-mix(in srgb,var(--primary) 12%,transparent);background:rgba(255,255,255,.64);transform:translateY(-1px)}
    .recover-order input{position:absolute;opacity:0;pointer-events:none}
    .recover-order:has(input:checked){border-color:var(--primary);background:color-mix(in srgb,var(--primary-bg) 76%,rgba(255,255,255,.5));box-shadow:0 12px 30px color-mix(in srgb,var(--primary) 16%,transparent)}
    .recover-order .badge{background:rgba(255,255,255,.68)!important;border:1px solid color-mix(in srgb,var(--primary) 14%,transparent)}
    .recover-code{font-family:monospace;font-size:clamp(1.8rem,8vw,3.25rem);font-weight:900;letter-spacing:.12em;color:#111827;word-break:break-word;line-height:1.1}
    .method-panel{display:none}
    .method-panel.is-visible{display:block}
    .recover-form-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:16px}
    .recover-field-6{grid-column:span 6}
    .recover-field-7{grid-column:span 7}
    .recover-field-5{grid-column:span 5}
    .recover-field-full{grid-column:1/-1}
    .recover-actions{display:flex;gap:10px;flex-wrap:wrap}
    .recover-order-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px}
    .recover-method-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
    @media(max-width:640px){
      .recover-page{min-height:calc(100vh - 70px);padding:12px}
      .recover-topbar{align-items:stretch}
      .recover-topbar .btn{width:100%;justify-content:center}
      .recover-steps{grid-template-columns:1fr}
      .recover-card-head{padding:16px}
      .recover-card-body{padding:16px}
      .recover-form-grid{grid-template-columns:1fr;gap:12px}
      .recover-field-6,.recover-field-7,.recover-field-5,.recover-field-full{grid-column:1/-1}
      .recover-method-grid,.recover-order-grid{grid-template-columns:1fr}
      .recover-actions .btn{width:100%}
    }
  </style>

  <div class="recover-steps" aria-label="Recovery progress">
    <div class="recover-step {{ $step === 'find' ? 'is-active' : ($step !== 'find' ? 'is-done' : '') }}">
      <div class="recover-step-num">1</div>
      <div class="recover-step-label">Find Order</div>
    </div>
    <div class="recover-step {{ $step === 'otp' ? 'is-active' : ($verified ? 'is-done' : '') }}">
      <div class="recover-step-num">2</div>
      <div class="recover-step-label">Verify Phone</div>
    </div>
    <div class="recover-step {{ in_array($step, ['deliver','done'], true) ? 'is-active' : '' }}">
      <div class="recover-step-num">3</div>
      <div class="recover-step-label">Recover Code</div>
    </div>
  </div>

  @if($step === 'done' && $recoveredCode)
    <div class="recover-card">
      <div class="recover-card-head">
        <div>
          <h5 class="fw-bold mb-1">Tracking Code Recovered</h5>
          <div class="text-muted small">Use this code on the Track Order page.</div>
        </div>
        <form method="POST" action="{{ route('track.recover.submit') }}">
          @csrf
          <input type="hidden" name="action" value="reset">
          <button class="btn btn-outline-secondary btn-sm" type="submit">Start Over</button>
        </form>
      </div>
      <div class="recover-card-body text-center">
        @if($selectedOrder)
          <div class="recover-soft mb-3 text-start">
            <div class="fw-bold">Order #{{ $selectedOrder->id }}</div>
            <div class="small text-muted">{{ $selectedOrder->product_name }} &middot; {{ $selectedOrder->status }} &middot; PHP {{ number_format((float) $selectedOrder->total_price, 2) }}</div>
          </div>
        @endif
        <div class="recover-code" id="recoveredCode">{{ $recoveredCode }}</div>
        <div class="d-flex justify-content-center flex-wrap gap-2 mt-4">
          <button type="button" class="btn btn-outline-primary" onclick="copyRecoveredCode()">
            <i class="bi bi-copy me-1"></i>Copy Code
          </button>
          <a href="{{ route('track.order', $recoveredCode) }}" class="btn btn-primary">
            <i class="bi bi-search me-1"></i>Track Order
          </a>
        </div>
      </div>
    </div>
  @elseif($step === 'otp')
    <div class="recover-card">
      <div class="recover-card-head">
        <div>
          <h5 class="fw-bold mb-1">Verify Your Phone</h5>
          <div class="text-muted small">Enter the 6-digit OTP sent to {{ $recovery['phone_masked'] ?? 'your phone' }}.</div>
        </div>
      </div>
      <div class="recover-card-body">
        <form method="POST" action="{{ route('track.recover.submit') }}" class="recover-form-grid">
          @csrf
          <input type="hidden" name="action" value="verify">
          <div class="recover-field-6">
            <label class="form-label fw-semibold">OTP Code <span class="text-danger">*</span></label>
            <input type="text" name="otp_code" class="form-control form-control-lg" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="123456" required autocomplete="one-time-code">
            <div class="form-text">Valid for 10 minutes. Do not share this code.</div>
          </div>
          <div class="recover-field-full recover-actions">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-shield-check me-1"></i>Verify OTP
            </button>
            <button type="submit" name="action" value="reset" class="btn btn-outline-secondary">Start Over</button>
          </div>
        </form>
      </div>
    </div>
  @elseif($step === 'deliver' && $verified)
    <div class="recover-card">
      <div class="recover-card-head">
        <div>
          <h5 class="fw-bold mb-1">Choose The Order</h5>
          <div class="text-muted small">Select the exact order, then choose how to receive the tracking code.</div>
        </div>
      </div>
      <div class="recover-card-body">
        <form method="POST" action="{{ route('track.recover.submit') }}" class="recover-form-grid" id="deliverForm">
          @csrf
          <input type="hidden" name="action" value="deliver">

          <div class="recover-field-full">
            <div class="recover-order-grid">
              @foreach($orders as $index => $order)
                <div>
                  <label class="recover-order">
                    <input type="radio" name="order_id" value="{{ $order->id }}" {{ $index === 0 ? 'checked' : '' }} required>
                    <div class="d-flex justify-content-between gap-2">
                      <div class="fw-bold">Order #{{ $order->id }}</div>
                      <span class="badge bg-light text-dark">{{ $order->order_type }}</span>
                    </div>
                    <div class="small text-muted mt-1">{{ $order->product_name }}</div>
                    <div class="small fw-semibold mt-2">PHP {{ number_format((float) $order->total_price, 2) }} &middot; {{ $order->status }}</div>
                    <div class="small text-muted">{{ \Carbon\Carbon::parse($order->created_at)->format('M d, Y h:i A') }}</div>
                  </label>
                </div>
              @endforeach
            </div>
          </div>

          <div class="recover-field-full">
            <label class="form-label fw-semibold">Receive Tracking Code By</label>
            <div class="recover-method-grid">
              <div>
                <label class="recover-order">
                  <input type="radio" name="delivery_method" value="screen" checked onchange="toggleEmailField()">
                  <div class="fw-bold"><i class="bi bi-display me-1"></i>Show on screen</div>
                  <div class="small text-muted">Fastest and no extra SMS cost.</div>
                </label>
              </div>
              <div>
                <label class="recover-order">
                  <input type="radio" name="delivery_method" value="sms" onchange="toggleEmailField()">
                  <div class="fw-bold"><i class="bi bi-phone me-1"></i>Send by SMS</div>
                  <div class="small text-muted">Code only, sent to the order phone number.</div>
                </label>
              </div>
              <div>
                <label class="recover-order">
                  <input type="radio" name="delivery_method" value="email" onchange="toggleEmailField()">
                  <div class="fw-bold"><i class="bi bi-envelope me-1"></i>Send by email</div>
                  <div class="small text-muted">Enter an email address below.</div>
                </label>
              </div>
            </div>
          </div>

          <div class="recover-field-7 method-panel" id="emailPanel">
            <label class="form-label fw-semibold">Email Address</label>
            <input type="email" name="email" class="form-control" value="{{ old('email') }}" maxlength="150" placeholder="name@example.com">
            <div class="form-text">The code will be sent to this email after phone verification.</div>
          </div>

          <div class="recover-field-full recover-actions">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-key-fill me-1"></i>Recover Tracking Code
            </button>
            <button type="submit" name="action" value="reset" class="btn btn-outline-secondary">Start Over</button>
          </div>
        </form>
      </div>
    </div>
  @else
    <div class="recover-card">
      <div class="recover-card-head">
        <div>
          <h5 class="fw-bold mb-1">Find Your Order</h5>
          <div class="text-muted small">Use the same details you entered when placing the order.</div>
        </div>
      </div>
      <div class="recover-card-body">
        <form method="POST" action="{{ route('track.recover.submit') }}" class="recover-form-grid">
          @csrf
          <input type="hidden" name="action" value="find">
          <div class="recover-field-6">
            <label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
            <input type="tel" name="phone" class="form-control" value="{{ old('phone') }}" placeholder="09XXXXXXXXX" maxlength="30" required inputmode="tel">
            <div class="form-text">Use the phone number verified during checkout.</div>
          </div>
          <div class="recover-field-6">
            <label class="form-label fw-semibold">Date You Placed The Order <span class="text-danger">*</span></label>
            <input type="date" name="order_date" class="form-control" value="{{ old('order_date') }}" max="{{ now()->toDateString() }}" required>
          </div>
          <div class="recover-field-7">
            <label class="form-label fw-semibold">Full Name Used In The Order <span class="text-danger">*</span></label>
            <input type="text" name="guest_name" class="form-control" value="{{ old('guest_name') }}" placeholder="e.g. Maria Santos" maxlength="120" required>
          </div>
          <div class="recover-field-5">
            <label class="form-label fw-semibold">Order Type</label>
            <select name="order_type" class="form-select">
              <option value="any" {{ old('order_type') === 'any' ? 'selected' : '' }}>Any order type</option>
              <option value="regular" {{ old('order_type') === 'regular' ? 'selected' : '' }}>Regular Cake Order</option>
              <option value="custom" {{ old('order_type') === 'custom' ? 'selected' : '' }}>Custom Cake Order</option>
            </select>
          </div>
          <div class="recover-field-full">
            <div class="recover-soft small text-muted">
              <i class="bi bi-lock-fill me-1"></i>
              We check your order details first. OTP is sent only when the details match an order, and tracking codes are shown only after verification.
            </div>
          </div>
          <div class="recover-field-full recover-actions">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-search me-1"></i>Continue
            </button>
          </div>
        </form>
      </div>
    </div>
  @endif
  </div>
</div>

<script>
function toggleEmailField() {
  var method = document.querySelector('input[name="delivery_method"]:checked')?.value || 'screen';
  var panel = document.getElementById('emailPanel');
  if (!panel) return;
  panel.classList.toggle('is-visible', method === 'email');
}

function copyRecoveredCode() {
  var code = document.getElementById('recoveredCode')?.textContent.trim() || '';
  if (!code) return;
  navigator.clipboard.writeText(code).then(function () {
    if (typeof cakeToast === 'function') cakeToast('Tracking code copied.', 'success');
  }).catch(function () {});
}

toggleEmailField();
</script>
@endsection
