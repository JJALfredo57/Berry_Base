@extends('layouts.app')
@section('content')
<div class="page-header">
  <h4 class="page-title"><i class="bi bi-palette me-2" style="color:var(--primary)"></i>Custom Orders</h4>
  <p class="page-subtitle">Review, approve, and manage customer custom cake requests.</p>
</div>

@if(session('msg'))<div class="alert alert-success border-0 py-2 mb-3">{{ session('msg') }}</div>@endif
@if(session('err'))<div class="alert alert-danger border-0 py-2 mb-3">{{ session('err') }}</div>@endif

@forelse($customOrders as $co)
@php
  $refImgs = [];
  if ($co->reference_images) {
    $dec = json_decode($co->reference_images, true);
    $refImgs = is_array($dec) ? $dec : [$co->reference_images];
  }
  $addons = $orderAddons[$co->order_id] ?? [];
  $breakdown = $co->price_breakdown ? json_decode($co->price_breakdown, true) : [];
  $estimatedPrice = (float)($co->estimated_price ?? 0);
  $statusColors = [
    'pending'  => ['bg'=>'#fff3cd','color'=>'#856404','label'=>'⏳ Pending Review'],
    'approved' => ['bg'=>'#d1fae5','color'=>'#065f46','label'=>'✅ Approved'],
    'rejected' => ['bg'=>'#fee2e2','color'=>'#991b1b','label'=>'❌ Rejected'],
  ];
  $sc = $statusColors[$co->review_status] ?? $statusColors['pending'];
  $orderStatus = $co->order_status ?? 'Pending Review';
  $isPending  = $co->review_status === 'pending';
  $isApproved = $co->review_status === 'approved';
  $priceConfirmed = $co->price_confirmed ?? null;
@endphp

