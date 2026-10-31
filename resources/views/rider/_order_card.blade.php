@php
  $bucket = $order->rider_bucket;
  $isPending = ($order->rider_assignment_status ?? '') === 'pending';
  $isSurpriseDelivery = !empty($order->is_surprise_delivery);
  $surpriseContact = $isSurpriseDelivery ? app(\App\Services\SurpriseContactService::class)->details($order) : null;
  $displayName = $isSurpriseDelivery ? ($surpriseContact['sender_name'] ?? 'Sender') : ($order->guest_name ?? 'Customer');
  $displayPhone = $isSurpriseDelivery ? ($surpriseContact['sender_phone'] ?? null) : ($order->guest_phone ?? null);
  $deliveryAddr = $isSurpriseDelivery ? ($order->recipient_address ?? $order->delivery_address ?? 'No delivery address') : ($order->delivery_address ?? 'No delivery address');
  $deliveryLat = $isSurpriseDelivery ? ($order->recipient_latitude ?? $order->latitude ?? null) : ($order->latitude ?? null);
  $deliveryLng = $isSurpriseDelivery ? ($order->recipient_longitude ?? $order->longitude ?? null) : ($order->longitude ?? null);
  $badgeClass = $isPending ? 'b-pending' : (str_contains($bucket, 'Remit') || str_contains($bucket, 'Rejected') ? 'b-remit' : (str_contains($bucket, 'Waiting') ? 'b-wait' : 'b-active'));
  $expiresIso = $isPending && !empty($order->rider_assignment_expires_at)
    ? \Illuminate\Support\Carbon::parse($order->rider_assignment_expires_at)->toIso8601String()
    : null;
@endphp
<article class="order {{ $isPending ? 'order-pending' : '' }}" data-order-id="{{ $order->id }}">
  <div class="order-head">
    <div class="order-title">
      <div class="order-id">Order #{{ $order->id }}</div>
      <div class="product">{{ $order->product_name }}</div>
    </div>
    <span class="badge {{ $badgeClass }}">{{ $isPending ? 'New request' : $bucket }}</span>
  </div>

  @if($expiresIso)
  <div class="countdown" data-expires="{{ $expiresIso }}">
    <i class="bi bi-hourglass-split"></i>
    <span>Respond within <strong class="countdown-time">--:--</strong></span>
  </div>
  @endif

  <div class="order-body">
    <ul class="detail">
      <li><i class="bi bi-person"></i><span>{{ $displayName }}{{ $displayPhone ? ' · '.$displayPhone : '' }} @if($isSurpriseDelivery)<span class="badge b-pending">Surprise</span>@endif</span></li>
      @if($isSurpriseDelivery)<li><i class="bi bi-gift"></i><span>Contact sender first · Recipient: {{ $surpriseContact['recipient_name'] }}{{ $surpriseContact['backup_phone'] ? ' · backup '.$surpriseContact['backup_phone'] : '' }}</span></li>@endif
      <li><i class="bi bi-geo-alt"></i><span>{{ $deliveryAddr }}</span></li>
      <li><i class="bi bi-calendar-event"></i><span>{{ $order->schedule_date ?? 'No date' }} {{ $order->schedule_time ?? '' }}</span></li>
      @if($order->distance_km !== null)<li><i class="bi bi-signpost"></i><span>{{ number_format($order->distance_km, 2) }} km away</span></li>@endif
      @if($order->remittance_amount)<li><i class="bi bi-cash-stack"></i><span>Remit PHP {{ number_format((float) $order->remittance_amount, 2) }}</span></li>@endif
    </ul>

    <div class="actions">
      @if($isPending)
        <form action="{{ route('rider.assignment.accept', [$order->id, $order->rider_token]) }}" method="POST" data-once>@csrf
          <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-check2-circle"></i>Accept delivery</button>
        </form>
        <button class="btn btn-danger" type="button" data-decline
                data-action="{{ route('rider.assignment.decline', [$order->id, $order->rider_token]) }}"
                data-label="Order #{{ $order->id }}">
          <i class="bi bi-x-circle"></i>Decline
        </button>
      @elseif(($order->status ?? '') === 'Out for Delivery')
        <a class="btn btn-primary btn-lg" href="{{ route('rider.show', [$order->id, $order->rider_token]) }}"><i class="bi bi-arrow-right-circle"></i>Open delivery</a>
        @if($deliveryLat && $deliveryLng)
          <a class="btn btn-outline" href="https://www.google.com/maps/dir/?api=1&destination={{ $deliveryLat }},{{ $deliveryLng }}&travelmode=driving" target="_blank" rel="noopener"><i class="bi bi-map"></i>Directions</a>
        @endif
      @elseif($order->remittance_amount)
        <div class="order-note"><i class="bi bi-cash-stack"></i> Included in dashboard COD remittance</div>
      @else
        <div class="order-note"><i class="bi bi-info-circle"></i> Accepted. Waiting for the seller to release the order.</div>
      @endif
    </div>
  </div>
</article>
