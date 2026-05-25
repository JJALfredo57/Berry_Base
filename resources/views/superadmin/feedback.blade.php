@extends('layouts.app')

@section('content')
<div class="cs-page-header">
  <div>
    <h4 class="cs-page-title"><i class="bi bi-chat-square-heart me-2" style="color:var(--primary)"></i>Customer Feedback</h4>
    <p class="cs-page-sub">Review suggestions, tag what is done, and keep improvement requests organized.</p>
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
      @endphp
      <div class="p-4" style="border-bottom:1.5px solid var(--gray-100)">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-2">
          <div style="min-width:0">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
              <h6 class="fw-bold mb-0">{{ $item->title }}</h6>
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
@endsection
