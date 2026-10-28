<!DOCTYPE html>
<html lang="en">
<head>
  @php
    $platformBrand = null;
    try { $platformBrand = \Illuminate\Support\Facades\DB::table('platform_settings')->first(); } catch (\Throwable $e) {}
    $rawPrimary = $platformBrand->platform_primary_color ?? '#7B3A0F';
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $rawPrimary)) $rawPrimary = '#7B3A0F';
    $hexAdjust = function(string $hex, float $factor): string {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
        if ($factor >= 0) {
            $r = (int) min(255, $r + (255 - $r) * $factor);
            $g = (int) min(255, $g + (255 - $g) * $factor);
            $b = (int) min(255, $b + (255 - $b) * $factor);
        } else {
            $f = 1 + $factor;
            $r = (int) max(0, $r * $f); $g = (int) max(0, $g * $f); $b = (int) max(0, $b * $f);
        }
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    };
    $primaryDark = $hexAdjust($rawPrimary, -0.30);
    $primaryBg = $hexAdjust($rawPrimary, 0.90);
    $pbgColor = $platformBrand->platform_bg_color ?? '#FFF8F8';

    $pendingOrders = $orders->filter(fn ($o) => ($o->rider_assignment_status ?? '') === 'pending')->values();
    $activeOrders = $orders->filter(fn ($o) => ($o->rider_assignment_status ?? '') !== 'pending' && $o->rider_bucket === 'Active Delivery')->values();
    $cashOrders = $orders->filter(fn ($o) => ($o->rider_assignment_status ?? '') !== 'pending' && $o->rider_bucket !== 'Active Delivery')->values();
    $feedSignature = md5($orders->map(fn ($o) => implode(':', [
        $o->id,
        $o->rider_bucket,
        $o->rider_assignment_status ?? '',
        $o->remittance_status ?? '',
    ]))->sort()->implode('|'));
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $firstName = trim(explode(' ', trim((string) $rider->name))[0] ?? '') ?: 'Rider';
  @endphp
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="{{ $rawPrimary }}">
  <title>Rider Dashboard</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root { --primary: {{ $rawPrimary }}; --primary-dark: {{ $primaryDark }}; --primary-bg: {{ $primaryBg }}; --ink:#111827; --muted:#6b7280; --line:#e5e7eb; }
    * { box-sizing:border-box; }
    body { margin:0; min-height:100dvh; background:{{ $pbgColor }}; color:var(--ink); font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; -webkit-font-smoothing:antialiased; }
    .topbar { background:linear-gradient(135deg,var(--primary),var(--primary-dark)); color:#fff; padding:18px clamp(14px,4vw,28px) 22px; display:flex; gap:12px; align-items:center; justify-content:space-between; }
    .title { min-width:0; }
    .title .hello { font-size:13px; opacity:.85; margin:0; }
    .title h1 { font-size:clamp(20px,5vw,26px); margin:2px 0 0; line-height:1.15; }
    .title .sub { margin:4px 0 0; opacity:.8; font-size:12px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .live { display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,.16); border-radius:999px; padding:3px 9px; font-weight:700; }
    .live::before { content:""; width:8px; height:8px; border-radius:50%; background:#4ade80; box-shadow:0 0 0 0 rgba(74,222,128,.7); animation:pulse 2s infinite; }
    .live.off::before { background:#fbbf24; animation:none; }
    @keyframes pulse { 70% { box-shadow:0 0 0 7px rgba(74,222,128,0); } 100% { box-shadow:0 0 0 0 rgba(74,222,128,0); } }
    .logout { border:1px solid rgba(255,255,255,.35); background:rgba(255,255,255,.15); color:#fff; border-radius:12px; width:44px; height:44px; display:grid; place-items:center; cursor:pointer; flex-shrink:0; }
    .wrap { width:min(1120px,100%); margin:-12px auto 0; padding:0 clamp(12px,3vw,24px) 32px; position:relative; }
    .notice { border-radius:12px; padding:12px 14px; margin:0 0 12px; font-size:14px; font-weight:650; }
    .ok { background:#ecfdf5; color:#166534; border:1px solid #bbf7d0; }
    .err { background:#fff1f2; color:#9f1239; border:1px solid #fecdd3; }

    .stats { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin:0 0 18px; }
    .stat { background:#fff; border:1px solid var(--line); border-radius:14px; padding:14px; display:flex; align-items:center; gap:12px; min-width:0; box-shadow:0 4px 14px rgba(17,24,39,.05); }
    .stat-icon { width:42px; height:42px; border-radius:12px; display:grid; place-items:center; font-size:20px; flex-shrink:0; }
    .stat-icon.s-new { background:#fff7ed; color:#c2410c; }
    .stat-icon.s-act { background:#eff6ff; color:#1d4ed8; }
    .stat-icon.s-cash { background:var(--primary-bg); color:var(--primary-dark); }
    .stat .label { color:var(--muted); font-size:12px; font-weight:700; }
    .stat .value { font-size:clamp(18px,4.5vw,24px); font-weight:850; line-height:1.1; overflow-wrap:anywhere; }
    .stat.hot { border-color:#fdba74; background:linear-gradient(180deg,#fff,#fff7ed); }

    .section { margin:0 0 20px; }
    .section-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin:0 0 10px; flex-wrap:wrap; }
    .section-head h2 { font-size:16px; margin:0; display:flex; align-items:center; gap:8px; }
    .count-pill { background:#fee2e2; color:#b91c1c; border-radius:999px; font-size:12px; padding:2px 9px; font-weight:800; }
    .count-pill.neutral { background:#e5e7eb; color:#374151; }
    .geo { border:1px solid #dbeafe; background:#eff6ff; color:#1d4ed8; border-radius:10px; padding:8px 11px; font-weight:750; cursor:pointer; display:flex; align-items:center; gap:8px; font-size:13px; }
    .list { display:grid; gap:12px; }

    .order { background:#fff; border:1px solid var(--line); border-radius:16px; overflow:hidden; box-shadow:0 4px 14px rgba(17,24,39,.05); }
    .order-pending { border-color:#fdba74; box-shadow:0 6px 20px rgba(234,88,12,.14); animation:pop .35s ease-out; }
    @keyframes pop { from { transform:translateY(6px); opacity:.4; } to { transform:none; opacity:1; } }
    .order-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:14px 16px; }
    .order-id { font-size:12px; color:var(--muted); font-weight:800; letter-spacing:.03em; }
    .product { font-size:17px; font-weight:850; margin-top:2px; overflow-wrap:anywhere; }
    .badge { border-radius:999px; padding:5px 10px; font-size:12px; font-weight:800; white-space:nowrap; display:inline-block; }
    .b-pending { background:#ffedd5; color:#9a3412; }
    .b-active { background:#dbeafe; color:#1d4ed8; }
    .b-remit { background:#fee2e2; color:#b91c1c; }
    .b-wait { background:#ede9fe; color:#6d28d9; }
    .countdown { margin:0 16px; padding:8px 12px; border-radius:10px; background:#fff7ed; color:#9a3412; font-size:13px; font-weight:700; display:flex; align-items:center; gap:8px; }
    .countdown.low { background:#fee2e2; color:#b91c1c; }
    .countdown strong { font-variant-numeric:tabular-nums; font-size:15px; }
    .order-body { display:grid; grid-template-columns:1.5fr .8fr; gap:14px; padding:14px 16px 16px; }
    .detail { list-style:none; margin:0; padding:0; display:grid; gap:9px; color:#374151; font-size:14px; line-height:1.4; overflow-wrap:anywhere; }
    .detail li { display:flex; gap:10px; align-items:flex-start; }
    .detail i { color:var(--primary); width:18px; text-align:center; margin-top:1px; flex-shrink:0; }
    .actions { display:grid; gap:8px; align-content:start; }
    .btn { border:0; border-radius:12px; padding:11px 14px; font-weight:800; text-decoration:none; text-align:center; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; min-height:46px; font-size:14px; width:100%; font-family:inherit; }
    .btn-lg { min-height:50px; font-size:15px; }
    .btn-primary { background:var(--primary); color:#fff; }
    .btn-outline { background:#fff; color:var(--primary-dark); border:1px solid var(--primary); }
    .btn-danger { background:#fff; color:#b91c1c; border:1px solid #fecaca; }
    .btn[disabled] { opacity:.6; cursor:not-allowed; }
    .empty { background:#fff; border:1px dashed #d1d5db; border-radius:16px; padding:28px 16px; text-align:center; color:var(--muted); font-size:14px; }
    .empty i { font-size:30px; color:var(--primary); }
    .empty .t { font-weight:800; margin-top:6px; color:var(--ink); }
    .order-note { color:var(--muted); font-size:13px; font-weight:700; text-align:center; padding:10px; border:1px solid var(--line); border-radius:10px; background:#f9fafb; }

    .remit-hub { background:#fff; border:1px solid var(--line); border-radius:16px; overflow:hidden; box-shadow:0 4px 14px rgba(17,24,39,.05); }
    .remit-hub > summary { list-style:none; cursor:pointer; padding:14px 16px; display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .remit-hub > summary::-webkit-details-marker { display:none; }
    .remit-hub > summary .chev { transition:transform .2s; color:var(--muted); }
    .remit-hub[open] > summary .chev { transform:rotate(180deg); }
    .remit-kicker { color:var(--muted); font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.05em; }
    .remit-total { font-size:clamp(22px,6vw,32px); font-weight:900; line-height:1.1; margin-top:2px; overflow-wrap:anywhere; }
    .remit-inner { border-top:1px solid #f3f4f6; }
    .remit-head { display:grid; grid-template-columns:1fr auto; gap:14px; padding:14px 16px; align-items:center; }
    .remit-copy { color:#4b5563; font-size:13px; line-height:1.45; max-width:720px; }
    .remit-actions { display:grid; gap:8px; min-width:min(260px,100%); }
    .btn-pay { background:#1d4ed8; color:#fff; }
    .btn-soft { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
    .remit-breakdown { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1px; background:#f3f4f6; }
    .remit-stat { background:#fff; padding:12px 16px; min-width:0; }
    .remit-stat .label { color:var(--muted); font-size:12px; font-weight:800; }
    .remit-stat .value { font-size:17px; font-weight:900; margin-top:2px; overflow-wrap:anywhere; }
    .remit-orders { padding:12px 16px 16px; display:grid; gap:8px; }
    .remit-order { display:flex; justify-content:space-between; gap:12px; border:1px solid #f3f4f6; border-radius:10px; padding:10px 12px; font-size:13px; }
    .remit-order span { color:var(--muted); }
    .cash-list { margin-top:12px; }

    .modal { position:fixed; inset:0; background:rgba(17,24,39,.55); display:none; align-items:flex-end; justify-content:center; z-index:50; padding:0; }
    .modal.open { display:flex; }
    .sheet { background:#fff; width:min(480px,100%); border-radius:20px 20px 0 0; padding:20px 18px calc(20px + env(safe-area-inset-bottom)); }
    .sheet h3 { margin:0 0 4px; font-size:18px; }
    .sheet p { margin:0 0 12px; color:var(--muted); font-size:13px; }
    .sheet textarea { width:100%; min-height:90px; resize:vertical; border:1px solid var(--line); border-radius:12px; padding:12px; font:inherit; font-size:14px; }
    .sheet .row { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:12px; }
    .quick { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:10px; }
    .quick button { border:1px solid var(--line); background:#f9fafb; border-radius:999px; padding:6px 11px; font-size:12px; font-weight:700; cursor:pointer; color:#374151; }

    .toast-stack { position:fixed; top:12px; left:50%; transform:translateX(-50%); z-index:60; display:grid; gap:8px; width:min(420px,calc(100% - 24px)); }
    .toast { background:#111827; color:#fff; border-radius:12px; padding:12px 14px; font-size:14px; font-weight:650; display:flex; gap:10px; align-items:center; box-shadow:0 10px 30px rgba(0,0,0,.25); animation:pop .25s ease-out; }
    .toast.good { background:#166534; }
    .toast.warn { background:#9a3412; }

    @media (min-width:640px) { .modal { align-items:center; } .sheet { border-radius:20px; } }
    @media (max-width:760px) {
      .order-body { grid-template-columns:1fr; }
      .remit-head { grid-template-columns:1fr; }
      .remit-actions { min-width:0; }
      .remit-breakdown { grid-template-columns:repeat(2,minmax(0,1fr)); }
      .remit-order { flex-direction:column; }
    }
    @media (max-width:420px) {
      .stats { grid-template-columns:1fr; }
      .stat { padding:12px; }
    }
    @media (prefers-reduced-motion:reduce) { * { animation:none !important; transition:none !important; } }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="title">
      <p class="hello">{{ $greeting }},</p>
      <h1>{{ $firstName }}</h1>
      <p class="sub">
        <span class="live" id="liveState">Live</span>
        @if($rider->phone)<span>{{ $rider->phone }}</span>@endif
      </p>
    </div>
    <form action="{{ route('rider.logout') }}" method="POST">@csrf
      <button class="logout" title="Logout" aria-label="Logout" type="submit"><i class="bi bi-box-arrow-right"></i></button>
    </form>
  </header>

  <main class="wrap">
    @if(session('msg'))<div class="notice ok">{{ session('msg') }}</div>@endif
    @if(session('err') || $errors->any())<div class="notice err">{{ session('err') ?: $errors->first() }}</div>@endif

    <div id="liveRegion">
      <section class="stats" aria-label="Today at a glance">
        <div class="stat {{ $pendingOrders->count() ? 'hot' : '' }}">
          <div class="stat-icon s-new"><i class="bi bi-bell"></i></div>
          <div><div class="label">Needs response</div><div class="value">{{ $pendingOrders->count() }}</div></div>
        </div>
        <div class="stat">
          <div class="stat-icon s-act"><i class="bi bi-bicycle"></i></div>
          <div><div class="label">Active deliveries</div><div class="value">{{ $activeOrders->count() }}</div></div>
        </div>
        <div class="stat">
          <div class="stat-icon s-cash"><i class="bi bi-cash-stack"></i></div>
          <div><div class="label">COD to remit</div><div class="value">PHP {{ number_format($unremitted['total'], 2) }}</div></div>
        </div>
      </section>

      <section class="section" aria-label="Needs your response">
        <div class="section-head">
          <h2><i class="bi bi-bell-fill" style="color:#ea580c"></i>Needs your response @if($pendingOrders->count())<span class="count-pill">{{ $pendingOrders->count() }}</span>@endif</h2>
        </div>
        <div class="list">
          @forelse($pendingOrders as $order)
            @include('rider._order_card', ['order' => $order])
          @empty
            <div class="empty"><i class="bi bi-inbox"></i><div class="t">No new requests</div><div>New delivery requests appear here automatically.</div></div>
          @endforelse
        </div>
      </section>

      <section class="section" aria-label="Active deliveries">
        <div class="section-head">
          <h2><i class="bi bi-bicycle" style="color:#1d4ed8"></i>Active deliveries @if($activeOrders->count())<span class="count-pill neutral">{{ $activeOrders->count() }}</span>@endif</h2>
          <button class="geo" type="button" onclick="useLocation()"><i class="bi bi-crosshair"></i> Sort by my location</button>
        </div>
        <div class="list">
          @forelse($activeOrders as $order)
            @include('rider._order_card', ['order' => $order])
          @empty
            <div class="empty"><i class="bi bi-check2-circle"></i><div class="t">No active deliveries</div><div>Accepted deliveries will show here.</div></div>
          @endforelse
        </div>
      </section>

      <section class="section" aria-label="COD remittance">
        <div class="section-head"><h2><i class="bi bi-wallet2" style="color:var(--primary)"></i>COD remittance</h2></div>
        <details class="remit-hub" {{ ($bulkRemittance['ready_count'] > 0 || $bulkRemittance['active_batch'] || $unremitted['rejected'] > 0) ? 'open' : '' }}>
          <summary>
            <div>
              <div class="remit-kicker">Bulk COD remittance</div>
              <div class="remit-total">PHP {{ number_format($bulkRemittance['active_batch']->total_amount ?? $bulkRemittance['ready_total'], 2) }}</div>
            </div>
            <i class="bi bi-chevron-down chev"></i>
          </summary>
          <div class="remit-inner">
            <div class="remit-head">
              <div class="remit-copy">
                @if($bulkRemittance['active_batch'])
                  A GCash payment is already active for this total. Open it again or check after payment.
                @elseif($bulkRemittance['ready_count'] > 0)
                  Pay once for all delivered COD cash instead of sending separate payments per order.
                @else
                  Delivered COD orders that need remittance will appear here.
                @endif
              </div>
              <div class="remit-actions">
                @if($bulkRemittance['active_batch'])
                  @if(!empty($bulkRemittance['active_batch']->paymongo_action_url))
                    <a class="btn btn-pay" href="{{ $bulkRemittance['active_batch']->paymongo_action_url }}" target="_blank" rel="noopener"><i class="bi bi-phone"></i>Open GCash Payment</a>
                  @endif
                  <form method="POST" action="{{ route('rider.remittance.bulk.check') }}">@csrf
                    <button class="btn btn-soft" type="submit"><i class="bi bi-arrow-repeat"></i>Check Payment Status</button>
                  </form>
                @elseif($bulkRemittance['ready_count'] > 0)
                  <form method="POST" action="{{ route('rider.remittance.bulk.qr') }}">@csrf
                    <input type="hidden" name="open_payment" value="1">
                    <button class="btn btn-pay" type="submit"><i class="bi bi-qr-code"></i>Pay Total via GCash</button>
                  </form>
                @else
                  <button class="btn btn-soft" type="button" disabled><i class="bi bi-check2-circle"></i>No COD to Remit</button>
                @endif
              </div>
            </div>
            <div class="remit-breakdown">
              <div class="remit-stat"><div class="label">Ready orders</div><div class="value">{{ $bulkRemittance['ready_count'] }}</div></div>
              <div class="remit-stat"><div class="label">Waiting amount</div><div class="value">PHP {{ number_format($bulkRemittance['waiting_total'], 2) }}</div></div>
              <div class="remit-stat"><div class="label">Waiting for check</div><div class="value">{{ $unremitted['waiting_seller'] + $unremitted['waiting_paymongo'] }}</div></div>
              <div class="remit-stat"><div class="label">Confirmed this week</div><div class="value">PHP {{ number_format($bulkRemittance['confirmed_total'], 2) }}</div></div>
            </div>
            @if($unremitted['rejected'] > 0)
              <div class="remit-orders"><div class="order-note" style="color:#b91c1c;background:#fff1f2;border-color:#fecdd3">{{ $unremitted['rejected'] }} rejected remittance(s) are included again when you pay the bulk total.</div></div>
            @endif
            @if($bulkRemittance['orders']->count())
              <div class="remit-orders">
                @foreach($bulkRemittance['orders']->take(6) as $remitOrder)
                  <div class="remit-order">
                    <div><strong>Order #{{ $remitOrder->order_id }}</strong> <span>{{ $remitOrder->product_name }}</span></div>
                    <strong>PHP {{ number_format((float) $remitOrder->amount, 2) }}</strong>
                  </div>
                @endforeach
                @if($bulkRemittance['orders']->count() > 6)
                  <div class="order-note">{{ $bulkRemittance['orders']->count() - 6 }} more order(s) included in this total.</div>
                @endif
              </div>
            @endif
          </div>
        </details>

        @if($cashOrders->count())
          <div class="list cash-list">
            @foreach($cashOrders as $order)
              @include('rider._order_card', ['order' => $order])
            @endforeach
          </div>
        @endif
      </section>
    </div>
  </main>

  <div class="modal" id="declineModal" role="dialog" aria-modal="true" aria-labelledby="declineTitle">
    <form class="sheet" id="declineForm" method="POST" data-once>
      @csrf
      <h3 id="declineTitle">Decline delivery</h3>
      <p id="declineLabel">Tell the seller why so they can assign another rider.</p>
      <div class="quick">
        <button type="button" data-reason="Too far from my location">Too far</button>
        <button type="button" data-reason="I am not available right now">Not available</button>
        <button type="button" data-reason="Vehicle problem">Vehicle problem</button>
      </div>
      <textarea name="reason" id="declineReason" maxlength="300" required placeholder="Reason for declining"></textarea>
      <div class="row">
        <button class="btn btn-outline" type="button" id="declineCancel">Back</button>
        <button class="btn btn-danger" type="submit"><i class="bi bi-x-circle"></i>Decline</button>
      </div>
    </form>
  </div>

  <div class="toast-stack" id="toasts" aria-live="polite"></div>

  <script>
    const FEED_URL = @json(route('rider.dashboard.feed'));
    const POLL_MS = 5000;
    let lastSignature = @json($feedSignature);
    let lastPendingIds = @json($pendingOrders->pluck('id')->values());
    let polling = false;

    function useLocation() {
      if (!navigator.geolocation) return;
      navigator.geolocation.getCurrentPosition(function (pos) {
        const url = new URL(window.location.href);
        url.searchParams.set('lat', pos.coords.latitude.toFixed(7));
        url.searchParams.set('lng', pos.coords.longitude.toFixed(7));
        window.location.href = url.toString();
      });
    }

    function toast(message, kind) {
      const box = document.getElementById('toasts');
      const el = document.createElement('div');
      el.className = 'toast ' + (kind || '');
      el.textContent = message;
      box.appendChild(el);
      setTimeout(function () { el.remove(); }, 6000);
    }

    function alertNewRequest() {
      try { if (navigator.vibrate) navigator.vibrate([200, 100, 200]); } catch (e) {}
      try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        const ctx = new Ctx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        osc.frequency.value = 880; gain.gain.value = 0.08;
        osc.start(); osc.stop(ctx.currentTime + 0.25);
      } catch (e) {}
    }

    function setLive(ok) {
      const el = document.getElementById('liveState');
      el.classList.toggle('off', !ok);
      el.textContent = ok ? 'Live' : 'Reconnecting...';
    }

    function tickCountdowns() {
      let expired = false;
      document.querySelectorAll('.countdown[data-expires]').forEach(function (box) {
        const left = Math.floor((new Date(box.dataset.expires).getTime() - Date.now()) / 1000);
        const label = box.querySelector('.countdown-time');
        if (left <= 0) { label.textContent = 'Expired'; expired = true; return; }
        label.textContent = String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
        box.classList.toggle('low', left <= 120);
      });
      if (expired && !polling) poll(true);
    }

    async function refreshRegion() {
      const res = await fetch(window.location.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) throw new Error('refresh failed');
      const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
      const next = doc.getElementById('liveRegion');
      if (!next) throw new Error('no region');
      const current = document.getElementById('liveRegion');
      const openState = current.querySelector('.remit-hub') ? current.querySelector('.remit-hub').open : null;
      current.innerHTML = next.innerHTML;
      const hub = current.querySelector('.remit-hub');
      if (hub && openState !== null) hub.open = openState || hub.open;
      tickCountdowns();
    }

    async function poll(force) {
      if (polling || (document.hidden && !force)) return;
      polling = true;
      try {
        const res = await fetch(FEED_URL, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
        if (res.status === 401) { window.location.reload(); return; }
        const data = await res.json();
        if (!res.ok || !data.ok) throw new Error('feed');
        setLive(true);
        if (data.signature !== lastSignature) {
          const fresh = data.pending_ids.filter(function (id) { return lastPendingIds.indexOf(id) === -1; });
          const gone = lastPendingIds.filter(function (id) { return data.pending_ids.indexOf(id) === -1; });
          await refreshRegion();
          lastSignature = data.signature;
          if (fresh.length) { toast('New delivery request: Order #' + fresh.join(', #'), 'warn'); alertNewRequest(); }
          else if (gone.length) { toast('A delivery request was updated or removed.'); }
          lastPendingIds = data.pending_ids;
          const modal = document.getElementById('declineModal');
          if (modal.classList.contains('open') && !document.querySelector('[data-decline][data-action="' + document.getElementById('declineForm').getAttribute('action') + '"]')) {
            closeDecline();
            toast('That request is no longer active.');
          }
        }
      } catch (e) {
        setLive(false);
      } finally {
        polling = false;
      }
    }

    function openDecline(action, label) {
      const form = document.getElementById('declineForm');
      form.setAttribute('action', action);
      document.getElementById('declineLabel').textContent = label + ' - tell the seller why so they can assign another rider.';
      document.getElementById('declineReason').value = '';
      document.getElementById('declineModal').classList.add('open');
      document.getElementById('declineReason').focus();
    }
    function closeDecline() { document.getElementById('declineModal').classList.remove('open'); }

    document.addEventListener('click', function (e) {
      const declineBtn = e.target.closest('[data-decline]');
      if (declineBtn) { openDecline(declineBtn.dataset.action, declineBtn.dataset.label); return; }
      const quick = e.target.closest('[data-reason]');
      if (quick) { document.getElementById('declineReason').value = quick.dataset.reason; return; }
      if (e.target.id === 'declineCancel' || e.target.id === 'declineModal') closeDecline();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeDecline(); });

    document.addEventListener('submit', function (e) {
      const form = e.target.closest('form[data-once]');
      if (!form) return;
      if (form.dataset.submitted) { e.preventDefault(); return; }
      form.dataset.submitted = '1';
      form.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = true; });
    });

    setInterval(tickCountdowns, 1000);
    setInterval(function () { poll(false); }, POLL_MS);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(true); });
    window.addEventListener('online', function () { poll(true); });
    tickCountdowns();
  </script>
</body>
</html>
