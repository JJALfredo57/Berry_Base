@extends('layouts.app')
@section('content')
<style>
.admin-msg-bubble{max-width:75%;border-radius:1rem;padding:.6rem 1rem;font-size:.9rem;word-break:break-word}
.admin-msg-bubble.has-media{display:inline-flex;flex-direction:column;width:fit-content;max-width:min(328px,100%)}
.admin-msg-bubble.image-only{padding:3px;background:transparent!important;color:inherit!important;box-shadow:none;border-radius:10px!important}
.admin-msg-bubble.image-only.mine{outline:2px solid color-mix(in srgb,var(--primary) 38%,transparent)}
.admin-msg-bubble.image-only.theirs{outline:1px solid #e5e7eb}
.admin-msg-bubble.image-only .admin-bubble-imgs{margin-top:0!important}
.admin-delivery-state{font-weight:700;color:var(--primary)}
.admin-bubble-imgs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px;margin-top:.4rem;width:min(304px,100%);max-width:100%}
.admin-bubble-imgs.img-count-1{grid-template-columns:1fr;width:min(240px,100%)}
.admin-bubble-imgs.img-count-2{grid-template-columns:repeat(2,minmax(0,1fr));width:min(304px,100%)}
.admin-img-tile,.admin-img-more{width:100%;aspect-ratio:1/1;border:0;border-radius:.45rem;cursor:zoom-in;display:block;min-width:0;overflow:hidden;padding:0;touch-action:manipulation;-webkit-tap-highlight-color:transparent}
.admin-img-tile{background:transparent}
.admin-img-tile img{width:100%;height:100%;object-fit:cover;display:block;cursor:zoom-in}
.admin-bubble-imgs.img-count-1 .admin-img-tile{aspect-ratio:4/3}
.admin-img-more{background:#111827;color:#fff;position:relative;font-size:1.35rem;font-weight:900;display:flex;align-items:center;justify-content:center}
.admin-img-more:before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 30% 20%,rgba(255,255,255,.16),transparent 34%),linear-gradient(135deg,rgba(17,24,39,.94),rgba(31,41,55,.98))}
.admin-img-more span{position:relative;z-index:1;text-shadow:0 2px 10px rgba(0,0,0,.35)}
#threadImgPreview{max-height:104px;overflow:hidden}
#threadPreviewStrip{flex-wrap:nowrap!important;overflow-x:auto;overflow-y:hidden;padding-bottom:4px;scrollbar-width:thin}
@media(max-width:640px){
  .admin-msg-bubble{max-width:84%}
  .admin-bubble-imgs,.admin-bubble-imgs.img-count-2{width:min(244px,100%)}
  .admin-bubble-imgs.img-count-1{width:min(220px,100%)}
}
</style>
@include('partials.message_interactions')
<div>
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <div class="d-flex align-items-center gap-3 mb-3">
        <a href="{{ route('admin.messages.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
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
              $hasText = trim((string) $m->message) !== '';
              $isImageOnly = !$hasText && count($imgs) > 0;
            @endphp
            <div class="d-flex {{ $isAdmin ? 'justify-content-end' : 'justify-content-start' }}"
                 data-msg-id="{{ $m->id }}"
                 data-sender="{{ $m->sender_role }}"
                 data-read="{{ $m->is_read }}"
                 data-reply-sender="{{ $isAdmin ? 'You' : ucfirst($m->sender_role) }}"
                 data-reply-snippet="{{ trim((string) $m->message) !== '' ? Str::limit($m->message, 80) : (count($imgs) ? 'Photo message' : 'Message') }}">
              <div class="admin-msg-bubble {{ $isAdmin ? 'mine' : 'theirs' }} {{ count($imgs) ? 'has-media' : '' }} {{ $isImageOnly ? 'image-only' : '' }}" style="background:{{ $isAdmin ? 'var(--primary)' : '#f1f3f5' }};color:{{ $isAdmin ? '#fff' : '#333' }};border-radius:{{ $isAdmin ? '1rem 1rem 0 1rem' : '1rem 1rem 1rem 0' }}">
                @if($m->reply_to)
                  <div class="msg-reply-quote {{ $isAdmin ? 'mine' : 'theirs' }}">
                    <div class="msg-reply-name">{{ $m->reply_to['label'] ?? 'Message' }}</div>
                    <div class="msg-reply-text">{{ $m->reply_to['snippet'] ?? 'Message' }}</div>
                  </div>
                @endif
                @if($m->message)<div style="white-space:pre-wrap;word-break:break-word">{{ $m->message }}</div>@endif
                @if(count($imgs))
                <div class="admin-bubble-imgs img-count-{{ min(count($imgs), 4) }}" data-lightbox-gallery data-gallery-sources='@json(array_values($imgs))' style="margin-top:{{ $m->message ? '.4rem' : '0' }}">
                  @foreach(array_slice($imgs, 0, 4) as $idx => $imgSrc)
                    @if($idx === 3 && count($imgs) > 4)
                      <button type="button" class="admin-img-more chat-img" data-src="{{ $imgSrc }}" data-gallery-index="3" title="View {{ count($imgs) }} images" onclick="return openMessageImageButton(this)">
                        <span>+{{ count($imgs) - 3 }}</span>
                      </button>
                    @else
                      <button type="button" class="admin-img-tile chat-img" data-src="{{ $imgSrc }}" data-gallery-index="{{ $idx }}" title="View image {{ $idx + 1 }}" onclick="return openMessageImageButton(this)">
                        <img src="{{ $imgSrc }}" class="chat-img" data-src="{{ $imgSrc }}" data-gallery-index="{{ $idx }}" alt="" onclick="return openMessageImageButton(this)" onerror="this.parentNode.style.display='none'">
                      </button>
                    @endif
                  @endforeach
                </div>
                @endif
                <div style="font-size:.65rem;opacity:.85;margin-top:.2rem;text-align:right">{{ \Carbon\Carbon::parse($m->created_at)->format('M d g:i A') }}@if($isAdmin) <span class="admin-delivery-state" data-read-status data-message-id="{{ $m->id }}" data-status="{{ $m->is_read ? 'seen' : 'sent' }}">{{ $m->is_read ? 'Seen' : 'Sent' }}</span>@endif</div>
                <div data-reactions>
                  @if(!empty($m->reaction_summary))
                    <div class="message-reactions {{ $isAdmin ? 'mine' : '' }}">
                      @foreach($m->reaction_summary as $reaction)
                        <span class="reaction-pill {{ !empty($reaction['mine']) ? 'mine' : '' }}" title="{{ $reaction['label'] }}"><span class="cake-face {{ $reaction['reaction'] }} tiny" aria-hidden="true"><span class="eye l"></span><span class="eye r"></span><span class="mouth"></span></span><strong>{{ $reaction['count'] }}</strong></span>
                      @endforeach
                    </div>
                  @endif
                </div>
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
            <div id="threadUploadSummary" style="display:flex;align-items:center;flex-wrap:wrap;margin-bottom:.35rem"></div>
            <form action="{{ route('admin.messages.send', $orderId) }}" method="POST" enctype="multipart/form-data" id="threadForm">
              @csrf
              <input type="hidden" id="replyToInput" data-reply-input name="reply_to_id" value="">
              <div class="reply-compose-preview" id="replyPreview" data-reply-preview>
                <div class="reply-compose-bar"></div>
                <div class="reply-compose-body">
                  <div class="reply-compose-label">Replying to <span data-reply-preview-name></span></div>
                  <div class="reply-compose-text" data-reply-preview-text></div>
                </div>
                <button type="button" class="reply-compose-close" onclick="BerryMessageInteractions.clearReply()" title="Cancel reply"><i class="bi bi-x-lg"></i></button>
              </div>
              <div class="d-flex gap-2">
                <textarea class="form-control" name="message" id="threadMsgInput" placeholder="Reply…" autocomplete="off" rows="1" maxlength="1000" style="resize:none;max-height:120px;line-height:1.4;overflow-y:auto"></textarea>
                <label class="btn btn-outline-secondary mb-0" id="threadImgBtn" title="Attach images">
                  <i class="bi bi-paperclip"></i>
                  <input type="file" name="images[]" id="threadImgFile" accept="image/*" multiple hidden data-size-preview-target="threadUploadSummary" onchange="previewThreadImgs(this)">
                </label>
                <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i></button>
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
const cb = document.getElementById('chatBox');
if (cb) cb.scrollTop = cb.scrollHeight;
const csrf = '{{ csrf_token() }}';

