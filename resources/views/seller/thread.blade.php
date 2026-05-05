@extends('layouts.app')
@section('content')
<style>
.thread-header{background:#fff;border:1px solid #e9ecef;border-radius:14px;padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:14px}
.thread-avatar{width:44px;height:44px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;flex-shrink:0}
.thread-card{border:1px solid #e9ecef;border-radius:14px;overflow:hidden;background:#fff}
.chat-box{height:460px;overflow-y:auto;padding:20px 16px;display:flex;flex-direction:column;gap:6px;background:#f8f9fa}
.msg-row{display:flex;gap:8px;align-items:flex-end}
.msg-row.mine{flex-direction:row-reverse}
.msg-avatar{width:28px;height:28px;border-radius:50%;background:#dee2e6;color:#666;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700;flex-shrink:0}
.msg-avatar.mine-av{background:var(--primary);color:#fff}
.msg-group{display:flex;flex-direction:column;max-width:72%;gap:2px}
.msg-group.mine{align-items:flex-end}
.bubble{padding:9px 13px;border-radius:16px;font-size:.875rem;line-height:1.45;word-break:break-word;position:relative}
.bubble.theirs{background:#fff;color:#333;border-radius:4px 16px 16px 16px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.bubble.mine{background:var(--primary);color:#fff;border-radius:16px 4px 16px 16px}
.bubble-time{font-size:.65rem;color:#adb5bd;margin-top:1px;padding:0 2px}
.bubble-time.mine{text-align:right}
.sender-label{font-size:.68rem;font-weight:600;margin-bottom:2px;padding:0 2px;color:#6c757d}
.sender-label.mine{text-align:right;color:var(--primary)}
.chat-img-wrap img{border-radius:10px;max-width:200px;max-height:200px;object-fit:cover;cursor:zoom-in;display:block;margin-top:6px}
.preview-bar{display:none;padding:10px 16px 6px;border-top:1px solid #f0f0f0;background:#fafafa}
.compose-area{padding:12px 14px;border-top:1px solid #e9ecef;background:#fff}
.compose-inner{display:flex;gap:8px;align-items:center;background:#f1f3f5;border-radius:24px;padding:6px 6px 6px 14px}
.compose-inner input{flex:1;border:none;background:transparent;outline:none;font-size:.9rem;padding:4px 0}
.compose-inner input::placeholder{color:#adb5bd}
.attach-btn{width:36px;height:36px;border-radius:50%;border:none;background:transparent;color:#6c757d;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1rem;transition:background .15s}
.attach-btn:hover{background:#e9ecef}
.attach-btn.active{background:#fce7f3;color:var(--primary)}
.send-btn{width:38px;height:38px;border-radius:50%;border:none;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.95rem;transition:opacity .15s;flex-shrink:0}
.send-btn:disabled{opacity:.5;cursor:not-allowed}
.status-badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.7rem;font-weight:600;background:#e9ecef;color:#555}
.status-badge.pending{background:#fff3cd;color:#856404}
.status-badge.confirmed{background:#d1e7dd;color:#0a3622}
.status-badge.cancelled{background:#f8d7da;color:#842029}
</style>

<div class="row justify-content-center">
  <div class="col-lg-7 col-xl-6">

    {{-- Header --}}
    <div class="thread-header">
      <a href="{{ route('seller.messages') }}" class="btn btn-sm btn-light me-1" style="border-radius:10px"><i class="bi bi-arrow-left"></i></a>
      <div class="thread-avatar">{{ strtoupper(substr($order->fullname ?? 'C', 0, 1)) }}</div>
      <div class="flex-grow-1 min-width-0">
        <div class="fw-bold" style="font-size:.95rem">{{ $order->fullname }}</div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <span class="text-muted small">{{ $order->product_name }}</span>
          <span class="status-badge {{ strtolower(str_replace(' ','',($order->status ?? ''))) }}">{{ $order->status }}</span>
        </div>
      </div>
      <div class="text-muted small text-end">
        <div>Order</div>
        <div class="fw-semibold" style="font-size:.8rem">#{{ $order->id }}</div>
      </div>
    </div>

    {{-- Chat card --}}
    <div class="thread-card">
      <div class="chat-box" id="chatBox">
        @forelse($messages as $m)
        @php
          $isMine = $m->sender_role === 'seller';
          $imgs = [];
          if (!empty($m->image_path)) {
            $decoded = json_decode($m->image_path, true);
            $imgs = is_array($decoded) ? $decoded : [$m->image_path];
          }
          $initials = $isMine ? 'Me' : strtoupper(substr($order->fullname ?? 'C', 0, 1));
          $label    = $isMine ? 'You' : ($order->fullname ?? 'Customer');
        @endphp
        <div class="msg-row {{ $isMine ? 'mine' : '' }}"
             data-msg-id="{{ $m->id }}"
             data-sender="{{ $m->sender_role }}"
             data-read="{{ $m->is_read ? '1' : '0' }}">
          <div class="msg-avatar {{ $isMine ? 'mine-av' : '' }}">{{ $initials }}</div>
          <div class="msg-group {{ $isMine ? 'mine' : '' }}">
            <div class="sender-label {{ $isMine ? 'mine' : '' }}">{{ $label }}</div>
            <div class="bubble {{ $isMine ? 'mine' : 'theirs' }}">
              @if($m->message)<div>{{ $m->message }}</div>@endif
              @if(count($imgs))
              <div class="chat-img-wrap">
                @foreach($imgs as $imgSrc)
                <img src="{{ $imgSrc }}" onerror="this.style.display='none'" onclick="openLightbox(this)" data-src="{{ $imgSrc }}">
                @endforeach
              </div>
              @endif
            </div>
            <div class="bubble-time {{ $isMine ? 'mine' : '' }}">
              {{ \Carbon\Carbon::parse($m->created_at)->format('M d, g:i A') }}
              @if($isMine)<span style="margin-left:3px;opacity:.7">✓</span>@endif
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
      <div class="preview-bar" id="threadImgPreview">
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center" id="threadPreviewStrip"></div>
      </div>

      {{-- Compose --}}
      <div class="compose-area">
        <form id="threadForm">
          @csrf
          <div class="compose-inner">
            <input type="text" name="message" id="threadMsgInput" placeholder="Type a message…" autocomplete="off">
            <label class="attach-btn" id="threadImgBtn" title="Attach image">
              <i class="bi bi-paperclip"></i>
              <input type="file" name="attachment" id="threadImgFile" accept="image/*" hidden onchange="previewThreadImg(this)">
            </label>
            <button type="submit" class="send-btn" id="threadSendBtn"><i class="bi bi-send-fill"></i></button>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>
@endsection

@push('scripts')
<script>
const cb      = document.getElementById('chatBox');
const csrf    = '{{ csrf_token() }}';
const sendUrl = '{{ route("seller.messages.send", $orderId) }}';

// Scroll to bottom on load
if (cb) cb.scrollTop = cb.scrollHeight;

// ── Mark customer messages as read on scroll into view ────────────────────
(function () {
  const markUrl = '{{ url("/seller/messages/mark-read-msg") }}';
  const obs = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const row = entry.target;
      if (row.dataset.read === '1' || row.dataset.sender === 'seller') return;
      row.dataset.read = '1';
      obs.unobserve(row);
      fetch(markUrl + '/' + row.dataset.msgId, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' }
      }).catch(() => {});
    });
  }, { threshold: 0.5 });
  document.querySelectorAll('[data-msg-id]').forEach(el => obs.observe(el));
})();

// ── Send message via AJAX ─────────────────────────────────────────────────
document.getElementById('threadForm').addEventListener('submit', async function (e) {
  e.preventDefault();

  const msgInput = document.getElementById('threadMsgInput');
  const fileInput = document.getElementById('threadImgFile');
  const sendBtn  = document.getElementById('threadSendBtn');
  const text     = msgInput.value.trim();
  const hasFile  = fileInput.files.length > 0;

  if (!text && !hasFile) return;

  sendBtn.disabled = true;
  sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:14px;height:14px"></span>';

  const fd = new FormData();
  fd.append('_token', csrf);
  if (text)    fd.append('message', text);
  if (hasFile) fd.append('attachment', fileInput.files[0]);

  try {
    const res = await fetch(sendUrl, { method: 'POST', body: fd });

    const ct = res.headers.get('content-type') || '';
    if (!ct.includes('application/json')) {
      const raw = await res.text();
      console.error('Non-JSON response (HTTP ' + res.status + '):', raw.substring(0, 800));
      alert('Server error (HTTP ' + res.status + '). Check browser console (F12) for details.');
      return;
    }

    const json = await res.json();

    if (json.ok) {
      appendMyBubble(text, hasFile ? previewDataUrl : null);
      msgInput.value = '';
      fileInput.value = '';
      clearPreview();
    } else {
      alert(json.error || 'Failed to send message.');
    }
  } catch (err) {
    console.error('Send error:', err);
    alert('Error: ' + err.message);
  } finally {
    sendBtn.disabled = false;
    sendBtn.innerHTML = '<i class="bi bi-send-fill"></i>';
  }
});

function appendMyBubble(text, imgSrc) {
  const now = new Date();
  const timeStr = now.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });

  let inner = '';
  if (text)   inner += `<div>${escHtml(text)}</div>`;
  if (imgSrc) inner += `<div class="chat-img-wrap"><img src="${imgSrc}" style="max-width:200px;border-radius:10px;display:block;margin-top:6px"></div>`;

  const row = document.createElement('div');
  row.className = 'msg-row mine';
  row.innerHTML = `
    <div class="msg-avatar mine-av">Me</div>
    <div class="msg-group mine">
      <div class="sender-label mine">You</div>
      <div class="bubble mine">${inner}</div>
      <div class="bubble-time mine">${timeStr} <span style="opacity:.7">✓</span></div>
    </div>`;
  cb.appendChild(row);
  cb.scrollTop = cb.scrollHeight;
}

function escHtml(str) {
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Image preview ─────────────────────────────────────────────────────────
let previewDataUrl = null;

function previewThreadImg(input) {
  const strip = document.getElementById('threadPreviewStrip');
  const bar   = document.getElementById('threadImgPreview');
  const btn   = document.getElementById('threadImgBtn');

  strip.innerHTML = '';
  previewDataUrl  = null;

  if (!input.files.length) { clearPreview(); return; }

  const r = new FileReader();
  r.onload = ev => {
    previewDataUrl = ev.target.result;
    bar.style.display = 'block';
    btn.classList.add('active');

    const wrap = document.createElement('div');
    wrap.style.cssText = 'position:relative;display:inline-block';

    const img = document.createElement('img');
    img.src = previewDataUrl;
    img.style.cssText = 'width:64px;height:64px;border-radius:8px;object-fit:cover;border:2px solid var(--primary)';

    const rm = document.createElement('button');
    rm.type = 'button';
    rm.innerHTML = '✕';
    rm.style.cssText = 'position:absolute;top:-6px;right:-6px;width:18px;height:18px;border-radius:50%;background:#ef4444;border:none;color:#fff;font-size:.6rem;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;line-height:1';
    rm.onclick = () => { input.value = ''; clearPreview(); };

    wrap.appendChild(img);
    wrap.appendChild(rm);
    strip.appendChild(wrap);
  };
  r.readAsDataURL(input.files[0]);
}

function clearPreview() {
  const bar  = document.getElementById('threadImgPreview');
  const strip = document.getElementById('threadPreviewStrip');
  const btn  = document.getElementById('threadImgBtn');
  strip.innerHTML = '';
  previewDataUrl  = null;
  bar.style.display  = 'none';
  btn.classList.remove('active');
}
</script>
@endpush
