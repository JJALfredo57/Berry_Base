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
          <form method="POST" action="{{ route('seller.feedback.store') }}">
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
</script>
@endpush
