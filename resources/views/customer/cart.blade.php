@extends('layouts.app')
@section('content')
<div class="container-fluid py-4">
  <style>
    .cart-shop-card{border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;background:#fff}
    .cart-shop-head{background:#fff7fb;border-bottom:1px solid #fce7f3}
    .cart-group-list{position:relative}
    .cart-group-list::before{content:"";position:absolute;left:40px;top:18px;bottom:18px;width:2px;background:linear-gradient(var(--primary),#f9a8d4);opacity:.5}
    .cart-group-item{position:relative}
    .cart-group-dot{width:12px;height:12px;border-radius:50%;background:var(--primary);box-shadow:0 0 0 5px #fff;position:absolute;left:35px;top:36px;z-index:1}
    @media(max-width:575.98px){.cart-group-list::before{left:28px}.cart-group-dot{left:23px}.cart-item-img{width:68px!important;height:68px!important}}
  </style>
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
      <h4 class="fw-bold mb-1"><i class="bi bi-cart3 me-2" style="color:var(--primary)"></i>My Cart</h4>
      <div class="text-muted small">Saved cakes stay here while you compare and choose what to checkout.</div>
    </div>
    <a href="{{ route('customer.catalog') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add more</a>
  </div>

  @if(session('msg'))<div class="alert alert-success border-0">{{ session('msg') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger border-0">{{ session('error') }}</div>@endif

  <div class="row g-3">
    <div class="col-lg-8">
      @forelse(($groups ?? collect()) as $group)
        <div class="cart-shop-card mb-3">
          <div class="cart-shop-head p-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
              @if(!empty($group->shop_logo))
                <img src="{{ $group->shop_logo }}" alt="{{ $group->shop_name }}" style="width:38px;height:38px;border-radius:9px;object-fit:cover">
              @else
                <div style="width:38px;height:38px;border-radius:9px;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff"><i class="bi bi-shop"></i></div>
              @endif
              <div>
                <div class="fw-bold">{{ $group->shop_name }}</div>
                <div class="text-muted small">{{ $group->item_count }} cake option{{ $group->item_count > 1 ? 's' : '' }} connected for one {{ strtolower('Pickup/Delivery') }}</div>
              </div>
            </div>
            <div class="text-end">
              <div class="fw-bold" style="color:var(--primary)">PHP {{ number_format($group->subtotal, 2) }}</div>
              <div class="text-muted small">{{ $group->quantity }} total item{{ $group->quantity > 1 ? 's' : '' }}</div>
            </div>
          </div>
          <div class="cart-group-list">
            @foreach($group->items as $item)
              <div class="cart-group-item p-3 ps-sm-5">
                <span class="cart-group-dot"></span>
                <div class="d-flex gap-3 ms-3">
                  <img class="cart-item-img" src="{{ $item->image_path ?: '/images/no-image.png' }}" alt="{{ $item->product_name }}" style="width:82px;height:82px;object-fit:cover;border-radius:8px">
                  <div class="flex-grow-1 min-w-0">
                    <div class="d-flex justify-content-between gap-2">
                      <div>
                        <div class="fw-bold">{{ $item->product_name }}</div>
                        <div class="text-muted small">Qty {{ $item->quantity }} @if($item->selected_size) &bull; {{ $item->selected_size }} @endif</div>
                      </div>
                      <div class="fw-bold text-nowrap" style="color:var(--primary)">PHP {{ number_format($item->final_unit_price_snapshot * $item->quantity, 2) }}</div>
                    </div>
                    @if($item->discount_amount_snapshot > 0)
                      <div class="small mt-1" style="color:#dc2626"><i class="bi bi-tags me-1"></i>{{ $item->discount_label_snapshot ?: 'Product discount' }}</div>
                    @endif
                    @if($item->custom_note)
                      <div class="small text-muted mt-2">{{ $item->custom_note }}</div>
                    @endif
                    <div class="d-flex flex-wrap gap-2 align-items-center mt-3">
                      <form action="{{ route('customer.cart.items.update', $item->id) }}" method="POST" class="d-flex align-items-center gap-2">
                        @csrf
                        <input type="number" min="1" max="99" name="quantity" value="{{ $item->quantity }}" class="form-control form-control-sm" style="width:76px">
                        <button class="btn btn-outline-secondary btn-sm" title="Update quantity"><i class="bi bi-arrow-repeat"></i></button>
                      </form>
                      <form action="{{ route('customer.cart.items.remove', $item->id) }}" method="POST">
                        @csrf
                        <button class="btn btn-outline-danger btn-sm" title="Remove item"><i class="bi bi-trash"></i></button>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
          <div class="p-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="small text-muted"><i class="bi bi-link-45deg me-1"></i>Same seller items use one schedule and one pickup/delivery fee.</div>
            <form action="{{ route('customer.cart.shops.checkout', $group->key) }}" method="POST">
              @csrf
              <button class="btn btn-primary btn-sm"><i class="bi bi-bag-check me-1"></i>Checkout this seller</button>
            </form>
          </div>
        </div>
      @empty
        <div class="card">
          <div class="card-body text-center py-5">
            <i class="bi bi-cart-x" style="font-size:2.4rem;color:#cbd5e1"></i>
            <h5 class="fw-bold mt-3">Your cart is empty</h5>
            <p class="text-muted">Add cakes from the catalog and come back when you are ready.</p>
            <a href="{{ route('customer.catalog') }}" class="btn btn-primary"><i class="bi bi-grid me-1"></i>Browse Catalog</a>
          </div>
        </div>
      @endforelse
    </div>

    <div class="col-lg-4">
      <div class="card position-sticky" style="top:1rem">
        <div class="card-body">
          <div class="fw-bold mb-2">Cart Summary</div>
          <div class="d-flex justify-content-between small text-muted mb-2"><span>Items</span><span>{{ $items->sum('quantity') }}</span></div>
          <div class="d-flex justify-content-between mb-3"><span class="fw-semibold">Subtotal</span><span class="fw-bold">PHP {{ number_format($subtotal, 2) }}</span></div>
          <div class="rounded-3 p-3 mb-3" style="background:#f8fafc;border:1px solid #e5e7eb">
            <div class="small fw-semibold"><i class="bi bi-stars me-1" style="color:var(--primary)"></i>{{ $loyalty->tier ?? 'Bronze' }} Member</div>
            <div class="text-muted small">{{ (int)($loyalty->points_balance ?? 0) }} points available</div>
          </div>
          @if($verificationStatus !== 'approved')
            <a href="{{ route('customer.verification') }}" class="btn btn-outline-primary w-100 btn-sm">
              <i class="bi bi-shield-check me-1"></i>Unlock verified rewards
            </a>
          @else
            <div class="alert alert-success py-2 small mb-0"><i class="bi bi-patch-check-fill me-1"></i>Verified benefits unlocked.</div>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
