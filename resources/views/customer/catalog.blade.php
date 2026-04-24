@extends('layouts.app')
@section('content')
<div class="container-fluid py-4">
<style>
.catalog-grid{
  display:grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap:16px;
}
.catalog-item{ transition: all .3s ease; }
@media (hover: hover) {
  .catalog-card:hover {
    transform: translateY(-8px) scale(1.02) !important;
    box-shadow: 0 20px 40px rgba(233,30,99,.18) !important;
  }
}
</style>

  <div class="text-center mb-5">
    <h3 class="fw-bold" style="color:var(--primary)">
      <i class="bi bi-cake2 me-2"></i>{{ $settings['tagline'] ?? 'Our Cakes' }}
    </h3>
    <p class="text-muted">Choose your cake and place your order</p>
  </div>

  @if(session('msg'))
    <div class="alert alert-success border-0"><i class="bi bi-check-circle me-2"></i>{{ session('msg') }}</div>
  @endif

  
<div class="mb-4 d-flex justify-content-center">
  <div style="width:100%;">
    <input type="text" id="catalogSearch" class="form-control form-control-lg"
      placeholder="Search cakes..."
      oninput="filterCatalog()">
  </div>
</div>

<div class="catalog-grid" id="catalogGrid">
    @forelse($products as $p)
    @php
      $classification = $p->classification ?? 'Standard';
      $classColors = [
        'Standard'   => ['bg'=>'#dbeafe','color'=>'#1e40af','icon'=>'bi-cake2'],
        'Fondant'    => ['bg'=>'#fce7f3','color'=>'#9d174d','icon'=>'bi-stars'],
        'Perishable' => ['bg'=>'#d1fae5','color'=>'#065f46','icon'=>'bi-snow'],
      ];
      $cls         = $classColors[$classification] ?? $classColors['Standard'];
      $sizes       = $productSizes[$p->id] ?? collect();
      $avgRating   = $productRatings[$p->id] ?? null;
      $reviews     = $productReviews[$p->id] ?? [];
      $reviewCount = $productReviewCounts[$p->id] ?? 0;
      $isAvailable = (int)($p->is_available ?? 1);
    @endphp
    <div class="catalog-item"  data-name="{{ strtolower($p->name . ' ' . ($p->description ?? '')) }}">
      <div class="catalog-card card h-100" style="{{ !$isAvailable ? 'opacity:.75' : '' }};transition:transform .3s cubic-bezier(.34,1.56,.64,1),box-shadow .3s ease">

        {{-- Image --}}
        <div class="img-zoom-wrap position-relative overflow-hidden" style="border-radius:1.1rem 1.1rem 0 0;height:220px">
          <img src="{{ $p->image_path }}" alt="{{ $p->name }}"
               class="img-zoom-target"
               style="width:100%;height:100%;object-fit:cover;transition:transform .4s ease;cursor:zoom-in;user-select:none;-webkit-user-drag:none"
               onerror="this.src='https://placehold.co/400x220/fce4ec/e91e63?text=🎂'"
               onmouseover="this.style.transform='scale(1.12)'"
               onmouseout="this.style.transform='scale(1)'"
               data-src="{{ $p->image_path }}"
               onmousedown="startLongPress(event,this)" onmouseup="cancelLongPress()" onmouseleave="cancelLongPress()"
               ontouchstart="startLongPress(event,this)" ontouchend="cancelLongPress()" ontouchcancel="cancelLongPress()">
          {{-- Classification Badge --}}
          <span class="position-absolute top-0 start-0 m-2 badge"
                style="background:{{ $cls['bg'] }};color:{{ $cls['color'] }};font-size:.72rem">
            <i class="bi {{ $cls['icon'] }} me-1"></i>{{ $classification }}
          </span>
          {{-- Rating Badge --}}
          @if($avgRating)
          <span class="position-absolute top-0 end-0 m-2 badge"
                style="background:rgba(0,0,0,.55);color:#fbbf24;font-size:.72rem">
            ★ {{ number_format($avgRating,1) }}
          </span>
          @endif
          {{-- Not Available overlay --}}
          @if(!$isAvailable)
          <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
               style="background:rgba(0,0,0,.45);border-radius:1.1rem 1.1rem 0 0">
            <span class="badge bg-danger px-3 py-2" style="font-size:.9rem">
              <i class="bi bi-x-circle me-1"></i>Not Available
            </span>
          </div>
          @endif
        </div>

        <div class="card-body d-flex flex-column p-4">
          <h5 class="fw-bold mb-1">{{ $p->name }}</h5>
          @if(!empty($p->shop_name))
          <a href="/shop/{{ $p->shop_slug }}" target="_blank"
             class="d-inline-flex align-items-center gap-1 text-decoration-none mb-1"
             style="font-size:.72rem;color:var(--primary)" onclick="event.stopPropagation()">
            @if(!empty($p->shop_logo))
              <img src="{{ $p->shop_logo }}" style="width:14px;height:14px;border-radius:3px;object-fit:cover;flex-shrink:0">
            @else
              <i class="bi bi-shop" style="font-size:.65rem"></i>
            @endif
            {{ $p->shop_name }}
          </a>
          @endif
          @if($p->flavor)
            <div class="text-muted small mb-1"><i class="bi bi-droplet me-1"></i>{{ $p->flavor }}</div>
          @endif
          <p class="text-muted small flex-grow-1 mb-2">{{ Str::limit($p->description, 80) }}</p>

          {{-- Rating summary --}}
          @if($avgRating)
          <div class="d-flex align-items-center gap-1 mb-2">
            @for($i=1;$i<=5;$i++)
              <i class="bi bi-star{{ $i <= round($avgRating) ? '-fill' : '' }}" style="color:#fbbf24;font-size:.8rem"></i>
            @endfor
            <span class="text-muted small ms-1">{{ number_format($avgRating,1) }} ({{ $reviewCount }} review{{ $reviewCount != 1 ? 's' : '' }})</span>
          </div>
          @endif

          {{-- Sizes preview --}}
          @if(count($sizes) > 0)
          <div class="mb-3">
            <div class="text-muted small mb-1">Available Sizes:</div>
            <div class="d-flex flex-wrap gap-1">
              @foreach($sizes as $sz)
              <span class="badge" style="background:var(--primary-light);color:var(--primary);font-size:.72rem">
                {{ $sz->label }} — ₱{{ number_format($sz->price,2) }}
              </span>
              @endforeach
            </div>
          </div>
          @endif

          <div class="d-flex align-items-center justify-content-between">
            <div>
              <span class="fw-bold fs-5" style="color:var(--primary)">₱{{ number_format($p->price,2) }}</span>
              @if(count($sizes) > 0)
                <div class="text-muted" style="font-size:.72rem">Base price</div>
              @endif
            </div>
            @if($isAvailable)
            <button class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#detailModal{{ $p->id }}">
              <i class="bi bi-cart-plus me-1"></i>Order
            </button>
            @else
            <button class="btn btn-secondary btn-sm px-3" disabled>
              <i class="bi bi-x-circle me-1"></i>Not Available
            </button>
            @endif
          </div>
        </div>
      </div>
    </div>

    {{-- ── PRODUCT DETAIL MODAL ──────────────────────────────────────── --}}
    <div class="modal fade" id="detailModal{{ $p->id }}" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content border-0" style="border-radius:1.4rem;overflow:hidden">

          {{-- Sticky Header --}}
          <div class="modal-header border-0 pb-0 px-4 pt-3" style="background:#fff;position:sticky;top:0;z-index:10">
            <div class="d-flex align-items-center gap-2 flex-wrap flex-grow-1">
              <span class="badge" style="background:{{ $cls['bg'] }};color:{{ $cls['color'] }};font-size:.72rem">
                <i class="bi {{ $cls['icon'] }} me-1"></i>{{ $classification }}
              </span>
              @if($isAvailable)
                <span class="badge bg-success" style="font-size:.72rem"><i class="bi bi-check-circle me-1"></i>Available</span>
              @else
                <span class="badge bg-danger" style="font-size:.72rem"><i class="bi bi-x-circle me-1"></i>Not Available</span>
              @endif
              <span class="fw-bold ms-1" style="font-size:.95rem">{{ $p->name }}</span>
            </div>
            <button type="button" class="btn-close ms-2" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body p-0">

            {{-- Top: Image (full width, clickable to zoom) --}}
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
                @if($avgRating)
                <div class="d-flex align-items-center gap-2 mb-1">
                  <div class="d-flex gap-1">
                    @for($i=1;$i<=5;$i++)
                      <i class="bi bi-star{{ $i <= round($avgRating) ? '-fill' : '' }}" style="color:#fbbf24;font-size:.85rem"></i>
                    @endfor
                  </div>
                  <span class="fw-bold small" style="color:#fbbf24">{{ number_format($avgRating,1) }}</span>
                  <span class="text-muted small">({{ $reviewCount }} review{{ $reviewCount != 1 ? 's' : '' }})</span>
                </div>
                @endif
              </div>

              {{-- Divider --}}
              <hr class="my-2">

              {{-- Details Grid --}}
              <div class="row g-2 mb-3">
                <div class="col-6">
                  <div class="p-2 rounded-2" style="background:#f8f9fa">
                    <div class="text-muted" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em">Price</div>
                    <div class="fw-bold" style="color:var(--primary);font-size:1.15rem">₱{{ number_format($p->price,2) }}</div>
                    @if(count($sizes) > 0)
                      <div class="text-muted" style="font-size:.68rem">Base price</div>
                    @endif
                  </div>
                </div>
                <div class="col-6">
                  <div class="p-2 rounded-2" style="background:#f8f9fa">
                    <div class="text-muted" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em">Type</div>
                    <div class="fw-semibold small" style="color:{{ $cls['color'] }}">
                      <i class="bi {{ $cls['icon'] }} me-1"></i>{{ $classification }}
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
              @if(count($sizes) > 0)
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
              @if(!empty($p->shop_name))
              <a href="/shop/{{ $p->shop_slug }}" target="_blank"
                 class="d-flex align-items-center gap-2 mb-3 p-2 rounded-2 text-decoration-none"
                 style="background:#fff0f6;border:1px solid #fce7f3">
                @if(!empty($p->shop_logo))
                  <img src="{{ $p->shop_logo }}" style="width:32px;height:32px;border-radius:8px;object-fit:cover;flex-shrink:0">
                @else
                  <div style="width:32px;height:32px;border-radius:8px;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="bi bi-shop-window text-white" style="font-size:.8rem"></i>
                  </div>
                @endif
                <div class="flex-grow-1">
                  <div class="fw-semibold" style="font-size:.82rem;color:#9d174d">{{ $p->shop_name }}</div>
                  <div class="text-muted" style="font-size:.7rem">Tap to view shop &rarr;</div>
                </div>
                <i class="bi bi-chevron-right" style="color:#d1d5db;font-size:.75rem"></i>
              </a>
              @else
              <div class="d-flex align-items-center gap-2 mb-3 p-2 rounded-2" style="background:#fff0f6;border:1px solid #fce7f3">
                @if(!empty($shopSettings['logo_path']))
                  <img src="{{ $shopSettings['logo_path'] }}" style="width:28px;height:28px;border-radius:7px;object-fit:cover;flex-shrink:0">
                @else
                  <div style="width:28px;height:28px;border-radius:7px;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="bi bi-cake2-fill text-white" style="font-size:.75rem"></i>
                  </div>
                @endif
                <div>
                  <div class="fw-semibold" style="font-size:.8rem">{{ $shopSettings['site_title'] ?? 'Cake Shop' }}</div>
                  @if(!empty($shopSettings['tagline']))
                    <div class="text-muted" style="font-size:.7rem">{{ $shopSettings['tagline'] }}</div>
                  @endif
                </div>
              </div>
              @endif

              {{-- Divider --}}
              <hr class="my-3">

              {{-- Order Form --}}
              @if($isAvailable)
              <form action="{{ route('customer.catalog.order') }}" method="POST" onsubmit="return confirmOrder(this)">
                @csrf
                <input type="hidden" name="product_id" value="{{ $p->id }}">

                {{-- Size Selection --}}
                @if(count($sizes) > 0)
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

                {{-- ── Date Availability Check ─────────────────────────────── --}}
                <div class="mb-3 p-3 rounded-3" style="background:#fff0f5;border:1px solid #fce7f3">
                  <label class="form-label fw-semibold small mb-1">
                    <i class="bi bi-calendar3 me-1" style="color:var(--primary)"></i>Check Date Availability
                  </label>
                  <input type="date" class="form-control form-control-sm modal-avail-date"
                         id="availDate{{ $p->id }}"
                         min="{{ date('Y-m-d') }}"
                         data-product-id="{{ $p->id }}"
                         data-shop-id="{{ $p->shop_id }}"
                         data-max-per-day="{{ (int)($p->max_per_day ?? 0) }}"
                         onchange="checkModalAvailability('{{ $p->id }}')">
                  <div class="form-text"><i class="bi bi-info-circle me-1"></i>You can order for today or any future date.</div>
                  <div id="availResult{{ $p->id }}" class="mt-2" style="font-size:.82rem;min-height:20px"></div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                  <i class="bi bi-arrow-right-circle me-1"></i>Proceed to Checkout
                </button>
              </form>
              @else
              <div class="alert alert-danger text-center border-0">
                <i class="bi bi-x-circle me-2"></i>This cake is currently not available.
              </div>
              @endif

              {{-- Reviews --}}
              <div class="mt-4">
                <div class="fw-bold mb-3" style="border-bottom:2px solid var(--primary);padding-bottom:.5rem">
                  <i class="bi bi-star-fill me-1" style="color:#fbbf24"></i>
                  Customer Reviews
                  @if($reviewCount > 0)
                    <span class="text-muted fw-normal small ms-1">({{ $reviewCount }})</span>
                  @endif
                </div>

                @if(count($reviews) > 0)
                  <div>
                    @foreach($reviews as $rv)
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
                            <i class="bi bi-star{{ $i <= $rv->rating ? '-fill' : '' }}" style="color:#fbbf24;font-size:.78rem"></i>
                          @endfor
                        </div>
                        @if($rv->review)
                          <p class="small mb-1 text-muted">{{ $rv->review }}</p>
                        @endif
                        @if(!empty($rv->image_path))
                          <img src="{{ $rv->image_path }}" alt="Review photo"
                               style="width:80px;height:80px;object-fit:cover;border-radius:.5rem;cursor:pointer;border:2px solid #fce7f3"
                               onclick="catLbOpen('{{ $rv->image_path }}')">
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

            </div>{{-- end px-4 --}}
          </div>{{-- end modal-body --}}
        </div>{{-- end modal-content --}}
      </div>{{-- end modal-dialog --}}
    </div>{{-- end modal --}}

    @empty
    <div class="col-12 text-center py-5">
      <i class="bi bi-cake2" style="font-size:3rem;color:#ddd"></i>
      <p class="text-muted mt-3">No products available yet.</p>
    </div>
    @endforelse
  </div>
