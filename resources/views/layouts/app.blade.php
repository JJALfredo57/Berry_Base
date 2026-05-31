<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
@php
  if (!isset($settings)) { $settings = \App\Helpers\CakeshopHelper::getSettings(); }
  if (!isset($bgCss))    { $bgCss    = \App\Helpers\CakeshopHelper::backgroundCss($settings); }
  $role        = session('user')['role'] ?? null;
  $uid         = session('user')['id'] ?? null;
  $username    = session('user')['username'] ?? null;
  $fullname    = session('user')['fullname'] ?? null;
  $userPhoto   = session('user')['profile_photo'] ?? null;
  $currentRoute = Route::currentRouteName();
  $sessionRole  = session('user')['role'] ?? null;

  // Base layout on SESSION ROLE — not route name
  // This prevents sellers from showing admin layout when redirected to admin routes
  $isAdmin  = in_array($sessionRole, ['admin', 'superadmin']);
  $isSeller = ($sessionRole === 'seller');

  // Load platform_settings for ALL users (needed for global theme color + superadmin branding)
  $platformBrand = null;
  try { $platformBrand = \Illuminate\Support\Facades\DB::table('platform_settings')->first(); } catch (\Exception $e) {}

  // Branding: always use platform_settings first (set by Super Admin), fallback to site_settings
  $brandTitle = $platformBrand->platform_name ?? $settings['site_title'] ?? 'Cake Shop';
  $brandLogo  = $platformBrand->platform_logo ?? $settings['logo_path'] ?? '';

  // Primary color: always from platform_settings (set by Super Admin).
  // Fallback to #7B3A0F (chocolate brown matching the platform logo).
  $rawPrimary = $platformBrand->platform_primary_color ?? '#7B3A0F';

  // Dashboard body background (set by Super Admin)
  $pbgType  = $platformBrand->platform_bg_type ?? 'color';
  $pbgColor = $platformBrand->platform_bg_color ?? '#FFF8F8';
  $pbgGradStart = $platformBrand->platform_bg_gradient_start ?? '#fff7fb';
  $pbgGradEnd   = $platformBrand->platform_bg_gradient_end   ?? '#ffe3f1';
  $pbgImage   = $platformBrand->platform_bg_image   ?? '';
  $pbgOpacity = (float)($platformBrand->platform_bg_opacity ?? 1.0);
  if ($pbgType === 'gradient') {
      $bodyBgCss = "background: linear-gradient(135deg, {$pbgGradStart} 0%, {$pbgGradEnd} 100%);";
  } elseif ($pbgType === 'image' && $pbgImage) {
      $bodyBgCss = "background: {$pbgColor};"; // color shows while image loads
  } else {
      $bodyBgCss = "background: {$pbgColor};";
  }

  // ── Seller: apply their own shop background & theme color ─────────────
  if ($isSeller && $uid) {
      try {
          $sellerShopRow = \Illuminate\Support\Facades\DB::table('shops')->where('seller_id', $uid)->first();
          if ($sellerShopRow) {
              $tc = $sellerShopRow->theme_color ?? '';
              if ($tc && preg_match('/^#[0-9A-Fa-f]{6}$/', $tc)) {
                  $rawPrimary = $tc;
              }
              $ss = \Illuminate\Support\Facades\DB::table('site_settings')->where('shop_id', $sellerShopRow->id)->first();
              if ($ss) {
                  $sBgType  = $ss->bg_type  ?? 'color';
                  $sBgColor = $ss->bg_color ?? '#f9f9f9';
                  if ($sBgType === 'gradient') {
                      $gs = $ss->gradient_start ?? '#fff7fb';
                      $ge = $ss->gradient_end   ?? '#ffe3f1';
                      $bodyBgCss = "background: linear-gradient(135deg, {$gs} 0%, {$ge} 100%);";
                      $pbgType = 'gradient'; $pbgImage = '';
                  } elseif ($sBgType === 'image' && !empty($ss->bg_image_path)) {
                      $bodyBgCss = "background: {$sBgColor};";
                      $pbgType   = 'image';
                      $pbgImage  = $ss->bg_image_path;
                      $pbgOpacity = (float)($ss->bg_image_opacity ?? 1.0);
                  } else {
                      $bodyBgCss = "background: {$sBgColor};";
                      $pbgType = 'color'; $pbgImage = '';
                  }
              }
          }
      } catch (\Throwable $e) {}
  }

  if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $rawPrimary)) $rawPrimary = '#7B3A0F';

  // Compute color variants from hex
  $hexAdjust = function(string $hex, float $factor): string {
      $hex = ltrim($hex, '#');
      $r = hexdec(substr($hex,0,2)); $g = hexdec(substr($hex,2,2)); $b = hexdec(substr($hex,4,2));
      if ($factor >= 0) { // lighten
          $r = (int)min(255, $r + (255-$r)*$factor);
          $g = (int)min(255, $g + (255-$g)*$factor);
          $b = (int)min(255, $b + (255-$b)*$factor);
      } else { // darken
          $f = 1 + $factor;
          $r = (int)max(0, $r*$f); $g = (int)max(0, $g*$f); $b = (int)max(0, $b*$f);
      }
      return sprintf('#%02x%02x%02x', $r, $g, $b);
  };
  $colorDark  = $hexAdjust($rawPrimary, -0.30);
  $colorLight = $hexAdjust($rawPrimary,  0.65);
  $colorMid   = $hexAdjust($rawPrimary,  0.40);
  $colorBg    = $hexAdjust($rawPrimary,  0.90);
  $sidebarBg  = $hexAdjust($rawPrimary, -0.72);

  // For route highlighting, still use route name
  $isAdminRoute  = str_starts_with($currentRoute ?? '', 'admin.')
                || str_starts_with($currentRoute ?? '', 'superadmin.');
  $isSellerRoute = str_starts_with($currentRoute ?? '', 'seller.');

  $unreadMessages = 0;
  if ($uid && $role) {
      try {
          if ($role === 'admin') {
              $unreadMessages = (int) \Illuminate\Support\Facades\DB::table('messages')
                  ->where('sender_role', 'customer')->where('is_read', false)->count();
          } elseif ($role === 'seller') {
              $unreadMessages = (int) \Illuminate\Support\Facades\DB::table('messages as m')
                  ->join('orders as o', 'o.id', '=', 'm.order_id')
                  ->join('shops as s', 's.id', '=', 'o.shop_id')
                  ->where('s.seller_id', $uid)
                  ->where('m.sender_role', 'customer')
                  ->where('m.is_read', false)
                  ->count();
          } else {
              $unreadMessages = (int) \Illuminate\Support\Facades\DB::table('messages as m')
                  ->join('orders as o', 'o.id', '=', 'm.order_id')
                  ->where('o.user_id', $uid)->where('m.sender_role', 'admin')->where('m.is_read', false)->count();
          }
      } catch (\Exception $e) {}
  }
  $notifCount = 0;
  $notifications = collect();
  if ($uid && $role) {
      try {
          if ($role === 'admin') {
              $notifCount    = (int) \Illuminate\Support\Facades\DB::table('notifications')->where('receiver_role','admin')->where('is_read', false)->count();
              $notifications = \Illuminate\Support\Facades\DB::table('notifications')->where('receiver_role','admin')->orderByDesc('id')->limit(10)->get();
          } else {
              $notifCount    = (int) \Illuminate\Support\Facades\DB::table('notifications')->where('receiver_role','customer')->where('receiver_user_id',$uid)->where('is_read', false)->count();
              $notifications = \Illuminate\Support\Facades\DB::table('notifications')->where('receiver_role','customer')->where('receiver_user_id',$uid)->orderByDesc('id')->limit(10)->get();
          }
      } catch (\Exception $e) {}
  }
