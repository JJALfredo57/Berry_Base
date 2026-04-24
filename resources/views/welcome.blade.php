@php
  $platform = null;
  try { $platform = \Illuminate\Support\Facades\DB::table('platform_settings')->first(); } catch (\Exception $e) {}

  $rawPrimary = $platform->platform_primary_color ?? '#7B3A0F';
  if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $rawPrimary)) $rawPrimary = '#7B3A0F';

  $adj = function(string $hex, float $f): string {
      $hex = ltrim($hex,'#');
      $r=hexdec(substr($hex,0,2)); $g=hexdec(substr($hex,2,2)); $b=hexdec(substr($hex,4,2));
      if ($f>=0){$r=(int)min(255,$r+(255-$r)*$f);$g=(int)min(255,$g+(255-$g)*$f);$b=(int)min(255,$b+(255-$b)*$f);}
      else{$f=1+$f;$r=(int)max(0,$r*$f);$g=(int)max(0,$g*$f);$b=(int)max(0,$b*$f);}
      return sprintf('#%02x%02x%02x',$r,$g,$b);
  };
  $toRgb = function(string $hex): string {
      $hex=ltrim($hex,'#');
      return hexdec(substr($hex,0,2)).','.hexdec(substr($hex,2,2)).','.hexdec(substr($hex,4,2));
  };

  $choc       = $rawPrimary;
  $chocMid    = $adj($rawPrimary, -0.25);
  $chocDark   = $adj($rawPrimary, -0.55);
  $chocXDark  = $adj($rawPrimary, -0.75);
  $chocPanel1 = $adj($rawPrimary, -0.63);
  $chocPanel2 = $adj($rawPrimary, -0.78);
  $chocPanel3 = $adj($rawPrimary, -0.88);
  $peach      = $adj($rawPrimary,  0.25);
  $peachSoft  = $adj($rawPrimary,  0.42);
  $textDark   = $adj($rawPrimary, -0.78);
  $textMid    = $adj($rawPrimary, -0.12);
  $textSoft   = $adj($rawPrimary,  0.22);
  $chocRgb    = $toRgb($choc);
  $chocMidRgb = $toRgb($chocMid);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ DB::table('site_settings')->value('site_title') ?? 'Simple Cake Shop' }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=Inter:wght@300;400;500&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { width: 100%; height: 100%; overflow: hidden; background: {{ $chocPanel2 }}; }

    /* ─── CSS Variables ─────────────────────────────────────────── */
    :root {
      --choc-xdark   : {{ $chocXDark }};
      --choc-dark    : {{ $chocDark }};
      --choc-mid     : {{ $chocMid }};
      --choc         : {{ $choc }};
      --gold         : #C8860A;
      --gold-light   : #E8A82A;
      --gold-pale    : rgba(200,134,10,0.15);
      --peach        : {{ $peach }};
      --peach-soft   : {{ $peachSoft }};
      --cream        : #FFF8F2;
      --cream-mid    : #F5E8DC;
      --cream-dark   : #E8D4C4;
      --text-dark    : {{ $textDark }};
      --text-mid     : {{ $textMid }};
      --text-soft    : {{ $textSoft }};
      --white        : #FFFFFF;
      --choc-rgb     : {{ $chocRgb }};
      --choc-mid-rgb : {{ $chocMidRgb }};
    }

    /* ─── Brand Intro Overlay ──────────────────────────────────── */
    #intro {
      position: fixed; inset: 0; z-index: 99999;
      background: var(--choc-xdark);
      display: flex; flex-direction: column;
      align-items: center; justify-content: center; gap: 20px;
      animation: introOut 0.7s cubic-bezier(0.4,0,1,1) 1.6s forwards;
    }
    .intro-logo-wrap {
      position: relative;
      opacity: 0; transform: scale(0.3) translateY(30px);
      animation: introPop 0.85s cubic-bezier(0.34,1.56,0.64,1) 0.25s forwards;
    }
    .intro-logo {
      width: clamp(80px,12vw,120px);
      height: clamp(80px,12vw,120px);
      object-fit: contain;
      filter: drop-shadow(0 0 40px rgba(200,134,10,0.55)) drop-shadow(0 0 80px rgba(200,134,10,0.2));
    }
    .intro-ring {
      position: absolute; inset: -16px;
      border-radius: 50%;
      border: 1px solid rgba(200,134,10,0.35);
      animation: introRing 1s ease 0.9s both;
    }
    .intro-ring2 {
      position: absolute; inset: -28px;
      border-radius: 50%;
      border: 1px solid rgba(200,134,10,0.15);
      animation: introRing 1.2s ease 1.05s both;
    }
    .intro-tagline {
      font-family: 'Inter', sans-serif;
      font-size: clamp(9px,1vw,11px);
      font-weight: 400;
      letter-spacing: 4px;
      text-transform: uppercase;
      color: rgba(200,134,10,0.7);
      opacity: 0;
      animation: fadeIn 0.6s ease 0.9s forwards;
    }

    /* ─── Main Splash ───────────────────────────────────────────── */
    #splash {
      position: fixed; inset: 0;
      display: flex;
      opacity: 0;
      animation: splashIn 0.7s ease 2.1s forwards;
    }

    /* ─── LEFT PANEL (Cream / Light) ────────────────────────────── */
    .left-panel {
      width: 52%;
      background: var(--cream);
      position: relative;
      display: flex; flex-direction: column; justify-content: center;
      padding: clamp(36px,6vw,88px) clamp(32px,5vw,80px);
      overflow: hidden;
    }

    /* Subtle corner blobs */
    .left-panel::before {
      content: '';
      position: absolute; top: -100px; right: -80px;
      width: 320px; height: 320px;
      background: radial-gradient(circle, rgba(200,134,10,0.07) 0%, transparent 70%);
      border-radius: 50%; pointer-events: none;
    }
    .left-panel::after {
      content: '';
      position: absolute; bottom: -80px; left: -70px;
      width: 260px; height: 260px;
      background: radial-gradient(circle, rgba(232,132,90,0.08) 0%, transparent 70%);
      border-radius: 50%; pointer-events: none;
    }

    /* Top accent line */
    .accent-line {
      position: absolute; top: 0; left: 0; right: 0; height: 3px;
      background: linear-gradient(90deg, transparent 0%, var(--gold) 30%, var(--peach-soft) 60%, var(--gold) 80%, transparent 100%);
      transform: scaleX(0); transform-origin: left;
      animation: lineReveal 0.9s ease 2.3s forwards;
    }

    /* Eyebrow label */
    .eyebrow {
      display: inline-flex; align-items: center; gap: 9px;
      font-family: 'Inter', sans-serif;
      font-size: clamp(9px,1vw,10.5px);
      font-weight: 500; letter-spacing: 3.5px; text-transform: uppercase;
      color: var(--gold);
      margin-bottom: clamp(14px,2.2vw,22px);
      opacity: 0; transform: translateY(18px);
      animation: fadeUp 0.65s ease 2.4s forwards;
    }
    .ew-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--gold); flex-shrink: 0; }

    /* Site title */
    .site-title {
      font-family: 'Playfair Display', serif;
      font-weight: 700;
      font-size: clamp(30px,5.2vw,66px);
      color: var(--text-dark);
      line-height: 1.06;
      margin-bottom: 8px;
      opacity: 0; transform: translateY(26px);
      animation: fadeUp 0.8s ease 2.55s forwards;
    }

    /* Tagline */
    .tagline {
      font-family: 'Playfair Display', serif;
      font-style: italic;
      font-size: clamp(13px,1.9vw,21px);
      color: var(--peach);
      margin-bottom: clamp(18px,2.8vw,30px);
      opacity: 0; transform: translateY(18px);
      animation: fadeUp 0.72s ease 2.7s forwards;
    }

    /* Gold ornamental divider */
    .divider {
      display: flex; align-items: center; gap: 10px;
      margin-bottom: clamp(16px,2.6vw,28px);
      opacity: 0;
      animation: fadeIn 0.6s ease 2.85s forwards;
    }
    .dv-line { height: 1px; width: clamp(36px,4.5vw,60px); background: linear-gradient(90deg, var(--gold), rgba(200,134,10,0.1)); }
    .dv-diamond { width: 6px; height: 6px; background: var(--gold); transform: rotate(45deg); border-radius: 1px; flex-shrink: 0; }
    .dv-line-r { height: 1px; width: 12px; background: rgba(200,134,10,0.3); }

    /* Description */
    .desc {
      font-family: 'DM Sans', sans-serif;
      font-size: clamp(12px,1.38vw,14.5px);
      color: var(--text-mid);
      line-height: 1.88;
      max-width: 375px;
      margin-bottom: clamp(22px,3.2vw,38px);
      opacity: 0; transform: translateY(14px);
      animation: fadeUp 0.72s ease 2.98s forwards;
    }

    /* Feature badges */
    .features {
      display: flex; flex-wrap: wrap; gap: 8px;
      margin-bottom: clamp(26px,3.8vw,44px);
      opacity: 0;
      animation: fadeIn 0.6s ease 3.1s forwards;
    }
    .fbadge {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 6px 14px;
      background: rgba(200,134,10,0.07);
      border: 1px solid rgba(200,134,10,0.2);
      border-radius: 24px;
      font-family: 'DM Sans', sans-serif;
      font-size: clamp(10px,1.05vw,12px);
      font-weight: 500;
      color: var(--text-mid);
      transition: background 0.2s, border-color 0.2s;
    }
    .fbadge:hover { background: rgba(200,134,10,0.12); border-color: rgba(200,134,10,0.35); }
    .fbadge i { color: var(--gold); font-size: 11px; }

    /* CTA Buttons */
    .btn-row {
      display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
      opacity: 0; transform: translateY(14px);
      animation: fadeUp 0.72s ease 3.24s forwards;
    }

    .btn-primary {
      display: inline-flex; align-items: center; gap: 10px;
      padding: clamp(13px,1.75vw,17px) clamp(26px,3.4vw,40px);
      background: linear-gradient(140deg, var(--choc-mid) 0%, #8B4418 60%, var(--choc) 100%);
      color: #fff; border: none; border-radius: 50px; cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      font-size: clamp(12px,1.32vw,14px);
      font-weight: 500; letter-spacing: 0.4px;
      position: relative; overflow: hidden; text-decoration: none;
      box-shadow: 0 8px 28px rgba(var(--choc-mid-rgb),0.32), 0 2px 8px rgba(var(--choc-mid-rgb),0.18), inset 0 1px 0 rgba(255,255,255,0.1);
      transition: transform 0.26s cubic-bezier(.34,1.56,.64,1), box-shadow 0.26s ease;
    }
    .btn-primary::before {
      content: ''; position: absolute; inset: 0;
      background: linear-gradient(140deg, rgba(255,255,255,0.16), transparent 60%);
      opacity: 0; transition: opacity 0.22s;
    }
    .btn-primary::after {
      content: ''; position: absolute;
      top: -50%; left: -75%; width: 50%; height: 200%;
      background: rgba(255,255,255,0.12);
      transform: skewX(-20deg);
      transition: left 0.5s ease;
    }
    .btn-primary:hover { transform: translateY(-3px) scale(1.035); box-shadow: 0 16px 42px rgba(var(--choc-mid-rgb),0.42); }
    .btn-primary:hover::before { opacity: 1; }
    .btn-primary:hover::after { left: 125%; }
    .btn-primary:active { transform: scale(0.97); }
    .btn-arrow { transition: transform 0.26s cubic-bezier(.34,1.56,.64,1); }
    .btn-primary:hover .btn-arrow { transform: translateX(5px); }

    .btn-secondary {
      display: inline-flex; align-items: center; gap: 7px;
      padding: clamp(12px,1.65vw,16px) clamp(20px,2.8vw,32px);
      background: transparent; color: var(--choc);
      border: 1.5px solid rgba(var(--choc-rgb),0.28);
      border-radius: 50px; cursor: pointer; text-decoration: none;
      font-family: 'DM Sans', sans-serif;
      font-size: clamp(12px,1.32vw,14px);
      font-weight: 500;
      transition: background 0.22s, border-color 0.22s, color 0.22s;
    }
    .btn-secondary:hover {
      background: rgba(var(--choc-rgb),0.07);
      border-color: rgba(var(--choc-rgb),0.48);
      color: var(--choc-mid);
    }

    /* Bottom hint */
    .hint {
      display: flex; align-items: center; gap: 10px;
      margin-top: clamp(18px,2.6vw,30px);
      font-family: 'Inter', sans-serif;
      font-size: 9.5px; letter-spacing: 2.2px; text-transform: uppercase;
      color: var(--text-soft); opacity: 0;
      animation: fadeIn 0.5s ease 3.6s forwards;
    }
    .hint-line { width: 22px; height: 1px; background: var(--peach); opacity: 0.5; }

    /* ─── RIGHT PANEL (Dark Chocolate) ──────────────────────────── */
    .right-panel {
      width: 48%;
      background: linear-gradient(150deg, {{ $chocPanel1 }} 0%, {{ $chocPanel2 }} 45%, {{ $chocPanel3 }} 100%);
      position: relative;
      display: flex; align-items: center; justify-content: center;
      overflow: hidden;
    }

    /* Animated gradient mesh */
    .mesh {
      position: absolute; inset: 0; pointer-events: none;
      background:
        radial-gradient(ellipse 55% 45% at 35% 25%, rgba(200,134,10,0.13) 0%, transparent 65%),
        radial-gradient(ellipse 45% 55% at 75% 80%, rgba(232,132,90,0.08) 0%, transparent 55%),
        radial-gradient(ellipse 60% 40% at 60% 55%, rgba(var(--choc-mid-rgb),0.2) 0%, transparent 60%);
      animation: meshShift 9s ease-in-out infinite alternate;
    }

    /* Subtle dot pattern */
    .dot-pattern {
      position: absolute; inset: 0;
      background-image: radial-gradient(circle, rgba(255,255,255,0.035) 1px, transparent 1px);
      background-size: 28px 28px;
      pointer-events: none;
    }

    /* Corner frame brackets */
    .bracket {
      position: absolute;
      width: 48px; height: 48px;
      border-color: rgba(200,134,10,0.22);
      border-style: solid;
      pointer-events: none;
    }
    .br-tl { top: 28px; left: 28px; border-width: 1px 0 0 1px; }
    .br-tr { top: 28px; right: 28px; border-width: 1px 1px 0 0; }
    .br-bl { bottom: 28px; left: 28px; border-width: 0 0 1px 1px; }
    .br-br { bottom: 28px; right: 28px; border-width: 0 1px 1px 0; }

    /* Center badge */
    .center-badge {
      position: absolute;
      top: 26px; left: 50%; transform: translateX(-50%);
      display: flex; align-items: center; gap: 7px;
      font-family: 'Inter', sans-serif;
      font-size: 9px; letter-spacing: 3px; text-transform: uppercase;
      color: rgba(200,134,10,0.55);
      white-space: nowrap;
    }
    .cb-line { width: 18px; height: 1px; background: rgba(200,134,10,0.3); }

    /* Logo stage */
    .logo-stage {
      position: relative; z-index: 2;
      width: min(50vw,400px); height: min(50vw,400px);
      display: flex; align-items: center; justify-content: center;
    }

    /* Pulse rings */
    .ring {
      position: absolute; border-radius: 50%;
      border: 1px solid;
      animation: ringPulse 4.2s ease-in-out infinite;
      pointer-events: none;
    }
    .r1 { width: 42%; height: 42%; border-color: rgba(200,134,10,0.40); animation-delay: 0s; }
    .r2 { width: 60%; height: 60%; border-color: rgba(200,134,10,0.22); animation-delay: 0.9s; }
    .r3 { width: 78%; height: 78%; border-color: rgba(200,134,10,0.11); animation-delay: 1.8s; }
    .r4 { width: 96%; height: 96%; border-color: rgba(200,134,10,0.05); animation-delay: 2.7s; }

    /* Spinning dashed ring */
    .spin-ring {
      position: absolute;
      width: 66%; height: 66%; border-radius: 50%;
      border: 1px dashed rgba(200,134,10,0.18);
      animation: spin 22s linear infinite;
      pointer-events: none;
    }
    .spin-ring-r {
      position: absolute;
      width: 84%; height: 84%; border-radius: 50%;
      border: 1px dashed rgba(200,134,10,0.08);
      animation: spin 34s linear infinite reverse;
      pointer-events: none;
    }

    /* Gold glow behind logo */
    .logo-glow {
      position: absolute;
      width: 50%; height: 50%; border-radius: 50%;
      background: radial-gradient(circle, rgba(200,134,10,0.28) 0%, transparent 70%);
      animation: glowPulse 3.8s ease-in-out infinite;
      pointer-events: none;
    }

    /* Logo image */
    .logo-img {
      width: 80%; height: 80%; object-fit: contain;
      position: relative; z-index: 5;
      opacity: 0; transform: scale(0.35) translateY(28px) rotate(-8deg);
      animation:
        logoReveal 1.15s cubic-bezier(0.34,1.56,0.64,1) 2.15s forwards,
        logoFloat 6.5s ease-in-out 3.3s infinite;
      filter:
        drop-shadow(0 24px 60px rgba(200,134,10,0.35))
        drop-shadow(0 8px 24px rgba(200,134,10,0.22))
        drop-shadow(0 2px 8px rgba(0,0,0,0.4));
    }

    /* Sparkle dots */
    .spark {
      position: absolute; border-radius: 50%;
      background: var(--gold-light);
      animation: sparkle var(--d,5s) ease-in-out var(--dl,0s) infinite;
      opacity: 0; pointer-events: none;
    }

    /* Progress bar */
    .pbar {
      position: absolute; bottom: 0; left: 0;
      height: 3px; width: 0%;
      background: linear-gradient(90deg, var(--choc), var(--gold), var(--peach-soft), var(--gold-light), var(--gold), var(--choc));
      background-size: 200% 100%;
      animation: loadBar 3.2s ease 2.2s forwards, shimmer 1.8s linear 2.2s infinite;
      z-index: 10;
    }

    /* ─── Exit animations ───────────────────────────────────────── */
    #splash.exiting { animation: splashExit 0.85s cubic-bezier(0.4,0,1,1) forwards; }
    #splash.exiting .left-panel { animation: slideLeft 0.7s ease 0.08s forwards; }
    #splash.exiting .right-panel { animation: slideRight 0.7s ease 0.08s forwards; }

    /* ─── Keyframes ─────────────────────────────────────────────── */
    @keyframes introOut    { to { opacity:0; pointer-events:none; } }
    @keyframes introPop    { to { opacity:1; transform:scale(1) translateY(0); } }
    @keyframes introRing   { from { transform:scale(0.3); opacity:0; } to { transform:scale(1); opacity:1; } }
    @keyframes splashIn    { to { opacity:1; } }
    @keyframes fadeUp      { to { opacity:1; transform:translateY(0); } }
    @keyframes fadeIn      { to { opacity:1; } }
    @keyframes lineReveal  { to { transform:scaleX(1); } }

    @keyframes logoReveal  { to { opacity:1; transform:scale(1) translateY(0) rotate(0deg); } }
    @keyframes logoFloat {
      0%,100% { transform: translateY(0)   rotate(0deg); }
      33%     { transform: translateY(-13px) rotate(1.8deg); }
      66%     { transform: translateY(-7px)  rotate(-1.2deg); }
    }

    @keyframes ringPulse {
      0%,100% { transform:scale(1); opacity:0.65; }
      50%     { transform:scale(1.06); opacity:1; }
    }
    @keyframes spin        { to { transform:rotate(360deg); } }
    @keyframes glowPulse {
      0%,100% { transform:scale(1); opacity:0.5; }
      50%     { transform:scale(1.35); opacity:1; }
    }
    @keyframes meshShift   { from { opacity:0.8; } to { opacity:1; } }
    @keyframes sparkle {
      0%   { opacity:0; transform:scale(0) translate(0,0); }
      35%  { opacity:1; }
      75%  { opacity:0.5; }
      100% { opacity:0; transform:scale(1.6) translate(var(--tx,20px),var(--ty,-32px)); }
    }
    @keyframes loadBar {
      0%   { width:0%; }
      55%  { width:65%; }
      82%  { width:86%; }
      100% { width:100%; }
    }
    @keyframes shimmer { 0% { background-position:0% 0%; } 100% { background-position:200% 0%; } }
    @keyframes splashExit  { to { opacity:0; transform:scale(1.03); pointer-events:none; } }
    @keyframes slideLeft   { to { opacity:0; transform:translateX(-56px); } }
    @keyframes slideRight  { to { opacity:0; transform:translateX(56px); } }

    /* ─── Responsive ────────────────────────────────────────────── */
    @media (max-width: 700px) {
      #splash { flex-direction: column; }
      .left-panel  { width:100%; order:2; padding:26px 22px 32px; justify-content:flex-start; }
      .right-panel { width:100%; order:1; flex:0 0 260px; }
      .logo-stage  { width:190px; height:190px; }
      .desc        { max-width:100%; }
      .btn-row     { flex-direction:column; align-items:flex-start; }
      .center-badge{ display:none; }
    }
    @media (min-width:701px) and (max-width:1024px) {
      .left-panel  { width:56%; }
      .right-panel { width:44%; }
      .logo-stage  { width:min(43vw,350px); height:min(43vw,350px); }
    }
  </style>
</head>
<body>

{{-- ── Brand Intro Overlay ─────────────────────────────────────── --}}
<div id="intro">
  <div class="intro-logo-wrap">
    <div class="intro-ring"></div>
    <div class="intro-ring2"></div>
    <img class="intro-logo" src="/Logocake_system.png" alt="Logo">
  </div>
  <div class="intro-tagline">Bautista, Pangasinan</div>
</div>

{{-- ── Main Splash ─────────────────────────────────────────────── --}}
<div id="splash">

  {{-- LEFT PANEL --}}
  <div class="left-panel">
    <div class="accent-line"></div>

    <div class="eyebrow">
      <div class="ew-dot"></div>
      Bautista, Pangasinan
      <div class="ew-dot"></div>
    </div>

    @php
      $settings  = DB::table('site_settings')->first();
      $siteTitle = $settings->site_title ?? 'Simple Cake Shop';
      $tagline   = $settings->tagline   ?? 'Order your dream cake';
    @endphp

    <div class="site-title">{{ $siteTitle }}</div>
    <div class="tagline">{{ $tagline }}</div>

    <div class="divider">
      <div class="dv-line"></div>
      <div class="dv-diamond"></div>
      <div class="dv-line-r"></div>
    </div>

    <p class="desc">Browse our handcrafted cakes, place your order online, and enjoy seamless pickup or delivery within our service areas &mdash; made fresh every single day.</p>

    <div class="features">
      <span class="fbadge"><i class="bi bi-cake2"></i> Custom Cakes</span>
      <span class="fbadge"><i class="bi bi-truck"></i> Delivery</span>
      <span class="fbadge"><i class="bi bi-star-fill"></i> Fresh Daily</span>
      <span class="fbadge"><i class="bi bi-phone"></i> Order Online</span>
    </div>

    <div class="btn-row">
      <button class="btn-primary" id="openBtn" onclick="enterSystem()">
        <span id="btnText">Browse Our Cakes</span>
        <svg class="btn-arrow" width="16" height="16" viewBox="0 0 16 16" fill="none">
          <path d="M3 8H13M9 4L13 8L9 12" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <a href="{{ route('login') }}" class="btn-secondary">
        <i class="bi bi-person-circle"></i>
        Sign In
      </a>
    </div>

    <div class="hint">
      <div class="hint-line"></div>
      Freshly baked with love
      <div class="hint-line"></div>
    </div>
  </div>

  {{-- RIGHT PANEL --}}
  <div class="right-panel">
    <div class="mesh"></div>
    <div class="dot-pattern"></div>

    {{-- Frame brackets --}}
    <div class="bracket br-tl"></div>
    <div class="bracket br-tr"></div>
    <div class="bracket br-bl"></div>
    <div class="bracket br-br"></div>

    {{-- Top label --}}
    <div class="center-badge">
      <div class="cb-line"></div>
      Est. in Pangasinan
      <div class="cb-line"></div>
    </div>

    {{-- Logo stage --}}
    <div class="logo-stage">
      <div class="ring r1"></div>
      <div class="ring r2"></div>
      <div class="ring r3"></div>
      <div class="ring r4"></div>
      <div class="spin-ring"></div>
      <div class="spin-ring-r"></div>
      <div class="logo-glow"></div>

      {{-- Sparkles --}}
      <div class="spark" style="width:5px;height:5px;top:12%;left:63%;--d:4.1s;--dl:0.4s;--tx:-22px;--ty:-36px"></div>
      <div class="spark" style="width:3px;height:3px;top:73%;left:76%;--d:5.2s;--dl:1.1s;--tx:16px;--ty:-24px"></div>
      <div class="spark" style="width:4px;height:4px;top:80%;left:27%;--d:3.9s;--dl:0.7s;--tx:-14px;--ty:-22px"></div>
      <div class="spark" style="width:6px;height:6px;top:18%;left:20%;--d:5.6s;--dl:1.7s;--tx:24px;--ty:26px"></div>
      <div class="spark" style="width:3px;height:3px;top:48%;left:91%;--d:4.4s;--dl:0.3s;--tx:-18px;--ty:-30px"></div>
      <div class="spark" style="width:4px;height:4px;top:6%;left:44%;--d:6.1s;--dl:2.0s;--tx:12px;--ty:-26px"></div>
      <div class="spark" style="width:3px;height:3px;top:60%;left:10%;--d:4.8s;--dl:1.4s;--tx:20px;--ty:18px"></div>

      <img class="logo-img" src="/Logocake_system.png" alt="{{ $siteTitle }}">
    </div>

    {{-- Progress bar --}}
    <div class="pbar"></div>
  </div>

</div>

<script>
(function () {
  var AUTO_MS = 14000;

  function enterSystem() {
    var btn = document.getElementById('openBtn');
    var txt = document.getElementById('btnText');
    if (btn.disabled) return;
    btn.disabled = true;
    btn.style.opacity = '0.72';
    txt.textContent = 'Loading\u2026';
    document.getElementById('splash').classList.add('exiting');
    setTimeout(function () {
      window.location.href = '{{ route("catalog") }}';
    }, 820);
  }

  document.getElementById('openBtn').addEventListener('click', enterSystem);
  setTimeout(enterSystem, AUTO_MS);
}());
</script>
</body>
</html>
