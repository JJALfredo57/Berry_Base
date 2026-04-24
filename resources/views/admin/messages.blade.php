@extends('layouts.app')
@section('content')
<div>
  <h4 class="fw-bold mb-4"><i class="bi bi-chat-dots me-2" style="color:var(--primary)"></i>Customer Messages</h4>

  @forelse($threads as $t)
  <a href="{{ route('admin.messages.thread', $t->order_id) }}" class="text-decoration-none">
    <div class="card mb-2 {{ $t->unread_count > 0 ? '' : '' }}" style="{{ $t->unread_count > 0 ? 'border-left:3px solid var(--primary)' : '' }}">
      <div class="card-body d-flex align-items-center gap-3 p-3">
        <div style="width:44px;height:44px;border-radius:50%;background:{{ $t->unread_count > 0 ? 'var(--primary)' : '#e9ecef' }};display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="bi bi-person {{ $t->unread_count > 0 ? 'text-white' : 'text-muted' }}"></i>
        </div>
        <div class="flex-grow-1 overflow-hidden">
          <div class="d-flex justify-content-between align-items-center">
            <span class="fw-semibold {{ $t->unread_count > 0 ? '' : 'text-muted' }}">{{ $t->fullname }} — Order #{{ $t->order_id }}</span>
            <small class="text-muted ms-2 flex-shrink-0">{{ $t->last_time ? \Carbon\Carbon::parse($t->last_time)->diffForHumans() : '' }}</small>
          </div>
          <div class="text-muted small text-truncate">{{ $t->last_message ?? 'No messages.' }}</div>
        </div>
        @if($t->unread_count > 0)
          <span class="badge rounded-pill" style="background:var(--primary)">{{ $t->unread_count }}</span>
        @endif
      </div>
    </div>
  </a>
  @empty
  <div class="card text-center py-5">
    <i class="bi bi-chat-slash" style="font-size:3rem;color:#ddd"></i>
    <p class="text-muted mt-3">No messages yet.</p>
  </div>
  @endforelse
</div>
@endsection
