@extends('layouts.app')
@section('page_title', 'Platform Feedback')
@section('content')
<div style="max-width:1040px;margin:0 auto">
  <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-4">
    <div>
      <h1 style="font-size:1.45rem;font-weight:800;margin:0 0 .25rem">Platform Feedback</h1>
      <p style="font-size:.88rem;color:var(--gray-500);margin:0">Share operational issues, product ideas, and seller experience improvements with the platform team.</p>
    </div>
    <a href="{{ route('seller.dashboard') }}" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Dashboard
    </a>
  </div>

  @if(session('msg'))
    <div class="alert alert-success border-0"><i class="bi bi-check-circle-fill me-2"></i>{{ session('msg') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger border-0"><i class="bi bi-exclamation-circle-fill me-2"></i>Please check the highlighted fields.</div>
  @endif

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card h-100" style="border:1.5px solid var(--gray-100);border-radius:14px">
        <div class="card-header d-flex align-items-center gap-2">
          <i class="bi bi-send-check" style="color:var(--primary)"></i>
          <span class="fw-bold">Send Feedback</span>
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('seller.feedback.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold">Type</label>
              <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                <option value="" @selected(old('category') === null || old('category') === '')>Choose type</option>
                <option value="suggestion" @selected(old('category') === 'suggestion')>Suggestion</option>
                <option value="feature" @selected(old('category') === 'feature')>Feature Request</option>
                <option value="bug" @selected(old('category') === 'bug')>Bug / Problem</option>
                <option value="experience" @selected(old('category') === 'experience')>Seller Experience</option>
                <option value="other" @selected(old('category') === 'other')>Other</option>
              </select>
              @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Short Title</label>
              <input type="text" name="title" maxlength="120" value="{{ old('title') }}"
                     class="form-control @error('title') is-invalid @enderror"
                     placeholder="Example: Improve order cutoff controls" required>
              <div class="form-text text-end"><span data-count-for="sellerFeedbackTitle">0</span>/120</div>
              @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Details</label>
              <textarea name="message" rows="8" maxlength="1200"
                        class="form-control @error('message') is-invalid @enderror"
                        placeholder="Describe what happened, what you expected, or what would make selling easier." required>{{ old('message') }}</textarea>
              <div class="form-text d-flex justify-content-between">
                <span>Include order numbers or page names when helpful.</span>
                <span><span data-count-for="sellerFeedbackMessage">0</span>/1200</span>
              </div>
              @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Screenshots / Photos <span class="text-muted fw-normal">(optional)</span></label>
              <input type="file"
                     id="sellerFeedbackAttachments"
                     name="attachments[]"
                     class="form-control @error('attachments') is-invalid @enderror @error('attachments.*') is-invalid @enderror"
                     accept="image/jpeg,image/png,image/webp"
                     multiple
                     data-size-preview-attached="1"
                     onchange="handleSellerFeedbackAttachments(this, 'sellerFeedbackAttachmentStrip', 'sellerFeedbackAttachmentNote')">
              <div class="form-text d-flex justify-content-between flex-wrap gap-1">
                <span>Attach up to 5 images. JPG, PNG, or WebP only.</span>
                <span id="sellerFeedbackAttachmentNote">0/5 selected</span>
              </div>
              @error('attachments')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              @error('attachments.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              <div id="sellerFeedbackAttachmentStrip" class="d-flex flex-wrap gap-2 mt-2"></div>
            </div>

            <button type="submit" class="btn btn-primary">
              <i class="bi bi-send-fill me-1"></i>Submit Feedback
            </button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card h-100" style="border:1.5px solid var(--gray-100);border-radius:14px">
        <div class="card-header d-flex align-items-center gap-2">
          <i class="bi bi-clock-history" style="color:var(--primary)"></i>
          <span class="fw-bold">Recent Submissions</span>
        </div>
        <div class="card-body">
          @forelse($recentFeedback as $item)
            <div class="p-3 mb-2" style="border:1.5px solid var(--gray-100);border-radius:10px;background:#fff">
              <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                <strong class="small">{{ $item->title }}</strong>
                <span class="badge {{ $item->status === 'done' ? 'text-bg-success' : 'text-bg-warning' }}">
                  {{ $item->status === 'done' ? 'Done' : 'Not yet' }}
                </span>
              </div>
              <div class="text-muted" style="font-size:.76rem">{{ ucfirst($item->category) }} - {{ \Carbon\Carbon::parse($item->created_at)->format('M d, Y') }}</div>
              @if($item->admin_note)
                <div class="mt-2 small" style="background:var(--gray-50);border-radius:8px;padding:.55rem .65rem">{{ $item->admin_note }}</div>
              @endif
            </div>
          @empty
            <div class="cs-empty py-4">
              <i class="bi bi-chat-square-text cs-empty-icon"></i>
              <div class="cs-empty-title">No feedback yet</div>
              <div class="cs-empty-sub">Your submitted seller feedback will appear here.</div>
            </div>
          @endforelse
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  function bindCounter(selector, counterSelector) {
    var input = document.querySelector(selector);
    var counter = document.querySelector(counterSelector);
    if (!input || !counter) return;
    function update() { counter.textContent = input.value.length; }
    input.addEventListener('input', update);
    update();
  }
  bindCounter('input[name="title"]', '[data-count-for="sellerFeedbackTitle"]');
  bindCounter('textarea[name="message"]', '[data-count-for="sellerFeedbackMessage"]');
});

async function compressSellerFeedbackFile(file) {
  var maxPx = 1200;
  var quality = 0.80;
  var imageUrl = URL.createObjectURL(file);

  try {
    var img = await new Promise(function(resolve, reject) {
      var image = new Image();
      image.onload = function() { resolve(image); };
      image.onerror = reject;
      image.src = imageUrl;
    });

    var scale = Math.min(maxPx / img.width, maxPx / img.height, 1);
    var canvas = document.createElement('canvas');
    canvas.width = Math.round(img.width * scale);
    canvas.height = Math.round(img.height * scale);
    canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);

    var blob = await new Promise(function(resolve) {
      canvas.toBlob(resolve, 'image/jpeg', quality);
    });
    if (!blob || blob.size >= file.size) return { file: file, originalSize: file.size, compressedSize: file.size };

    var cleanName = file.name.replace(/\.[^.]+$/, '') + '.jpg';
    return {
      file: new File([blob], cleanName, { type: 'image/jpeg', lastModified: Date.now() }),
      originalSize: file.size,
      compressedSize: blob.size
    };
  } catch (e) {
    return { file: file, originalSize: file.size, compressedSize: file.size };
  } finally {
    URL.revokeObjectURL(imageUrl);
  }
}

function formatSellerFeedbackSize(bytes) {
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / 1024 / 1024).toFixed(2) + ' MB';
}

