<!DOCTYPE html>
<html lang="en">
<head>
  @php
    if (!isset($settings)) { $settings = \App\Helpers\CakeshopHelper::getSettings(); }
    $platformBrand = null;
    try { $platformBrand = \Illuminate\Support\Facades\DB::table('platform_settings')->first(); } catch (\Throwable $e) {}

    $rawPrimary = $platformBrand->platform_primary_color ?? '#7B3A0F';
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $rawPrimary)) $rawPrimary = '#7B3A0F';

    $hexAdjust = function(string $hex, float $factor): string {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        if ($factor >= 0) {
            $r = (int) min(255, $r + (255 - $r) * $factor);
            $g = (int) min(255, $g + (255 - $g) * $factor);
            $b = (int) min(255, $b + (255 - $b) * $factor);
        } else {
            $f = 1 + $factor;
            $r = (int) max(0, $r * $f);
            $g = (int) max(0, $g * $f);
            $b = (int) max(0, $b * $f);
        }
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    };

    $primaryDark = $hexAdjust($rawPrimary, -0.30);
    $primaryBg = $hexAdjust($rawPrimary, 0.90);
    $primaryLight = $hexAdjust($rawPrimary, 0.65);

    $pbgType = $platformBrand->platform_bg_type ?? 'color';
    $pbgColor = $platformBrand->platform_bg_color ?? '#FFF8F8';
    $pbgGradStart = $platformBrand->platform_bg_gradient_start ?? '#fff7fb';
    $pbgGradEnd = $platformBrand->platform_bg_gradient_end ?? '#ffe3f1';
    $pbgImage = $platformBrand->platform_bg_image ?? '';
    $pbgOpacity = (float) ($platformBrand->platform_bg_opacity ?? 1.0);
    $bodyBgCss = $pbgType === 'gradient'
        ? "background: linear-gradient(135deg, {$pbgGradStart} 0%, {$pbgGradEnd} 100%);"
        : "background: {$pbgColor};";
  @endphp
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="theme-color" content="{{ $rawPrimary }}">
  <title>Delivery #{{ $order->id }}</title>
  @if(!empty($settings['logo_path']))
    <link rel="icon" type="image/png" href="{{ $settings['logo_path'] }}">
  @endif
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root {
      --primary: {{ $rawPrimary }};
      --primary-dark: {{ $primaryDark }};
      --primary-bg: {{ $primaryBg }};
      --primary-light: {{ $primaryLight }};
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
    html { font-size: clamp(16px, 4vw, 22px); }
    body { width: 100%; min-height: 100vh; {{ $bodyBgCss }} font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 1rem; color: #111; position: relative; }
    @if($pbgType === 'image' && $pbgImage)
    body::before { content: ""; position: fixed; inset: 0; background: url('{{ $pbgImage }}') center/cover no-repeat; opacity: {{ $pbgOpacity }}; pointer-events: none; z-index: -1; }
    @endif

    /* ── Header ─────────────── */
    .hdr {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: #fff;
      padding: clamp(12px, 3vw, 20px) clamp(14px, 4vw, 22px);
      display: flex;
      align-items: center;
      gap: clamp(8px, 2vw, 14px);
      box-shadow: 0 4px 18px rgba(0,0,0,.10);
    }
    .back-btn { width: clamp(36px, 9vw, 46px); height: clamp(36px, 9vw, 46px); border: 1px solid rgba(255,255,255,.34); border-radius: 12px; background: rgba(255,255,255,.16); color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; cursor: pointer; font-size: clamp(18px, 4.5vw, 22px); }
    .back-btn:active { transform: scale(.98); background: rgba(255,255,255,.24); }
    .hdr-logo { width: clamp(28px, 7vw, 40px); height: clamp(28px, 7vw, 40px); border-radius: 6px; object-fit: cover; flex-shrink: 0; }
    .hdr-text { flex: 1; min-width: 0; }
    .hdr-shop { font-size: clamp(11px, 3vw, 15px); opacity: .85; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .hdr-order { font-size: clamp(16px, 4.5vw, 22px); font-weight: 700; }

    /* ── Section ─────────────── */
    .section { background: rgba(255,255,255,.96); margin: clamp(8px, 2vw, 12px) 0; padding: 0; border-top: 1px solid rgba(0,0,0,.04); border-bottom: 1px solid rgba(0,0,0,.04); }
    .section-title { font-size: clamp(11px, 2.8vw, 14px); font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #9ca3af; padding: clamp(12px, 3vw, 16px) clamp(14px, 4vw, 20px) clamp(4px, 1vw, 8px); }
    .row { display: flex; align-items: flex-start; gap: clamp(10px, 2.5vw, 16px); padding: clamp(12px, 3vw, 16px) clamp(14px, 4vw, 20px); border-top: 1px solid #f3f4f6; }
    .row-icon { width: clamp(38px, 9vw, 50px); height: clamp(38px, 9vw, 50px); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: clamp(18px, 4.5vw, 24px); }
    .row-body { flex: 1; min-width: 0; overflow: hidden; }
    .row-label { font-size: clamp(11px, 2.8vw, 14px); color: #9ca3af; margin-bottom: 3px; }
    .row-value { font-size: clamp(14px, 3.8vw, 19px); font-weight: 600; word-break: break-word; overflow-wrap: anywhere; }
    .row-sub { font-size: clamp(12px, 3vw, 15px); color: #6b7280; margin-top: 4px; }
    .row-link { font-size: clamp(13px, 3.2vw, 16px); font-weight: 600; color: var(--primary); text-decoration: none; display: inline-block; margin-top: 5px; }

    /* ── Payment Banner ──────── */
    .pay-banner { margin: clamp(8px, 2vw, 12px) 0; padding: clamp(14px, 3.5vw, 18px) clamp(14px, 4vw, 20px); display: flex; align-items: center; gap: clamp(10px, 3vw, 16px); }
    .pay-banner .pay-icon { font-size: clamp(24px, 6vw, 34px); flex-shrink: 0; }
    .pay-banner .pay-body { flex: 1; min-width: 0; }
    .pay-banner .pay-label { font-size: clamp(10px, 2.5vw, 13px); font-weight: 700; text-transform: uppercase; letter-spacing: .05em; opacity: .75; margin-bottom: 2px; }
    .pay-banner .pay-amount { font-size: clamp(20px, 5.5vw, 28px); font-weight: 800; line-height: 1.2; }
    .pay-banner .pay-note { font-size: clamp(11px, 2.8vw, 14px); opacity: .75; margin-top: 3px; }
    .pay-cod   { background: #fff8e1; border-left: 4px solid #f59e0b; color: #92400e; }
    .pay-ok    { background: #f0fdf4; border-left: 4px solid #22c55e; color: #166534; }
    .pay-gcash { background: #eff6ff; border-left: 4px solid #3b82f6; color: #1e40af; }

    /* ── Photo upload ────────── */
    .photo-section { padding: clamp(10px, 2.5vw, 14px) clamp(14px, 4vw, 20px); border-top: 1px solid #f3f4f6; }
    .photo-label { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: clamp(14px, 3.5vw, 18px); background: var(--primary-bg); border: 2px dashed var(--primary-light); border-radius: 12px; font-size: clamp(14px, 3.5vw, 18px); font-weight: 600; color: var(--primary-dark); cursor: pointer; }
    .photo-label:active { background: var(--primary-light); }
    .photo-label i { font-size: clamp(18px, 5vw, 24px); }
    .photo-preview { width: 100%; border-radius: 10px; margin-top: 10px; display: none; object-fit: cover; max-height: clamp(180px, 45vw, 260px); }
    .note-input { width: 100%; padding: clamp(12px, 3vw, 16px); border: 1.5px solid #e5e7eb; border-radius: 10px; font-size: clamp(14px, 3.5vw, 18px); font-family: inherit; resize: none; margin-top: 10px; }
    .note-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 12%, transparent); }
    .qr-box { background:#f8fafc; border:1px solid #e5e7eb; border-radius:12px; padding:14px; margin-top:12px; text-align:center; }
    .qr-img { display:block; width:min(260px, 86vw); aspect-ratio:1/1; object-fit:contain; margin:10px auto; border:8px solid #fff; border-radius:10px; box-shadow:0 8px 24px rgba(15,23,42,.10); }
    .qr-actions { display:grid; grid-template-columns:1fr; gap:8px; margin-top:10px; }
    .btn-remit-alt { width:100%; padding:clamp(12px,3.2vw,16px); border:1.5px solid #bfdbfe; border-radius:12px; background:#fff; color:#1d4ed8; font-size:clamp(13px,3.4vw,16px); font-weight:700; cursor:pointer; text-decoration:none; display:flex; align-items:center; justify-content:center; gap:8px; }
    .btn-remit-alt:active { background:#eff6ff; }
    .btn-remit-alt[disabled] { opacity:.6; cursor:not-allowed; }
    .qr-countdown { font-size:clamp(13px,3.2vw,16px); color:#1d4ed8; font-weight:800; margin-top:8px; }
    .qr-countdown.expired { color:#b91c1c; }
    .remit-choice-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px; }
    .remit-choice { border:1.5px solid #e5e7eb; border-radius:12px; background:#fff; color:#374151; padding:12px 8px; font:inherit; font-size:clamp(13px,3.2vw,16px); font-weight:750; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:7px; min-height:48px; }
    .remit-choice.active { border-color:#2563eb; background:#eff6ff; color:#1d4ed8; }
    .remit-method-panel { display:none; }
    .remit-method-panel.active { display:block; }
    @media(max-width:360px){ .remit-choice-grid { grid-template-columns:1fr; } }

    /* ── Action buttons ──────── */
    .actions { padding: clamp(12px, 3vw, 16px) clamp(14px, 4vw, 20px); display: flex; flex-direction: column; gap: clamp(8px, 2.5vw, 12px); }
    .btn-deliver {
      width: 100%; padding: clamp(16px, 4.5vw, 22px) clamp(14px, 4vw, 20px);
      border: none; border-radius: 14px;
      background: #16a34a; color: #fff;
      font-size: clamp(15px, 4.2vw, 20px); font-weight: 700; cursor: pointer;
      display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-deliver:active { background: #15803d; transform: scale(.99); }
    .btn-issue {
      width: 100%; padding: clamp(14px, 4vw, 20px) clamp(14px, 4vw, 20px);
      border: 2px solid #ef4444; border-radius: 14px;
      background: #fff; color: #ef4444;
      font-size: clamp(14px, 3.8vw, 19px); font-weight: 600; cursor: pointer;
      display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-issue:active { background: #fff5f5; }

    /* ── Issue form ──────────── */
    .issue-section { background: #fff5f5; border-top: 2px solid #fecaca; padding: clamp(14px, 3.5vw, 18px) clamp(14px, 4vw, 20px); display: none; }
    .issue-title { font-size: clamp(14px, 3.8vw, 18px); font-weight: 700; color: #ef4444; margin-bottom: clamp(10px, 3vw, 14px); }
    .issue-opts { display: flex; gap: clamp(6px, 2vw, 10px); margin-bottom: clamp(10px, 3vw, 14px); }
    .issue-opt { flex: 1; background: #fff; border: 2px solid #e5e7eb; border-radius: 12px; padding: clamp(10px, 3vw, 14px) 4px; text-align: center; cursor: pointer; font-size: clamp(11px, 2.8vw, 14px); font-weight: 600; color: #374151; }
    .issue-opt .oi { font-size: clamp(20px, 5.5vw, 28px); display: block; margin-bottom: 4px; }
    .issue-opt.sel { border-color: #ef4444; background: #fff; color: #ef4444; }
    .btn-submit { width: 100%; padding: clamp(15px, 4.2vw, 20px); border: none; border-radius: 12px; background: #ef4444; color: #fff; font-size: clamp(15px, 4vw, 19px); font-weight: 700; cursor: pointer; margin-top: 10px; }
    .btn-submit:active { background: #dc2626; }
    .btn-cancel { width: 100%; padding: clamp(10px, 3vw, 14px); background: none; border: none; font-size: clamp(13px, 3.2vw, 16px); color: #9ca3af; cursor: pointer; margin-top: 4px; }

    /* ── Result screens ──────── */
    .result { text-align: center; padding: clamp(48px, 12vw, 72px) clamp(20px, 5vw, 32px); }
    .result-icon { font-size: clamp(48px, 14vw, 72px); display: block; margin-bottom: 16px; }
    .result-title { font-size: clamp(18px, 5vw, 26px); font-weight: 700; margin-bottom: 8px; }
    .result-msg { font-size: clamp(13px, 3.2vw, 17px); color: #6b7280; line-height: 1.6; }

    /* ── Spinner ─────────────── */
    .spin { width: clamp(18px, 4.5vw, 24px); height: clamp(18px, 4.5vw, 24px); border: 2.5px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: sp .7s linear infinite; display: inline-block; }
    @keyframes sp { to { transform: rotate(360deg); } }

    /* ── Confirm sheet ───────── */
    .rc-overlay { position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9000;display:flex;align-items:flex-end;justify-content:center;opacity:0;pointer-events:none;transition:opacity .22s; }
    .rc-overlay.open { opacity:1;pointer-events:all; }
    .rc-sheet { background:#fff;width:100%;max-width:480px;border-radius:22px 22px 0 0;padding:clamp(20px,5vw,28px) clamp(20px,5vw,28px) clamp(24px,6vw,32px);transform:translateY(100%);transition:transform .28s cubic-bezier(.32,.72,0,1); }
    .rc-overlay.open .rc-sheet { transform:translateY(0); }
    .rc-icon { width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.6rem;margin:0 auto 14px; }
    .rc-title { font-size:clamp(16px,4.5vw,20px);font-weight:800;color:#111;text-align:center;margin-bottom:6px; }
    .rc-msg { font-size:clamp(13px,3.5vw,16px);color:#6b7280;text-align:center;line-height:1.5;margin-bottom:22px; }
    .rc-btns { display:flex;flex-direction:column;gap:10px; }
    .rc-ok { width:100%;padding:clamp(14px,4vw,18px);border:none;border-radius:13px;font-size:clamp(15px,4vw,18px);font-weight:700;color:#fff;cursor:pointer; }
    .rc-cancel { width:100%;padding:clamp(12px,3.5vw,15px);border:none;border-radius:13px;font-size:clamp(13px,3.5vw,16px);font-weight:600;color:#6b7280;background:#f3f4f6;cursor:pointer; }
  </style>
</head>
<body>

{{-- Header --}}
<div class="hdr">
  <button type="button" class="back-btn" onclick="goBack()" aria-label="Back">
    <i class="bi bi-arrow-left"></i>
  </button>
  @if(!empty($settings['logo_path']))
    <img src="{{ $settings['logo_path'] }}" class="hdr-logo" onerror="this.style.display='none'">
  @endif
  <div class="hdr-text">
    <div class="hdr-shop">{{ $settings['site_title'] ?? 'Cake Shop' }} · Delivery</div>
    <div class="hdr-order">Order #{{ $order->id }}</div>
  </div>
</div>

@if(session('msg'))
<div class="pay-banner pay-ok">
  <div class="pay-icon"><i class="bi bi-check-circle-fill"></i></div>
  <div class="pay-body">
    <div class="pay-label">Success</div>
    <div class="pay-note">{{ session('msg') }}</div>
  </div>
</div>
@endif
@if(session('err') || $errors->any())
<div class="pay-banner pay-cod">
  <div class="pay-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
  <div class="pay-body">
    <div class="pay-label">Please Check</div>
    <div class="pay-note">{{ session('err') ?: $errors->first() }}</div>
  </div>
</div>
@endif

@if(isset($done) && $done && empty($remittanceOnly))
{{-- Already done --}}
<div class="result">
  <span class="result-icon">
    @if(in_array($order->status,['Delivered','Picked Up'])) ✅
    @elseif($order->status==='Issue Reported') ⚠️
    @elseif($order->status==='Attempted Delivery') 🏠
    @else 📦 @endif
  </span>
  <div class="result-title">Already Updated</div>
  <div class="result-msg">Order #{{ $order->id }}<br>Status: <strong>{{ $order->status }}</strong><br><br>This delivery has already been updated.</div>
</div>

@else

{{-- Customer --}}
<div class="section">
  <div class="section-title">Customer</div>

  <div class="row">
    <div class="row-icon" style="background:var(--primary-bg);color:var(--primary-dark)">👤</div>
    <div class="row-body">
      <div class="row-label">Name</div>
      <div class="row-value">{{ $order->guest_name ?? 'Customer' }}</div>
    </div>
  </div>

  @if($order->guest_phone)
  <div class="row">
    <div class="row-icon" style="background:#f0fdf4">📞</div>
    <div class="row-body">
      <div class="row-label">Phone — tap to call</div>
      <a href="tel:{{ $order->guest_phone }}" class="row-value" style="color:#16a34a;text-decoration:none">{{ $order->guest_phone }}</a>
    </div>
  </div>
  @endif

  @php $deliveryAddr = $order->delivery_address ?? $order->address ?? null; @endphp
  @if($deliveryAddr)
  <div class="row">
    <div class="row-icon" style="background:#eff6ff">📍</div>
    <div class="row-body">
      <div class="row-label">Delivery Address</div>
      <div class="row-value">{{ $deliveryAddr }}</div>
      @if(($order->latitude ?? null) && ($order->longitude ?? null))
      <a href="https://www.google.com/maps/dir/?api=1&destination={{ $order->latitude }},{{ $order->longitude }}&travelmode=driving"
         target="_blank" class="row-link">🗺️ Get Directions →</a>
      @else
      <a href="https://www.google.com/maps/dir/?api=1&destination={{ urlencode($deliveryAddr) }}&travelmode=driving"
         target="_blank" class="row-link">🗺️ Get Directions →</a>
      @endif
    </div>
  </div>
  @endif
</div>

{{-- Order --}}
<div class="section">
  <div class="section-title">Order Details</div>

  <div class="row">
    <div class="row-icon" style="background:var(--primary-bg);color:var(--primary-dark)">🎂</div>
    <div class="row-body">
      <div class="row-label">Product</div>
      <div class="row-value">{{ $order->product_name }}</div>
      @if($order->selected_size)<div class="row-sub">Size: {{ $order->selected_size }}</div>@endif
      @if($order->custom_note)<div class="row-sub">Note: {{ $order->custom_note }}</div>@endif
    </div>
  </div>

  @if(isset($addons) && $addons->count())
  <div class="row">
    <div class="row-icon" style="background:#f5f3ff">🎁</div>
    <div class="row-body">
      <div class="row-label">Add-ons</div>
      <div class="row-value" style="font-size:14px">{{ $addons->pluck('addon_name')->implode(' · ') }}</div>
    </div>
  </div>
  @endif
</div>

{{-- Payment --}}
@php
  $totalAmount = (float) ($order->total_price ?? 0);
  $depositAmount = (float) ($order->deposit_amount ?? 0);
  $depositPaid = in_array($order->payment_status ?? '', ['Partial Payment', 'Paid'], true) || ($order->deposit_status ?? '') === 'paid';
  $remainingAmount = max(0, $totalAmount - ($depositPaid ? $depositAmount : 0));
  $cashMethod = in_array($order->payment_method, ['COD', 'COP'], true);
@endphp
@if($order->payment_status === 'Paid')
<div class="pay-banner pay-ok">
  <div class="pay-icon">✅</div>
  <div class="pay-body">
    <div class="pay-label">Payment Settled</div>
    <div class="pay-amount">₱0.00</div>
    <div class="pay-note">No collection needed</div>
  </div>
</div>
@elseif($depositPaid && $depositAmount > 0)
<div class="pay-banner {{ $cashMethod ? 'pay-cod' : 'pay-gcash' }}">
  <div class="pay-icon">{{ $cashMethod ? 'Cash' : 'GCash' }}</div>
  <div class="pay-body">
    <div class="pay-label">{{ $cashMethod ? 'Collect Remaining Balance' : 'GCash Remaining Balance Pending' }}</div>
    <div class="pay-amount">&#8369;{{ number_format($remainingAmount,2) }}</div>
    <div class="pay-note">{{ $cashMethod ? 'Deposit of PHP '.number_format($depositAmount,2).' already paid' : 'Customer must complete GCash payment before delivery can be marked delivered.' }}</div>
  </div>
</div>
@elseif($cashMethod)
<div class="pay-banner pay-cod">
  <div class="pay-icon">💵</div>
  <div class="pay-body">
    <div class="pay-label">Collect Cash from Customer</div>
    <div class="pay-amount">₱{{ number_format($totalAmount,2) }}</div>
  </div>
</div>
@else
<div class="pay-banner pay-gcash">
  <div class="pay-icon">📱</div>
  <div class="pay-body">
    <div class="pay-label">GCash — Not Yet Paid</div>
    <div class="pay-amount">₱{{ number_format($totalAmount,2) }}</div>
    <div class="pay-note">Customer needs to pay via GCash</div>
  </div>
</div>
@endif

{{-- Photo + Note --}}
@if(empty($remittanceOnly))
<div class="section">
  <div class="section-title">Proof of Delivery</div>
  <div class="photo-section">
    <label for="deliveryPhoto" class="photo-label">
      <i class="bi bi-camera" style="font-size:20px"></i>
      <span id="photoLabel">Take or upload a photo</span>
    </label>
    <input type="file" id="deliveryPhoto" accept="image/*" capture="environment" style="display:none"
           onchange="previewPhoto(this,'photoPreview','photoLabel')">
    <img id="photoPreview" class="photo-preview" src="">
    <textarea class="note-input" id="deliveryNote" rows="2"
              placeholder="Optional note (e.g. left at gate, customer received it)"></textarea>
  </div>
</div>
@endif

@if(!empty($remittance))
<div class="section" id="remittancePanel">
  <div class="section-title">Cash Remittance</div>
  <div class="row">
    <div class="row-icon" style="background:#eff6ff;color:#1d4ed8"><i class="bi bi-cash-stack"></i></div>
    <div class="row-body">
      <div class="row-label">Amount to remit to seller</div>
      <div class="row-value">&#8369;{{ number_format((float)$remittance->amount, 2) }}</div>
      <div class="row-sub">Status: {{ ucfirst(str_replace('_', ' ', $remittance->status ?? 'pending')) }}</div>
      @if(($remittance->status ?? '') === 'rejected' && $remittance->seller_note)
        <div class="row-sub" style="color:#b91c1c">Seller note: {{ $remittance->seller_note }}</div>
      @elseif(($remittance->status ?? '') === 'submitted')
        <div class="row-sub" style="color:#1d4ed8">Waiting for seller confirmation.</div>
      @endif
    </div>
  </div>
  @if(($remittance->status ?? '') !== 'confirmed')
  <div class="photo-section">
    @php
      $qrActive = ($remittance->status ?? '') === 'awaiting_payment'
        && !empty($remittance->paymongo_qr_image)
        && (empty($remittance->paymongo_expires_at) || now()->lt($remittance->paymongo_expires_at));
      $qrExpired = ($remittance->status ?? '') === 'qr_expired'
        || (!empty($remittance->paymongo_expires_at) && now()->gte($remittance->paymongo_expires_at) && ($remittance->status ?? '') !== 'confirmed');
    @endphp

    <div class="remit-choice-grid" role="group" aria-label="Choose remittance method">
      <button class="remit-choice active" type="button" data-remit-tab="gcash" onclick="showRemitMethod('gcash')">
        <i class="bi bi-qr-code"></i> GCash
      </button>
      <button class="remit-choice" type="button" data-remit-tab="cash" onclick="showRemitMethod('cash')">
        <i class="bi bi-shop"></i> Cash to Shop
      </button>
    </div>

    <div class="remit-method-panel active" id="remitPanelGcash">
      <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px;margin-bottom:12px">
        <div style="font-size:12px;color:#1d4ed8;font-weight:800;text-transform:uppercase;letter-spacing:.04em">GCash via PayMongo</div>
        <div style="font-size:14px;color:#1e3a8a;font-weight:650;line-height:1.45">Scan the QR with GCash or open the payment link on this device. The system verifies it after PayMongo confirms payment.</div>
      </div>

      @if($qrActive)
        <div class="qr-box">
          <div class="row-label">Scan with GCash</div>
          <div class="row-value">&#8369;{{ number_format((float)$remittance->amount, 2) }}</div>
        <img class="qr-img" src="{{ $remittance->paymongo_qr_image }}" alt="PayMongo GCash QR for remittance">
        @if(!empty($remittance->paymongo_expires_at))
          <div class="row-sub">Expires {{ \Carbon\Carbon::parse($remittance->paymongo_expires_at)->diffForHumans() }}</div>
          <div class="qr-countdown" id="remitQrCountdown" data-expires-at="{{ \Carbon\Carbon::parse($remittance->paymongo_expires_at)->toIso8601String() }}">Expires in --:--</div>
        @endif
        <div class="qr-actions">
          @if(!empty($remittance->paymongo_action_url))
            <a class="btn-remit-alt" id="openGcashPaymentLink" href="{{ $remittance->paymongo_action_url }}" target="_blank" rel="noopener">
              <i class="bi bi-phone"></i> Open GCash Payment
            </a>
          @endif
            <form method="POST" action="{{ route('rider.remittance.check', [$order->id, $order->rider_token]) }}">
              @csrf
              <button class="btn-remit-alt" type="submit"><i class="bi bi-arrow-repeat"></i> Check Payment Status</button>
            </form>
          </div>
        </div>
      @endif

      <form method="POST" action="{{ route('rider.remittance.qr', [$order->id, $order->rider_token]) }}" id="remitQrGenerateForm">
        @csrf
        <button class="btn-deliver" type="submit" style="margin-top:12px" id="remitQrGenerateButton">
          <i class="bi bi-qr-code"></i> {{ $qrExpired ? 'Generate New QR Code' : 'Generate QR Code' }}
        </button>
      </form>
    </div>

    <div class="remit-method-panel" id="remitPanelCash">
      <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:12px;margin-bottom:12px">
        <div style="font-size:12px;color:#c2410c;font-weight:800;text-transform:uppercase;letter-spacing:.04em">Cash Handover</div>
        <div style="font-size:14px;color:#7c2d12;font-weight:650;line-height:1.45">Use this only after you physically gave the COD cash to the shop. This will not be added to online payout.</div>
      </div>
      <form method="POST" action="{{ route('rider.remittance', [$order->id, $order->rider_token]) }}">
        @csrf
        <input type="hidden" name="amount" value="{{ number_format((float)$remittance->amount, 2, '.', '') }}">
        <input type="hidden" name="remittance_method" value="cash_handover">
        <textarea class="note-input" name="rider_note" rows="2" maxlength="500" placeholder="Optional note to seller">{{ old('rider_note', $remittance->rider_note ?? '') }}</textarea>
        <button class="btn-deliver" type="submit" style="margin-top:12px;background:#c2410c">
          <i class="bi bi-shop"></i> Mark Cash Handed to Shop
        </button>
      </form>
    </div>
  </div>
  @endif
</div>
@endif

{{-- Buttons --}}
@if(empty($remittanceOnly))
<div class="actions" id="actionSection">
  <button class="btn-deliver" onclick="confirmDeliver()">
    <i class="bi bi-check-circle-fill" style="font-size:20px"></i> Mark as Delivered ✓
  </button>
  <button class="btn-issue" onclick="showIssueForm()">
    <i class="bi bi-exclamation-triangle" style="font-size:17px"></i> Report an Issue
  </button>
</div>
@endif

{{-- Issue Form --}}
@if(empty($remittanceOnly))
<div class="issue-section" id="issueSection">
  <div class="issue-title"><i class="bi bi-exclamation-triangle me-1"></i>What happened?</div>
  <div class="issue-opts">
    <div class="issue-opt" onclick="selectIssue('damaged',this)">
      <span class="oi">🎂💔</span>Damaged
    </div>
    <div class="issue-opt" onclick="selectIssue('not_home',this)">
      <span class="oi">🏠❌</span>Not Home
    </div>
    <div class="issue-opt" onclick="selectIssue('other',this)">
      <span class="oi">⚠️</span>Other
    </div>
  </div>
  <textarea class="note-input" id="issueNote" rows="3" placeholder="Describe what happened..."></textarea>
  <div class="photo-section" style="padding:10px 0 0">
    <label for="issuePhoto" class="photo-label">
      <i class="bi bi-camera" style="font-size:20px"></i>
      <span id="issuePhotoLabel">Take issue photo (optional)</span>
    </label>
    <input type="file" id="issuePhoto" accept="image/*" capture="environment" style="display:none"
           onchange="previewPhoto(this,'issuePhotoPreview','issuePhotoLabel')">
    <img id="issuePhotoPreview" class="photo-preview" src="">
  </div>
  <button class="btn-submit" onclick="submitIssue()"><i class="bi bi-send me-1"></i>Submit Report</button>
  <button class="btn-cancel" onclick="hideIssueForm()">Cancel</button>
</div>
@endif

{{-- Success --}}
<div class="result" id="successScreen" style="display:none">
  <span class="result-icon">✅</span>
  <div class="result-title" style="color:#15803d">Delivered!</div>
  <div class="result-msg">Order #{{ $order->id }} has been marked as delivered.<br>The customer has been notified. 🎂</div>
</div>

<div class="result" id="issueSuccessScreen" style="display:none">
  <span class="result-icon">📋</span>
  <div class="result-title" style="color:#ef4444">Issue Reported</div>
  <div class="result-msg" id="issueSuccessMsg">Admin has been notified and will contact the customer.</div>
</div>

@endif

{{-- Confirm bottom-sheet --}}
<div class="rc-overlay" id="rcOverlay" onclick="rcClose(event)">
  <div class="rc-sheet">
    <div class="rc-icon" id="rcIcon"></div>
    <div class="rc-title" id="rcTitle"></div>
    <div class="rc-msg"   id="rcMsg"></div>
    <div class="rc-btns">
      <button class="rc-ok"     id="rcOk"></button>
      <button class="rc-cancel" id="rcCancel" onclick="rcDismiss()">Cancel</button>
    </div>
  </div>
</div>

<script>
const ORDER_ID = '{{ $order->id }}', TOKEN = '{{ $order->rider_token }}';
let selectedIssue = null, _rcCb = null;

function goBack() {
  if (window.history.length > 1) {
    window.history.back();
    return;
  }
  window.location.href = '{{ route('rider.login') }}';
}

function formatPeso(amount) {
  return '₱' + Number(amount || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

let latestPaymentStatus = @json($order->payment_status ?? 'Unpaid');
let latestPaymentMethod = @json($order->payment_method ?? '');
let latestRemainingAmount = {{ $order->payment_status === 'Paid' ? 0 : $remainingAmount }};
function updatePaymentBanner(data) {
  const banner = document.querySelector('.pay-banner');
  if (!banner || !data || !data.ok) return;

  banner.classList.remove('pay-ok', 'pay-cod', 'pay-gcash');
  banner.classList.add(data.banner_class || 'pay-gcash');

  const icon = banner.querySelector('.pay-icon');
  const label = banner.querySelector('.pay-label');
  const amount = banner.querySelector('.pay-amount');
  let note = banner.querySelector('.pay-note');
  if (!note) {
    note = document.createElement('div');
    note.className = 'pay-note';
    banner.querySelector('.pay-body')?.appendChild(note);
  }

  if (icon) icon.textContent = data.icon || '';
  if (label) label.textContent = data.label || '';
  if (amount) amount.textContent = formatPeso(data.amount);
  if (note) note.textContent = data.note || '';

  latestPaymentStatus = data.payment_status || latestPaymentStatus;
  latestRemainingAmount = Number(data.remaining_amount || 0);
}

async function refreshPaymentStatus() {
  if (paymentPollInFlight || paymentPollStopped || document.hidden) return;
  paymentPollInFlight = true;
  try {
    const res = await fetch('/rider/' + ORDER_ID + '/' + TOKEN + '/payment-status', {
      headers: { 'Accept': 'application/json' },
      cache: 'no-store'
    });
    if (!res.ok) return;
    const data = await res.json();
    updatePaymentBanner(data);
    if (data.payment_status === 'Paid') stopPaymentPolling();
  } catch (e) {
  } finally {
    paymentPollInFlight = false;
  }
}

let paymentPollTimer = null;
let paymentPollInFlight = false;
let paymentPollStopped = false;

function startPaymentPolling() {
  if (paymentPollTimer || paymentPollStopped) return;
  refreshPaymentStatus();
  paymentPollTimer = setInterval(refreshPaymentStatus, 10000);
}

function stopPaymentPolling() {
  paymentPollStopped = true;
  if (paymentPollTimer) clearInterval(paymentPollTimer);
  paymentPollTimer = null;
}

document.addEventListener('visibilitychange', function() {
  if (!document.hidden && !paymentPollStopped) refreshPaymentStatus();
});

refreshPaymentStatus();
startPaymentPolling();

function rcOpen({ icon, iconBg, title, message, okLabel, okColor, onConfirm }) {
  document.getElementById('rcIcon').style.background = iconBg || '#dcfce7';
  document.getElementById('rcIcon').textContent = icon || '✅';
  document.getElementById('rcTitle').textContent = title || 'Are you sure?';
  document.getElementById('rcMsg').textContent = message || '';
  const ok = document.getElementById('rcOk');
  ok.textContent = okLabel || 'Confirm';
  ok.style.background = okColor || '#16a34a';
  _rcCb = onConfirm || null;
  ok.onclick = function() { rcDismiss(); if (_rcCb) _rcCb(); };
  document.getElementById('rcOverlay').classList.add('open');
}
function rcDismiss() { document.getElementById('rcOverlay').classList.remove('open'); }
function rcClose(e) { if (e.target === document.getElementById('rcOverlay')) rcDismiss(); }

function cakeConfirm(opts) { rcOpen(opts); }

function previewPhoto(input, imgId, lblId) {
  if (input.files && input.files[0]) {
    document.getElementById(imgId).src = URL.createObjectURL(input.files[0]);
    document.getElementById(imgId).style.display = 'block';
    document.getElementById(lblId).textContent = '✓ Photo selected';
  }
}
function toggleRemittanceReceipt(method) {
  const wrap = document.getElementById('remittanceReceiptLabelWrap');
  const input = document.getElementById('remittanceReceipt');
  if (!wrap || !input) return;
  const needsReceipt = method === 'gcash';
  wrap.style.display = needsReceipt ? 'flex' : 'none';
  input.required = needsReceipt;
}
toggleRemittanceReceipt(document.getElementById('remittanceMethod')?.value || '');
function showRemitMethod(method) {
  document.querySelectorAll('[data-remit-tab]').forEach(button => {
    button.classList.toggle('active', button.dataset.remitTab === method);
  });
  document.getElementById('remitPanelGcash')?.classList.toggle('active', method === 'gcash');
  document.getElementById('remitPanelCash')?.classList.toggle('active', method === 'cash');
}

function startRemittanceQrCountdown() {
  const countdown = document.getElementById('remitQrCountdown');
  const form = document.getElementById('remitQrGenerateForm');
  const generateButton = document.getElementById('remitQrGenerateButton');
  if (!countdown || !form || !countdown.dataset.expiresAt) return;

  const expiresAt = new Date(countdown.dataset.expiresAt).getTime();
  if (!Number.isFinite(expiresAt)) return;

  const autoKey = 'remit_qr_regenerated_' + ORDER_ID + '_' + expiresAt;
  let timer = null;
  const tick = () => {
    const remaining = Math.max(0, expiresAt - Date.now());
    const totalSeconds = Math.ceil(remaining / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    countdown.textContent = remaining > 0
      ? 'Expires in ' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0')
      : 'QR expired. Generating a new QR...';

    if (remaining > 0) return;

    countdown.classList.add('expired');
    document.getElementById('openGcashPaymentLink')?.setAttribute('aria-disabled', 'true');
    if (generateButton) {
      generateButton.disabled = true;
      generateButton.innerHTML = '<span class="spin"></span> Generating new QR...';
    }

    if (!sessionStorage.getItem(autoKey)) {
      sessionStorage.setItem(autoKey, '1');
      form.submit();
    } else if (generateButton) {
      generateButton.disabled = false;
      generateButton.innerHTML = '<i class="bi bi-qr-code"></i> Generate New QR Code';
    }
    if (timer) clearInterval(timer);
  };

  tick();
  timer = setInterval(tick, 1000);
}
startRemittanceQrCountdown();
function selectIssue(type, el) {
  selectedIssue = type;
  document.querySelectorAll('.issue-opt').forEach(o => o.classList.remove('sel'));
  el.classList.add('sel');
}
function showIssueForm() {
  document.getElementById('issueSection').style.display = 'block';
  document.getElementById('issueSection').scrollIntoView({ behavior:'smooth' });
}
function hideIssueForm() {
  document.getElementById('issueSection').style.display = 'none';
  selectedIssue = null;
  document.querySelectorAll('.issue-opt').forEach(o => o.classList.remove('sel'));
}
function hide() {
  document.getElementById('actionSection').style.display = 'none';
  document.getElementById('issueSection').style.display = 'none';
  document.querySelectorAll('.section,.pay-banner,.pay-cod,.pay-ok,.pay-gcash').forEach(el => el.style.display = 'none');
}
function confirmDeliver() {
  if (latestPaymentMethod === 'GCash'
      && latestPaymentStatus !== 'Paid'
      && latestRemainingAmount > 0.009) {
    rcOpen({
      icon: '!',
      iconBg: '#fef3c7',
      title: 'GCash Payment Pending',
      message: 'This order still has a remaining GCash balance of ' + formatPeso(latestRemainingAmount) + '. Ask the customer to complete payment first, then try again.',
      okLabel: 'Got it',
      okColor: '#d97706'
    });
    return;
  }
  rcOpen({
    icon: '✅',
    iconBg: '#dcfce7',
    title: 'Mark as Delivered?',
    message: 'Confirm Order #' + ORDER_ID + ' as delivered to the customer.',
    okLabel: 'Mark Delivered',
    okColor: '#16a34a',
    onConfirm: function() {
      doFetch('/rider/' + ORDER_ID + '/' + TOKEN + '/delivered', {
        note: document.getElementById('deliveryNote').value,
        photo: document.getElementById('deliveryPhoto').files[0],
      }, document.querySelector('.btn-deliver'), '<i class="bi bi-check-circle-fill" style="font-size:20px"></i> Mark as Delivered ✓',
      (data) => {
        if (data && data.needs_remittance) {
          window.location.reload();
          return;
        }
        hide();
        document.getElementById('successScreen').style.display = 'block';
      });
    }
  });
}
function submitIssue() {
  if (!selectedIssue) { alert('Please select what happened.'); return; }
  doFetch('/rider/' + ORDER_ID + '/' + TOKEN + '/issue', {
    issue_type: selectedIssue,
    note: document.getElementById('issueNote').value,
    photo: document.getElementById('issuePhoto').files[0],
  }, document.querySelector('.btn-submit'), '<i class="bi bi-send me-1"></i>Submit Report',
  () => {
    hide();
    document.getElementById('issueSuccessScreen').style.display = 'block';
    if (selectedIssue === 'not_home')
      document.getElementById('issueSuccessMsg').textContent = 'Customer Not Home reported. Admin will contact the customer to reschedule.';
  });
}
async function doFetch(url, fields, btn, originalHtml, onSuccess) {
  btn.disabled = true;
  btn.innerHTML = '<div class="spin"></div>';
  const fd = new FormData();
  fd.append('_token', '{{ csrf_token() }}');
  for (const [k,v] of Object.entries(fields)) if (v !== undefined && v !== null && v !== '') fd.append(k, v);
  try {
    const res = await fetch(url, { method:'POST', body:fd });
    const data = await res.json();
    if (data.ok) { onSuccess(data); }
    else {
      if (data.payment_blocked) {
        latestRemainingAmount = Number(data.remaining_amount || latestRemainingAmount || 0);
        rcOpen({
          icon: '!',
          iconBg: '#fef3c7',
          title: 'GCash Payment Pending',
          message: data.error || 'Customer must complete GCash payment first.',
          okLabel: 'Got it',
          okColor: '#d97706'
        });
      } else {
        alert(data.error || 'Error. Please try again.');
      }
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  } catch(e) { alert('Network error. Please try again.'); btn.disabled = false; btn.innerHTML = originalHtml; }
}
</script>
<script>
(function () {
  async function registerRiderPush() {
    if (!@json((bool) config('services.fcm.mobile_registration_enabled'))) return;

    const capacitor = window.Capacitor;
    const push = capacitor?.Plugins?.PushNotifications;
    if (!capacitor?.isNativePlatform?.() || !push) return;

    try {
      let permission = await push.checkPermissions();
      if (permission.receive !== 'granted') {
        permission = await push.requestPermissions();
      }
      if (permission.receive !== 'granted') return;

      await push.removeAllListeners();
      await push.addListener('registration', function (token) {
        const value = token?.value || token;
        if (!value) return;
        fetch('{{ route('device.register') }}', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
          },
          body: JSON.stringify({
            device_token: value,
            device_type: 'android',
            platform: navigator.platform || 'Android',
            device_name: navigator.userAgent || '',
            rider_order_id: ORDER_ID,
            rider_token: TOKEN,
          }),
        }).catch(function () {});
      });

      await push.addListener('pushNotificationActionPerformed', function (event) {
        const url = event?.notification?.data?.url;
        if (url) window.location.href = url;
      });

      await push.register();
    } catch (error) {
      console.warn('Rider push setup skipped', error);
    }
  }

  registerRiderPush();
})();
</script>
</body>
</html>