BerryMessageInteractions.init({
  csrf,
  reactUrl: '{{ route("admin.messages.react", [$orderId, "__ID__"]) }}',
  replyInput: '#replyToInput',
  replyPreview: '#replyPreview',
  composer: '#threadMsgInput'
});

const threadMsgInput = document.getElementById('threadMsgInput');
if (threadMsgInput) {
  const growThreadInput = () => {
    threadMsgInput.style.height = 'auto';
    threadMsgInput.style.height = Math.min(threadMsgInput.scrollHeight, 120) + 'px';
  };
  threadMsgInput.addEventListener('input', growThreadInput);
  threadMsgInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
      e.preventDefault();
      document.getElementById('threadForm').requestSubmit();
    }
  });
  growThreadInput();
}

// Mark unread messages as read via IntersectionObserver
(function() {
  const markUrl = '{{ url("/admin/messages/mark-read-msg") }}';
  const obs = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const row = entry.target;
      if (row.dataset.read === '1' || row.dataset.sender === 'admin') return;
      const id = row.dataset.msgId;
      row.dataset.read = '1';
      obs.unobserve(row);
      fetch(markUrl + '/' + id, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' }
      }).catch(() => {});
    });
  }, { threshold: 0.5 });

  document.querySelectorAll('[data-msg-id]').forEach(el => obs.observe(el));
})();

