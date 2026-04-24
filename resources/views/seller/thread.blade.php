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
            <form action="{{ route('seller.messages.send', $orderId) }}" method="POST" enctype="multipart/form-data" id="threadForm">
              @csrf
              <div class="d-flex gap-2">
                <input type="text" class="form-control" name="message" id="threadMsgInput" placeholder="Reply…" autocomplete="off">
                <label class="btn btn-outline-secondary mb-0" id="threadImgBtn" title="Attach images">
                  <i class="bi bi-paperclip"></i>
                  <input type="file" name="images[]" id="threadImgFile" accept="image/*" multiple hidden onchange="previewThreadImgs(this)">
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

// Mark unread messages as read via IntersectionObserver
(function() {
  const markUrl = '{{ url("/admin/messages/mark-read-msg") }}';
  const csrf    = '{{ csrf_token() }}';
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
  document.getElementById('threadImgFile').value = '';
  renderThreadPreview();
}
</script>
@endpush
