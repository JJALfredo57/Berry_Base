@extends('layouts.app')

@section('content')
<style>
.feedback-attachments{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:1rem}
.feedback-attachment-thumb{width:74px;height:74px;border-radius:10px;object-fit:cover;border:2px solid var(--gray-100);cursor:pointer;transition:transform .15s,box-shadow .15s}
.feedback-attachment-thumb:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(0,0,0,.12)}
.feedback-lightbox{display:none;position:fixed;inset:0;z-index:9999;background:rgba(17,24,39,.92);align-items:center;justify-content:center;padding:62px 24px 34px}
.feedback-lightbox.open{display:flex}
.feedback-lightbox img{max-width:min(94vw,1180px);max-height:82vh;object-fit:contain;border-radius:14px;box-shadow:0 24px 70px rgba(0,0,0,.45);background:#fff}
.feedback-lb-back{position:absolute;top:18px;left:18px;height:40px;border-radius:999px;border:0;background:#fff;color:#111827;display:flex;align-items:center;justify-content:center;gap:7px;padding:0 14px;font-weight:700}
.feedback-lb-close{position:absolute;top:18px;right:18px;width:40px;height:40px;border-radius:50%;border:0;background:#fff;color:#111827;display:flex;align-items:center;justify-content:center}
.feedback-lb-nav{position:absolute;top:50%;transform:translateY(-50%);width:44px;height:44px;border-radius:50%;border:0;background:#fff;color:#111827;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 24px rgba(0,0,0,.22)}
.feedback-lb-nav.prev{left:18px}
.feedback-lb-nav.next{right:18px}
.feedback-lb-counter{position:absolute;bottom:14px;left:50%;transform:translateX(-50%);background:rgba(255,255,255,.92);color:#111827;border-radius:999px;padding:5px 12px;font-size:.8rem;font-weight:700}
@media(max-width:640px){.feedback-lightbox{padding:72px 10px 42px}.feedback-lb-nav{top:auto;bottom:14px;transform:none}.feedback-lb-nav.prev{left:14px}.feedback-lb-nav.next{right:14px}.feedback-lb-counter{bottom:18px}}
</style>
<div class="cs-page-header">
  <div>
    <h4 class="cs-page-title"><i class="bi bi-chat-square-heart me-2" style="color:var(--primary)"></i>Platform Feedback</h4>
    <p class="cs-page-sub">Review customer, guest, and seller suggestions, then keep improvement requests organized.</p>
  </div>
</div>

<div class="row g-3 mb-4">
  @foreach([
    ['All Feedback', $stats['total'], 'bi-inbox', 'var(--primary)', 'var(--primary-bg)'],
    ['Not Yet', $stats['open'], 'bi-hourglass-split', '#d97706', '#fff7ed'],
    ['Done', $stats['done'], 'bi-check2-circle', '#16a34a', '#f0fdf4'],
  ] as [$label, $value, $icon, $color, $bg])
    <div class="col-6 col-md-4">
      <div class="cs-stat-card h-100">
        <div class="cs-stat-icon" style="background:{{ $bg }}"><i class="bi {{ $icon }}" style="color:{{ $color }}"></i></div>
        <div>
          <div class="cs-stat-num" style="color:{{ $color }}">{{ number_format($value) }}</div>
          <div class="cs-stat-label">{{ $label }}</div>
        </div>
      </div>
    </div>
  @endforeach
</div>

@if(session('msg'))<div class="alert alert-success border-0"><i class="bi bi-check-circle-fill me-2"></i>{{ session('msg') }}</div>@endif
@if(session('err'))<div class="alert alert-danger border-0"><i class="bi bi-exclamation-circle-fill me-2"></i>{{ session('err') }}</div>@endif

<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
    <span><i class="bi bi-filter-square me-2" style="color:var(--primary)"></i>Feedback Inbox</span>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <div class="cs-search-bar" style="width:min(280px,70vw)">
        <input type="text" class="form-control form-control-sm" placeholder="Search feedback..."
               value="{{ $search }}" oninput="pgSearch(this.value)">
      </div>
      <select class="form-select form-select-sm" style="width:140px" onchange="pgFilter('status', this.value)">
        <option value="all" @selected($status === 'all')>All Status</option>
        <option value="open" @selected($status === 'open')>Not yet</option>
        <option value="done" @selected($status === 'done')>Done</option>
      </select>
      <select class="form-select form-select-sm" style="width:165px" onchange="pgFilter('category', this.value)">
        <option value="all" @selected($category === 'all')>All Types</option>
        <option value="suggestion" @selected($category === 'suggestion')>Suggestion</option>
        <option value="feature" @selected($category === 'feature')>Feature Request</option>
        <option value="bug" @selected($category === 'bug')>Bug / Problem</option>
        <option value="experience" @selected($category === 'experience')>Experience</option>
        <option value="other" @selected($category === 'other')>Other</option>
      </select>
      @if($hasSource ?? false)
      <select class="form-select form-select-sm" style="width:145px" onchange="pgFilter('source', this.value)">
        <option value="all" @selected($source === 'all')>All Sources</option>
        <option value="customer" @selected($source === 'customer')>Customers</option>
        <option value="guest" @selected($source === 'guest')>Guests</option>
        <option value="seller" @selected($source === 'seller')>Sellers</option>
      </select>
      @endif
    </div>
  </div>

  <div class="card-body p-0">
    @forelse($feedback as $item)
      @php
        $name = $item->user_fullname ?? $item->name ?? $item->user_username ?? 'Customer';
        $email = $item->user_email ?? $item->email;
        $categoryLabels = [
          'suggestion' => 'Suggestion',
          'feature' => 'Feature Request',
          'bug' => 'Bug / Problem',
          'experience' => 'Experience',
          'other' => 'Other',
        ];
        $sourceRole = $item->source_role ?? (!empty($item->user_id) ? 'customer' : 'guest');
        $sourceLabels = ['customer' => 'Customer', 'guest' => 'Guest', 'seller' => 'Seller'];
        $sourceClasses = ['customer' => 'text-bg-primary', 'guest' => 'text-bg-secondary', 'seller' => 'text-bg-info'];
      @endphp
      <div class="p-4" style="border-bottom:1.5px solid var(--gray-100)">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-2">
          <div style="min-width:0">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
              <h6 class="fw-bold mb-0">{{ $item->title }}</h6>
              <span class="badge {{ $sourceClasses[$sourceRole] ?? 'text-bg-light' }}">{{ $sourceLabels[$sourceRole] ?? ucfirst($sourceRole) }}</span>
              <span class="badge text-bg-light">{{ $categoryLabels[$item->category] ?? ucfirst($item->category) }}</span>
              <span class="badge {{ $item->status === 'done' ? 'text-bg-success' : 'text-bg-warning' }}">
                {{ $item->status === 'done' ? 'Done' : 'Not yet' }}
              </span>
            </div>
            <div class="text-muted" style="font-size:.78rem">
              {{ $name }}@if($email) - {{ $email }}@endif - {{ \Carbon\Carbon::parse($item->created_at)->format('M d, Y h:i A') }}
            </div>
          </div>
          <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#feedbackManage{{ $item->id }}">
            <i class="bi bi-pencil-square me-1"></i>Manage
          </button>
        </div>

        <p class="mb-3" style="font-size:.9rem;line-height:1.65;white-space:pre-wrap">{{ $item->message }}</p>

        @php
          $attachments = [];
          if (!empty($item->attachment_paths ?? null)) {
            $decoded = json_decode($item->attachment_paths, true);
            $attachments = is_array($decoded) ? $decoded : [];
          }
        @endphp
        @if(count($attachments) > 0)
          <div class="feedback-attachments">
            @foreach($attachments as $attachment)
              <img src="{{ $attachment }}"
                   alt="Feedback attachment"
                   class="feedback-attachment-thumb"
                   data-feedback-gallery='@json($attachments)'
                   data-feedback-index="{{ $loop->index }}"
                   onerror="this.style.display='none'">
            @endforeach
          </div>
        @endif

        @if($item->admin_note)
          <div class="mb-3" style="background:var(--gray-50);border:1.5px solid var(--gray-100);border-radius:10px;padding:.75rem .9rem;font-size:.84rem">
            <strong>Admin note:</strong> {{ $item->admin_note }}
          </div>
        @endif

        <div class="collapse" id="feedbackManage{{ $item->id }}">
          <form method="POST" action="{{ route('superadmin.feedback.update', $item->id) }}" class="p-3" style="background:var(--gray-50);border-radius:10px">
            @csrf
            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                  <option value="open" @selected($item->status === 'open')>Not yet</option>
                  <option value="done" @selected($item->status === 'done')>Done</option>
                </select>
              </div>
              <div class="col-md-9">
                <label class="form-label">Note</label>
                <textarea name="admin_note" rows="2" maxlength="1000" class="form-control form-control-sm" placeholder="Optional internal note or resolution update">{{ $item->admin_note }}</textarea>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary btn-sm">
                  <i class="bi bi-save me-1"></i>Save Update
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    @empty
      <div class="cs-empty py-5">
        <i class="bi bi-inbox cs-empty-icon"></i>
        <div class="cs-empty-title">No feedback found</div>
        <div class="cs-empty-sub">Try changing your search or filters.</div>
      </div>
    @endforelse
  </div>
</div>

{{ $feedback->links('vendor.pagination.custom') }}

<div id="feedbackAttachmentLightbox" class="feedback-lightbox" onclick="if(event.target===this)closeFeedbackAttachment()">
  <button type="button" class="feedback-lb-back" onclick="closeFeedbackAttachment()" aria-label="Back to feedback">
    <i class="bi bi-arrow-left"></i>Back
  </button>
  <button type="button" class="feedback-lb-close" onclick="closeFeedbackAttachment()" aria-label="Close preview"><i class="bi bi-x-lg"></i></button>
  <button type="button" class="feedback-lb-nav prev" onclick="moveFeedbackAttachment(-1)" aria-label="Previous image"><i class="bi bi-chevron-left"></i></button>
  <img id="feedbackAttachmentLightboxImg" src="" alt="Feedback attachment preview">
  <button type="button" class="feedback-lb-nav next" onclick="moveFeedbackAttachment(1)" aria-label="Next image"><i class="bi bi-chevron-right"></i></button>
  <div id="feedbackAttachmentCounter" class="feedback-lb-counter"></div>
</div>

<script>
var feedbackAttachmentGallery = [];
var feedbackAttachmentIndex = 0;

function renderFeedbackAttachment() {
  var img = document.getElementById('feedbackAttachmentLightboxImg');
  var counter = document.getElementById('feedbackAttachmentCounter');
  var prev = document.querySelector('.feedback-lb-nav.prev');
  var next = document.querySelector('.feedback-lb-nav.next');
  if (!img || !counter) return;

  var total = feedbackAttachmentGallery.length;
  img.src = total ? feedbackAttachmentGallery[feedbackAttachmentIndex] : '';
  counter.textContent = total ? (feedbackAttachmentIndex + 1) + ' / ' + total : '';
  if (prev) prev.style.display = total > 1 ? 'flex' : 'none';
  if (next) next.style.display = total > 1 ? 'flex' : 'none';
}

function openFeedbackAttachment(gallery, index) {
  var box = document.getElementById('feedbackAttachmentLightbox');
  if (!box) return;
  if (box.parentNode !== document.body) {
    document.body.appendChild(box);
  }
  feedbackAttachmentGallery = Array.isArray(gallery) ? gallery : [gallery];
  feedbackAttachmentIndex = Math.max(0, Math.min(parseInt(index || 0, 10), feedbackAttachmentGallery.length - 1));
  renderFeedbackAttachment();
  box.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function moveFeedbackAttachment(step) {
  if (!feedbackAttachmentGallery.length) return;
  feedbackAttachmentIndex = (feedbackAttachmentIndex + step + feedbackAttachmentGallery.length) % feedbackAttachmentGallery.length;
  renderFeedbackAttachment();
}

function closeFeedbackAttachment() {
  var box = document.getElementById('feedbackAttachmentLightbox');
  var img = document.getElementById('feedbackAttachmentLightboxImg');
  if (!box || !img) return;
  box.classList.remove('open');
  img.src = '';
  feedbackAttachmentGallery = [];
  feedbackAttachmentIndex = 0;
  document.body.style.overflow = '';
}
document.addEventListener('click', function(e) {
  var thumb = e.target.closest('.feedback-attachment-thumb');
  if (!thumb) return;
  var gallery = [];
  try { gallery = JSON.parse(thumb.dataset.feedbackGallery || '[]'); } catch (err) { gallery = [thumb.src]; }
  openFeedbackAttachment(gallery, thumb.dataset.feedbackIndex || 0);
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeFeedbackAttachment();
  if (e.key === 'ArrowLeft') moveFeedbackAttachment(-1);
  if (e.key === 'ArrowRight') moveFeedbackAttachment(1);
});
</script>
@endsection
