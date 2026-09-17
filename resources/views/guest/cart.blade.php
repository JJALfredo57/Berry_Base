@extends('layouts.app')
@section('content')
<div class="container-fluid py-4">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
      <h4 class="fw-bold mb-1"><i class="bi bi-cart3 me-2" style="color:var(--primary)"></i>Your Cart</h4>
      <div class="text-muted small">Guest cart is saved in this browser session. OTP verification is required at checkout.</div>
    </div>
    <a href="{{ route('catalog') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add more</a>
  </div>
  @if(session('msg'))<div class="alert alert-success border-0">{{ session('msg') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger border-0">{{ session('error') }}</div>@endif
  <div class="row g-3">
    <div class="col-lg-8">
      @forelse($items as $item)
        <div class="card mb-3">
          <div class="card-body">
            <div class="d-flex gap-3">
              <img src="{{ $item->image_path ?: '/images/no-image.png' }}" alt="{{ $item->product_name }}" style="width:82px;height:82px;object-fit:cover;border-radius:8px">
              <div class="flex-grow-1">
                <div class="d-flex justify-content-between gap-2">
                  <div>
                    <div class="fw-bold">{{ $item->product_name }}</div>
                    <div class="text-muted small">{{ $item->shop_name ?: 'BerryBase Seller' }} @if($item->selected_size) &bull; {{ $item->selected_size }} @endif</div>
                  </div>
                  <div class="fw-bold text-nowrap" style="color:var(--primary)">PHP {{ number_format($item->final_unit_price_snapshot * $item->quantity, 2) }}</div>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center mt-3">
                  <form action="{{ route('cart.items.update', $item->id) }}" method="POST" class="d-flex gap-2">
                    @csrf
                    <input type="number" min="1" max="99" name="quantity" value="{{ $item->quantity }}" class="form-control form-control-sm" style="width:76px">
                    <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-repeat"></i></button>
                  </form>
                  <form action="{{ route('cart.items.checkout', $item->id) }}" method="POST">@csrf<button class="btn btn-primary btn-sm"><i class="bi bi-bag-check me-1"></i>Checkout</button></form>
                  <form action="{{ route('cart.items.remove', $item->id) }}" method="POST">@csrf<button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form>
                </div>
              </div>
            </div>
          </div>
        </div>
      @empty
        <div class="card"><div class="card-body text-center py-5"><i class="bi bi-cart-x" style="font-size:2.4rem;color:#cbd5e1"></i><h5 class="fw-bold mt-3">Your cart is empty</h5><a href="{{ route('catalog') }}" class="btn btn-primary mt-2">Browse Catalog</a></div></div>
      @endforelse
    </div>
    <div class="col-lg-4">
      <div class="card"><div class="card-body"><div class="fw-bold mb-2">Cart Summary</div><div class="d-flex justify-content-between"><span>Subtotal</span><span class="fw-bold">PHP {{ number_format($subtotal, 2) }}</span></div><div class="alert alert-info small mt-3 mb-0">Login or create an account to save orders, earn rewards, and unlock verified benefits.</div></div></div>
    </div>
  </div>
</div>
@endsection
