@extends('layouts.app')

@section('content')
@php
  $isPublicFeedback = $isPublicFeedback ?? false;
  $feedbackStoreRoute = $isPublicFeedback ? route('guest.feedback.store') : route('customer.feedback.store');
  $backRoute = $isPublicFeedback ? route('catalog') : route('customer.dashboard');
@endphp
<div class="container-fluid py-4">
  <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-4">
    <div>
      <h4 class="fw-bold mb-1"><i class="bi bi-chat-square-heart me-2" style="color:var(--primary)"></i>Feedback & Suggestions</h4>
      <p class="text-muted small mb-0">Tell us what works, what feels confusing, or what we should improve next.</p>
    </div>
    <a href="{{ $backRoute }}" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>{{ $isPublicFeedback ? 'Catalog' : 'Dashboard' }}
    </a>
  </div>

  @if(session('msg'))
    <div class="alert alert-success border-0"><i class="bi bi-check-circle-fill me-2"></i>{{ session('msg') }}</div>
  @endif

  <div class="row g-4">
    <div class="{{ $isPublicFeedback ? 'col-lg-8 col-xl-7 mx-auto' : 'col-lg-7' }}">
      <div class="card">
        <div class="card-header">
          <i class="bi bi-pencil-square me-2" style="color:var(--primary)"></i>Send Feedback
        </div>
        <div class="card-body">
          <form method="POST" action="{{ $feedbackStoreRoute }}" enctype="multipart/form-data">
            @csrf
            @if($isPublicFeedback)
            <div class="row g-2 mb-3">
              <div class="col-md-6">
                <label class="form-label">Name <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" name="name" maxlength="120" value="{{ old('name') }}"
                       class="form-control @error('name') is-invalid @enderror"
                       placeholder="Your name">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label">Email <span class="text-muted fw-normal">(optional)</span></label>
                <input type="email" name="email" maxlength="150" value="{{ old('email') }}"
                       class="form-control @error('email') is-invalid @enderror"
                       placeholder="you@example.com">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>
            @endif
            <div class="mb-3">
              <label class="form-label">Type</label>
              <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                @if($isPublicFeedback)
                  <option value="" @selected(old('category') === null || old('category') === '')>All Types</option>
                @endif
                @foreach([
                  'suggestion' => 'Suggestion',
                  'feature' => 'Feature Request',
                  'bug' => 'Bug / Problem',
                  'experience' => $isPublicFeedback ? 'Experience' : 'Customer Experience',
                  'other' => 'Other',
                ] as $value => $label)
                  <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                @endforeach
              </select>
              @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
              <label class="form-label">Short Title</label>
              <input type="text" name="title" maxlength="120" value="{{ old('title') }}"
                     class="form-control @error('title') is-invalid @enderror"
                     placeholder="Example: Improve checkout address selection" required>
              <div class="form-text text-end"><span data-count-for="feedbackTitle">0</span>/120</div>
              @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
              <label class="form-label">Details</label>
              <textarea name="message" rows="7" maxlength="1000"
                        class="form-control @error('message') is-invalid @enderror"
                        placeholder="Share the issue, suggestion, or improvement you want us to review." required>{{ old('message') }}</textarea>
              <div class="form-text d-flex justify-content-between">
                <span>Max 1000 characters.</span>
                <span><span data-count-for="feedbackMessage">0</span>/1000</span>
              </div>
              @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
              <label class="form-label">Screenshots / Photos <span class="text-muted fw-normal">(optional)</span></label>
              <input type="file"
                     id="feedbackAttachments"
                     name="attachments[]"
                     class="form-control @error('attachments') is-invalid @enderror @error('attachments.*') is-invalid @enderror"
                     accept="image/jpeg,image/png,image/webp"
                     multiple
                     data-size-preview-attached="1"
                     onchange="handleFeedbackAttachments(this, 'feedbackAttachmentStrip', 'feedbackAttachmentNote')">
              <div class="form-text d-flex justify-content-between flex-wrap gap-1">
                <span>Attach up to 5 images. JPG, PNG, or WebP only.</span>
                <span id="feedbackAttachmentNote">0/5 selected</span>
              </div>
              @error('attachments')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              @error('attachments.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              <div id="feedbackAttachmentStrip" class="d-flex flex-wrap gap-2 mt-2"></div>
            </div>

            <button type="submit" class="btn btn-primary">
              <i class="bi bi-send-fill me-1"></i>Submit Feedback
            </button>
          </form>
        </div>
      </div>
    </div>

    @if(!$isPublicFeedback)
    <div class="col-lg-5">
      <div class="card h-100">
        <div class="card-header">
          <i class="bi bi-clock-history me-2" style="color:var(--primary)"></i>Your Recent Feedback
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
              <div class="cs-empty-sub">Your submitted suggestions will appear here.</div>
            </div>
          @endforelse
        </div>
      </div>
    </div>
    @endif
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
  bindCounter('input[name="title"]', '[data-count-for="feedbackTitle"]');
  bindCounter('textarea[name="message"]', '[data-count-for="feedbackMessage"]');
});

async function compressFeedbackFile(file) {
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

function formatFeedbackSize(bytes) {
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / 1024 / 1024).toFixed(2) + ' MB';
}

async function handleFeedbackAttachments(input, stripId, noteId) {
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
    valid.push(await compressFeedbackFile(file));
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
        'Original: ' + formatFeedbackSize(item.originalSize) + '<br>' +
        '<span style="color:#059669">Saved: ' + formatFeedbackSize(item.compressedSize) + (saved ? ' (-' + saved + '%)' : '') + '</span>' +
      '</div>';
    card.querySelector('button').onclick = function() {
      valid.splice(idx, 1);
      var next = new DataTransfer();
      valid.forEach(function(item) { next.items.add(item.file); });
      input.files = next.files;
      handleFeedbackAttachments(input, stripId, noteId);
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