</div>

{{-- Lightbox --}}
<div id="lightboxOverlay"
     style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;z-index:9999;background:rgba(0,0,0,0);align-items:center;justify-content:center;transition:background .3s ease"
     onclick="catLbBgClick(event)">

  {{-- Image wrapper --}}
  <div id="lightboxWrapper"
       style="position:relative;transform:scale(0.3);opacity:0;transition:transform .4s cubic-bezier(.34,1.56,.64,1),opacity .3s ease">
    <img id="lightboxImg" src=""
         style="max-width:90vw;max-height:82vh;border-radius:1rem;object-fit:contain;display:block;cursor:default;user-select:none"
         onclick="event.stopPropagation()">

    {{-- Zoom controls --}}
    <div style="position:absolute;bottom:-56px;left:50%;transform:translateX(-50%);display:flex;gap:10px;align-items:center">
      <button onclick="event.stopPropagation();catLbZoom(-0.25)"
              style="background:rgba(255,255,255,.18);border:none;color:#fff;width:40px;height:40px;border-radius:50%;font-size:1.3rem;cursor:pointer">−</button>
      <span id="zoomLabel" style="color:#fff;font-size:.82rem;min-width:48px;text-align:center">100%</span>
      <button onclick="event.stopPropagation();catLbZoom(0.25)"
              style="background:rgba(255,255,255,.18);border:none;color:#fff;width:40px;height:40px;border-radius:50%;font-size:1.3rem;cursor:pointer">+</button>
      <button onclick="event.stopPropagation();catLbReset()"
              style="background:rgba(255,255,255,.18);border:none;color:#fff;padding:0 14px;height:40px;border-radius:20px;font-size:.78rem;cursor:pointer">Reset</button>
    </div>
  </div>

  {{-- Close button --}}
  <button id="lbCloseBtn"
          style="position:fixed;top:20px;right:24px;background:rgba(255,255,255,.18);border:none;color:#fff;width:44px;height:44px;border-radius:50%;font-size:1.2rem;cursor:pointer;opacity:0;transition:opacity .3s ease .15s"
          onclick="catLbClose()">
    <i class="bi bi-x-lg"></i>
  </button>

  {{-- Hint --}}
  <div style="position:fixed;bottom:20px;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.45);font-size:.75rem;opacity:0;transition:opacity .3s ease .2s" id="lbHint">
    Scroll to zoom &nbsp;·&nbsp; Click outside to close &nbsp;·&nbsp; ESC to exit
  </div>
