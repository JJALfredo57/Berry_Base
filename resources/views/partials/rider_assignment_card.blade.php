@php
  $isPending = ($lock->rider_assignment_status ?? '') === 'pending';
  $expiresAt = !empty($lock->rider_assignment_expires_at) ? \Illuminate\Support\Carbon::parse($lock->rider_assignment_expires_at) : null;
@endphp
<div class="rider-assignment-card border rounded-3 p-2 px-3 d-flex flex-wrap align-items-center gap-2 {{ $isPending ? 'border-warning bg-warning-subtle' : 'border-success bg-success-subtle' }}">
  <div class="small flex-grow-1">
    @if($isPending)
      <i class="bi bi-hourglass-split me-1"></i>Waiting for <strong>{{ $lock->rider_name }}</strong> to accept
      @if($expiresAt)<span class="text-muted">(until {{ $expiresAt->format('g:i A') }})</span>@endif
    @else
      <i class="bi bi-check-circle-fill text-success me-1"></i>Accepted by <strong>{{ $lock->rider_name }}</strong>
    @endif
  </div>
  @if($isPending)
  <form action="{{ $cancelRoute }}" method="POST" class="m-0">
    @csrf
    <button type="submit" class="btn btn-outline-danger btn-sm fw-semibold"
            data-cs-confirm="Cancel the assignment to {{ $lock->rider_name }}? The rider will be notified and the request will be removed."
            data-cs-title="Cancel Assignment"
            data-cs-ok="Cancel Assignment">
      <i class="bi bi-x-circle me-1"></i>Cancel assignment
    </button>
  </form>
  @endif
</div>
