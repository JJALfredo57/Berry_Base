@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
  <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-4">
    <div>
      <h4 class="fw-bold mb-1"><i class="bi bi-chat-square-heart me-2" style="color:var(--primary)"></i>Feedback & Suggestions</h4>
      <p class="text-muted small mb-0">Tell us what works, what feels confusing, or what we should improve next.</p>
    </div>
    <a href="{{ route('customer.dashboard') }}" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Dashboard
    </a>
  </div>

  @if(session('msg'))
    <div class="alert alert-success border-0"><i class="bi bi-check-circle-fill me-2"></i>{{ session('msg') }}</div>
  @endif

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header">
          <i class="bi bi-pencil-square me-2" style="color:var(--primary)"></i>Send Feedback
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('customer.feedback.store') }}">
            @csrf
            <div class="mb-3">
              <label class="form-label">Type</label>
              <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                @foreach([
                  'suggestion' => 'Suggestion',
                  'feature' => 'Feature Request',
                  'bug' => 'Bug / Problem',
                  'experience' => 'Customer Experience',
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

            <button type="submit" class="btn btn-primary">
              <i class="bi bi-send-fill me-1"></i>Submit Feedback
            </button>
          </form>
        </div>
      </div>
    </div>

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
</script>
@endpush