<div class="card mb-4">
  <div class="card-body p-0">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between px-4 pt-3 pb-2 flex-wrap gap-2"
         style="border-bottom:1px solid #f0f0f0">
      <div class="d-flex align-items-center gap-3">
        {{-- Avatar --}}
        @if($co->profile_photo)
          <img src="{{ $co->profile_photo }}" style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid var(--primary)">
        @else
          <div style="width:44px;height:44px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1.1rem">
            {{ strtoupper(substr($co->fullname,0,1)) }}
          </div>
        @endif
        <div>
          <div class="fw-bold">{{ $co->fullname }}</div>
          <div class="text-muted small">@<span>{{ $co->username }}</span> &bull; {{ $co->phone }}</div>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="badge px-3 py-2" style="background:{{ $sc['bg'] }};color:{{ $sc['color'] }};font-size:.8rem">
          {{ $sc['label'] }}
        </span>
        <span class="badge bg-light text-dark px-3 py-2" style="font-size:.78rem">
          Custom Order #{{ $co->id }}
        </span>
        @if($co->order_id)
        <a href="{{ route('seller.messages.thread', $co->order_id) }}" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-chat-dots me-1"></i>Message
        </a>
        @endif
      </div>
    </div>

    <div class="p-4">
      <div class="row g-4">

        {{-- LEFT: Reference Images + Details --}}
        <div class="col-lg-5">

          {{-- Reference Images --}}
          @if(count($refImgs) > 0)
          <div class="mb-3">
            <div class="fw-semibold small mb-2" style="color:var(--primary)">
              <i class="bi bi-images me-1"></i>Reference Images ({{ count($refImgs) }})
            </div>
            <div class="d-flex flex-wrap gap-2">
              @foreach($refImgs as $img)
              <img src="{{ $img }}"
                   class="chat-img" data-src="{{ $img }}"
                   style="width:90px;height:90px;object-fit:cover;border-radius:.6rem;cursor:zoom-in;border:2px solid #fce7f3"
                   onclick="openLightbox(this)"
                   onerror="this.style.display='none'">
              @endforeach
            </div>
          </div>
          @else
          <div class="mb-3 p-3 rounded-2 text-muted small" style="background:#f8f9fa;border:1px dashed #dee2e6">
            <i class="bi bi-image me-1"></i>No reference images uploaded
          </div>
          @endif

          {{-- Cake Details --}}
          <div class="mb-3">
            <div class="fw-semibold small mb-2" style="color:var(--primary)"><i class="bi bi-cake2 me-1"></i>Cake Details</div>
            <div style="overflow-x:auto"><table class="table table-sm table-borderless mb-0" style="font-size:.85rem">
              <tr><td class="text-muted" style="width:40%">Cake Name</td><td class="fw-semibold">{{ $co->cake_name }}</td></tr>
              @if($co->flavor)<tr><td class="text-muted">Flavor</td><td>{{ $co->flavor }}</td></tr>@endif
              @if($co->size_label)<tr><td class="text-muted">Size</td><td>{{ $co->size_label }}</td></tr>@endif
              @if($co->layers)<tr><td class="text-muted">Layers</td><td>{{ $co->layers }}</td></tr>@endif
              @if($co->design_complexity)<tr><td class="text-muted">Complexity</td><td>{{ $co->design_complexity }}</td></tr>@endif
              @if($co->time_slot)<tr><td class="text-muted">Time Slot</td><td>{{ $co->time_slot }}</td></tr>@endif
              @if($co->dedication)<tr><td class="text-muted">Dedication</td><td><em>"{{ $co->dedication }}"</em></td></tr>@endif
              @if($co->schedule_date)<tr><td class="text-muted">Schedule</td><td>{{ \Carbon\Carbon::parse($co->schedule_date)->format('M d, Y') }} @if($co->schedule_time) {{ \Carbon\Carbon::parse($co->schedule_time)->format('g:i A') }}@endif</td></tr>@endif
              <tr><td class="text-muted">Fulfillment</td><td>{{ $co->fulfillment_type ?? 'Pickup' }}</td></tr>
            </table></div>
          </div>

          {{-- Add-ons --}}
          @if(count($addons) > 0)
          <div class="mb-3">
            <div class="fw-semibold small mb-1" style="color:var(--primary)"><i class="bi bi-gift me-1"></i>Add-ons</div>
            <div class="d-flex flex-wrap gap-1">
              @foreach($addons as $a)
              <span class="badge" style="background:#f0fdf4;color:#166534;font-size:.75rem">
                {{ $a->addon_name }}@if($a->addon_price > 0) +₱{{ number_format($a->addon_price,2) }}@endif
              </span>
              @endforeach
            </div>
          </div>
          @endif

          {{-- Customer Note --}}
          @if($co->custom_note)
          <div class="mb-3">
            <div class="fw-semibold small mb-1" style="color:var(--primary)"><i class="bi bi-chat-left-text me-1"></i>Customer Notes</div>
            <div class="p-2 rounded-2 small text-muted" style="background:#f8f9fa;border-left:3px solid var(--primary)">
              {{ $co->custom_note }}
            </div>
          </div>
          @endif

          {{-- Admin comment (if already reviewed) --}}
          @if($co->admin_comment)
          <div class="mb-3">
            <div class="fw-semibold small mb-1" style="color:{{ $isApproved ? '#065f46' : '#991b1b' }}">
              <i class="bi bi-person-check me-1"></i>Admin Comment
            </div>
            <div class="p-2 rounded-2 small" style="background:{{ $isApproved ? '#f0fdf4' : '#fef2f2' }};border-left:3px solid {{ $isApproved ? '#22c55e' : '#ef4444' }}">
              {{ $co->admin_comment }}
            </div>
          </div>
          @endif

          {{-- Price Breakdown (always visible) --}}
          @if($estimatedPrice > 0 && count($breakdown) > 0)
          <div class="mb-3">
            <div class="fw-semibold small mb-2" style="color:var(--primary)"><i class="bi bi-receipt me-1"></i>Estimated Price Breakdown</div>
            <div class="rounded-2 overflow-hidden" style="border:1px solid #e9ecef;font-size:.83rem">
              @if(isset($breakdown['base_price']))
              <div class="d-flex justify-content-between px-3 py-2" style="background:#f8f9fa">
                <span class="text-muted">Base Price</span>
                <span>₱{{ number_format($breakdown['base_price'],2) }}</span>
              </div>
              @endif
              @if(!empty($breakdown['size_surcharge']) && $breakdown['size_surcharge'] > 0)
              <div class="d-flex justify-content-between px-3 py-2" style="border-top:1px solid #f0f0f0">
                <span class="text-muted">Size ({{ $co->size_label }})</span>
                <span>+₱{{ number_format($breakdown['size_surcharge'],2) }}</span>
              </div>
              @endif
              @if(!empty($breakdown['complexity_surcharge']) && $breakdown['complexity_surcharge'] > 0)
              <div class="d-flex justify-content-between px-3 py-2" style="border-top:1px solid #f0f0f0">
                <span class="text-muted">Design ({{ $co->design_complexity }})</span>
                <span>+₱{{ number_format($breakdown['complexity_surcharge'],2) }}</span>
              </div>
              @endif
              @if(!empty($breakdown['addon_total']) && $breakdown['addon_total'] > 0)
              <div class="d-flex justify-content-between px-3 py-2" style="border-top:1px solid #f0f0f0">
                <span class="text-muted">Add-ons</span>
                <span>+₱{{ number_format($breakdown['addon_total'],2) }}</span>
              </div>
              @endif
              @if(!empty($breakdown['delivery_fee']) && $breakdown['delivery_fee'] > 0)
              <div class="d-flex justify-content-between px-3 py-2" style="border-top:1px solid #f0f0f0">
                <span class="text-muted">Delivery Fee</span>
                <span>+₱{{ number_format($breakdown['delivery_fee'],2) }}</span>
              </div>
              @endif
              <div class="d-flex justify-content-between px-3 py-2 fw-bold" style="border-top:2px solid var(--primary);background:#fff0f5;color:var(--primary)">
                <span>Estimated Total</span>
                <span>₱{{ number_format($estimatedPrice,2) }}</span>
              </div>
            </div>
          </div>
          @endif

          @if($co->admin_price)
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="text-muted small">Final Price Set:</span>
            <span class="fw-bold" style="color:var(--primary);font-size:1.05rem">₱{{ number_format($co->admin_price,2) }}</span>
            @if($priceConfirmed === 'pending')
              <span class="badge" style="background:#fff3cd;color:#856404;font-size:.72rem">⏳ Awaiting customer</span>
            @elseif($priceConfirmed === 'accepted')
              <span class="badge bg-success" style="font-size:.72rem">✅ Customer accepted</span>
            @elseif($priceConfirmed === 'cancelled')
              <span class="badge bg-danger" style="font-size:.72rem">❌ Customer cancelled</span>
            @endif
          </div>
          @endif

        </div>

        {{-- RIGHT: Actions --}}
        <div class="col-lg-7">

          {{-- PENDING — Approve / Reject forms --}}
          @if($isPending)
          <div class="d-flex flex-column gap-3">

            {{-- Approve Form --}}
            <div class="p-3 rounded-3" style="border:2px solid #22c55e;background:#f0fdf4">
              <div class="fw-semibold mb-3" style="color:#16a34a"><i class="bi bi-check-circle me-1"></i>Approve this Order</div>
              <form action="{{ route('seller.custom_orders.approve', $co->id) }}" method="POST">
                @csrf
                <div class="mb-3">
                  <label class="form-label fw-semibold small">
                    Final Price <span class="text-danger">*</span>
                    @if($estimatedPrice > 0)
                      <span class="text-muted fw-normal">(Estimated: ₱{{ number_format($estimatedPrice,2) }})</span>
                    @endif
                  </label>
                  <div class="input-group">
                    <span class="input-group-text">₱</span>
                    <input type="number" class="form-control fw-bold" name="admin_price"
                           id="adminPriceInput{{ $co->id }}"
                           step="0.01" min="0"
                           value="{{ $estimatedPrice > 0 ? $estimatedPrice : '' }}"
                           placeholder="e.g. 2500.00" required
                           oninput="checkPriceChange({{ $co->id }}, {{ $estimatedPrice }})">
                  </div>
                  <div id="priceChangeNote{{ $co->id }}" class="form-text" style="display:none;color:#d97706">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Price differs from estimate — customer will need to <strong>confirm</strong> before order proceeds.
                  </div>
                  <div id="priceSameNote{{ $co->id }}" class="form-text" style="color:#16a34a">
                    <i class="bi bi-check-circle me-1"></i>
                    Same as estimate — customer will still need to pay deposit to confirm.
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold small">Message to Customer <span class="text-muted fw-normal">(optional)</span></label>
                  <textarea class="form-control" name="admin_comment" rows="2"
                            placeholder="e.g. Your cake will be ready by the scheduled date!"></textarea>
                </div>
                <button type="submit" class="btn btn-success w-100">
                  <i class="bi bi-check-circle me-1"></i>Approve & Set Price
                </button>
              </form>
            </div>

            {{-- Reject Form --}}
            <div class="p-3 rounded-3" style="border:2px solid #ef4444;background:#fef2f2">
              <div class="fw-semibold mb-3" style="color:#dc2626"><i class="bi bi-x-circle me-1"></i>Reject this Order</div>
              <form action="{{ route('seller.custom_orders.reject', $co->id) }}" method="POST">
                @csrf
                <div class="mb-3">
                  <label class="form-label fw-semibold small">Reason for Rejection <span class="text-danger">*</span></label>
                  <textarea class="form-control" name="reason" rows="3"
                            placeholder="e.g. Design too complex for current availability. Please simplify the request." required></textarea>
                </div>
                <button type="submit" class="btn btn-danger w-100"
                        data-cs-confirm="Reject this custom order?"
                        data-cs-title="Reject Custom Order"
                        data-cs-ok="Reject"
                        data-cs-ok-color="#dc2626"
                        data-cs-icon="bi-x-octagon"
                        data-cs-icon-bg="#fee2e2"
                        data-cs-icon-color="#dc2626">
                  <i class="bi bi-x-circle me-1"></i>Reject Order
                </button>
              </form>
            </div>

          </div>
          @endif

          {{-- APPROVED — Progress Photo Form (if order is Preparing or Confirmed) --}}
          @if($isApproved && $co->order_id && in_array($orderStatus, ['Confirmed','Preparing','Out for Delivery']))
          <div class="p-3 rounded-3 mb-3" style="border:2px solid var(--primary);background:#fff0f6">
            <div class="fw-semibold mb-3" style="color:var(--primary)">
              <i class="bi bi-camera me-1"></i>Send Progress Photo to Customer
            </div>
            <form action="{{ route('seller.custom_orders.progress', $co->id) }}" method="POST" enctype="multipart/form-data">
              @csrf

              {{-- Image preview --}}
              <div class="mb-3">
                <label class="form-label fw-semibold small">Progress Photo</label>
                <div id="progressPreviewWrap{{ $co->id }}" style="display:none;margin-bottom:8px">
                  <img id="progressPreview{{ $co->id }}" style="max-height:160px;border-radius:.6rem;border:2px solid var(--primary)">
                </div>
                <input type="file" class="form-control" name="progress_image" accept="image/*"
                       onchange="previewProgress(this, {{ $co->id }})">
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold small">Message</label>
                <textarea class="form-control" name="progress_message" rows="2"
                          placeholder="e.g. Here's a sneak peek of your cake! 🎂"></textarea>
              </div>

              <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary"
                        onclick="this.closest('form').reset();document.getElementById('progressPreviewWrap{{ $co->id }}').style.display='none'">
                  <i class="bi bi-x-circle me-1"></i>Cancel
                </button>
                <button type="submit" class="btn btn-primary flex-grow-1">
                  <i class="bi bi-send me-1"></i>Send to Customer
                </button>
              </div>
            </form>
          </div>
          @endif

          {{-- Progress photo already sent --}}
          @if($co->progress_image || $co->progress_message)
          <div class="p-3 rounded-3" style="background:#f8f9fa;border:1px solid #e9ecef">
            <div class="fw-semibold small mb-2" style="color:var(--primary)">
              <i class="bi bi-camera me-1"></i>Last Progress Update
              @if($co->progress_sent_at)
                <span class="text-muted fw-normal ms-1" style="font-size:.72rem">
                  {{ \Carbon\Carbon::parse($co->progress_sent_at)->diffForHumans() }}
                </span>
              @endif
            </div>
            @if($co->progress_image)
              <img src="{{ $co->progress_image }}"
                   class="chat-img" data-src="{{ $co->progress_image }}"
                   style="max-height:140px;border-radius:.6rem;cursor:zoom-in;margin-bottom:8px;display:block"
                   onclick="openLightbox(this)">
            @endif
            @if($co->progress_message)
              <div class="small text-muted">{{ str_replace('[custom_order:'.$co->id.']', '', $co->progress_message) }}</div>
            @endif
          </div>
          @endif

          {{-- Already reviewed summary (approved/rejected) --}}
          @if(!$isPending && $co->reviewed_at)
          <div class="text-muted small mt-3">
            <i class="bi bi-clock me-1"></i>Reviewed {{ \Carbon\Carbon::parse($co->reviewed_at)->diffForHumans() }}
          </div>
          @endif

        </div>
      </div>
    </div>

  </div>
