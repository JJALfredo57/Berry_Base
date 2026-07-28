@extends('layouts.app')
@section('content')
@php
  $paidAt = $transaction->paid_at ?? $transaction->created_at;
  $serviceFee = isset($transaction->payment_service_fee) ? (float) $transaction->payment_service_fee : 0;
  $customerPaidAmount = isset($transaction->customer_paid_amount) && (float) $transaction->customer_paid_amount > 0
    ? (float) $transaction->customer_paid_amount
    : ((float) $transaction->amount + $serviceFee);
@endphp
<div class="container py-4" style="max-width:860px">
  <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3 no-print">
    <a href="{{ route('track.order', $trackCode) }}" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Back to Tracking
    </a>
    <button type="button" class="btn btn-primary" onclick="window.print()">
      <i class="bi bi-download me-1"></i>Print / Save PDF
    </button>
  </div>

  <div class="card" style="border-radius:10px;overflow:hidden">
    <div class="card-body p-4 p-md-5">
      <div class="d-flex justify-content-between gap-3 flex-wrap pb-3 mb-3" style="border-bottom:1px solid #e5e7eb">
        <div>
          <div class="small text-muted mb-1">Official Payment Receipt</div>
          <h3 class="fw-bold mb-1" style="color:var(--primary)">{{ $transaction->type_label }}</h3>
          <div class="text-muted">Receipt #{{ $transaction->id }} • Order #{{ $transaction->order_id }}</div>
        </div>
        <div class="text-md-end">
          <div class="small text-muted">Amount Paid</div>
          <div class="fw-bold" style="font-size:2rem;color:#16a34a">PHP {{ number_format($customerPaidAmount, 2) }}</div>
          <span class="badge bg-success">Paid</span>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <div class="p-3 rounded-3" style="background:#f8fafc;border:1px solid #e5e7eb">
            <div class="small text-muted">Customer</div>
            <div class="fw-semibold">{{ $transaction->guest_name ?? 'Guest' }}</div>
            <div class="small text-muted">{{ $transaction->guest_phone ?? 'No phone saved' }}</div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="p-3 rounded-3" style="background:#f8fafc;border:1px solid #e5e7eb">
            <div class="small text-muted">Payment</div>
            <div class="fw-semibold">{{ $transaction->method }} via {{ $transaction->provider ?? $transaction->method }}</div>
            <div class="small text-muted">{{ \Carbon\Carbon::parse($paidAt)->format('M d, Y g:i A') }}</div>
          </div>
        </div>
      </div>

      <div class="table-responsive mb-4">
        <table class="table align-middle">
          <tbody>
            <tr>
              <th style="width:210px">Product</th>
              <td>{{ $transaction->product_name }}</td>
            </tr>
            <tr>
              <th>Tracking Code</th>
              <td><span style="font-family:monospace">{{ $transaction->track_code }}</span></td>
            </tr>
            <tr>
              <th>Fulfillment</th>
              <td>{{ $transaction->fulfillment_type ?? 'N/A' }}</td>
            </tr>
            @if($transaction->schedule_date)
              <tr>
                <th>Schedule</th>
                <td>{{ \Carbon\Carbon::parse($transaction->schedule_date)->format('M d, Y') }}{{ $transaction->schedule_time ? ' • ' . \Carbon\Carbon::parse($transaction->schedule_time)->format('g:i A') : '' }}</td>
              </tr>
            @endif
            @if($transaction->selected_size)
              <tr>
                <th>Size</th>
                <td>{{ $transaction->selected_size }}</td>
              </tr>
            @endif
            @if($receiptAddons->count())
              <tr>
                <th>Add-ons</th>
                <td>{{ $receiptAddons->map(fn($a) => $a->addon_name)->implode(', ') }}</td>
              </tr>
            @endif
            @if($transaction->provider_reference)
              <tr>
                <th>Reference</th>
                <td>{{ $transaction->provider_reference }}</td>
              </tr>
            @endif
          </tbody>
        </table>
      </div>

      <div class="p-3 rounded-3" style="background:#fff7ed;border:1px solid #fed7aa">
        <div class="d-flex justify-content-between gap-3 flex-wrap">
          <span>Order Amount</span>
          <strong>PHP {{ number_format((float) $transaction->order_total, 2) }}</strong>
        </div>
        <div class="d-flex justify-content-between gap-3 flex-wrap mt-2">
          <span>This Receipt Covers</span>
          <strong>PHP {{ number_format((float) $transaction->amount, 2) }}</strong>
        </div>
        @if($serviceFee > 0)
          <div class="d-flex justify-content-between gap-3 flex-wrap mt-2">
            <span>PayMongo Service Fee</span>
            <strong>PHP {{ number_format($serviceFee, 2) }}</strong>
          </div>
          <div class="d-flex justify-content-between gap-3 flex-wrap mt-2 pt-2" style="border-top:1px dashed #fed7aa">
            <span>Total Paid by Customer</span>
            <strong>PHP {{ number_format($customerPaidAmount, 2) }}</strong>
          </div>
        @endif
        <div class="d-flex justify-content-between gap-3 flex-wrap mt-2">
          <span>Remaining Balance After This Payment</span>
          <strong>PHP {{ number_format((float) $transaction->remaining_balance, 2) }}</strong>
        </div>
      </div>

      <div class="small text-muted mt-3">
        Keep this receipt for payment verification. PayMongo service fee is shown separately when GCash is used.
      </div>
    </div>
  </div>
</div>
<style>
@media print {
  .no-print, nav, footer { display:none !important; }
  body { background:#fff !important; }
  .card { border:0 !important; box-shadow:none !important; }
}
</style>
@endsection