let threadFiles = [];

function previewThreadImgs(input) {
  threadFiles = Array.from(input.files);
  renderThreadPreview();
}

function renderThreadPreview() {
  const strip = document.getElementById('threadPreviewStrip');
  const bar   = document.getElementById('threadImgPreview');
  const btn   = document.getElementById('threadImgBtn');
  strip.innerHTML = '';
  if (threadFiles.length === 0) {
    bar.style.display = 'none';
    btn.style.background = ''; btn.style.color = '';
    const summary = document.getElementById('threadImgFile')?.dataset.sizePreviewId
      ? document.getElementById(document.getElementById('threadImgFile').dataset.sizePreviewId)
      : null;
    if (summary) summary.style.display = 'none';
    return;
  }
  bar.style.display = 'block';
  btn.style.background = 'var(--primary-light)'; btn.style.color = 'var(--primary)';
  threadFiles.forEach((file, idx) => {
    const wrap = document.createElement('div');
    wrap.style = 'position:relative;display:inline-block';
    const img = document.createElement('img');
    img.style = 'width:64px;height:64px;border-radius:.4rem;object-fit:cover;border:2px solid var(--primary)';
    const r = new FileReader();
    r.onload = e => img.src = e.target.result;
    r.readAsDataURL(file);
    const rm = document.createElement('button');
    rm.type = 'button';
    rm.innerHTML = '✕';
    rm.style = 'position:absolute;top:-5px;right:-5px;width:16px;height:16px;border-radius:50%;background:#ef4444;border:none;color:white;font-size:.55rem;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0';
    rm.onclick = () => { threadFiles.splice(idx, 1); rebuildInput(); renderThreadPreview(); };
    wrap.appendChild(img); wrap.appendChild(rm);
    strip.appendChild(wrap);
  });
}

function rebuildInput() {
  // Rebuild the file input DataTransfer to reflect removed files
  const dt = new DataTransfer();
  threadFiles.forEach(f => dt.items.add(f));
  document.getElementById('threadImgFile').files = dt.files;
}

function clearThreadImg() {
  threadFiles = [];
  const input = document.getElementById('threadImgFile');
  if (input) {
    input.value = '';
    if (typeof window.csClearFileUploadPreview === 'function') window.csClearFileUploadPreview(input);
  }
  renderThreadPreview();
}

window.addEventListener('pageshow', function(event) {
  if (event.persisted) clearThreadImg();
});

document.getElementById('threadForm')?.addEventListener('submit', function(e) {
  const input = document.getElementById('threadImgFile');
  if (typeof window.csIsFileUploadOptimizing === 'function' && window.csIsFileUploadOptimizing(input)) {
    e.preventDefault();
    if (typeof cakeToast === 'function') cakeToast('Please wait for images to finish optimizing.', 'warning');
    else alert('Please wait for images to finish optimizing.');
  }
});

(function startThreadReadStatusRefresh() {
  const url = '{{ route("admin.messages.read_statuses") }}';
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
