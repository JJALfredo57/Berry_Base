@extends('layouts.app')

@section('content')
@php
  $acceptedDate = $offer->accepted_date ?: $offer->preferred_date;
  $acceptedTime = substr((string)($offer->accepted_time ?: $offer->preferred_time), 0, 5);
  $acceptedAt = $acceptedDate ? \Carbon\Carbon::parse($acceptedDate.' '.($acceptedTime ?: '00:00')) : null;
  $expiresAt = !empty($offer->expires_at) ? \Carbon\Carbon::parse($offer->expires_at) : null;
  $isActiveOffer = in_array($offer->status, ['accepted', 'customer_accepted'], true) && (!$expiresAt || $expiresAt->isFuture());
  $total = $finalPrice * max(1, (int) $offer->quantity);
@endphp
<style>
.request-offer-wrap{max-width:980px;margin:0 auto;padding:clamp(14px,3vw,28px)}.offer-hero{background:#fff;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;box-shadow:0 18px 44px rgba(15,23,42,.08)}.offer-top{display:grid;grid-template-columns:320px 1fr;gap:0}.offer-img{width:100%;height:100%;min-height:280px;object-fit:cover;background:#f8fafc}.offer-info{padding:1.25rem}.offer-title{font-weight:850;color:#111827;margin:0;font-size:clamp(1.35rem,3vw,2rem)}.offer-meta{display:flex;gap:.5rem;flex-wrap:wrap;margin:.75rem 0}.offer-pill{display:inline-flex;align-items:center;gap:.35rem;border-radius:999px;padding:.35rem .65rem;font-size:.78rem;font-weight:800;background:#f8fafc;color:#475569}.offer-pill.rush{background:#fff1f2;color:#be123c}.offer-pill.ok{background:#dcfce7;color:#166534}.offer-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem;margin:1rem 0}.offer-box{background:#f8fafc;border:1px solid #eef2f7;border-radius:14px;padding:.8rem}.offer-label{display:block;font-size:.68rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;font-weight:800}.offer-value{font-weight:800;color:#111827;margin-top:.2rem}.offer-note{background:#fff7fb;border:1px solid #fbcfe8;border-radius:14px;padding:.9rem;margin-top:.75rem;color:#831843}.offer-actions{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-top:1rem}.offer-btn{border:0;border-radius:12px;padding:.8rem 1rem;font-weight:850}.offer-btn.primary{background:#e91e63;color:#fff}.offer-btn.soft{background:#fff;color:#991b1b;border:1px solid #fecaca}.offer-decline{display:none;margin-top:.75rem}.offer-decline.show{display:block}@media(max-width:760px){.offer-top{grid-template-columns:1fr}.offer-img{height:230px;min-height:0}.offer-grid,.offer-actions{grid-template-columns:1fr}.request-offer-wrap{padding:10px}}
</style>

<div class="request-offer-wrap">
  <a href="{{ $isCustomer ? route('customer.catalog') : route('catalog') }}" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-arrow-left me-1"></i>Back to catalog</a>

  <div class="offer-hero">
    <div class="offer-top">
      @if($offer->image_path)
        <img class="offer-img" src="{{ $offer->image_path }}" alt="{{ $offer->product_name }}">
      @else
        <div class="offer-img d-flex align-items-center justify-content-center"><i class="bi bi-cake2" style="font-size:4rem;color:#e91e63;opacity:.35"></i></div>
      @endif
      <div class="offer-info">
        <div class="offer-meta">
          @if($offer->is_rush)<span class="offer-pill rush"><i class="bi bi-lightning-charge-fill"></i>Rush request</span>@endif
          <span class="offer-pill ok"><i class="bi bi-check-circle"></i>{{ $offer->status === 'accepted' ? 'Seller accepted' : ucfirst(str_replace('_',' ', $offer->status)) }}</span>
          <span class="offer-pill"><i class="bi bi-shop"></i>{{ $offer->shop_name }}</span>
        </div>
        <h1 class="offer-title">{{ $offer->alternative_product_name ?: $offer->product_name }}</h1>
        <p class="text-muted mb-0">Review the seller's offer. This will become an order only after you accept and finish checkout.</p>

        <div class="offer-grid">
          <div class="offer-box"><span class="offer-label">Final Price</span><div class="offer-value">PHP {{ number_format($finalPrice, 2) }} each</div></div>
          <div class="offer-box"><span class="offer-label">Total</span><div class="offer-value">PHP {{ number_format($total, 2) }}</div></div>
          <div class="offer-box"><span class="offer-label">Quantity</span><div class="offer-value">{{ (int)$offer->quantity }} pc{{ (int)$offer->quantity === 1 ? '' : 's' }}</div></div>
          <div class="offer-box"><span class="offer-label">Accepted Schedule</span><div class="offer-value">{{ $acceptedAt ? $acceptedAt->format('M d, Y g:i A') : 'To confirm' }}</div></div>
          @if($expiresAt)<div class="offer-box"><span class="offer-label">Offer Expires</span><div class="offer-value" data-countdown="{{ $expiresAt->toIso8601String() }}">{{ $expiresAt->format('M d, g:i A') }}</div></div>@endif
          @if($offer->allow_similar_cake)<div class="offer-box"><span class="offer-label">Similar Cake</span><div class="offer-value">Allowed by customer</div></div>@endif
        </div>

        @if($offer->seller_response)
          <div class="offer-note"><strong>Seller message:</strong><br>{{ $offer->seller_response }}</div>
        @endif
        @if($offer->customer_note)
          <div class="offer-note" style="background:#f8fafc;border-color:#e5e7eb;color:#334155"><strong>Your request note:</strong><br>{{ $offer->customer_note }}</div>
        @endif

        @if($isActiveOffer)
          <div class="offer-actions">
            <form method="POST" action="{{ $isCustomer ? route('customer.order_requests.accept', $offer->id) : route('order_requests.accept_token', ['id'=>$offer->id, 'token'=>$token]) }}" data-prevent-double-submit>
              @csrf
              <button class="offer-btn primary w-100" type="submit" data-loading-text="Preparing checkout..."><i class="bi bi-bag-check me-1"></i>Accept Offer</button>
            </form>
            <button class="offer-btn soft" type="button" onclick="document.getElementById('declineBox').classList.toggle('show')"><i class="bi bi-x-circle me-1"></i>Decline</button>
          </div>
          <div id="declineBox" class="offer-decline">
            <form method="POST" action="{{ $isCustomer ? route('customer.order_requests.decline', $offer->id) : route('order_requests.decline_token', ['id'=>$offer->id, 'token'=>$token]) }}">
              @csrf
              <textarea name="customer_decision_note" class="form-control mb-2" rows="2" maxlength="500" placeholder="Optional note for seller"></textarea>
              <button class="btn btn-outline-danger w-100 fw-bold" type="submit">Confirm decline</button>
            </form>
          </div>
        @else
          <div class="alert alert-warning border-0 mt-3 mb-0">This offer is no longer active. You can send a new request or message the seller.</div>
        @endif
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  var el = document.querySelector('[data-countdown]');
  if(!el) return;
  var target = new Date(el.getAttribute('data-countdown')).getTime();
  function tick(){
    var diff = target - Date.now();
    if(diff <= 0){ el.textContent = 'Expired'; return; }
    var mins = Math.floor(diff / 60000), h = Math.floor(mins / 60), m = mins % 60;
    el.textContent = h + 'h ' + m + 'm left';
  }
  tick(); setInterval(tick, 30000);
})();
</script>
@endsection
