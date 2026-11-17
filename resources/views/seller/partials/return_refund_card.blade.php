@php
  $state = $states[$item->status] ?? [ucfirst(str_replace('_', ' ', $item->status)), 'progress'];
  $canReturn = $item->source === 'return' && $item->status === 'submitted';
  $canReceive = $item->source === 'return' && $item->status === 'returned_to_seller';
  $canRefund = $item->source === 'refund' && in_array($item->status, ['pending', 'pending_seller_approval'], true);
  $rejectId = 'reject'.$item->source.$item->record_id;
@endphp
<details class="rr-card">
  <summary><div class="rr-main"><div class="rr-ref">{{ $item->reference }} · Order #{{ $item->order_id }}</div><div class="rr-meta"><span class="rr-badge">{{ $item->source === 'return' ? 'Return request' : 'Cancellation refund' }}</span><span>{{ $item->customer_name ?: 'Customer' }}</span><strong>₱{{ number_format($item->refund_amount, 2) }}</strong></div></div><span class="rr-state {{ $state[1] }}">{{ $state[0] }}</span></summary>
  <div class="rr-body">
    @if($item->description)<p class="small">{{ $item->description }}</p>@endif
    <div class="rr-grid"><div><span class="rr-label">Customer</span>{{ $item->customer_name ?: 'Customer' }}</div><div><span class="rr-label">Refund amount</span>₱{{ number_format($item->refund_amount, 2) }}</div><div><span class="rr-label">Fulfillment</span>{{ $item->fulfillment_type ?: '—' }}</div></div>
    @if($item->decision_note)<div class="rr-note"><strong>Decision note:</strong> {{ $item->decision_note }}</div>@endif
    @if(!$history && ($canReturn || $canReceive || $canRefund))
      <div class="rr-actions">
        @if($canReceive)
          <form method="POST" action="{{ route('seller.return_cases.receive', $item->record_id) }}" data-prevent-double-submit>@csrf<button class="btn btn-primary" data-loading-text="Confirming...">Confirm cake received</button></form>
        @elseif($canReturn)
          <form method="POST" action="{{ route('seller.return_cases.decide', $item->record_id) }}" data-prevent-double-submit>@csrf<input type="hidden" name="decision" value="approved"><button class="btn btn-success" data-loading-text="Approving...">Approve return</button></form>
        @else
          <form method="POST" action="{{ route('seller.return_cases.refunds.approve', $item->record_id) }}" data-prevent-double-submit>@csrf<button class="btn btn-success" data-loading-text="Approving...">Approve refund</button></form>
        @endif
        <button class="btn btn-outline-danger" type="button" data-reject="{{ $rejectId }}">Reject request</button>
      </div>
      <form id="{{ $rejectId }}" class="rr-reject" method="POST" action="{{ $item->source === 'return' ? route('seller.return_cases.decide', $item->record_id) : route('seller.return_cases.refunds.reject', $item->record_id) }}" data-prevent-double-submit>
        @csrf
        @if($item->source === 'return')<input type="hidden" name="decision" value="rejected">@endif
        <label class="form-label mt-2">Reason for rejection</label><textarea class="form-control" name="note" minlength="{{ $item->source === 'return' ? 10 : 5 }}" maxlength="500" required></textarea><button class="btn btn-danger mt-2" data-loading-text="Rejecting...">Confirm rejection</button>
      </form>
    @endif
  </div>
</details>
