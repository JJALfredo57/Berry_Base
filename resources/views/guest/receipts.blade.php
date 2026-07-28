@extends('layouts.app')
@section('content')
<div class="container-fluid py-4" style="padding-left:clamp(12px,3vw,32px);padding-right:clamp(12px,3vw,32px)">
  <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
    <div>
      <h4 class="fw-bold mb-1" style="color:var(--primary)"><i class="bi bi-receipt-cutoff me-2"></i>Receipt History</h4>
      <div class="text-muted small">Receipts linked to the phone number of Order #{{ $order->id }}.</div>
    </div>
    <a href="{{ route('track.order', $order->track_code) }}" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Back to Tracking
    </a>
  </div>

  <div class="card mb-3">
    <div class="card-body p-3">
      <form method="GET" class="d-flex gap-2 flex-wrap">
        <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Search order #, tracking code, or product" style="max-width:360px">
        <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Search</button>
        @if($search)
          <a href="{{ route('guest.receipts', $order->track_code) }}" class="btn btn-outline-secondary">Clear</a>
        @endif
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0">
      @forelse($receipts as $r)
        @php
          $paidAmount = $r->payment_status === 'Paid' ? (float)$r->total_price : (float)$r->deposit_amount;
          $paidDate = $r->paid_at ?? $r->deposit_paid_at ?? $r->created_at;
        @endphp
        <div class="p-3 d-flex align-items-center justify-content-between gap-3 flex-wrap" style="border-bottom:1px solid #eef2f7">
          <div style="min-width:220px">
            <div class="fw-bold">Order #{{ $r->id }}</div>
            <div class="small text-muted">{{ $r->product_name }}</div>
            <div class="small text-muted">Track: <span style="font-family:monospace">{{ $r->track_code }}</span></div>
          </div>
          <div>
            <div class="small text-muted">Date Paid</div>
            <div class="fw-semibold">{{ \Carbon\Carbon::parse($paidDate)->format('M d, Y') }}</div>
          </div>
          <div>
            <div class="small text-muted">Amount Paid</div>
            <div class="fw-bold" style="color:#16a34a">₱{{ number_format($paidAmount, 2) }}</div>
          </div>
          <div>
            <span class="badge {{ $r->payment_status === 'Paid' ? 'bg-success' : 'bg-primary' }}">{{ $r->payment_status }}</span>
          </div>
          <a href="{{ route('guest.receipt', $r->track_code) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-eye me-1"></i>View Receipt
          </a>
        </div>
      @empty
        <div class="p-4 text-center text-muted">No paid receipts found for this phone number.</div>
      @endforelse
    </div>
  </div>

  <div class="mt-3">
    {{ $receipts->links() }}
  </div>
</div>
@endsection
