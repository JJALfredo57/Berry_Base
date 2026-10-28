@php $isDeclined = ($alert->state ?? '') === 'declined'; @endphp
<div class="rider-assignment-alert d-flex align-items-start gap-2 rounded-3 border border-danger bg-danger-subtle p-2 px-3 {{ $class ?? '' }}" role="alert">
  <i class="bi bi-exclamation-triangle-fill text-danger mt-1"></i>
  <div class="small">
    <div class="fw-bold text-danger">
      {{ $isDeclined ? 'Rider declined this delivery' : 'Rider did not respond in time' }} — action needed
    </div>
    <div>
      <strong>{{ $alert->rider_name }}</strong>
      {{ $isDeclined ? 'declined' : 'did not respond to' }} Order #{{ $orderId }}.
      @if(!empty($alert->reason))<span class="text-muted">Reason: {{ $alert->reason }}.</span>@endif
      Please assign another rider.
    </div>
  </div>
</div>
