@extends('layouts.app')
@section('content')
<div class="container-fluid py-4 cart-page-shell">
  <style>
    .cart-page-shell{max-width:1540px;margin-inline:auto;padding-inline:clamp(.15rem,1.5vw,1rem)}
    .cart-page-head{padding:1rem 1.1rem;border:1px solid color-mix(in srgb,var(--primary) 16%,#e5e7eb);border-radius:18px;background:linear-gradient(135deg,color-mix(in srgb,var(--primary-bg) 72%,#fff),#fff);box-shadow:0 10px 30px rgba(15,23,42,.05)}
    .cart-title-icon{width:42px;height:42px;border-radius:13px;display:grid;place-items:center;color:#fff;background:linear-gradient(135deg,var(--primary),var(--primary-dark));box-shadow:0 8px 18px color-mix(in srgb,var(--primary) 24%,transparent);flex:0 0 auto}
    .cart-add-more{display:inline-flex;align-items:center;gap:.65rem;min-height:44px;padding:.55rem .9rem;border:1px solid color-mix(in srgb,var(--primary) 28%,#fff);border-radius:13px;background:#fff;color:var(--primary);font-weight:750;text-decoration:none;box-shadow:0 7px 18px color-mix(in srgb,var(--primary) 10%,transparent);transition:transform .18s ease,box-shadow .18s ease,background .18s ease}
    .cart-add-more:hover{color:#fff;background:var(--primary);transform:translateY(-2px);box-shadow:0 11px 24px color-mix(in srgb,var(--primary) 22%,transparent)}
    .cart-add-more-icon{width:29px;height:29px;border-radius:9px;display:grid;place-items:center;background:var(--primary-bg);color:var(--primary)}
    .cart-add-more:hover .cart-add-more-icon{background:rgba(255,255,255,.18);color:#fff}
    .cart-shop-card{border:1px solid #e7e5e4;border-radius:18px;overflow:hidden;background:#fff;box-shadow:0 10px 30px rgba(15,23,42,.06)}
    .cart-shop-head{background:linear-gradient(135deg,color-mix(in srgb,var(--primary-bg) 65%,#fff),#fff);border-bottom:1px solid color-mix(in srgb,var(--primary) 14%,#e5e7eb)}
    .cart-shop-head-grid{display:grid;grid-template-columns:36px minmax(0,1fr) auto;align-items:center;gap:.8rem}
    .cart-select-all-label{display:grid;place-items:center;margin:0;cursor:pointer}
    .cart-check{appearance:none;-webkit-appearance:none;width:24px;height:24px;margin:0;border:2px solid #cbd5e1;border-radius:8px;background:#fff;display:grid;place-content:center;cursor:pointer;transition:background .18s ease,border-color .18s ease,box-shadow .18s ease,transform .18s ease}
    .cart-check::before{content:"";width:10px;height:6px;border-left:2px solid #fff;border-bottom:2px solid #fff;transform:rotate(-45deg) scale(0);transition:transform .16s ease}
    .cart-check:checked,.cart-check:indeterminate{background:var(--primary);border-color:var(--primary);box-shadow:0 0 0 4px color-mix(in srgb,var(--primary) 12%,transparent)}
    .cart-check:checked::before{transform:rotate(-45deg) scale(1)}
    .cart-check:indeterminate::before{width:10px;height:0;border-left:0;border-bottom:2px solid #fff;transform:none}
    .cart-check:focus-visible{outline:3px solid color-mix(in srgb,var(--primary) 28%,transparent);outline-offset:3px}
    .cart-select-label{font-size:.72rem;font-weight:750;color:var(--primary);margin-top:.3rem;text-align:center;white-space:nowrap}
    .cart-group-item{position:relative;padding:1rem;transition:background .18s ease,box-shadow .18s ease}
    .cart-group-item+.cart-group-item{border-top:1px solid #f1f5f9}
    .cart-group-item.is-selected{background:linear-gradient(90deg,color-mix(in srgb,var(--primary-bg) 42%,#fff),#fff 45%);box-shadow:inset 3px 0 var(--primary)}
    .cart-item-layout{display:grid;grid-template-columns:36px 82px minmax(0,1fr);align-items:start;gap:.8rem}
    .cart-item-layout>*,.cart-shop-head-grid>*{min-width:0}
    .cart-item-check-wrap{display:grid;place-items:center;padding-top:.15rem}
    .cart-item-img{width:82px;height:82px;object-fit:cover;border-radius:13px!important;border:1px solid #f1f5f9;box-shadow:0 6px 15px rgba(15,23,42,.09)}
    .cart-item-title-row{display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem}
    .cart-item-title-row .fw-bold{overflow-wrap:anywhere}
    .cart-item-price{color:var(--primary);white-space:nowrap;padding:.3rem .55rem;border-radius:9px;background:var(--primary-bg)}
    .cart-shop-footer{padding:1rem;background:#fcfcfd;border-top:1px solid #f1f5f9}
    .cart-checkout-btn{min-height:42px;border-radius:11px;padding-inline:1rem;font-weight:750;box-shadow:0 7px 16px color-mix(in srgb,var(--primary) 18%,transparent)}
    .cart-summary-card{border:1px solid color-mix(in srgb,var(--primary) 14%,#e5e7eb);border-radius:18px;box-shadow:0 10px 30px rgba(15,23,42,.06);overflow:hidden}
    .cart-summary-title{display:flex;align-items:center;gap:.6rem;padding-bottom:.75rem;margin-bottom:.75rem;border-bottom:1px solid #f1f5f9}
    .cart-empty-card{border:1px dashed color-mix(in srgb,var(--primary) 28%,#cbd5e1);border-radius:18px;background:linear-gradient(145deg,#fff,var(--primary-bg));box-shadow:none}
    .cart-qty-wrap{display:flex;flex-direction:column;gap:.35rem}
    .cart-qty-control{display:inline-grid;grid-template-columns:38px minmax(54px,70px) 38px;align-items:center;border:1px solid #e5e7eb;border-radius:999px;overflow:hidden;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.04)}
    .cart-qty-btn{height:38px;border:0;background:#fff;color:var(--primary);font-weight:800;display:flex;align-items:center;justify-content:center;transition:background .15s ease,color .15s ease}
    .cart-qty-btn:hover{background:#fff0f5;color:#7c2d12}
    .cart-qty-value{height:38px;display:flex;align-items:center;justify-content:center;border-inline:1px solid #e5e7eb;font-weight:800;color:#111827;background:#f8fafc;min-width:54px}
    .cart-fixed-dialog{position:fixed;inset:0;z-index:2050;display:none;align-items:center;justify-content:center;padding:1rem;background:rgba(15,23,42,.42);backdrop-filter:blur(3px)}
    .cart-fixed-dialog.show{display:flex}
    .cart-dialog-card{width:min(420px,100%);background:#fff;border-radius:14px;box-shadow:0 24px 70px rgba(15,23,42,.28);border:1px solid #f1f5f9;overflow:hidden;animation:cartDialogIn .16s ease-out}
    .cart-dialog-body{padding:1.15rem}
    .cart-dialog-icon{width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#fff0f5;color:var(--primary);font-size:1.2rem;flex-shrink:0}
    .custom-hold-wrap{margin-top:.55rem;padding:.6rem .7rem;border-radius:10px;background:#fff;border:1px solid #fce7f3}
    .custom-hold-head{display:flex;align-items:center;justify-content:space-between;gap:.75rem;font-size:.78rem;color:#6b7280;margin-bottom:.4rem}
    .custom-hold-bar{height:8px;border-radius:999px;background:#f3f4f6;overflow:hidden;box-shadow:inset 0 1px 2px rgba(15,23,42,.08)}
    .custom-hold-fill{height:100%;width:100%;border-radius:inherit;background:linear-gradient(90deg,var(--primary),#f472b6);transition:width .45s ease,background .2s ease}
    .custom-hold-wrap.expired{border-color:#fecaca;background:#fffafa}
    .custom-hold-wrap.expired .custom-hold-fill{background:#ef4444}
    .custom-hold-info{border:0;background:transparent;color:var(--primary);padding:0;line-height:1}
    .custom-hold-tip{position:relative;display:inline-flex}
    .custom-hold-tip:hover::after,.custom-hold-tip:focus-within::after{content:attr(data-tip);position:absolute;right:0;bottom:calc(100% + 8px);width:min(280px,76vw);background:#111827;color:#fff;border-radius:8px;padding:.55rem .65rem;font-size:.75rem;line-height:1.3;box-shadow:0 12px 30px rgba(15,23,42,.25);z-index:10}
    @media(max-width:575.98px){.custom-hold-head{align-items:flex-start;flex-direction:column;gap:.35rem}.custom-hold-tip:hover::after,.custom-hold-tip:focus-within::after{left:0;right:auto}}
    .custom-reschedule-note{margin-top:.55rem;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.55rem;padding:.65rem .75rem;border-radius:10px;background:#f8fafc;border:1px solid #e5e7eb}
    .custom-reschedule-note .meta{min-width:0;color:#4b5563;font-size:.78rem;line-height:1.35}
    .custom-reschedule-dialog .cart-dialog-card{width:min(560px,100%)}
    .custom-reschedule-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
    .custom-reschedule-choice{border:1px solid #e5e7eb;border-radius:10px;padding:.7rem .8rem;background:#fff;display:flex;align-items:center;gap:.5rem;cursor:pointer}
    .custom-reschedule-choice:has(input:checked){border-color:var(--primary);background:#fff7fb;color:var(--primary)}
    @media(max-width:575.98px){.custom-reschedule-grid{grid-template-columns:1fr}.custom-reschedule-note{align-items:flex-start}.custom-reschedule-note .btn{width:100%}}
    @keyframes cartDialogIn{from{transform:translateY(8px) scale(.98);opacity:.6}to{transform:none;opacity:1}}
    @media(max-width:767.98px){
      .cart-page-head{align-items:stretch!important}.cart-add-more{width:100%;justify-content:center}
      .cart-shop-head-grid{grid-template-columns:32px minmax(0,1fr)}
      .cart-shop-total{grid-column:2;text-align:left!important;padding-left:0}
      .cart-item-layout{grid-template-columns:28px 68px minmax(0,1fr);gap:.6rem}
      .cart-item-img{width:68px!important;height:68px!important}
      .cart-item-title-row{flex-direction:column;gap:.35rem}.cart-item-price{font-size:.82rem}
      .cart-shop-footer{align-items:stretch!important}.cart-shop-footer form,.cart-checkout-btn{width:100%}
      .cart-summary-card{position:static!important}.cart-select-label{font-size:.66rem}
    }
    @media(max-width:374.98px){.cart-shop-head{padding:.8rem .65rem!important}.cart-shop-head-grid{grid-template-columns:26px minmax(0,1fr)}.cart-group-item{padding:.8rem .65rem}.cart-item-layout{grid-template-columns:26px 60px minmax(0,1fr);gap:.5rem}.cart-item-img{width:60px!important;height:60px!important}.cart-page-head{padding:.85rem}}
    @media(prefers-reduced-motion:reduce){.cart-add-more,.cart-check{transition-duration:.01ms!important}.cart-add-more:hover{transform:none}}
  </style>
  <div class="cart-page-head d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
      <div class="cart-title-icon"><i class="bi bi-cart3"></i></div>
      <div>
        <h4 class="fw-bold mb-1">Your Cart</h4>
        <div class="text-muted small">Review your cakes, choose what to include, then checkout by seller.</div>
      </div>
    </div>
    <a href="{{ route('catalog') }}" class="cart-add-more"><span class="cart-add-more-icon"><i class="bi bi-plus-lg"></i></span><span>Add More Cakes</span></a>
  </div>
  @if(session('msg'))<div class="alert alert-success border-0">{{ session('msg') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger border-0">{{ session('error') }}</div>@endif
  <div class="row g-3">
    <div class="col-lg-8">
      @forelse(($groups ?? collect()) as $group)
        @php $checkoutFormId = 'guestCartCheckout' . $loop->iteration; @endphp
        <div class="cart-shop-card mb-3" data-cart-group="{{ $checkoutFormId }}">
          <div class="cart-shop-head p-3">
            <div class="cart-shop-head-grid">
              <label class="cart-select-all-label" for="selectAll{{ $checkoutFormId }}">
                <input class="cart-check cart-select-all" type="checkbox" id="selectAll{{ $checkoutFormId }}" data-cart-group="{{ $checkoutFormId }}" checked>
                <span class="cart-select-label">All</span>
              </label>
              <div class="d-flex align-items-center gap-2 min-w-0">
              @if(!empty($group->shop_logo))
                <img src="{{ $group->shop_logo }}" alt="{{ $group->shop_name }}" style="width:42px;height:42px;border-radius:12px;object-fit:cover">
              @else
                <div style="width:38px;height:38px;border-radius:9px;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff"><i class="bi bi-shop"></i></div>
              @endif
              <div>
                <div class="fw-bold">{{ $group->shop_name }}</div>
                <div class="text-muted small">{{ $group->item_count }} cake option{{ $group->item_count > 1 ? 's' : '' }} connected for one pickup/delivery</div>
              </div>
              </div>
            <div class="text-end cart-shop-total">
              <div class="fw-bold" style="color:var(--primary)">PHP {{ number_format($group->subtotal, 2) }}</div>
              <div class="text-muted small">{{ $group->quantity }} total item{{ $group->quantity > 1 ? 's' : '' }}</div>
            </div>
          </div>
          </div>
          <div class="cart-group-list">
            @foreach($group->items as $item)
              <div class="cart-group-item is-selected">
                <div class="cart-item-layout">
                  <div class="cart-item-check-wrap"><input class="cart-check cart-item-check" type="checkbox" name="selected_item_ids[]" value="{{ $item->id }}" form="{{ $checkoutFormId }}" data-cart-group="{{ $checkoutFormId }}" data-item-subtotal="{{ (float) $item->final_unit_price_snapshot * (int) $item->quantity }}" data-item-quantity="{{ (int) $item->quantity }}" checked aria-label="Select {{ $item->product_name }} for checkout"></div>
                  <img class="cart-item-img" src="{{ $item->image_path ?: '/images/no-image.png' }}" alt="{{ $item->product_name }}" style="width:82px;height:82px;object-fit:cover;border-radius:8px">
                  <div class="flex-grow-1 min-w-0">
                    <div class="cart-item-title-row">
                      <div>
                        <div class="fw-bold">{{ $item->product_name }}</div>
                        <div class="text-muted small">Qty {{ $item->quantity }} @if($item->selected_size) &bull; {{ $item->selected_size }} @endif</div>
                      </div>
                      <div class="fw-bold cart-item-price">PHP {{ number_format($item->final_unit_price_snapshot * $item->quantity, 2) }}</div>
                    </div>
                    @php
                      $cartMeta = json_decode($item->meta ?? '[]', true) ?: [];
                      $isCustomCakeCart = ($cartMeta['cart_type'] ?? '') === 'custom_cake';
                    @endphp
                    @if($isCustomCakeCart)
                      <div class="mt-2 p-2 rounded-3" style="background:#fff7fb;border:1px solid #fce7f3">
                        <div class="small fw-bold mb-1" style="color:var(--primary)"><i class="bi bi-palette me-1"></i>Custom cake draft</div>
                        <div class="small text-muted">
                          {{ $cartMeta['flavor'] ?? 'Flavor not set' }}
                          @if(!empty($cartMeta['size'])) &bull; {{ $cartMeta['size'] }} @endif
                          @if(!empty($cartMeta['layers'])) &bull; {{ $cartMeta['layers'] }} @endif
                        </div>
                        <div class="small text-muted">
                          <i class="bi bi-calendar-event me-1"></i>{{ $cartMeta['schedule_date'] ?? 'No date' }}
                          @if(!empty($cartMeta['time_slot'])) &bull; {{ $cartMeta['time_slot'] }} @endif
                          &bull; {{ $cartMeta['fulfillment_type'] ?? 'Pickup' }}
                        </div>
                        <div class="small text-muted">Seller capacity will be checked again before checkout submits this request.</div>
                        <div class="custom-reschedule-note">
                          <div class="meta">
                            <div class="fw-semibold text-dark"><i class="bi bi-calendar2-week me-1" style="color:var(--primary)"></i>Need another slot?</div>
                            <div>Keep this draft and update only the schedule.</div>
                          </div>
                          <button type="button" class="btn btn-outline-primary btn-sm" onclick="customRescheduleOpen(this)"
                            data-action="{{ route('cart.items.reschedule_custom_hold', $item->id) }}"
                            data-date="{{ $cartMeta['schedule_date'] ?? '' }}"
                            data-time="{{ $cartMeta['schedule_time'] ?? '' }}"
                            data-fulfillment="{{ $cartMeta['fulfillment_type'] ?? 'Pickup' }}"
                            data-summary="{{ ($cartMeta['schedule_date'] ?? 'No date') . (!empty($cartMeta['time_slot']) ? ' - ' . $cartMeta['time_slot'] : '') . ' - ' . ($cartMeta['fulfillment_type'] ?? 'Pickup') }}">
                            <i class="bi bi-calendar-plus me-1"></i>Reschedule
                          </button>
                        </div>
                        @if(!empty($cartMeta['fulfillment_hold_expires_at']))
                          <div class="custom-hold-wrap" data-custom-hold data-hold-expires="{{ $cartMeta['fulfillment_hold_expires_at'] }}" data-hold-minutes="{{ (int)($cartMeta['fulfillment_hold_minutes'] ?? 15) }}">
                            <div class="custom-hold-head">
                              <span><i class="bi bi-hourglass-split me-1"></i><strong data-hold-label>Schedule hold active</strong></span>
                              <span class="custom-hold-tip" data-tip="This timer temporarily keeps your selected custom cake date and time. When it expires, update fulfillment to recheck seller availability.">
                                <button type="button" class="custom-hold-info" aria-label="What is this timer?"><i class="bi bi-info-circle"></i></button>
                              </span>
                            </div>
                            <div class="custom-hold-bar"><div class="custom-hold-fill" data-hold-fill></div></div>
                            <div class="small mt-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                              <span class="text-muted" data-hold-text>Calculating hold time...</span>
                              <form action="{{ route('cart.items.refresh_custom_hold', $item->id) }}" method="POST" class="m-0 d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary btn-sm py-1 px-2 d-none" data-hold-update>Refresh fulfillment</button>
                              </form>
                            </div>
                          </div>
                        @endif
                      </div>
                    @endif
                    <div class="d-flex flex-wrap gap-2 align-items-center mt-3">
                      @php
                        $cartQtyTracked = $item->available_quantity !== null;
                        $cartQtyMax = $cartQtyTracked ? max(0, (int) $item->available_quantity) : 99;
                      @endphp
                      @if($isCustomCakeCart)
                        <div class="cart-qty-wrap">
                          <div class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill" style="border:1px solid #e5e7eb;background:#f8fafc">
                            <i class="bi bi-lock-fill" style="color:var(--primary)"></i>
                            <span class="small text-muted">Qty locked</span>
                            <span class="fw-bold">{{ $item->quantity }}</span>
                          </div>
                          <div class="small text-muted">Quantity is part of this custom cake request.</div>
                        </div>
                        <form action="{{ route('cart.items.remove', $item->id) }}" method="POST" class="m-0">
                          @csrf
                          <button type="submit" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-trash3 me-1"></i>Remove custom cake
                          </button>
                        </form>
                      @else
                        <div class="cart-qty-wrap">
                          <div class="cart-qty-control" aria-label="Quantity for {{ $item->product_name }}">
                            <button type="button" class="cart-qty-btn" onclick="cartQtyStep('qtyForm{{ $item->id }}','removeForm{{ $item->id }}',-1, @js($item->product_name))" aria-label="Decrease quantity"><i class="bi bi-dash-lg"></i></button>
                            <span class="cart-qty-value" id="qtyValue{{ $item->id }}">{{ $item->quantity }}</span>
                            <button type="button" class="cart-qty-btn" onclick="cartQtyStep('qtyForm{{ $item->id }}','removeForm{{ $item->id }}',1, @js($item->product_name))" aria-label="Increase quantity"><i class="bi bi-plus-lg"></i></button>
                          </div>
                          @if($cartQtyTracked)
                            @if($cartQtyMax > 0)
                              <div class="small text-muted"><i class="bi bi-box-seam me-1"></i>{{ $cartQtyMax }} available</div>
                            @else
                              <div class="small text-danger"><i class="bi bi-exclamation-circle me-1"></i>Out of stock</div>
                            @endif
                          @endif
                        </div>
                        <form id="qtyForm{{ $item->id }}" action="{{ route('cart.items.update', $item->id) }}" method="POST" class="d-none">
                          @csrf
                          <input type="hidden" name="quantity" value="{{ $item->quantity }}" data-cart-qty-input data-current-quantity="{{ $item->quantity }}" data-max-quantity="{{ $cartQtyMax }}" data-stock-tracked="{{ $cartQtyTracked ? '1' : '0' }}">
                        </form>
                        <form id="removeForm{{ $item->id }}" action="{{ route('cart.items.remove', $item->id) }}" method="POST" class="d-none">@csrf</form>
                      @endif
                    </div>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
          <div class="cart-shop-footer d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
              <div class="small text-muted"><i class="bi bi-link-45deg me-1"></i>Same seller items use one schedule and one pickup/delivery fee.</div>
              <div class="small fw-semibold mt-1" data-cart-selected-summary="{{ $checkoutFormId }}" style="color:var(--primary)"></div>
            </div>
            <form id="{{ $checkoutFormId }}" action="{{ route('cart.shops.checkout', $group->key) }}" method="POST">
              @csrf
              <button class="btn btn-primary cart-checkout-btn" data-cart-checkout-button="{{ $checkoutFormId }}"><i class="bi bi-bag-check me-1"></i>Checkout Selected</button>
            </form>
          </div>
        </div>
      @empty
        <div class="card cart-empty-card"><div class="card-body text-center py-5"><i class="bi bi-cart-x" style="font-size:2.4rem;color:#cbd5e1"></i><h5 class="fw-bold mt-3">Your cart is empty</h5><p class="text-muted">Add cakes from the catalog and return here when you are ready.</p><a href="{{ route('catalog') }}" class="btn btn-primary mt-2"><i class="bi bi-grid me-1"></i>Browse Catalog</a></div></div>
      @endforelse
    </div>
    <div class="col-lg-4">
      <div class="card cart-summary-card position-sticky" style="top:1rem"><div class="card-body"><div class="cart-summary-title"><span class="cart-add-more-icon"><i class="bi bi-receipt"></i></span><div><div class="fw-bold">Cart Summary</div><div class="text-muted" style="font-size:.72rem">Guest checkout overview</div></div></div><div class="d-flex justify-content-between"><span>Subtotal</span><span class="fw-bold">PHP {{ number_format($subtotal, 2) }}</span></div><div class="alert alert-info small mt-3 mb-0">Login or create an account to save orders, earn rewards, and unlock verified benefits.</div></div></div>
    </div>
  </div>
</div>
<div class="cart-fixed-dialog" id="cartQtyDialog" role="dialog" aria-modal="true" aria-labelledby="cartQtyDialogTitle">
  <div class="cart-dialog-card">
    <div class="cart-dialog-body">
      <div class="d-flex gap-3 align-items-start">
        <div class="cart-dialog-icon"><i class="bi bi-exclamation-triangle-fill" id="cartQtyDialogIcon"></i></div>
        <div class="flex-grow-1">
          <div class="fw-bold mb-1" id="cartQtyDialogTitle">Remove this cake?</div>
          <div class="text-muted small" id="cartQtyDialogMessage">Quantity is already 1.</div>
        </div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-4">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="cartQtyCloseDialog()" id="cartQtyCancelBtn">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="cartQtyConfirmDialog()" id="cartQtyConfirmBtn">Remove</button>
      </div>
    </div>
  </div>
</div>
<div class="cart-fixed-dialog custom-reschedule-dialog" id="customRescheduleDialog" role="dialog" aria-modal="true" aria-labelledby="customRescheduleTitle">
  <div class="cart-dialog-card">
    <form method="POST" id="customRescheduleForm">
      @csrf
      <div class="cart-dialog-body">
        <div class="d-flex gap-3 align-items-start mb-3">
          <div class="cart-dialog-icon"><i class="bi bi-calendar2-week"></i></div>
          <div class="flex-grow-1 min-w-0">
            <div class="fw-bold mb-1" id="customRescheduleTitle">Reschedule custom cake</div>
            <div class="text-muted small">Your design details stay in the cart. Only the schedule and fulfillment hold will be updated.</div>
          </div>
        </div>
        <div class="rounded-3 p-2 mb-3 small" style="background:#f8fafc;border:1px solid #e5e7eb">
          <span class="text-muted">Current:</span> <span class="fw-semibold" data-custom-reschedule-current>Selected schedule</span>
        </div>
        <div class="custom-reschedule-grid mb-3">
          <div>
            <label class="form-label small fw-semibold" for="customRescheduleDate">New date</label>
            <input type="date" class="form-control" id="customRescheduleDate" name="schedule_date" required>
          </div>
          <div>
            <label class="form-label small fw-semibold" for="customRescheduleTime">New time</label>
            <input type="time" class="form-control" id="customRescheduleTime" name="time_slot" required>
          </div>
        </div>
        <div class="small fw-semibold mb-2">Fulfillment</div>
        <div class="custom-reschedule-grid mb-3">
          <label class="custom-reschedule-choice mb-0">
            <input class="form-check-input m-0" type="radio" name="fulfillment_type" value="Pickup" checked>
            <span><i class="bi bi-shop me-1"></i>Pickup</span>
          </label>
          <label class="custom-reschedule-choice mb-0">
            <input class="form-check-input m-0" type="radio" name="fulfillment_type" value="Delivery">
            <span><i class="bi bi-truck me-1"></i>Delivery</span>
          </label>
        </div>
        <div class="small text-muted"><i class="bi bi-info-circle me-1"></i>Delivery uses the address already saved on this draft.</div>
        <div class="d-flex justify-content-end gap-2 mt-4">
          <button type="button" class="btn btn-outline-secondary btn-sm" onclick="customRescheduleClose()">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check2-circle me-1"></i>Update schedule</button>
        </div>
      </div>
    </form>
  </div>
</div>
<script>
let cartQtyPendingRemoveFormId = null;

function cartQtyShowDialog(type, title, message, removeFormId = null) {
  cartQtyPendingRemoveFormId = removeFormId;
  const dialog = document.getElementById('cartQtyDialog');
  const titleEl = document.getElementById('cartQtyDialogTitle');
  const messageEl = document.getElementById('cartQtyDialogMessage');
  const confirmBtn = document.getElementById('cartQtyConfirmBtn');
  const cancelBtn = document.getElementById('cartQtyCancelBtn');
  const icon = document.getElementById('cartQtyDialogIcon');
  if (!dialog || !titleEl || !messageEl || !confirmBtn || !cancelBtn) return;
  titleEl.textContent = title;
  messageEl.textContent = message;
  confirmBtn.style.display = type === 'remove' ? '' : 'none';
  confirmBtn.textContent = 'Remove';
  cancelBtn.textContent = type === 'remove' ? 'Cancel' : 'OK';
  if (icon) icon.className = type === 'remove' ? 'bi bi-exclamation-triangle-fill' : 'bi bi-info-circle-fill';
  dialog.classList.add('show');
}

function cartQtyCloseDialog() {
  const dialog = document.getElementById('cartQtyDialog');
  if (dialog) dialog.classList.remove('show');
  cartQtyPendingRemoveFormId = null;
}

function cartQtyConfirmDialog() {
  if (!cartQtyPendingRemoveFormId) return cartQtyCloseDialog();
  const form = document.getElementById(cartQtyPendingRemoveFormId);
  if (form) form.submit();
  cartQtyCloseDialog();
}

function cartQtyStep(formId, removeFormId, delta, productName) {
  const form = document.getElementById(formId);
  if (!form) return;
  const input = form.querySelector('[data-cart-qty-input]');
  if (!input) return;
  const current = parseInt(input.value || input.dataset.currentQuantity || '1', 10) || 1;
  const maxRaw = parseInt(input.dataset.maxQuantity || '99', 10);
  const max = Number.isNaN(maxRaw) ? 99 : maxRaw;
  const tracked = input.dataset.stockTracked === '1';

  if (delta < 0 && current <= 1) {
    cartQtyShowDialog('remove', 'Remove this cake?', 'Quantity is already 1. Do you want to remove ' + productName + ' from your cart?', removeFormId);
    return;
  }

  const next = current + delta;
  if (delta > 0 && tracked && max <= 0) {
    cartQtyShowDialog('info', 'Out of stock', productName + ' is currently out of stock. Please remove it from your cart or choose another cake.');
    return;
  }
  if (delta > 0 && tracked && next > max) {
    cartQtyShowDialog('info', 'Available stock reached', 'Only ' + max + ' ' + productName + ' available. Please reduce the quantity or choose another cake.');
    return;
  }

  const nextValue = (delta > 0 && tracked) ? Math.min(next, max) : next;
  input.value = Math.max(1, nextValue);
  form.submit();
}

function customRescheduleOpen(button) {
  const dialog = document.getElementById('customRescheduleDialog');
  const form = document.getElementById('customRescheduleForm');
  if (!dialog || !form || !button) return;
  form.action = button.dataset.action || '';
  const dateInput = document.getElementById('customRescheduleDate');
  const timeInput = document.getElementById('customRescheduleTime');
  const current = dialog.querySelector('[data-custom-reschedule-current]');
  if (dateInput) {
    dateInput.value = button.dataset.date || '';
    dateInput.min = new Date().toISOString().slice(0, 10);
  }
  if (timeInput) timeInput.value = button.dataset.time || '';
  dialog.querySelectorAll('[name="fulfillment_type"]').forEach(input => {
    input.checked = input.value === (button.dataset.fulfillment || 'Pickup');
  });
  if (current) current.textContent = button.dataset.summary || 'Selected schedule';
  dialog.classList.add('show');
}

function customRescheduleClose() {
  const dialog = document.getElementById('customRescheduleDialog');
  if (dialog) dialog.classList.remove('show');
}
function refreshCustomHoldTimers() {
  const now = Date.now();
  document.querySelectorAll('[data-custom-hold]').forEach(box => {
    const expiresRaw = box.dataset.holdExpires || '';
    const holdMinutes = Math.max(1, parseInt(box.dataset.holdMinutes || '15', 10) || 15);
    const expires = Date.parse(expiresRaw.replace(' ', 'T'));
    const fill = box.querySelector('[data-hold-fill]');
    const text = box.querySelector('[data-hold-text]');
    const label = box.querySelector('[data-hold-label]');
    const update = box.querySelector('[data-hold-update]');
    if (!expires || Number.isNaN(expires)) return;
    const totalMs = holdMinutes * 60 * 1000;
    const remaining = Math.max(0, expires - now);
    const pct = Math.max(0, Math.min(100, (remaining / totalMs) * 100));
    if (fill) fill.style.width = pct + '%';
    if (remaining <= 0) {
      box.classList.add('expired');
      if (label) label.textContent = 'Schedule hold expired';
      if (text) text.textContent = 'Update fulfillment to recheck custom cake availability.';
      if (update) update.classList.remove('d-none');
      return;
    }
    box.classList.remove('expired');
    if (update) update.classList.add('d-none');
    const minutes = Math.floor(remaining / 60000);
    const seconds = Math.floor((remaining % 60000) / 1000);
    if (label) label.textContent = 'Schedule hold active';
    if (text) text.textContent = minutes + ':' + String(seconds).padStart(2, '0') + ' schedule hold left';
  });
}
document.addEventListener('DOMContentLoaded', function () {
  refreshCustomHoldTimers();
  setInterval(refreshCustomHoldTimers, 1000);

  function money(value) {
    return 'PHP ' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function refreshGroup(groupId) {
    const checks = Array.from(document.querySelectorAll('.cart-item-check[data-cart-group="' + groupId + '"]'));
    const selected = checks.filter(check => check.checked);
    const selectedQty = selected.reduce((sum, check) => sum + (parseInt(check.dataset.itemQuantity || '0', 10) || 0), 0);
    const selectedTotal = selected.reduce((sum, check) => sum + (parseFloat(check.dataset.itemSubtotal || '0') || 0), 0);
    const summary = document.querySelector('[data-cart-selected-summary="' + groupId + '"]');
    const button = document.querySelector('[data-cart-checkout-button="' + groupId + '"]');
    const selectAll = document.querySelector('.cart-select-all[data-cart-group="' + groupId + '"]');

    if (summary) {
      summary.textContent = selected.length
        ? selected.length + ' selected cake option' + (selected.length > 1 ? 's' : '') + ' • ' + selectedQty + ' item' + (selectedQty > 1 ? 's' : '') + ' • ' + money(selectedTotal)
        : 'Select at least one cake to checkout.';
    }
    if (button) button.disabled = selected.length === 0;
    if (selectAll) {
      selectAll.checked = checks.length > 0 && selected.length === checks.length;
      selectAll.indeterminate = selected.length > 0 && selected.length < checks.length;
      selectAll.setAttribute('aria-checked', selectAll.indeterminate ? 'mixed' : String(selectAll.checked));
    }
    checks.forEach(check => check.closest('.cart-group-item')?.classList.toggle('is-selected', check.checked));
  }

  document.querySelectorAll('.cart-item-check').forEach(check => {
    check.addEventListener('change', () => refreshGroup(check.dataset.cartGroup));
  });

  document.querySelectorAll('.cart-select-all').forEach(check => {
    check.addEventListener('change', () => {
      document.querySelectorAll('.cart-item-check[data-cart-group="' + check.dataset.cartGroup + '"]').forEach(item => {
        item.checked = check.checked;
      });
      refreshGroup(check.dataset.cartGroup);
    });
  });

  document.querySelectorAll('[data-cart-group]').forEach(group => refreshGroup(group.dataset.cartGroup));
});
</script>
@endsection
