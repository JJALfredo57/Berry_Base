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
    $hasShopCover = !empty($shop->shop_cover);
    $sbgType   = $hasShopCover ? 'image' : ($shopSettings->bg_type ?? 'color');
    $sbgColor  = $shopSettings->bg_color ?? '#f9f9f9';
    $sbgGradS  = $shopSettings->gradient_start ?? '#fff7fb';
    $sbgGradE  = $shopSettings->gradient_end   ?? '#ffe3f1';
    $sbgImg    = $hasShopCover ? $shop->shop_cover : ($shopSettings->bg_image_path ?? '');
    $sbgOpacity= $hasShopCover ? 1.0 : (float)($shopSettings->bg_image_opacity ?? 1.0);
    if ($hasShopCover) {
        $bodyBg = "background:transparent;";
    } elseif ($sbgType === 'gradient') {
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
    html,body{overflow-x:hidden}
    body{font-family:'DM Sans',system-ui,sans-serif;{{ $bodyBg }}color:var(--gray-900);margin:0;-webkit-font-smoothing:antialiased;font-size:16px}
    body.has-shop-bg-image::before{
      content:"";
      position:fixed;
      inset:0;
      z-index:0;
      pointer-events:none;
      background:var(--shop-bg-image) center/cover no-repeat;
      opacity:var(--shop-bg-opacity);
    }
    body.has-shop-bg-image::after{
      content:"";
      position:fixed;
      inset:0;
      z-index:0;
      pointer-events:none;
      backdrop-filter:blur(9px);
      -webkit-backdrop-filter:blur(9px);
      background:transparent;
    }
    body.has-shop-bg-image > *{position:relative;z-index:1}
    img{max-width:100%}
    a{text-decoration:none;color:inherit}

    /* ── Navbar ── */
    .top-nav{
      position:sticky;top:0;z-index:200;
      background:rgba(255,255,255,.96);
      backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
      border-bottom:1px solid rgba(0,0,0,.07);
      padding:.6rem 0;
    }
    .nav-inner{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:0 clamp(.9rem,4vw,2rem)}
    .nav-back{display:flex;align-items:center;gap:.4rem;font-size:.9rem;font-weight:600;color:var(--gray-600);transition:color .15s;border:1.5px solid var(--gray-300);padding:.4rem .85rem;border-radius:var(--radius-md);min-height:40px;flex-shrink:0}
    .nav-back:hover{color:var(--primary);border-color:var(--primary)}
    .nav-shop-name{font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;color:var(--gray-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1;min-width:0;padding:0 .5rem}
    .btn-order-now{font-size:.88rem;font-weight:700;color:#fff;background:var(--primary);border:none;border-radius:var(--radius-md);padding:.45rem 1.1rem;transition:all .15s;box-shadow:0 2px 8px rgba(229,57,53,.25);display:inline-flex;align-items:center;gap:.4rem;min-height:40px;flex-shrink:0}
    .btn-order-now:hover{background:var(--primary-dark);transform:translateY(-1px);color:#fff}
    .btn-custom-cake{font-size:.88rem;font-weight:700;color:var(--primary);background:#fff;border:2px solid var(--primary);border-radius:var(--radius-md);padding:.45rem 1.1rem;transition:all .15s;display:inline-flex;align-items:center;gap:.4rem;cursor:pointer;min-height:40px}
    .btn-custom-cake:hover{background:var(--primary);color:#fff;transform:translateY(-1px)}

    /* ── Cover ── */
    .cover-wrap{height:260px;position:relative;overflow:hidden;background:
      radial-gradient(circle at 18% 24%,rgba(255,255,255,.26),transparent 28%),
      linear-gradient(135deg,var(--primary) 0%,var(--primary-light) 60%,var(--primary-bg) 100%)}
    .cover-wrap img{width:100%;height:100%;object-fit:cover}
    .cover-overlay{position:absolute;inset:0;background:linear-gradient(to bottom,rgba(0,0,0,.02) 0%,rgba(0,0,0,.44) 100%)}
    .cover-placeholder{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.9);font-family:'Playfair Display',serif;font-size:clamp(1.7rem,6vw,3rem);font-weight:700;text-align:center;padding:1rem}
    @media(max-width:576px){.cover-wrap{height:170px}}

    /* ── Shop Header ── */
    .shop-header{background:#fff;border-bottom:1px solid var(--gray-200)}
    .shop-header-inner{padding:0 clamp(.9rem,4vw,2rem)}
    .shop-logo-wrap{margin-top:-46px;margin-bottom:.75rem;position:relative;display:inline-block}
    .shop-logo{
      width:88px;height:88px;border-radius:20px;
      border:4px solid #fff;box-shadow:0 4px 20px rgba(0,0,0,.15);
      object-fit:cover;display:block;
    }
    .shop-logo-placeholder{
      width:88px;height:88px;border-radius:20px;
      border:4px solid #fff;box-shadow:0 4px 20px rgba(0,0,0,.15);
      background:var(--primary);display:flex;align-items:center;justify-content:center;
      font-family:'Playfair Display',serif;font-size:2.2rem;color:#fff;font-weight:700;
    }
    .shop-name{font-family:'Playfair Display',serif;font-size:clamp(1.3rem,4vw,1.85rem);font-weight:700;color:var(--gray-900);margin:0}
    .badge-verified{display:inline-flex;align-items:center;gap:.3rem;background:#FFF3E0;color:#E65100;font-size:.8rem;font-weight:700;padding:.25rem .7rem;border-radius:99px}
    .badge-new{display:inline-flex;align-items:center;gap:.3rem;background:#E8F5E9;color:#2E7D32;font-size:.8rem;font-weight:700;padding:.25rem .7rem;border-radius:99px}
    .shop-meta{display:flex;flex-wrap:wrap;gap:.6rem 1rem;font-size:.875rem;color:var(--gray-500);margin-top:.5rem}
    .shop-meta span{display:flex;align-items:center;gap:.3rem}
    .shop-meta i{color:var(--primary)}
    .stars{color:#FFC107}

    /* ── Tabs ── */
    .tab-bar{display:flex;gap:0;border-bottom:2px solid var(--gray-200);margin-top:1.25rem;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none}
    .tab-bar::-webkit-scrollbar{display:none}
    .tab-btn{padding:.7rem 1.25rem;font-size:.9rem;font-weight:600;color:var(--gray-500);border:none;background:transparent;cursor:pointer;border-bottom:2.5px solid transparent;margin-bottom:-2px;transition:all .15s;white-space:nowrap;min-height:44px}
    .tab-btn.active{color:var(--primary);border-bottom-color:var(--primary)}
    .tab-pane{display:none;padding:1.5rem clamp(.9rem,4vw,2rem)}
    .tab-pane.active{display:block}

    /* ── Product Cards ── */
    .product-card{background:#fff;border-radius:var(--radius-lg);overflow:hidden;border:1.5px solid var(--gray-200);transition:all .22s;height:100%;display:flex;flex-direction:column}
    .product-card:hover{border-color:var(--primary-light);box-shadow:0 8px 32px rgba(229,57,53,.13);transform:translateY(-4px)}
    .product-img-wrap{height:190px;overflow:hidden;background:linear-gradient(135deg,var(--primary-bg),var(--primary-light));position:relative}
    .product-img-wrap img{width:100%;height:100%;object-fit:cover;transition:transform .4s ease}
    .product-card:hover .product-img-wrap img{transform:scale(1.07)}
    .product-img-ph{width:100%;height:100%;display:flex;align-items:center;justify-content:center}
    .product-body{padding:1rem;flex:1;display:flex;flex-direction:column}
    .product-name{font-size:1rem;font-weight:700;color:var(--gray-900);margin:0 0 .25rem;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
    .product-desc{font-size:.82rem;color:var(--gray-500);line-height:1.45;margin:.25rem 0;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
    .product-price{font-size:1.1rem;font-weight:800;color:var(--primary);margin-top:.5rem}
    .product-price-old{font-size:.78rem;color:var(--gray-400);text-decoration:line-through;margin-left:.35rem;font-weight:600}
    .product-price-meta{font-size:.78rem;color:var(--gray-500);font-weight:600;margin-top:.05rem}
    .product-card-bottom{display:flex;align-items:flex-end;justify-content:space-between;gap:.65rem;margin-top:auto;padding-top:.4rem}
    .product-stock-note{width:max-content;max-width:100%;font-size:.76rem;font-weight:700;margin:.35rem 0 .15rem;display:inline-flex;align-items:center;gap:.25rem;border-radius:99px;padding:.2rem .55rem;background:#f8fafc;color:#475569;border:1px solid #e2e8f0;white-space:nowrap}
    .product-stock-note.is-ok{background:#ecfdf5;color:#047857;border-color:#a7f3d0}
    .product-stock-note.is-low{background:#fffbeb;color:#92400e;border-color:#fde68a}
    .product-stock-note.is-out{background:#fef2f2;color:#b91c1c;border-color:#fecaca}
    .product-rating-badge{position:absolute;top:.6rem;right:.6rem;z-index:2;background:rgba(0,0,0,.58);color:#fbbf24;font-size:.72rem;font-weight:800;padding:.22rem .55rem;border-radius:99px}
    .product-sold-badge{white-space:nowrap;background:#fff1f2;color:#be123c;font-size:.78rem;font-weight:800;border-radius:99px;padding:.25rem .55rem}
    .deal-detail-pills{display:flex;flex-wrap:wrap;gap:.25rem;margin:.35rem 0 .1rem}
    .deal-detail-pills span{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;border-radius:99px;font-size:.66rem;font-weight:800;padding:.16rem .45rem}
    .shop-see-more-wrap{display:none}
    .shop-see-more-btn{border-radius:999px;padding:.68rem 1.25rem;font-weight:800;box-shadow:0 10px 24px rgba(229,57,53,.14)}
    .deal-badge{position:absolute;left:.6rem;top:2.25rem;z-index:2;background:linear-gradient(135deg,#fb7185,#f97316);color:#fff;font-size:.7rem;font-weight:800;padding:.22rem .6rem;border-radius:99px;box-shadow:0 8px 18px rgba(190,18,60,.18)}
    .size-choice-btn:disabled{opacity:.48;cursor:not-allowed;text-decoration:line-through}
    .flavor-tag{display:inline-block;background:var(--primary-bg);color:var(--primary);font-size:.75rem;padding:.22rem .65rem;border-radius:99px;margin:.35rem 0;font-weight:600}
    .class-badge{position:absolute;top:.6rem;left:.6rem;font-size:.72rem;font-weight:700;padding:.22rem .6rem;border-radius:99px}
    .btn-order{display:block;width:100%;background:var(--primary);color:#fff;padding:.65rem 1rem;border-radius:var(--radius-md);font-size:.9rem;font-weight:700;border:none;cursor:pointer;transition:all .18s;text-align:center;margin-top:.75rem;min-height:44px;display:flex;align-items:center;justify-content:center;gap:.35rem}
    .btn-order:hover{background:var(--primary-dark);transform:translateY(-1px);color:#fff}
    .size-choice-btn{border-color:var(--primary)!important;font-size:.78rem;color:#111827;transition:background .16s ease,color .16s ease,box-shadow .16s ease,transform .16s ease}
    .size-choice-btn:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(229,57,53,.12)}
    .size-choice-btn.is-selected{background:var(--primary)!important;color:#fff!important;border-color:var(--primary)!important;box-shadow:0 8px 20px color-mix(in srgb,var(--primary) 24%,transparent)}
    .size-choice-btn.is-selected .text-muted{color:rgba(255,255,255,.78)!important}
    .size-choice-btn:focus{box-shadow:0 0 0 .16rem color-mix(in srgb,var(--primary) 22%,transparent)}
    .size-view-more-btn{font-size:.78rem;font-weight:700;color:var(--primary);background:#fff;border:1px dashed var(--primary);border-radius:999px;padding:.25rem .75rem}

    /* ── Reviews ── */
    .review-card{background:#fff;border-radius:var(--radius-md);padding:1.1rem 1.25rem;border:1.5px solid var(--gray-200);margin-bottom:.75rem}
    .reviewer-avatar{width:42px;height:42px;border-radius:50%;background:var(--primary-bg);display:flex;align-items:center;justify-content:center;font-size:.95rem;font-weight:700;color:var(--primary);flex-shrink:0}

    /* ── Info rows ── */
    .info-row{display:flex;gap:1rem;padding:1rem 1.25rem;border-bottom:1px solid var(--gray-100);align-items:flex-start}
    .info-icon{width:38px;height:38px;border-radius:10px;background:var(--primary-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0}

    /* ── Filter chips ── */
    .filter-chip{padding:.42rem .95rem;border-radius:99px;border:1.5px solid var(--gray-200);background:#fff;color:var(--gray-700);font-size:.82rem;font-weight:600;cursor:pointer;transition:all .15s;min-height:38px}
    .filter-chip.active,.filter-chip:hover{background:var(--primary);color:#fff;border-color:var(--primary)}

    /* ── Empty ── */
    .empty-state{text-align:center;padding:4rem 1rem}

    /* ── Mobile overrides ── */
    @media(max-width:575.98px){
      .shop-logo{width:74px;height:74px;border-radius:16px}
      .shop-logo-placeholder{width:74px;height:74px;border-radius:16px;font-size:1.8rem}
      .shop-logo-wrap{margin-top:-38px}
      .shop-meta{font-size:.875rem;gap:.5rem .75rem}
      .product-card{border-radius:14px}
      .product-img-wrap{height:128px}
      .product-name{font-size:.92rem;line-height:1.25;margin-bottom:.2rem;min-height:2.3em}
      .product-body{padding:.62rem;gap:.2rem}
      .product-desc,.product-rating-row,.product-body > .deal-detail-pills{display:none!important}
      .flavor-tag{font-size:.68rem;padding:.16rem .45rem;margin:.15rem 0;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
      .product-stock-note{font-size:.66rem;padding:.12rem .4rem;margin:.12rem 0 .05rem;max-width:100%;overflow:hidden;text-overflow:ellipsis}
      .product-price{font-size:.95rem;margin-top:.15rem;line-height:1.15}
      .product-price-old{display:block;margin-left:0;font-size:.68rem}
      .product-price-meta{font-size:.68rem}
      .product-card-bottom{align-items:flex-start;flex-direction:column;gap:.12rem;padding-top:.1rem}
      .product-sold-badge{font-size:.66rem;padding:.16rem .42rem}
      .product-rating-badge{font-size:.66rem;padding:.16rem .42rem;top:.45rem;right:.45rem}
      .class-badge{font-size:.66rem;padding:.16rem .42rem;top:.45rem;left:.45rem;max-width:60%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
      .deal-badge{font-size:.64rem;padding:.16rem .42rem;top:1.95rem;max-width:70%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
      .btn-order{font-size:.78rem;min-height:38px;padding:.5rem .6rem;margin-top:.45rem;border-radius:10px}
      .tab-btn{font-size:.875rem;padding:.65rem .85rem}
      .tab-pane{padding:1.2rem .9rem}
      .filter-chip{font-size:.82rem;padding:.4rem .85rem}
      .deal-detail-pills span{font-size:.62rem}
      .review-card{padding:.9rem 1rem}
      .info-row{padding:.85rem 1rem;gap:.75rem}
      .info-icon{width:34px;height:34px}
      /* Bump all tiny inline sizes inside modal */
      .modal-content .small,.modal-content .form-text{font-size:.875rem!important}
      .modal-body [style*=".68rem"]{font-size:.8rem!important}
      .modal-body [style*=".7rem"]{font-size:.82rem!important}
      .modal-body [style*=".72rem"]{font-size:.82rem!important}
      .modal-body [style*=".78rem"]{font-size:.88rem!important}
      .modal-body [style*=".82rem"]{font-size:.9rem!important}
      .modal-body h4{font-size:1.15rem!important}
    }
  </style>
</head>
<body @if($sbgType === 'image' && !empty($sbgImg)) class="has-shop-bg-image" style="--shop-bg-image:url('{{ $sbgImg }}');--shop-bg-opacity:{{ $sbgOpacity }};" @endif>

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
  @else
    <div class="cover-placeholder">{{ $shop->shop_name }}</div>
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
        <p style="font-size:.9rem;color:var(--gray-600);line-height:1.7;margin:.4rem 0 0;max-width:600px">{{ $shop->description }}</p>
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

    @php
      $viewerRole = session('user')['role'] ?? null;
    @endphp
    @if($shop->tier === 'verified')
    <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin:.85rem 0 1rem">
      @if($viewerRole === 'customer')
        <a href="{{ route('customer.custom_order', ['shop' => $shop->shop_slug]) }}" class="btn-custom-cake">
          <i class="bi bi-palette-fill"></i> Order Custom Cake
        </a>
      @elseif(!$viewerRole)
        <a href="{{ route('guest.custom_order', ['shop' => $shop->shop_slug]) }}" class="btn-custom-cake">
          <i class="bi bi-palette-fill"></i> Order Custom Cake
        </a>
      @else
        <button type="button" class="btn-custom-cake" data-viewer-role="{{ $viewerRole }}" onclick="return confirmCustomCake(event, this)">
          <i class="bi bi-palette-fill"></i> Order Custom Cake
        </button>
      @endif
    </div>
    @endif

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
        'Standard'   => ['bg'=>'#dbeafe','color'=>'#1e40af','icon'=>'bi-cake2'],
        'Fondant'    => ['bg'=>'#fce7f3','color'=>'#9d174d','icon'=>'bi-stars'],
        'Perishable' => ['bg'=>'#d1fae5','color'=>'#065f46','icon'=>'bi-snow'],
      ];
    @endphp

    {{-- ── TOP SELLERS ── --}}
    @if($bestSellers->count() > 0)
    <div style="margin-bottom:2rem">
      <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:1rem">
        <span style="background:linear-gradient(135deg,#ff6b35,#f7c59f);width:32px;height:32px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="bi bi-fire" style="color:#fff;font-size:.95rem"></i>
        </span>
        <div>
          <div style="font-family:'Playfair Display',serif;font-weight:700;font-size:1.1rem;color:var(--gray-900)">Top Sellers</div>
          <div style="font-size:.82rem;color:var(--gray-500)">Most ordered from this shop</div>
        </div>
      </div>
      <div class="row g-3">
        @foreach($bestSellers as $bs)
        @php
          $bsClassification = $bs->classification ?? 'Standard';
          $bsCb = $classBadge[$bsClassification] ?? $classBadge['Standard'];
          $bsSizes = $productSizes[$bs->id] ?? collect();
          $bsPricing = $bs->discount_snapshot ?? null;
          $bsDeal = $bs->active_discount ?? null;
          $bsDealBadge = ($bsDeal && property_exists($bsDeal, 'deal_badge_label')) ? trim((string) ($bsDeal->deal_badge_label ?? '')) : '';
          $bsDealNote = ($bsDeal && property_exists($bsDeal, 'deal_note')) ? trim((string) ($bsDeal->deal_note ?? '')) : '';
          $bsDealQty = ($bsDeal && property_exists($bsDeal, 'deal_quantity_limit')) ? (int) ($bsDeal->deal_quantity_limit ?? 0) : 0;
          $bsBestEnjoyedBy = ($bsDeal && property_exists($bsDeal, 'best_enjoyed_by') && !empty($bsDeal->best_enjoyed_by)) ? \Carbon\Carbon::parse($bsDeal->best_enjoyed_by) : null;
          $bsStockTracked = property_exists($bs, 'available_quantity') && $bs->available_quantity !== null;
          $bsStockQty = $bsStockTracked ? max(0, (int) $bs->available_quantity) : null;
          $bsHasSizes = $bsSizes->count() > 0;
          $bsHasStock = $bsHasSizes
            ? collect($bsSizes)->contains(fn($sz) => !property_exists($sz, 'available_quantity') || $sz->available_quantity === null || (int) $sz->available_quantity > 0)
            : (!$bsStockTracked || $bsStockQty > 0);
          $bsRating = $productRatings[$bs->id] ?? null;
          $bsAvg = $bsRating ? (float) $bsRating->avg_rating : 0;
          $bsReviewCount = $bsRating ? (int) $bsRating->review_count : 0;
          if ($bsHasSizes) {
            $bsStockClass = '';
            $bsStockIcon = 'bi-rulers';
            $bsStockLabel = 'By size';
          } elseif ($bsStockTracked && $bsStockQty <= 0) {
            $bsStockClass = 'is-out';
            $bsStockIcon = 'bi-exclamation-circle';
            $bsStockLabel = 'Out of stock';
          } elseif ($bsStockTracked && $bsStockQty <= 3) {
            $bsStockClass = 'is-low';
            $bsStockIcon = 'bi-box-seam';
            $bsStockLabel = 'Only '.$bsStockQty.' left';
          } elseif ($bsStockTracked) {
            $bsStockClass = 'is-ok';
            $bsStockIcon = 'bi-box-seam';
            $bsStockLabel = $bsStockQty.' available';
          } else {
            $bsStockClass = '';
            $bsStockIcon = 'bi-check-circle';
            $bsStockLabel = 'Available';
          }
        @endphp
        <div class="col-6 col-md-3">
          <div class="product-card" style="border-color:#ffd8c0;position:relative">
            <div style="position:absolute;top:.5rem;right:.5rem;z-index:3;background:linear-gradient(135deg,#ff6b35,#e53935);color:#fff;font-size:.75rem;font-weight:700;padding:.22rem .6rem;border-radius:99px;display:flex;align-items:center;gap:.25rem">
              <i class="bi bi-fire"></i> Top Seller
            </div>
            <div class="product-img-wrap">
              @if($bs->image_path)
                <img src="{{ $bs->image_path }}" alt="{{ $bs->name }}"
                     onerror="this.parentElement.querySelector('.product-img-ph').style.display='flex';this.style.display='none'">
              @endif
              <div class="product-img-ph" style="display:{{ $bs->image_path ? 'none' : 'flex' }}">
                <i class="bi bi-cake2" style="font-size:2.5rem;color:var(--primary);opacity:.35"></i>
              </div>
              <span class="class-badge" style="background:{{ $bsCb['bg'] }};color:{{ $bsCb['color'] }}"><i class="bi {{ $bsCb['icon'] }} me-1"></i>{{ $bsClassification }}</span>
              @if($bsAvg > 0)
                <span class="product-rating-badge" style="top:2.35rem"><i class="bi bi-star-fill me-1"></i>{{ number_format($bsAvg,1) }}</span>
              @endif
              @if(!empty($bsPricing['has_discount']) && $bsDealBadge)
                <span class="deal-badge"><i class="bi bi-stars me-1"></i>{{ $bsDealBadge }}</span>
              @endif
            </div>
            <div class="product-body">
              <div class="product-name">{{ $bs->name }}</div>
              @if($bs->flavor)
                <span class="flavor-tag"><i class="bi bi-droplet me-1" style="font-size:.62rem"></i>{{ $bs->flavor }}</span>
              @endif
              <div class="product-stock-note {{ $bsStockClass }}">
                <i class="bi {{ $bsStockIcon }}"></i>{{ $bsStockLabel }}
              </div>
              @if($bs->description)
                <p class="product-desc">{{ \Illuminate\Support\Str::limit($bs->description, 80) }}</p>
              @endif
              @if($bsAvg > 0)
                <div class="small mb-1 product-rating-row" style="color:#f59e0b">
                  @for($i=1;$i<=5;$i++)<i class="bi bi-star{{ $i <= round($bsAvg) ? '-fill' : '' }}"></i>@endfor
                  <span class="text-muted ms-1">{{ number_format($bsAvg,1) }} ({{ $bsReviewCount }} review{{ $bsReviewCount != 1 ? 's' : '' }})</span>
                </div>
              @endif
              <div class="product-card-bottom">
                <div>
                  <div class="product-price">
                    @if(!empty($bsPricing['has_discount']))
                      &#8369;{{ number_format($bsPricing['final_unit_price'],2) }}
                      <span class="product-price-old">&#8369;{{ number_format($bsPricing['original_unit_price'],2) }}</span>
                    @else
                      &#8369;{{ number_format($bs->price,2) }}
                    @endif
                  </div>
                  @if(!empty($bsPricing['badge_text']))
                    <div class="product-price-meta" style="color:#be123c">{{ $bsPricing['badge_text'] }}</div>
                  @elseif($bsHasSizes)
                    <div class="product-price-meta">Base price</div>
                  @endif
                </div>
                <span class="product-sold-badge"><i class="bi bi-fire me-1"></i>{{ number_format($bs->total_sold) }} sold</span>
              </div>
              @if(!empty($bsPricing['has_discount']) && ($bsDealNote || $bsDealQty > 0 || $bsBestEnjoyedBy))
                <div class="deal-detail-pills">
                  @if($bsDealNote)<span>{{ $bsDealNote }}</span>@endif
                  @if($bsDealQty > 0)<span>{{ $bsDealQty }} deal pcs</span>@endif
                  @if($bsBestEnjoyedBy)<span>Fresh until {{ $bsBestEnjoyedBy->format('M d, g:i A') }}</span>@endif
                </div>
              @endif
              <button class="btn-order" data-bs-toggle="modal" data-bs-target="#shopOrderModal{{ $bs->id }}" {{ !$bsHasStock ? 'disabled' : '' }}>
                <i class="bi {{ $bsHasStock ? 'bi-cart-plus' : 'bi-x-circle' }} me-1"></i>{{ $bsHasStock ? 'Order Now' : 'Not Available' }}
              </button>
            </div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
    <hr style="border-color:var(--gray-200);margin-bottom:1.5rem">
    @endif
    {{-- ── END TOP SELLERS ── --}}

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
      @php
        $classification = $p->classification ?? 'Standard';
        $cb = $classBadge[$classification] ?? $classBadge['Standard'];
        $sizes = $productSizes[$p->id] ?? collect();
        $pricing = $p->discount_snapshot ?? null;
        $activeDiscount = $p->active_discount ?? null;
        $sweetDealBadge = ($activeDiscount && property_exists($activeDiscount, 'deal_badge_label')) ? trim((string) ($activeDiscount->deal_badge_label ?? '')) : '';
        $sweetDealNote = ($activeDiscount && property_exists($activeDiscount, 'deal_note')) ? trim((string) ($activeDiscount->deal_note ?? '')) : '';
        $sweetDealQty = ($activeDiscount && property_exists($activeDiscount, 'deal_quantity_limit')) ? (int) ($activeDiscount->deal_quantity_limit ?? 0) : 0;
        $bestEnjoyedBy = ($activeDiscount && property_exists($activeDiscount, 'best_enjoyed_by') && !empty($activeDiscount->best_enjoyed_by)) ? \Carbon\Carbon::parse($activeDiscount->best_enjoyed_by) : null;
        $stockTracked = property_exists($p, 'available_quantity') && $p->available_quantity !== null;
        $stockQty = $stockTracked ? max(0, (int) $p->available_quantity) : null;
        $hasSizeOptions = $sizes->count() > 0;
        $hasStock = $hasSizeOptions
          ? collect($sizes)->contains(fn($sz) => !property_exists($sz, 'available_quantity') || $sz->available_quantity === null || (int) $sz->available_quantity > 0)
          : (!$stockTracked || $stockQty > 0);
        $cardRating = $productRatings[$p->id] ?? null;
        $cardAvg = $cardRating ? (float) $cardRating->avg_rating : 0;
        $cardReviewCount = $cardRating ? (int) $cardRating->review_count : 0;
        if ($hasSizeOptions) {
          $stockClass = '';
          $stockIcon = 'bi-rulers';
          $stockLabel = 'By size';
        } elseif ($stockTracked && $stockQty <= 0) {
          $stockClass = 'is-out';
          $stockIcon = 'bi-exclamation-circle';
          $stockLabel = 'Out of stock';
        } elseif ($stockTracked && $stockQty <= 3) {
          $stockClass = 'is-low';
          $stockIcon = 'bi-box-seam';
          $stockLabel = 'Only '.$stockQty.' left';
        } elseif ($stockTracked) {
          $stockClass = 'is-ok';
          $stockIcon = 'bi-box-seam';
          $stockLabel = $stockQty.' available';
        } else {
          $stockClass = '';
          $stockIcon = 'bi-check-circle';
          $stockLabel = 'Available';
        }
      @endphp
      <div class="col-6 col-md-4 col-lg-3 prod-item" data-class="{{ $classification }}">
        <div class="product-card">
          <div class="product-img-wrap">
            @if($p->image_path)
              <img src="{{ $p->image_path }}" alt="{{ $p->name }}"
                   onerror="this.parentElement.querySelector('.product-img-ph').style.display='flex';this.style.display='none'">
            @endif
            <div class="product-img-ph" style="display:{{ $p->image_path ? 'none' : 'flex' }}">
              <i class="bi bi-cake2" style="font-size:2.5rem;color:var(--primary);opacity:.35"></i>
            </div>
            <span class="class-badge" style="background:{{ $cb['bg'] }};color:{{ $cb['color'] }}"><i class="bi {{ $cb['icon'] }} me-1"></i>{{ $classification }}</span>
            @if($cardAvg > 0)
              <span class="product-rating-badge"><i class="bi bi-star-fill me-1"></i>{{ number_format($cardAvg,1) }}</span>
            @endif
            @if(!empty($pricing['has_discount']) && $sweetDealBadge)
              <span class="deal-badge"><i class="bi bi-stars me-1"></i>{{ $sweetDealBadge }}</span>
            @endif
          </div>
          <div class="product-body">
            <div class="product-name">{{ $p->name }}</div>
            @if($p->flavor)
              <span class="flavor-tag"><i class="bi bi-droplet me-1" style="font-size:.62rem"></i>{{ $p->flavor }}</span>
            @endif
            <div class="product-stock-note {{ $stockClass }}">
              <i class="bi {{ $stockIcon }}"></i>{{ $stockLabel }}
            </div>
            @if($p->description)
              <p class="product-desc">{{ \Illuminate\Support\Str::limit($p->description, 80) }}</p>
            @endif
            @if($cardAvg > 0)
              <div class="small mb-1 product-rating-row" style="color:#f59e0b">
                @for($i=1;$i<=5;$i++)<i class="bi bi-star{{ $i <= round($cardAvg) ? '-fill' : '' }}"></i>@endfor
                <span class="text-muted ms-1">{{ number_format($cardAvg,1) }} ({{ $cardReviewCount }} review{{ $cardReviewCount != 1 ? 's' : '' }})</span>
              </div>
            @endif
            <div class="product-card-bottom">
              <div>
                <div class="product-price">
                  @if(!empty($pricing['has_discount']))
                    &#8369;{{ number_format($pricing['final_unit_price'],2) }}
                    <span class="product-price-old">&#8369;{{ number_format($pricing['original_unit_price'],2) }}</span>
                  @else
                    &#8369;{{ number_format($p->price,2) }}
                  @endif
                </div>
                @if(!empty($pricing['badge_text']))
                  <div class="product-price-meta" style="color:#be123c">{{ $pricing['badge_text'] }}</div>
                @elseif($hasSizeOptions)
                  <div class="product-price-meta">Base price</div>
                @endif
              </div>
              @if((int)($p->total_sold ?? 0) > 0)
                <span class="product-sold-badge"><i class="bi bi-fire me-1"></i>{{ number_format($p->total_sold) }} sold</span>
              @endif
            </div>
            @if(!empty($pricing['has_discount']) && ($sweetDealNote || $sweetDealQty > 0 || $bestEnjoyedBy))
              <div class="deal-detail-pills">
                @if($sweetDealNote)<span>{{ $sweetDealNote }}</span>@endif
                @if($sweetDealQty > 0)<span>{{ $sweetDealQty }} deal pcs</span>@endif
                @if($bestEnjoyedBy)<span>Fresh until {{ $bestEnjoyedBy->format('M d, g:i A') }}</span>@endif
              </div>
            @endif
            <button class="btn-order" data-bs-toggle="modal" data-bs-target="#shopOrderModal{{ $p->id }}" {{ !$hasStock ? 'disabled' : '' }}>
              <i class="bi {{ $hasStock ? 'bi-cart-plus' : 'bi-x-circle' }} me-1"></i>{{ $hasStock ? 'Order Now' : 'Not Available' }}
            </button>
          </div>
        </div>
      </div>
      @endforeach
    </div>
    <div class="shop-see-more-wrap text-center mt-4" id="shopSeeMoreWrap">
      <button type="button" class="btn btn-primary shop-see-more-btn" onclick="loadMoreShopProducts()">
        <i class="bi bi-chevron-down me-1"></i><span data-shop-see-more-text>See more cakes</span>
      </button>
      <div class="small text-muted mt-2" id="shopSeeMoreHint"></div>
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
      $pricing = $p->discount_snapshot ?? null;
      $activeDiscount = $p->active_discount ?? null;
      $sweetDealBadge = ($activeDiscount && property_exists($activeDiscount, 'deal_badge_label')) ? trim((string) ($activeDiscount->deal_badge_label ?? '')) : '';
      $sweetDealNote = ($activeDiscount && property_exists($activeDiscount, 'deal_note')) ? trim((string) ($activeDiscount->deal_note ?? '')) : '';
      $sweetDealQty = ($activeDiscount && property_exists($activeDiscount, 'deal_quantity_limit')) ? (int) ($activeDiscount->deal_quantity_limit ?? 0) : 0;
      $bestEnjoyedBy = ($activeDiscount && property_exists($activeDiscount, 'best_enjoyed_by') && !empty($activeDiscount->best_enjoyed_by)) ? \Carbon\Carbon::parse($activeDiscount->best_enjoyed_by) : null;
      $stockTracked = property_exists($p, 'available_quantity') && $p->available_quantity !== null;
      $stockQty = $stockTracked ? max(0, (int) $p->available_quantity) : null;
      $hasSizeOptions = $sizes->count() > 0;
      $hasStock = $hasSizeOptions
        ? collect($sizes)->contains(fn($sz) => !property_exists($sz, 'available_quantity') || $sz->available_quantity === null || (int) $sz->available_quantity > 0)
        : (!$stockTracked || $stockQty > 0);
      $baseDisplayPrice = !empty($pricing['has_discount']) ? (float) $pricing['final_unit_price'] : (float) $p->price;
    @endphp
    <div class="modal fade" id="shopOrderModal{{ $p->id }}" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg modal-fullscreen-sm-down">
        <div class="modal-content border-0" style="border-radius:1.4rem;overflow:hidden">

          {{-- Sticky Header --}}
          <div class="modal-header border-0 pb-0 px-4 pt-3" style="background:#fff;position:sticky;top:0;z-index:10">
            <div class="d-flex align-items-center gap-2 flex-wrap flex-grow-1">
              <span class="badge" style="background:{{ $cls['bg'] }};color:{{ $cls['color'] }};font-size:.72rem">
                <i class="bi {{ $cls['icon'] }} me-1"></i>{{ $p->classification }}
              </span>
              <span class="badge {{ $hasStock ? 'bg-success' : 'bg-danger' }}" style="font-size:.72rem"><i class="bi {{ $hasStock ? 'bi-check-circle' : 'bi-x-circle' }} me-1"></i>{{ $hasStock ? 'Available' : 'Out of Stock' }}</span>
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
                    <div class="fw-bold" style="color:var(--primary);font-size:1.15rem">
                      ₱{{ number_format($baseDisplayPrice,2) }}
                      @if(!empty($pricing['has_discount']))
                        <span class="product-price-old">₱{{ number_format($pricing['original_unit_price'],2) }}</span>
                      @endif
                    </div>
                    @if(!empty($pricing['badge_text']))
                      <div class="fw-semibold" style="font-size:.7rem;color:#dc2626">{{ $pricing['badge_text'] }}</div>
                    @elseif($sizes->count() > 0)
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

              @if(!empty($pricing['has_discount']) && ($sweetDealBadge || $sweetDealNote || $sweetDealQty > 0 || $bestEnjoyedBy))
              <div class="alert border-0 py-2 small mb-3" style="background:#fff7ed;border-radius:.7rem;color:#9a3412">
                <i class="bi bi-stars me-1"></i>
                <strong>{{ $sweetDealBadge ?: 'Sweet Deal' }}</strong>
                <div class="deal-detail-pills mt-2">
                  @if($sweetDealNote)<span>{{ $sweetDealNote }}</span>@endif
                  @if($sweetDealQty > 0)<span>{{ $sweetDealQty }} deal pcs</span>@endif
                  @if($bestEnjoyedBy)<span>Fresh until {{ $bestEnjoyedBy->format('M d, g:i A') }}</span>@endif
                </div>
              </div>
              @endif

              <div class="alert border-0 py-2 small mb-3" style="background:#f8fafc;border-radius:.7rem;color:#475569">
                <i class="bi bi-box-seam me-1" style="color:var(--primary)"></i>
                @if($hasSizeOptions)
                  Stock varies by size. Choose a size to see availability.
                @elseif($stockTracked)
                  {{ $stockQty }} available.
                @else
                  Available for checkout.
                @endif
              </div>

              <hr class="my-3">

              {{-- Order Form --}}
              @php
                $viewerRole = session('user')['role'] ?? null;
                $canCheckoutFromShop = !$viewerRole || $viewerRole === 'customer';
              @endphp
              @if($viewerRole === 'customer')
                <form action="{{ route('customer.catalog.order') }}" method="POST" onsubmit="return confirmOrder(this)">
              @else
                <form action="{{ route('catalog.select') }}" method="POST" onsubmit="return confirmOrder(this)">
              @endif
                <input type="hidden" name="_viewer_can_checkout" value="{{ $canCheckoutFromShop ? '1' : '0' }}">
                <input type="hidden" name="_viewer_role" value="{{ $viewerRole ?? 'guest' }}">
                @csrf
                <input type="hidden" name="product_id" value="{{ $p->id }}">

                {{-- Size Selection --}}
                @if($sizes->count() > 0)
                <div class="mb-3">
                  <div class="fw-semibold small mb-2"><i class="bi bi-rulers me-1" style="color:var(--primary)"></i>Sizes <span class="text-danger">*</span></div>
                  <input type="hidden" name="selected_size" id="selectedSize{{ $p->id }}">
                  <div class="d-flex flex-wrap gap-2" data-size-picker id="sizeOptions{{ $p->id }}">
                    @foreach($sizes as $sz)
                      @php
                        $sizeStockTracked = property_exists($sz, 'available_quantity') && $sz->available_quantity !== null;
                        $sizeStockQty = $sizeStockTracked ? max(0, (int) $sz->available_quantity) : null;
                        $sizeAvailable = !$sizeStockTracked || $sizeStockQty > 0;
                        $sizePricing = \App\Helpers\CakeshopHelper::calculateDiscountSnapshot((float) $sz->price, $activeDiscount);
                        $sizeDisplayPrice = !empty($sizePricing['has_discount']) ? (float) $sizePricing['final_unit_price'] : (float) $sz->price;
                      @endphp
                      <button type="button"
                              class="size-choice-btn px-3 py-1 rounded-pill border bg-white {{ $loop->iteration > 4 ? 'd-none is-extra-size' : '' }}"
                              data-product-id="{{ $p->id }}"
                              data-base-price="{{ $baseDisplayPrice }}"
                              data-size-label="{{ $sz->label }}"
                              data-price="{{ $sizeDisplayPrice }}"
                              data-stock-tracked="{{ $sizeStockTracked ? '1' : '0' }}"
                              data-stock-qty="{{ $sizeStockTracked ? $sizeStockQty : '' }}"
                              onclick="selectModalSize(this)" {{ $sizeAvailable ? '' : 'disabled' }}>
                        <span class="fw-semibold">{{ $sz->label }}</span>
                        <span class="text-muted ms-1">— ₱{{ number_format($sizeDisplayPrice,2) }}</span>
                        @if($sizeStockTracked)
                          <span class="text-muted ms-1">({{ $sizeStockQty }} left)</span>
                        @endif
                      </button>
                    @endforeach
                    @if($sizes->count() > 4)
                      <button type="button" class="size-view-more-btn" data-size-toggle data-product-id="{{ $p->id }}" onclick="toggleSizeOptions(this)">
                        View more
                      </button>
                    @endif
                  </div>
                  <div class="small text-danger mt-1 d-none" data-size-error>Please select a size.</div>
                  <div class="mt-2 p-2 rounded-2 d-flex align-items-center justify-content-between" style="background:#fff0f5">
                    <span class="small text-muted">Total Price:</span>
                    <span class="fw-bold" style="color:var(--primary);font-size:1.05rem" id="modalPrice{{ $p->id }}">
                      ₱{{ number_format($baseDisplayPrice,2) }}
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
                           min="1" max="{{ $hasSizeOptions ? 20 : ($stockTracked ? max(1, $stockQty) : 20) }}" value="1" required style="width:70px" data-default-max="{{ $hasSizeOptions ? 20 : ($stockTracked ? max(1, $stockQty) : 20) }}">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3"
                            onclick="changeQty('{{ $p->id }}', 1)">+</button>
                  </div>
                </div>

                <div class="alert border-0 py-2 small mb-3" style="background:#fff0f5;border-radius:.7rem">
                  <i class="bi bi-info-circle me-1" style="color:var(--primary)"></i>
                  You'll choose pickup/delivery on the next step.
                </div>

                {{-- Date Availability --}}
                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" {{ $hasStock ? '' : 'disabled' }}>
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
          <div style="font-size:.78rem;color:var(--gray-500);margin-bottom:.15rem;text-transform:uppercase;letter-spacing:.04em">{{ $label }}</div>
          <div style="font-size:.95rem;font-weight:600;color:var(--gray-900)">{{ $value }}</div>
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

{{-- Checkout dialog --}}
<div id="shopCheckoutDialog" class="shop-dialog" aria-hidden="true" onclick="shopDialogBackdrop(event)">
  <div class="shop-dialog-panel" role="dialog" aria-modal="true" aria-labelledby="shopDialogTitle">
    <div id="shopDialogIconWrap" class="shop-dialog-icon">
      <i id="shopDialogIcon" class="bi bi-cart-check"></i>
    </div>
    <h3 id="shopDialogTitle">Proceed to Checkout?</h3>
    <p id="shopDialogMessage">You will be redirected to the checkout page.</p>
    <div class="shop-dialog-actions">
      <button type="button" id="shopDialogCancel" class="shop-dialog-cancel" onclick="shopDialogClose()">Cancel</button>
      <button type="button" id="shopDialogOk" class="shop-dialog-ok">Proceed</button>
    </div>
  </div>
</div>

<style>
  .shop-dialog {
    position:fixed;
    inset:0;
    z-index:10050;
    display:none;
    align-items:center;
    justify-content:center;
    padding:1rem;
    background:rgba(15,23,42,0);
    transition:background .22s ease;
  }
  .shop-dialog.is-open { background:rgba(15,23,42,.54); }
  .shop-dialog-panel {
    width:min(420px,100%);
    background:#fff;
    border-radius:18px;
    box-shadow:0 24px 70px rgba(15,23,42,.28);
    padding:1.45rem;
    text-align:center;
    transform:translateY(18px) scale(.94);
    opacity:0;
    transition:transform .22s ease, opacity .22s ease;
  }
  .shop-dialog.is-open .shop-dialog-panel { transform:translateY(0) scale(1); opacity:1; }
  .shop-dialog-icon {
    width:58px;
    height:58px;
    border-radius:16px;
    margin:0 auto .9rem;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#dbeafe;
    color:#2563eb;
    font-size:1.45rem;
  }
  .shop-dialog h3 {
    margin:0 0 .45rem;
    color:#111827;
    font-size:1.12rem;
    font-weight:800;
  }
  .shop-dialog p {
    margin:0;
    color:#6b7280;
    font-size:.9rem;
    line-height:1.6;
  }
  .shop-dialog-actions {
    display:flex;
    gap:.65rem;
    margin-top:1.25rem;
  }
  .shop-dialog-actions button {
    flex:1;
    border:0;
    border-radius:12px;
    padding:.72rem 1rem;
    font-weight:800;
    font-size:.88rem;
  }
  .shop-dialog-cancel {
    background:#f3f4f6;
    color:#374151;
  }
  .shop-dialog-ok {
    background:var(--primary);
    color:#fff;
  }
  @media(max-width:575.98px) {
    .shop-dialog-panel { padding:1.25rem; }
    .shop-dialog-actions { flex-direction:column-reverse; }
  }
</style>

<script>
let pendingCheckoutForm = null;

function shopDialogOpen(opts) {
  const dialog = document.getElementById('shopCheckoutDialog');
  const iconWrap = document.getElementById('shopDialogIconWrap');
  const icon = document.getElementById('shopDialogIcon');
  const title = document.getElementById('shopDialogTitle');
  const message = document.getElementById('shopDialogMessage');
  const cancel = document.getElementById('shopDialogCancel');
  const ok = document.getElementById('shopDialogOk');

  opts = opts || {};
  title.textContent = opts.title || 'Proceed to Checkout?';
  message.textContent = opts.message || 'You will be redirected to the checkout page.';
  icon.className = 'bi ' + (opts.icon || 'bi-cart-check');
  iconWrap.style.background = opts.iconBg || '#dbeafe';
  iconWrap.style.color = opts.iconColor || '#2563eb';
  cancel.style.display = opts.showCancel === false ? 'none' : '';
  ok.textContent = opts.okLabel || 'Proceed';
  ok.style.background = opts.okColor || 'var(--primary)';
  ok.onclick = function() {
    const cb = opts.onConfirm;
    shopDialogClose(function() {
      if (typeof cb === 'function') cb();
    });
  };

  dialog.style.display = 'flex';
  dialog.setAttribute('aria-hidden', 'false');
  document.body.style.overflow = 'hidden';
  requestAnimationFrame(function() { dialog.classList.add('is-open'); });
}

function shopDialogClose(afterClose) {
  const dialog = document.getElementById('shopCheckoutDialog');
  dialog.classList.remove('is-open');
  setTimeout(function() {
    dialog.style.display = 'none';
    dialog.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    if (typeof afterClose === 'function') afterClose();
  }, 220);
}

function shopDialogBackdrop(e) {
  if (e.target && e.target.id === 'shopCheckoutDialog') shopDialogClose();
}

function confirmCustomCake(event, el) {
  if (event) event.preventDefault();
  const href = el.getAttribute('href');
  const viewerRole = el.dataset.viewerRole || 'guest';
  const fallbackMessage = href
    ? 'Start a custom cake order for this shop?'
    : 'Custom cake ordering is disabled while you are signed in as ' + viewerRole + '. To test this flow, sign out or use a customer account.';

  if (typeof shopDialogOpen !== 'function' || !document.getElementById('shopCheckoutDialog')) {
    if (href && window.confirm(fallbackMessage)) window.location.href = href;
    if (!href) window.alert(fallbackMessage);
    return false;
  }

  if (!href) {
    shopDialogOpen({
      title: 'Admin Preview Mode',
      message: 'Custom cake ordering is disabled while you are signed in as ' + viewerRole + '. To test this flow, sign out or use a customer account.',
      icon: 'bi-shield-lock',
      iconBg: '#fff7ed',
      iconColor: '#ea580c',
      okLabel: 'Got it',
      okColor: '#ea580c',
      showCancel: false
    });
    return false;
  }

  shopDialogOpen({
    title: 'Start a Custom Cake Order?',
    message: 'You will be taken to the custom cake request form for this shop.',
    icon: 'bi-palette-fill',
    iconBg: '#fce7f3',
    iconColor: 'var(--primary)',
    okLabel: 'Continue',
    okColor: 'var(--primary)',
    onConfirm: function() {
      window.location.href = href;
    }
  });
  return false;
}

window.confirmCustomCake = confirmCustomCake;

function confirmOrder(form) {
  const canCheckout = form.querySelector('input[name="_viewer_can_checkout"]')?.value === '1';
  const viewerRole = form.querySelector('input[name="_viewer_role"]')?.value || 'guest';

  if (!canCheckout) {
    shopDialogOpen({
      title: 'Admin Preview Mode',
      message: 'Checkout is disabled while you are signed in as ' + viewerRole + '. To test ordering, sign out or use a customer account.',
      icon: 'bi-shield-lock',
      iconBg: '#fff7ed',
      iconColor: '#ea580c',
      okLabel: 'Got it',
      okColor: '#ea580c',
      showCancel: false
    });
    return false;
  }

  if (!validateSizeSelection(form)) return false;

  if (typeof cakeConfirm === 'function') {
    cakeConfirm({
      title: 'Proceed to Checkout?',
      message: 'You will be redirected to the checkout page.',
      icon: 'bi-cart-check',
      iconBg: '#dbeafe',
      iconColor: '#2563eb',
      okLabel: 'Proceed',
      okColor: '#2563eb',
      onConfirm: () => form.submit()
    });
    return false;
  }

  pendingCheckoutForm = form;
  shopDialogOpen({
    title: 'Proceed to Checkout?',
    message: 'Your selected item will be prepared for checkout. You can review delivery and payment details on the next page.',
    icon: 'bi-cart-check',
    iconBg: '#dbeafe',
    iconColor: '#2563eb',
    okLabel: 'Proceed',
    okColor: '#2563eb',
    onConfirm: function() {
      const submitForm = pendingCheckoutForm;
      pendingCheckoutForm = null;
      if (submitForm) submitForm.submit();
    }
  });
  return false;
}
function validateSizeSelection(form) {
  const input = form.querySelector('input[name="selected_size"]');
  const selected = form.querySelector('.size-choice-btn.is-selected');
  if (input && !input.value && selected) {
    input.value = selected.dataset.sizeLabel || '';
  }
  if (!input || input.value) return true;

  const error = form.querySelector('[data-size-error]');
  const picker = form.querySelector('[data-size-picker]');
  if (error) error.classList.remove('d-none');
  if (picker) picker.scrollIntoView({ behavior: 'smooth', block: 'center' });
  return false;
}

function selectModalSize(button) {
  if (!button || button.disabled) return;
  const productId = button.dataset.productId || '';
  const basePrice = parseFloat(button.dataset.basePrice || '0');
  const form = button.closest('form');
  const picker = button.closest('[data-size-picker]');
  const input = form ? form.querySelector('input[name="selected_size"]') : null;
  const error = form ? form.querySelector('[data-size-error]') : null;

  if (input) input.value = button.dataset.sizeLabel || '';
  if (error) error.classList.add('d-none');
  if (picker) {
    picker.querySelectorAll('.size-choice-btn').forEach(btn => {
      btn.classList.remove('is-selected');
      btn.setAttribute('aria-pressed', 'false');
    });
  }
  button.classList.add('is-selected');
  button.setAttribute('aria-pressed', 'true');
  const qtyInput = document.getElementById('qty' + productId);
  if (qtyInput) {
    const tracked = button.dataset.stockTracked === '1';
    const stockQty = parseInt(button.dataset.stockQty || '0', 10) || 0;
    const nextMax = tracked ? Math.max(1, stockQty) : (parseInt(qtyInput.dataset.defaultMax || '20', 10) || 20);
    qtyInput.max = String(nextMax);
    if ((parseInt(qtyInput.value || '1', 10) || 1) > nextMax) qtyInput.value = String(nextMax);
  }
  updateModalPrice(productId, basePrice, button);
}

function toggleSizeOptions(button) {
  const productId = button.dataset.productId || '';
  const picker = document.getElementById('sizeOptions' + productId);
  if (!picker) return;

  const expanded = picker.dataset.expanded === '1';
  picker.querySelectorAll('.is-extra-size').forEach(btn => btn.classList.toggle('d-none', expanded));
  picker.dataset.expanded = expanded ? '0' : '1';
  button.textContent = expanded ? 'View more' : 'View less';
}

function updateModalPrice(productId, basePrice, priceSource) {
  const price = priceSource && priceSource.dataset.price ? parseFloat(priceSource.dataset.price) : basePrice;
  const el    = document.getElementById('modalPrice' + productId);
  if (el) el.textContent = '₱' + price.toLocaleString('en-PH', {minimumFractionDigits:2});
}
function changeQty(productId, delta) {
  const input = document.getElementById('qty' + productId);
  if (!input) return;
  const max = parseInt(input.max || input.dataset.defaultMax || '20', 10) || 20;
  let val = (parseInt(input.value || '1', 10) || 1) + delta;
  if (val < 1) val = 1;
  if (val > max) val = max;
  input.value = val;
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
const SHOP_INITIAL_LIMIT = 20;
const SHOP_LOAD_STEP = 10;
let shopVisibleLimit = SHOP_INITIAL_LIMIT;
let activeShopClass = 'all';

function applyShopProductVisibility(resetLimit) {
  if (resetLimit) shopVisibleLimit = SHOP_INITIAL_LIMIT;
  let matchCount = 0;
  let shownCount = 0;

  document.querySelectorAll('.prod-item').forEach(el => {
    const matches = activeShopClass === 'all' || el.dataset.class === activeShopClass;
    if (matches) matchCount++;
    const shouldShow = matches && shownCount < shopVisibleLimit;
    el.style.display = shouldShow ? '' : 'none';
    if (shouldShow) shownCount++;
  });

  const seeMoreWrap = document.getElementById('shopSeeMoreWrap');
  const seeMoreHint = document.getElementById('shopSeeMoreHint');
  if (seeMoreWrap) seeMoreWrap.style.display = matchCount > shownCount ? 'block' : 'none';
  if (seeMoreHint) {
    const remaining = matchCount - shownCount;
    seeMoreHint.textContent = remaining > 0 ? remaining + ' more cake' + (remaining === 1 ? '' : 's') + ' available' : '';
  }
}

function loadMoreShopProducts() {
  shopVisibleLimit += SHOP_LOAD_STEP;
  applyShopProductVisibility(false);
}

function filterProds(cls, btn) {
  activeShopClass = cls || 'all';
  document.querySelectorAll('.filter-chip').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  applyShopProductVisibility(true);
}

document.addEventListener('DOMContentLoaded', function() {
  applyShopProductVisibility(true);
});

window.filterProds = filterProds;
window.loadMoreShopProducts = loadMoreShopProducts;
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
