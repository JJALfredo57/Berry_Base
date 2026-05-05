@extends('layouts.app')
@section('content')
<div>
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <div class="d-flex align-items-center gap-3 mb-3">
        <a href="{{ route('seller.messages') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <div>
          <h5 class="fw-bold mb-0">{{ $order->fullname }} — Order #{{ $order->id }}</h5>
          <span class="small text-muted">{{ $order->product_name }} &bull; <strong>{{ $order->status }}</strong></span>
        </div>
      </div>
      <div class="card">
        <div class="card-body p-0">
          <div class="p-3 overflow-auto" style="height:420px;display:flex;flex-direction:column;gap:.5rem" id="chatBox">
            @forelse($messages as $m)
            @php
              $isAdmin = $m->sender_role === 'admin';
              $imgs = [];
              if (!empty($m->image_path)) {
                $decoded = json_decode($m->image_path, true);
                $imgs = is_array($decoded) ? $decoded : [$m->image_path];
              }
            @endphp
            <div class="d-flex {{ $isAdmin ? 'justify-content-end' : 'justify-content-start' }}"
                 data-msg-id="{{ $m->id }}"
                 data-sender="{{ $m->sender_role }}"
                 data-read="{{ $m->is_read }}">
              <div style="max-width:75%;background:{{ $isAdmin ? 'var(--primary)' : '#f1f3f5' }};color:{{ $isAdmin ? '#fff' : '#333' }};border-radius:{{ $isAdmin ? '1rem 1rem 0 1rem' : '1rem 1rem 1rem 0' }};padding:.6rem 1rem;font-size:.9rem">
                @if($m->message)<div>{{ $m->message }}</div>@endif
                @if(count($imgs))
                <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:{{ $m->message ? '.4rem' : '0' }}">
                  @foreach($imgs as $imgSrc)
                  <img src="{{ $imgSrc }}"
                       class="chat-img"
                       data-src="{{ $imgSrc }}"
                       style="width:{{ count($imgs) > 1 ? '100px' : '200px' }};height:{{ count($imgs) > 1 ? '100px' : 'auto' }};max-width:100%;border-radius:.4rem;cursor:zoom-in;object-fit:cover;display:block"
                       onerror="this.style.display='none'"
                       onclick="openLightbox(this)">
                  @endforeach
                </div>
                @endif
                <div style="font-size:.65rem;opacity:.7;margin-top:.2rem;text-align:right">{{ \Carbon\Carbon::parse($m->created_at)->format('M d g:i A') }}</div>
              </div>
            </div>
            @empty
            <div class="text-center text-muted small py-3">No messages yet.</div>
            @endforelse
          </div>

          {{-- Multi-image preview strip --}}
          <div id="threadImgPreview" style="display:none;padding:.6rem 1rem .25rem;border-top:1px solid #f0f0f0;background:#fafafa">
            <div id="threadPreviewStrip" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"></div>
          </div>

          <div class="border-top p-3">
            <form id="threadForm" enctype="multipart/form-data">
              @csrf
              <div class="d-flex gap-2">
                <input type="text" class="form-control" name="message" id="threadMsgInput" placeholder="Reply…" autocomplete="off">
                <label class="btn btn-outline-secondary mb-0" id="threadImgBtn" title="Attach image">
                  <i class="bi bi-paperclip"></i>
                  <input type="file" name="attachment" id="threadImgFile" accept="image/*" hidden onchange="previewThreadImgs(this)">
                </label>
                <button type="submit" class="btn btn-primary" id="threadSendBtn"><i class="bi bi-send"></i></button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
@push('scripts')
<script>
const cb   = document.getElementById('chatBox');
const csrf = '{{ csrf_token() }}';
const sendUrl = '{{ route("seller.messages.send", $orderId) }}';
if (cb) cb.scrollTop = cb.scrollHeight;

