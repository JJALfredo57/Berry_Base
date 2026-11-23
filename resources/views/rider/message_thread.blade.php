@extends('rider.messages_layout')
@section('title', 'Conversation')
@section('content')
<main class="chat-shell thread-shell" id="riderThread" data-index="{{ route('rider.delivery_messages.index', $order->id) }}" data-send="{{ route('rider.delivery_messages.send', $order->id) }}" data-login="{{ route('login') }}">
  <header class="chat-head thread-head">
    <a class="icon-button" href="{{ route('rider.messages') }}" aria-label="Back to chats"><i class="bi bi-arrow-left" aria-hidden="true"></i></a>
    @if($customer?->profile_photo)<img class="avatar" src="{{ $customer->profile_photo }}" alt="">@else<span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($customer?->fullname ?: 'Customer', 0, 1)) }}</span>@endif
    <div class="head-copy"><h1>{{ $customer?->fullname ?: 'Customer' }}</h1><p>Delivery conversation</p></div>
  </header>
  <div class="order-context"><div>Order #{{ $order->id }}</div><span>{{ $order->status }}</span></div>
  <section class="message-list" id="messageList" aria-label="Messages" aria-live="polite" aria-busy="true"><p class="thread-empty">Loading conversation…</p></section>
  <form class="composer" id="messageForm" enctype="multipart/form-data">
    <div class="refresh-status" id="threadStatus" role="status" hidden><span></span> <button type="button" id="retryMessages">Retry</button></div>
    <div class="compose-error" id="messageError" role="alert"></div>
    <div class="attachment" id="messageAttachment" hidden><img alt="Selected image preview"><span>Image attached</span><button type="button" id="removeAttachment">Remove</button></div>
    <div class="compose-row">
      <label class="attach-button" for="messageImage" aria-label="Attach image"><i class="bi bi-image" aria-hidden="true"></i><input class="visually-hidden" id="messageImage" name="image" type="file" accept="image/jpeg,image/png,image/webp" aria-label="Attach image"></label>
      <textarea name="message" maxlength="1000" rows="1" placeholder="Type a message…" aria-label="Message"></textarea>
      <button class="send-button" type="submit" aria-label="Send message" disabled><i class="bi bi-send-fill" aria-hidden="true"></i></button>
    </div>
  </form>
</main>
<dialog class="image-viewer" id="messageImageViewer"><form method="dialog"><button class="icon-button" aria-label="Close image"><i class="bi bi-x-lg" aria-hidden="true"></i></button></form><img alt="Message attachment"></dialog>
@endsection
@section('scripts')
<script src="{{ asset('js/rider-messages.js') }}?v=1" defer></script>
@endsection
