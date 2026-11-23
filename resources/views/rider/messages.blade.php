@extends('rider.messages_layout')
@section('title', 'Chats')
@section('content')
<main class="chat-shell">
  <header class="chat-head"><a class="icon-button" href="{{ route('rider.dashboard') }}" aria-label="Back to dashboard"><i class="bi bi-arrow-left" aria-hidden="true"></i></a><div class="head-copy"><h1>Chats</h1><p>Your delivery conversations</p></div></header>
  <div class="inbox-tools">
    <form class="search-box" method="GET" action="{{ route('rider.messages') }}" role="search">
      <i class="bi bi-search" aria-hidden="true"></i><input name="q" type="search" maxlength="100" value="{{ $search }}" placeholder="Search customer or order" aria-label="Search customer or order"><input type="hidden" name="filter" value="{{ $filter }}"><button type="submit">Search</button>
    </form>
    <nav class="chat-tabs" aria-label="Conversation filter">
      <a href="{{ route('rider.messages', ['filter' => 'all', 'q' => $search]) }}" @if($filter === 'all') aria-current="page" @endif>All</a>
      <a href="{{ route('rider.messages', ['filter' => 'active', 'q' => $search]) }}" @if($filter === 'active') aria-current="page" @endif>Active</a>
    </nav>
  </div>
  <p id="inboxStatus" class="status-note" role="status" hidden>Unable to refresh chats. Your last loaded conversations are still shown.</p>
  <section id="conversationList" aria-label="Conversations">
    @forelse($conversations as $conversation)
      @php $name = $conversation->customer_display_name ?: 'Customer'; @endphp
      <a class="conversation {{ $conversation->delivery_unread_count ? 'unread' : '' }}" href="{{ route('rider.messages.show', $conversation->id) }}">
        @if($conversation->customer_profile_photo)<img class="avatar" src="{{ $conversation->customer_profile_photo }}" alt="" loading="lazy">@else<span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>@endif
        <div class="conversation-copy">
          <div class="conversation-title"><strong>{{ $name }}</strong>@if($conversation->delivery_last_at)<time datetime="{{ \Carbon\Carbon::parse($conversation->delivery_last_at)->toIso8601String() }}">{{ \Carbon\Carbon::parse($conversation->delivery_last_at)->isToday() ? \Carbon\Carbon::parse($conversation->delivery_last_at)->format('g:i A') : \Carbon\Carbon::parse($conversation->delivery_last_at)->format('M j') }}</time>@endif</div>
          <div class="conversation-preview"><p>{{ $conversation->delivery_last_sender === 'rider' ? 'You: ' : '' }}{{ $conversation->delivery_last_message ?: ($conversation->delivery_last_image ? 'Image attachment' : 'Start a conversation') }}</p>@if($conversation->delivery_unread_count)<span class="unread-badge" aria-label="{{ $conversation->delivery_unread_count }} unread messages">{{ $conversation->delivery_unread_count > 99 ? '99+' : $conversation->delivery_unread_count }}</span>@endif</div>
          <div class="order-label">Order #{{ $conversation->id }} · {{ $conversation->status }}</div>
        </div>
      </a>
    @empty
      <div class="empty-state"><i class="bi bi-chat-square-text" aria-hidden="true"></i><h2>{{ $search !== '' ? 'No chats found' : ($filter === 'active' ? 'No active delivery chats' : 'No conversations yet') }}</h2><p>{{ $search !== '' ? 'Try another customer name or order number.' : 'Conversations will appear here when a delivery is ready for chat.' }}</p></div>
    @endforelse
    @if($conversations->hasPages())<nav class="pagination" aria-label="Chat pages">@if($conversations->previousPageUrl())<a href="{{ $conversations->previousPageUrl() }}">Previous</a>@else<span></span>@endif<span>Page {{ $conversations->currentPage() }} of {{ $conversations->lastPage() }}</span>@if($conversations->nextPageUrl())<a href="{{ $conversations->nextPageUrl() }}">Next</a>@else<span></span>@endif</nav>@endif
  </section>
</main>
@endsection
@section('scripts')
<script>
(() => {
  let loading = false;
  async function refresh() {
    if (document.hidden || loading) return;
    loading = true;
    try {
      const response = await fetch(location.href, {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}});
      if (response.redirected) { location.assign(response.url); return; }
      if (!response.ok) throw new Error('Refresh failed');
      const next = new DOMParser().parseFromString(await response.text(), 'text/html').getElementById('conversationList');
      if (!next) throw new Error('Missing conversations');
      const current = document.getElementById('conversationList');
      if (!current.contains(document.activeElement)) current.replaceChildren(...next.childNodes);
      document.getElementById('inboxStatus').hidden = true;
    } catch (_) { document.getElementById('inboxStatus').hidden = false; }
    finally { loading = false; }
  }
  setInterval(refresh, 10000);
  document.addEventListener('visibilitychange', refresh);
  window.addEventListener('pageshow', refresh);
})();
</script>
@endsection
