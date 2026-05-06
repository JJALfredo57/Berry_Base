<div class="card mb-3">
  <div class="card-body p-4">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <div>
        <h6 class="fw-bold mb-0">Order #{{ $t->order_id }} — {{ $t->product_name }}</h6>
        <div class="text-muted small">
          {{ $t->fullname }}
          &bull;
          <span class="badge {{ $isDelivery ? 'bg-info text-dark' : '' }}" style="{{ !$isDelivery ? 'background:#ede9fe;color:#5b21b6' : '' }}">
            {{ $isDelivery ? '🚴 Delivery' : '🏪 Pickup' }}
          </span>
          &bull; {{ \Carbon\Carbon::parse($t->sent_at)->format('M d, Y g:i A') }}
        </div>
        {{-- Rider assignment status --}}
        @if($isDelivery)
        <div class="mt-1">
          @if($t->rider_id)
            @php $assignedRider = $riders->firstWhere('id', $t->rider_id); @endphp
            <span class="badge bg-success" style="font-size:clamp(.66rem,1.3vw,.7rem)">
              <i class="bi bi-bicycle me-1"></i>Rider: {{ $assignedRider->name ?? 'Assigned' }}
            </span>
          @else
            <span class="badge bg-warning text-dark" style="font-size:clamp(.66rem,1.3vw,.7rem)">
              <i class="bi bi-exclamation-triangle me-1"></i>No rider assigned yet
            </span>
          @endif
        </div>
        @endif
      </div>

      <div class="d-flex gap-2 align-items-center flex-wrap">
        <span class="badge {{ $t->ticket_status==='done' ? 'bg-success' : ($t->ticket_status==='in_progress' ? 'bg-warning text-dark' : 'bg-secondary') }}">
          {{ $t->ticket_status === 'in_progress' ? '🍳 In Progress' : ($t->ticket_status === 'done' ? '✅ Done' : '⏳ Pending') }}
        </span>

        @php
          $kitchenNext = ['pending'=>'in_progress','in_progress'=>'done','done'=>null];
          $nextKitchen = $kitchenNext[$t->ticket_status] ?? null;
          $kitchenBtnLabel = ['in_progress'=>'🍳 Start Preparing','done'=>'✅ Mark Done'];
          $kitchenBtnClass = ['in_progress'=>'btn-warning text-dark','done'=>'btn-success'];
        @endphp

        @if($nextKitchen)
          @if($nextKitchen === 'done' && $isDelivery && !$t->rider_id)
          {{-- BLOCK: Delivery + no rider → show assign modal button --}}
          <button type="button" class="btn btn-sm btn-success"
                  data-bs-toggle="modal" data-bs-target="#assignRiderModal{{ $t->order_id }}">
            ✅ Mark Done
          </button>
          @else
          {{-- Normal: Pickup OR Delivery with rider assigned --}}
          <form action="{{ route('seller.kitchen.update', $t->id) }}" method="POST" class="d-inline">
            @csrf
            <input type="hidden" name="status" value="{{ $nextKitchen }}">
            <button type="submit" class="btn btn-sm {{ $kitchenBtnClass[$nextKitchen] ?? 'btn-primary' }}"
                    data-cs-confirm="{{ $kitchenBtnLabel[$nextKitchen] }} for Order #{{ $t->order_id }}?"
                    data-cs-title="Kitchen Update"
                    data-cs-ok="{{ $kitchenBtnLabel[$nextKitchen] }}"
                    data-cs-icon="bi-fire"
                    data-cs-icon-bg="#fef3c7"
                    data-cs-icon-color="#d97706">
              {{ $kitchenBtnLabel[$nextKitchen] }}
            </button>
          </form>
          @endif
        @else
        <span class="badge bg-success px-3 py-2"><i class="bi bi-check-all me-1"></i>Completed</span>
        @if($isDelivery && $t->rider_id)
        <form action="{{ route('seller.kitchen.resend_sms', $t->order_id) }}" method="POST" class="d-inline">
          @csrf
          <button type="submit" class="btn btn-sm fw-semibold"
                  style="background:#eff6ff;color:#1d4ed8;border:1.5px solid #bfdbfe;border-radius:.6rem;font-size:.8rem;white-space:nowrap"
                  data-cs-confirm="Resend rider notification for Order #{{ $t->order_id }}?"
                  data-cs-title="Resend to Rider"
                  data-cs-ok="Resend"
                  data-cs-icon="bi-send-check"
                  data-cs-icon-bg="#eff6ff"
                  data-cs-icon-color="#1d4ed8">
            <i class="bi bi-send-check me-1"></i>Resend to Rider
          </button>
        </form>
        @endif
        @endif
      </div>
    </div>

    {{-- ── IMAGES ────────────────────────────────────────────────── --}}
    @php
      $refImgs = $customImages[$t->order_id] ?? [];
      $isCustom = count($refImgs) > 0;
    @endphp

    <div class="mb-3">
      <div class="d-flex flex-wrap gap-2 align-items-start mb-2">

        {{-- Product image — hide if custom order (shows default placeholder) --}}
        @if($t->product_image && !$isCustom)
        <div>
          <div class="text-muted small fw-semibold mb-1">
            <i class="bi bi-image me-1"></i>Product Photo
          </div>
          <img src="{{ $t->product_image }}"
               alt="{{ $t->product_name }}"
               onclick="kitchenImgOpen(this.src, '{{ addslashes($t->product_name) }}')"
               onerror="this.parentElement.style.display='none'"
               style="width:90px;height:90px;object-fit:cover;border-radius:.8rem;cursor:zoom-in;border:2px solid #f3f4f6;transition:transform .2s ease;box-shadow:0 2px 8px rgba(0,0,0,.1)"
               onmouseover="this.style.transform='scale(1.06)'"
               onmouseout="this.style.transform='scale(1)'">
        </div>
        @endif

        {{-- Custom order reference images --}}
        @if($isCustom)
        <div>
          <div class="text-muted small fw-semibold mb-1">
            <i class="bi bi-images me-1" style="color:var(--primary)"></i>
            Customer Reference Photos ({{ count($refImgs) }})
          </div>
          <div class="d-flex flex-wrap gap-2">
            @foreach($refImgs as $idx => $img)
            <img src="{{ $img }}"
                 alt="Reference {{ $idx + 1 }}"
                 onclick="kitchenImgOpen('{{ $img }}', 'Reference Photo {{ $idx + 1 }}')"
                 onerror="this.style.display='none'"
                 style="width:90px;height:90px;object-fit:cover;border-radius:.8rem;cursor:zoom-in;border:2px solid #fce7f3;transition:transform .2s ease;box-shadow:0 2px 8px rgba(233,30,99,.12)"
                 onmouseover="this.style.transform='scale(1.06)'"
                 onmouseout="this.style.transform='scale(1)'">
            @endforeach
          </div>
        </div>
        @elseif(!$t->product_image)
        {{-- No image at all --}}
        <div class="text-muted small"><i class="bi bi-image me-1"></i>No photo available</div>
        @endif

      </div>
    </div>

    {{-- SMS failed warning --}}
    @if($t->ticket_status === 'done' && $isDelivery && $t->rider_id && isset($t->rider_sms_sent) && (int)$t->rider_sms_sent === 0)
    <div class="d-flex align-items-center gap-2 rounded-3 px-3 py-2 mb-3"
         style="background:#fff7ed;border:1.5px solid #fed7aa">
      <i class="bi bi-exclamation-triangle-fill flex-shrink-0" style="color:#9a3412"></i>
      <span style="color:#9a3412;font-size:.85rem">
        <strong>Rider was not notified</strong> — Initial SMS failed. Use <em>Resend to Rider</em> above to retry.
      </span>
    </div>
    @endif

    @if(!empty($t->custom_note))
    <div class="mb-3 p-3 rounded-3" style="background:#fff7ed;border:1.5px solid #fed7aa">
      <div class="text-muted small fw-semibold mb-1">
        <i class="bi bi-chat-left-quote me-1" style="color:#d97706"></i>Cake Message / Product Note
      </div>
      <div class="fw-semibold" style="color:#7c2d12;white-space:pre-wrap">{{ $t->custom_note }}</div>
    </div>
    @endif

    <pre class="mb-0 p-3 rounded small" style="background:#f8f9fa;white-space:pre-wrap;font-family:'Courier New',monospace">{{ $t->instructions }}</pre>
  </div>
