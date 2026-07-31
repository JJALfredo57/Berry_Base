@extends('layouts.app')
@section('content')
<style>
/* ── Layout ── */
.thread-header{background:#fff;border:1px solid #e9ecef;border-radius:14px;padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:14px}
.thread-avatar{width:44px;height:44px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;flex-shrink:0}
.thread-card{border:1px solid #e9ecef;border-radius:14px;overflow:hidden;background:#fff}
/* ── Chat box ── */
.chat-box{height:460px;overflow-y:auto;padding:20px 16px;display:flex;flex-direction:column;gap:6px;background:#f8f9fa}
.msg-row{display:flex;gap:8px;align-items:flex-end}
.msg-row.mine{flex-direction:row-reverse}
.msg-av{width:28px;height:28px;border-radius:50%;background:#dee2e6;color:#666;display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:700;flex-shrink:0;text-transform:uppercase}
.msg-av.mine{background:var(--primary);color:#fff}
.msg-group{display:flex;flex-direction:column;max-width:72%;gap:2px}
.msg-group.mine{align-items:flex-end}
.bubble{padding:9px 13px;border-radius:16px;font-size:.875rem;line-height:1.5;word-break:break-word}
.bubble.theirs{background:#fff;color:#333;border-radius:4px 16px 16px 16px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.bubble.mine{background:var(--primary);color:#fff;border-radius:16px 4px 16px 16px}
.bubble-imgs{display:flex;flex-wrap:wrap;gap:4px;margin-top:6px}
.bubble-imgs img{border-radius:8px;object-fit:cover;cursor:zoom-in;max-width:180px;max-height:180px;display:block}
.bubble-imgs img.solo{max-width:220px;max-height:220px}
.bubble-time{font-size:.65rem;color:#adb5bd;padding:0 2px}
.bubble-time.mine{text-align:right}
.sender-lbl{font-size:.68rem;font-weight:600;color:#6c757d;padding:0 2px;margin-bottom:1px}
.sender-lbl.mine{text-align:right;color:var(--primary)}
/* ── Image preview cards ── */
.img-preview-bar{display:none;padding:10px 14px 6px;border-top:1px solid #f0f0f0;background:#fafafa;max-height:140px;overflow-y:auto}
.img-cards{display:flex;gap:8px;flex-wrap:wrap}
.img-card{position:relative;background:#fff;border:1.5px solid #e9ecef;border-radius:10px;overflow:hidden;width:96px;flex-shrink:0}
.img-card img{width:96px;height:72px;object-fit:cover;display:block}
.img-card-info{padding:3px 5px;font-size:.58rem;line-height:1.3;color:#6c757d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.img-card-size{font-size:.58rem;color:#16a34a;font-weight:600}
.img-card-rm{position:absolute;top:3px;right:3px;width:18px;height:18px;border-radius:50%;background:rgba(0,0,0,.55);border:none;color:#fff;font-size:.55rem;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;line-height:1}
.img-compressing{opacity:.5;pointer-events:none}
/* ── Compose ── */
.compose-wrap{padding:10px 14px 12px;border-top:1px solid #e9ecef;background:#fff}
.compose-row{display:flex;gap:8px;align-items:flex-end}
.compose-box{flex:1;border:1.5px solid #e9ecef;border-radius:14px;padding:9px 12px;font-size:.9rem;resize:none;outline:none;max-height:120px;min-height:40px;overflow-y:auto;line-height:1.4;color:#333;transition:border-color .2s;white-space:pre-wrap;word-break:break-word}
.compose-box:focus{border-color:var(--primary)}
.compose-box:empty::before{content:attr(data-placeholder);color:#adb5bd;pointer-events:none}
.attach-btn{width:38px;height:38px;border-radius:50%;border:1.5px solid #e9ecef;background:#fff;color:#6c757d;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1rem;transition:all .15s;flex-shrink:0}
.attach-btn:hover,.attach-btn.active{border-color:var(--primary);color:var(--primary);background:#fce7f3}
.send-btn{width:40px;height:40px;border-radius:50%;border:none;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.95rem;transition:opacity .15s;flex-shrink:0}
.send-btn:disabled{opacity:.45;cursor:not-allowed}
.status-badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.7rem;font-weight:600;background:#e9ecef;color:#555}
.thread-page{width:100%;max-width:1320px;margin:0 auto;padding:0 clamp(4px,1.5vw,18px)}
.thread-shell{display:grid;grid-template-columns:1fr;gap:14px}
.thread-topline{display:flex;align-items:center;gap:14px;min-width:0;flex:1}
.order-chip{display:inline-flex;align-items:center;gap:5px;padding:3px 8px;border-radius:999px;background:#f8fafc;color:#475569;border:1px solid #e2e8f0;font-size:.7rem;font-weight:700}
.order-context{background:#fff;border:1px solid #e9ecef;border-radius:14px;padding:14px;margin-bottom:16px}
.order-context-grid{display:grid;grid-template-columns:120px 1fr;gap:14px;align-items:start}
.order-photo{width:120px;aspect-ratio:1;border-radius:12px;object-fit:cover;border:1.5px solid #eef2f7;background:#f8fafc;cursor:zoom-in;display:block}
.order-photo-placeholder{width:120px;aspect-ratio:1;border-radius:12px;border:1.5px solid #eef2f7;background:#fff7ed;color:#c2410c;display:flex;align-items:center;justify-content:center;font-size:1.7rem}
.order-title-row{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px}
.order-product-title{font-size:1rem;font-weight:800;color:#111827;line-height:1.25;margin:0}
.order-subtitle{font-size:.78rem;color:#6b7280;margin-top:2px}
.order-total{font-size:1.08rem;font-weight:800;color:var(--primary);white-space:nowrap;text-align:right}
.detail-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px}
.detail-item{background:#f8fafc;border:1px solid #eef2f7;border-radius:10px;padding:8px 10px;min-width:0}
.detail-label{font-size:.64rem;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px}
.detail-value{font-size:.78rem;font-weight:700;color:#1f2937;line-height:1.35;word-break:break-word}
.detail-value.muted{font-weight:600;color:#64748b}
.order-notes{margin-top:10px;display:grid;grid-template-columns:1fr;gap:8px}
.note-panel{border-radius:10px;padding:9px 11px;font-size:.78rem;line-height:1.45}
.note-panel.warning{background:#fffbeb;color:#92400e;border:1px solid #fde68a}
.note-panel.privacy{background:#f8fafc;color:#475569;border:1px solid #e2e8f0;display:flex;gap:9px;align-items:flex-start}
.note-panel.custom{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.addon-list{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.addon-pill{font-size:.72rem;font-weight:700;color:#475569;background:#fff;border:1px solid #e2e8f0;border-radius:999px;padding:4px 8px}
@media (max-width: 767.98px){
  .thread-page{padding:0}
  .thread-header{align-items:flex-start;padding:12px;gap:10px;flex-wrap:wrap}
  .thread-topline{width:100%}
  .thread-avatar{width:38px;height:38px}
  .thread-order-meta{width:100%;text-align:left!important;padding-left:48px}
  .order-context{padding:12px}
  .order-context-grid{grid-template-columns:76px 1fr;gap:10px}
  .order-photo,.order-photo-placeholder{width:76px;border-radius:10px}
  .order-title-row{display:block}
  .order-total{text-align:left;margin-top:6px;font-size:.95rem}
  .detail-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}
  .detail-item{padding:7px 8px}
  .chat-box{height:calc(100vh - 430px);min-height:360px;padding:14px 10px}
  .msg-group{max-width:84%}
}
@media (max-width: 420px){
  .detail-grid{grid-template-columns:1fr}
  .thread-order-meta{padding-left:0}
  .order-context-grid{grid-template-columns:1fr}
  .order-photo,.order-photo-placeholder{width:100%;max-height:190px}
}
</style>

<div class="thread-page">
    @php
      $productTitle = $order->product_name ?? ($customOrder->cake_name ?? 'Custom Cake');
      $customRefs = [];
      if ($customOrder) {
          $decodedRefs = json_decode($customOrder->reference_images ?? '[]', true);
          $customRefs = is_array($decodedRefs) ? array_values(array_filter($decodedRefs)) : [];
      }
      $cakeImage = $customOrder ? ($customRefs[0] ?? null) : ($order->product_image_path ?? null);
      $displayStatus = $order->status === 'Pickup' ? 'Ready for Pickup' : $order->status;
      $schedule = $order->schedule_date
          ? \Carbon\Carbon::parse($order->schedule_date)->format('M d, Y') . ($order->schedule_time ? ' ' . $order->schedule_time : '')
          : 'Not set';
      $fulfillment = $order->fulfillment_type ?? 'Pickup';
      $paymentStatus = $order->payment_status ?? 'Unpaid';
      $paymentMethod = $order->payment_method ?? 'Not set';
      $isDelivery = strtolower((string) $fulfillment) === 'delivery';
      $privacyDetails = 'Customer information is shown only for fulfilling this order. Do not share contact details, address, cake photos, notes, or payment information outside BerryBase operations.';
    @endphp

    {{-- Header --}}
    <div class="thread-header">
      <a href="{{ route('seller.messages') }}" class="btn btn-sm btn-light" style="border-radius:10px"><i class="bi bi-arrow-left"></i></a>
      <div class="thread-topline">
        <div class="thread-avatar">{{ strtoupper(substr($order->fullname ?? 'C', 0, 1)) }}</div>
        <div class="flex-grow-1 min-width-0">
          <div class="fw-bold text-truncate" style="font-size:.95rem">{{ $order->fullname }}</div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="text-muted small text-truncate" style="max-width:100%">{{ $productTitle }}</span>
            <span class="status-badge" data-order-field="status">{{ $displayStatus }}</span>
          </div>
        </div>
      </div>
      <div class="text-muted text-end thread-order-meta" style="font-size:.75rem">
        <div>Order</div>
        <div class="fw-semibold text-break">#<span data-order-field="track_code">{{ $order->track_code ?? $order->id }}</span></div>
      </div>
    </div>

    {{-- Order context --}}
    <div class="order-context">
      <div class="order-context-grid">
        @if($cakeImage)
          <div data-lightbox-gallery="thread-order-{{ $order->id }}" style="display:flex;flex-direction:column;gap:8px;min-width:0">
            <img src="{{ $cakeImage }}" data-src="{{ $cakeImage }}" alt="{{ $productTitle }}" class="order-photo chat-img" onclick="openLightbox(this)" onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'order-photo-placeholder',innerHTML:'<i class=&quot;bi bi-image&quot;></i>'}))">
            @if(count($customRefs) > 1)
              <div style="display:flex;gap:6px;flex-wrap:wrap">
                @foreach($customRefs as $idx => $refImg)
                  @if($idx > 0)
                    <img src="{{ $refImg }}" data-src="{{ $refImg }}" class="chat-img" onclick="openLightbox(this)" alt="Reference photo"
                         style="width:44px;height:44px;border-radius:8px;object-fit:cover;cursor:zoom-in;border:1.5px solid #eef2f7">
                  @endif
                @endforeach
              </div>
            @endif
          </div>
        @else
          <div class="order-photo-placeholder"><i class="bi bi-image"></i></div>
        @endif

        <div class="min-width-0">
          <div class="order-title-row">
            <div class="min-width-0">
              <h2 class="order-product-title">{{ $productTitle }}</h2>
              <div class="order-subtitle">
                {{ $order->quantity ?? 1 }} item(s)
                @if($order->selected_size) &bull; {{ $order->selected_size }} @endif
                @if($customOrder && ($customOrder->flavor ?? null)) &bull; {{ $customOrder->flavor }} @endif
              </div>
            </div>
            <div class="order-total">&#8369;<span data-order-field="total_price_raw">{{ number_format((float)($order->total_price ?? 0), 2) }}</span></div>
          </div>

          <div class="detail-grid" id="orderLiveDetails">
            <div class="detail-item">
              <div class="detail-label">Schedule</div>
              <div class="detail-value" data-order-field="schedule">{{ $schedule }}</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Fulfillment</div>
              <div class="detail-value" data-order-field="fulfillment_type">{{ $fulfillment }}</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Payment</div>
              <div class="detail-value" data-order-field="payment_method">{{ $paymentMethod }}</div>
              <div class="detail-value muted" data-order-field="payment_status">{{ $paymentStatus }}</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Total</div>
              <div class="detail-value" data-order-field="total_price">&#8369;{{ number_format((float)($order->total_price ?? 0), 2) }}</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Contact</div>
              <div class="detail-value" data-order-field="phone">{{ $order->phone ?? 'Not provided' }}</div>
            </div>
            @if($isDelivery)
            <div class="detail-item" style="grid-column:1/-1">
              <div class="detail-label">Delivery Address</div>
              <div class="detail-value" data-order-field="delivery_address">{{ $order->delivery_address ?? 'Not provided' }}</div>
            </div>
            @endif
          </div>

          @if($orderAddons->count())
          <div class="addon-list">
            @foreach($orderAddons as $addon)
              <span class="addon-pill">
                <i class="bi bi-plus-circle me-1"></i>{{ $addon->addon_name ?? 'Add-on' }}
                @if((float)($addon->addon_price ?? 0) > 0)
                  · &#8369;{{ number_format((float)$addon->addon_price, 2) }}
                @endif
              </span>
            @endforeach
          </div>
          @endif

          <div class="order-notes">
            @if($order->special_notes || $order->custom_note || ($customOrder && (($customOrder->custom_note ?? null) || ($customOrder->description ?? null) || ($customOrder->dedication ?? null))))
              <div class="note-panel warning">
                <strong>Customer notes:</strong>
                {{ $order->special_notes ?? $order->custom_note ?? $customOrder->custom_note ?? $customOrder->description ?? $customOrder->dedication }}
              </div>
            @endif
            @if($customOrder && (($customOrder->size ?? null) || ($customOrder->layers ?? null) || ($customOrder->design_complexity ?? null) || ($customOrder->time_slot ?? null)))
              <div class="note-panel custom">
                <strong>Custom details:</strong>
                @if($customOrder->size ?? null) Size {{ $customOrder->size }}. @endif
                @if($customOrder->layers ?? null) Layers {{ $customOrder->layers }}. @endif
                @if($customOrder->design_complexity ?? null) Design {{ $customOrder->design_complexity }}. @endif
                @if($customOrder->time_slot ?? null) Preferred time {{ $customOrder->time_slot }}. @endif
              </div>
            @endif
            <div class="note-panel privacy">
              <i class="bi bi-shield-lock-fill" style="color:var(--primary);font-size:1rem;line-height:1.4"></i>
              <div><strong>Data Privacy Reminder:</strong> {{ $privacyDetails }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Chat card --}}
    <div class="thread-card">

      {{-- Messages --}}
      <div class="chat-box" id="chatBox">
        @forelse($messages as $m)
        @php
          $isMine = $m->sender_role === 'seller';
          $imgs = [];
          if (!empty($m->image_path)) {
            $d = json_decode($m->image_path, true);
            $imgs = is_array($d) ? $d : [$m->image_path];
          }
        @endphp
        <div class="msg-row {{ $isMine ? 'mine' : '' }}"
             data-msg-id="{{ $m->id }}"
             data-sender="{{ $m->sender_role }}"
             data-read="{{ $m->is_read ? '1' : '0' }}">
          <div class="msg-av {{ $isMine ? 'mine' : '' }}">{{ $isMine ? 'Me' : strtoupper(substr($order->fullname ?? 'C', 0, 1)) }}</div>
          <div class="msg-group {{ $isMine ? 'mine' : '' }}">
            <div class="sender-lbl {{ $isMine ? 'mine' : '' }}">{{ $isMine ? 'You' : ($order->fullname ?? 'Customer') }}</div>
            <div class="bubble {{ $isMine ? 'mine' : 'theirs' }}">
              @if($m->message)<div style="white-space:pre-wrap;word-break:break-word">{{ $m->message }}</div>@endif
              @if(count($imgs))
              <div class="bubble-imgs">
                @foreach($imgs as $src)
                <img src="{{ $src }}" class="{{ count($imgs) === 1 ? 'solo' : '' }}"
                     data-src="{{ $src }}" onclick="openLightbox(this)"
                     onerror="this.style.display='none'">
                @endforeach
              </div>
              @endif
            </div>
            <div class="bubble-time {{ $isMine ? 'mine' : '' }}">
              {{ \Carbon\Carbon::parse($m->created_at)->format('M d, g:i A') }}
              @if($isMine) <span style="opacity:.65">✓</span>@endif
            </div>
          </div>
        </div>
        @empty
        <div class="text-center py-5">
          <div style="font-size:2.5rem;margin-bottom:8px">💬</div>
          <div class="fw-semibold text-muted">No messages yet</div>
          <div class="small text-muted">Send a message to start the conversation.</div>
        </div>
        @endforelse
      </div>

      {{-- Image preview bar --}}
      <div class="img-preview-bar" id="imgPreviewBar">
        <div class="img-cards" id="imgCards"></div>
      </div>

      {{-- Compose --}}
      <div class="compose-wrap">
        <form id="threadForm">@csrf
          <div id="threadUploadSummary" style="display:flex;align-items:center;flex-wrap:wrap;margin-bottom:.35rem"></div>
          <div class="compose-row">
            <div contenteditable="true" id="msgInput" class="compose-box" data-placeholder="Type a message…"
                 onkeydown="handleEnter(event)"></div>
            <label class="attach-btn" id="attachBtn" title="Attach images">
              <i class="bi bi-paperclip"></i>
              <input type="file" id="imgFilePicker" accept="image/*" multiple hidden data-size-preview-target="threadUploadSummary" onchange="onFilePick(this)">
            </label>
            <button type="submit" class="send-btn" id="sendBtn" title="Send">
              <i class="bi bi-send-fill"></i>
            </button>
          </div>
        </form>
      </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
const cb      = document.getElementById('chatBox');
const csrf    = '{{ csrf_token() }}';
const sendUrl = '{{ route("seller.messages.send", $orderId) }}';
const orderDataUrl = '{{ route("seller.messages.thread_order_data", $orderId) }}';
if (cb) cb.scrollTop = cb.scrollHeight;

function setOrderField(name, value) {
  document.querySelectorAll(`[data-order-field="${name}"]`).forEach(el => {
    const next = String(value ?? '');
    if (el.textContent !== next) el.textContent = next;
  });
}

async function refreshOrderDetails() {
  try {
    const res = await fetch(orderDataUrl, { headers: { 'Accept': 'application/json' } });
    if (!res.ok) return;
    const data = await res.json();
    if (!data.ok || !data.order) return;
    const o = data.order;
    setOrderField('status', o.status);
    setOrderField('track_code', o.track_code);
    setOrderField('schedule', o.schedule);
    setOrderField('fulfillment_type', o.fulfillment_type);
    setOrderField('payment_method', o.payment_method);
    setOrderField('payment_status', o.payment_status);
    setOrderField('phone', o.phone);
    setOrderField('total_price', String.fromCharCode(8369) + o.total_price);
    setOrderField('total_price_raw', o.total_price);
    setOrderField('delivery_address', o.delivery_address);
  } catch (e) {}
}

refreshOrderDetails();
setInterval(refreshOrderDetails, 8000);

// ── Auto-grow compose box ─────────────────────────────────────────────────
const msgInput = document.getElementById('msgInput');
msgInput.addEventListener('input', () => {
  if (!msgInput.textContent.trim() && !msgInput.innerHTML.includes('<img')) {
    msgInput.innerHTML = '';
  }
});

function handleEnter(e) {
  if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
    e.preventDefault();
    document.getElementById('threadForm').dispatchEvent(new Event('submit'));
  }
}

// ── Mark messages as read ─────────────────────────────────────────────────
(function () {
  const markUrl = '{{ url("/seller/messages/mark-read-msg") }}';
  const obs = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const row = entry.target;
      if (row.dataset.read === '1' || row.dataset.sender === 'seller') return;
      row.dataset.read = '1'; obs.unobserve(row);
      fetch(markUrl + '/' + row.dataset.msgId, { method:'POST', headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/json'} })
        .then(() => {
          if (typeof refreshBubbleUnread === 'function') refreshBubbleUnread();
          if (typeof refreshSellerSidebarBadges === 'function') refreshSellerSidebarBadges();
        })
        .catch(()=>{});
    });
  }, { threshold: 0.5 });
  document.querySelectorAll('[data-msg-id]').forEach(el => obs.observe(el));
})();

// ── Image compression ─────────────────────────────────────────────────────
const MAX_PX  = 1200;
const QUALITY = 0.82;

async function compressImage(file) {
  return new Promise(resolve => {
    const img = new Image();
    const url = URL.createObjectURL(file);
    img.onload = () => {
      URL.revokeObjectURL(url);
      let w = img.naturalWidth, h = img.naturalHeight;
      const needsResize = w > MAX_PX || h > MAX_PX;
      if (needsResize) {
        const r = Math.min(MAX_PX / w, MAX_PX / h);
        w = Math.round(w * r); h = Math.round(h * r);
      }
      const canvas = document.createElement('canvas');
      canvas.width = w; canvas.height = h;
      canvas.getContext('2d').drawImage(img, 0, 0, w, h);
      canvas.toBlob(blob => {
        // If compressed is bigger than original (rare), use original
        const useOrig = blob.size >= file.size && !needsResize;
        resolve({
          file     : useOrig ? file : new File([blob], file.name.replace(/\.[^.]+$/, '.jpg'), { type:'image/jpeg' }),
          origSize : file.size,
          newSize  : useOrig ? file.size : blob.size,
          origW    : img.naturalWidth,
          origH    : img.naturalHeight,
          newW     : w, newH: h,
        });
      }, 'image/jpeg', QUALITY);
    };
    img.src = url;
  });
}

function fmtSize(bytes) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024*1024) return (bytes/1024).toFixed(0) + ' KB';
  return (bytes/1024/1024).toFixed(1) + ' MB';
}

// ── Image picker state ────────────────────────────────────────────────────
let pickedImages = []; // [{id, file, preview, origSize, newSize, origW, origH, newW, newH}]
let pickId = 0;

async function onFilePick(input) {
  const files = Array.from(input.files);
  input.value = '';
  if (!files.length) return;

  const bar   = document.getElementById('imgPreviewBar');
  const cards = document.getElementById('imgCards');
  bar.style.display = 'block';
  document.getElementById('attachBtn').classList.add('active');

  for (const file of files) {
    const id  = ++pickId;
    const tmp = { id, file: null, preview: null, compressing: true };
    pickedImages.push(tmp);

    // Placeholder card while compressing
    const card = document.createElement('div');
    card.className = 'img-card img-compressing';
    card.id = 'imgcard-' + id;
    card.innerHTML = `
      <div style="width:96px;height:72px;background:#f0f0f0;display:flex;align-items:center;justify-content:center">
        <span class="spinner-border spinner-border-sm text-secondary"></span>
      </div>
      <div class="img-card-info">Compressing…</div>`;
    cards.appendChild(card);

    // Compress
    const result = await compressImage(file);
    const pct    = Math.round((1 - result.newSize / result.origSize) * 100);
    const previewUrl = URL.createObjectURL(result.file);

    // Update state
    const entry = pickedImages.find(x => x.id === id);
    if (!entry) { URL.revokeObjectURL(previewUrl); continue; } // was removed
    Object.assign(entry, { file: result.file, preview: previewUrl, compressing: false,
      origSize: result.origSize, newSize: result.newSize,
      origW: result.origW, origH: result.origH, newW: result.newW, newH: result.newH });

    // Update card
    const sizeInfo = result.origSize !== result.newSize
      ? `${fmtSize(result.origSize)} → <span class="img-card-size">${fmtSize(result.newSize)}</span> <span style="color:#16a34a">(${pct}% smaller)</span>`
      : `<span class="img-card-size">${fmtSize(result.newSize)}</span>`;

    card.className = 'img-card';
    card.innerHTML = `
      <img src="${previewUrl}" onclick="openImgPreview('${previewUrl}')" title="${result.origW}×${result.origH} → ${result.newW}×${result.newH}">
      <div class="img-card-info">${sizeInfo}</div>
      <button class="img-card-rm" onclick="removeImage(${id})" title="Remove">✕</button>`;
  }
}

function removeImage(id) {
  const idx = pickedImages.findIndex(x => x.id === id);
  if (idx !== -1) {
    if (pickedImages[idx].preview) URL.revokeObjectURL(pickedImages[idx].preview);
    pickedImages.splice(idx, 1);
  }
  const card = document.getElementById('imgcard-' + id);
  if (card) card.remove();
  if (!pickedImages.length) clearImgPicker();
}

function clearImgPicker(revoke = true) {
  if (revoke) pickedImages.forEach(x => { if (x.preview) URL.revokeObjectURL(x.preview); });
  pickedImages = [];
  document.getElementById('imgCards').innerHTML = '';
  document.getElementById('imgPreviewBar').style.display = 'none';
  document.getElementById('attachBtn').classList.remove('active');
  const picker = document.getElementById('imgFilePicker');
  const summary = picker?.dataset.sizePreviewId ? document.getElementById(picker.dataset.sizePreviewId) : null;
  if (summary) summary.style.display = 'none';
}

function openImgPreview(src) {
  const el = document.createElement('img');
  el.src = src; el.dataset.src = src;
  openLightbox(el);
}

// ── Send ──────────────────────────────────────────────────────────────────
document.getElementById('threadForm').addEventListener('submit', async function (e) {
  e.preventDefault();
  const text     = msgInput.innerText.trim();
  const hasImgs  = pickedImages.filter(x => !x.compressing && x.file).length > 0;
  if (!text && !hasImgs) return;

  const sendBtn = document.getElementById('sendBtn');
  sendBtn.disabled = true;
  sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:14px;height:14px"></span>';

  const fd = new FormData();
  fd.append('_token', csrf);
  if (text) fd.append('message', text);
  pickedImages.filter(x => !x.compressing && x.file).forEach(x => fd.append('images[]', x.file));

  try {
    const res = await fetch(sendUrl, {
      method:'POST',
      body:fd,
      headers:{ 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' }
    });
    const ct  = res.headers.get('content-type') || '';
    if (!ct.includes('application/json')) {
      const raw = await res.text();
      console.error('HTTP ' + res.status, raw.substring(0, 600));
      alert('Message could not be sent. Please try again.');
      return;
    }
    const json = await res.json();
    if (json.ok) {
      appendMyBubble(text, pickedImages.filter(x => x.preview).map(x => x.preview));
      msgInput.innerHTML = '';
      clearImgPicker(false); // keep blob URLs alive so optimistic bubble images stay clickable
    } else {
      alert(json.error || 'Failed to send.');
    }
  } catch (err) {
    console.error('Send error:', err);
    alert('Error: ' + err.message);
  } finally {
    sendBtn.disabled = false;
    sendBtn.innerHTML = '<i class="bi bi-send-fill"></i>';
  }
});

function appendMyBubble(text, imgPreviews) {
  const now = new Date().toLocaleString('en-US', { month:'short', day:'numeric', hour:'numeric', minute:'2-digit', hour12:true });
  let imgHtml = '';
  if (imgPreviews.length) {
    imgHtml = '<div class="bubble-imgs">' +
      imgPreviews.map(src => `<img src="${src}" class="${imgPreviews.length===1?'solo':''}" onclick="openImgPreview('${src}')">`).join('') +
      '</div>';
  }
  const row = document.createElement('div');
  row.className = 'msg-row mine';
  row.innerHTML = `
    <div class="msg-av mine">Me</div>
    <div class="msg-group mine">
      <div class="sender-lbl mine">You</div>
      <div class="bubble mine">${text ? `<div style="white-space:pre-wrap">${escHtml(text)}</div>` : ''}${imgHtml}</div>
      <div class="bubble-time mine">${now} <span style="opacity:.65">✓</span></div>
    </div>`;
  cb.appendChild(row);
  cb.scrollTop = cb.scrollHeight;
}

function escHtml(s) {
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
}
</script>
@endpush