@endphp
  <title>{{ $brandTitle }}</title>

  {{-- Favicon --}}
  @if(!empty($brandLogo))
    <link rel="icon" type="image/png" href="{{ $brandLogo }}">
    <link rel="apple-touch-icon" href="{{ $brandLogo }}">
  @else
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎂</text></svg>">
  @endif
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap');

    :root {
      --primary:        {{ $rawPrimary }};
      --primary-dark:   {{ $colorDark }};
      --primary-light:  {{ $colorLight }};
      --primary-mid:    {{ $colorMid }};
      --primary-bg:     {{ $colorBg }};
      --sidebar-bg:     {{ $sidebarBg }};
      --secondary:      #FF8A80;
      --accent:         #FFF176;
      --cream:          #FFF8F8;

      /* ── Neutrals ── */
      --gray-50:   #FAFAFA;
      --gray-100:  #F5F5F5;
      --gray-200:  #EEEEEE;
      --gray-300:  #E0E0E0;
      --gray-400:  #BDBDBD;
      --gray-500:  #9E9E9E;
      --gray-600:  #757575;
      --gray-700:  #616161;
      --gray-800:  #424242;
      --gray-900:  #212121;

      /* ── Semantic ── */
      --success:   #2E7D32;
      --warning:   #F57F17;
      --danger:    #C62828;
      --info:      #1565C0;
      --success-bg:#E8F5E9;
      --warning-bg:#FFFDE7;
      --danger-bg: #FFEBEE;
      --info-bg:   #E3F2FD;

      /* ── Layout ── */
      --sidebar-w:    256px;
      --sidebar-coll: 68px;
      --topbar-h:     60px;
      --radius-sm:    6px;
      --radius-md:    10px;
      --radius-lg:    16px;
      --radius-xl:    24px;
      --shadow-sm:    0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.06);
      --shadow-md:    0 4px 12px rgba(0,0,0,.08), 0 2px 4px rgba(0,0,0,.06);
      --shadow-lg:    0 10px 32px rgba(0,0,0,.10), 0 4px 8px rgba(0,0,0,.06);
    }

    *, *::before, *::after { box-sizing: border-box; }

    html { font-size: clamp(14px, 1.5vw, 16px); scroll-behavior: smooth; }
    body {
      font-family: 'DM Sans', system-ui, -apple-system, sans-serif;
      {{ $bodyBgCss }}
      color: var(--gray-900);
      min-height: 100vh;
      margin: 0;
      font-size: 1rem;
      -webkit-font-smoothing: antialiased;
    }

    /* ── Responsive heading sizes ── */
    h1 { font-size: clamp(1.4rem, 3.5vw, 2rem); }
    h2 { font-size: clamp(1.2rem, 3vw, 1.7rem); }
    h3 { font-size: clamp(1.1rem, 2.5vw, 1.5rem); }
    h4 { font-size: clamp(1rem, 2.2vw, 1.3rem); }
    h5 { font-size: clamp(.95rem, 2vw, 1.15rem); }
    h6 { font-size: clamp(.9rem, 1.8vw, 1.05rem); }

    /* ── Responsive text utilities ── */
    .small, small { font-size: clamp(.75rem, 1.4vw, .875rem); }
    .fw-bold { font-weight: 700; }
    .fs-5 { font-size: clamp(.95rem, 2vw, 1.1rem) !important; }
    .fs-6 { font-size: clamp(.88rem, 1.8vw, 1rem) !important; }

    /* ── Responsive button sizes ── */
    .btn { font-size: clamp(.82rem, 1.6vw, .95rem); padding: clamp(.35rem, 1vw, .5rem) clamp(.7rem, 1.8vw, 1rem); }
    .btn-sm { font-size: clamp(.75rem, 1.4vw, .85rem); padding: clamp(.25rem, .7vw, .35rem) clamp(.5rem, 1.2vw, .75rem); }
    .btn-lg { font-size: clamp(.95rem, 2vw, 1.1rem); padding: clamp(.5rem, 1.3vw, .7rem) clamp(1rem, 2.5vw, 1.4rem); }

    /* ── Responsive badge ── */
    .badge { font-size: clamp(.65rem, 1.2vw, .78rem); }
    .status-badge { font-size: clamp(.7rem, 1.3vw, .82rem); font-weight:600; padding:.3rem .75rem; border-radius:99px; white-space:nowrap; }

    /* ── Form controls ── */
    .form-control, .form-select { font-size: clamp(.83rem, 1.6vw, .95rem); }
    .form-label { font-size: clamp(.8rem, 1.5vw, .9rem); }
    .form-text  { font-size: clamp(.72rem, 1.3vw, .82rem); }

    /* ── Links ── */
    a { text-decoration: none; color: inherit; }
    a:hover { color: var(--primary); }

    /* ── Buttons ── */
    .btn {
      font-weight: 500;
      border-radius: var(--radius-md);
      letter-spacing: .01em;
      transition: all .2s cubic-bezier(.34,1.56,.64,1);
      position: relative;
      overflow: hidden;
    }
    .btn::after {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(circle, rgba(255,255,255,.32) 0%, transparent 65%);
      transform: scale(0);
      opacity: 0;
      border-radius: inherit;
      transition: transform .35s ease, opacity .35s ease;
    }
    .btn:active::after { transform: scale(2.5); opacity: 1; transition: 0s; }
    .btn-primary {
      background: var(--primary);
      border-color: var(--primary);
      color: #fff;
      box-shadow: 0 2px 8px color-mix(in srgb, var(--primary) 40%, transparent);
    }
    .btn-primary:hover, .btn-primary:focus {
      background: var(--primary-dark);
      border-color: var(--primary-dark);
      color: #fff;
      box-shadow: 0 6px 20px color-mix(in srgb, var(--primary) 45%, transparent);
      transform: translateY(-2px) scale(1.02);
    }
    .btn-primary:active { transform: translateY(0) scale(.98); box-shadow: none; }
    .btn-outline-primary { color: var(--primary); border-color: var(--primary); background: transparent; }
    .btn-outline-primary:hover { background: var(--primary); color: #fff; border-color: var(--primary); transform: translateY(-1px); box-shadow: 0 4px 12px color-mix(in srgb, var(--primary) 30%, transparent); }
    .btn-secondary { background: var(--gray-100); border-color: var(--gray-200); color: var(--gray-800); }
    .btn-secondary:hover { background: var(--gray-200); border-color: var(--gray-300); color: var(--gray-900); transform: translateY(-1px); }
    .btn-lg { padding: .7rem 1.6rem; font-size: .975rem; border-radius: var(--radius-md); }
    .btn-sm { padding: .3rem .75rem; font-size: .82rem; border-radius: var(--radius-sm); }

    /* ── Color utilities ── */
    .text-primary { color: var(--primary) !important; }
    .bg-primary   { background: var(--primary) !important; }
    .border-primary { border-color: var(--primary) !important; }

    /* ── Forms ── */
    .form-control, .form-select {
      border: 1.5px solid var(--gray-200);
      border-radius: var(--radius-md);
      padding: .6rem .875rem;
      font-family: inherit;
      font-size: .9rem;
      background: #fff;
      transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
    }
    .form-control:focus, .form-select:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3.5px color-mix(in srgb, var(--primary) 15%, transparent);
      outline: none;
      transform: translateY(-1px);
    }
    .form-control.is-invalid { border-color: var(--danger); }
    .form-control.is-invalid:focus { box-shadow: 0 0 0 3px rgba(198,40,40,.12); }
    .form-label { font-size: .85rem; font-weight: 600; color: var(--gray-700); margin-bottom: .35rem; }
    .form-text  { font-size: .78rem; color: var(--gray-500); margin-top: .25rem; }
    .input-group-text { background: var(--gray-50); border: 1.5px solid var(--gray-200); color: var(--gray-500); border-radius: var(--radius-md); }
    .input-group > .form-control { border-radius: 0 var(--radius-md) var(--radius-md) 0 !important; }
    .input-group > .input-group-text:first-child { border-radius: var(--radius-md) 0 0 var(--radius-md) !important; border-right: 0; }
    .invalid-feedback { font-size: .78rem; color: var(--danger); }

    /* ── Cards ── */
    .card {
      border: 1.5px solid var(--gray-100);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-sm);
      background: #fff;
      transition: box-shadow .22s ease, transform .22s ease, border-color .22s ease;
    }
    .card:hover {
      box-shadow: var(--shadow-md);
      transform: translateY(-2px);
      border-color: var(--gray-200);
    }
    .card-header { background: transparent; border-bottom: 1.5px solid var(--gray-100); padding: 1rem 1.25rem; font-weight: 600; }
    .card-body { padding: 1.25rem; }

    /* ── Alerts ── */
    .alert { border-radius: var(--radius-md); border: 0; font-size: .875rem; }
    .alert-success { background: var(--success-bg); color: var(--success); }
    .alert-danger  { background: var(--danger-bg);  color: var(--danger); }
    .alert-warning { background: var(--warning-bg); color: var(--warning); }
    .alert-info    { background: var(--info-bg);    color: var(--info); }

    /* ── Status badges ── */
    .status-badge { font-size: .75rem; font-weight: 600; padding: .3rem .8rem; border-radius: 99px; white-space: nowrap; display: inline-block; }
    .status-Pending          { background:#fff3cd; color:#856404; }
    .status-Pending-Review   { background:#fef9c3; color:#a16207; }
    .status-Confirmed        { background:#cff4fc; color:#055160; }
    .status-Preparing        { background:#d1ecf1; color:#0c5460; }
    .status-Out-for-Delivery { background:#d4edda; color:#155724; }
    .status-Delivered        { background:#d4edda; color:#155724; }
    .status-Cancelled        { background:#f8d7da; color:#721c24; }
    .status-Paid             { background:#d4edda; color:#155724; }
    .status-Unpaid           { background:#fff3cd; color:#856404; }
    .img-cover { width:100%; object-fit:cover; border-radius:1rem 1rem 0 0; }
    .nav-avatar { width:32px;height:32px;border-radius:50%;object-fit:cover;border:2px solid var(--primary);flex-shrink:0; }
    .nav-avatar-fallback { width:32px;height:32px;border-radius:50%;background:var(--primary);color:#fff;font-size:.8rem;font-weight:700;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0; }

    /* ── Pagination ── */
    .cs-pagination { display:flex;gap:4px;align-items:center;flex-wrap:wrap; }
    .cs-page-btn { min-width:34px;height:34px;padding:0 8px;border-radius:8px;border:1.5px solid #e5e7eb;background:#fff;color:#555;font-size:.83rem;font-weight:500;cursor:pointer;transition:all .15s;display:inline-flex;align-items:center;justify-content:center; }
    .cs-page-btn:hover { border-color:var(--primary);color:var(--primary); }
    .cs-page-btn.active { background:var(--primary);border-color:var(--primary);color:#fff;box-shadow:0 2px 8px rgba(0,0,0,.15); }
    .cs-page-btn:disabled { opacity:.4;cursor:not-allowed; }
    .cs-page-btn.dots { border-color:transparent;background:transparent;cursor:default; }

    /* ── Toast ── */
    #csToastContainer { position:fixed;top:18px;left:50%;transform:translateX(-50%);z-index:9999;display:flex;flex-direction:column;align-items:center;gap:10px;width:min(92vw,520px);pointer-events:none; }
    .cs-toast { width:100%;padding:14px 16px;border-radius:18px;background:rgba(255,255,255,.96);backdrop-filter:blur(16px);box-shadow:0 18px 48px rgba(15,23,42,.16),0 4px 14px rgba(15,23,42,.08);display:flex;align-items:flex-start;gap:12px;font-size:.84rem;animation:toastIn .42s cubic-bezier(.34,1.56,.64,1);border:1px solid rgba(255,255,255,.75);pointer-events:auto;position:relative;overflow:hidden; }
    .cs-toast::before { content:'';position:absolute;left:0;top:0;bottom:0;width:4px;background:#3b82f6; }
    .cs-toast.hiding { animation:toastOut .3s ease forwards; }
    @@keyframes toastIn  { from{opacity:0;transform:translateY(-18px) scale(.96)} to{opacity:1;transform:translateY(0) scale(1)} }
    @@keyframes toastOut { from{opacity:1;transform:translateY(0) scale(1)} to{opacity:0;transform:translateY(-12px) scale(.96)} }
    .cs-toast-success::before { background:#22c55e; }
    .cs-toast-error::before { background:#ef4444; }
    .cs-toast-warning::before { background:#f59e0b; }
    .cs-toast-info::before { background:#3b82f6; }
    .cs-toast-success .cs-toast-icon { color:#22c55e; }
    .cs-toast-error   .cs-toast-icon { color:#ef4444; }
    .cs-toast-warning .cs-toast-icon { color:#f59e0b; }
    .cs-toast-info    .cs-toast-icon { color:#3b82f6; }
    .modal-backdrop { display:none !important; opacity:0 !important; pointer-events:none !important; }

    /* ═══════════════════════════════════════════════
       GLOBAL ANIMATION & COMPONENT SYSTEM
    ═══════════════════════════════════════════════ */

    /* ── Keyframes ── */
    @@keyframes csSlideUp   { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }
    @@keyframes csFadeIn    { from{opacity:0} to{opacity:1} }
    @@keyframes csScaleIn   { from{opacity:0;transform:scale(.95)} to{opacity:1;transform:scale(1)} }
    @@keyframes csShimmer   { 0%{background-position:-200% center} 100%{background-position:200% center} }
    @@keyframes csPendingPulse { 0%,100%{box-shadow:0 0 0 0 rgba(234,179,8,.35)} 50%{box-shadow:0 0 0 6px rgba(234,179,8,0)} }
    @@keyframes csIconBounce { 0%,100%{transform:scale(1)} 40%{transform:scale(1.18) rotate(-5deg)} 70%{transform:scale(.95)} }
    @@keyframes csPageIn    { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:none} }
    @@keyframes csBarGlow   { 0%,100%{opacity:.55} 50%{opacity:1} }
    @@keyframes csSpin      { to{transform:rotate(360deg)} }

    /* Smart global loading bar */
    #csTopProgress {
      position:fixed;top:0;left:0;right:0;height:3px;z-index:100000;
      pointer-events:none;opacity:0;transform:translateY(-3px);
      transition:opacity .18s ease,transform .18s ease;
    }
    #csTopProgress.cs-loading { opacity:1;transform:translateY(0); }
    #csTopProgressBar {
      height:100%;width:0;
      background:linear-gradient(90deg,var(--primary-dark),var(--primary),var(--secondary),var(--primary));
      background-size:180% 100%;
      box-shadow:0 0 18px color-mix(in srgb,var(--primary) 55%,transparent);
      transition:width .28s ease;
      animation:csShimmer 1.1s linear infinite, csBarGlow 1.4s ease-in-out infinite;
    }
    #csTopProgress.cs-done #csTopProgressBar { width:100% !important; }
    .cs-btn-loading {
      pointer-events:none !important;
      opacity:.78;
    }
    .cs-btn-loading .cs-btn-spinner {
      width:.9rem;height:.9rem;border-radius:50%;
      border:2px solid currentColor;border-right-color:transparent;
      display:inline-block;vertical-align:-.12em;
      animation:csSpin .7s linear infinite;
    }

    /* ── Page entrance ── */
    .admin-page { animation: csPageIn .38s ease both; }
    .customer-wrap { animation: csPageIn .35s ease both; }

    /* ── Staggered children ── */
    .cs-stagger > * { opacity:0; animation:csSlideUp .4s ease forwards; }
    .cs-stagger > *:nth-child(1){animation-delay:.04s}
    .cs-stagger > *:nth-child(2){animation-delay:.09s}
    .cs-stagger > *:nth-child(3){animation-delay:.14s}
    .cs-stagger > *:nth-child(4){animation-delay:.19s}
    .cs-stagger > *:nth-child(5){animation-delay:.24s}
    .cs-stagger > *:nth-child(6){animation-delay:.29s}
    .cs-stagger > *:nth-child(7){animation-delay:.34s}
    .cs-stagger > *:nth-child(8){animation-delay:.39s}
    .cs-stagger > *:nth-child(9){animation-delay:.44s}
    .cs-stagger > *:nth-child(n+10){animation-delay:.48s}

    /* ── Stat Card component ── */
    .cs-stat-card {
      background:#fff; border:1.5px solid var(--gray-100);
      border-radius:var(--radius-lg); padding:1.25rem 1.5rem;
      display:flex; align-items:flex-start; gap:1rem;
      transition:box-shadow .25s ease, transform .25s ease, border-color .25s ease;
      position:relative; overflow:hidden; cursor:default;
    }
    .cs-stat-card::before {
      content:''; position:absolute; inset:0;
      background:linear-gradient(135deg, rgba(255,255,255,.7) 0%, transparent 60%);
      pointer-events:none;
    }
    .cs-stat-card:hover {
      box-shadow:0 8px 28px rgba(0,0,0,.10), 0 2px 8px rgba(0,0,0,.06);
      transform:translateY(-4px);
      border-color:color-mix(in srgb, var(--primary) 25%, transparent);
    }
    .cs-stat-icon {
      width:48px; height:48px; border-radius:var(--radius-md);
      display:flex; align-items:center; justify-content:center;
      font-size:1.2rem; flex-shrink:0;
      transition:transform .35s cubic-bezier(.34,1.56,.64,1);
    }
    .cs-stat-card:hover .cs-stat-icon { animation:csIconBounce .5s ease; }
    .cs-stat-body { min-width:0; flex:1; }
    .min-width-0 { min-width:0; }
    .cs-stat-num {
      font-size:clamp(1.4rem,3vw,1.8rem); font-weight:700; line-height:1;
      color:var(--gray-900); letter-spacing:-.02em;
    }
    .cs-stat-label { font-size:.73rem; color:var(--gray-500); margin-top:.3rem; font-weight:500; text-transform:uppercase; letter-spacing:.04em; }
    .cs-stat-trend { font-size:.72rem; font-weight:600; margin-top:.5rem; display:flex; align-items:center; gap:3px; }
    .cs-stat-trend.up   { color:#16a34a; }
    .cs-stat-trend.down { color:#dc2626; }
    .cs-stat-trend.flat { color:var(--gray-400); }

    /* ── Filter pills ── */
    .cs-filter-wrap { display:flex; gap:6px; flex-wrap:wrap; }
    .cs-filter-pill {
      display:inline-flex; align-items:center; gap:5px;
      padding:6px 14px; border-radius:99px;
      font-size:.78rem; font-weight:500; cursor:pointer;
      border:1.5px solid var(--gray-200); background:#fff; color:var(--gray-600);
      transition:all .2s cubic-bezier(.34,1.56,.64,1);
      white-space:nowrap; user-select:none;
    }
    .cs-filter-pill:hover { border-color:var(--primary); color:var(--primary); background:var(--primary-bg); transform:translateY(-1px); }
    .cs-filter-pill.active { background:var(--primary); border-color:var(--primary); color:#fff; box-shadow:0 3px 10px color-mix(in srgb,var(--primary) 35%,transparent); transform:translateY(-1px); }
    .cs-filter-pill .cs-count { background:rgba(255,255,255,.25); padding:1px 6px; border-radius:99px; font-size:.68rem; }
    .cs-filter-pill:not(.active) .cs-count { background:var(--gray-100); color:var(--gray-500); }

    /* ── Table refinements ── */
    .table { border-collapse:separate; border-spacing:0; }
    .table thead th { background:var(--gray-50); font-size:.76rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--gray-500); padding:.75rem 1rem; border-bottom:1.5px solid var(--gray-100); white-space:nowrap; }
    .table tbody tr { transition:background .15s ease; }
    .table tbody td { padding:.75rem 1rem; border-bottom:1px solid var(--gray-100); vertical-align:middle; font-size:.875rem; }
    .table tbody tr:hover td { background:var(--primary-bg); }
    .table tbody tr:last-child td { border-bottom:0; }

    /* ── Empty state ── */
    .cs-empty { text-align:center; padding:3.5rem 2rem; }
    .cs-empty-icon { font-size:3rem; opacity:.35; display:block; margin-bottom:.875rem; }
    .cs-empty-title { font-size:.95rem; font-weight:600; color:var(--gray-600); margin-bottom:.3rem; }
    .cs-empty-sub { font-size:.82rem; color:var(--gray-400); }

    /* ── Page header ── */
    .cs-page-header {
      display:flex; align-items:center; justify-content:space-between;
      flex-wrap:wrap; gap:12px; margin-bottom:1.5rem;
    }
    .cs-page-title { font-size:clamp(1.1rem,2.5vw,1.35rem); font-weight:700; color:var(--gray-900); margin:0 0 2px; }
    .cs-page-sub { font-size:.8rem; color:var(--gray-500); margin:0; }
    .cs-page-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }

    /* ── Skeleton loader ── */
    .cs-skeleton {
      background:linear-gradient(90deg, var(--gray-100) 25%, var(--gray-50) 50%, var(--gray-100) 75%);
      background-size:200% 100%;
      animation:csShimmer 1.5s ease-in-out infinite;
      border-radius:var(--radius-md);
    }
    .cs-skeleton-line { height:.82rem; margin:.38rem 0; }
    .cs-skeleton-title { height:1.1rem; width:65%; margin:.2rem 0 .75rem; }
    .cs-skeleton-avatar { width:44px;height:44px;border-radius:50%;flex-shrink:0; }
    .cs-skeleton-thumb { width:100%;aspect-ratio:4/3;border-radius:var(--radius-md); }
    .cs-skeleton-card {
      background:#fff;border:1.5px solid var(--gray-100);
      border-radius:var(--radius-lg);padding:1rem;box-shadow:var(--shadow-sm);
    }
    .cs-loading-surface {
      position:relative;
      min-height:92px;
    }
    .cs-loading-surface::after {
      content:'';
      position:absolute;
      inset:0;
      z-index:24;
      border-radius:inherit;
      pointer-events:none;
      background:linear-gradient(180deg,rgba(255,255,255,.74),rgba(255,255,255,.9));
      backdrop-filter:blur(2px);
      -webkit-backdrop-filter:blur(2px);
      opacity:0;
      transition:opacity .16s ease;
    }
    .cs-loading-surface.cs-surface-loading::after { opacity:1; }
    .cs-loading-panel {
      position:absolute;
      z-index:25;
      left:50%;
      top:50%;
      width:min(420px,calc(100% - 28px));
      transform:translate(-50%,-50%) scale(.98);
      opacity:0;
      pointer-events:none;
      transition:opacity .16s ease,transform .16s ease;
    }
    .cs-surface-loading > .cs-loading-panel {
      opacity:1;
      transform:translate(-50%,-50%) scale(1);
    }
    .cs-loading-panel-inner {
      background:rgba(255,255,255,.96);
      border:1.5px solid var(--gray-100);
      border-radius:var(--radius-lg);
      box-shadow:0 20px 50px rgba(15,23,42,.14);
      padding:1rem;
    }
    .cs-loading-head {
      display:flex;
      align-items:center;
      gap:.75rem;
      margin-bottom:.8rem;
    }
    .cs-loading-orbit {
      width:34px;
      height:34px;
      border-radius:50%;
      border:3px solid color-mix(in srgb,var(--primary) 22%,#fff);
      border-top-color:var(--primary);
      box-shadow:0 0 0 5px color-mix(in srgb,var(--primary) 8%,transparent);
      animation:csSpin .75s linear infinite;
      flex-shrink:0;
    }
    .cs-loading-title {
      font-size:.86rem;
      font-weight:800;
      color:var(--gray-800);
      line-height:1.2;
    }
    .cs-loading-sub {
      font-size:.74rem;
      color:var(--gray-500);
      margin-top:.12rem;
    }
    .cs-loading-lines {
      display:grid;
      gap:.45rem;
    }
    .cs-loading-lines .cs-skeleton-line { margin:0; }
    .cs-page-busy .admin-page,
    .cs-page-busy .customer-wrap {
      cursor:progress;
    }
    .cs-img-loading {
      opacity:.45;
      filter:blur(8px) saturate(.9);
      transform:scale(1.01);
      transition:opacity .35s ease,filter .35s ease,transform .35s ease;
      background:linear-gradient(90deg,var(--gray-100),var(--gray-50),var(--gray-100));
      background-size:200% 100%;
      animation:csShimmer 1.5s ease-in-out infinite;
    }
    .cs-img-loaded {
      opacity:1;
      filter:none;
      transform:none;
      animation:none;
    }
    @@media (prefers-reduced-motion: reduce) {
      #csTopProgressBar,
      .cs-btn-loading .cs-btn-spinner,
      .cs-skeleton,
      .cs-loading-orbit,
      .cs-img-loading,
      .status-Pending {
        animation:none !important;
      }
      .admin-page,
      .customer-wrap {
        animation:none !important;
      }
    }

    /* ── Sidebar enhancements ── */
    .sb-link {
      transition:background .18s ease, color .18s ease, transform .18s ease;
      position:relative;
    }
    .sb-link::before {
      content:''; position:absolute;
      left:0; top:50%; transform:translateY(-50%);
      width:3px; height:0;
      background:rgba(255,255,255,.9);
      border-radius:0 3px 3px 0;
      transition:height .22s cubic-bezier(.34,1.56,.64,1);
    }
    .sb-link:hover::before  { height:45%; }
    .sb-link.active::before { height:68%; }
    .sb-link i { transition:transform .25s cubic-bezier(.34,1.56,.64,1); }
    .sb-link:hover i  { transform:scale(1.18) translateX(1px); }
    .sb-link.active i { transform:scale(1.1); }

    /* ── Topbar scroll shadow ── */
    #adminTopbar, #sellerTopbar { transition:box-shadow .25s ease; }
    #adminTopbar.cs-scrolled, #sellerTopbar.cs-scrolled { box-shadow:0 4px 24px rgba(0,0,0,.08); border-bottom-color:transparent; }

    /* ── Status badge pulse for Pending ── */
    .status-Pending { animation:csPendingPulse 2.2s ease-in-out infinite; }

    /* ── Modal enhancements ── */
    .modal.fade .modal-dialog { transform:translateY(-24px) scale(.96); transition:transform .3s cubic-bezier(.34,1.56,.64,1); }
    .modal.show .modal-dialog { transform:translateY(0) scale(1); }
    .modal-content { border:0; border-radius:var(--radius-xl); box-shadow:0 24px 64px rgba(0,0,0,.18); overflow:hidden; }
    .modal-header { border-bottom:1.5px solid var(--gray-100); padding:1.25rem 1.5rem; }
    .modal-footer { border-top:1.5px solid var(--gray-100); padding:1rem 1.5rem; background:var(--gray-50); }

    /* ── Utility animation classes ── */
    .cs-fade-up  { animation:csSlideUp .4s ease both; }
    .cs-fade-in  { animation:csFadeIn .35s ease both; }
    .cs-scale-in { animation:csScaleIn .3s ease both; }

    /* ═══════════ ADMIN SIDEBAR LAYOUT ═══════════ */
    @if($isAdmin || $isSeller)
    body { background:{{ ($settings['bg_type'] ?? 'gradient') === 'image' && !empty($settings['bg_image_path']) ? 'transparent' : '#f0f2f8' }}; }

    #adminSidebar {
      position:fixed; top:0; left:0;
      width:var(--sidebar-w); height:100vh;
      background:var(--sidebar-bg); color:#c8cfe8;
      display:flex; flex-direction:column;
      z-index:1040;
      transition:width .25s cubic-bezier(.4,0,.2,1), transform .25s cubic-bezier(.4,0,.2,1);
      overflow:hidden;
      box-shadow:4px 0 24px rgba(0,0,0,.18);
    }
    #adminSidebar.collapsed { width:var(--sidebar-coll); }

    .sb-brand { display:flex;align-items:center;gap:12px;padding:0 16px;height:var(--topbar-h);border-bottom:1px solid rgba(255,255,255,.06);flex-shrink:0;overflow:hidden;white-space:nowrap; }
    .sb-brand-icon { width:34px;height:34px;flex-shrink:0;border-radius:9px;background:var(--primary);display:flex;align-items:center;justify-content:center; }
    .sb-brand-text { font-weight:700;font-size:.9rem;color:#fff;overflow:hidden;text-overflow:ellipsis; }
    .sb-brand-sub  { font-size:.62rem;color:rgba(255,255,255,.35); }

    .sb-nav { flex:1;overflow-y:auto;padding:8px;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.1) transparent; }
    .sb-nav::-webkit-scrollbar { width:3px; }
    .sb-nav::-webkit-scrollbar-thumb { background:rgba(255,255,255,.1);border-radius:2px; }

    .sb-label { font-size:.6rem;font-weight:700;letter-spacing:.1em;color:rgba(255,255,255,.28);text-transform:uppercase;padding:12px 10px 3px;white-space:nowrap;overflow:hidden;transition:opacity .2s; }
    #adminSidebar.collapsed .sb-label { opacity:0; }

    .sb-link { display:flex;align-items:center;gap:11px;padding:9px 11px;border-radius:9px;color:rgba(255,255,255,.58);font-size:.83rem;font-weight:500;cursor:pointer;transition:all .15s;white-space:nowrap;overflow:hidden;position:relative;border:none;background:none;width:100%;text-align:left;text-decoration:none; }
    .sb-link:hover { background:rgba(255,255,255,.07);color:#fff; }
    .sb-link.active { background:var(--primary);color:#fff;box-shadow:0 3px 12px rgba(0,0,0,.2); }
    .sb-link i { font-size:1.05rem;flex-shrink:0;width:20px;text-align:center; }
    .sb-link-text { overflow:hidden;text-overflow:ellipsis;transition:opacity .2s; }
    #adminSidebar.collapsed .sb-link-text { opacity:0; }
    .sb-badge { margin-left:auto;flex-shrink:0;background:#ef4444;color:#fff;font-size:.6rem;font-weight:700;padding:1px 6px;border-radius:99px;min-width:18px;text-align:center;transition:opacity .2s; }
    #adminSidebar.collapsed .sb-badge { opacity:0; }

    .sb-user { border-top:1px solid rgba(255,255,255,.06);padding:12px;display:flex;align-items:center;gap:10px;flex-shrink:0;overflow:hidden;white-space:nowrap; }
    .sb-user-info { overflow:hidden;transition:opacity .2s; }
    #adminSidebar.collapsed .sb-user-info { opacity:0; }
    .sb-user-name { font-size:.79rem;font-weight:600;color:#fff;overflow:hidden;text-overflow:ellipsis; }
    .sb-user-role { font-size:.63rem;color:rgba(255,255,255,.35); }

    #adminMain { margin-left:var(--sidebar-w);transition:margin-left .25s cubic-bezier(.4,0,.2,1);min-height:100vh;display:flex;flex-direction:column; }
    #adminMain.expanded { margin-left:var(--sidebar-coll); }

    #adminTopbar { position:sticky;top:0;z-index:1030;height:var(--topbar-h);background:rgba(255,255,255,.94);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border-bottom:1px solid rgba(0,0,0,.06);display:flex;align-items:center;padding:0 20px;gap:12px; }
    .tb-toggle { width:36px;height:36px;border-radius:9px;border:none;background:#f0f2f8;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .15s;flex-shrink:0;color:#555; }
    .tb-toggle:hover { background:var(--primary-light);color:var(--primary); }
    .tb-bread { flex:1;font-size:.84rem;color:#666;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
    .tb-bread strong { color:var(--gray-900); }
    .tb-btn { width:36px;height:36px;border-radius:9px;border:none;background:transparent;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#555;transition:all .15s;position:relative; }
    .tb-btn:hover { background:var(--primary-light);color:var(--primary); }

    .admin-page { flex:1; padding: clamp(14px, 2.5vw, 28px); width:100%; }
    .page-header { margin-bottom: clamp(16px, 2vw, 24px); }
    .page-title { font-size: clamp(1.1rem, 2.5vw, 1.4rem); font-weight:700; color:var(--gray-900); margin:0 0 2px; }
    .page-subtitle { font-size: clamp(.78rem, 1.4vw, .88rem); color:#888; margin:0; }

    #sbOverlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1039; }

    @@media(max-width:767px) {
      #adminSidebar { transform:translateX(-100%);width:var(--sidebar-w) !important; }
      #adminSidebar.mobile-open { transform:translateX(0); }
      #adminMain { margin-left:0 !important; }
      #sbOverlay.active { display:block; }
      .admin-page { padding: 12px; }
    }
    @endif

    /* ═══════════ CUSTOMER LAYOUT ═══════════ */
    @if(!$isAdmin && !$isSeller)
    body { {!! $bgCss ?? '' !!} }
    .navbar-glass { background:rgba(255,255,255,.9);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);border-bottom:1px solid rgba(0,0,0,.07); }
    .nav-link { color:#444 !important;font-weight:500;padding:.5rem .85rem !important;border-radius:.6rem;transition:.15s; }
    .nav-link:hover,.nav-link.active { background:var(--primary-light);color:var(--primary) !important; }
    .customer-wrap { padding-top:72px; width:100%; }
    /* Remove Bootstrap container max-width restrictions — full width */
    .container, .container-fluid, .container-lg, .container-xl, .container-xxl {
      width: 100%; max-width: 100%; padding-left: clamp(12px, 3vw, 32px); padding-right: clamp(12px, 3vw, 32px);
    }
    @@media(max-width:767px) {
      .card-body { padding: clamp(.75rem, 3vw, 1rem); }
      .customer-wrap { padding-top:66px; }
    }
    @endif
    {{-- ── Seller Sidebar CSS ── --}}
    @if($isSeller)
    body { background: var(--gray-50,#FAFAFA); }
    #sellerSidebar {
      position:fixed;top:0;left:0;bottom:0;z-index:1040;
      width:var(--sidebar-w);background:var(--sidebar-bg);color:#fff;
      display:flex;flex-direction:column;overflow:hidden;
      transition:width .25s cubic-bezier(.4,0,.2,1);
    }
    #sellerSidebar.collapsed { width:var(--sidebar-coll); }
    #sellerMain {
      margin-left:var(--sidebar-w);
      transition:margin-left .25s cubic-bezier(.4,0,.2,1);
      min-height:100vh;display:flex;flex-direction:column;
    }
    #sellerMain.expanded { margin-left:var(--sidebar-coll); }
    #sellerTopbar {
      position:sticky;top:0;z-index:1030;height:var(--topbar-h);
      margin-left:var(--sidebar-w);
      background:rgba(255,255,255,.94);backdrop-filter:blur(16px);
      border-bottom:1px solid rgba(0,0,0,.06);
      display:flex;align-items:center;padding:0 20px;gap:12px;
      transition:margin-left .25s cubic-bezier(.4,0,.2,1);
    }
    #sellerSidebar.collapsed ~ #sellerTopbar { margin-left:var(--sidebar-coll); }
    #sellerSidebar.collapsed .sb-label,
    #sellerSidebar.collapsed .sb-link-text,
    #sellerSidebar.collapsed .sb-badge,
    #sellerSidebar.collapsed .sb-brand-text,
    #sellerSidebar.collapsed .sb-brand-sub,
    #sellerSidebar.collapsed .sb-user-info { opacity:0;pointer-events:none; }
    #sellerOverlay { display:none;position:fixed;inset:0;z-index:1039;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px); }
    @@media(max-width:767px) {
      #sellerSidebar { transform:translateX(-100%);width:var(--sidebar-w) !important; }
      #sellerSidebar.mobile-open { transform:translateX(0); }
      #sellerTopbar { margin-left:0 !important; }
      #sellerMain { margin-left:0 !important; }
      #sellerOverlay.active { display:block; }
    }
    @endif

    /* ═══════════════════════════════════════
       GLOBAL RESPONSIVE FIXES
    ═══════════════════════════════════════ */

    /* Prevent horizontal scroll on all screens */
    html, body { width:100%; max-width:100%; overflow-x:hidden; }
    body { min-width:0; }
    #adminMain, #sellerMain, .admin-page, .customer-wrap, main, section, .card, .modal-content {
      min-width:0;
      max-width:100%;
    }

    /* Make all images and videos responsive by default */
    img, picture, video, canvas, svg, iframe { max-width:100%; height:auto; }
    iframe { display:block; border:0; }
    img { object-fit:contain; }
    input, select, textarea, button { max-width:100%; }
    textarea { resize:vertical; }

    /* Table horizontal scroll wrapper */
    .table-wrap,
    .table-responsive,
    .cs-responsive-table {
      width:100%;
      max-width:100%;
      overflow-x:auto;
      overflow-y:hidden;
      -webkit-overflow-scrolling:touch;
      scrollbar-width:thin;
    }
    .table-wrap .table,
    .table-responsive .table,
    .cs-responsive-table .table {
      min-width:min(620px, max-content);
      margin-bottom:0;
    }
    .table th,
    .table td {
      max-width:min(48vw, 320px);
      overflow-wrap:anywhere;
    }

    /* Common overflow guards for dynamic content */
    :where(.admin-page,.customer-wrap) :where(.row, [class*="col-"], .d-flex, .input-group, .btn-group, form, fieldset) {
      min-width:0;
    }
    :where(.admin-page,.customer-wrap) :where(p, li, td, th, span, div, a, label, small) {
      overflow-wrap:anywhere;
    }
    :where(.admin-page,.customer-wrap) :where(pre, code, .text-monospace) {
      max-width:100%;
      overflow-x:auto;
      white-space:pre-wrap;
    }
    :where(.admin-page,.customer-wrap) [style*="min-width"] {
      max-width:100%;
    }
    :where(.admin-page,.customer-wrap) [style*="width:"] {
      max-width:100%;
    }
    .dropdown-menu {
      max-width:calc(100vw - 1rem);
      overflow-wrap:anywhere;
    }
    .modal-body,
    .modal-header,
    .modal-footer,
    .card-header,
    .card-body {
      min-width:0;
      overflow-wrap:anywhere;
    }
    .modal-body {
      max-height:calc(100dvh - 8rem);
      overflow-y:auto;
    }
    .list-group-item,
    .alert {
      min-width:0;
      overflow-wrap:anywhere;
    }

    /* Flex filter/search bars: wrap on small screens */
    .cs-filter-bar {
      display:flex; flex-wrap:wrap; gap:.5rem; align-items:center;
    }
    .cs-filter-bar .form-control,
    .cs-filter-bar .form-select {
      flex:1; min-width:0; max-width:100%;
    }

    @@media(max-width:575px) {
      /* Inputs & selects: never force a min-width that causes overflow */
      .form-control, .form-select, textarea, input, select {
        min-width:0 !important;
        max-width:100% !important;
      }
      .form-control, .form-select, textarea, input, select {
        font-size:16px !important;
      }

      /* Cards: reduce padding on tiny screens */
      .card-body { padding:.875rem !important; }
      .modal-body { padding:1rem !important; }
      .modal-footer { padding:.875rem 1rem !important; }

      /* Page headers: stack vertically */
      .cs-page-header { flex-direction:column; align-items:flex-start !important; }
      .cs-page-actions { width:100%; }

      /* Stat cards: make them full width on tiny phones */
      .cs-stat-card { padding:1rem !important; }

      /* Buttons in button groups: wrap */
      .btn-group-wrap { display:flex; flex-wrap:wrap; gap:.4rem; }
      .btn-toolbar, .btn-group {
        flex-wrap:wrap;
        max-width:100%;
      }
      .btn-group > .btn {
        flex:0 1 auto;
      }
      .pagination,
      .cs-pagination {
        justify-content:center;
      }

      /* Dialog box: full-width on small phones */
      #csDlgBox { padding:1.25rem !important; border-radius:1rem !important; }
    }

    @@media(max-width:767px) {
      /* Bigger touch targets for topbar buttons */
      .tb-toggle, .tb-btn { width:44px !important; height:44px !important; }
      /* Admin/seller page padding */
      .admin-page { padding:10px 10px 24px !important; }

      /* Topbar breadcrumb: truncate so actions have space */
      .tb-bread { font-size:.78rem !important; }

      /* Seller topbar title */
      .tb-title { font-size:.85rem !important; font-weight:600; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

      /* Modal full-width on mobile */
      .modal-dialog { margin:.5rem !important; max-width:calc(100vw - 1rem) !important; }
      .modal-dialog-centered { min-height:calc(100% - 1rem) !important; }

      /* Stack flex rows on mobile */
      .mobile-stack { flex-direction:column !important; align-items:stretch !important; }
      .mobile-stack > * { width:100% !important; min-width:0 !important; max-width:100% !important; }
      :where(.admin-page,.customer-wrap) > .d-flex,
      :where(.admin-page,.customer-wrap) .card-header.d-flex,
      :where(.admin-page,.customer-wrap) .card-body > .d-flex,
      :where(.admin-page,.customer-wrap) form.d-flex {
        flex-wrap:wrap !important;
      }
      :where(.admin-page,.customer-wrap) .justify-content-between {
        gap:.65rem;
      }
      :where(.admin-page,.customer-wrap) .card-header.justify-content-between,
      :where(.admin-page,.customer-wrap) .card-body.justify-content-between {
        align-items:flex-start !important;
      }
      :where(.admin-page,.customer-wrap) .ms-auto {
        margin-left:0 !important;
      }

      /* Full-width selects and inputs in forms on mobile */
      select.form-select, input.form-control { width:100%; }

      /* Filters: wrap and make full-width */
      .filter-row { flex-wrap:wrap !important; }
      .filter-row .form-control,
      .filter-row .form-select { min-width:0 !important; flex:1 1 140px !important; }
      .cs-filter-pill {
        flex:1 1 auto;
        justify-content:center;
        min-height:38px;
      }
      .table th,
      .table td {
        max-width:72vw;
        padding:.65rem .75rem;
      }
      .cs-responsive-table {
        margin-left:-.25rem;
        margin-right:-.25rem;
        width:calc(100% + .5rem);
        max-width:calc(100% + .5rem);
      }
    }

    @@media(max-width:399px) {
      /* Very small phones (iPhone SE, Galaxy A) */
      .admin-page { padding:8px 8px 20px !important; }
      .card { border-radius:var(--radius-md) !important; }
      .modal-dialog { margin:.25rem !important; max-width:calc(100vw - .5rem) !important; }
      .btn { font-size:.78rem !important; padding:.3rem .65rem !important; }
      .cs-filter-pill { width:100%; }
      .btn-sm { font-size:.72rem !important; padding:.22rem .5rem !important; }
    }
  </style>
  @stack('styles')
</head>
<body>
<div id="csTopProgress" aria-hidden="true"><div id="csTopProgressBar"></div></div>

{{-- ═══ Facebook/Messenger in-app browser – full modal warning ═══ --}}
<div id="fbIabOverlay" style="display:none;position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,.7);align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;border-radius:18px;padding:28px 22px;max-width:360px;width:100%;text-align:center;box-shadow:0 24px 64px rgba(0,0,0,.35);">
    <div style="font-size:52px;margin-bottom:8px;">🗺️</div>
    <h5 style="color:#dc2626;font-weight:800;font-size:17px;margin:0 0 8px;">Map Won't Work Here!</h5>
    <p style="color:#374151;font-size:14px;margin:0 0 14px;line-height:1.5;">
      The <strong>Facebook / Messenger browser</strong> does not support GPS and maps.<br><br>
      Please open in <strong>Chrome</strong> for the full ordering experience.
    </p>
    <div style="background:#fef9c3;border:1px solid #fde047;border-radius:10px;padding:12px 14px;margin-bottom:18px;font-size:13px;color:#713f12;text-align:left;line-height:1.6;">
      <strong>How to open in Chrome:</strong><br>
      1. Tap the <strong>⋮</strong> or <strong>···</strong> button at the top-right<br>
      2. Select <strong>"Open in Chrome"</strong><br>
      &nbsp;&nbsp;&nbsp;or <strong>"Open in external browser"</strong>
    </div>
    <a id="fbOpenChromeBtn" href="#" style="display:block;background:#1a73e8;color:#fff;padding:13px;border-radius:10px;font-weight:700;font-size:15px;text-decoration:none;margin-bottom:10px;">
      🌐 Open in Chrome
    </a>
    <button onclick="document.getElementById('fbIabOverlay').style.display='none'" style="background:none;border:1px solid #d1d5db;color:#6b7280;padding:10px;border-radius:10px;font-size:13px;cursor:pointer;width:100%;">
      Close (GPS/map won't work)
    </button>
  </div>
</div>
<script>
(function(){
  var ua = navigator.userAgent || '';
  var isFbIab = /FBAN|FBAV|FB_IAB|FBIOS|FBDV|MessengerLiteForiOS|Messenger|Instagram/.test(ua);
  if (!isFbIab) return;
  var overlay = document.getElementById('fbIabOverlay');
  overlay.style.display = 'flex';
  var btn = document.getElementById('fbOpenChromeBtn');
  var isAndroid = /Android/.test(ua);
  if (isAndroid) {
    // Android: intent URL forces open in Chrome
    btn.href = 'intent://' + location.host + location.pathname + location.search
             + '#Intent;scheme=https;package=com.android.chrome;end;';
  } else {
    // iOS: can't force Chrome, instruct user manually
    btn.closest('div').querySelector('a').outerHTML =
      '<div style="background:#e5e7eb;color:#374151;padding:12px;border-radius:10px;font-size:13px;margin-bottom:10px;">'
      + '📱 Sa iPhone: I-tap <strong>⋮</strong> → <strong>Open in Safari</strong></div>';
  }
})();
</script>

{{-- ═══ PLATFORM BG IMAGE OVERLAY (superadmin-controlled) ═══ --}}
@if($pbgType === 'image' && !empty($pbgImage))
<div aria-hidden="true" style="
  position:fixed;inset:0;z-index:-1;pointer-events:none;
  background:url('{{ $pbgImage }}') center/cover no-repeat;
  opacity:{{ $pbgOpacity }};
"></div>
@endif

@if($isAdmin)
{{-- ═══ ADMIN SIDEBAR ══════════════════════════════════════════════ --}}
<div id="adminSidebar">
  <div class="sb-brand">
    <div class="sb-brand-icon">
      @if(!empty($brandLogo))
        <img src="{{ $brandLogo }}" style="width:26px;height:26px;border-radius:6px;object-fit:cover" onerror="this.style.display='none'">
      @else
        <i class="bi bi-cake2-fill text-white" style="font-size:.95rem"></i>
      @endif
    </div>
    <div>
      <div class="sb-brand-text">{{ $brandTitle }}</div>
      <div class="sb-brand-sub">
        @if($sessionRole === 'superadmin') Super Admin @else Admin Panel @endif
      </div>
    </div>
  </div>

  <nav class="sb-nav">

    @if($sessionRole === 'superadmin')
    {{-- ═══════════════════════════════════════════ --}}
    {{-- SUPER ADMIN SIDEBAR — Platform Management  --}}
    {{-- ═══════════════════════════════════════════ --}}
    <div class="sb-label">Platform</div>
    <a href="{{ route('superadmin.dashboard') }}" class="sb-link {{ str_starts_with($currentRoute,'superadmin.dashboard') ? 'active' : '' }}">
      <i class="bi bi-speedometer2"></i><span class="sb-link-text">Dashboard</span>
    </a>

    <div class="sb-label">Sellers</div>
    <a href="{{ route('superadmin.sellers') }}" class="sb-link {{ str_starts_with($currentRoute,'superadmin.sellers') ? 'active' : '' }}">
      <i class="bi bi-shop"></i><span class="sb-link-text">Seller Applications</span>
      @php try { $pendingApps = (int)\Illuminate\Support\Facades\DB::table('shops')->where('status','pending')->count(); } catch(\Exception $e) { $pendingApps=0; } @endphp
      @if($pendingApps > 0)<span class="sb-badge">{{ $pendingApps }}</span>@endif
    </a>

    <div class="sb-label">Customers</div>
    <a href="{{ route('superadmin.feedback') }}" class="sb-link {{ str_starts_with($currentRoute,'superadmin.feedback') ? 'active' : '' }}">
      <i class="bi bi-chat-square-heart"></i><span class="sb-link-text">Feedback</span>
      @php try { $openFeedback = (int)\Illuminate\Support\Facades\DB::table('customer_feedback')->where('status','open')->count(); } catch(\Exception $e) { $openFeedback=0; } @endphp
      @if($openFeedback > 0)<span class="sb-badge">{{ $openFeedback > 9 ? '9+' : $openFeedback }}</span>@endif
    </a>

    <div class="sb-label">Settings</div>
    <a href="{{ route('superadmin.settings') }}" class="sb-link {{ ($currentRoute==='superadmin.settings' && request()->input('tab','platform') !== 'logs' && request()->input('tab','platform') !== 'backup') ? 'active' : '' }}">
      <i class="bi bi-sliders"></i><span class="sb-link-text">Platform Settings</span>
    </a>
    <a href="{{ route('superadmin.settings', ['tab' => 'logs']) }}" class="sb-link {{ ($currentRoute==='superadmin.settings' && request()->input('tab') === 'logs') ? 'active' : '' }}">
      <i class="bi bi-journal-text"></i><span class="sb-link-text">Activity Logs</span>
    </a>
    <a href="{{ route('superadmin.settings', ['tab' => 'backup']) }}" class="sb-link {{ ($currentRoute==='superadmin.settings' && request()->input('tab') === 'backup') ? 'active' : '' }}">
      <i class="bi bi-cloud-arrow-up"></i><span class="sb-link-text">Backup</span>
    </a>

    @else
    {{-- ═══════════════════════════════════════════ --}}
    {{-- ADMIN SIDEBAR — Shop Management            --}}
    {{-- ═══════════════════════════════════════════ --}}
    <div class="sb-label">Main</div>
    <a href="{{ route('admin.dashboard') }}" class="sb-link {{ str_starts_with($currentRoute,'admin.dashboard') ? 'active' : '' }}">
      <i class="bi bi-speedometer2"></i><span class="sb-link-text">Dashboard</span>
    </a>
    <a href="{{ route('admin.orders.index') }}" class="sb-link {{ str_starts_with($currentRoute,'admin.orders') ? 'active' : '' }}">
      <i class="bi bi-bag-check"></i><span class="sb-link-text">Orders</span>
      @php try { $pendingOrdSb = (int)\Illuminate\Support\Facades\DB::table('orders')->where('status','Pending')->count(); } catch(\Exception $e) { $pendingOrdSb=0; } @endphp
      @if($pendingOrdSb > 0)<span class="sb-badge">{{ $pendingOrdSb }}</span>@endif
    </a>
    <a href="{{ route('admin.kitchen.index') }}" class="sb-link {{ str_starts_with($currentRoute,'admin.kitchen') ? 'active' : '' }}">
      <i class="bi bi-fire"></i><span class="sb-link-text">Kitchen</span>
    </a>
    <a href="{{ route('admin.messages.index') }}" class="sb-link {{ str_starts_with($currentRoute,'admin.messages') ? 'active' : '' }}">
      <i class="bi bi-chat-dots"></i><span class="sb-link-text">Messages</span>
      @if($unreadMessages > 0)<span class="sb-badge">{{ $unreadMessages > 9 ? '9+' : $unreadMessages }}</span>@endif
    </a>

    <div class="sb-label">Catalog</div>
    <a href="{{ route('admin.products.index') }}" class="sb-link {{ str_starts_with($currentRoute,'admin.products') ? 'active' : '' }}">
      <i class="bi bi-cake2"></i><span class="sb-link-text">Products</span>
    </a>
    <a href="{{ route('admin.addons.index') }}" class="sb-link {{ str_starts_with($currentRoute,'admin.addons') ? 'active' : '' }}">
      <i class="bi bi-gift"></i><span class="sb-link-text">Add-ons</span>
    </a>
    <a href="{{ route('admin.custom_options.index') }}" class="sb-link {{ str_starts_with($currentRoute,'admin.custom_options') ? 'active' : '' }}">
      <i class="bi bi-sliders"></i><span class="sb-link-text">Custom Options</span>
    </a>
    @php try { $pendingCustom = (int)\Illuminate\Support\Facades\DB::table('custom_orders')->where('review_status','pending')->count(); } catch(\Exception $e) { $pendingCustom=0; } @endphp
    <a href="{{ route('admin.custom_orders.index') }}" class="sb-link {{ str_starts_with($currentRoute,'admin.custom_orders') ? 'active' : '' }}">
      <i class="bi bi-palette"></i><span class="sb-link-text">Custom Orders</span>
      @if($pendingCustom > 0)<span class="sb-badge">{{ $pendingCustom }}</span>@endif
    </a>

    <div class="sb-label">Delivery</div>
    <a href="{{ route('admin.delivery_zones.index') }}" class="sb-link {{ str_starts_with($currentRoute,'admin.delivery_zones') ? 'active' : '' }}">
      <i class="bi bi-geo-alt"></i><span class="sb-link-text">Delivery Zones</span>
    </a>
    <a href="{{ route('admin.riders.index') }}" class="sb-link {{ str_starts_with($currentRoute,'admin.riders') ? 'active' : '' }}">
      <i class="bi bi-bicycle"></i><span class="sb-link-text">Riders</span>
    </a>

    <div class="sb-label">System</div>
    <a href="{{ route('admin.settings.index') }}" class="sb-link {{ str_starts_with($currentRoute,'admin.settings') ? 'active' : '' }}">
      <i class="bi bi-gear"></i><span class="sb-link-text">Settings</span>
    </a>
    <a href="{{ route('admin.logs.index') }}" class="sb-link {{ str_starts_with($currentRoute,'admin.logs') ? 'active' : '' }}">
      <i class="bi bi-journal-text"></i><span class="sb-link-text">Activity Logs</span>
    </a>
    @endif
    <form method="POST" action="{{ route('logout') }}" style="margin:0">@csrf<button type="submit" class="sb-link" style="margin-top:4px;background:none;border:none;width:100%;text-align:left;padding:0;cursor:pointer">
      <i class="bi bi-box-arrow-right" style="color:#ef4444"></i><span class="sb-link-text" style="color:#ef4444">Logout</span>
    </button></form>
  </nav>

  <div class="sb-user">
    @if($userPhoto)
      <img src="{{ $userPhoto }}" class="nav-avatar" onerror="this.style.display='none'">
    @else
      <span class="nav-avatar-fallback">{{ strtoupper(substr($fullname??'A',0,1)) }}</span>
    @endif
    <div class="sb-user-info">
      <div class="sb-user-name">{{ $fullname ?? $username }}</div>
      <div class="sb-user-role">Administrator</div>
    </div>
  </div>
</div>

<div id="sbOverlay" onclick="closeSidebar()"></div>

<div id="adminMain">
  <div id="adminTopbar">
    <button class="tb-toggle" onclick="toggleSidebar()"><i class="bi bi-list" style="font-size:1.2rem"></i></button>
    <div class="tb-bread">
      @hasSection('breadcrumb') @yield('breadcrumb') @else <strong>{{ $brandTitle }}</strong> @endif
    </div>
  </div>

  {{-- Flash messages --}}
  @if(session('msg') || session('error') || session('err') || session('warn'))
  <div style="padding:12px 22px 0">
    @if(session('msg'))<div class="alert alert-success border-0 py-2 d-flex align-items-center gap-2 cs-flash mb-0"><i class="bi bi-check-circle-fill"></i>{{ session('msg') }}</div>@endif
    @if(session('error') || session('err'))<div class="alert alert-danger border-0 py-2 d-flex align-items-center gap-2 cs-flash mb-0"><i class="bi bi-exclamation-circle-fill"></i>{{ session('error') ?? session('err') }}</div>@endif
    @if(session('warn'))<div class="alert alert-warning border-0 py-2 d-flex align-items-center gap-2 cs-flash mb-0"><i class="bi bi-exclamation-triangle-fill"></i>{{ session('warn') }}</div>@endif
  </div>
  @endif


  <div class="admin-page">@yield('content')</div>
@elseif($isSeller)
{{-- ═══ SELLER SIDEBAR ════════════════════════════════════════════════ --}}
@php
  $sellerShop = null;
  try {
    $sellerShop = \Illuminate\Support\Facades\DB::table('shops')
      ->where('seller_id', $uid)->first();
  } catch(\Exception $e) {}
@endphp
<div id="sellerSidebar">
  <div class="sb-brand">
    <div class="sb-brand-icon" style="background:var(--primary)">
      @if($sellerShop?->shop_logo)
        <img src="{{ $sellerShop->shop_logo }}" style="width:26px;height:26px;border-radius:6px;object-fit:cover">
      @else
        <i class="bi bi-shop text-white" style="font-size:.95rem"></i>
      @endif
    </div>
    <div>
      <div class="sb-brand-text">{{ Str::limit($sellerShop?->shop_name ?? 'My Shop', 18) }}</div>
      <div class="sb-brand-sub">Seller Dashboard</div>
    </div>
  </div>

  <nav class="sb-nav">
    <div class="sb-label">Main</div>
    <a href="{{ route('seller.dashboard') }}" class="sb-link {{ $currentRoute==='seller.dashboard' ? 'active' : '' }}">
      <i class="bi bi-speedometer2"></i><span class="sb-link-text">Dashboard</span>
    </a>
    <a href="{{ route('seller.orders') }}" class="sb-link {{ str_starts_with($currentRoute,'seller.orders') ? 'active' : '' }}">
      <i class="bi bi-bag-check"></i><span class="sb-link-text">Orders</span>
      @php try { $selPend = (int)\Illuminate\Support\Facades\DB::table('orders')->where('shop_id',$sellerShop?->id)->where('status','Pending')->count(); } catch(\Exception $e){ $selPend=0; } @endphp
      @if($selPend > 0)<span class="sb-badge">{{ $selPend }}</span>@endif
    </a>
    <a href="{{ route('seller.kitchen') }}" class="sb-link {{ str_starts_with($currentRoute,'seller.kitchen') ? 'active' : '' }}">
      <i class="bi bi-fire"></i><span class="sb-link-text">Kitchen</span>
    </a>
    <a href="{{ route('seller.messages') }}" class="sb-link {{ str_starts_with($currentRoute,'seller.messages') ? 'active' : '' }}">
      <i class="bi bi-chat-dots"></i><span class="sb-link-text">Messages</span>
      @php try { $selMsg = (int)\Illuminate\Support\Facades\DB::table('messages as m')->join('orders as o','o.id','=','m.order_id')->where('o.shop_id',$sellerShop?->id)->where('m.sender_role','customer')->where('m.is_read', false)->count(); } catch(\Exception $e){ $selMsg=0; } @endphp
      @if($selMsg > 0)<span class="sb-badge">{{ $selMsg > 9 ? '9+' : $selMsg }}</span>@endif
    </a>

    <div class="sb-label">Catalog</div>
    <a href="{{ route('seller.products') }}" class="sb-link {{ str_starts_with($currentRoute,'seller.products') ? 'active' : '' }}">
      <i class="bi bi-cake2"></i><span class="sb-link-text">Products</span>
    </a>
    @if($sellerShop?->tier === 'verified')
    <a href="{{ route('seller.custom_orders') }}" class="sb-link {{ str_starts_with($currentRoute,'seller.custom_orders') ? 'active' : '' }}">
      <i class="bi bi-palette"></i><span class="sb-link-text">Custom Orders</span>
      @php try { $selCust = (int)\Illuminate\Support\Facades\DB::table('custom_orders')->where('shop_id',$sellerShop?->id)->where('review_status','pending')->count(); } catch(\Exception $e){ $selCust=0; } @endphp
      @if($selCust > 0)<span class="sb-badge">{{ $selCust }}</span>@endif
    </a>
    @endif
    <a href="{{ route('seller.addons') }}" class="sb-link {{ str_starts_with($currentRoute,'seller.addons') ? 'active' : '' }}">
      <i class="bi bi-gift"></i><span class="sb-link-text">Add-ons</span>
    </a>
    <a href="{{ route('seller.custom_options') }}" class="sb-link {{ str_starts_with($currentRoute,'seller.custom_options') ? 'active' : '' }}">
      <i class="bi bi-sliders"></i><span class="sb-link-text">Custom Options</span>
    </a>

    <div class="sb-label">Delivery</div>
    <a href="{{ route('seller.zones') }}" class="sb-link {{ str_starts_with($currentRoute,'seller.zones') ? 'active' : '' }}">
      <i class="bi bi-geo-alt"></i><span class="sb-link-text">Delivery Zones</span>
    </a>
    <a href="{{ route('seller.riders') }}" class="sb-link {{ str_starts_with($currentRoute,'seller.riders') ? 'active' : '' }}">
      <i class="bi bi-bicycle"></i><span class="sb-link-text">Riders</span>
    </a>

    <div class="sb-label">Settings</div>
    <a href="{{ route('seller.settings') }}" class="sb-link {{ str_starts_with($currentRoute,'seller.settings') ? 'active' : '' }}">
      <i class="bi bi-gear"></i><span class="sb-link-text">Shop Settings</span>
    </a>
    <a href="{{ route('seller.reviews') }}" class="sb-link {{ str_starts_with($currentRoute,'seller.reviews') ? 'active' : '' }}">
      <i class="bi bi-star"></i><span class="sb-link-text">Reviews</span>
    </a>
    <a href="{{ route('seller.feedback') }}" class="sb-link {{ str_starts_with($currentRoute,'seller.feedback') ? 'active' : '' }}">
      <i class="bi bi-chat-square-heart"></i><span class="sb-link-text">Platform Feedback</span>
    </a>
    <form method="POST" action="{{ route('logout') }}" style="margin:0">@csrf<button type="submit" class="sb-link" style="margin-top:4px;background:none;border:none;width:100%;text-align:left;padding:0;cursor:pointer">
      <i class="bi bi-box-arrow-right" style="color:#ef4444"></i>
      <span class="sb-link-text" style="color:#ef4444">Logout</span>
    </button></form>
  </nav>

  <div class="sb-user">
    @if($userPhoto)
      <img src="{{ $userPhoto }}" class="nav-avatar" onerror="this.style.display='none'">
    @else
      <span class="nav-avatar-fallback">{{ strtoupper(substr($fullname??'S',0,1)) }}</span>
    @endif
    <div class="sb-user-info">
      <div class="sb-user-name">{{ Str::limit($fullname ?? $username, 16) }}</div>
      <div class="sb-user-role">Seller</div>
    </div>
  </div>
</div>

{{-- Seller topbar --}}
<div id="sellerTopbar">
  <button class="tb-toggle" onclick="toggleSidebar()">
    <i class="bi bi-list" style="font-size:1.2rem"></i>
  </button>
  <div class="tb-title">
    @hasSection('page_title') @yield('page_title') @else {{ $sellerShop?->shop_name ?? 'Seller Dashboard' }} @endif
  </div>
  <div style="margin-left:auto;display:flex;align-items:center;gap:.75rem">
    @if($userPhoto)
      <img src="{{ $userPhoto }}" class="nav-avatar" onerror="this.style.display='none'">
    @else
      <span class="nav-avatar-fallback">{{ strtoupper(substr($fullname??'S',0,1)) }}</span>
    @endif
  </div>
</div>

<div id="sellerOverlay" onclick="closeSidebar()"></div>

<div id="sellerMain">
  <div class="admin-page">@yield('content')</div>
</div>

  <footer class="text-center py-3" style="color:#bbb;font-size:.76rem;border-top:1px solid #e9ecef;margin-top:auto">
    @if(!empty($brandLogo))
      <img src="{{ $brandLogo }}" style="height:28px;width:auto;object-fit:contain;border-radius:5px;margin-bottom:.3rem;display:block;margin-left:auto;margin-right:auto" onerror="this.style.display='none'">
    @endif
    &copy; {{ date('Y') }} {{ $brandTitle }} Admin
  </footer>
</div>

@else
{{-- ═══ CUSTOMER SIDEBAR ════════════════════════════════════════════ --}}
<style>
/* ── Customer Topbar (slim) ── */
.cust-topbar {
  position:fixed;top:0;left:0;right:0;z-index:1030;
  height:56px;
  background:rgba(255,255,255,.94);
  backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);
  border-bottom:1px solid rgba(0,0,0,.07);
  display:flex;align-items:center;
  padding:0 clamp(14px,4vw,24px);
  gap:12px;
}
.cust-topbar-brand {
  flex:1;display:flex;align-items:center;gap:10px;
  font-weight:700;font-size:clamp(1rem,3vw,1.15rem);
  color:var(--primary);text-decoration:none;
}
.cust-topbar-brand img { width:32px;height:32px;border-radius:8px;object-fit:cover; }
.cust-topbar-brand .brand-icon {
  width:32px;height:32px;border-radius:8px;
  background:var(--primary);
  display:inline-flex;align-items:center;justify-content:center;
  flex-shrink:0;
}
.cust-menu-btn {
  width:38px;height:38px;border-radius:10px;border:none;
  background:var(--primary-light);color:var(--primary);
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;font-size:1.1rem;flex-shrink:0;
}
.cust-menu-btn:hover { background:var(--primary);color:#fff; }

/* ── Sidebar ── */
.cust-sidebar {
  position:fixed;top:0;left:0;bottom:0;
  width:clamp(260px,75vw,300px);
  background:#fff;
  z-index:1050;
  display:flex;flex-direction:column;
  box-shadow:4px 0 32px rgba(0,0,0,.18);
  transform:translateX(-100%);
  transition:transform .3s cubic-bezier(.4,0,.2,1);
}
.cust-sidebar.open { transform:translateX(0); }

.csb-header {
  background:linear-gradient(135deg,var(--primary),#c2185b);
  padding:20px 20px 24px;
  display:flex;align-items:center;gap:12px;
}
.csb-header-logo {
  width:46px;height:46px;border-radius:12px;object-fit:cover;
  border:2px solid rgba(255,255,255,.4);flex-shrink:0;
}
.csb-header-icon {
  width:46px;height:46px;border-radius:12px;
  background:rgba(255,255,255,.2);
  display:flex;align-items:center;justify-content:center;
  font-size:1.3rem;flex-shrink:0;
}
.csb-shop-name { font-size:clamp(1rem,4vw,1.1rem);font-weight:700;color:#fff; }
.csb-shop-sub  { font-size:.75rem;color:rgba(255,255,255,.75); }
.csb-close {
  margin-left:auto;width:32px;height:32px;border-radius:8px;
  background:rgba(255,255,255,.15);border:none;color:#fff;
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;font-size:1rem;flex-shrink:0;
}
.csb-close:hover { background:rgba(255,255,255,.3); }

.csb-nav { flex:1;overflow-y:auto;padding:12px; }
.csb-section-label {
  font-size:.65rem;font-weight:700;text-transform:uppercase;
  letter-spacing:.08em;color:#9ca3af;padding:12px 10px 6px;
}
.csb-link {
  display:flex;align-items:center;gap:12px;
  padding:12px 14px;border-radius:12px;
  color:#374151;font-weight:500;
  font-size:clamp(.9rem,3vw,1rem);
  text-decoration:none;margin-bottom:2px;
  transition:all .15s;
}
.csb-link:hover { background:var(--primary-light);color:var(--primary); }
.csb-link.active { background:var(--primary);color:#fff; }
.csb-link i { font-size:1.1rem;width:22px;text-align:center;flex-shrink:0; }
.csb-link .csb-badge {
  margin-left:auto;background:#ef4444;color:#fff;
  font-size:.65rem;font-weight:700;
  padding:2px 7px;border-radius:99px;min-width:20px;text-align:center;
}

.csb-divider { height:1px;background:#f3f4f6;margin:8px 0; }

.csb-footer { padding:12px;border-top:1px solid #f3f4f6; }
.csb-footer-note { font-size:.75rem;color:#9ca3af;text-align:center;padding:8px; }

/* ── Overlay ── */
.csb-overlay {
  position:fixed;inset:0;background:rgba(0,0,0,.5);
  z-index:1049;display:none;
  backdrop-filter:blur(2px);
}
.csb-overlay.open { display:block; }

/* ── customer-wrap adjust ── */
.customer-wrap { padding-top:56px; }
@@media(max-width:767px) { .customer-wrap { padding-top:56px; } }

/* Become Seller modal */
.bsm-overlay {
  position:fixed;inset:0;z-index:1060;
  display:none;align-items:center;justify-content:center;
  padding:18px;background:rgba(17,24,39,.58);
  backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);
}
.bsm-overlay.bsm-open { display:flex; }
.bsm-panel {
  position:relative;overflow:hidden;
  width:min(920px,100%);min-height:min(560px,calc(100vh - 36px));
  border-radius:18px;background:var(--primary-dark);
  box-shadow:0 28px 80px rgba(0,0,0,.32);
  display:flex;align-items:center;justify-content:center;
}
.bsm-panel::before {
  content:"";position:absolute;inset:0;
  background:
    radial-gradient(circle at 14% 20%, rgba(255,241,118,.95) 0 12%, transparent 12.5%),
    radial-gradient(circle at 88% 14%, rgba(255,138,128,.9) 0 16%, transparent 16.5%),
    radial-gradient(circle at 88% 88%, rgba(255,255,255,.16) 0 20%, transparent 20.5%),
    linear-gradient(145deg,var(--primary-dark),var(--primary) 48%,#2f1f18);
}
.bsm-panel::after {
  content:"";position:absolute;left:-7%;right:-7%;bottom:-1px;height:26%;
  background:#fff;
  clip-path:polygon(0 48%,8% 40%,15% 55%,22% 43%,31% 58%,40% 46%,50% 61%,62% 44%,72% 58%,84% 43%,94% 57%,100% 47%,100% 100%,0 100%);
}
.bsm-close {
  position:absolute;top:16px;right:16px;z-index:2;
  width:38px;height:38px;border-radius:10px;border:0;
  background:rgba(255,255,255,.9);color:#111827;
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;
}
.bsm-content {
  position:relative;z-index:1;text-align:center;color:#fff;
  width:min(620px,88%);padding:72px 0 112px;
}
.bsm-kicker {
  display:inline-flex;align-items:center;gap:8px;
  padding:7px 12px;border-radius:999px;
  background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);
  font-size:.78rem;font-weight:700;margin-bottom:18px;
}
.bsm-title {
  font-family:'Playfair Display',serif;
  font-size:clamp(2rem,6vw,3.9rem);
  line-height:1.08;font-weight:700;margin:0 0 14px;
}
.bsm-text {
  font-size:clamp(.95rem,2vw,1.1rem);
  line-height:1.7;margin:0 auto 24px;
  color:rgba(255,255,255,.88);max-width:520px;
}
.bsm-actions { display:flex;justify-content:center;gap:12px;flex-wrap:wrap; }
.bsm-primary {
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  min-height:46px;padding:0 24px;border-radius:999px;
  background:#fff;color:var(--primary);font-weight:800;text-decoration:none;
  box-shadow:0 12px 28px rgba(0,0,0,.18);
}
.bsm-secondary {
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  min-height:46px;padding:0 20px;border-radius:999px;
  background:rgba(255,255,255,.12);color:#fff;font-weight:700;text-decoration:none;
  border:1px solid rgba(255,255,255,.24);
}
@media(max-width:640px) {
  .bsm-panel { min-height:min(520px,calc(100vh - 36px));border-radius:16px; }
  .bsm-content { width:86%;padding:72px 0 96px; }
  .bsm-actions { flex-direction:column;align-items:stretch; }
}
</style>

{{-- Overlay --}}
<div class="csb-overlay" id="csbOverlay" onclick="closeCustSidebar()"></div>

{{-- Sidebar --}}
<div class="cust-sidebar" id="custSidebar">
  <div class="csb-header">
    @if(!empty($brandLogo))
      <img src="{{ $brandLogo }}" class="csb-header-logo" onerror="this.style.display='none'">
    @else
      <div class="csb-header-icon"><i class="bi bi-cake2-fill text-white"></i></div>
    @endif
    <div>
      <div class="csb-shop-name">{{ $brandTitle }}</div>
      <div class="csb-shop-sub">{{ $settings['tagline'] ?? 'Order your cake today!' }}</div>
    </div>
    <button class="csb-close" onclick="closeCustSidebar()"><i class="bi bi-x-lg"></i></button>
  </div>

  <nav class="csb-nav">
    <div class="csb-section-label">Menu</div>
    @if($role === 'customer')
    <a href="{{ route('customer.dashboard') }}" class="csb-link {{ $currentRoute==='customer.dashboard' ? 'active' : '' }}" onclick="closeCustSidebar()">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <a href="{{ route('customer.catalog') }}" class="csb-link {{ $currentRoute==='customer.catalog' ? 'active' : '' }}" onclick="closeCustSidebar()">
      <i class="bi bi-shop"></i> Catalog
    </a>
    <a href="{{ route('customer.orders') }}" class="csb-link {{ str_starts_with($currentRoute,'customer.orders') || str_starts_with($currentRoute,'customer.custom_orders') ? 'active' : '' }}" onclick="closeCustSidebar()">
      <i class="bi bi-bag-check"></i> Orders
    </a>
    <a href="{{ route('customer.messages') }}" class="csb-link {{ str_starts_with($currentRoute,'customer.messages') ? 'active' : '' }}" onclick="closeCustSidebar()">
      <i class="bi bi-chat-dots"></i> Messages
      @if($unreadMessages > 0)<span class="csb-badge">{{ $unreadMessages > 9 ? '9+' : $unreadMessages }}</span>@endif
    </a>
    <a href="{{ route('customer.feedback') }}" class="csb-link {{ str_starts_with($currentRoute,'customer.feedback') ? 'active' : '' }}" onclick="closeCustSidebar()">
      <i class="bi bi-chat-square-heart"></i> Feedback
    </a>
    <a href="{{ route('customer.profile') }}" class="csb-link {{ str_starts_with($currentRoute,'customer.profile') ? 'active' : '' }}" onclick="closeCustSidebar()">
      <i class="bi bi-person"></i> Profile
    </a>
    <form method="POST" action="{{ route('logout') }}" style="margin:0">@csrf<button type="submit" class="csb-link" style="color:#ef4444;background:none;border:none;cursor:pointer;width:100%;text-align:left">
      <i class="bi bi-box-arrow-right"></i> Logout
    </button></form>
    @else
    <a href="{{ route('catalog') }}" class="csb-link {{ $currentRoute==='catalog' ? 'active' : '' }}" onclick="closeCustSidebar()">
      <i class="bi bi-shop"></i> Catalog
    </a>
    <a href="{{ route('guest.feedback') }}" class="csb-link {{ $currentRoute==='guest.feedback' ? 'active' : '' }}" onclick="closeCustSidebar()">
      <i class="bi bi-chat-square-heart"></i> Feedback
    </a>
    @endif
    <div class="csb-divider"></div>
    <div class="csb-section-label">Track</div>
    <a href="#" class="csb-link" onclick="closeCustSidebar(); csTrackPrompt(); return false;">
      <i class="bi bi-search"></i> Track My Order
    </a>

    <div class="csb-divider"></div>
    <div class="csb-section-label">For Riders</div>
    <div style="padding:2px 8px 10px">
      <div style="background:linear-gradient(135deg,#fef3c7,#fef9c3);border:1.5px solid #fde68a;border-radius:14px;padding:14px">
        <div style="display:flex;align-items:center;gap:7px;margin-bottom:5px">
          <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#d97706,#b45309);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="bi bi-bicycle" style="color:#fff;font-size:.85rem"></i>
          </div>
          <div>
            <div style="font-size:.82rem;font-weight:700;color:#78350f;line-height:1.2">Rider Access</div>
            <div style="font-size:.7rem;color:#92400e;line-height:1.3">Enter your 6-digit PIN from SMS</div>
          </div>
        </div>
        <form action="{{ route('rider.pin') }}" method="POST" id="riderAccessForm" style="margin-top:10px">
          @csrf
          <input type="text" name="pin" id="riderPinInput"
                 inputmode="text" maxlength="8"
                 placeholder="e.g. A3K7X2MQ"
                 autocomplete="one-time-code" autocorrect="off" autocapitalize="characters" spellcheck="false"
                 style="width:100%;border:1.5px solid #fbbf24;border-radius:10px;padding:.6rem .65rem;font-size:1.1rem;font-weight:700;letter-spacing:.2em;text-align:center;font-family:monospace;background:#fffbeb;color:#92400e;outline:none;margin-bottom:6px;transition:border-color .15s,box-shadow .15s;text-transform:uppercase"
                 onfocus="this.style.borderColor='#d97706';this.style.boxShadow='0 0 0 3px rgba(217,119,6,.15)'"
                 onblur="this.style.borderColor='#fbbf24';this.style.boxShadow='none'">
          @if(session('rider_err'))
          <div style="color:#b91c1c;font-size:.72rem;margin-bottom:6px;line-height:1.35;padding:6px 8px;background:#fff1f2;border-radius:7px;border:1px solid #fecdd3">
            <i class="bi bi-exclamation-circle me-1"></i>{{ session('rider_err') }}
          </div>
          @endif
          <button type="submit" id="riderPinBtn"
                  style="width:100%;padding:.6rem;background:linear-gradient(135deg,#d97706,#b45309);color:#fff;border:none;border-radius:10px;font-weight:700;font-size:.85rem;cursor:pointer;transition:opacity .15s;display:flex;align-items:center;justify-content:center;gap:6px">
            <i class="bi bi-arrow-right-circle-fill"></i> Open My Delivery
          </button>
        </form>
      </div>
    </div>

    <div class="csb-divider"></div>
    <div class="csb-section-label">Sellers</div>
    <a href="{{ route('login') }}" class="csb-link" onclick="closeCustSidebar()" style="color:#e53935;font-weight:600">
      <i class="bi bi-person-badge"></i> Seller Login
    </a>
    <button onclick="openBecomeSellerModal()"
            class="csb-link" style="color:#e53935;font-weight:600;background:none;border:none;cursor:pointer;width:100%;text-align:left;padding:0">
      <i class="bi bi-stars"></i> Become a Seller
    </button>

    @if($role === 'admin')
    <div class="csb-divider"></div>
    <div class="csb-section-label">Admin</div>
    <a href="{{ route('admin.dashboard') }}" class="csb-link" onclick="closeCustSidebar()">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <form method="POST" action="{{ route('logout') }}" style="margin:0">@csrf<button type="submit" class="csb-link" style="color:#ef4444;background:none;border:none;cursor:pointer">
      <i class="bi bi-box-arrow-right"></i> Logout
    </button></form>
    @endif
  </nav>

  <div class="csb-footer">
    <div class="csb-footer-note">&copy; {{ date('Y') }} {{ $brandTitle }}</div>
  </div>
</div>

{{-- Become a Seller Modal --}}
<div id="becomeSellerModal" class="bsm-overlay" onclick="if(event.target===this)closeBecomeSellerModal()">
  <div class="bsm-card">
    <button class="bsm-close" onclick="closeBecomeSellerModal()" aria-label="Close">&times;</button>

    <div class="bsm-hero">
      <div class="bsm-hero-icon"><i class="bi bi-shop-window"></i></div>
      <h2 class="bsm-title">Start Selling on {{ $brandTitle }}</h2>
      <p class="bsm-sub">Join our growing community of cake sellers. Reach more customers and grow your business online.</p>
    </div>

    <div class="bsm-perks">
      <div class="bsm-perk">
        <div class="bsm-perk-icon" style="background:#fdf2f8;color:var(--primary)"><i class="bi bi-people-fill"></i></div>
        <div>
          <div class="bsm-perk-title">Reach More Customers</div>
          <div class="bsm-perk-desc">Get discovered by customers in your area browsing for custom cakes and pastries.</div>
        </div>
      </div>
      <div class="bsm-perk">
        <div class="bsm-perk-icon" style="background:#f0fdf4;color:#16a34a"><i class="bi bi-phone-fill"></i></div>
        <div>
          <div class="bsm-perk-title">Accept GCash &amp; Cash Payments</div>
          <div class="bsm-perk-desc">Receive payments via GCash, Cash on Pickup, or Cash on Delivery — all in one place.</div>
        </div>
      </div>
      <div class="bsm-perk">
        <div class="bsm-perk-icon" style="background:#eff6ff;color:#1d4ed8"><i class="bi bi-graph-up-arrow"></i></div>
        <div>
          <div class="bsm-perk-title">Manage Orders Easily</div>
          <div class="bsm-perk-desc">Your own seller dashboard — track orders, manage products, and view your sales history.</div>
        </div>
      </div>
      <div class="bsm-perk">
        <div class="bsm-perk-icon" style="background:#fff7ed;color:#ea580c"><i class="bi bi-shield-check-fill"></i></div>
        <div>
          <div class="bsm-perk-title">Verified &amp; Trusted</div>
          <div class="bsm-perk-desc">Your shop is reviewed before going live. Customers trust verified sellers on our platform.</div>
        </div>
      </div>
    </div>

    <div class="bsm-footer">
      <a href="{{ route('seller.apply') }}" class="bsm-cta-btn">
        <i class="bi bi-shop-window me-2"></i>Start Selling Now
      </a>
      <a href="{{ route('login') }}" class="bsm-login-link">
        Already a seller? <strong>Sign in here</strong>
      </a>
    </div>
  </div>
</div>

<style>
.bsm-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center;padding:1rem;backdrop-filter:blur(3px)}
.bsm-overlay.bsm-open{display:flex;animation:bsmFadeIn .22s ease}
@keyframes bsmFadeIn{from{opacity:0}to{opacity:1}}
.bsm-card{background:#fff;border-radius:24px;width:100%;max-width:480px;max-height:92vh;overflow-y:auto;position:relative;box-shadow:0 24px 64px rgba(0,0,0,.2);animation:bsmSlideUp .28s cubic-bezier(.34,1.56,.64,1)}
@keyframes bsmSlideUp{from{transform:translateY(36px);opacity:0}to{transform:translateY(0);opacity:1}}
.bsm-close{position:absolute;top:.875rem;right:.875rem;width:2rem;height:2rem;border:none;border-radius:50%;background:#f3f4f6;color:#6b7280;font-size:1.2rem;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s;z-index:1}
.bsm-close:hover{background:#e5e7eb;color:#111}
.bsm-hero{background:linear-gradient(135deg,var(--primary,#e91e63) 0%,#c2185b 100%);border-radius:24px 24px 0 0;padding:2.25rem 2rem 1.75rem;text-align:center;color:#fff}
.bsm-hero-icon{width:60px;height:60px;background:rgba(255,255,255,.18);border-radius:16px;display:inline-flex;align-items:center;justify-content:center;font-size:1.6rem;margin-bottom:.875rem}
.bsm-title{font-family:'Playfair Display',serif;font-size:1.45rem;font-weight:700;margin:0 0 .4rem;line-height:1.3}
.bsm-sub{font-size:.85rem;opacity:.88;margin:0;line-height:1.5}
.bsm-perks{padding:1.5rem 1.75rem;display:flex;flex-direction:column;gap:1rem}
.bsm-perk{display:flex;align-items:flex-start;gap:.875rem}
.bsm-perk-icon{width:2.4rem;height:2.4rem;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0}
.bsm-perk-title{font-size:.875rem;font-weight:700;color:#111827;margin-bottom:.12rem}
.bsm-perk-desc{font-size:.78rem;color:#6b7280;line-height:1.45}
.bsm-footer{padding:1.25rem 1.75rem 1.75rem;display:flex;flex-direction:column;align-items:center;gap:.75rem;border-top:1.5px solid #f3f4f6}
.bsm-cta-btn{display:inline-flex;align-items:center;justify-content:center;width:100%;background:linear-gradient(135deg,var(--primary,#e91e63),#c2185b);color:#fff;border-radius:12px;padding:.875rem 1.5rem;font-size:.975rem;font-weight:700;text-decoration:none;transition:opacity .15s,transform .15s;box-shadow:0 4px 16px rgba(233,30,99,.28)}
.bsm-cta-btn:hover{opacity:.9;transform:translateY(-1px);color:#fff}
.bsm-login-link{font-size:.82rem;color:#9ca3af;text-decoration:none}
.bsm-login-link:hover{color:var(--primary,#e91e63)}
.bsm-login-link strong{color:var(--primary,#e91e63)}
</style>

{{-- Top bar --}}
<div class="cust-topbar">
  <button class="cust-menu-btn" onclick="openCustSidebar()" aria-label="Menu">
    <i class="bi bi-list"></i>
  </button>
  <a href="{{ route('catalog') }}" class="cust-topbar-brand">
    @if(!empty($brandLogo))
      <img src="{{ $brandLogo }}" onerror="this.style.display='none'">
    @else
      <div class="brand-icon"><i class="bi bi-cake2-fill text-white" style="font-size:.9rem"></i></div>
    @endif
    <span>{{ $brandTitle }}</span>
  </a>
</div>

{{-- Become Seller modal --}}
<div class="bsm-overlay" id="becomeSellerModal" onclick="closeBecomeSellerModal(event)" aria-hidden="true">
  <div class="bsm-panel" role="dialog" aria-modal="true" aria-labelledby="bsmTitle">
    <button type="button" class="bsm-close" onclick="closeBecomeSellerModal()" aria-label="Close">
      <i class="bi bi-x-lg"></i>
    </button>
    <div class="bsm-content">
      <div class="bsm-kicker"><i class="bi bi-shop-window"></i>{{ $brandTitle }} Sellers</div>
      <h1 class="bsm-title" id="bsmTitle">Let more customers discover your cakes</h1>
      <p class="bsm-text">
        Open your shop profile, accept online orders, and manage your cakes from one seller dashboard built for {{ $brandTitle }}.
      </p>
      <div class="bsm-actions">
        <a href="{{ route('seller.apply') }}" class="bsm-primary">
          <i class="bi bi-shop-window"></i> Sell Here
        </a>
        <a href="{{ route('login') }}" class="bsm-secondary">
          <i class="bi bi-person-badge"></i> Seller Login
        </a>
      </div>
    </div>
  </div>
</div>

<script>
function openCustSidebar() {
  document.getElementById('custSidebar').classList.add('open');
  document.getElementById('csbOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeCustSidebar() {
  document.getElementById('custSidebar').classList.remove('open');
  document.getElementById('csbOverlay').classList.remove('open');
  document.body.style.overflow = '';
}
function openBecomeSellerModal() {
  var modal = document.getElementById('becomeSellerModal');
  if (!modal) return;
  closeCustSidebar();
  modal.classList.add('bsm-open');
  modal.setAttribute('aria-hidden', 'false');
  document.body.style.overflow = 'hidden';
}
function closeBecomeSellerModal(event) {
  if (event && event.target !== event.currentTarget) return;
  var modal = document.getElementById('becomeSellerModal');
  if (!modal) return;
  modal.classList.remove('bsm-open');
  modal.setAttribute('aria-hidden', 'true');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    closeCustSidebar();
    closeBecomeSellerModal();
  }
});

@if(session('rider_err'))
// Auto-open sidebar and focus the PIN input when there's an access error
document.addEventListener('DOMContentLoaded', function () {
  openCustSidebar();
  setTimeout(function () {
    var inp = document.getElementById('riderPinInput');
    if (inp) { inp.scrollIntoView({ block: 'center' }); inp.focus(); }
  }, 320);
});
@endif

// PIN input: alphanumeric uppercase + loading state on submit
(function () {
  var pinInput = document.getElementById('riderPinInput');
  var pinForm  = document.getElementById('riderAccessForm');
  var pinBtn   = document.getElementById('riderPinBtn');
  if (pinInput) {
    pinInput.addEventListener('input', function () {
      this.value = this.value.replace(/[^A-Za-z0-9]/g, '').slice(0, 8).toUpperCase();
    });
  }
  if (pinForm && pinBtn) {
    pinForm.addEventListener('submit', function () {
      pinBtn.disabled = true;
      pinBtn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:.8rem;height:.8rem"></span>&nbsp;Verifying…';
    });
  }
})();
</script>

@if(session('msg') || session('error') || session('err') || session('warn'))
<div style="padding-top:68px;padding-left:clamp(12px,3vw,24px);padding-right:clamp(12px,3vw,24px)">
  @if(session('msg'))<div class="alert alert-success border-0 py-2 d-flex align-items-center gap-2 cs-flash"><i class="bi bi-check-circle-fill me-1"></i>{{ session('msg') }}</div>@endif
  @if(session('error')||session('err'))<div class="alert alert-danger border-0 py-2 d-flex align-items-center gap-2 cs-flash"><i class="bi bi-exclamation-circle-fill me-1"></i>{{ session('error') ?? session('err') }}</div>@endif
  @if(session('warn'))<div class="alert alert-warning border-0 py-2 d-flex align-items-center gap-2 cs-flash"><i class="bi bi-exclamation-triangle-fill me-1"></i>{{ session('warn') }}</div>@endif
</div>
@endif


<main class="customer-wrap">@yield('content')</main>
<footer class="text-center py-4 mt-5" style="color:#aaa;font-size:clamp(.74rem,1.5vw,.82rem)">
  @if(!empty($brandLogo))
    <img src="{{ $brandLogo }}" style="height:32px;width:auto;object-fit:contain;border-radius:6px;margin-bottom:.4rem;display:block;margin-left:auto;margin-right:auto" onerror="this.style.display='none'">
  @endif
  &copy; {{ date('Y') }} {{ $brandTitle }}. All rights reserved.
</footer>

@endif
{{-- ══ END of admin/customer layout split — scripts below load for EVERYONE ══ --}}

{{-- ── Toast container ── --}}
<div id="csToastContainer"></div>

{{-- ── Custom Dialog (pure CSS/JS, no Bootstrap modal dependency) ── --}}
<div id="csDlgBackdrop" onclick="csDlgBgClick(event)"
     style="display:none;position:fixed;inset:0;z-index:10500;background:rgba(0,0,0,0);transition:background .22s ease;overflow-y:auto">
  <div style="display:flex;align-items:center;justify-content:center;min-height:100%;padding:1rem">
    <div id="csDlgBox"
         style="background:#fff;border-radius:1.4rem;padding:1.75rem;max-width:400px;width:100%;
                box-shadow:0 24px 64px rgba(0,0,0,.22);
                transform:scale(.85) translateY(20px);opacity:0;
                transition:transform .32s cubic-bezier(.34,1.56,.64,1),opacity .22s ease">
      <div style="text-align:center;margin-bottom:.75rem">
        <div id="csDlgIconCircle" style="display:inline-flex;align-items:center;justify-content:center;width:52px;height:52px;border-radius:50%;background:#fff0f6">
          <i id="csDlgIcon" class="bi bi-question-circle" style="font-size:1.5rem;color:var(--primary)"></i>
        </div>
      </div>
      <div id="csDlgTitle" style="font-size:1.05rem;font-weight:700;text-align:center;color:#111827;margin-bottom:.4rem"></div>
      <div id="csDlgMsg"   style="color:#6b7280;text-align:center;font-size:.88rem;line-height:1.65;margin-bottom:1.5rem;white-space:pre-line"></div>
      <div id="csDlgInputWrap" style="display:none;margin-bottom:1.25rem">
        <input id="csDlgInput" type="text" class="form-control" style="text-align:center">
      </div>
      <div id="csDlgBtns" style="display:flex;gap:.6rem;justify-content:center;flex-wrap:wrap"></div>
    </div>
  </div>
</div>

{{-- ── Global Lightbox (image / PDF viewer) ── --}}
<div id="csLightbox"
     style="display:none;position:fixed;inset:0;z-index:10200;
            background:rgba(0,0,0,0);align-items:center;justify-content:center;
            transition:background .25s ease;overflow:hidden"
     onclick="if(event.target.id==='csLightbox')csLightboxClose()">
  <button id="csLbClose" onclick="csLightboxClose()"
          style="position:absolute;top:18px;right:20px;z-index:3;
                 background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.18);
                 color:#fff;width:46px;height:46px;border-radius:50%;font-size:1.1rem;
                 cursor:pointer;display:flex;align-items:center;justify-content:center;
                 transition:background .15s;opacity:0"
          onmouseover="this.style.background='rgba(255,255,255,.28)'"
          onmouseout="this.style.background='rgba(255,255,255,.15)'">
    <i class="bi bi-x-lg"></i>
  </button>
  <div id="csLbTitle"
       style="position:absolute;top:22px;left:20px;z-index:3;
              color:rgba(255,255,255,.9);font-size:.9rem;font-weight:600;
              max-width:calc(100% - 110px);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
              opacity:0;transition:opacity .25s .1s"></div>
  <div id="csLbImgWrap"
       style="display:flex;align-items:center;justify-content:center;
              width:100%;height:100%;padding:72px 24px 82px;overflow:hidden;
              transform:scale(0.88);opacity:0;
              transition:transform .32s cubic-bezier(.34,1.56,.64,1),opacity .22s ease">
    <img id="csLbImg" src="" alt=""
         style="max-width:100%;max-height:100%;object-fit:contain;
                border-radius:.75rem;transition:transform .25s ease;cursor:zoom-in;user-select:none"
         onclick="csLbToggleZoom()" ondragstart="return false">
  </div>
  <div id="csLbPdfWrap"
       style="display:none;width:100%;height:100%;padding:72px 20px 20px;
              transform:scale(0.88);opacity:0;
              transition:transform .32s cubic-bezier(.34,1.56,.64,1),opacity .22s ease">
    <iframe id="csLbPdf" src=""
            style="width:100%;height:100%;border:none;border-radius:.75rem;background:#fff"></iframe>
  </div>
  <div id="csLbZoomBar"
       style="position:absolute;bottom:18px;left:50%;transform:translateX(-50%);
              display:flex;gap:8px;align-items:center;z-index:3;
              background:rgba(0,0,0,.5);backdrop-filter:blur(8px);
              padding:6px 12px;border-radius:40px;
              opacity:0;transition:opacity .25s .15s">
    <button onclick="csLbZoom(-0.25)"
            style="background:rgba(255,255,255,.15);border:none;color:#fff;
                   width:36px;height:36px;border-radius:50%;font-size:1.2rem;
                   cursor:pointer;line-height:1;display:flex;align-items:center;justify-content:center">−</button>
    <span id="csLbZoomLbl" style="color:rgba(255,255,255,.85);font-size:.8rem;min-width:42px;text-align:center">100%</span>
    <button onclick="csLbZoom(0.25)"
            style="background:rgba(255,255,255,.15);border:none;color:#fff;
                   width:36px;height:36px;border-radius:50%;font-size:1.2rem;
                   cursor:pointer;line-height:1;display:flex;align-items:center;justify-content:center">+</button>
    <div style="width:1px;height:20px;background:rgba(255,255,255,.2);margin:0 4px"></div>
    <button onclick="csLbReset()"
            style="background:rgba(255,255,255,.12);border:none;color:rgba(255,255,255,.7);
                   padding:0 12px;height:36px;border-radius:20px;font-size:.75rem;cursor:pointer">Reset</button>
  </div>
  <div id="csLbHint"
       style="position:absolute;bottom:62px;left:50%;transform:translateX(-50%);
              color:rgba(255,255,255,.3);font-size:.7rem;white-space:nowrap;z-index:3;
              opacity:0;transition:opacity .25s .2s">
    Click image to toggle zoom &bull; Scroll to zoom &bull; ESC to close
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/cakeshop.js') }}"></script>
<script>
// ── Sidebar ──
// ── Sidebar (always defined as stubs, real impl for admin) ──
function toggleSidebar() {
  var sb = document.getElementById('adminSidebar') || document.getElementById('sellerSidebar');
  var ov = document.getElementById('sbOverlay') || document.getElementById('sellerOverlay');
  var mn = document.getElementById('adminMain') || document.getElementById('sellerMain');
  if (!sb) return;
  if (window.innerWidth < 768) {
    var o = sb.classList.toggle('mobile-open');
    if (ov) ov.classList.toggle('active', o);
  } else {
    var col = sb.classList.toggle('collapsed');
    if (mn) mn.classList.toggle('expanded', col);
    try { localStorage.setItem('cakeshop_sb', col ? 'collapsed' : 'expanded'); } catch(e) {}
  }
}
function closeSidebar() {
  var sb = document.getElementById('adminSidebar') || document.getElementById('sellerSidebar');
  var ov = document.getElementById('sbOverlay') || document.getElementById('sellerOverlay');
  if (sb) sb.classList.remove('mobile-open');
  if (ov) ov.classList.remove('active');
}
@if($isAdmin)
(function() {
  var sb = document.getElementById('adminSidebar');
  var mn = document.getElementById('adminMain');
  if (!sb || !mn) return;
  function applyState() {
    if (window.innerWidth < 768) {
      sb.classList.remove('collapsed'); mn.classList.remove('expanded');
    } else {
      var stored; try { stored = localStorage.getItem('cakeshop_sb'); } catch(e) { stored = null; }
      var col = stored === 'collapsed';
      sb.classList.toggle('collapsed', col); mn.classList.toggle('expanded', col);
    }
  }
  applyState();
  window.addEventListener('resize', applyState);
})();
@endif

// ── Custom Dialog System ─────────────────────────────────────────
@if($isSeller)
(function() {
  var sb = document.getElementById('sellerSidebar');
  var mn = document.getElementById('sellerMain');
  if (!sb || !mn) return;
  function applyState() {
    if (window.innerWidth < 768) {
      sb.classList.remove('collapsed'); mn.classList.remove('expanded');
    } else {
      var stored; try { stored = localStorage.getItem('cakeshop_sb'); } catch(e) { stored = null; }
      var col = stored === 'collapsed';
      sb.classList.toggle('collapsed', col); mn.classList.toggle('expanded', col);
    }
  }
  applyState();
  window.addEventListener('resize', applyState);
})();
@endif

var _csDlgOkCb = null, _csDlgCancelCb = null;

function _csDlgOpen() {
  var bd = document.getElementById('csDlgBackdrop');
  var box = document.getElementById('csDlgBox');
  bd.style.display = 'block';
  document.body.style.overflow = 'hidden';
  requestAnimationFrame(function() {
    bd.style.background = 'rgba(0,0,0,.48)';
    box.style.transform = 'scale(1) translateY(0)';
    box.style.opacity = '1';
  });
}
function _csDlgClose(cb) {
  var bd = document.getElementById('csDlgBackdrop');
  var box = document.getElementById('csDlgBox');
  bd.style.background = 'rgba(0,0,0,0)';
  box.style.transform = 'scale(.85) translateY(20px)';
  box.style.opacity = '0';
  setTimeout(function() {
    bd.style.display = 'none';
    document.body.style.overflow = '';
    if (cb) cb();
  }, 260);
}
function _csDlgOk() {
  var cb = _csDlgOkCb;
  var inputWrap = document.getElementById('csDlgInputWrap');
  var val = inputWrap && inputWrap.style.display !== 'none' ? document.getElementById('csDlgInput').value : null;
  _csDlgOkCb = null; _csDlgCancelCb = null;
  _csDlgClose(function() { if (cb) cb(val); });
}
function _csDlgCancel() {
  var cb = _csDlgCancelCb;
  _csDlgOkCb = null; _csDlgCancelCb = null;
  _csDlgClose(function() { if (cb) cb(); });
}
function csDlgBgClick(e) {
  var bd = document.getElementById('csDlgBackdrop');
  if (e.target === bd || e.target === bd.firstElementChild) _csDlgCancel();
}
function _csDlgBuild(opts) {
  document.getElementById('csDlgIconCircle').style.background = opts.iconBg || '#fff0f6';
  var ic = document.getElementById('csDlgIcon');
  ic.className = 'bi ' + (opts.icon || 'bi-question-circle');
  ic.style.color = opts.iconColor || 'var(--primary)';
  document.getElementById('csDlgTitle').textContent = opts.title || '';
  document.getElementById('csDlgMsg').textContent   = opts.message || '';
  var iw = document.getElementById('csDlgInputWrap');
  var inp = document.getElementById('csDlgInput');
  if (opts.prompt) {
    iw.style.display = '';
    inp.value = opts.defaultVal || '';
    inp.placeholder = opts.placeholder || '';
    inp.readOnly = !!opts.readOnly;
    setTimeout(function() { opts.readOnly ? inp.select() : inp.focus(); }, 350);
  } else { iw.style.display = 'none'; }
  var btns = document.getElementById('csDlgBtns');
  btns.innerHTML = '';
  _csDlgOkCb = opts.onConfirm || null;
  _csDlgCancelCb = opts.onCancel || null;
  if (opts.showCancel !== false) {
    var cb = document.createElement('button');
    cb.type = 'button'; cb.className = 'btn btn-outline-secondary flex-fill';
    cb.textContent = opts.cancelLabel || 'Cancel';
    cb.onclick = _csDlgCancel; btns.appendChild(cb);
  }
  var ob = document.createElement('button');
  ob.type = 'button'; ob.className = 'btn flex-fill fw-semibold';
  ob.style.cssText = 'background:' + (opts.okColor||'var(--primary)') + ';color:#fff;border:none';
  ob.textContent = opts.okLabel || 'OK';
  ob.onclick = _csDlgOk; btns.appendChild(ob);
  _csDlgOpen();
}

// Public API — keep same cakeConfirm signature for backward compat
function cakeConfirm({title='Are you sure?',message='',icon='bi-question-circle',iconBg='#fff0f6',iconColor='var(--primary)',okLabel='Confirm',okColor='var(--primary)',onConfirm,onCancel}) {
  _csDlgBuild({title,message,icon,iconBg,iconColor,okLabel,okColor,onConfirm,onCancel,showCancel:true});
}
function confirmDelete(msg,cb) { cakeConfirm({title:'Delete?',message:msg,icon:'bi-trash',iconBg:'#fff1f2',iconColor:'#ef4444',okLabel:'Delete',okColor:'#ef4444',onConfirm:cb}); }
function confirmAction(title,msg,cb) { cakeConfirm({title,message:msg,onConfirm:cb}); }

// csAlert — replaces alert()
function csAlert(msg,opts) {
  opts = opts || {};
  showToast(msg, opts.type || 'info', opts.duration || 4200);
  if (typeof opts.onOk === 'function') setTimeout(opts.onOk, 0);
}
// csPrompt — read-only copy box
function csPrompt(label,val) {
  _csDlgBuild({title:'Copy',message:label,icon:'bi-clipboard',iconBg:'#f0fdf4',iconColor:'#16a34a',
    prompt:true,readOnly:true,defaultVal:val||'',okLabel:'Done',showCancel:false});
}
// csTrackPrompt — order tracking input
function csTrackPrompt() {
  _csDlgBuild({title:'Track Your Order',message:'Enter your order tracking code:',
    icon:'bi-search',iconBg:'#fff0f6',iconColor:'var(--primary)',
    prompt:true,placeholder:'e.g. TRK-12345',okLabel:'Track',showCancel:true,
    onConfirm:function(val){ if(val&&val.trim()) window.location='/track/'+val.trim(); }});
}

// Override native browser dialogs
window.alert = csAlert;

// Auto-intercept: data-cs-confirm on any clickable element
document.addEventListener('click', function(e) {
  var el = e.target.closest('[data-cs-confirm]');
  if (!el) return;
  var msg = el.getAttribute('data-cs-confirm');
  e.preventDefault(); e.stopImmediatePropagation();
  cakeConfirm({
    title:  el.getAttribute('data-cs-title')    || 'Are you sure?',
    message: msg,
    icon:   el.getAttribute('data-cs-icon')     || 'bi-question-circle',
    iconBg: el.getAttribute('data-cs-icon-bg')  || '#fff0f6',
    iconColor: el.getAttribute('data-cs-icon-color') || 'var(--primary)',
    okLabel: el.getAttribute('data-cs-ok')      || 'Confirm',
    okColor: el.getAttribute('data-cs-ok-color')|| 'var(--primary)',
    onConfirm: function() {
      el.removeAttribute('data-cs-confirm');
      if (el.tagName==='A' && el.href && el.href!==window.location.href+'#') {
        if (typeof window.csLoadingStart === 'function') window.csLoadingStart();
        if (typeof window.csBeginSmartLoading === 'function') {
          window.csBeginSmartLoading(document.querySelector('.admin-page') || document.querySelector('.customer-wrap'), {
            title:'Opening page',
            sub:'Getting the next screen ready...'
          }, 120);
        }
        el.target==='_blank' ? window.open(el.href) : (window.location.href=el.href);
      } else if (el.tagName==='FORM') {
        if (typeof window.csLoadingStart === 'function') window.csLoadingStart();
        if (typeof window.csBeginSmartLoading === 'function') {
          window.csBeginSmartLoading(el.closest('.card,.admin-page,.customer-wrap') || document.querySelector('.admin-page') || document.querySelector('.customer-wrap'), {
            title:'Saving changes',
            sub:'Please wait while we process this request...'
          }, 120);
        }
        el.submit();
      } else {
        if (typeof window.csSetButtonLoading === 'function') window.csSetButtonLoading(el);
        el.click();
        setTimeout(function(){ el.setAttribute('data-cs-confirm',msg); }, 200);
      }
    }
  });
}, true);

// Auto-intercept: data-cs-confirm on forms
document.addEventListener('submit', function(e) {
  var form = e.target;
  var msg = form.getAttribute('data-cs-confirm');
  if (!msg) return;
  e.preventDefault();
  cakeConfirm({
    title:  form.getAttribute('data-cs-title')   || 'Are you sure?',
    message: msg,
    icon:   form.getAttribute('data-cs-icon')    || 'bi-question-circle',
    iconBg: form.getAttribute('data-cs-icon-bg') || '#fff0f6',
    iconColor: 'var(--primary)',
    okLabel: form.getAttribute('data-cs-ok')     || 'Confirm',
    okColor: 'var(--primary)',
    onConfirm: function() {
      form.removeAttribute('data-cs-confirm');
      if (typeof window.csLoadingStart === 'function') window.csLoadingStart();
      if (typeof window.csBeginSmartLoading === 'function') {
        window.csBeginSmartLoading(form.closest('.card,.admin-page,.customer-wrap') || document.querySelector('.admin-page') || document.querySelector('.customer-wrap'), {
          title:'Saving changes',
          sub:'Please wait while we process this request...'
        }, 120);
      }
      var submitter = form.querySelector('button[type="submit"],input[type="submit"],button:not([type])');
      if (typeof window.csSetButtonLoading === 'function') window.csSetButtonLoading(submitter);
      form.submit();
    }
  });
}, true);

// ESC closes lightbox or dialog; +/- to zoom lightbox
document.addEventListener('keydown', function(e) {
  var lb = document.getElementById('csLightbox');
  if (lb && lb.style.display !== 'none') {
    if (e.key === 'Escape')             { if (typeof window.csLightboxClose==='function') window.csLightboxClose(); return; }
    if (e.key === '+' || e.key === '=') { if (typeof window.csLbZoom==='function') window.csLbZoom(0.25); return; }
    if (e.key === '-')                  { if (typeof window.csLbZoom==='function') window.csLbZoom(-0.25); return; }
    return;
  }
  var bd = document.getElementById('csDlgBackdrop');
  if (e.key==='Escape' && bd && bd.style.display!=='none') _csDlgCancel();
});

// Clean up stuck Bootstrap backdrops on every page load
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.modal-backdrop').forEach(function(el){ el.remove(); });
  document.body.classList.remove('modal-open');
  document.body.style.overflow = '';
  document.body.style.paddingRight = '';
  document.addEventListener('hidden.bs.modal', function() {
    if (!document.querySelector('.modal.show')) {
      document.querySelectorAll('.modal-backdrop').forEach(function(el){ el.remove(); });
      document.body.classList.remove('modal-open');
      document.body.style.overflow = '';
      document.body.style.paddingRight = '';
    }
  });
});

// ── Toast ──
// Smart loading feedback for navigation, fetch calls, forms, and images
(function() {
  var progress = document.getElementById('csTopProgress');
  var bar = document.getElementById('csTopProgressBar');
  var activeLoads = 0;
  var hideTimer = null;
  var pct = 0;
  var surfaceTimer = null;
  var activeSurface = null;

  function pageSurface() {
    return document.querySelector('.admin-page') || document.querySelector('.customer-wrap') || document.querySelector('main') || document.body;
  }

  function loadingMarkup(title, sub) {
    return '' +
      '<div class="cs-loading-panel" aria-hidden="true">' +
        '<div class="cs-loading-panel-inner">' +
          '<div class="cs-loading-head">' +
            '<span class="cs-loading-orbit"></span>' +
            '<div><div class="cs-loading-title">' + title + '</div><div class="cs-loading-sub">' + sub + '</div></div>' +
          '</div>' +
          '<div class="cs-loading-lines">' +
            '<div class="cs-skeleton cs-skeleton-line" style="width:88%"></div>' +
            '<div class="cs-skeleton cs-skeleton-line" style="width:68%"></div>' +
            '<div class="cs-skeleton cs-skeleton-line" style="width:78%"></div>' +
          '</div>' +
        '</div>' +
      '</div>';
  }

  function ensurePanel(surface) {
    if (!surface || surface === document.body) return null;
    if (!surface.classList.contains('cs-loading-surface')) surface.classList.add('cs-loading-surface');
    var panel = surface.querySelector(':scope > .cs-loading-panel');
    if (!panel) {
      var wrap = document.createElement('div');
      wrap.innerHTML = loadingMarkup('Loading content', 'Preparing a smooth view...');
      panel = wrap.firstElementChild;
      surface.appendChild(panel);
    }
    return panel;
  }

  window.csShowSmartLoading = function(surface, opts) {
    opts = opts || {};
    surface = surface || pageSurface();
    if (!surface || surface === document.body) return;
    clearTimeout(surfaceTimer);
    activeSurface = surface;
    var panel = ensurePanel(surface);
    if (panel) {
      var title = panel.querySelector('.cs-loading-title');
      var sub = panel.querySelector('.cs-loading-sub');
      if (title) title.textContent = opts.title || 'Loading content';
      if (sub) sub.textContent = opts.sub || 'Preparing a smooth view...';
    }
    document.body.classList.add('cs-page-busy');
    surface.classList.add('cs-surface-loading');
  };

  window.csHideSmartLoading = function() {
    clearTimeout(surfaceTimer);
    if (activeSurface) activeSurface.classList.remove('cs-surface-loading');
    document.body.classList.remove('cs-page-busy');
    activeSurface = null;
  };

  window.csBeginSmartLoading = function(surface, opts, delay) {
    clearTimeout(surfaceTimer);
    surfaceTimer = setTimeout(function() {
      window.csShowSmartLoading(surface, opts);
    }, typeof delay === 'number' ? delay : 120);
  };

  window.csRenderSkeleton = function(target, type, count) {
    var el = typeof target === 'string' ? document.querySelector(target) : target;
    if (!el) return;
    type = type || 'list';
    count = Math.max(1, Math.min(parseInt(count || 3, 10), 8));
    var html = '';
    for (var i = 0; i < count; i++) {
      if (type === 'card') {
        html += '<div class="cs-skeleton-card mb-3"><div class="cs-skeleton cs-skeleton-thumb mb-3"></div><div class="cs-skeleton cs-skeleton-title"></div><div class="cs-skeleton cs-skeleton-line" style="width:92%"></div><div class="cs-skeleton cs-skeleton-line" style="width:64%"></div></div>';
      } else if (type === 'table') {
        html += '<tr><td colspan="12"><div class="cs-skeleton cs-skeleton-line" style="width:96%"></div><div class="cs-skeleton cs-skeleton-line" style="width:72%"></div></td></tr>';
      } else {
        html += '<div class="d-flex align-items-center gap-3 mb-3"><div class="cs-skeleton cs-skeleton-avatar"></div><div class="flex-grow-1"><div class="cs-skeleton cs-skeleton-title"></div><div class="cs-skeleton cs-skeleton-line" style="width:92%"></div><div class="cs-skeleton cs-skeleton-line" style="width:58%"></div></div></div>';
      }
    }
    el.innerHTML = html;
  };

  function setProgress(next) {
    if (!bar) return;
    pct = Math.max(pct, Math.min(next, 96));
    bar.style.width = pct + '%';
  }

  window.csLoadingStart = function() {
    if (!progress || !bar) return;
    activeLoads += 1;
    clearTimeout(hideTimer);
    progress.classList.remove('cs-done');
    progress.classList.add('cs-loading');
    if (pct <= 0 || pct >= 100) {
      pct = 8;
      bar.style.width = pct + '%';
    }
    setProgress(pct + 14);
  };

  window.csLoadingDone = function(force) {
    if (!progress || !bar) return;
    activeLoads = force ? 0 : Math.max(0, activeLoads - 1);
    if (activeLoads > 0) return;
    progress.classList.add('cs-done');
    bar.style.width = '100%';
    hideTimer = setTimeout(function() {
      progress.classList.remove('cs-loading', 'cs-done');
      bar.style.width = '0';
      pct = 0;
    }, 320);
  };

  function shouldTrackLink(a, event) {
    if (!a || !a.href || a.target === '_blank' || a.hasAttribute('download')) return false;
    if (event && (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0)) return false;
    if (a.dataset.csNoLoading === 'true') return false;
    var url;
    try { url = new URL(a.href, window.location.href); } catch(e) { return false; }
    if (url.origin !== window.location.origin) return false;
    if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return false;
    var href = a.getAttribute('href') || '';
    if (href === '#' || href.indexOf('javascript:') === 0) return false;
    return true;
  }

  document.addEventListener('click', function(event) {
    var a = event.target.closest ? event.target.closest('a') : null;
    if (shouldTrackLink(a, event)) {
      window.csLoadingStart();
      window.csBeginSmartLoading(pageSurface(), {
        title:'Opening page',
        sub:'Getting the next screen ready...'
      }, 180);
    }
  }, true);

  document.addEventListener('submit', function(event) {
    var form = event.target;
    if (!form || form.dataset.csNoLoading === 'true') return;
    if (form.hasAttribute('data-cs-confirm')) return;
    setTimeout(function() {
      if (event.defaultPrevented) return;
      window.csLoadingStart();
      var submitter = event.submitter || form.querySelector('button[type="submit"],input[type="submit"],button:not([type])');
      window.csSetButtonLoading(submitter);
      window.csBeginSmartLoading(form.closest('.card,.cs-loading-surface,.admin-page,.customer-wrap') || pageSurface(), {
        title:'Saving changes',
        sub:'Please wait while we process this request...'
      }, 220);
    }, 0);
  });

  window.addEventListener('pageshow', function() { window.csLoadingDone(true); window.csHideSmartLoading(); });
  window.addEventListener('beforeunload', function() { window.csLoadingStart(); });

  if (window.fetch) {
    var nativeFetch = window.fetch.bind(window);
    window.fetch = function() {
      var started = false;
      var timer = setTimeout(function() {
        started = true;
        window.csLoadingStart();
      }, 140);
      return nativeFetch.apply(null, arguments).finally(function() {
        clearTimeout(timer);
        if (started) window.csLoadingDone();
      });
    };
  }

  window.csSetButtonLoading = function(button, label) {
    if (!button || button.dataset.csLoading === 'true' || button.disabled) return;
    var tag = (button.tagName || '').toLowerCase();
    if (tag !== 'button' && !(tag === 'input' && /submit|button/i.test(button.type || ''))) return;
    button.dataset.csLoading = 'true';
    button.dataset.csOriginalHtml = tag === 'input' ? button.value : button.innerHTML;
    button.disabled = true;
    button.classList.add('cs-btn-loading');
    var text = label || button.getAttribute('data-loading-text') || 'Processing...';
    if (tag === 'input') {
      button.value = text;
    } else {
      button.innerHTML = '<span class="cs-btn-spinner me-2"></span><span>' + text + '</span>';
    }
  };

  window.csResetButtonLoading = function(button) {
    if (!button || button.dataset.csLoading !== 'true') return;
    var tag = (button.tagName || '').toLowerCase();
    button.disabled = false;
    button.classList.remove('cs-btn-loading');
    if (tag === 'input') button.value = button.dataset.csOriginalHtml || button.value;
    else button.innerHTML = button.dataset.csOriginalHtml || button.innerHTML;
    delete button.dataset.csLoading;
    delete button.dataset.csOriginalHtml;
  };
})();

function showToast(message,type='success',duration=3500) {
  const icons={success:'bi-check-circle-fill',error:'bi-exclamation-circle-fill',warning:'bi-exclamation-triangle-fill',info:'bi-info-circle-fill'};
  const c=document.getElementById('csToastContainer');
  const t=document.createElement('div'); t.className='cs-toast cs-toast-'+type;
  t.innerHTML='<i class="bi '+icons[type]+' cs-toast-icon" style="font-size:1.1rem;flex-shrink:0"></i><span>'+message+'</span>';
  c.appendChild(t);
  setTimeout(()=>{ t.classList.add('hiding'); setTimeout(()=>t.remove(),260); },duration);
}

// ── Auto-dismiss flash ──
document.querySelectorAll('.cs-flash').forEach(el => {
  setTimeout(() => { el.style.transition='opacity .4s'; el.style.opacity='0'; setTimeout(()=>el.remove(),420); }, 4000);
});

// ── Pagination ──
window.csPagers = {};
function csPagination(containerId, itemSelector, opts={}) {
  const { perPage=10, updateCount=null } = opts;
  const container = document.getElementById(containerId);
  if (!container) return;
  let currentPage=1, allItems=[], activeItems=[];

  function getItems() { allItems=[...container.querySelectorAll(itemSelector)]; activeItems=allItems; }

  function render() {
    const s=(currentPage-1)*perPage, e=s+perPage;
    allItems.forEach(el=>el.style.display='none');
    activeItems.forEach((el,i)=>{ el.style.display=(i>=s&&i<e)?'':' none'; });
    // fix space above
    activeItems.forEach((el,i)=>{ el.style.display=(i>=s&&i<e)?'':'none'; });
    renderPager();
    if (updateCount) updateCount(activeItems.length);
  }

  function renderPager() {
    const total = Math.ceil(activeItems.length / perPage);
    const pager = document.getElementById(containerId + '_pager');
    if (!pager) return;
    if (total <= 1) { pager.innerHTML = ''; return; }
    const cid = containerId;
    let html = '<div class="cs-pagination">';
    html += '<button class="cs-page-btn" ' + (currentPage===1?'disabled':'onclick="csPagers[\"'+cid+'\"].go(' + (currentPage-1) + ')"')+'><i class="bi bi-chevron-left"></i></button>';
    buildRange(currentPage, total).forEach(p => {
      if (p === '...') { html += '<button class="cs-page-btn dots">…</button>'; return; }
      const isActive = p === currentPage;
      html += '<button class="cs-page-btn ' + (isActive?'active':'')+'" '+(isActive?'':' onclick="csPagers[\"'+cid+'\"].go('+p+')"')+'>'+p+'</button>';
    });
    html += '<button class="cs-page-btn" ' + (currentPage===total?'disabled':'onclick="csPagers[\"'+cid+'\"].go(' + (currentPage+1) + ')"')+'><i class="bi bi-chevron-right"></i></button>';
    html += '<span class="ms-1 text-muted" style="font-size:.78rem">' + activeItems.length + ' item' + (activeItems.length!==1?'s':'')+'</span></div>';
    pager.innerHTML = html;
  }

  function buildRange(cur,total) {
    if (total<=7) return Array.from({length:total},(_,i)=>i+1);
    if (cur<=4)  return [1,2,3,4,5,'...',total];
    if (cur>=total-3) return [1,'...',total-4,total-3,total-2,total-1,total];
    return [1,'...',cur-1,cur,cur+1,'...',total];
  }

  function filterItems(searchText, statusFilter) {
    searchText=(searchText||'').toLowerCase();
    activeItems=allItems.filter(el=>{
      const ms=!searchText||(el.dataset.search||'').toLowerCase().includes(searchText);
      const mf=!statusFilter||statusFilter==='All'||statusFilter==='all'||
               (el.dataset.status||'').toLowerCase()===statusFilter.toLowerCase()||
               (el.dataset.filter||'').toLowerCase()===statusFilter.toLowerCase();
      return ms&&mf;
    });
    currentPage=1; render();
  }

  getItems(); render();
  csPagers[containerId] = {
    go(p) { const t=Math.ceil(activeItems.length/perPage); if(p<1||p>t)return; currentPage=p; render(); container.scrollIntoView({behavior:'smooth',block:'start'}); },
    filter: filterItems,
    refresh() { getItems(); render(); }
  };
}

// ── Topbar scroll shadow ──
(function() {
  const topbar = document.getElementById('adminTopbar') || document.getElementById('sellerTopbar');
  if (!topbar) return;
  const main = document.getElementById('adminMain') || document.getElementById('sellerMain');
  const scroller = main || window;
  function onScroll() {
    const scrolled = (main ? main.scrollTop : window.scrollY) > 8;
    topbar.classList.toggle('cs-scrolled', scrolled);
  }
  scroller.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
})();

// ── Count-up animation for stat numbers ──
function csCountUp(el, target, duration) {
  const isNum = /^[\d,\.]+$/.test(target.replace(/[₱%+]/g,'').trim());
  if (!isNum) return;
  const prefix = target.match(/^[₱+]/) ? target[0] : '';
  const suffix = target.match(/[%+]$/) ? target[target.length-1] : '';
  const raw    = parseFloat(target.replace(/[^0-9.]/g,'')) || 0;
  const isFloat = target.includes('.');
  const decimals = isFloat ? (target.split('.')[1]?.replace(/[^0-9]/g,'').length || 0) : 0;
  const start = performance.now();
  function tick(now) {
    const p = Math.min((now - start) / duration, 1);
    const ease = 1 - Math.pow(1 - p, 3);
    const cur = raw * ease;
    el.textContent = prefix + (decimals > 0 ? cur.toFixed(decimals) : Math.floor(cur).toLocaleString()) + suffix;
    if (p < 1) requestAnimationFrame(tick);
    else el.textContent = target;
  }
  requestAnimationFrame(tick);
}

// ── Intersection observer: trigger stagger + count-up on first view ──
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('form:not([data-cs-no-loading])').forEach(function(form) {
    var btn = form.querySelector('button[type="submit"],input[type="submit"],button:not([type])');
    if (!btn || btn.hasAttribute('data-loading-text')) return;
    var label = (btn.tagName || '').toLowerCase() === 'input' ? (btn.value || '') : (btn.textContent || '');
    label = label.replace(/\s+/g, ' ').trim().toLowerCase();
    var next = 'Processing...';
    if (/send|message|reply|otp|email|sms/.test(label)) next = 'Sending...';
    else if (/save|update|restore|approve|reject|archive|delete|submit|apply|confirm|mark|resend|cancel/.test(label)) next = 'Saving...';
    else if (/pay|gcash|deposit|checkout|order|place/.test(label)) next = 'Preparing payment...';
    else if (/login|verify|sign in/.test(label)) next = 'Verifying...';
    btn.setAttribute('data-loading-text', next);
  });

  document.querySelectorAll('table.table').forEach(function(table) {
    if (table.closest('.table-responsive, .table-wrap, .cs-responsive-table')) return;
    var wrap = document.createElement('div');
    wrap.className = 'cs-responsive-table';
    table.parentNode.insertBefore(wrap, table);
    wrap.appendChild(table);
  });

  document.querySelectorAll('.admin-page img, .customer-wrap img').forEach(function(img) {
    if (!img.hasAttribute('loading')) img.setAttribute('loading', 'lazy');
    if (!img.hasAttribute('decoding')) img.setAttribute('decoding', 'async');
    if (!img.complete) {
      img.classList.add('cs-img-loading');
      img.addEventListener('load', function() {
        img.classList.remove('cs-img-loading');
        img.classList.add('cs-img-loaded');
      }, { once: true });
      img.addEventListener('error', function() {
        img.classList.remove('cs-img-loading');
      }, { once: true });
    } else {
      img.classList.add('cs-img-loaded');
    }
  });

  // Count-up on .cs-stat-num
  const iObs = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const el = entry.target;
      const orig = el.dataset.target || el.textContent.trim();
      el.dataset.target = orig;
      csCountUp(el, orig, 900);
      iObs.unobserve(el);
    });
  }, { threshold: 0.4 });
  document.querySelectorAll('.cs-stat-num').forEach(el => iObs.observe(el));

  // Stagger children that are initially off-screen
  const sObs = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      entry.target.style.animationPlayState = 'running';
    });
  }, { threshold: 0.05 });
  document.querySelectorAll('.cs-stagger > *').forEach(el => {
    el.style.animationPlayState = 'paused';
    sObs.observe(el);
  });
});
</script>

{{-- ── Mini Chat Popup ─────────────────────────────────────────── --}}
@if($isAdmin || $isSeller)
@php
  $popupDataUrl = $isAdmin ? route('admin.messages.popup_data') : route('seller.messages.popup_data');
  $popupSendUrl = $isAdmin ? route('admin.messages.popup_send') : route('seller.messages.popup_send');
  $csrfToken    = csrf_token();
  $fullMsgUrl   = $isAdmin ? route('admin.messages.index') : route('seller.messages');
@endphp
<style>
@@keyframes chatPopIn { from{opacity:0;transform:scale(.85) translateY(20px)} to{opacity:1;transform:scale(1) translateY(0)} }
@@keyframes chatPopOut { from{opacity:1;transform:scale(1) translateY(0)} to{opacity:0;transform:scale(.85) translateY(20px)} }
@@keyframes msgSlideIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }

#miniChat {
  position:fixed;
  bottom:104px;
  right:24px;
  width:min(320px, calc(100vw - 24px));
  height:min(420px, calc(100vh - 140px));
  max-height:calc(100dvh - 140px);
  background:white;
  border-radius:16px;
  box-shadow:0 8px 40px rgba(0,0,0,.18);
  z-index:8990;
  display:none;
  flex-direction:column;
  overflow:hidden;
  animation: chatPopIn .3s cubic-bezier(.34,1.56,.64,1);
}
#miniChat.closing { animation: chatPopOut .25s ease forwards; }

@@media(max-width:575px) {
  #miniChat {
    right:12px;
    bottom:82px;
    width:calc(100vw - 24px);
    height:min(420px, calc(100dvh - 104px));
    border-radius:14px;
  }
}

@@media(max-height:430px) and (orientation:landscape) {
  #miniChat {
    top:10px;
    bottom:10px;
    right:10px;
    height:auto;
    max-height:none;
    width:min(360px, calc(100vw - 20px));
  }
}

#miniChatHeader {
  background:linear-gradient(135deg,#e91e63,#c2185b);
  padding:12px 14px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  flex-shrink:0;
}
#miniChatMessages {
  flex:1;
  overflow-y:auto;
  padding:12px;
  display:flex;
  flex-direction:column;
  gap:6px;
  background:#f8f9fa;
}
#miniChatMessages::-webkit-scrollbar { width:4px; }
#miniChatMessages::-webkit-scrollbar-thumb { background:#e0e0e0; border-radius:2px; }

.mc-msg-wrap { display:flex; flex-direction:column; animation: msgSlideIn .2s ease; }
.mc-msg-wrap.me { align-items:flex-end; }
.mc-msg-wrap.them { align-items:flex-start; }
.mc-order-tag {
  font-size:.65rem;
  color:#aaa;
  margin-bottom:2px;
  padding:0 4px;
}
.mc-bubble {
  max-width:220px;
  padding:8px 12px;
  border-radius:18px;
  font-size:.82rem;
  line-height:1.4;
  word-break:break-word;
}
.mc-msg-wrap.me .mc-bubble {
  background:#e91e63;
  color:white;
  border-bottom-right-radius:4px;
}
.mc-msg-wrap.them .mc-bubble {
  background:white;
  color:#222;
  border-bottom-left-radius:4px;
  box-shadow:0 1px 4px rgba(0,0,0,.08);
}
.mc-time {
  font-size:.62rem;
  color:#bbb;
  margin-top:2px;
  padding:0 4px;
}
#miniChatInput {
  display:flex;
  align-items:center;
  gap:8px;
  padding:10px 12px;
  border-top:1px solid #f0f0f0;
  background:white;
  flex-shrink:0;
}
#mcInput {
  flex:1;
  border:1.5px solid #f0f0f0;
  border-radius:20px;
  padding:7px 14px;
  font-size:.82rem;
  outline:none;
  transition:border-color .2s;
  font-family:inherit;
}
#mcInput:focus { border-color:#e91e63; }
#mcSendBtn {
  width:34px; height:34px;
  border-radius:50%;
  background:#e91e63;
  border:none;
  color:white;
  cursor:pointer;
  display:flex; align-items:center; justify-content:center;
  flex-shrink:0;
  transition:background .2s;
}
#mcSendBtn:hover { background:#c2185b; }
</style>

<div id="miniChat">
  {{-- Header --}}
  <div id="miniChatHeader">
    <div style="display:flex;align-items:center;gap:8px">
      <div style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center">
        <i class="bi bi-cake2-fill" style="color:white;font-size:.9rem"></i>
      </div>
      <div>
        <div style="color:white;font-weight:700;font-size:.88rem">Cake Shop</div>
        <div style="color:rgba(255,255,255,.7);font-size:.7rem">Messages</div>
      </div>
    </div>
    <div style="display:flex;gap:6px;align-items:center">
      <a href="{{ $fullMsgUrl }}"
         style="color:rgba(255,255,255,.8);font-size:.75rem;text-decoration:none;background:rgba(255,255,255,.15);padding:4px 10px;border-radius:12px;white-space:nowrap"
         title="Open full messages">
        <i class="bi bi-arrows-fullscreen me-1"></i>Full
      </a>
      <button onclick="closeMiniChat()" style="background:rgba(255,255,255,.15);border:none;color:white;width:28px;height:28px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center">
        <i class="bi bi-x-lg" style="font-size:.75rem"></i>
      </button>
    </div>
  </div>

  {{-- Messages area --}}
  <div id="miniChatMessages">
    <div id="mcLoading" style="text-align:center;padding:20px;color:#bbb;font-size:.82rem">
      <i class="bi bi-three-dots" style="font-size:1.2rem"></i>
    </div>
  </div>

  {{-- Multi-image preview strip --}}
  <div id="mcImgPreviewBar" style="display:none;padding:8px 12px 4px;border-top:1px solid #f0f0f0;background:#fafafa">
    <div id="mcImgPreviewStrip" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center"></div>
  </div>

  {{-- Input --}}
  <div id="miniChatInput" style="display:none">
    <label for="mcImageInput" id="mcImgBtn" style="width:32px;height:32px;border-radius:50%;background:#f5f5f5;border:none;color:#aaa;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s;margin:0" title="Attach images">
      <i class="bi bi-paperclip" style="font-size:.85rem"></i>
      <input type="file" id="mcImageInput" accept="image/*" multiple hidden onchange="mcImageSelected(this)">
    </label>
    <input type="text" id="mcInput" placeholder="Aa" maxlength="500"
           onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();mcSend()}">
    <button id="mcSendBtn" onclick="mcSend()">
      <i class="bi bi-send-fill" style="font-size:.8rem"></i>
    </button>
  </div>
</div>

<script>
var MC_DATA_URL  = '{{ $popupDataUrl }}';
var MC_SEND_URL  = '{{ $popupSendUrl }}';
var MC_CSRF      = '{{ $csrfToken }}';
var MC_ROLE      = '{{ session("user")["role"] ?? "" }}';
var MC_USER_ID   = '{{ session("user")["id"] ?? "" }}';
@php
  $_mcRole = session('user')['role'] ?? '';
  $markOrderReadUrl = $_mcRole === 'admin'
    ? url('/admin/messages/mark-order-read')
    : ($_mcRole === 'seller' ? url('/seller/messages/mark-order-read') : url('/customer/messages/mark-order-read'));
@endphp
var MC_MARK_URL  = '{{ $markOrderReadUrl }}';

var mcOpen           = false;
var mcLatestOrderId  = null;
var mcActiveUserId   = null;
var mcViewState      = 'list';
var mcActiveCustomer = null;
var mcPollTimer      = null;
var mcSelectedImages = [];

// ── Open / Close ──────────────────────────────────────────────────────
window.toggleMiniChat = function toggleMiniChat() { mcOpen ? closeMiniChat() : openMiniChat(); };

function openMiniChat() {
  const chat = document.getElementById('miniChat');
  chat.style.display = 'flex';
  chat.classList.remove('closing');
  mcOpen = true;
  loadMcMessages();
  startMcPoll();
}

function closeMiniChat() {
  const chat = document.getElementById('miniChat');
  chat.classList.add('closing');
  stopMcPoll();
  setTimeout(() => { chat.style.display = 'none'; mcOpen = false; }, 240);
}

// ── Polling ───────────────────────────────────────────────────────────
function startMcPoll() {
  stopMcPoll();
  mcPollTimer = setInterval(() => {
    if (!mcOpen) { stopMcPoll(); return; }
    if (mcViewState === 'list') {
      silentRefreshList();
    } else if (mcViewState === 'conversation' && mcActiveCustomer) {
      silentRefreshConversation();
    }
  }, 5000);
}

function stopMcPoll() {
  if (mcPollTimer) { clearInterval(mcPollTimer); mcPollTimer = null; }
}

// ── Silent refresh for list view (just update unread dots) ────────────
async function silentRefreshList() {
  try {
    const res  = await fetch(MC_DATA_URL + '?limit=40');
    const data = await res.json();
    if (!data.messages) return;
    if (mcViewState !== 'list') return;
    const container = document.getElementById('miniChatMessages');
    container.innerHTML = '';
    if (MC_ROLE === 'admin' || MC_ROLE === 'seller') renderAdminList(container, data.messages);
    else renderMcTimeline(container, data.messages);
    container.scrollTop = container.scrollHeight;
  } catch(e) {}
}

// ── Silent refresh for conversation view ─────────────────────────────
async function silentRefreshConversation() {
  if (!mcActiveCustomer) return;
  try {
    const res  = await fetch(MC_DATA_URL + '?limit=40');
    const data = await res.json();
    if (!data.messages) return;

    // Re-filter messages for this customer
    const name = mcActiveCustomer.name;
    const msgs = data.messages.filter(m => (m.customer_name || 'Customer') === name);

    // Only process messages newer than what we already have
    const knownIds = mcActiveCustomer.knownIds || new Set();
    const newMsgs  = msgs.filter(m => m.id && !knownIds.has(m.id));
    if (newMsgs.length === 0) return; // no genuinely new messages from server

    // Add new IDs to known set
    msgs.forEach(m => { if (m.id) knownIds.add(m.id); });
    mcActiveCustomer.knownIds = knownIds;
    mcActiveCustomer.msgs = msgs;

    // Remove optimistic bubbles (those without data-msg-id) before appending real ones
    const container = document.getElementById('miniChatMessages');
    [...container.querySelectorAll('.mc-msg-wrap.me:not([data-msg-id])')].forEach(el => el.remove());

    // Append only the new messages
    renderMcTimeline(container, newMsgs, true);
    container.scrollTop = container.scrollHeight;
  } catch(e) {}
}

// ── Load messages (main entry) ────────────────────────────────────────
async function loadMcMessages() {
  const container = document.getElementById('miniChatMessages');
  container.innerHTML = '<div id="mcLoading" style="text-align:center;padding:20px;color:#bbb;font-size:.82rem"><i class="bi bi-three-dots" style="font-size:1.2rem"></i></div>';
  setMcInput(false);

  try {
    const res  = await fetch(MC_DATA_URL + '?limit=40');
    const data = await res.json();
    container.innerHTML = '';

    if (!data.messages || data.messages.length === 0) {
      container.innerHTML = '<div style="text-align:center;padding:30px 16px;color:#bbb"><i class="bi bi-chat-dots" style="font-size:2rem;display:block;margin-bottom:8px"></i><div style="font-size:.8rem">No messages yet</div></div>';
      return;
    }

    if (MC_ROLE === 'admin' || MC_ROLE === 'seller') {
      mcViewState = 'list';
      renderAdminList(container, data.messages);
    } else {
      mcViewState = 'conversation';
      renderMcTimeline(container, data.messages);
      setMcInput(true);
    }

    container.scrollTop = container.scrollHeight;
  } catch(e) {
    container.innerHTML = '<div style="text-align:center;padding:20px;color:#f44336;font-size:.8rem">Failed to load messages.</div>';
  }
}

// ── Render admin customer grid ────────────────────────────────────────
function renderAdminList(container, messages) {
  const customerMap = {};
  const seen = {};
  // messages are already sorted newest first — process as-is
  messages.forEach(msg => {
    const key = msg.customer_name || 'Customer';
    if (!customerMap[key]) {
      customerMap[key] = { msgs: [], photo: msg.customer_photo, orderId: msg.order_id, userId: msg.customer_user_id };
      seen[key] = msg; // first occurrence = latest message
    }
    customerMap[key].msgs.push(msg);
  });

  const bubbleWrap = document.createElement('div');
  bubbleWrap.style = 'padding:8px 4px';

  const title = document.createElement('div');
  title.style = 'font-size:.72rem;color:#bbb;text-align:center;margin-bottom:10px;font-weight:600';
  title.textContent = 'RECENT CONVERSATIONS';
  bubbleWrap.appendChild(title);

  const grid = document.createElement('div');
  grid.style = 'display:flex;flex-wrap:wrap;gap:12px;justify-content:center';

  Object.entries(seen).forEach(([name, latestMsg]) => {
    const cust = customerMap[name];
    const unread = cust.msgs.filter(m => m.sender_role === 'customer' && !m.is_read).length;

    const btn = document.createElement('div');
    btn.style = 'display:flex;flex-direction:column;align-items:center;gap:4px;cursor:pointer;padding:8px;border-radius:12px;transition:background .2s;min-width:64px';
    btn.onmouseenter = () => btn.style.background = '#fff0f5';
    btn.onmouseleave = () => btn.style.background = 'transparent';
    btn.onclick = () => openCustomerChat(name, cust.orderId, cust, cust.userId);

    const avatarWrap = document.createElement('div');
    avatarWrap.style = 'position:relative';
    if (latestMsg.customer_photo) {
      const img = document.createElement('img');
      img.src = latestMsg.customer_photo;
      img.style = 'width:52px;height:52px;border-radius:50%;object-fit:cover;border:3px solid #e91e63';
      avatarWrap.appendChild(img);
    } else {
      const av = document.createElement('div');
      av.style = 'width:52px;height:52px;border-radius:50%;background:#e91e63;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:1.1rem;border:3px solid #c2185b';
      av.textContent = name.charAt(0).toUpperCase();
      avatarWrap.appendChild(av);
    }
    if (unread > 0) {
      const dot = document.createElement('div');
      dot.style = 'position:absolute;top:-2px;right:-2px;width:18px;height:18px;background:#ff3b30;border-radius:50%;border:2px solid white;display:flex;align-items:center;justify-content:center;font-size:9px;color:white;font-weight:800';
      dot.textContent = unread > 9 ? '9+' : unread;
      avatarWrap.appendChild(dot);
    }
    btn.appendChild(avatarWrap);

    const nameEl = document.createElement('div');
    nameEl.style = 'font-size:.68rem;color:#555;font-weight:600;text-align:center;max-width:64px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap';
    nameEl.textContent = name.split(' ')[0];
    btn.appendChild(nameEl);

    const lastEl = document.createElement('div');
    lastEl.style = 'font-size:.62rem;color:#aaa;text-align:center;max-width:64px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap';
    lastEl.textContent = latestMsg.message ? latestMsg.message.substring(0,16)+'…' : '📷 Photo';
    btn.appendChild(lastEl);

    grid.appendChild(btn);
  });

  bubbleWrap.appendChild(grid);
  container.appendChild(bubbleWrap);
}

// ── Open specific customer conversation ───────────────────────────────
async function openCustomerChat(customerName, orderId, customerData, userId) {
  mcLatestOrderId  = orderId;
  mcActiveUserId   = userId || null;
  mcViewState      = 'conversation';
  const initIds = new Set();
  (customerData.msgs || []).forEach(m => { if (m.id) initIds.add(m.id); });
  mcActiveCustomer = { name: customerName, orderId, userId, msgs: customerData.msgs || [], knownIds: initIds };

  const container = document.getElementById('miniChatMessages');
  container.innerHTML = '';

  // Back button
  const back = document.createElement('div');
  back.dataset.mcBack = '1';
  back.style = 'display:flex;align-items:center;gap:6px;padding:6px 4px 10px;cursor:pointer;color:#e91e63;font-size:.8rem;font-weight:600';
  back.innerHTML = '<i class="bi bi-arrow-left"></i> Back';
  back.onclick = () => {
    mcViewState      = 'list';
    mcActiveCustomer = null;
    setMcInput(false);
    loadMcMessages();
  };
  container.appendChild(back);

  const header = document.createElement('div');
  header.dataset.mcHeader = '1';
  header.style = 'text-align:center;font-size:.78rem;font-weight:700;color:#555;margin-bottom:8px';
  header.textContent = customerName;
  container.appendChild(header);

  renderMcTimeline(container, customerData.msgs || []);
  container.scrollTop = container.scrollHeight;

  // Show input immediately
  setMcInput(true);

  // Mark all unread messages of this customer as read
  if (orderId) {
    try {
      await fetch(MC_MARK_URL + '/' + orderId, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': MC_CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
      });
      // Clear unread badge from the bubble icon
      updateBubbleBadge(-1); // -1 = recalculate
    } catch(e) {}
  }
}

// ── Mark single message read (fallback) ──────────────────────────────
async function markMsgRead(msgId) {
  try {
    await fetch('{{ session("user") ? (session("user")["role"] === "admin" ? url("/admin/messages/mark-read-msg") : (session("user")["role"] === "seller" ? url("/seller/messages/mark-read-msg") : url("/customer/messages/mark-read-msg"))) : "" }}/' + msgId, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': MC_CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
    });
  } catch(e) {}
}

// ── Show/hide input bar ───────────────────────────────────────────────
function setMcInput(show) {
  const bar     = document.getElementById('miniChatInput');
  const preBar  = document.getElementById('mcImgPreviewBar');
  if (bar) bar.style.display = show ? 'flex' : 'none';
  if (!show && preBar) { preBar.style.display = 'none'; mcClearImage(); }
}

// ── Update the floating bubble unread badge ───────────────────────────
function updateBubbleBadge(delta) {
  const badge = document.querySelector('#cakeMsgBubble > div');
  if (!badge) return;
  if (delta === -1) { badge.style.display = 'none'; return; }
  const cur = parseInt(badge.textContent) || 0;
  const next = Math.max(0, cur + delta);
  if (next === 0) badge.style.display = 'none';
  else { badge.textContent = next > 9 ? '9+' : next; badge.style.display = 'flex'; }
}

// ── Render timeline ───────────────────────────────────────────────────
function renderMcTimeline(container, messages, appendOnly = false) {
  let lastOrderId = null;

  // Setup IntersectionObserver for visibility-based mark-as-read
  const markUrl = MC_MARK_URL.replace(/\/mark-order-read.*/, '/mark-read-msg');
  const obs = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const el = entry.target;
      if (el.dataset.read === '1') return;
      el.dataset.read = '1';
      obs.unobserve(el);
      fetch(markUrl + '/' + el.dataset.msgId, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': MC_CSRF, 'Content-Type': 'application/json' }
      }).then(() => updateBubbleBadge(-1)).catch(() => {});
    });
  }, { threshold: 0.6 });

  messages.forEach(msg => {
    const isMe = (MC_ROLE === 'admin'    && msg.sender_role === 'admin')   ||
                 (MC_ROLE === 'seller'   && msg.sender_role === 'seller')  ||
                 (MC_ROLE === 'customer' && msg.sender_role === 'customer');

    if (msg.order_id !== lastOrderId) {
      const tag = document.createElement('div');
      tag.style = 'text-align:center;font-size:.65rem;color:#bbb;margin:6px 0 2px';
      tag.textContent = msg.order_id ? '📦 ' + (msg.product_name || 'Order #' + msg.order_id) : '💬 General Inquiry';
      container.appendChild(tag);
      lastOrderId = msg.order_id;
      if (msg.order_id) mcLatestOrderId = msg.order_id;
    }

    const wrap = document.createElement('div');
    wrap.className = 'mc-msg-wrap ' + (isMe ? 'me' : 'them');
    if (msg.id) wrap.dataset.msgId = msg.id;
    // For visibility-based mark-as-read (only track messages from the other role)
    if (!isMe && !msg.is_read && msg.id) {
      wrap.dataset.msgId = msg.id;
      wrap.dataset.read  = '0';
      obs.observe(wrap);
    }

    const bubble = document.createElement('div');
    bubble.className = 'mc-bubble';

    // Parse image_path — may be single string or JSON array
    if (msg.image_path) {
      let imgPaths = [];
      try { const p = JSON.parse(msg.image_path); imgPaths = Array.isArray(p) ? p : [msg.image_path]; }
      catch { imgPaths = [msg.image_path]; }

      const imgGrid = document.createElement('div');
      imgGrid.style = 'display:flex;flex-wrap:wrap;gap:4px;margin-bottom:' + (msg.message ? '4px' : '0');
      imgPaths.forEach(src => {
        const img = document.createElement('img');
        img.src = src;
        img.className = 'chat-img';
        img.dataset.src = src;
        const sz = imgPaths.length > 1 ? '80px' : '180px';
        const imgH = imgPaths.length > 1 ? sz : 'auto';
        img.style = 'width:' + sz + ';height:' + imgH + ';max-width:100%;border-radius:8px;cursor:zoom-in;object-fit:cover;display:block';
        img.onclick = () => openLightbox(img);
        imgGrid.appendChild(img);
      });
      bubble.appendChild(imgGrid);
    }
    if (msg.message) bubble.appendChild(document.createTextNode(msg.message));
    wrap.appendChild(bubble);

    const time = document.createElement('div');
    time.className = 'mc-time';
    time.textContent = formatMcTime(msg.created_at);
    wrap.appendChild(time);
    container.appendChild(wrap);
  });
}

// ── Multi-image selected preview ──────────────────────────────────────
function mcImageSelected(input) {
  if (input.files && input.files.length > 0) {
    mcSelectedImages = [...mcSelectedImages, ...Array.from(input.files)];
    input.value = ''; // reset so same file can be re-added
    renderMcImagePreview();
  }
}

function renderMcImagePreview() {
  const btn    = document.getElementById('mcImgBtn');
  const preBar = document.getElementById('mcImgPreviewBar');
  const strip  = document.getElementById('mcImgPreviewStrip');
  strip.innerHTML = '';
  if (mcSelectedImages.length === 0) {
    preBar.style.display = 'none';
    btn.style.background = '#f5f5f5'; btn.style.color = '#aaa'; btn.title = 'Attach images';
    return;
  }
  preBar.style.display = 'block';
  btn.style.background = '#fce4ec'; btn.style.color = '#e91e63';
  btn.title = mcSelectedImages.length + ' image(s) selected';

  mcSelectedImages.forEach((file, idx) => {
    const wrap = document.createElement('div');
    wrap.style = 'position:relative;display:inline-block;flex-shrink:0';
    const img = document.createElement('img');
    img.style = 'width:52px;height:52px;border-radius:.4rem;object-fit:cover;border:2px solid var(--primary)';
    const r = new FileReader();
    r.onload = e => img.src = e.target.result;
    r.readAsDataURL(file);
    const rm = document.createElement('button');
    rm.type = 'button'; rm.innerHTML = '&times;';
    rm.style = 'position:absolute;top:-5px;right:-5px;width:15px;height:15px;border-radius:50%;background:#ef4444;border:none;color:white;font-size:.5rem;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0';
    rm.onclick = () => { mcSelectedImages.splice(idx, 1); renderMcImagePreview(); };
    wrap.appendChild(img); wrap.appendChild(rm);
    strip.appendChild(wrap);
  });
}

function mcClearImage() {
  mcSelectedImages = [];
  const fileIn = document.getElementById('mcImageInput');
  if (fileIn) fileIn.value = '';
  renderMcImagePreview();
}

// ── Send message ──────────────────────────────────────────────────────
async function mcSend() {
  const input  = document.getElementById('mcInput');
  const text   = input.value.trim();
  const images = [...mcSelectedImages];
  if (!text && images.length === 0) return;

  input.value = '';
  mcClearImage();

  // Optimistic UI
  const container = document.getElementById('miniChatMessages');
  const wrap   = document.createElement('div');
  wrap.className = 'mc-msg-wrap me';
  const bubble = document.createElement('div');
  bubble.className = 'mc-bubble';

  if (images.length > 0) {
    const grid = document.createElement('div');
    grid.style = 'display:flex;flex-wrap:wrap;gap:4px;margin-bottom:' + (text ? '4px' : '0');
    images.forEach(f => {
      const pi = document.createElement('img');
      const sz = images.length > 1 ? '80px' : '180px';
      const piH = images.length > 1 ? sz : 'auto';
      pi.style = 'width:' + sz + ';height:' + piH + ';border-radius:8px;object-fit:cover;opacity:.7;cursor:zoom-in';
      pi.className = 'chat-img';
      pi.src = URL.createObjectURL(f);
      grid.appendChild(pi);
    });
    bubble.appendChild(grid);
  }
  if (text) bubble.appendChild(document.createTextNode(text));
  const time = document.createElement('div');
  time.className = 'mc-time';
  time.textContent = 'Sending…';
  wrap.appendChild(bubble); wrap.appendChild(time);
  container.appendChild(wrap);
  container.scrollTop = container.scrollHeight;

  try {
    const fd = new FormData();
    if (text)    fd.append('message', text);
    fd.append('order_id', mcLatestOrderId || 0);
    fd.append('user_id',  mcActiveUserId || MC_USER_ID);
    fd.append('_token',   MC_CSRF);
    images.forEach(f => fd.append('images[]', f));

    const res  = await fetch(MC_SEND_URL, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.order_id) mcLatestOrderId = data.order_id;
    time.textContent = formatMcTime(data.created_at || new Date().toISOString());
    // Tag optimistic bubble with real msg ID so silentRefresh won't duplicate it
    if (data.id) {
      wrap.dataset.msgId = data.id;
      if (mcActiveCustomer && mcActiveCustomer.knownIds) mcActiveCustomer.knownIds.add(data.id);
    }

    // Update optimistic images with real paths
    if (data.image_path) {
      let paths = [];
      try { const p = JSON.parse(data.image_path); paths = Array.isArray(p) ? p : [data.image_path]; }
      catch { paths = [data.image_path]; }
      const imgs = bubble.querySelectorAll('img');
      imgs.forEach((img, i) => {
        if (paths[i]) { img.src = paths[i]; img.dataset.src = paths[i]; img.style.opacity = '1'; img.onclick = () => openLightbox(img); }
      });
    }
  } catch(e) {
    time.textContent = 'Failed';
    time.style.color = '#f44336';
  }
}

function formatMcTime(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  const now = new Date();
  const diff = Math.floor((now - d) / 1000);
  if (diff < 60)  return 'Just now';
  if (diff < 3600) return Math.floor(diff/60) + 'm ago';
  if (diff < 86400) return d.toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
  return d.toLocaleDateString([],{month:'short',day:'numeric'});
}
</script>
@endif

{{-- ── Floating Cake Messenger Bubble (Seller only) ─────────────── --}}
@if($isSeller)
@php
  $msgRoute    = route('seller.messages');
  $unreadCount = $unreadMessages ?? 0;
@endphp
<style>
@@keyframes cakeBubbleFloat { 0%,100%{transform:translateY(0) rotate(-2deg)} 50%{transform:translateY(-10px) rotate(2deg)} }
@@keyframes cakeBubblePing1 { 0%{transform:scale(1);opacity:.5} 100%{transform:scale(2.2);opacity:0} }
@@keyframes cakeBubblePing2 { 0%{transform:scale(1);opacity:.3} 100%{transform:scale(2.8);opacity:0} }
@@keyframes cakeBubbleWiggle { 0%,100%{transform:rotate(0)} 20%{transform:rotate(-15deg)} 40%{transform:rotate(12deg)} 60%{transform:rotate(-8deg)} 80%{transform:rotate(5deg)} }
@@keyframes cakeTooltipIn { from{opacity:0;transform:scale(.85) translateX(10px)} to{opacity:1;transform:scale(1) translateX(0)} }

#cakeMsgBubble {
  position:fixed;
  bottom:28px;
  right:28px;
  z-index:9000;
  width:64px;
  height:64px;
  border-radius:50%;
  cursor:pointer;
  border:none;
  outline:none;
  padding:0;
  background:none;
  animation: cakeBubbleFloat 3.5s ease-in-out infinite;
  transition:filter .2s;
}
#cakeMsgBubble:hover { filter:brightness(1.1); }
#cakeMsgBubble:hover { animation: cakeBubbleWiggle .5s ease-in-out forwards; }
#cakeMsgPing1, #cakeMsgPing2 {
  position:fixed;
  bottom:28px; right:28px;
  width:64px; height:64px;
  border-radius:50%;
  pointer-events:none;
  z-index:8999;
}
#cakeMsgPing1 { background:rgba(233,30,99,.25); animation: cakeBubblePing1 2.2s ease-out infinite; }
#cakeMsgPing2 { background:rgba(233,30,99,.15); animation: cakeBubblePing2 2.2s ease-out infinite .45s; }
#cakeMsgTooltip {
  position:fixed;
  bottom:44px;
  right:100px;
  background:white;
  border-radius:14px 14px 4px 14px;
  padding:8px 14px;
  font-size:12px;
  font-weight:600;
  color:#9d174d;
  box-shadow:0 4px 18px rgba(233,30,99,.2);
  white-space:nowrap;
  display:none;
  z-index:9001;
  animation: cakeTooltipIn .3s cubic-bezier(.34,1.56,.64,1);
  pointer-events:none;
}
#cakeMsgTooltip::after {
  content:'';
  position:absolute;
  bottom:-6px; right:10px;
  width:12px; height:12px;
  background:white;
  clip-path:polygon(0 0,100% 0,100% 100%);
  border-radius:0 0 3px 0;
}

@@media(max-width:575px) {
  #cakeMsgBubble,
  #cakeMsgPing1,
  #cakeMsgPing2 {
    width:56px;
    height:56px;
    right:16px;
    bottom:16px;
  }
  #cakeMsgTooltip {
    right:84px;
    bottom:30px;
    max-width:calc(100vw - 112px);
    overflow:hidden;
    text-overflow:ellipsis;
  }
}

@@media(max-height:430px) and (orientation:landscape) {
  #cakeMsgBubble,
  #cakeMsgPing1,
  #cakeMsgPing2 {
    right:14px;
    bottom:14px;
    width:52px;
    height:52px;
  }
  #cakeMsgTooltip { display:none !important; }
}
</style>

<div id="cakeMsgPing1"></div>
<div id="cakeMsgPing2"></div>
<div id="cakeMsgTooltip">
  @if($unreadCount > 0)
    <i class="bi bi-chat-dots-fill me-1" style="color:#e91e63"></i>{{ $unreadCount }} unread message{{ $unreadCount > 1 ? 's' : '' }}
  @else
    <i class="bi bi-chat-dots me-1" style="color:#e91e63"></i>Messages
  @endif
</div>

<a href="#" id="cakeMsgBubble" onclick="event.preventDefault();if(typeof toggleMiniChat==='function')toggleMiniChat()"
   onmouseenter="document.getElementById('cakeMsgTooltip').style.display='block'"
   onmouseleave="document.getElementById('cakeMsgTooltip').style.display='none'">
  <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
    <defs>
      <radialGradient id="cbg" cx="40%" cy="35%" r="65%">
        <stop offset="0%" stop-color="#f48fb1"/>
        <stop offset="100%" stop-color="#c2185b"/>
      </radialGradient>
      <radialGradient id="ccream" cx="50%" cy="30%" r="60%">
        <stop offset="0%" stop-color="#fffde7"/>
        <stop offset="100%" stop-color="#fff8e1"/>
      </radialGradient>
    </defs>
    <circle cx="32" cy="32" r="30" fill="url(#cbg)"/>
    <ellipse cx="22" cy="18" rx="8" ry="5" fill="white" opacity=".18" transform="rotate(-30 22 18)"/>
    <!-- Bottom cake layer -->
    <rect x="10" y="36" width="44" height="14" rx="4" fill="#fff8e1"/>
    <rect x="10" y="36" width="44" height="5" rx="2" fill="#ffcc80"/>
    <!-- Middle layer -->
    <rect x="13" y="26" width="38" height="12" rx="3.5" fill="#f8bbd0"/>
    <rect x="13" y="26" width="38" height="4.5" rx="2" fill="#f48fb1"/>
    <!-- Cream dollops -->
    <ellipse cx="18" cy="26" rx="4.5" ry="3.5" fill="url(#ccream)"/>
    <ellipse cx="26" cy="25" rx="4.5" ry="3.5" fill="url(#ccream)"/>
    <ellipse cx="32" cy="24.5" rx="4.5" ry="3.5" fill="url(#ccream)"/>
    <ellipse cx="38" cy="25" rx="4.5" ry="3.5" fill="url(#ccream)"/>
    <ellipse cx="46" cy="26" rx="4.5" ry="3.5" fill="url(#ccream)"/>
    <!-- Sprinkles -->
    <rect x="16" y="40" width="5" height="2" rx="1" fill="#f48fb1" transform="rotate(-20 18 41)"/>
    <rect x="25" y="43" width="5" height="2" rx="1" fill="#81d4fa" transform="rotate(15 27 44)"/>
    <rect x="34" y="40" width="5" height="2" rx="1" fill="#a5d6a7" transform="rotate(-35 36 41)"/>
    <rect x="43" y="43" width="5" height="2" rx="1" fill="#ffe082" transform="rotate(25 45 44)"/>
    <circle cx="30" cy="44" r="1.2" fill="#f48fb1"/>
    <circle cx="40" cy="41" r="1.2" fill="#80cbc4"/>
    <circle cx="20" cy="44" r="1.2" fill="#ffe082"/>
    <!-- Candles -->
    <rect x="20" y="14" width="5" height="13" rx="2" fill="#f48fb1"/>
    <rect x="29.5" y="12" width="5" height="13" rx="2" fill="#80cbc4"/>
    <rect x="39" y="14" width="5" height="13" rx="2" fill="#ffe082"/>
    <!-- Candle stripes -->
    <rect x="20" y="17" width="5" height="1.5" rx=".7" fill="white" opacity=".5"/>
    <rect x="20" y="21" width="5" height="1.5" rx=".7" fill="white" opacity=".5"/>
    <rect x="29.5" y="15" width="5" height="1.5" rx=".7" fill="white" opacity=".5"/>
    <rect x="29.5" y="19" width="5" height="1.5" rx=".7" fill="white" opacity=".5"/>
    <rect x="39" y="17" width="5" height="1.5" rx=".7" fill="white" opacity=".5"/>
    <rect x="39" y="21" width="5" height="1.5" rx=".7" fill="white" opacity=".5"/>
    <!-- Flames -->
    <path d="M22.5 14 Q24 10 22.5 7 Q21 10 22.5 14Z" fill="#ffcc02"/>
    <path d="M22.5 14 Q23.5 11 22.5 9 Q21.5 11 22.5 14Z" fill="#ff9800" opacity=".8"/>
    <path d="M32 12 Q33.5 8 32 5 Q30.5 8 32 12Z" fill="#ffcc02"/>
    <path d="M32 12 Q33 9 32 7 Q31 9 32 12Z" fill="#ff9800" opacity=".8"/>
    <path d="M41.5 14 Q43 10 41.5 7 Q40 10 41.5 14Z" fill="#ffcc02"/>
    <path d="M41.5 14 Q42.5 11 41.5 9 Q40.5 11 41.5 14Z" fill="#ff9800" opacity=".8"/>
    <!-- Flame glows -->
    <circle cx="22.5" cy="10" r="1.5" fill="#fff176" opacity=".7"/>
    <circle cx="32" cy="8" r="1.5" fill="#fff176" opacity=".7"/>
    <circle cx="41.5" cy="10" r="1.5" fill="#fff176" opacity=".7"/>
    <!-- Chat bubble tail -->
    <path d="M18 50 Q14 56 10 58 Q16 54 22 50Z" fill="#fff8e1"/>
  </svg>
  @if($unreadCount > 0)
  <div style="position:absolute;top:-2px;right:-2px;width:22px;height:22px;background:#ff3b30;border-radius:50%;border:2.5px solid white;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;color:white;font-family:system-ui;box-shadow:0 2px 8px rgba(255,59,48,.5)">
    {{ $unreadCount > 9 ? '9+' : $unreadCount }}
  </div>
  @endif
</a>
@endif

@stack('modals')
@stack('scripts')

{{-- ── Global Image Lightbox ─────────────────────────────────────── --}}
<style>
#imgLightbox {
  position:fixed;inset:0;z-index:99999;
  background:rgba(0,0,0,.92);
  display:none;flex-direction:column;
  align-items:center;justify-content:center;
  animation:lbIn .2s ease;
}
@@keyframes lbIn { from{opacity:0} to{opacity:1} }
#imgLightbox.open { display:flex; }
#lbImg {
  max-width:90vw;max-height:80vh;
  object-fit:contain;border-radius:.75rem;
  transform-origin:center;
  transition:transform .2s ease;
  user-select:none;
}
#lbToolbar {
  position:fixed;top:0;left:0;right:0;
  display:flex;align-items:center;justify-content:space-between;
  padding:10px 16px;
  background:linear-gradient(to bottom,rgba(0,0,0,.7),transparent);
  z-index:100000;
}
#lbNavPrev,#lbNavNext {
  position:fixed;top:50%;transform:translateY(-50%);
  width:44px;height:44px;border-radius:50%;
  background:rgba(255,255,255,.15);border:none;
  color:white;font-size:1.2rem;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition:background .2s;
  backdrop-filter:blur(4px);
}
#lbNavPrev { left:16px; }
#lbNavNext { right:16px; }
#lbNavPrev:hover,#lbNavNext:hover { background:rgba(255,255,255,.3); }
#lbNavPrev:disabled,#lbNavNext:disabled { opacity:.25;cursor:not-allowed; }
.lb-tb-btn {
  width:36px;height:36px;border-radius:50%;
  background:rgba(255,255,255,.15);border:none;
  color:white;font-size:.9rem;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition:background .2s;
  backdrop-filter:blur(4px);
}
.lb-tb-btn:hover { background:rgba(255,255,255,.3); }
#lbCounter { color:rgba(255,255,255,.7);font-size:.8rem;font-weight:600; }
#lbZoomLabel { color:rgba(255,255,255,.6);font-size:.75rem;min-width:40px;text-align:center; }
</style>

<div id="imgLightbox" onclick="lbBgClick(event)">
  {{-- Top toolbar --}}
  <div id="lbToolbar">
    <div style="display:flex;align-items:center;gap:8px">
      <button class="lb-tb-btn" onclick="lbZoom(-1)" title="Zoom out"><i class="bi bi-zoom-out"></i></button>
      <span id="lbZoomLabel">100%</span>
      <button class="lb-tb-btn" onclick="lbZoom(1)" title="Zoom in"><i class="bi bi-zoom-in"></i></button>
      <button class="lb-tb-btn" onclick="lbZoomReset()" title="Reset zoom"><i class="bi bi-arrow-counterclockwise"></i></button>
    </div>
    <span id="lbCounter"></span>
    <div style="display:flex;gap:8px">
      <a id="lbDownload" class="lb-tb-btn" download title="Download" style="text-decoration:none"><i class="bi bi-download"></i></a>
      <button class="lb-tb-btn" onclick="closeLightbox()" title="Close"><i class="bi bi-x-lg"></i></button>
    </div>
  </div>

  {{-- Prev / Next --}}
  <button id="lbNavPrev" onclick="lbNav(-1)"><i class="bi bi-chevron-left"></i></button>
  <img id="lbImg" src="" alt="Image" draggable="false">
  <button id="lbNavNext" onclick="lbNav(1)"><i class="bi bi-chevron-right"></i></button>
</div>

<script>
// ── Lightbox state ────────────────────────────────────────────────────
if (typeof lbImages === 'undefined') { var lbImages = []; }
if (typeof lbIndex === 'undefined')  { var lbIndex  = 0; }
if (typeof lbZoomLvl === 'undefined'){ var lbZoomLvl= 1; }
var LB_ZOOM_STEPS = [0.25, 0.5, 0.75, 1, 1.25, 1.5, 2, 3, 4];

function openLightbox(imgEl) {
  // Collect all .chat-img images visible in the page / bubble
  const scope = imgEl.closest('#chatBox') || imgEl.closest('#miniChatMessages') || document;
  const imgs = [...scope.querySelectorAll('.chat-img[data-src]')];
  lbImages  = imgs.map(i => i.dataset.src || i.src);
  lbIndex   = imgs.indexOf(imgEl);
  if (lbIndex < 0) { lbImages = [imgEl.dataset.src || imgEl.src]; lbIndex = 0; }

  lbZoomLvl = 1;
  lbRender();
  document.getElementById('imgLightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeLightbox() {
  document.getElementById('imgLightbox').classList.remove('open');
  document.body.style.overflow = '';
}

function lbBgClick(e) {
  if (e.target === document.getElementById('imgLightbox')) closeLightbox();
}

function lbNav(dir) {
  lbIndex = Math.max(0, Math.min(lbImages.length - 1, lbIndex + dir));
  lbZoomLvl = 1;
  lbRender();
}

function lbRender() {
  const img  = document.getElementById('lbImg');
  const prev = document.getElementById('lbNavPrev');
  const next = document.getElementById('lbNavNext');
  const ctr  = document.getElementById('lbCounter');
  const dl   = document.getElementById('lbDownload');

  img.src = lbImages[lbIndex];
  img.style.transform = 'scale(' + lbZoomLvl + ')';
  document.getElementById('lbZoomLabel').textContent = Math.round(lbZoomLvl * 100) + '%';
  prev.disabled = lbIndex === 0;
  next.disabled = lbIndex === lbImages.length - 1;
  ctr.textContent = lbImages.length > 1 ? (lbIndex + 1) + ' / ' + lbImages.length : '';
  dl.href = lbImages[lbIndex];
  // Hide nav buttons if only 1 image
  prev.style.display = next.style.display = lbImages.length > 1 ? 'flex' : 'none';
}

function lbZoom(dir) {
  const cur = LB_ZOOM_STEPS.indexOf(lbZoomLvl);
  const next = Math.max(0, Math.min(LB_ZOOM_STEPS.length - 1, cur + dir));
  lbZoomLvl = LB_ZOOM_STEPS[next];
  document.getElementById('lbImg').style.transform = 'scale(' + lbZoomLvl + ')';
  document.getElementById('lbZoomLabel').textContent = Math.round(lbZoomLvl * 100) + '%';
}

function lbZoomReset() {
  lbZoomLvl = 1;
  document.getElementById('lbImg').style.transform = 'scale(1)';
  document.getElementById('lbZoomLabel').textContent = '100%';
}

// Keyboard nav
document.addEventListener('keydown', e => {
  if (!document.getElementById('imgLightbox').classList.contains('open')) return;
  if (e.key === 'Escape')      closeLightbox();
  if (e.key === 'ArrowLeft')   lbNav(-1);
  if (e.key === 'ArrowRight')  lbNav(1);
  if (e.key === '+' || e.key === '=') lbZoom(1);
  if (e.key === '-')           lbZoom(-1);
});

// Scroll to zoom on image
document.getElementById('lbImg').addEventListener('wheel', e => {
  e.preventDefault();
  lbZoom(e.deltaY < 0 ? 1 : -1);
}, { passive: false });
</script>

{{-- Dev Mode: CSS for toast + polling JS --}}
<style>
.cs-dev-toast {
  background: linear-gradient(135deg, #fffbeb 0%, #fff 100%);
  border: 1.5px dashed #f59e0b;
  max-width: 420px;
  align-items: flex-start;
  gap: 10px;
}
</style>
@if(!empty($platformBrand->dev_mode))
<script>
(function () {
  var POLL_URL = '{{ route("dev.sms.poll") }}';

  function showDevSmsToast(sms) {
    var c = document.getElementById('csToastContainer');
    if (!c) return;
    var t = document.createElement('div');
    t.className = 'cs-toast cs-dev-toast';
    t.innerHTML =
      '<div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#fef3c7,#fde68a);display:flex;align-items:center;justify-content:center;flex-shrink:0">' +
        '<i class="bi bi-bug-fill" style="color:#d97706;font-size:.95rem"></i>' +
      '</div>' +
      '<div style="flex:1;min-width:0">' +
        '<div style="font-weight:700;color:#92400e;font-size:.73rem;text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px">' +
          'Dev Mode &mdash; SMS Preview' +
          '<span style="font-weight:400;color:#b45309;margin-left:6px;text-transform:none;letter-spacing:0">' + sms.time + '</span>' +
        '</div>' +
        '<div style="font-size:.72rem;color:#b45309;margin-bottom:5px">To: <strong>+' + sms.to + '</strong></div>' +
        '<div style="font-size:.76rem;color:#78350f;background:#fffbeb;border-radius:7px;padding:7px 9px;font-family:monospace;line-height:1.55;word-break:break-word;border:1px solid #fde68a">' + escHtml(sms.message) + '</div>' +
      '</div>' +
      '<button onclick="this.closest(\'.cs-toast\').remove()" style="background:none;border:none;color:#b45309;font-size:1.05rem;padding:0;line-height:1;cursor:pointer;flex-shrink:0;align-self:flex-start;margin-top:1px">&times;</button>';
    c.appendChild(t);
    setTimeout(function () {
      t.classList.add('hiding');
      setTimeout(function () { if (t.parentNode) t.remove(); }, 270);
    }, 15000);
  }

  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function pollDevSms() {
    fetch(POLL_URL, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (items) {
        if (!Array.isArray(items)) return;
        items.forEach(function (sms, i) {
          setTimeout(function () { showDevSmsToast(sms); }, i * 350);
        });
      })
      .catch(function () {});
  }

  setInterval(pollDevSms, 3000);
}());
</script>
@endif
<script>
// ── Shared pagination helpers (used by all paginated list pages) ─────────
let _pgSearchTimer;
function pgSearch(val) {
  clearTimeout(_pgSearchTimer);
  _pgSearchTimer = setTimeout(function() {
    var url = new URL(window.location.href);
    url.searchParams.set('search', val);
    url.searchParams.set('page', '1');
    window.location = url.toString();
  }, 500);
}
function pgFilter(param, val) {
  var url = new URL(window.location.href);
  url.searchParams.set(param, val);
  url.searchParams.set('page', '1');
  window.location = url.toString();
}
</script>
<script>
// ── Global Lightbox ──────────────────────────────────────────────────────
(function() {
  var _lbScale = 1;
  var _lbOpen  = false;
  function _lbEl(id) { return document.getElementById(id); }
  function _applyZoom() {
    var img = _lbEl('csLbImg');
    if (img) { img.style.transform='scale('+_lbScale+')'; img.style.cursor=_lbScale>1?'zoom-out':'zoom-in'; }
    var lbl = _lbEl('csLbZoomLbl');
    if (lbl) lbl.textContent = Math.round(_lbScale*100)+'%';
  }
  window.csLightboxOpen = function(src, title) {
    if (!src) return;
    var isPdf = /\.pdf(\?.*)?$/i.test(src);
    var lb = _lbEl('csLightbox'); if (!lb) return;
    var imgWrap  = _lbEl('csLbImgWrap');
    var pdfWrap  = _lbEl('csLbPdfWrap');
    var zoomBar  = _lbEl('csLbZoomBar');
    var hint     = _lbEl('csLbHint');
    var closeBtn = _lbEl('csLbClose');
    var titleEl  = _lbEl('csLbTitle');
    _lbScale = 1; _applyZoom();
    titleEl.textContent = title || '';
    imgWrap.style.transform='scale(0.88)'; imgWrap.style.opacity='0';
    pdfWrap.style.transform='scale(0.88)'; pdfWrap.style.opacity='0';
    closeBtn.style.opacity='0'; titleEl.style.opacity='0';
    zoomBar.style.opacity='0'; hint.style.opacity='0';
    lb.style.background='rgba(0,0,0,0)';
    if (isPdf) {
      imgWrap.style.display='none'; pdfWrap.style.display='';
      zoomBar.style.display='none'; hint.style.display='none';
      _lbEl('csLbPdf').src=src; _lbEl('csLbImg').src='';
    } else {
      pdfWrap.style.display='none'; imgWrap.style.display='flex';
      zoomBar.style.display='flex'; hint.style.display='';
      _lbEl('csLbImg').src=src; _lbEl('csLbPdf').src='';
    }
    lb.style.display='flex';
    document.body.style.overflow='hidden';
    _lbOpen=true;
    requestAnimationFrame(function() {
      lb.style.background='rgba(0,0,0,.92)';
      var target=isPdf?pdfWrap:imgWrap;
      target.style.transform='scale(1)'; target.style.opacity='1';
      closeBtn.style.opacity='1'; titleEl.style.opacity='1';
      if (!isPdf) { zoomBar.style.opacity='1'; hint.style.opacity='1'; }
    });
  };
  window.csLightboxClose = function() {
    var lb=_lbEl('csLightbox'); if (!lb||!_lbOpen) return;
    _lbOpen=false;
    var imgWrap=_lbEl('csLbImgWrap'), pdfWrap=_lbEl('csLbPdfWrap');
    lb.style.background='rgba(0,0,0,0)';
    imgWrap.style.transform='scale(0.88)'; imgWrap.style.opacity='0';
    pdfWrap.style.transform='scale(0.88)'; pdfWrap.style.opacity='0';
    _lbEl('csLbClose').style.opacity='0'; _lbEl('csLbTitle').style.opacity='0';
    _lbEl('csLbZoomBar').style.opacity='0'; _lbEl('csLbHint').style.opacity='0';
    setTimeout(function() {
      lb.style.display='none';
      _lbEl('csLbImg').src=''; _lbEl('csLbPdf').src='';
      document.body.style.overflow='';
      _lbScale=1; _applyZoom();
    }, 300);
  };
  window.csLbToggleZoom = function() { _lbScale=_lbScale>=2?1:2; _applyZoom(); };
  window.csLbZoom  = function(d) { _lbScale=Math.min(4,Math.max(0.5,_lbScale+d)); _applyZoom(); };
  window.csLbReset = function() { _lbScale=1; _applyZoom(); };
  // Backward-compat aliases so existing call sites keep working
  window.kitchenImgOpen  = function(s,t) { window.csLightboxOpen(s,t||''); };
  window.kitchenImgClose = window.csLightboxClose;
  window.openDocViewer   = function(s,t) { window.csLightboxOpen(s,t||''); };
  window.closeDocViewer  = window.csLightboxClose;
  window.catLbOpen       = function(s)   { window.csLightboxOpen(s,''); };
  window.catLbClose      = window.csLightboxClose;
  window.catLbZoom       = function(d)   { window.csLbZoom(d); };
  window.catLbReset      = window.csLbReset;
  window.catLbBgClick    = function(e)   { if (e&&e.target&&(e.target.id==='lightboxOverlay'||e.target.id==='csLightbox')) window.csLightboxClose(); };
  document.addEventListener('DOMContentLoaded', function() {
    var lb=document.getElementById('csLightbox'); if (!lb) return;
    lb.addEventListener('wheel', function(e) {
      if (_lbOpen && _lbEl('csLbImgWrap') && _lbEl('csLbImgWrap').style.display!=='none') {
        e.preventDefault(); window.csLbZoom(e.deltaY<0?0.15:-0.15);
      }
    }, { passive: false });
  });
})();
</script>

<script>
/* ── Image upload size preview ─────────────────────────────────────────── */
(function () {
  var MAX_PX = 1200, QUALITY = 0.80;

  function fmtSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
  }

  function getOrCreatePreview(input) {
    var id = 'img-size-preview-' + (input.dataset.sizePreviewId || Math.random().toString(36).slice(2));
    input.dataset.sizePreviewId = id;
    var el = document.getElementById(id);
    if (!el) {
      el = document.createElement('div');
      el.id = id;
      el.style.cssText = 'font-size:.75rem;margin-top:.3rem;color:#6b7280;display:none';
      input.parentNode.insertBefore(el, input.nextSibling);
    }
    return el;
  }

  function compressAndShow(input, file, preview) {
    var orig = file.size;
    var ext  = file.name.split('.').pop().toLowerCase();
    var imageExts = ['jpg','jpeg','png','webp'];

    if (!imageExts.includes(ext)) {
      preview.innerHTML = '<span style="color:#6b7280">📎 Size: <strong>' + fmtSize(orig) + '</strong></span>';
      preview.style.display = 'block';
      return;
    }

    var reader = new FileReader();
    reader.onload = function (e) {
      var img = new Image();
      img.onload = function () {
        var w = img.width, h = img.height;
        var scale = Math.min(MAX_PX / w, MAX_PX / h, 1);
        var canvas = document.createElement('canvas');
        canvas.width  = Math.round(w * scale);
        canvas.height = Math.round(h * scale);
        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(function (blob) {
          if (!blob) return;
          var saved = Math.round((1 - blob.size / orig) * 100);
          var color = saved >= 50 ? '#059669' : saved >= 20 ? '#d97706' : '#6b7280';
          preview.innerHTML =
            '<span style="color:#6b7280">Original: <strong>' + fmtSize(orig) + '</strong></span>' +
            '&nbsp;&nbsp;→&nbsp;&nbsp;' +
            '<span style="color:' + color + '">Saved as: <strong>~' + fmtSize(blob.size) + '</strong>' +
            (saved > 0 ? ' <span style="background:' + color + ';color:#fff;border-radius:4px;padding:1px 5px;font-size:.68rem">-' + saved + '%</span>' : '') +
            '</span>';
          preview.style.display = 'block';
        }, 'image/jpeg', QUALITY);
      };
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  }

  function attachToInput(input) {
    if (input.dataset.sizePreviewAttached) return;
    input.dataset.sizePreviewAttached = '1';
    input.addEventListener('change', function () {
      var preview = getOrCreatePreview(input);
      if (!this.files || !this.files[0]) { preview.style.display = 'none'; return; }
      compressAndShow(input, this.files[0], preview);
    });
  }

  function attachAll() {
    document.querySelectorAll('input[type="file"]').forEach(attachToInput);
  }

  document.addEventListener('DOMContentLoaded', attachAll);
  // Also catch dynamically added inputs (e.g. inside modals)
  var obs = new MutationObserver(attachAll);
  obs.observe(document.body, { childList: true, subtree: true });
})();
</script>
</body>
</html>