</div>

{{-- Assign Rider Modal (only for Delivery + no rider + Mark Done) --}}
@if($isDelivery && !$t->rider_id)
<div class="modal fade" id="assignRiderModal{{ $t->order_id }}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
    <div class="modal-content border-0" style="border-radius:1.2rem;overflow:hidden">
      <div class="modal-header border-0 pt-4 px-4">
        <h5 class="modal-title fw-bold">🚴 Assign Rider — Order #{{ $t->order_id }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body px-4 pt-0 pb-3">
        <div class="alert border-0 py-2 mb-3" style="background:#fef9c3">
          <i class="bi bi-exclamation-triangle me-1" style="color:#854d0e"></i>
          <span style="color:#854d0e;font-size:clamp(.78rem,1.6vw,.85rem)">
            This is a <strong>Delivery order</strong>. Please assign a rider before marking as done.
          </span>
        </div>
        <form action="{{ route('seller.kitchen.assign_rider', $t->order_id) }}" method="POST">
          @csrf
          <div class="mb-3">
            <label class="form-label fw-semibold small">Select Rider <span class="text-danger">*</span></label>
            <select class="form-select" name="rider_id" required>
              <option value="">— Choose a rider —</option>
              @foreach($riders as $r)
              <option value="{{ $r->id }}">{{ $r->name }}{{ $r->nickname ? ' ('.$r->nickname.')' : '' }} — {{ $r->vehicle_type }}</option>
              @endforeach
            </select>
            @if($riders->isEmpty())
            <div class="form-text text-danger">No active riders. <a href="{{ route('seller.riders') }}">Add riders here →</a></div>
            @endif
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success flex-fill fw-semibold">
              <i class="bi bi-bicycle me-1"></i>Assign & Mark Done
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endif