</div>

<script>
function confirmOrder(form) {
  const openModal = document.querySelector('.modal.show');
  if (openModal && typeof bootstrap !== 'undefined') {
    const bsModal = bootstrap.Modal.getInstance(openModal);
    if (bsModal) {
      openModal.addEventListener('hidden.bs.modal', () => form.submit(), { once: true });
      bsModal.hide();
      return false;
    }
  }
  form.submit();
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

// ── Long press to open lightbox ──────────────────────────────
let lpTimer = null;
let lpFired  = false;

function startLongPress(e, img) {
  lpFired = false;
  lpTimer = setTimeout(() => {
    lpFired = true;
    catLbOpen(img.dataset.src || img.src);
  }, 600);
}
function cancelLongPress() {
  if (lpTimer) { clearTimeout(lpTimer); lpTimer = null; }
}

// ── Lightbox ─────────────────────────────────────────────────
let catLbScale = 1;

function catLbOpen(src) {
  const overlay  = document.getElementById('lightboxOverlay');
  const wrapper  = document.getElementById('lightboxWrapper');
  const img      = document.getElementById('lightboxImg');
  const closeBtn = document.getElementById('lbCloseBtn');
  const hint     = document.getElementById('lbHint');

  catLbScale = 1;
  document.getElementById('zoomLabel').textContent = '100%';
  img.style.transform = 'scale(1)';
  img.src = src;

  overlay.style.display = 'flex';
  document.body.style.overflow = 'hidden';

  // Animate in
  requestAnimationFrame(() => {
    overlay.style.background  = 'rgba(0,0,0,.92)';
    wrapper.style.transform   = 'scale(1)';
    wrapper.style.opacity     = '1';
    closeBtn.style.opacity    = '1';
    hint.style.opacity        = '1';
  });
}

function catLbClose() {
  const overlay  = document.getElementById('lightboxOverlay');
  const wrapper  = document.getElementById('lightboxWrapper');
  const closeBtn = document.getElementById('lbCloseBtn');
  const hint     = document.getElementById('lbHint');

  overlay.style.pointerEvents = 'none';
  overlay.style.background  = 'rgba(0,0,0,0)';
  wrapper.style.transform   = 'scale(0.3)';
  wrapper.style.opacity     = '0';
  closeBtn.style.opacity    = '0';
  hint.style.opacity        = '0';

  setTimeout(() => {
    overlay.style.display = 'none';
    overlay.style.pointerEvents = '';
    document.body.style.overflow = '';
  }, 380);
}

function catLbBgClick(e) {
  if (e.target === document.getElementById('lightboxOverlay')) catLbClose();
}

function catLbZoom(delta) {
  catLbScale = Math.min(3, Math.max(0.5, catLbScale + delta));
  document.getElementById('lightboxImg').style.transform = 'scale(' + catLbScale + ')';
  document.getElementById('zoomLabel').textContent = Math.round(catLbScale * 100) + '%';
}

function catLbReset() {
  catLbScale = 1;
  document.getElementById('lightboxImg').style.transform = 'scale(1)';
  document.getElementById('zoomLabel').textContent = '100%';
}

// Scroll to zoom
document.getElementById('lightboxOverlay').addEventListener('wheel', e => {
  if (document.getElementById('lightboxOverlay').style.display === 'flex') {
    e.preventDefault();
    catLbZoom(e.deltaY < 0 ? 0.15 : -0.15);
  }
}, { passive: false });

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') catLbClose();
  if (e.key === '+' || e.key === '=') catLbZoom(0.25);
  if (e.key === '-') catLbZoom(-0.25);
});
</script>

