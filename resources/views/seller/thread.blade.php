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
.bubble.has-media{display:inline-flex;flex-direction:column;width:fit-content;max-width:min(328px,100%)}
.bubble.theirs{background:#fff;color:#333;border-radius:4px 16px 16px 16px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.bubble.mine{background:var(--primary);color:#fff;border-radius:16px 4px 16px 16px}
.bubble.image-only{padding:3px;background:transparent;color:inherit;box-shadow:none;border-radius:10px}
.bubble.mine.image-only{outline:2px solid color-mix(in srgb,var(--primary) 38%,transparent)}
.bubble.theirs.image-only{outline:1px solid #e5e7eb}
.bubble.image-only .bubble-imgs{margin-top:0}
.bubble-imgs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px;margin-top:6px;width:min(304px,100%);max-width:100%}
.bubble-imgs.img-count-1{grid-template-columns:1fr;width:min(240px,100%)}
.bubble-imgs.img-count-2{grid-template-columns:repeat(2,minmax(0,1fr));width:min(304px,100%)}
.bubble-img-tile,.bubble-img-more{width:100%;aspect-ratio:1/1;border:0;border-radius:8px;cursor:zoom-in;display:block;min-width:0;overflow:hidden;padding:0;touch-action:manipulation;-webkit-tap-highlight-color:transparent}
.bubble-img-tile{background:transparent}
.bubble-img-tile img{width:100%;height:100%;object-fit:cover;display:block;cursor:zoom-in}
.bubble-imgs.img-count-1 .bubble-img-tile{aspect-ratio:4/3}
.bubble-img-more{background:#111827;color:#fff;position:relative;font-size:1.35rem;font-weight:900;display:flex;align-items:center;justify-content:center}
.bubble-img-more:before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 30% 20%,rgba(255,255,255,.16),transparent 34%),linear-gradient(135deg,rgba(17,24,39,.94),rgba(31,41,55,.98))}
.bubble-img-more span{position:relative;z-index:1;text-shadow:0 2px 10px rgba(0,0,0,.35)}
.bubble-time{font-size:.65rem;color:#adb5bd;padding:0 2px}
.bubble-time.mine{text-align:right}
.delivery-state{font-weight:700;color:var(--primary)}
.sender-lbl{font-size:.68rem;font-weight:600;color:#6c757d;padding:0 2px;margin-bottom:1px}
.sender-lbl.mine{text-align:right;color:var(--primary)}
/* ── Image preview cards ── */
.img-preview-bar{display:none;padding:10px 14px 6px;border-top:1px solid #f0f0f0;background:#fafafa;max-height:126px;overflow:hidden}
.img-cards{display:flex;gap:8px;flex-wrap:nowrap;overflow-x:auto;overflow-y:hidden;padding-bottom:4px;scrollbar-width:thin}
.img-card{position:relative;background:#fff;border:1.5px solid #e9ecef;border-radius:10px;overflow:hidden;width:88px;flex:0 0 88px}
.img-card img{width:88px;height:64px;object-fit:cover;display:block}
.img-card-info{padding:3px 5px;font-size:.56rem;line-height:1.3;color:#6c757d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
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
#threadUploadSummary{margin-bottom:.4rem}
#threadUploadSummary .cs-upload-summary{display:flex!important;flex-wrap:nowrap!important;gap:.38rem;width:100%;overflow-x:auto;overflow-y:hidden;margin-top:0;padding-bottom:2px;scrollbar-width:thin}
#threadUploadSummary .cs-upload-pill{width:auto!important;flex:0 0 auto;max-width:min(260px,78vw);border-radius:999px!important;white-space:nowrap}
#threadUploadSummary .cs-upload-pill span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#threadUploadSummary .cs-upload-arrow{display:none}
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
  .bubble-imgs,.bubble-imgs.img-count-2{width:min(244px,100%)}
  .bubble-imgs.img-count-1{width:min(220px,100%)}
  .img-preview-bar{max-height:98px;padding:8px 12px 4px}
  .img-card{width:78px;flex-basis:78px}
  .img-card img{width:78px;height:58px}
}
@media (max-width: 420px){
  .detail-grid{grid-template-columns:1fr}
  .thread-order-meta{padding-left:0}
  .order-context-grid{grid-template-columns:1fr}
  .order-photo,.order-photo-placeholder{width:100%;max-height:190px}
}
</style>
@include('partials.message_interactions')

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
          $hasText = trim((string) $m->message) !== '';
          $isImageOnly = !$hasText && count($imgs) > 0;
        @endphp
        <div class="msg-row {{ $isMine ? 'mine' : '' }}"
             data-msg-id="{{ $m->id }}"
             data-sender="{{ $m->sender_role }}"
             data-read="{{ $m->is_read ? '1' : '0' }}"
             data-reply-sender="{{ $isMine ? 'You' : ($order->fullname ?? 'Customer') }}"
             data-reply-snippet="{{ trim((string) $m->message) !== '' ? Str::limit($m->message, 80) : (count($imgs) ? 'Photo message' : 'Message') }}">
          <div class="msg-av {{ $isMine ? 'mine' : '' }}">{{ $isMine ? 'Me' : strtoupper(substr($order->fullname ?? 'C', 0, 1)) }}</div>
          <div class="msg-group {{ $isMine ? 'mine' : '' }}">
            <div class="sender-lbl {{ $isMine ? 'mine' : '' }}">{{ $isMine ? 'You' : ($order->fullname ?? 'Customer') }}</div>
            <div class="bubble {{ $isMine ? 'mine' : 'theirs' }} {{ count($imgs) ? 'has-media' : '' }} {{ $isImageOnly ? 'image-only' : '' }}">
              @if($m->reply_to)
                <div class="msg-reply-quote {{ $isMine ? 'mine' : 'theirs' }}">
                  <div class="msg-reply-name">{{ $m->reply_to['label'] ?? 'Message' }}</div>
                  <div class="msg-reply-text">{{ $m->reply_to['snippet'] ?? 'Message' }}</div>
                </div>
              @endif
              @if($m->message)<div style="white-space:pre-wrap;word-break:break-word">{{ $m->message }}</div>@endif
              @if(count($imgs))
              <div class="bubble-imgs img-count-{{ min(count($imgs), 4) }}" data-lightbox-gallery data-gallery-sources='@json(array_values($imgs))'>
                @foreach(array_slice($imgs, 0, 4) as $idx => $src)
                  @if($idx === 3 && count($imgs) > 4)
                    <button type="button" class="bubble-img-more chat-img" data-src="{{ $src }}" data-gallery-index="3" title="View {{ count($imgs) }} images" onclick="return openMessageImageButton(this)">
                      <span>+{{ count($imgs) - 3 }}</span>
                    </button>
                  @else
                    <button type="button" class="bubble-img-tile chat-img" data-src="{{ $src }}" data-gallery-index="{{ $idx }}" title="View image {{ $idx + 1 }}" onclick="return openMessageImageButton(this)">
                      <img src="{{ $src }}" class="chat-img" data-src="{{ $src }}" data-gallery-index="{{ $idx }}" alt="" onclick="return openMessageImageButton(this)" onerror="this.parentNode.style.display='none'">
                    </button>
                  @endif
                @endforeach
              </div>
              @endif
            </div>
            <div class="bubble-time {{ $isMine ? 'mine' : '' }}">
              {{ \Carbon\Carbon::parse($m->created_at)->format('M d, g:i A') }}
              @if($isMine) <span class="delivery-state" data-read-status data-message-id="{{ $m->id }}" data-status="{{ $m->is_read ? 'seen' : 'sent' }}">{{ $m->is_read ? 'Seen' : 'Sent' }}</span>@endif
            </div>
            <div data-reactions>
              @if(!empty($m->reaction_summary))
                <div class="message-reactions {{ $isMine ? 'mine' : '' }}">
                  @foreach($m->reaction_summary as $reaction)
                    <span class="reaction-pill {{ !empty($reaction['mine']) ? 'mine' : '' }}" title="{{ $reaction['label'] }}">
                      <span class="cake-face {{ $reaction['reaction'] }} tiny" aria-hidden="true"><span class="eye l"></span><span class="eye r"></span><span class="mouth"></span></span><strong>{{ $reaction['count'] }}</strong>
                    </span>
                  @endforeach
                </div>
              @endif
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
          <input type="hidden" id="replyToInput" data-reply-input name="reply_to_id" value="">
          <div class="reply-compose-preview" id="replyPreview" data-reply-preview>
            <div class="reply-compose-bar"></div>
            <div class="reply-compose-body">
              <div class="reply-compose-label">Replying to <span data-reply-preview-name></span></div>
              <div class="reply-compose-text" data-reply-preview-text></div>
            </div>
            <button type="button" class="reply-compose-close" onclick="BerryMessageInteractions.clearReply()" title="Cancel reply"><i class="bi bi-x-lg"></i></button>
          </div>
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
const newMessagesUrl = '{{ route("seller.messages.thread_new_messages", $orderId) }}';
const customerName = @json($order->fullname ?? 'Customer');
const reactionUrlTemplate = '{{ route("seller.messages.react", [$orderId, "__ID__"]) }}';
const reactionSnapshotsUrl = '{{ route("seller.messages.reaction_snapshots", $orderId) }}';
if (cb) cb.scrollTop = cb.scrollHeight;

BerryMessageInteractions.init({
  csrf,
  reactUrl: reactionUrlTemplate,
  replyInput: '#replyToInput',
  replyPreview: '#replyPreview',
  composer: '#msgInput'
});

let latestThreadMessageId = Math.max(0, ...Array.from(document.querySelectorAll('[data-msg-id]'))
  .map(row => parseInt(row.dataset.msgId || '0', 10))
  .filter(Number.isFinite));

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
const sellerMarkReadUrl = '{{ url("/seller/messages/mark-read-msg") }}';
const sellerReadObserver = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (!entry.isIntersecting) return;
    const row = entry.target;
    if (row.dataset.read === '1' || row.dataset.sender === 'seller') return;
    row.dataset.read = '1';
    sellerReadObserver.unobserve(row);
    fetch(sellerMarkReadUrl + '/' + row.dataset.msgId, { method:'POST', headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/json'} })
      .then(() => {
        if (typeof refreshBubbleUnread === 'function') refreshBubbleUnread();
        if (typeof refreshSellerSidebarBadges === 'function') refreshSellerSidebarBadges();
      })
      .catch(()=>{});
  });
}, { threshold: 0.5 });

function observeSellerMessageRow(row) {
  if (row && row.dataset.sender !== 'seller' && row.dataset.read !== '1') {
    sellerReadObserver.observe(row);
  }
}

document.querySelectorAll('[data-msg-id]').forEach(observeSellerMessageRow);

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

function renderThreadUploadTotalSummary() {
  const target = document.getElementById('threadUploadSummary');
  const picker = document.getElementById('imgFilePicker');
  if (!target || !picker) return;

  if (!pickedImages.length) {
    if (typeof window.csClearFileUploadPreview === 'function') window.csClearFileUploadPreview(picker);
    else target.innerHTML = '';
    return;
  }

  const ready = pickedImages.filter(x => !x.compressing && x.file);
  const pending = pickedImages.length - ready.length;
  const original = ready.reduce((sum, item) => sum + (item.origSize || item.file?.size || 0), 0);
  const optimized = ready.reduce((sum, item) => sum + (item.newSize || item.file?.size || 0), 0);
  const label = pickedImages.length + (pickedImages.length === 1 ? ' image' : ' images');
  const previewId = picker.dataset.sizePreviewId || ('img-size-preview-' + Math.random().toString(36).slice(2));
  picker.dataset.sizePreviewId = previewId;
  target.querySelectorAll('[id^="img-size-preview-"]').forEach(node => { if (node.id !== previewId) node.remove(); });

  let preview = document.getElementById(previewId);
  if (!preview) {
    preview = document.createElement('div');
    preview.id = previewId;
    target.appendChild(preview);
  }

  preview.style.display = 'block';
  preview.innerHTML = '<div class="cs-upload-summary">' +
    '<span class="cs-upload-pill is-muted"><i class="bi bi-images"></i><span>' + label + ': <strong>' + fmtSize(original) + '</strong></span></span>' +
    '<span class="cs-upload-arrow">-&gt;</span>' +
    '<span class="cs-upload-pill is-muted"><i class="bi bi-lightning-charge-fill"></i><span>Optimized: <strong>~' + fmtSize(optimized) + '</strong></span></span>' +
    (pending ? '<span class="cs-upload-pill is-warning"><i class="bi bi-hourglass-split"></i><span>' + pending + ' optimizing</span></span>' : '') +
    '</div>';
}

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
    renderThreadUploadTotalSummary();

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
    renderThreadUploadTotalSummary();

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
  else renderThreadUploadTotalSummary();
}

function clearImgPicker(revoke = true) {
  if (revoke) pickedImages.forEach(x => { if (x.preview) URL.revokeObjectURL(x.preview); });
  pickedImages = [];
  document.getElementById('imgCards').innerHTML = '';
  document.getElementById('imgPreviewBar').style.display = 'none';
  document.getElementById('attachBtn').classList.remove('active');
  const picker = document.getElementById('imgFilePicker');
  if (picker) {
    picker.value = '';
    if (typeof window.csClearFileUploadPreview === 'function') window.csClearFileUploadPreview(picker);
    else {
      const summary = picker.dataset.sizePreviewId ? document.getElementById(picker.dataset.sizePreviewId) : null;
      if (summary) { summary.innerHTML = ''; summary.style.display = 'none'; }
      const target = picker.dataset.sizePreviewTarget ? document.getElementById(picker.dataset.sizePreviewTarget) : null;
      if (target) target.innerHTML = '';
    }
  }
}

function openImgPreview(src) {
  const el = document.createElement('img');
  el.src = src; el.dataset.src = src;
  openLightbox(el);
}

// ── Send ──────────────────────────────────────────────────────────────────
function threadEscAttr(s) {
  return s ? String(s)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;') : '';
}

function threadImageGridHtml(imgs) {
  const cleanImgs = imgs.filter(Boolean);
  if (!cleanImgs.length) return '';

  const galleryJson = threadEscAttr(JSON.stringify(cleanImgs));
  const gridClass = 'img-count-' + Math.min(cleanImgs.length, 4);
  const items = cleanImgs.slice(0, 4).map((src, index) => {
    const safeSrc = threadEscAttr(src);
    if (index === 3 && cleanImgs.length > 4) {
      return `<button type="button" class="bubble-img-more chat-img" data-src="${safeSrc}" data-gallery-index="3" title="View ${cleanImgs.length} images" onclick="return openMessageImageButton(this)"><span>+${cleanImgs.length - 3}</span></button>`;
    }
    return `<button type="button" class="bubble-img-tile chat-img" data-src="${safeSrc}" data-gallery-index="${index}" title="View image ${index + 1}" onclick="return openMessageImageButton(this)">
      <img src="${safeSrc}" class="chat-img" data-src="${safeSrc}" data-gallery-index="${index}" alt="" onclick="return openMessageImageButton(this)" onerror="this.parentNode.style.display='none'">
    </button>`;
  }).join('');

  return `<div class="bubble-imgs ${gridClass}" data-lightbox-gallery data-gallery-sources="${galleryJson}">${items}</div>`;
}

document.getElementById('threadForm').addEventListener('submit', async function (e) {
  e.preventDefault();
  const text     = msgInput.innerText.trim();
  const picker   = document.getElementById('imgFilePicker');
  if (pickedImages.some(x => x.compressing) || (typeof window.csIsFileUploadOptimizing === 'function' && window.csIsFileUploadOptimizing(picker))) {
    if (typeof cakeToast === 'function') cakeToast('Please wait for images to finish optimizing.', 'warning');
    else alert('Please wait for images to finish optimizing.');
    return;
  }
  const hasImgs  = pickedImages.filter(x => !x.compressing && x.file).length > 0;
  if (!text && !hasImgs) return;

  const sendBtn = document.getElementById('sendBtn');
  sendBtn.disabled = true;
  sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:14px;height:14px"></span>';

  const fd = new FormData();
  fd.append('_token', csrf);
  if (text) fd.append('message', text);
  const replyToInput = document.getElementById('replyToInput');
  const optimisticReply = replyToInput && replyToInput.value ? {
    label: document.querySelector('[data-reply-preview-name]')?.textContent || 'Message',
    snippet: document.querySelector('[data-reply-preview-text]')?.textContent || 'Message'
  } : null;
  if (replyToInput && replyToInput.value) fd.append('reply_to_id', replyToInput.value);
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
      appendMyBubble(text, pickedImages.filter(x => x.preview).map(x => x.preview), json.id || '', optimisticReply);
      BerryMessageInteractions.clearReply();
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

function appendMyBubble(text, imgPreviews, msgId = '', reply = null) {
  const now = new Date().toLocaleString('en-US', { month:'short', day:'numeric', hour:'numeric', minute:'2-digit', hour12:true });
  const imgHtml = threadImageGridHtml(imgPreviews);
  const hasMedia = imgPreviews.length > 0;
  const imageOnly = !text && hasMedia;
  const row = document.createElement('div');
  row.className = 'msg-row mine';
  if (msgId) {
    row.dataset.msgId = msgId;
    row.dataset.sender = 'seller';
    row.dataset.read = '0';
    latestThreadMessageId = Math.max(latestThreadMessageId, parseInt(msgId, 10) || 0);
  }
  row.innerHTML = `
    <div class="msg-av mine">Me</div>
    <div class="msg-group mine">
      <div class="sender-lbl mine">You</div>
      <div class="bubble mine${hasMedia ? ' has-media' : ''}${imageOnly ? ' image-only' : ''}">${BerryMessageInteractions.replyHtml(reply, true)}${text ? `<div style="white-space:pre-wrap">${escHtml(text)}</div>` : ''}${imgHtml}</div>
      <div class="bubble-time mine">${now} <span class="delivery-state" data-read-status data-message-id="${msgId || ''}" data-status="sent">Sent</span></div>
      <div data-reactions></div>
    </div>`;
  cb.appendChild(row);
  if (msgId) {
    row.dataset.replySender = 'You';
    row.dataset.replySnippet = text || (hasMedia ? 'Photo message' : 'Message');
  }
  BerryMessageInteractions.bindRow(row);
  cb.scrollTop = cb.scrollHeight;
}

function escHtml(s) {
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
}

function parseThreadImages(imagePath) {
  if (!imagePath) return [];
  try {
    const decoded = JSON.parse(imagePath);
    return Array.isArray(decoded) ? decoded.filter(Boolean) : [imagePath];
  } catch (e) {
    return [imagePath];
  }
}

function renderThreadMessage(message) {
  const id = parseInt(message.id || '0', 10);
  if (!id || document.querySelector('[data-msg-id="' + id + '"]')) return false;

  const isMine = message.sender_role === 'seller';
  const imgs = parseThreadImages(message.image_path);
  const hasMedia = imgs.length > 0;
  const hasText = !!(message.message && String(message.message).trim());
  const imageOnly = !hasText && hasMedia;
  const imgHtml = threadImageGridHtml(imgs);
  const replyHtml = BerryMessageInteractions.replyHtml(message.reply_to, isMine);
  const reactionHtml = BerryMessageInteractions.reactionsHtml(message.reactions || [], isMine);
  const senderName = isMine ? 'You' : (message.sender_role === 'customer' ? customerName : 'Admin');
  const avatarText = isMine ? 'Me' : threadEscAttr(String(senderName || 'C').slice(0, 1).toUpperCase());
  const row = document.createElement('div');
  row.className = 'msg-row' + (isMine ? ' mine' : '');
  row.dataset.msgId = String(id);
  row.dataset.sender = message.sender_role || '';
  row.dataset.read = message.is_read ? '1' : '0';
  row.dataset.replySender = senderName;
  row.dataset.replySnippet = hasText ? String(message.message).slice(0, 90) : (hasMedia ? 'Photo message' : 'Message');
  row.innerHTML = `
    <div class="msg-av ${isMine ? 'mine' : ''}">${avatarText}</div>
    <div class="msg-group ${isMine ? 'mine' : ''}">
      <div class="sender-lbl ${isMine ? 'mine' : ''}">${escHtml(senderName)}</div>
      <div class="bubble ${isMine ? 'mine' : 'theirs'}${hasMedia ? ' has-media' : ''}${imageOnly ? ' image-only' : ''}">
        ${replyHtml}
        ${hasText ? `<div style="white-space:pre-wrap;word-break:break-word">${escHtml(message.message)}</div>` : ''}
        ${imgHtml}
      </div>
      <div class="bubble-time ${isMine ? 'mine' : ''}">
        ${escHtml(message.created_at || '')}${isMine ? ` <span class="delivery-state" data-read-status data-message-id="${id}" data-status="${message.is_read ? 'seen' : 'sent'}">${message.is_read ? 'Seen' : 'Sent'}</span>` : ''}
      </div>
      <div data-reactions>${reactionHtml}</div>
    </div>`;
  cb.appendChild(row);
  observeSellerMessageRow(row);
  BerryMessageInteractions.bindRow(row);
  latestThreadMessageId = Math.max(latestThreadMessageId, id);
  return true;
}

let sellerMessagePollBusy = false;
async function pollSellerThreadMessages() {
  if (sellerMessagePollBusy || document.hidden || !cb) return;
  sellerMessagePollBusy = true;
  try {
    const wasNearBottom = cb.scrollHeight - cb.scrollTop - cb.clientHeight < 96;
    const res = await fetch(newMessagesUrl + '?after_id=' + encodeURIComponent(latestThreadMessageId), {
      headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
    });
    if (!res.ok) return;
    const data = await res.json();
    if (!data.ok || !Array.isArray(data.messages)) return;
    let appended = false;
    data.messages.forEach(message => {
      if (renderThreadMessage(message)) appended = true;
    });
    if (appended) {
      if (wasNearBottom) cb.scrollTop = cb.scrollHeight;
      if (typeof refreshBubbleUnread === 'function') refreshBubbleUnread();
      if (typeof refreshSellerSidebarBadges === 'function') refreshSellerSidebarBadges();
    }
  } catch (e) {
  } finally {
    sellerMessagePollBusy = false;
  }
}

setInterval(pollSellerThreadMessages, 7000);
document.addEventListener('visibilitychange', pollSellerThreadMessages);
setTimeout(pollSellerThreadMessages, 1800);

(function startThreadReactionRefresh() {
  let running = false;

  async function refresh() {
    if (running || document.hidden || !cb) return;
    const ids = Array.from(document.querySelectorAll('#chatBox [data-msg-id]'))
      .map(row => row.dataset.msgId)
      .filter(Boolean)
      .slice(-80);
    if (!ids.length) return;

    running = true;
    try {
      const res = await fetch(reactionSnapshotsUrl, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'},
        body: JSON.stringify({ids})
      });
      if (!res.ok) return;
      const data = await res.json();
      if (!data.ok || !data.reactions) return;
      Object.entries(data.reactions).forEach(([id, items]) => {
        BerryMessageInteractions.updateReactions(id, Array.isArray(items) ? items : []);
      });
    } catch (e) {
    } finally {
      running = false;
    }
  }

  setInterval(refresh, 9000);
  document.addEventListener('visibilitychange', refresh);
  setTimeout(refresh, 2200);
})();

(function startThreadReadStatusRefresh() {
  const url = '{{ route("seller.messages.read_statuses") }}';
  let running = false;

  async function refresh() {
    if (running || document.hidden) return;
    const nodes = [...document.querySelectorAll('[data-read-status][data-status="sent"][data-message-id]')]
      .filter(node => node.dataset.messageId);
    if (!nodes.length) return;

    running = true;
    try {
      const ids = [...new Set(nodes.map(node => node.dataset.messageId))].slice(0, 50);
      const res = await fetch(url, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'},
        body: JSON.stringify({ids})
      });
      if (!res.ok) return;
      const data = await res.json();
      const statuses = data.statuses || {};
      nodes.forEach(node => {
        if (statuses[node.dataset.messageId]) {
          node.textContent = 'Seen';
          node.dataset.status = 'seen';
        }
      });
    } catch (e) {
    } finally {
      running = false;
    }
  }

  setInterval(refresh, 10000);
  document.addEventListener('visibilitychange', refresh);
  setTimeout(refresh, 1500);
})();
</script>
@endpush
