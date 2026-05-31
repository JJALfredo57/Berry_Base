<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Browse Shops — Cake Shop Platform</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap');
    :root{--primary:#E53935;--primary-dark:#B71C1C;--primary-light:#FFCDD2;--primary-bg:#FFF8F8;--cream:#FFF8F8;--gray-50:#FAFAFA;--gray-100:#F5F5F5;--gray-200:#EEEEEE;--gray-300:#E0E0E0;--gray-400:#BDBDBD;--gray-500:#9E9E9E;--gray-600:#757575;--gray-700:#616161;--gray-800:#424242;--gray-900:#212121;--radius-md:10px;--radius-lg:16px;--shadow-sm:0 1px 3px rgba(0,0,0,.08);--shadow-md:0 4px 12px rgba(0,0,0,.08)}
    *,*::before,*::after{box-sizing:border-box}
    html,body{width:100%;max-width:100%;overflow-x:hidden}
    body{font-family:'DM Sans',system-ui,sans-serif;background:var(--cream);color:var(--gray-900);margin:0;-webkit-font-smoothing:antialiased}
    img,svg,video,canvas{max-width:100%;height:auto}
    a{text-decoration:none;color:inherit}
    .platform-nav{position:sticky;top:0;z-index:100;background:rgba(255,248,248,.92);backdrop-filter:blur(12px);border-bottom:1px solid rgba(229,57,53,.1);padding:.875rem 0}
    .nav-brand{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:700;color:var(--primary)}
    .shop-card{background:#fff;border-radius:var(--radius-lg);overflow:hidden;border:1.5px solid var(--gray-100);transition:all .22s;cursor:pointer;height:100%}
    .shop-card:hover{border-color:var(--primary-light);box-shadow:0 8px 32px rgba(229,57,53,.12);transform:translateY(-4px)}
    .shop-cover{width:100%;height:140px;object-fit:cover;background:linear-gradient(135deg,var(--primary-light),#FF8A80)}
    .shop-logo-wrap{margin:-28px 0 0 1rem;position:relative;z-index:1}
    .shop-logo{width:56px;height:56px;border-radius:14px;object-fit:cover;border:3px solid #fff;box-shadow:var(--shadow-sm);background:var(--primary);display:inline-flex;align-items:center;justify-content:center;font-size:1.2rem;color:#fff;font-weight:700}
    .shop-info{padding:.75rem 1rem 1.25rem}
    .shop-name{font-size:.975rem;font-weight:700;color:var(--gray-900);margin:0 0 .25rem}
    .verified-badge{display:inline-flex;align-items:center;gap:.25rem;background:#FFF3E0;color:#E65100;font-size:.68rem;font-weight:700;padding:.2rem .5rem;border-radius:99px;margin-left:.4rem}
    .shop-rating{display:flex;align-items:center;gap:.35rem;font-size:.78rem;color:var(--gray-600);margin-top:.5rem}
    .stars{color:#FFC107;font-size:.8rem}
    .form-control,.form-select{border:1.5px solid var(--gray-200);border-radius:var(--radius-md);padding:.6rem .875rem;font-family:inherit;font-size:.875rem}
    .form-control:focus,.form-select:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(229,57,53,.1)}
    .btn-primary{background:var(--primary);border-color:var(--primary);color:#fff;border-radius:var(--radius-md);font-weight:600}
    .btn-primary:hover{background:var(--primary-dark);border-color:var(--primary-dark);color:#fff}
    .btn-outline-secondary{border-radius:var(--radius-md)}
    .empty-state{text-align:center;padding:4rem 1rem}
    .empty-icon{width:80px;height:80px;border-radius:24px;background:var(--gray-100);display:inline-flex;align-items:center;justify-content:center;margin-bottom:1.25rem}
    @media(max-width:768px){.filter-row{flex-direction:column}}
    @media(max-width:575px){
      .platform-nav{padding:.65rem 0}
      .nav-brand{min-width:0;max-width:70%;font-size:clamp(1rem,5vw,1.2rem)}
      .shop-cover{height:120px}
      .shop-info{padding:.75rem .85rem 1rem}
      .shop-rating{flex-wrap:wrap;gap:.3rem .45rem}
      .shop-rating span:last-child{margin-left:0!important}
      .btn{min-height:40px}
      .container[style*="padding"]{padding:1.25rem .75rem!important}
    }
  </style>
</head>
<body>

{{-- NAV --}}
<nav class="platform-nav">
  <div class="container">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem">
      <a href="{{ route('platform.home') }}" class="nav-brand" style="display:flex;align-items:center;gap:.4rem">
        <i class="bi bi-arrow-left" style="font-size:1rem"></i>
        <i class="bi bi-shop" style="font-size:1.1rem"></i>
        <span class="d-none d-sm-inline">{{ $platform->platform_name ?? 'Cake Shop Platform' }}</span>
      </a>
      <div style="display:flex;gap:.75rem">
        <a href="{{ route('login') }}" style="font-size:.875rem;font-weight:500;color:var(--gray-700)">Sign In</a>
      </div>
    </div>
  </div>
</nav>

{{-- PAGE HEADER --}}
<div style="background:#fff;border-bottom:1px solid var(--gray-100);padding:1.75rem 0">
  <div class="container">
    <h1 style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:700;color:var(--gray-900);margin:0 0 .25rem">Browse Cake Shops</h1>
    <p style="font-size:.875rem;color:var(--gray-500);margin:0">{{ $shops->total() }} shop{{ $shops->total() != 1 ? 's' : '' }} found</p>
  </div>
</div>

<div class="container" style="padding:2rem 1rem">

  {{-- FILTERS --}}
  <form method="GET" action="{{ route('platform.shops') }}" id="filterForm">
    <div style="background:#fff;border-radius:var(--radius-lg);padding:1.25rem;box-shadow:var(--shadow-sm);border:1.5px solid var(--gray-100);margin-bottom:2rem">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-5">
          <label style="font-size:.8rem;font-weight:600;color:var(--gray-700);display:block;margin-bottom:.3rem">Search Shops</label>
          <div style="position:relative">
            <i class="bi bi-search" style="position:absolute;left:.875rem;top:50%;transform:translateY(-50%);color:var(--gray-400);font-size:.875rem;pointer-events:none"></i>
            <input type="text" class="form-control" name="q" value="{{ $search }}"
                   placeholder="Shop name, city..." style="padding-left:2.5rem">
          </div>
        </div>
        <div class="col-6 col-md-3">
          <label style="font-size:.8rem;font-weight:600;color:var(--gray-700);display:block;margin-bottom:.3rem">City</label>
          <select class="form-select" name="city" onchange="this.form.submit()">
            <option value="">All Cities</option>
            @foreach($cities as $c)
              <option value="{{ $c }}" {{ $city === $c ? 'selected' : '' }}>{{ $c }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label style="font-size:.8rem;font-weight:600;color:var(--gray-700);display:block;margin-bottom:.3rem">Tier</label>
          <select class="form-select" name="tier" onchange="this.form.submit()">
            <option value="">All Tiers</option>
            <option value="verified" {{ $tier === 'verified' ? 'selected' : '' }}>Verified</option>
            <option value="basic" {{ $tier === 'basic' ? 'selected' : '' }}>Basic</option>
          </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill" style="padding:.6rem">Search</button>
          @if($search || $city || $tier)
            <a href="{{ route('platform.shops') }}" class="btn btn-outline-secondary" style="padding:.6rem .875rem" title="Clear filters">
              <i class="bi bi-x-lg"></i>
            </a>
          @endif
        </div>
      </div>
    </div>
  </form>

  {{-- ACTIVE FILTERS --}}
  @if($search || $city || $tier)
  <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1.25rem">
    <span style="font-size:.8rem;color:var(--gray-500);align-self:center">Filters:</span>
    @if($search)
      <span style="background:var(--primary-bg);color:var(--primary);border:1px solid var(--primary-light);border-radius:99px;padding:.2rem .75rem;font-size:.78rem;font-weight:600;display:inline-flex;align-items:center;gap:.4rem">
        "{{ $search }}"
        <a href="{{ route('platform.shops', array_filter(['city'=>$city,'tier'=>$tier])) }}" style="color:var(--primary)">&times;</a>
      </span>
    @endif
    @if($city)
      <span style="background:var(--primary-bg);color:var(--primary);border:1px solid var(--primary-light);border-radius:99px;padding:.2rem .75rem;font-size:.78rem;font-weight:600;display:inline-flex;align-items:center;gap:.4rem">
        {{ $city }}
        <a href="{{ route('platform.shops', array_filter(['q'=>$search,'tier'=>$tier])) }}" style="color:var(--primary)">&times;</a>
      </span>
    @endif
    @if($tier)
      <span style="background:var(--primary-bg);color:var(--primary);border:1px solid var(--primary-light);border-radius:99px;padding:.2rem .75rem;font-size:.78rem;font-weight:600;display:inline-flex;align-items:center;gap:.4rem">
        {{ ucfirst($tier) }} Seller
        <a href="{{ route('platform.shops', array_filter(['q'=>$search,'city'=>$city])) }}" style="color:var(--primary)">&times;</a>
      </span>
    @endif
  </div>
  @endif

  {{-- SHOPS GRID --}}
  @if($shops->count() > 0)
  <div class="row g-3">
    @foreach($shops as $shop)
    <div class="col-sm-6 col-lg-4">
      <a href="{{ route('platform.shop', $shop->shop_slug) }}" style="display:block;height:100%">
        <div class="shop-card">
          @if($shop->shop_cover)
            <img src="{{ $shop->shop_cover }}" class="shop-cover" alt="{{ $shop->shop_name }}"
                 onerror="this.style.background='linear-gradient(135deg,#FFCDD2,#FF8A80)';this.src=''">
          @else
            <div class="shop-cover" style="display:flex;align-items:center;justify-content:center">
              <i class="bi bi-shop" style="font-size:2.5rem;color:rgba(229,57,53,.25)"></i>
            </div>
          @endif

          <div class="shop-logo-wrap">
            @if($shop->shop_logo)
              <img src="{{ $shop->shop_logo }}" class="shop-logo" alt="">
            @else
              <div class="shop-logo">{{ strtoupper(substr($shop->shop_name, 0, 1)) }}</div>
            @endif
          </div>

          <div class="shop-info">
            <div style="display:flex;align-items:center;flex-wrap:wrap;gap:.25rem;margin-bottom:.25rem">
              <span class="shop-name">{{ $shop->shop_name }}</span>
              @if($shop->tier === 'verified')
                <span class="verified-badge"><i class="bi bi-patch-check-fill"></i> Verified</span>
              @endif
            </div>
            <div style="font-size:.78rem;color:var(--gray-500);display:flex;align-items:center;gap:.3rem">
              <i class="bi bi-geo-alt" style="font-size:.75rem"></i>{{ $shop->city ?? 'Philippines' }}
            </div>
            @if($shop->description)
              <p style="font-size:.78rem;color:var(--gray-600);margin:.5rem 0 0;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
                {{ $shop->description }}
              </p>
            @endif
            <div class="shop-rating">
              @php $rating = round($shop->avg_rating ?? 0, 1); @endphp
              <span class="stars">
                @for($i = 1; $i <= 5; $i++)
                  <i class="bi bi-star{{ $i <= $rating ? '-fill' : ($i - $rating < 1 ? '-half' : '') }}"></i>
                @endfor
              </span>
              <span>{{ $rating > 0 ? number_format($rating,1) : 'New' }}</span>
              @if($shop->review_count > 0)
                <span style="color:var(--gray-400)">({{ $shop->review_count }})</span>
              @endif
              <span style="margin-left:auto;background:var(--gray-100);color:var(--gray-600);font-size:.7rem;padding:.15rem .5rem;border-radius:99px">
                {{ $shop->product_count }} products
              </span>
            </div>
          </div>
        </div>
      </a>
    </div>
    @endforeach
  </div>

  {{-- PAGINATION --}}
  @if($shops->hasPages())
  <div style="margin-top:2rem">
    {{ $shops->withQueryString()->links() }}
  </div>
  @endif

  @else
  <div class="empty-state">
    <div class="empty-icon"><i class="bi bi-shop" style="font-size:2rem;color:var(--gray-400)"></i></div>
    <h3 style="font-size:1.1rem;font-weight:700;color:var(--gray-900);margin:0 0 .5rem">No shops found</h3>
    <p style="font-size:.875rem;color:var(--gray-500);margin:0 0 1.5rem">Try adjusting your search or filters.</p>
    <a href="{{ route('platform.shops') }}" class="btn btn-primary" style="padding:.6rem 1.5rem">Clear Filters</a>
  </div>
  @endif

</div>

<footer style="background:#1a1a1a;color:#9ca3af;padding:2rem 0;margin-top:3rem;text-align:center">
  <div class="container">
    @if(!empty($platform->platform_logo))
      <img src="{{ $platform->platform_logo }}" style="height:44px;width:auto;object-fit:contain;border-radius:8px;margin-bottom:.75rem;display:block;margin-left:auto;margin-right:auto" onerror="this.style.display='none'">
    @endif
    <div style="font-size:1rem;font-weight:700;color:#fff;margin-bottom:.3rem">{{ $platform->platform_name ?? 'Cake Shop Platform' }}</div>
    <div style="font-size:.78rem">&copy; {{ date('Y') }} {{ $platform->platform_name ?? 'Cake Shop Platform' }}. All rights reserved.</div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
