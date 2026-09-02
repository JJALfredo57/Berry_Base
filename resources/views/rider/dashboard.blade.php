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
  @endphp
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="{{ $rawPrimary }}">
  <title>Rider Dashboard</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root { --primary: {{ $rawPrimary }}; --primary-dark: {{ $primaryDark }}; --primary-bg: {{ $primaryBg }}; }
    * { box-sizing:border-box; }
    body { margin:0; min-height:100dvh; background:{{ $pbgColor }}; color:#111827; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
    .topbar { background:linear-gradient(135deg,var(--primary),var(--primary-dark)); color:#fff; padding:16px clamp(14px,4vw,28px); display:flex; gap:12px; align-items:center; justify-content:space-between; }
    .title { min-width:0; }
    .title h1 { font-size:clamp(20px,5vw,28px); margin:0; line-height:1.1; }
    .title p { margin:4px 0 0; opacity:.86; font-size:clamp(12px,3vw,14px); }
    .logout { border:1px solid rgba(255,255,255,.35); background:rgba(255,255,255,.15); color:#fff; border-radius:8px; width:42px; height:42px; display:grid; place-items:center; cursor:pointer; }
    .wrap { width:min(1120px,100%); margin:0 auto; padding:14px clamp(12px,3vw,24px) 28px; }
    .notice { border-radius:8px; padding:12px 14px; margin:0 0 12px; font-size:14px; font-weight:650; }
    .ok { background:#ecfdf5; color:#166534; border:1px solid #bbf7d0; }
    .err { background:#fff1f2; color:#9f1239; border:1px solid #fecdd3; }
    .summary { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px; margin:12px 0 16px; }
    .metric { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:12px; min-width:0; }
    .metric .label { color:#6b7280; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; }
    .metric .value { font-size:clamp(18px,4vw,26px); font-weight:850; margin-top:4px; color:#111827; overflow-wrap:anywhere; }
    .metric .hint { color:#6b7280; font-size:12px; line-height:1.35; margin-top:5px; }
    .metric.primary { background:var(--primary-bg); border-color:color-mix(in srgb,var(--primary) 28%,#fff); }
    .metric.primary .value { color:var(--primary-dark); }
    .toolbar { display:flex; align-items:center; justify-content:space-between; gap:10px; margin:10px 0; flex-wrap:wrap; }
    .toolbar h2 { font-size:16px; margin:0; }
    .geo { border:1px solid #dbeafe; background:#eff6ff; color:#1d4ed8; border-radius:8px; padding:9px 11px; font-weight:750; cursor:pointer; display:flex; align-items:center; gap:8px; }
    .list { display:grid; gap:10px; }
    .order { background:#fff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; }
    .order-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:14px; border-bottom:1px solid #f3f4f6; }
    .order-id { font-size:13px; color:#6b7280; font-weight:800; }
    .product { font-size:17px; font-weight:850; margin-top:2px; }
    .badge { border-radius:999px; padding:6px 9px; font-size:12px; font-weight:850; white-space:nowrap; }
    .b-pending { background:#fff7ed; color:#9a3412; }
    .b-active { background:#eff6ff; color:#1d4ed8; }
    .b-remit { background:#fef2f2; color:#b91c1c; }
    .b-wait { background:#f5f3ff; color:#6d28d9; }
    .order-body { display:grid; grid-template-columns:1.4fr .8fr; gap:12px; padding:14px; }
    .detail { color:#374151; font-size:14px; line-height:1.45; overflow-wrap:anywhere; }
    .detail i { color:var(--primary); margin-right:5px; }
    .actions { display:grid; gap:8px; align-content:start; }
    .btn { border:0; border-radius:8px; padding:11px 12px; font-weight:850; text-decoration:none; text-align:center; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; min-height:42px; }
    .btn-primary { background:var(--primary); color:#fff; }
    .btn-outline { background:#fff; color:var(--primary-dark); border:1px solid var(--primary); }
    .btn-danger { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
    .decline { display:grid; gap:8px; }
    .decline textarea { width:100%; min-height:64px; resize:vertical; border:1px solid #e5e7eb; border-radius:8px; padding:10px; font:inherit; }
    .empty { background:#fff; border:1px dashed #d1d5db; border-radius:8px; padding:32px 16px; text-align:center; color:#6b7280; }
    @media (max-width:760px) {
      .summary { grid-template-columns:repeat(2,minmax(0,1fr)); }
      .summary .primary { grid-column:1 / -1; }
      .order-body { grid-template-columns:1fr; }
      .order-head { flex-direction:column; }
      .badge { white-space:normal; }
    }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="title">
      <h1>Rider Dashboard</h1>
      <p>{{ $rider->name }}{{ $rider->phone ? ' - '.$rider->phone : '' }}</p>
    </div>
    <form action="{{ route('rider.logout') }}" method="POST">@csrf
      <button class="logout" title="Logout" type="submit"><i class="bi bi-box-arrow-right"></i></button>
    </form>
  </header>

  <main class="wrap">
    @if(session('msg'))<div class="notice ok">{{ session('msg') }}</div>@endif
    @if(session('err') || $errors->any())<div class="notice err">{{ session('err') ?: $errors->first() }}</div>@endif

    <section class="summary" aria-label="Remittance summary">
      <div class="metric primary"><div class="label">COD Cash Not Confirmed</div><div class="value">PHP {{ number_format($unremitted['total'], 2) }}</div><div class="hint">Cash collected from delivered COD orders that still needs seller confirmation.</div></div>
      <div class="metric"><div class="label">COD Orders to Settle</div><div class="value">{{ $unremitted['count'] }}</div><div class="hint">Delivered COD orders still open for remittance.</div></div>
      <div class="metric"><div class="label">Remit Now / Retry</div><div class="value">{{ $unremitted['needs_action'] }}</div><div class="hint">Open these orders and submit cash handover or generate a new GCash QR.</div></div>
      <div class="metric"><div class="label">Waiting for Check</div><div class="value">{{ $unremitted['waiting_seller'] + $unremitted['waiting_paymongo'] }}</div><div class="hint">Waiting for seller confirmation or PayMongo GCash payment verification.</div></div>
      <div class="metric"><div class="label">Rejected by Seller</div><div class="value">{{ $unremitted['rejected'] }}</div><div class="hint">Seller rejected the remittance. Open the order and follow the seller note.</div></div>
    </section>

    <div class="toolbar">
      <h2>Assigned Deliveries</h2>
      <button class="geo" type="button" onclick="useLocation()"><i class="bi bi-crosshair"></i> Sort by my location</button>
    </div>

    <section class="list">
      @forelse($orders as $order)
        @php
          $bucket = $order->rider_bucket;
          $badgeClass = str_contains($bucket, 'Pending') ? 'b-pending' : (str_contains($bucket, 'Remit') || str_contains($bucket, 'Rejected') ? 'b-remit' : (str_contains($bucket, 'Waiting') ? 'b-wait' : 'b-active'));
        @endphp
        <article class="order">
          <div class="order-head">
            <div>
              <div class="order-id">Order #{{ $order->id }}</div>
              <div class="product">{{ $order->product_name }}</div>
            </div>
            <span class="badge {{ $badgeClass }}">{{ $bucket }}</span>
          </div>
          <div class="order-body">
            <div class="detail">
              <div><i class="bi bi-person"></i>{{ $order->guest_name ?? 'Customer' }}{{ $order->guest_phone ? ' - '.$order->guest_phone : '' }}</div>
              <div><i class="bi bi-geo-alt"></i>{{ $order->delivery_address ?? 'No delivery address' }}</div>
              <div><i class="bi bi-calendar-event"></i>{{ $order->schedule_date ?? 'No date' }} {{ $order->schedule_time ?? '' }}</div>
              @if($order->distance_km !== null)<div><i class="bi bi-signpost"></i>{{ number_format($order->distance_km, 2) }} km away</div>@endif
              @if($order->remittance_amount)<div><i class="bi bi-cash-stack"></i>Remit PHP {{ number_format((float) $order->remittance_amount, 2) }}</div>@endif
            </div>
            <div class="actions">
              @if(($order->rider_assignment_status ?? '') === 'pending')
                <form action="{{ route('rider.assignment.accept', [$order->id, $order->rider_token]) }}" method="POST">@csrf
                  <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i>Accept</button>
                </form>
                <form class="decline" action="{{ route('rider.assignment.decline', [$order->id, $order->rider_token]) }}" method="POST">@csrf
                  <textarea name="reason" maxlength="300" required placeholder="Reason if declining"></textarea>
                  <button class="btn btn-danger" type="submit"><i class="bi bi-x-circle"></i>Decline</button>
                </form>
              @else
                <a class="btn btn-primary" href="{{ route('rider.show', [$order->id, $order->rider_token]) }}"><i class="bi bi-arrow-right-circle"></i>Open</a>
                @if($order->latitude && $order->longitude)
                  <a class="btn btn-outline" href="https://www.google.com/maps/dir/?api=1&destination={{ $order->latitude }},{{ $order->longitude }}&travelmode=driving" target="_blank" rel="noopener"><i class="bi bi-map"></i>Directions</a>
                @endif
              @endif
            </div>
          </div>
        </article>
      @empty
        <div class="empty"><i class="bi bi-check2-circle" style="font-size:32px;color:var(--primary)"></i><div style="font-weight:800;margin-top:8px">No rider actions right now</div><div>New assignments and unremitted COD orders will appear here.</div></div>
      @endforelse
    </section>
  </main>

  <script>
    function useLocation() {
      if (!navigator.geolocation) return;
      navigator.geolocation.getCurrentPosition(function (pos) {
        const url = new URL(window.location.href);
        url.searchParams.set('lat', pos.coords.latitude.toFixed(7));
        url.searchParams.set('lng', pos.coords.longitude.toFixed(7));
        window.location.href = url.toString();
      });
    }
  </script>
</body>
</html>