// ── Mark customer messages as read ────────────────────────────────────────
(function() {
  const markUrl = '{{ url("/seller/messages/mark-read-msg") }}';
  const obs = new IntersectionObserver((entries) => {
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

// ── AJAX form submit ──────────────────────────────────────────────────────
document.getElementById('threadForm').addEventListener('submit', async function(e) {
  e.preventDefault();

  const msgInput = document.getElementById('threadMsgInput');
  const fileInput = document.getElementById('threadImgFile');
  const sendBtn  = document.getElementById('threadSendBtn');
  const text = msgInput.value.trim();
  const hasFile = fileInput.files.length > 0;

  if (!text && !hasFile) return;

  sendBtn.disabled = true;
  sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

  const fd = new FormData();
  fd.append('_token', csrf);
  if (text) fd.append('message', text);
  if (hasFile) fd.append('attachment', fileInput.files[0]);

  try {
    const res  = await fetch(sendUrl, { method: 'POST', body: fd });

    // If server returned non-JSON (500 error page, redirect, etc.) show it
    const contentType = res.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      const raw = await res.text();
      console.error('Unexpected response (HTTP ' + res.status + '):', raw.substring(0, 500));
      alert('Server error (HTTP ' + res.status + '). Check browser console for details.');
      return;
    }

    const json = await res.json();

    if (json.ok) {
      // Append message bubble instantly
      const now = new Date();
      const timeStr = now.toLocaleString('en-US', { month:'short', day:'numeric', hour:'numeric', minute:'2-digit', hour12:true });
      let bubbleHtml = '';

      if (text) {
        bubbleHtml += `<div>${escHtml(text)}</div>`;
      }
      if (hasFile && threadPreviewSrc) {
        bubbleHtml += `<div style="margin-top:${text ? '.4rem' : '0'}"><img src="${threadPreviewSrc}" style="width:200px;max-width:100%;border-radius:.4rem;display:block"></div>`;
      }
      bubbleHtml += `<div style="font-size:.65rem;opacity:.7;margin-top:.2rem;text-align:right">${timeStr}</div>`;

      const wrap = document.createElement('div');
      wrap.className = 'd-flex justify-content-end';
      wrap.innerHTML = `<div style="max-width:75%;background:var(--primary);color:#fff;border-radius:1rem 1rem 0 1rem;padding:.6rem 1rem;font-size:.9rem">${bubbleHtml}</div>`;
      cb.appendChild(wrap);
      cb.scrollTop = cb.scrollHeight;

      // Reset form
      msgInput.value = '';
      fileInput.value = '';
      threadPreviewSrc = null;
      renderThreadPreview();
    } else {
      alert(json.error || 'Failed to send message.');
    }
  } catch (err) {
    console.error('Send message error:', err);
    alert('Error: ' + err.message);
  } finally {
    sendBtn.disabled = false;
    sendBtn.innerHTML = '<i class="bi bi-send"></i>';
  }
});

function escHtml(str) {
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Image preview ─────────────────────────────────────────────────────────
let threadPreviewSrc = null;

function previewThreadImgs(input) {
  const strip = document.getElementById('threadPreviewStrip');
  const bar   = document.getElementById('threadImgPreview');
  const btn   = document.getElementById('threadImgBtn');
  strip.innerHTML = '';
  threadPreviewSrc = null;

  if (!input.files.length) {
    bar.style.display = 'none';
    btn.style.background = ''; btn.style.color = '';
    return;
  }

  const file = input.files[0];
  const r = new FileReader();
  r.onload = e => {
    threadPreviewSrc = e.target.result;
    bar.style.display = 'block';
    btn.style.background = 'var(--primary-light)'; btn.style.color = 'var(--primary)';

    const wrap = document.createElement('div');
    wrap.style = 'position:relative;display:inline-block';
    const img = document.createElement('img');
    img.src = e.target.result;
    img.style = 'width:64px;height:64px;border-radius:.4rem;object-fit:cover;border:2px solid var(--primary)';
    const rm = document.createElement('button');
    rm.type = 'button'; rm.innerHTML = '✕';
    rm.style = 'position:absolute;top:-5px;right:-5px;width:16px;height:16px;border-radius:50%;background:#ef4444;border:none;color:white;font-size:.55rem;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0';
    rm.onclick = () => { input.value = ''; threadPreviewSrc = null; bar.style.display = 'none'; btn.style.background = ''; btn.style.color = ''; };
    wrap.appendChild(img); wrap.appendChild(rm);
    strip.appendChild(wrap);
  };
  r.readAsDataURL(file);
}
</script>
@endpush
