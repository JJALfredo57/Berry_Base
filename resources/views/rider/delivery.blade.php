<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="theme-color" content="#e91e63">
  <title>Delivery #{{ $order->id }}</title>
  @if(!empty($settings['logo_path']))
    <link rel="icon" type="image/png" href="{{ $settings['logo_path'] }}">
  @endif
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
    html { font-size: clamp(16px, 4vw, 22px); }
    body { width: 100%; min-height: 100vh; background: #f5f5f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 1rem; color: #111; }

    /* ── Header ─────────────── */
    .hdr {
      background: linear-gradient(135deg, #e91e63, #c2185b);
      color: #fff;
      padding: clamp(12px, 3vw, 20px) clamp(14px, 4vw, 22px);
      display: flex;
      align-items: center;
      gap: clamp(8px, 2vw, 14px);
    }
    .hdr-logo { width: clamp(28px, 7vw, 40px); height: clamp(28px, 7vw, 40px); border-radius: 6px; object-fit: cover; flex-shrink: 0; }
    .hdr-text { flex: 1; min-width: 0; }
    .hdr-shop { font-size: clamp(11px, 3vw, 15px); opacity: .85; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .hdr-order { font-size: clamp(16px, 4.5vw, 22px); font-weight: 700; }

    /* ── Section ─────────────── */
    .section { background: #fff; margin: clamp(8px, 2vw, 12px) 0; padding: 0; }
    .section-title { font-size: clamp(11px, 2.8vw, 14px); font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #9ca3af; padding: clamp(12px, 3vw, 16px) clamp(14px, 4vw, 20px) clamp(4px, 1vw, 8px); }
    .row { display: flex; align-items: flex-start; gap: clamp(10px, 2.5vw, 16px); padding: clamp(12px, 3vw, 16px) clamp(14px, 4vw, 20px); border-top: 1px solid #f3f4f6; }
    .row-icon { width: clamp(38px, 9vw, 50px); height: clamp(38px, 9vw, 50px); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: clamp(18px, 4.5vw, 24px); }
    .row-body { flex: 1; min-width: 0; overflow: hidden; }
    .row-label { font-size: clamp(11px, 2.8vw, 14px); color: #9ca3af; margin-bottom: 3px; }
    .row-value { font-size: clamp(14px, 3.8vw, 19px); font-weight: 600; word-break: break-word; overflow-wrap: anywhere; }
    .row-sub { font-size: clamp(12px, 3vw, 15px); color: #6b7280; margin-top: 4px; }
    .row-link { font-size: clamp(13px, 3.2vw, 16px); font-weight: 600; color: #2563eb; text-decoration: none; display: inline-block; margin-top: 5px; }

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
    .photo-label { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: clamp(14px, 3.5vw, 18px); background: #f9fafb; border: 2px dashed #d1d5db; border-radius: 12px; font-size: clamp(14px, 3.5vw, 18px); font-weight: 600; color: #6b7280; cursor: pointer; }
    .photo-label:active { background: #f3f4f6; }
    .photo-label i { font-size: clamp(18px, 5vw, 24px); }
    .photo-preview { width: 100%; border-radius: 10px; margin-top: 10px; display: none; object-fit: cover; max-height: clamp(180px, 45vw, 260px); }
    .note-input { width: 100%; padding: clamp(12px, 3vw, 16px); border: 1.5px solid #e5e7eb; border-radius: 10px; font-size: clamp(14px, 3.5vw, 18px); font-family: inherit; resize: none; margin-top: 10px; }
    .note-input:focus { outline: none; border-color: #e91e63; }

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
  @if(!empty($settings['logo_path']))
    <img src="{{ $settings['logo_path'] }}" class="hdr-logo" onerror="this.style.display='none'">
  @endif
  <div class="hdr-text">
    <div class="hdr-shop">{{ $settings['site_title'] ?? 'Cake Shop' }} · Delivery</div>
    <div class="hdr-order">Order #{{ $order->id }}</div>
  </div>
</div>

@if(isset($done) && $done)
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
    <div class="row-icon" style="background:#fce7f3">👤</div>
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
    <div class="row-icon" style="background:#fce7f3">🎂</div>
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
@if($order->payment_method === 'COD')
<div class="pay-banner pay-cod">
  <div class="pay-icon">💵</div>
  <div class="pay-body">
    <div class="pay-label">Collect Cash from Customer</div>
    <div class="pay-amount">₱{{ number_format($order->total_price,2) }}</div>
  </div>
</div>
@elseif($order->payment_status === 'Paid')
<div class="pay-banner pay-ok">
  <div class="pay-icon">✅</div>
  <div class="pay-body">
    <div class="pay-label">GCash — Fully Paid</div>
    <div class="pay-amount">₱{{ number_format($order->total_price,2) }}</div>
    <div class="pay-note">No collection needed</div>
  </div>
</div>
@elseif($order->payment_status === 'Partial Payment')
  @php $rem = $order->total_price - ($order->deposit_amount ?? 0); @endphp
<div class="pay-banner pay-gcash">
  <div class="pay-icon">📱</div>
  <div class="pay-body">
    <div class="pay-label">Collect Remaining Balance</div>
    <div class="pay-amount">₱{{ number_format($rem,2) }}</div>
    <div class="pay-note">Deposit of ₱{{ number_format($order->deposit_amount,2) }} already paid</div>
  </div>
</div>
@else
<div class="pay-banner pay-gcash">
  <div class="pay-icon">📱</div>
  <div class="pay-body">
    <div class="pay-label">GCash — Not Yet Paid</div>
    <div class="pay-amount">₱{{ number_format($order->total_price,2) }}</div>
    <div class="pay-note">Customer needs to pay via GCash</div>
  </div>
</div>
@endif

{{-- Photo + Note --}}
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

{{-- Buttons --}}
<div class="actions" id="actionSection">
  <button class="btn-deliver" onclick="confirmDeliver()">
    <i class="bi bi-check-circle-fill" style="font-size:20px"></i> Mark as Delivered ✓
  </button>
  <button class="btn-issue" onclick="showIssueForm()">
    <i class="bi bi-exclamation-triangle" style="font-size:17px"></i> Report an Issue
  </button>
</div>

{{-- Issue Form --}}
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
      () => { hide(); document.getElementById('successScreen').style.display = 'block'; });
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
    if (data.ok) { onSuccess(); }
    else { alert(data.error || 'Error. Please try again.'); btn.disabled = false; btn.innerHTML = originalHtml; }
  } catch(e) { alert('Network error. Please try again.'); btn.disabled = false; btn.innerHTML = originalHtml; }
}
</script>
</body>
</html>
