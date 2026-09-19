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
    @keyframes cartDialogIn{from{transform:translateY(8px) scale(.98);opacity:.6}to{transform:none;opacity:1}}
    @media(max-width:575.98px){.cart-group-list::before{left:28px}.cart-group-dot{left:23px}.cart-item-img{width:68px!important;height:68px!important}}
  </style>
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
      @forelse(($groups ?? collect()) as $group)
        @php $checkoutFormId = 'guestCartCheckout' . $loop->iteration; @endphp
        <div class="cart-shop-card mb-3" data-cart-group="{{ $checkoutFormId }}">
          <div class="cart-shop-head p-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
              @if(!empty($group->shop_logo))
                <img src="{{ $group->shop_logo }}" alt="{{ $group->shop_name }}" style="width:38px;height:38px;border-radius:9px;object-fit:cover">
              @else
                <div style="width:38px;height:38px;border-radius:9px;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff"><i class="bi bi-shop"></i></div>
              @endif
              <div>
                <div class="fw-bold">{{ $group->shop_name }}</div>
                <div class="text-muted small">{{ $group->item_count }} cake option{{ $group->item_count > 1 ? 's' : '' }} connected for one pickup/delivery</div>
              </div>
            </div>
            <div class="form-check ms-sm-auto">
              <input class="form-check-input cart-select-all" type="checkbox" id="selectAll{{ $checkoutFormId }}" data-cart-group="{{ $checkoutFormId }}" checked>
              <label class="form-check-label small fw-semibold" for="selectAll{{ $checkoutFormId }}">Select all</label>
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
                <div class="d-flex gap-3 ms-3 align-items-start">
                  <input class="form-check-input cart-item-check mt-1" type="checkbox" name="selected_item_ids[]" value="{{ $item->id }}" form="{{ $checkoutFormId }}" data-cart-group="{{ $checkoutFormId }}" data-item-subtotal="{{ (float) $item->final_unit_price_snapshot * (int) $item->quantity }}" data-item-quantity="{{ (int) $item->quantity }}" checked aria-label="Select {{ $item->product_name }} for checkout">
                  <img class="cart-item-img" src="{{ $item->image_path ?: '/images/no-image.png' }}" alt="{{ $item->product_name }}" style="width:82px;height:82px;object-fit:cover;border-radius:8px">
                  <div class="flex-grow-1 min-w-0">
                    <div class="d-flex justify-content-between gap-2">
                      <div>
                        <div class="fw-bold">{{ $item->product_name }}</div>
                        <div class="text-muted small">Qty {{ $item->quantity }} @if($item->selected_size) &bull; {{ $item->selected_size }} @endif</div>
                      </div>
                      <div class="fw-bold text-nowrap" style="color:var(--primary)">PHP {{ number_format($item->final_unit_price_snapshot * $item->quantity, 2) }}</div>
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
                      </div>
                    @endif
                    <div class="d-flex flex-wrap gap-2 align-items-center mt-3">
                      @php
                        $cartQtyTracked = $item->available_quantity !== null;
                        $cartQtyMax = $cartQtyTracked ? max(1, (int) $item->available_quantity) : 99;
                      @endphp
                      <div class="cart-qty-wrap">
                        <div class="cart-qty-control" aria-label="Quantity for {{ $item->product_name }}">
                          <button type="button" class="cart-qty-btn" onclick="cartQtyStep('qtyForm{{ $item->id }}','removeForm{{ $item->id }}',-1, @js($item->product_name))" aria-label="Decrease quantity"><i class="bi bi-dash-lg"></i></button>
                          <span class="cart-qty-value" id="qtyValue{{ $item->id }}">{{ $item->quantity }}</span>
                          <button type="button" class="cart-qty-btn" onclick="cartQtyStep('qtyForm{{ $item->id }}','removeForm{{ $item->id }}',1, @js($item->product_name))" aria-label="Increase quantity"><i class="bi bi-plus-lg"></i></button>
                        </div>
                        @if($cartQtyTracked)
                          <div class="small text-muted"><i class="bi bi-box-seam me-1"></i>{{ $cartQtyMax }} available</div>
                        @endif
                      </div>
                      <form id="qtyForm{{ $item->id }}" action="{{ route('cart.items.update', $item->id) }}" method="POST" class="d-none">
                        @csrf
                        <input type="hidden" name="quantity" value="{{ $item->quantity }}" data-cart-qty-input data-current-quantity="{{ $item->quantity }}" data-max-quantity="{{ $cartQtyMax }}" data-stock-tracked="{{ $cartQtyTracked ? '1' : '0' }}">
                      </form>
                      <form id="removeForm{{ $item->id }}" action="{{ route('cart.items.remove', $item->id) }}" method="POST" class="d-none">@csrf</form>
                    </div>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
          <div class="p-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
              <div class="small text-muted"><i class="bi bi-link-45deg me-1"></i>Same seller items use one schedule and one pickup/delivery fee.</div>
              <div class="small fw-semibold mt-1" data-cart-selected-summary="{{ $checkoutFormId }}" style="color:var(--primary)"></div>
            </div>
            <form id="{{ $checkoutFormId }}" action="{{ route('cart.shops.checkout', $group->key) }}" method="POST">
              @csrf
              <button class="btn btn-primary btn-sm" data-cart-checkout-button="{{ $checkoutFormId }}"><i class="bi bi-bag-check me-1"></i>Checkout selected</button>
            </form>
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
  const max = parseInt(input.dataset.maxQuantity || '99', 10) || 99;
  const tracked = input.dataset.stockTracked === '1';

  if (delta < 0 && current <= 1) {
    cartQtyShowDialog('remove', 'Remove this cake?', 'Quantity is already 1. Do you want to remove ' + productName + ' from your cart?', removeFormId);
    return;
  }

  const next = current + delta;
  if (delta > 0 && tracked && next > max) {
    cartQtyShowDialog('info', 'Available stock reached', 'Only ' + max + ' ' + productName + ' available. Please reduce the quantity or choose another cake.');
    return;
  }

  input.value = Math.max(1, Math.min(next, max));
  form.submit();
}
document.addEventListener('DOMContentLoaded', function () {
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
    }
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
