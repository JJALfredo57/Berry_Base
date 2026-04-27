<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $shop->shop_name }}</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  @php
    $tc        = $shop->theme_color ?? '#E53935';
    $sbgType   = $shopSettings->bg_type ?? 'color';
    $sbgColor  = $shopSettings->bg_color ?? '#f9f9f9';
    $sbgGradS  = $shopSettings->gradient_start ?? '#fff7fb';
    $sbgGradE  = $shopSettings->gradient_end   ?? '#ffe3f1';
    $sbgImg    = $shopSettings->bg_image_path  ?? '';
    $sbgOpacity= (float)($shopSettings->bg_image_opacity ?? 1.0);
    if ($sbgType === 'gradient') {
        $bodyBg = "background:linear-gradient(135deg,{$sbgGradS} 0%,{$sbgGradE} 100%);";
    } elseif ($sbgType === 'image' && $sbgImg) {
        $bodyBg = "background:{$sbgColor};";
    } else {
        $bodyBg = "background:{$sbgColor};";
    }
  @endphp
  <style>
    :root{
      --primary:{{ $tc }};
      --primary-dark:color-mix(in srgb,{{ $tc }} 78%,black);
      --primary-light:color-mix(in srgb,{{ $tc }} 35%,white);
      --primary-bg:color-mix(in srgb,{{ $tc }} 9%,white);
      --gray-100:#F5F5F5;--gray-200:#EEEEEE;--gray-300:#E0E0E0;--gray-400:#BDBDBD;
      --gray-500:#9E9E9E;--gray-600:#757575;--gray-700:#616161;--gray-800:#424242;--gray-900:#212121;
      --radius-md:10px;--radius-lg:16px;
      --shadow-sm:0 1px 3px rgba(0,0,0,.08);--shadow-md:0 4px 16px rgba(0,0,0,.1);
    }
    *,*::before,*::after{box-sizing:border-box}
    body{font-family:'DM Sans',system-ui,sans-serif;{{ $bodyBg }}color:var(--gray-900);margin:0;-webkit-font-smoothing:antialiased}
    a{text-decoration:none;color:inherit}

    /* ── Navbar ── */
    .top-nav{
      position:sticky;top:0;z-index:200;
      background:rgba(255,255,255,.96);
      backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
      border-bottom:1px solid rgba(0,0,0,.07);
      padding:.6rem 0;
    }
    .nav-inner{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:0 clamp(1rem,4vw,2rem)}
    .nav-back{display:flex;align-items:center;gap:.4rem;font-size:.85rem;font-weight:600;color:var(--gray-600);transition:color .15s;border:1.5px solid var(--gray-300);padding:.35rem .8rem;border-radius:var(--radius-md)}
    .nav-back:hover{color:var(--primary);border-color:var(--primary)}
    .nav-shop-name{font-family:'Playfair Display',serif;font-size:1.05rem;font-weight:700;color:var(--gray-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px}
    .btn-order-now{font-size:.83rem;font-weight:700;color:#fff;background:var(--primary);border:none;border-radius:var(--radius-md);padding:.45rem 1.1rem;transition:all .15s;box-shadow:0 2px 8px rgba(229,57,53,.25);display:inline-flex;align-items:center;gap:.4rem}
    .btn-order-now:hover{background:var(--primary-dark);transform:translateY(-1px);color:#fff}
    .btn-custom-cake{font-size:.83rem;font-weight:700;color:var(--primary);background:#fff;border:2px solid var(--primary);border-radius:var(--radius-md);padding:.45rem 1.1rem;transition:all .15s;display:inline-flex;align-items:center;gap:.4rem;cursor:pointer}
    .btn-custom-cake:hover{background:var(--primary);color:#fff;transform:translateY(-1px)}

    /* ── Cover ── */
    .cover-wrap{height:280px;position:relative;overflow:hidden;background:linear-gradient(135deg,var(--primary) 0%,var(--primary-light) 60%,var(--primary-bg) 100%)}
    .cover-wrap img{width:100%;height:100%;object-fit:cover}
    .cover-overlay{position:absolute;inset:0;background:linear-gradient(to bottom,rgba(0,0,0,.05) 0%,rgba(0,0,0,.45) 100%)}
    @media(max-width:576px){.cover-wrap{height:200px}}

    /* ── Shop Header ── */
    .shop-header{background:#fff;border-bottom:1px solid var(--gray-200)}
    .shop-header-inner{padding:0 clamp(1rem,4vw,2rem)}
    .shop-logo-wrap{margin-top:-52px;margin-bottom:.75rem;position:relative;display:inline-block}
    .shop-logo{
      width:96px;height:96px;border-radius:22px;
      border:4px solid #fff;box-shadow:0 4px 20px rgba(0,0,0,.15);
      object-fit:cover;display:block;
    }
    .shop-logo-placeholder{
      width:96px;height:96px;border-radius:22px;
      border:4px solid #fff;box-shadow:0 4px 20px rgba(0,0,0,.15);
      background:var(--primary);display:flex;align-items:center;justify-content:center;
      font-family:'Playfair Display',serif;font-size:2.4rem;color:#fff;font-weight:700;
    }
    .shop-name{font-family:'Playfair Display',serif;font-size:clamp(1.3rem,3.5vw,1.85rem);font-weight:700;color:var(--gray-900);margin:0}
    .badge-verified{display:inline-flex;align-items:center;gap:.3rem;background:#FFF3E0;color:#E65100;font-size:.7rem;font-weight:700;padding:.22rem .65rem;border-radius:99px}
    .badge-new{display:inline-flex;align-items:center;gap:.3rem;background:#E8F5E9;color:#2E7D32;font-size:.7rem;font-weight:700;padding:.22rem .65rem;border-radius:99px}
    .shop-meta{display:flex;flex-wrap:wrap;gap:.75rem 1.25rem;font-size:.8rem;color:var(--gray-500);margin-top:.5rem}
    .shop-meta span{display:flex;align-items:center;gap:.3rem}
    .shop-meta i{color:var(--primary)}
    .stars{color:#FFC107}

    /* ── Tabs ── */
    .tab-bar{display:flex;gap:0;border-bottom:2px solid var(--gray-200);margin-top:1.25rem;overflow-x:auto}
    .tab-btn{padding:.65rem 1.35rem;font-size:.875rem;font-weight:600;color:var(--gray-500);border:none;background:transparent;cursor:pointer;border-bottom:2.5px solid transparent;margin-bottom:-2px;transition:all .15s;white-space:nowrap}
    .tab-btn.active{color:var(--primary);border-bottom-color:var(--primary)}
    .tab-pane{display:none;padding:1.75rem clamp(1rem,4vw,2rem)}
    .tab-pane.active{display:block}

    /* ── Product Cards ── */
    .product-card{background:#fff;border-radius:var(--radius-lg);overflow:hidden;border:1.5px solid var(--gray-200);transition:all .22s;height:100%;display:flex;flex-direction:column}
    .product-card:hover{border-color:var(--primary-light);box-shadow:0 8px 32px rgba(229,57,53,.13);transform:translateY(-4px)}
    .product-img-wrap{height:200px;overflow:hidden;background:linear-gradient(135deg,var(--primary-bg),var(--primary-light));position:relative}
    .product-img-wrap img{width:100%;height:100%;object-fit:cover;transition:transform .4s ease}
    .product-card:hover .product-img-wrap img{transform:scale(1.07)}
    .product-img-ph{width:100%;height:100%;display:flex;align-items:center;justify-content:center}
    .product-body{padding:1rem;flex:1;display:flex;flex-direction:column}
    .product-name{font-size:.9rem;font-weight:700;color:var(--gray-900);margin:0 0 .25rem}
    .product-desc{font-size:.75rem;color:var(--gray-500);line-height:1.5;margin:.25rem 0;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;flex-grow:1}
    .product-price{font-size:1.05rem;font-weight:700;color:var(--primary);margin-top:.5rem}
    .flavor-tag{display:inline-block;background:var(--primary-bg);color:var(--primary);font-size:.68rem;padding:.2rem .6rem;border-radius:99px;margin:.35rem 0;font-weight:600}
    .class-badge{position:absolute;top:.6rem;left:.6rem;font-size:.68rem;font-weight:700;padding:.2rem .55rem;border-radius:99px}
    .btn-order{display:block;width:100%;background:var(--primary);color:#fff;padding:.55rem 1rem;border-radius:var(--radius-md);font-size:.83rem;font-weight:700;border:none;cursor:pointer;transition:all .18s;text-align:center;margin-top:.75rem}
    .btn-order:hover{background:var(--primary-dark);transform:translateY(-1px);color:#fff}

    /* ── Reviews ── */
    .review-card{background:#fff;border-radius:var(--radius-md);padding:1.1rem 1.25rem;border:1.5px solid var(--gray-200);margin-bottom:.75rem}
    .reviewer-avatar{width:40px;height:40px;border-radius:50%;background:var(--primary-bg);display:flex;align-items:center;justify-content:center;font-size:.9rem;font-weight:700;color:var(--primary);flex-shrink:0}

    /* ── Info rows ── */
    .info-row{display:flex;gap:1rem;padding:1rem 1.25rem;border-bottom:1px solid var(--gray-100);align-items:flex-start}
    .info-icon{width:36px;height:36px;border-radius:10px;background:var(--primary-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0}

    /* ── Filter chips ── */
    .filter-chip{padding:.32rem .85rem;border-radius:99px;border:1.5px solid var(--gray-200);background:#fff;color:var(--gray-700);font-size:.76rem;font-weight:600;cursor:pointer;transition:all .15s}
    .filter-chip.active,.filter-chip:hover{background:var(--primary);color:#fff;border-color:var(--primary)}

    /* ── Empty ── */
    .empty-state{text-align:center;padding:4rem 1rem}
  </style>
</head>
<body>

{{-- Shop background image overlay --}}
@if($sbgType === 'image' && !empty($sbgImg))
<div aria-hidden="true" style="position:fixed;inset:0;z-index:-1;pointer-events:none;background:url('{{ $sbgImg }}') center/cover no-repeat;opacity:{{ $sbgOpacity }};"></div>
@endif

{{-- ── NAVBAR ── --}}
<nav class="top-nav">
  <div class="nav-inner">
    <a href="#" onclick="goBack(event)" class="nav-back">
      <i class="bi bi-arrow-left"></i>
      <span>Back</span>
    </a>

    <div class="nav-shop-name">{{ $shop->shop_name }}</div>

    <a href="{{ route('catalog') }}" class="btn-order-now">
      <i class="bi bi-bag-plus"></i>
      <span class="d-none d-sm-inline">Order Now</span>
    </a>
  </div>
</nav>

{{-- ── COVER PHOTO ── --}}
<div class="cover-wrap">
  @if($shop->shop_cover)
    <img src="{{ $shop->shop_cover }}" alt="{{ $shop->shop_name }}">
  @endif
  <div class="cover-overlay"></div>
</div>

{{-- ── SHOP HEADER ── --}}
<div class="shop-header">
  <div class="shop-header-inner">

    <div class="shop-logo-wrap">
      @if($shop->shop_logo)
        <img src="{{ $shop->shop_logo }}" class="shop-logo" alt="{{ $shop->shop_name }}">
      @else
        <div class="shop-logo-placeholder">{{ strtoupper(substr($shop->shop_name,0,1)) }}</div>
      @endif
    </div>

    <div style="margin-bottom:1rem">
      <div style="display:flex;align-items:center;flex-wrap:wrap;gap:.5rem;margin-bottom:.4rem">
        <h1 class="shop-name">{{ $shop->shop_name }}</h1>
        @if($shop->tier === 'verified')
          <span class="badge-verified"><i class="bi bi-patch-check-fill"></i> Verified</span>
        @else
          <span class="badge-new"><i class="bi bi-stars"></i> New</span>
        @endif
      </div>

      @if($shop->description)
        <p style="font-size:.875rem;color:var(--gray-600);line-height:1.65;margin:.4rem 0 0;max-width:600px">{{ $shop->description }}</p>
      @endif

      <div class="shop-meta mt-2">
        @if($shop->city)
        <span><i class="bi bi-geo-alt-fill"></i>{{ $shop->city }}</span>
        @endif
        @if($shop->contact_number)
        <span><i class="bi bi-telephone-fill"></i>{{ $shop->contact_number }}</span>
        @endif
        @php $r = round($avgRating ?? 0, 1); @endphp
        <span>
          <span class="stars" style="font-size:.78rem">
            @for($i=1;$i<=5;$i++)<i class="bi bi-star{{ $i<=$r?'-fill':($i-0.5<=$r?'-half':'') }}"></i>@endfor
          </span>
          <strong style="color:var(--gray-800)">{{ $r>0?number_format($r,1):'New' }}</strong>
          @if($reviewCount>0)<span style="color:var(--gray-400)">({{ $reviewCount }})</span>@endif
        </span>
        <span><i class="bi bi-grid-3x3-gap-fill"></i>{{ $products->count() }} product{{ $products->count()!=1?'s':'' }}</span>
      </div>
    </div>

    <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin:.85rem 0 1rem">
      @if(session('user'))
        <a href="{{ route('customer.custom_order') }}?shop={{ $shop->shop_slug }}" class="btn-custom-cake">
          <i class="bi bi-palette-fill"></i> Order Custom Cake
        </a>
      @else
        <a href="{{ route('guest.custom_order') }}?shop={{ $shop->shop_slug }}" class="btn-custom-cake">
          <i class="bi bi-palette-fill"></i> Order Custom Cake
        </a>
      @endif
    </div>

    <div class="tab-bar">
      <button class="tab-btn active" onclick="switchTab('products',this)">
        <i class="bi bi-grid-3x3-gap me-1"></i>Products
        @if($products->count()>0)<span style="background:var(--primary);color:#fff;font-size:.68rem;padding:.1rem .42rem;border-radius:99px;margin-left:.3rem">{{ $products->count() }}</span>@endif
      </button>
      <button class="tab-btn" onclick="switchTab('reviews',this)">
        <i class="bi bi-star me-1"></i>Reviews
        @if($reviewCount>0)<span style="background:var(--primary);color:#fff;font-size:.68rem;padding:.1rem .42rem;border-radius:99px;margin-left:.3rem">{{ $reviewCount }}</span>@endif
      </button>
      <button class="tab-btn" onclick="switchTab('info',this)">
        <i class="bi bi-info-circle me-1"></i>Shop Info
      </button>
    </div>
  </div>
</div>

{{-- ── PRODUCTS ── --}}
<div id="tab-products" class="tab-pane active">
  @if($products->count() > 0)
    @php
      $classes = $products->pluck('classification')->unique()->filter()->values();
      $classBadge = [
        'Standard'   => ['bg'=>'#dbeafe','color'=>'#1e40af'],
        'Fondant'    => ['bg'=>'#fce7f3','color'=>'#9d174d'],
        'Perishable' => ['bg'=>'#d1fae5','color'=>'#065f46'],
      ];
    @endphp
    @if($classes->count() > 1)
    <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1.5rem">
      <button class="filter-chip active" onclick="filterProds('all',this)">All</button>
      @foreach($classes as $c)
      <button class="filter-chip" onclick="filterProds('{{ $c }}',this)">{{ $c }}</button>
      @endforeach
    </div>
    @endif

    <div class="row g-3" id="prodsGrid">
      @foreach($products as $p)
      @php $cb = $classBadge[$p->classification] ?? ['bg'=>'#dbeafe','color'=>'#1e40af']; @endphp
      <div class="col-6 col-md-4 col-lg-3 prod-item" data-class="{{ $p->classification }}">
        <div class="product-card">
          <div class="product-img-wrap">
            @if($p->image_path)
              <img src="{{ $p->image_path }}" alt="{{ $p->name }}"
                   onerror="this.parentElement.querySelector('.product-img-ph').style.display='flex';this.style.display='none'">
            @endif
            <div class="product-img-ph" style="display:{{ $p->image_path ? 'none' : 'flex' }}">
              <i class="bi bi-cake2" style="font-size:2.5rem;color:var(--primary);opacity:.35"></i>
            </div>
            <span class="class-badge" style="background:{{ $cb['bg'] }};color:{{ $cb['color'] }}">{{ $p->classification }}</span>
          </div>
          <div class="product-body">
            <div class="product-name">{{ $p->name }}</div>
            @if($p->flavor)
              <span class="flavor-tag"><i class="bi bi-droplet me-1" style="font-size:.62rem"></i>{{ $p->flavor }}</span>
            @endif
            @if($p->description)
              <p class="product-desc">{{ $p->description }}</p>
            @endif
            <div class="product-price">₱{{ number_format($p->price,2) }}</div>
            <button class="btn-order" data-bs-toggle="modal" data-bs-target="#shopOrderModal{{ $p->id }}">
              <i class="bi bi-bag-plus me-1"></i>Order Now
            </button>
          </div>
        </div>
      </div>
      @endforeach
    </div>

    {{-- ── ORDER MODALS (full catalog-style) ── --}}
    @foreach($products as $p)
    @php
      $sizes       = $productSizes[$p->id] ?? collect();
      $pRating     = $productRatings[$p->id] ?? null;
      $pReviews    = $productReviews[$p->id] ?? collect();
      $pAvg        = $pRating ? round((float)$pRating->avg_rating, 1) : 0;
      $pRevCount   = $pRating ? (int)$pRating->review_count : 0;
      $classBadge  = [
        'Standard'   => ['bg'=>'#dbeafe','color'=>'#1e40af','icon'=>'bi-cake2'],
        'Fondant'    => ['bg'=>'#fce7f3','color'=>'#9d174d','icon'=>'bi-stars'],
        'Perishable' => ['bg'=>'#d1fae5','color'=>'#065f46','icon'=>'bi-snow'],
      ];
      $cls = $classBadge[$p->classification] ?? $classBadge['Standard'];
    @endphp
    <div class="modal fade" id="shopOrderModal{{ $p->id }}" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content border-0" style="border-radius:1.4rem;overflow:hidden">

          {{-- Sticky Header --}}
          <div class="modal-header border-0 pb-0 px-4 pt-3" style="background:#fff;position:sticky;top:0;z-index:10">
            <div class="d-flex align-items-center gap-2 flex-wrap flex-grow-1">
              <span class="badge" style="background:{{ $cls['bg'] }};color:{{ $cls['color'] }};font-size:.72rem">
                <i class="bi {{ $cls['icon'] }} me-1"></i>{{ $p->classification }}
              </span>
              <span class="badge bg-success" style="font-size:.72rem"><i class="bi bi-check-circle me-1"></i>Available</span>
              <span class="fw-bold ms-1" style="font-size:.95rem">{{ $p->name }}</span>
            </div>
            <button type="button" class="btn-close ms-2" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body p-0">

            {{-- Product Image --}}
            <div style="position:relative;background:#fafafa">
              <img src="{{ $p->image_path }}" alt="{{ $p->name }}"
                   style="width:100%;max-height:260px;object-fit:cover;display:block;cursor:zoom-in"
                   onerror="this.src='https://placehold.co/800x260/fce4ec/e91e63?text=🎂'"
                   onclick="catLbOpen('{{ $p->image_path }}')">
              <div style="position:absolute;bottom:10px;right:12px;background:rgba(0,0,0,.45);color:#fff;border-radius:20px;padding:3px 10px;font-size:.72rem;pointer-events:none">
                <i class="bi bi-zoom-in me-1"></i>Tap to zoom
              </div>
            </div>

            <div class="px-4 pt-3 pb-4">

              {{-- Name + Rating --}}
              <div class="mb-2">
                <h4 class="fw-bold mb-1">{{ $p->name }}</h4>
                @if($pAvg > 0)
                <div class="d-flex align-items-center gap-2 mb-1">
                  <div class="d-flex gap-1">
                    @for($i=1;$i<=5;$i++)
                      <i class="bi bi-star{{ $i <= round($pAvg) ? '-fill' : '' }}" style="color:#fbbf24;font-size:.85rem"></i>
                    @endfor
                  </div>
                  <span class="fw-bold small" style="color:#fbbf24">{{ number_format($pAvg,1) }}</span>
                  <span class="text-muted small">({{ $pRevCount }} review{{ $pRevCount!=1?'s':'' }})</span>
                </div>
                @endif
              </div>

              <hr class="my-2">

              {{-- Details Grid --}}
              <div class="row g-2 mb-3">
                <div class="col-6">
                  <div class="p-2 rounded-2" style="background:#f8f9fa">
                    <div class="text-muted" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em">Price</div>
                    <div class="fw-bold" style="color:var(--primary);font-size:1.15rem">₱{{ number_format($p->price,2) }}</div>
                    @if($sizes->count() > 0)
                      <div class="text-muted" style="font-size:.68rem">Base price</div>
                    @endif
                  </div>
                </div>
                <div class="col-6">
                  <div class="p-2 rounded-2" style="background:#f8f9fa">
                    <div class="text-muted" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em">Type</div>
                    <div class="fw-semibold small" style="color:{{ $cls['color'] }}">
                      <i class="bi {{ $cls['icon'] }} me-1"></i>{{ $p->classification }}
                    </div>
                  </div>
                </div>
                @if($p->flavor)
                <div class="col-12">
                  <div class="p-2 rounded-2" style="background:#f8f9fa">
                    <div class="text-muted" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em">Flavor</div>
                    <div class="fw-semibold small"><i class="bi bi-droplet me-1" style="color:var(--primary)"></i>{{ $p->flavor }}</div>
                  </div>
                </div>
                @endif
                @if($p->description)
                <div class="col-12">
                  <div class="p-2 rounded-2" style="background:#f8f9fa">
                    <div class="text-muted" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em">Description</div>
                    <div class="small text-muted mt-1">{{ $p->description }}</div>
                  </div>
                </div>
                @endif
              </div>

              {{-- Sizes --}}
              @if($sizes->count() > 0)
              <div class="mb-3">
                <div class="fw-semibold small mb-2"><i class="bi bi-rulers me-1" style="color:var(--primary)"></i>Available Sizes</div>
                <div class="d-flex flex-wrap gap-2">
                  @foreach($sizes as $sz)
                  <div class="px-3 py-1 rounded-pill border" style="border-color:var(--primary)!important;font-size:.78rem">
                    <span class="fw-semibold">{{ $sz->label }}</span>
                    <span class="text-muted ms-1">— ₱{{ number_format($sz->price,2) }}</span>
                  </div>
                  @endforeach
                </div>
              </div>
              @endif

              {{-- Shop Info --}}
              <a href="{{ route('platform.shop', $shop->shop_slug) }}"
                 class="d-flex align-items-center gap-2 mb-3 p-2 rounded-2 text-decoration-none"
                 style="background:#fff0f6;border:1px solid #fce7f3">
                @if($shop->shop_logo)
                  <img src="{{ $shop->shop_logo }}" style="width:32px;height:32px;border-radius:8px;object-fit:cover;flex-shrink:0">
                @else
                  <div style="width:32px;height:32px;border-radius:8px;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="bi bi-shop-window text-white" style="font-size:.8rem"></i>
                  </div>
                @endif
                <div class="flex-grow-1">
                  <div class="fw-semibold" style="font-size:.82rem;color:#9d174d">{{ $shop->shop_name }}</div>
                  <div class="text-muted" style="font-size:.7rem">Tap to view shop &rarr;</div>
                </div>
                <i class="bi bi-chevron-right" style="color:#d1d5db;font-size:.75rem"></i>
              </a>

              <hr class="my-3">

              {{-- Order Form --}}
              @if(session('user'))
                <form action="{{ route('customer.catalog.order') }}" method="POST" onsubmit="return confirmOrder(this)">
              @else
                <form action="{{ route('catalog.select') }}" method="POST" onsubmit="return confirmOrder(this)">
              @endif
                @csrf
                <input type="hidden" name="product_id" value="{{ $p->id }}">

                {{-- Size Selection --}}
                @if($sizes->count() > 0)
                <div class="mb-3">
                  <label class="form-label fw-semibold small">Select Size <span class="text-danger">*</span></label>
                  <select class="form-select" name="selected_size"
                          onchange="updateModalPrice('{{ $p->id }}', {{ $p->price }}, this)" required>
                    <option value="">-- Choose a size --</option>
                    @foreach($sizes as $sz)
                      <option value="{{ $sz->label }}" data-price="{{ $sz->price }}">
                        {{ $sz->label }} — ₱{{ number_format($sz->price,2) }}
                      </option>
                    @endforeach
                  </select>
                  <div class="mt-2 p-2 rounded-2 d-flex align-items-center justify-content-between" style="background:#fff0f5">
                    <span class="small text-muted">Total Price:</span>
                    <span class="fw-bold" style="color:var(--primary);font-size:1.05rem" id="modalPrice{{ $p->id }}">
                      ₱{{ number_format($p->price,2) }}
                    </span>
                  </div>
                </div>
                @endif

                {{-- Quantity --}}
                <div class="mb-3">
                  <label class="form-label fw-semibold small">Quantity</label>
                  <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3"
                            onclick="changeQty('{{ $p->id }}', -1)">−</button>
                    <input type="number" class="form-control text-center fw-bold"
                           name="quantity" id="qty{{ $p->id }}"
                           min="1" max="20" value="1" required style="width:70px">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3"
                            onclick="changeQty('{{ $p->id }}', 1)">+</button>
                  </div>
                </div>

                <div class="alert border-0 py-2 small mb-3" style="background:#fff0f5;border-radius:.7rem">
                  <i class="bi bi-info-circle me-1" style="color:var(--primary)"></i>
                  You'll choose pickup/delivery on the next step.
                </div>

                {{-- Date Availability --}}
                <div class="mb-3 p-3 rounded-3" style="background:#fff0f5;border:1px solid #fce7f3">
                  <label class="form-label fw-semibold small mb-1">
                    <i class="bi bi-calendar3 me-1" style="color:var(--primary)"></i>Check Date Availability
                  </label>
                  <input type="date" class="form-control form-control-sm"
                         id="availDate{{ $p->id }}"
                         min="{{ date('Y-m-d') }}"
                         data-product-id="{{ $p->id }}"
                         data-shop-id="{{ $shop->id }}"
                         onchange="checkModalAvailability('{{ $p->id }}')">
                  <div class="form-text"><i class="bi bi-info-circle me-1"></i>You can order for today or any future date.</div>
                  <div id="availResult{{ $p->id }}" class="mt-2" style="font-size:.82rem;min-height:20px"></div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                  <i class="bi bi-arrow-right-circle me-1"></i>Proceed to Checkout
                </button>
              </form>

              {{-- Reviews --}}
              <div class="mt-4">
                <div class="fw-bold mb-3" style="border-bottom:2px solid var(--primary);padding-bottom:.5rem">
                  <i class="bi bi-star-fill me-1" style="color:#fbbf24"></i>
                  Customer Reviews
                  @if($pRevCount > 0)
                    <span class="text-muted fw-normal small ms-1">({{ $pRevCount }})</span>
                  @endif
                </div>
                @if($pReviews->count() > 0)
                  <div>
                    @foreach($pReviews->take(5) as $rv)
                    <div class="d-flex gap-3 mb-3 pb-3" style="border-bottom:1px solid #f0f0f0">
                      <div style="flex-shrink:0">
                        @if($rv->profile_photo)
                          <img src="{{ $rv->profile_photo }}" style="width:38px;height:38px;border-radius:50%;object-fit:cover">
                        @else
                          <div style="width:38px;height:38px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.9rem">
                            {{ strtoupper(substr($rv->fullname,0,1)) }}
                          </div>
                        @endif
                      </div>
                      <div class="flex-grow-1">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-1">
                          <span class="fw-semibold small">{{ $rv->fullname }}</span>
                          <span class="text-muted" style="font-size:.72rem">{{ \Carbon\Carbon::parse($rv->created_at)->diffForHumans() }}</span>
                        </div>
                        <div class="d-flex gap-1 my-1">
                          @for($i=1;$i<=5;$i++)
                            <i class="bi bi-star{{ $i<=$rv->rating?'-fill':'' }}" style="color:#fbbf24;font-size:.78rem"></i>
                          @endfor
                        </div>
                        @if($rv->review)
                          <p class="small mb-0 text-muted">{{ $rv->review }}</p>
                        @endif
                      </div>
                    </div>
                    @endforeach
                  </div>
                @else
                  <div class="text-center py-4 text-muted">
                    <i class="bi bi-chat-square-text" style="font-size:2rem;opacity:.3"></i>
                    <p class="small mt-2">No reviews yet. Be the first to review!</p>
                  </div>
                @endif
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>
    @endforeach

  @else
    <div class="empty-state">
      <i class="bi bi-cake2" style="font-size:3rem;color:var(--gray-300);display:block;margin-bottom:1rem"></i>
      <h3 style="font-size:1rem;font-weight:700;margin:0 0 .4rem">No products yet</h3>
      <p style="font-size:.875rem;color:var(--gray-500);margin:0 0 1.5rem">This shop hasn't listed any products yet.</p>
      <a href="{{ route('catalog') }}" class="btn-order-now">
        <i class="bi bi-shop"></i> Browse Catalog
      </a>
    </div>
  @endif
</div>

{{-- ── REVIEWS ── --}}
<div id="tab-reviews" class="tab-pane">
  @if($reviews->count() > 0)
    <div style="background:#fff;border-radius:var(--radius-lg);padding:1.25rem 1.5rem;border:1.5px solid var(--gray-200);margin-bottom:1.5rem;display:inline-flex;align-items:center;gap:1.5rem">
      <div style="text-align:center">
        <div style="font-family:'Playfair Display',serif;font-size:3rem;font-weight:700;color:var(--primary);line-height:1">{{ number_format($avgRating,1) }}</div>
        <div class="stars" style="font-size:1rem;margin:.25rem 0">
          @for($i=1;$i<=5;$i++)<i class="bi bi-star{{ $i<=$r?'-fill':'' }}"></i>@endfor
        </div>
        <div style="font-size:.78rem;color:var(--gray-500)">{{ $reviewCount }} review{{ $reviewCount!=1?'s':'' }}</div>
      </div>
    </div>

    @foreach($reviews as $rev)
    <div class="review-card">
      <div style="display:flex;align-items:flex-start;gap:.875rem">
        @if($rev->profile_photo)
          <img src="{{ $rev->profile_photo }}" style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0">
        @else
          <div class="reviewer-avatar">{{ strtoupper(substr($rev->reviewer_name,0,1)) }}</div>
        @endif
        <div style="flex:1;min-width:0">
          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.4rem">
            <span style="font-size:.875rem;font-weight:700">{{ $rev->reviewer_name }}</span>
            <span style="font-size:.75rem;color:var(--gray-400)">{{ \Carbon\Carbon::parse($rev->created_at)->diffForHumans() }}</span>
          </div>
          <div class="stars" style="font-size:.8rem;margin:.2rem 0">
            @for($i=1;$i<=5;$i++)<i class="bi bi-star{{ $i<=$rev->rating?'-fill':'' }}"></i>@endfor
          </div>
          @if($rev->review)
            <p style="font-size:.875rem;color:var(--gray-700);margin:.4rem 0 0;line-height:1.6">{{ $rev->review }}</p>
          @endif
        </div>
      </div>
    </div>
    @endforeach
  @else
    <div class="empty-state">
      <i class="bi bi-star" style="font-size:2.5rem;color:var(--gray-300);display:block;margin-bottom:1rem"></i>
      <h3 style="font-size:1rem;font-weight:700;margin:0 0 .4rem">No reviews yet</h3>
      <p style="font-size:.875rem;color:var(--gray-500);margin:0">Be the first to order and leave a review!</p>
    </div>
  @endif
</div>

{{-- ── SHOP INFO ── --}}
<div id="tab-info" class="tab-pane">
  <div style="background:#fff;border-radius:var(--radius-lg);border:1.5px solid var(--gray-200);overflow:hidden;max-width:520px;margin-bottom:1.5rem">
    @foreach([
      ['bi-shop-window','Shop Name', $shop->shop_name],
      ['bi-geo-alt','City / Municipality', $shop->city],
      ['bi-map','Full Address', $shop->address],
      ['bi-telephone','Contact Number', $shop->contact_number],
      ['bi-award','Seller Tier', ucfirst($shop->tier).' Seller'],
    ] as [$icon,$label,$value])
      @if($value)
      <div class="info-row">
        <div class="info-icon"><i class="bi {{ $icon }}" style="color:var(--primary)"></i></div>
        <div>
          <div style="font-size:.7rem;color:var(--gray-500);margin-bottom:.15rem;text-transform:uppercase;letter-spacing:.04em">{{ $label }}</div>
          <div style="font-size:.9rem;font-weight:600;color:var(--gray-900)">{{ $value }}</div>
        </div>
      </div>
      @endif
    @endforeach
  </div>

  <a href="{{ route('catalog') }}" class="btn-order-now">
    <i class="bi bi-bag-plus"></i> Order from this Shop
  </a>
</div>

{{-- Lightbox --}}
<div id="lightboxOverlay"
     style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;z-index:9999;background:rgba(0,0,0,0);align-items:center;justify-content:center;transition:background .3s ease"
     onclick="catLbBgClick(event)">
  <div id="lightboxWrapper"
       style="position:relative;transform:scale(0.3);opacity:0;transition:transform .4s cubic-bezier(.34,1.56,.64,1),opacity .3s ease">
    <img id="lightboxImg" src=""
         style="max-width:90vw;max-height:82vh;border-radius:1rem;object-fit:contain;display:block;cursor:default;user-select:none"
         onclick="event.stopPropagation()">
    <div style="position:absolute;bottom:-56px;left:50%;transform:translateX(-50%);display:flex;gap:10px;align-items:center">
      <button onclick="event.stopPropagation();catLbZoom(-0.25)" style="background:rgba(255,255,255,.18);border:none;color:#fff;width:40px;height:40px;border-radius:50%;font-size:1.3rem;cursor:pointer">−</button>
      <span id="zoomLabel" style="color:#fff;font-size:.82rem;min-width:48px;text-align:center">100%</span>
      <button onclick="event.stopPropagation();catLbZoom(0.25)" style="background:rgba(255,255,255,.18);border:none;color:#fff;width:40px;height:40px;border-radius:50%;font-size:1.3rem;cursor:pointer">+</button>
      <button onclick="event.stopPropagation();catLbReset()" style="background:rgba(255,255,255,.18);border:none;color:#fff;padding:0 14px;height:40px;border-radius:20px;font-size:.78rem;cursor:pointer">Reset</button>
    </div>
  </div>
  <button id="lbCloseBtn"
          style="position:fixed;top:20px;right:24px;background:rgba(255,255,255,.18);border:none;color:#fff;width:44px;height:44px;border-radius:50%;font-size:1.2rem;cursor:pointer;opacity:0;transition:opacity .3s ease .15s"
          onclick="catLbClose()">
    <i class="bi bi-x-lg"></i>
  </button>
  <div style="position:fixed;bottom:20px;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.45);font-size:.75rem;opacity:0;transition:opacity .3s ease .2s" id="lbHint">
    Scroll to zoom &nbsp;·&nbsp; Click outside to close &nbsp;·&nbsp; ESC to exit
  </div>
</div>

<div style="height:3rem"></div>

<script>
function confirmOrder(form) {
  cakeConfirm({ title:'Proceed to Checkout?', message:'You will be redirected to the checkout page.', icon:'bi-cart-check', iconBg:'#dbeafe', iconColor:'#2563eb', okLabel:'Proceed', okColor:'#2563eb', onConfirm:() => form.submit() });
  return false;
}
function updateModalPrice(productId, basePrice, select) {
  const opt   = select.options[select.selectedIndex];
  const price = opt.dataset.price ? parseFloat(opt.dataset.price) : basePrice;
  const el    = document.getElementById('modalPrice' + productId);
  if (el) el.textContent = '₱' + price.toLocaleString('en-PH', {minimumFractionDigits:2});
}
function changeQty(productId, delta) {
  const input = document.getElementById('qty' + productId);
  if (!input) return;
  let val = parseInt(input.value) + delta;
  if (val < 1) val = 1;
  if (val > 20) val = 20;
  input.value = val;
}
function checkModalAvailability(productId) {
  const input    = document.getElementById('availDate' + productId);
  const date     = input?.value;
  const shopId   = input?.dataset?.shopId || '';
  const resultEl = document.getElementById('availResult' + productId);
  if (!date || !resultEl) return;
  resultEl.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split me-1"></i>Checking...</span>';
  fetch('/catalog/availability?date=' + date + (shopId ? '&shop_id=' + shopId : ''))
    .then(r => r.json())
    .then(data => {
      if (data.status === 'available')
        resultEl.innerHTML = '<span class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>' + data.message + '</span>';
      else if (data.status === 'almost')
        resultEl.innerHTML = '<span class="text-warning fw-semibold"><i class="bi bi-exclamation-triangle-fill me-1"></i>' + data.message + '</span>';
      else if (data.status === 'full')
        resultEl.innerHTML = '<span class="text-danger fw-semibold"><i class="bi bi-x-circle-fill me-1"></i>' + data.message + '</span>';
      else
        resultEl.innerHTML = '<span class="text-muted small">Could not check availability.</span>';
    })
    .catch(() => { resultEl.innerHTML = '<span class="text-muted small">Could not check.</span>'; });
}
let catLbScale = 1;
function catLbOpen(src) {
  const overlay=document.getElementById('lightboxOverlay'),wrapper=document.getElementById('lightboxWrapper'),
        img=document.getElementById('lightboxImg'),closeBtn=document.getElementById('lbCloseBtn'),hint=document.getElementById('lbHint');
  catLbScale=1; document.getElementById('zoomLabel').textContent='100%'; img.style.transform='scale(1)'; img.src=src;
  overlay.style.display='flex'; document.body.style.overflow='hidden';
  requestAnimationFrame(()=>{ overlay.style.background='rgba(0,0,0,.92)'; wrapper.style.transform='scale(1)'; wrapper.style.opacity='1'; closeBtn.style.opacity='1'; hint.style.opacity='1'; });
}
function catLbClose() {
  const overlay=document.getElementById('lightboxOverlay'),wrapper=document.getElementById('lightboxWrapper'),
        closeBtn=document.getElementById('lbCloseBtn'),hint=document.getElementById('lbHint');
  overlay.style.background='rgba(0,0,0,0)'; wrapper.style.transform='scale(0.3)'; wrapper.style.opacity='0'; closeBtn.style.opacity='0'; hint.style.opacity='0';
  setTimeout(()=>{ overlay.style.display='none'; document.body.style.overflow=''; },380);
}
function catLbBgClick(e) { if(e.target===document.getElementById('lightboxOverlay')) catLbClose(); }
function catLbZoom(delta) { catLbScale=Math.min(3,Math.max(0.5,catLbScale+delta)); document.getElementById('lightboxImg').style.transform='scale('+catLbScale+')'; document.getElementById('zoomLabel').textContent=Math.round(catLbScale*100)+'%'; }
function catLbReset() { catLbScale=1; document.getElementById('lightboxImg').style.transform='scale(1)'; document.getElementById('zoomLabel').textContent='100%'; }
document.getElementById('lightboxOverlay').addEventListener('wheel',e=>{ if(document.getElementById('lightboxOverlay').style.display==='flex'){e.preventDefault();catLbZoom(e.deltaY<0?0.15:-0.15);} },{passive:false});
document.addEventListener('keydown',e=>{ if(e.key==='Escape')catLbClose(); if(e.key==='+'||e.key==='=')catLbZoom(0.25); if(e.key==='-')catLbZoom(-0.25); });
function goBack(e) {
  e.preventDefault();
  if (window.history.length > 1) { history.back(); }
  else { window.location.href = '{{ route("catalog") }}'; }
}
function switchTab(name, btn) {
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  btn.classList.add('active');
}
function filterProds(cls, btn) {
  document.querySelectorAll('.filter-chip').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.prod-item').forEach(el => {
    el.style.display = (cls === 'all' || el.dataset.class === cls) ? '' : 'none';
  });
}
</script>
<footer style="background:#1a1a1a;color:#9ca3af;padding:2rem 0;margin-top:3rem;text-align:center">
  <div style="max-width:1200px;margin:0 auto;padding:0 1rem">
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