async function handleSellerFeedbackAttachments(input, stripId, noteId) {
  var strip = document.getElementById(stripId);
  var note = document.getElementById(noteId);
  if (!strip) return;

  var valid = [];
  var files = Array.from(input.files || []);
  var maxFiles = 5;
  var maxSize = 5 * 1024 * 1024;
  var allowed = ['image/jpeg', 'image/png', 'image/webp'];
  var rejected = [];

  for (var i = 0; i < files.length; i++) {
    var file = files[i];
    if (valid.length >= maxFiles) { rejected.push(file.name + ' (limit reached)'); continue; }
    if (allowed.indexOf(file.type) === -1) { rejected.push(file.name + ' (unsupported type)'); continue; }
    if (file.size > maxSize) { rejected.push(file.name + ' (over 5MB)'); continue; }
    valid.push(await compressSellerFeedbackFile(file));
  }

  var dt = new DataTransfer();
  valid.forEach(function(item) { dt.items.add(item.file); });
  input.files = dt.files;

  strip.innerHTML = '';
  valid.forEach(function(item, idx) {
    var file = item.file;
    var url = URL.createObjectURL(file);
    var saved = item.originalSize > item.compressedSize ? Math.round((1 - item.compressedSize / item.originalSize) * 100) : 0;
    var card = document.createElement('div');
    card.style.cssText = 'width:116px;border:1.5px solid var(--gray-100);border-radius:10px;background:#fff;padding:5px;position:relative';
    card.innerHTML =
      '<img src="' + url + '" style="width:104px;height:62px;object-fit:cover;border-radius:7px;display:block">' +
      '<button type="button" aria-label="Remove image" style="position:absolute;top:-7px;right:-7px;width:22px;height:22px;border-radius:50%;border:0;background:#ef4444;color:#fff;font-size:.75rem;line-height:1">x</button>' +
      '<div style="font-size:.62rem;color:#6b7280;margin-top:3px;line-height:1.25">' +
        'Original: ' + formatSellerFeedbackSize(item.originalSize) + '<br>' +
        '<span style="color:#059669">Saved: ' + formatSellerFeedbackSize(item.compressedSize) + (saved ? ' (-' + saved + '%)' : '') + '</span>' +
      '</div>';
    card.querySelector('button').onclick = function() {
      valid.splice(idx, 1);
      var next = new DataTransfer();
      valid.forEach(function(item) { next.items.add(item.file); });
      input.files = next.files;
      handleSellerFeedbackAttachments(input, stripId, noteId);
      URL.revokeObjectURL(url);
    };
    strip.appendChild(card);
  });

  if (note) {
    note.textContent = valid.length + '/5 selected' + (rejected.length ? ' - skipped ' + rejected.length : '');
    note.style.color = rejected.length ? '#dc2626' : '';
  }
}
</script>
@endpush