@push('scripts')
@endpush

<script>
function filterCatalog(){
  let q = document.getElementById('catalogSearch').value.toLowerCase();
  document.querySelectorAll('.catalog-item').forEach(el=>{
    let name = el.getAttribute('data-name');
    if(name.includes(q)){
      el.style.display='';
    }else{
      el.style.display='none';
    }
  });
}
</script>


<script>
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
      if (data.status === 'available') {
        resultEl.innerHTML = '<span class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>' + data.message + '</span>';
      } else if (data.status === 'almost') {
        resultEl.innerHTML = '<span class="text-warning fw-semibold"><i class="bi bi-exclamation-triangle-fill me-1"></i>' + data.message + '</span>';
      } else if (data.status === 'full') {
        resultEl.innerHTML = '<span class="text-danger fw-semibold"><i class="bi bi-x-circle-fill me-1"></i>' + data.message + '</span>';
      } else if (data.status === 'invalid') {
        resultEl.innerHTML = '<span class="text-danger small"><i class="bi bi-x-circle me-1"></i>' + data.message + '</span>';
      } else {
        resultEl.innerHTML = '<span class="text-muted small">Could not check availability.</span>';
      }
    })
    .catch(function() { resultEl.innerHTML = '<span class="text-muted small">Could not check.</span>'; });
}
</script>

@endsection
