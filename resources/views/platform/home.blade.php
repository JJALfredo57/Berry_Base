@php
  $rawPrimary = $platform->platform_primary_color ?? '#E53935';
  if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $rawPrimary)) $rawPrimary = '#E53935';

  $adj = function(string $hex, float $f): string {
      $hex = ltrim($hex,'#');
      $r=hexdec(substr($hex,0,2)); $g=hexdec(substr($hex,2,2)); $b=hexdec(substr($hex,4,2));
      if ($f>=0){$r=(int)min(255,$r+(255-$r)*$f);$g=(int)min(255,$g+(255-$g)*$f);$b=(int)min(255,$b+(255-$b)*$f);}
      else{$f=1+$f;$r=(int)max(0,$r*$f);$g=(int)max(0,$g*$f);$b=(int)max(0,$b*$f);}
      return sprintf('#%02x%02x%02x',$r,$g,$b);
  };
  $toRgb = function(string $hex): string {
      $hex=ltrim($hex,'#'); return hexdec(substr($hex,0,2)).','.hexdec(substr($hex,2,2)).','.hexdec(substr($hex,4,2));
  };

  $pDark  = $adj($rawPrimary, -0.30);
  $pLight = $adj($rawPrimary,  0.65);
  $pMid   = $adj($rawPrimary,  0.78);
  $pBg    = $adj($rawPrimary,  0.90);
  $pRgb   = $toRgb($rawPrimary);
  $pBgRgb = $toRgb($pBg);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $platform->platform_name ?? 'Cake Shop Platform' }}</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap');
    :root {
      --primary:{{ $rawPrimary }}; --primary-dark:{{ $pDark }}; --primary-light:{{ $pLight }};
      --primary-bg:{{ $pBg }}; --secondary:#FF8A80; --cream:{{ $pBg }};
      --primary-rgb:{{ $pRgb }};
      --gray-50:#FAFAFA; --gray-100:#F5F5F5; --gray-200:#EEEEEE;
      --gray-300:#E0E0E0; --gray-400:#BDBDBD; --gray-500:#9E9E9E;
      --gray-600:#757575; --gray-700:#616161; --gray-800:#424242;
      --gray-900:#212121; --success:#2E7D32; --radius-md:10px;
      --radius-lg:16px; --shadow-sm:0 1px 3px rgba(0,0,0,.08);
      --shadow-md:0 4px 12px rgba(0,0,0,.08);
    }
    *, *::before, *::after { box-sizing: border-box; }
    html { scroll-behavior: smooth; width:100%; overflow-x:hidden; }
    body { font-family:'DM Sans',system-ui,sans-serif; background:var(--cream); color:var(--gray-900); margin:0; -webkit-font-smoothing:antialiased; width:100%; overflow-x:hidden; }
    img, svg, video, canvas { max-width:100%; height:auto; }
    a { text-decoration:none; color:inherit; }

    /* ── NAV ── */
    .platform-nav {
      position:sticky; top:0; z-index:100;
      background:rgba({{ $pBgRgb }},.92); backdrop-filter:blur(12px);
      border-bottom:1px solid rgba(var(--primary-rgb),.1);
      padding:.875rem 0; transition:box-shadow .2s;
    }
    .platform-nav.scrolled { box-shadow:0 4px 24px rgba(0,0,0,.08); }
    .nav-brand { font-family:'Playfair Display',serif; font-size:1.4rem; font-weight:700; color:var(--primary); display:flex; align-items:center; gap:.5rem; }
    .nav-links { display:flex; align-items:center; gap:1.5rem; }
    .nav-link-item { font-size:.875rem; font-weight:500; color:var(--gray-700); transition:color .15s; }
    .nav-link-item:hover { color:var(--primary); }
    .btn-nav-primary { background:var(--primary); color:#fff; padding:.5rem 1.25rem; border-radius:var(--radius-md); font-size:.875rem; font-weight:600; border:none; cursor:pointer; transition:all .18s; box-shadow:0 2px 8px rgba(var(--primary-rgb),.25); }
    .btn-nav-primary:hover { background:var(--primary-dark); transform:translateY(-1px); box-shadow:0 4px 12px rgba(var(--primary-rgb),.35); }
    .btn-nav-outline { background:transparent; color:var(--primary); padding:.5rem 1.25rem; border-radius:var(--radius-md); font-size:.875rem; font-weight:600; border:1.5px solid var(--primary); cursor:pointer; transition:all .18s; }
    .btn-nav-outline:hover { background:var(--primary-bg); }

    /* ── HERO ── */
    .hero {
      background:linear-gradient(135deg, {{ $pBg }} 0%, {{ $pMid }} 40%, {{ $pLight }} 100%);
      padding:5rem 0 4rem; position:relative; overflow:hidden;
    }
    .hero::before {
      content:''; position:absolute; top:-30%; right:-10%;
      width:600px; height:600px; border-radius:50%;
      background:radial-gradient(circle, rgba(var(--primary-rgb),.12) 0%, transparent 70%);
      pointer-events:none;
    }
    .hero::after {
      content:''; position:absolute; bottom:-20%; left:-5%;
      width:400px; height:400px; border-radius:50%;
      background:radial-gradient(circle, rgba(255,138,128,.15) 0%, transparent 70%);
      pointer-events:none;
    }
    .hero-eyebrow { display:inline-flex; align-items:center; gap:.5rem; background:#fff; border:1.5px solid var(--primary-light); border-radius:99px; padding:.35rem .875rem; font-size:.78rem; font-weight:600; color:var(--primary); margin-bottom:1.5rem; }
    .hero-title { font-family:'Playfair Display',serif; font-size:clamp(2.2rem,5vw,3.5rem); font-weight:700; line-height:1.1; color:var(--gray-900); margin:0 0 1rem; }
    .hero-title span { color:var(--primary); }
    .hero-desc { font-size:clamp(.95rem,2vw,1.1rem); color:var(--gray-600); line-height:1.7; max-width:520px; margin:0 0 2rem; }
    .hero-actions { display:flex; flex-wrap:wrap; gap:.875rem; }
    .btn-hero-primary { background:var(--primary); color:#fff; padding:.875rem 2rem; border-radius:var(--radius-md); font-size:1rem; font-weight:600; border:none; cursor:pointer; transition:all .18s; box-shadow:0 4px 16px rgba(var(--primary-rgb),.3); display:inline-flex; align-items:center; gap:.5rem; }
    .btn-hero-primary:hover { background:var(--primary-dark); transform:translateY(-2px); box-shadow:0 8px 24px rgba(var(--primary-rgb),.4); color:#fff; }
    .btn-hero-secondary { background:#fff; color:var(--gray-900); padding:.875rem 2rem; border-radius:var(--radius-md); font-size:1rem; font-weight:600; border:1.5px solid var(--gray-200); cursor:pointer; transition:all .18s; display:inline-flex; align-items:center; gap:.5rem; box-shadow:var(--shadow-sm); }
    .btn-hero-secondary:hover { border-color:var(--primary); color:var(--primary); transform:translateY(-2px); }
    .hero-stats { display:flex; gap:2rem; flex-wrap:wrap; margin-top:2.5rem; padding-top:2rem; border-top:1px solid rgba(var(--primary-rgb),.15); }
    .hero-stat-num { font-family:'Playfair Display',serif; font-size:2rem; font-weight:700; color:var(--primary); line-height:1; }
    .hero-stat-lbl { font-size:.8rem; color:var(--gray-500); margin-top:.2rem; }
    .hero-img { width:100%; max-width:480px; border-radius:24px; box-shadow:0 24px 64px rgba(var(--primary-rgb),.15); object-fit:cover; }

    /* ── SECTIONS ── */
    .section { padding:4rem 0; }
    .section-alt { background:#fff; }
    .section-title { font-family:'Playfair Display',serif; font-size:clamp(1.6rem,3.5vw,2.2rem); font-weight:700; color:var(--gray-900); margin:0 0 .5rem; }
    .section-sub { font-size:.95rem; color:var(--gray-500); margin:0 0 2.5rem; }

    /* ── SHOP CARDS ── */
    .shop-card { background:#fff; border-radius:var(--radius-lg); overflow:hidden; border:1.5px solid var(--gray-100); transition:all .22s; cursor:pointer; height:100%; }
    .shop-card:hover { border-color:var(--primary-light); box-shadow:0 8px 32px rgba(var(--primary-rgb),.12); transform:translateY(-4px); }
    .shop-cover { width:100%; height:140px; object-fit:cover; background:linear-gradient(135deg,var(--primary-light),var(--secondary)); }
    .shop-logo-wrap { margin:-28px 0 0 1rem; position:relative; z-index:1; }
    .shop-logo { width:56px; height:56px; border-radius:14px; object-fit:cover; border:3px solid #fff; box-shadow:var(--shadow-sm); background:var(--primary); }
    .shop-info { padding:.75rem 1rem 1.25rem; }
    .shop-name { font-size:.975rem; font-weight:700; color:var(--gray-900); margin:0 0 .25rem; }
    .shop-city { font-size:.78rem; color:var(--gray-500); display:flex; align-items:center; gap:.3rem; }
    .verified-badge { display:inline-flex; align-items:center; gap:.25rem; background:#FFF3E0; color:#E65100; font-size:.68rem; font-weight:700; padding:.2rem .5rem; border-radius:99px; margin-left:.4rem; }
    .shop-rating { display:flex; align-items:center; gap:.35rem; font-size:.78rem; color:var(--gray-600); margin-top:.5rem; }
    .stars { color:#FFC107; font-size:.8rem; }

    /* ── PRODUCT CARDS ── */
    .product-card { background:#fff; border-radius:var(--radius-lg); overflow:hidden; border:1.5px solid var(--gray-100); transition:all .22s; }
    .product-card:hover { border-color:var(--primary-light); box-shadow:0 8px 32px rgba(var(--primary-rgb),.12); transform:translateY(-3px); }
    .product-img { width:100%; height:200px; object-fit:cover; }
    .product-body { padding:1rem; }
    .product-name { font-size:.9rem; font-weight:700; color:var(--gray-900); margin:0 0 .25rem; }
    .product-shop { font-size:.75rem; color:var(--gray-500); margin-bottom:.5rem; }
    .product-price { font-size:1rem; font-weight:700; color:var(--primary); }
    .product-flavor { display:inline-block; background:var(--gray-100); color:var(--gray-600); font-size:.7rem; padding:.2rem .6rem; border-radius:99px; margin-top:.4rem; }

    /* ── SEARCH ── */
    .search-bar { background:#fff; border-radius:var(--radius-lg); padding:1.5rem; box-shadow:var(--shadow-md); border:1.5px solid var(--gray-100); margin-bottom:2rem; }
    .search-input { border:1.5px solid var(--gray-200); border-radius:var(--radius-md); padding:.7rem 1rem; font-family:inherit; font-size:.9rem; width:100%; transition:border-color .15s; }
    .search-input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(var(--primary-rgb),.1); }

    /* ── HOW IT WORKS ── */
    .how-step { text-align:center; padding:1.5rem 1rem; }
    .how-icon { width:64px; height:64px; border-radius:20px; background:var(--primary-bg); display:inline-flex; align-items:center; justify-content:center; margin-bottom:1rem; }
    .how-title { font-size:.975rem; font-weight:700; color:var(--gray-900); margin:0 0 .4rem; }
    .how-desc { font-size:.82rem; color:var(--gray-500); line-height:1.6; margin:0; }

    /* ── FOOTER ── */
    .platform-footer { background:var(--gray-900); color:var(--gray-400); padding:3rem 0 2rem; }
    .footer-brand { font-family:'Playfair Display',serif; font-size:1.3rem; color:#fff; font-weight:700; margin-bottom:.5rem; }
    .footer-desc { font-size:.83rem; line-height:1.6; max-width:280px; }
    .footer-link { display:block; font-size:.83rem; color:var(--gray-500); margin-bottom:.4rem; transition:color .15s; }
    .footer-link:hover { color:#fff; }
    .footer-bottom { margin-top:2rem; padding-top:1.5rem; border-top:1px solid rgba(255,255,255,.08); font-size:.78rem; text-align:center; }

    /* ── RESPONSIVE ── */
    @media (max-width:768px) {
      .hero { padding:3rem 0 2.5rem; }
      .hero-img-wrap { display:none; }
      .hero-stats { gap:1.25rem; }
      .hero-stat-num { font-size:1.5rem; }
      .nav-links { display:none; }
      .nav-mobile-menu { display:flex; }
      .section { padding:2.5rem 0; }
    }
    @media (max-width:575px) {
      .platform-nav { padding:.65rem 0; }
      .nav-brand { min-width:0; flex:1; font-size:clamp(1rem, 5vw, 1.2rem); }
      .nav-brand i { flex-shrink:0; }
      .platform-nav .container > div:first-child { gap:.6rem; }
      .platform-nav .container > div:first-child > div:last-child { gap:.45rem !important; flex-shrink:0; }
      .btn-nav-primary, .btn-nav-outline { padding:.45rem .7rem; font-size:.78rem; }
      .nav-link-item { font-size:.82rem; }
      .hero { padding:2.25rem 0 2rem; }
      .hero-actions > a,
      .btn-hero-primary,
      .btn-hero-secondary { width:100%; justify-content:center; padding:.8rem 1rem; }
      .hero-stats { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:.75rem; }
      .hero-stat-num { font-size:1.35rem; }
      .hero-stat-lbl { font-size:.72rem; }
      .search-bar { padding:1rem; }
      .product-img { height:160px; }
      .shop-cover { height:120px; }
    }
    @media (max-width:380px) {
      .hero-stats { grid-template-columns:1fr; }
      .product-img { height:135px; }
      .product-body { padding:.8rem; }
      .btn-nav-primary { display:none; }
    }
    @media (min-width:769px) {
      .nav-mobile-menu { display:none; }
    }
  </style>
</head>
<body>

{{-- NAV --}}
<nav class="platform-nav" id="mainNav">
  <div class="container">
    <div style="display:flex;align-items:center;justify-content:space-between">
      <a href="{{ route('platform.home') }}" class="nav-brand">
        <i class="bi bi-shop" style="font-size:1.2rem"></i>
        {{ $platform->platform_name ?? 'CakeShop' }}
      </a>

      {{-- Desktop nav --}}
      <div class="nav-links">
        <a href="{{ route('platform.shops') }}" class="nav-link-item">Browse Shops</a>
        <a href="{{ route('catalog') }}" class="nav-link-item">All Products</a>
        <a href="{{ route('seller.apply') }}" class="nav-link-item">Sell on Platform</a>
      </div>

      <div style="display:flex;align-items:center;gap:.75rem">
        @if(session('user'))
          <a href="{{ session('user')['role'] === 'admin' || session('user')['role'] === 'superadmin' ? route('admin.dashboard') : route('customer.orders') }}"
             class="btn-nav-outline">My Account</a>
        @else
          <a href="{{ route('login') }}" class="nav-link-item">Sign In</a>
          <a href="{{ route('seller.apply') }}" class="btn-nav-primary">Become a Seller</a>
        @endif

        {{-- Mobile menu btn --}}
        <button class="nav-mobile-menu btn" style="background:none;border:none;padding:.25rem" onclick="toggleMobileNav()">
          <i class="bi bi-list" style="font-size:1.4rem;color:var(--gray-700)"></i>
        </button>
      </div>
    </div>

    {{-- Mobile nav --}}
    <div id="mobileNav" style="display:none;padding:1rem 0 .5rem;border-top:1px solid var(--gray-200);margin-top:.875rem">
      <a href="{{ route('platform.shops') }}" style="display:block;padding:.6rem 0;font-size:.9rem;font-weight:500;color:var(--gray-700);border-bottom:1px solid var(--gray-100)">Browse Shops</a>
      <a href="{{ route('catalog') }}" style="display:block;padding:.6rem 0;font-size:.9rem;font-weight:500;color:var(--gray-700);border-bottom:1px solid var(--gray-100)">All Products</a>
      <a href="{{ route('seller.apply') }}" style="display:block;padding:.6rem 0;font-size:.9rem;font-weight:500;color:var(--gray-700);border-bottom:1px solid var(--gray-100)">Sell on Platform</a>
      <a href="{{ route('login') }}" style="display:block;padding:.6rem 0;font-size:.9rem;font-weight:500;color:var(--primary)">Sign In</a>
    </div>
  </div>
</nav>

{{-- HERO --}}
<section class="hero">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <div class="hero-eyebrow">
          <i class="bi bi-geo-alt-fill" style="font-size:.75rem"></i>
          Pangasinan's Online Cake Platform
        </div>
        <h1 class="hero-title">
          Order Fresh<br><span>Artisan Cakes</span><br>from Local Shops
        </h1>
        <p class="hero-desc">
          Discover handcrafted cakes from verified local cake shops. Order online, track in real time, and enjoy delivery or pickup — all in one place.
        </p>
        <div class="hero-actions">
          <a href="{{ route('platform.shops') }}" class="btn-hero-primary">
            <i class="bi bi-shop"></i> Browse Shops
          </a>
          <a href="{{ route('catalog') }}" class="btn-hero-secondary">
            <i class="bi bi-grid-3x3-gap"></i> View All Products
          </a>
        </div>
        <div class="hero-stats">
          <div>
            <div class="hero-stat-num">{{ $stats['shops'] }}</div>
            <div class="hero-stat-lbl">Verified Shops</div>
          </div>
          <div>
            <div class="hero-stat-num">{{ $stats['products'] }}</div>
            <div class="hero-stat-lbl">Products Available</div>
          </div>
          <div>
            <div class="hero-stat-num">{{ $stats['orders'] }}</div>
            <div class="hero-stat-lbl">Orders Completed</div>
          </div>
        </div>
      </div>
      <div class="col-lg-6 hero-img-wrap text-center">
        <img src="/background_system_opening.png" alt="Artisan Cakes" class="hero-img"
             onerror="this.style.display='none'">
      </div>
    </div>
  </div>
</section>

{{-- SHOPS SECTION --}}
@if($shops->count() > 0)
<section class="section section-alt">
  <div class="container">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:2.5rem">
      <div>
        <h2 class="section-title">Our Cake Shops</h2>
        <p class="section-sub" style="margin:0">Discover local cake shops near you</p>
      </div>
      <a href="{{ route('platform.shops') }}" style="font-size:.875rem;font-weight:600;color:var(--primary);display:flex;align-items:center;gap:.3rem">
        View all shops <i class="bi bi-arrow-right"></i>
      </a>
    </div>
    <div class="row g-3">
      @foreach($shops->take(6) as $shop)
      <div class="col-sm-6 col-lg-4">
        <a href="{{ route('platform.shop', $shop->shop_slug) }}" style="display:block;height:100%">
          <div class="shop-card">
            {{-- Cover --}}
            @if($shop->shop_cover)
              <img src="{{ $shop->shop_cover }}" class="shop-cover" alt="{{ $shop->shop_name }}"
                   onerror="this.style.background='linear-gradient(135deg,#FFCDD2,#FF8A80)'">
            @else
              <div class="shop-cover" style="display:flex;align-items:center;justify-content:center">
                <i class="bi bi-shop" style="font-size:2.5rem;color:rgba(var(--primary-rgb),.3)"></i>
              </div>
            @endif

            <div class="shop-logo-wrap">
              @if($shop->shop_logo)
                <img src="{{ $shop->shop_logo }}" class="shop-logo" alt="">
              @else
                <div class="shop-logo" style="display:inline-flex;align-items:center;justify-content:center;font-size:1.2rem;color:#fff;font-weight:700">
                  {{ strtoupper(substr($shop->shop_name, 0, 1)) }}
                </div>
              @endif
            </div>

            <div class="shop-info">
              <div style="display:flex;align-items:center;flex-wrap:wrap;gap:.25rem">
                <span class="shop-name">{{ $shop->shop_name }}</span>
                @if($shop->tier === 'verified')
                  <span class="verified-badge"><i class="bi bi-patch-check-fill"></i> Verified</span>
                @endif
              </div>
              <div class="shop-city">
                <i class="bi bi-geo-alt" style="font-size:.75rem"></i>
                {{ $shop->city ?? 'Philippines' }}
              </div>
              <div class="shop-rating">
                @php $rating = round($shop->avg_rating ?? 0, 1); @endphp
                <span class="stars">
                  @for($i = 1; $i <= 5; $i++)
                    <i class="bi bi-star{{ $i <= $rating ? '-fill' : ($i - $rating < 1 ? '-half' : '') }}"></i>
                  @endfor
                </span>
                <span>{{ $rating > 0 ? number_format($rating, 1) : 'New' }}</span>
                @if($shop->review_count > 0)
                  <span style="color:var(--gray-400)">({{ $shop->review_count }})</span>
                @endif
                <span style="margin-left:auto;font-size:.75rem;color:var(--gray-400)">
                  {{ $shop->product_count }} product{{ $shop->product_count != 1 ? 's' : '' }}
                </span>
              </div>
            </div>
          </div>
        </a>
      </div>
      @endforeach
    </div>
  </div>
</section>
@endif

{{-- FEATURED PRODUCTS --}}
@if($featuredProducts->count() > 0)
<section class="section">
  <div class="container">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:2.5rem">
      <div>
        <h2 class="section-title">Featured Cakes</h2>
        <p class="section-sub" style="margin:0">Fresh picks from our verified shops</p>
      </div>
      <a href="{{ route('catalog') }}" style="font-size:.875rem;font-weight:600;color:var(--primary);display:flex;align-items:center;gap:.3rem">
        View all <i class="bi bi-arrow-right"></i>
      </a>
    </div>
    <div class="row g-3">
      @foreach($featuredProducts as $p)
      <div class="col-6 col-md-4 col-lg-3">
        <a href="{{ route('platform.shop', $p->shop_slug) }}" style="display:block;height:100%">
          <div class="product-card" style="height:100%">
            @if($p->image_path)
              <img src="{{ $p->image_path }}" class="product-img" alt="{{ $p->name }}"
                   onerror="this.style.background='linear-gradient(135deg,#FFE0E0,#FFCDD2)';this.src=''">
            @else
              <div class="product-img" style="display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#FFE0E0,#FFCDD2)">
                <i class="bi bi-cake2" style="font-size:2.5rem;color:var(--primary);opacity:.4"></i>
              </div>
            @endif
            <div class="product-body">
              <div class="product-name">{{ $p->name }}</div>
              <div class="product-shop">
                <i class="bi bi-shop" style="font-size:.7rem"></i> {{ $p->shop_name }}
                @if($p->tier === 'verified')
                  <i class="bi bi-patch-check-fill" style="color:#E65100;font-size:.7rem;margin-left:.2rem"></i>
                @endif
              </div>
              <div class="product-price">₱{{ number_format($p->price, 2) }}</div>
              @if($p->flavor)
                <span class="product-flavor">{{ $p->flavor }}</span>
              @endif
            </div>
          </div>
        </a>
      </div>
      @endforeach
    </div>
  </div>
</section>
@endif

{{-- HOW IT WORKS --}}
<section class="section section-alt">
  <div class="container">
    <div style="text-align:center;margin-bottom:2.5rem">
      <h2 class="section-title">How It Works</h2>
      <p class="section-sub">Order your dream cake in 3 easy steps</p>
    </div>
    <div class="row g-3 justify-content-center">
      @foreach([
        ['bi-search','Browse Shops','Explore our verified local cake shops and their products.'],
        ['bi-bag-check','Place Your Order','Choose your cake, set a date, and pay a deposit online.'],
        ['bi-truck','Receive Your Cake','Get it delivered or pick it up at the shop on your chosen date.'],
      ] as [$icon, $title, $desc])
      <div class="col-md-4">
        <div class="how-step">
          <div class="how-icon">
            <i class="bi {{ $icon }}" style="font-size:1.6rem;color:var(--primary)"></i>
          </div>
          <div class="how-title">{{ $title }}</div>
          <p class="how-desc">{{ $desc }}</p>
        </div>
      </div>
      @endforeach
    </div>
  </div>
</section>

{{-- CTA --}}
<section style="background:linear-gradient(135deg,var(--primary) 0%,var(--primary-dark) 100%);padding:4rem 0;text-align:center">
  <div class="container">
    <h2 style="font-family:'Playfair Display',serif;font-size:clamp(1.6rem,3.5vw,2.2rem);font-weight:700;color:#fff;margin:0 0 .75rem">
      Own a Cake Shop? Sell with Us.
    </h2>
    <p style="font-size:.975rem;color:rgba(255,255,255,.8);margin:0 0 2rem;max-width:440px;margin-left:auto;margin-right:auto;line-height:1.6">
      Join our platform, reach more customers, and grow your cake business online.
    </p>
    <a href="{{ route('seller.apply') }}"
       style="display:inline-flex;align-items:center;gap:.6rem;background:#fff;color:var(--primary);padding:.875rem 2rem;border-radius:var(--radius-md);font-size:.975rem;font-weight:700;box-shadow:0 4px 16px rgba(0,0,0,.15);transition:all .2s"
       onmouseover="this.style.transform='translateY(-2px)'"
       onmouseout="this.style.transform='none'">
      <i class="bi bi-shop"></i> Apply as Seller
    </a>
  </div>
</section>

{{-- FOOTER --}}
<footer class="platform-footer">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-4">
        @if(!empty($platform->platform_logo))
          <img src="{{ $platform->platform_logo }}" style="height:48px;width:auto;object-fit:contain;border-radius:8px;margin-bottom:.6rem;display:block" onerror="this.style.display='none'">
        @endif
        <div class="footer-brand">{{ $platform->platform_name ?? 'CakeShop' }}</div>
        <p class="footer-desc">Your local cake cakeshop — connecting customers with the best artisan cake shops in Pangasinan.</p>
      </div>
      <div class="col-6 col-md-2">
        <div style="font-size:.78rem;font-weight:700;color:var(--gray-400);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.875rem">Platform</div>
        <a href="{{ route('platform.shops') }}" class="footer-link">Browse Shops</a>
        <a href="{{ route('catalog') }}" class="footer-link">All Products</a>
        <a href="{{ route('seller.apply') }}" class="footer-link">Become a Seller</a>
      </div>
      <div class="col-6 col-md-2">
        <div style="font-size:.78rem;font-weight:700;color:var(--gray-400);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.875rem">Account</div>
        <a href="{{ route('login') }}" class="footer-link">Sign In</a>
        <a href="{{ route('register') }}" class="footer-link">Register</a>
        <a href="{{ route('catalog') }}" class="footer-link">Track Order</a>
      </div>
    </div>
    <div class="footer-bottom">
      &copy; {{ date('Y') }} {{ $platform->platform_name ?? 'CakeShop Platform' }}. All rights reserved.
    </div>
  </div>
</footer>

<script>
// Sticky nav shadow
window.addEventListener('scroll', function() {
  document.getElementById('mainNav').classList.toggle('scrolled', window.scrollY > 10);
});

function toggleMobileNav() {
  const nav = document.getElementById('mobileNav');
  nav.style.display = nav.style.display === 'none' ? 'block' : 'none';
}
</script>
</body>
</html>
