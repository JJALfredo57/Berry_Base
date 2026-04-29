@extends('layouts.app')
@section('content')
<div>
  <h4 class="fw-bold mb-4"><i class="bi bi-fire me-2" style="color:var(--primary)"></i>Kitchen Tickets</h4>

  @if(session('msg'))<div class="alert alert-success border-0"><i class="bi bi-check-circle me-2"></i>{{ session('msg') }}</div>@endif
  @if(session('err'))<div class="alert alert-danger border-0"><i class="bi bi-exclamation-circle me-2"></i>{{ session('err') }}</div>@endif

  @php
    $ticketsPending   = $tickets->where('ticket_status', 'pending');
    $ticketsPreparing = $tickets->where('ticket_status', 'in_progress');
    $ticketsDone      = $tickets->where('ticket_status', 'done');
  @endphp

  {{-- ── STATUS TABS ─────────────────────────────────────────────── --}}
  <div class="d-flex gap-2 mb-4 flex-wrap">
    <button id="tab-pending" class="btn btn-sm fw-semibold kitchen-tab active-tab"
            onclick="showKitchenTab('pending')"
            style="border-radius:2rem;padding:.45rem 1.1rem">
      ⏳ Pending
      <span class="badge ms-1" id="cnt-pending"
            style="background:rgba(255,255,255,.35);color:inherit">{{ $ticketsPending->count() }}</span>
    </button>
    <button id="tab-in_progress" class="btn btn-sm fw-semibold kitchen-tab"
            onclick="showKitchenTab('in_progress')"
            style="border-radius:2rem;padding:.45rem 1.1rem">
      🍳 Preparing
      <span class="badge ms-1" id="cnt-in_progress"
            style="background:rgba(255,255,255,.35);color:inherit">{{ $ticketsPreparing->count() }}</span>
    </button>
    <button id="tab-done" class="btn btn-sm fw-semibold kitchen-tab"
            onclick="showKitchenTab('done')"
            style="border-radius:2rem;padding:.45rem 1.1rem">
      ✅ Completed
      <span class="badge ms-1" id="cnt-done"
            style="background:rgba(255,255,255,.35);color:inherit">{{ $ticketsDone->count() }}</span>
    </button>
  </div>

  <style>
    .kitchen-tab { background:#f3f4f6; color:#374151; border:1.5px solid #e5e7eb; }
    .kitchen-tab:hover { background:#e9ecef; color:#111; }
    .kitchen-tab.active-tab { background:var(--primary,#e91e8c); color:#fff; border-color:var(--primary,#e91e8c); }
    .kitchen-tab.active-tab .badge { background:rgba(255,255,255,.3)!important; color:#fff!important; }
  </style>

  {{-- ── PENDING PANE ─────────────────────────────────────────────── --}}
  <div id="pane-pending">
    @forelse($ticketsPending as $t)
      @php $isDelivery = $t->fulfillment_type === 'Delivery'; @endphp
      @include('seller._kitchen_ticket', ['t' => $t, 'isDelivery' => $isDelivery, 'riders' => $riders, 'customImages' => $customImages])
    @empty
      <div class="card text-center py-5">
        <i class="bi bi-hourglass" style="font-size:3rem;color:#ddd"></i>
        <p class="text-muted mt-3">No pending tickets.</p>
      </div>
    @endforelse
  </div>

  {{-- ── PREPARING PANE ───────────────────────────────────────────── --}}
  <div id="pane-in_progress" style="display:none">
    @forelse($ticketsPreparing as $t)
      @php $isDelivery = $t->fulfillment_type === 'Delivery'; @endphp
      @include('seller._kitchen_ticket', ['t' => $t, 'isDelivery' => $isDelivery, 'riders' => $riders, 'customImages' => $customImages])
    @empty
      <div class="card text-center py-5">
        <i class="bi bi-fire" style="font-size:3rem;color:#ddd"></i>
        <p class="text-muted mt-3">No tickets being prepared.</p>
      </div>
    @endforelse
  </div>

  {{-- ── COMPLETED PANE ───────────────────────────────────────────── --}}
  <div id="pane-done" style="display:none">
    @forelse($ticketsDone as $t)
      @php $isDelivery = $t->fulfillment_type === 'Delivery'; @endphp
      @include('seller._kitchen_ticket', ['t' => $t, 'isDelivery' => $isDelivery, 'riders' => $riders, 'customImages' => $customImages])
    @empty
      <div class="card text-center py-5">
        <i class="bi bi-check-all" style="font-size:3rem;color:#ddd"></i>
        <p class="text-muted mt-3">No completed tickets yet.</p>
      </div>
    @endforelse
  </div>
</div>

<script>
// ── Tab switching ──────────────────────────────────────────────────────────
function showKitchenTab(status) {
  ['pending','in_progress','done'].forEach(function(s) {
    document.getElementById('pane-' + s).style.display = s === status ? '' : 'none';
    var btn = document.getElementById('tab-' + s);
    btn.classList.toggle('active-tab', s === status);
  });
}
</script>

@endsection