</div>
@empty
<div class="card text-center py-5">
  <i class="bi bi-palette" style="font-size:3rem;color:#ddd"></i>
  <p class="text-muted mt-3">No custom orders yet.</p>
</div>
@endforelse

@push('scripts')
<script>
// Make these global so inline onclick= can call them
window.previewProgress = function(input, id) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      document.getElementById('progressPreview' + id).src = e.target.result;
      document.getElementById('progressPreviewWrap' + id).style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
  }
};

window.checkPriceChange = function(id, estimated) {
  const input    = document.getElementById('adminPriceInput' + id);
  const noteWarn = document.getElementById('priceChangeNote' + id);
  const noteOk   = document.getElementById('priceSameNote' + id);
  if (!input || !noteWarn || !noteOk) return;
  const val  = parseFloat(input.value) || 0;
  const diff = Math.abs(val - estimated);
  if (estimated > 0 && diff > 0.01) {
    noteWarn.style.display = 'block';
    noteOk.style.display   = 'none';
  } else {
    noteWarn.style.display = 'none';
    noteOk.style.display   = estimated > 0 ? 'block' : 'none';
  }
};

// Run on page load for all price inputs
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('[id^="adminPriceInput"]').forEach(function(input) {
    const id        = input.id.replace('adminPriceInput', '');
    const estimated = parseFloat(input.dataset.estimated || input.value) || 0;
    window.checkPriceChange(id, estimated);
  });
});
</script>
@endpush
@endsection
