@extends('layouts.app')
@section('content')
<div class="container-fluid py-4">
  <h4 class="fw-bold mb-4"><i class="bi bi-chat-dots me-2" style="color:var(--primary)"></i>Messages</h4>

  @forelse($threads as $t)
  <a href="{{ route('customer.messages.thread', $t->order_id) }}" class="text-decoration-none">
    <div class="card mb-2 {{ $t->unread_count > 0 ? 'border-start border-3' : '' }}" style="{{ $t->unread_count > 0 ? 'border-left-color:var(--primary)!important' : '' }}">
      <div class="card-body d-flex align-items-center gap-3 p-3">
        <div style="width:44px;height:44px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="bi bi-shop text-white"></i>
        </div>
        <div class="flex-grow-1 overflow-hidden">
          <div class="d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Order #{{ $t->order_id }} — {{ $t->product_name }}</span>
            <small class="text-muted ms-2 flex-shrink-0">{{ $t->last_time ? \Carbon\Carbon::parse($t->last_time)->diffForHumans() : '' }}</small>
          </div>
          <div class="text-muted small text-truncate">{{ $t->last_message ?? 'No messages yet.' }}</div>
        </div>
        @if($t->unread_count > 0)
          <span class="badge rounded-pill" style="background:var(--primary)">{{ $t->unread_count }}</span>
        @endif
      </div>
    </div>
  </a>
  @empty
  <div class="card text-center py-5">
    <div class="card-body">
      <i class="bi bi-chat-slash" style="font-size:3rem;color:#ddd"></i>
      <p class="text-muted mt-3 mb-3">No messages yet.</p>
      <a href="{{ route('customer.orders') }}" class="btn btn-primary">View My Orders</a>
    </div>
  </div>
  @endforelse
</div>
@endsection
