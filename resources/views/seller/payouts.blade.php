@extends('layouts.app')
@section('page_title','Payouts')

@section('content')
@php
  $minimumPayout = (float)($payoutSettings->payout_minimum_amount ?? 0);
  $requestBlockReason = null;
  if (!empty($shop->payout_paused)) {
    $requestBlockReason = 'Payouts are paused for your shop. Please contact admin.';
  } elseif (empty($shop->payout_account_name) || empty($shop->payout_account_number)) {
    $requestBlockReason = 'Complete your GCash payout details first before requesting a payout.';
  } elseif (empty($shop->payout_details_verified)) {
    $requestBlockReason = 'Your payout details need admin verification before payout request.';
  } elseif (($summary['available'] ?? 0) <= 0) {
    $requestBlockReason = 'No available balance yet. Earnings become available after paid orders are delivered and cleared.';
  } elseif (($summary['available'] ?? 0) < $minimumPayout) {
    $requestBlockReason = 'Your available balance is below the minimum payout amount of ₱'.number_format($minimumPayout, 2).'.';
  }
@endphp
<div class="cs-page-header">
  <div>
    <h4 class="cs-page-title"><i class="bi bi-wallet2 me-2" style="color:var(--primary)"></i>Payouts</h4>
    <p class="cs-page-sub">Track your earnings and keep your payout details accurate.</p>
  </div>
  @if($requestBlockReason)
    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="alert(@js($requestBlockReason))">
      <i class="bi bi-cash-coin me-1"></i>Request Payout
    </button>
  @else
    <form action="{{ route('seller.payouts.request') }}" method="POST" data-prevent-double-submit>
      @csrf
      <button class="btn btn-primary btn-sm" type="submit" data-loading-text="Submitting...">
        <i class="bi bi-cash-coin me-1"></i>Request Payout
      </button>
    </form>
  @endif
</div>

@if(session('msg'))
  <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>{{ session('msg') }}</div>
@endif
@if(session('err'))
  <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i>{{ session('err') }}</div>
@endif
@foreach($errors->all() as $e)
  <div class="alert alert-danger">{{ $e }}</div>
@endforeach

<div class="alert alert-info d-flex gap-3 align-items-start">
  <i class="bi bi-info-circle-fill fs-5"></i>
  <div>
    <strong>Reminder:</strong> earnings become available only after an order is paid, delivered, and cleared by the platform hold period.
    Keep your account name and number exact. Incorrect payout details can delay or fail transfers.
  </div>
</div>

<div class="row g-3 mb-4">
  @foreach([
    ['Clearing', $summary['pending'], 'bi-hourglass-split', '#92400e', '#fffbeb'],
    ['Available', $summary['available'], 'bi-cash-stack', '#166534', '#ecfdf5'],
    ['Requested/Processing', $summary['processing'], 'bi-arrow-repeat', '#1d4ed8', '#eff6ff'],
    ['Paid Out', $summary['paid'], 'bi-check2-circle', '#6d28d9', '#f5f3ff'],
  ] as [$label, $value, $icon, $color, $bg])
  <div class="col-6 col-lg-3">
    <div class="cs-stat-card h-100">
      <div class="cs-stat-icon" style="background:{{ $bg }}"><i class="bi {{ $icon }}" style="color:{{ $color }}"></i></div>
      <div class="cs-stat-body">
        <div class="cs-stat-num" style="color:{{ $color }}">₱{{ number_format($value, 2) }}</div>
        <div class="cs-stat-label">{{ $label }}</div>
      </div>
    </div>
  </div>
  @endforeach
</div>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-bank me-2" style="color:var(--primary)"></i>Payout Details</div>
      <div class="card-body">
        <div class="mb-3">
          <span class="badge {{ $shop->payout_details_verified ? 'text-bg-success' : 'text-bg-warning' }}">
            {{ $shop->payout_details_verified ? 'Verified by admin' : 'Needs admin verification' }}
          </span>
          <div class="form-text">Changing details resets verification for your safety.</div>
        </div>
        <form action="{{ route('seller.payouts.details') }}" method="POST" data-prevent-double-submit>
          @csrf
          <div class="mb-3">
            <label class="form-label">Receive via</label>
            <input class="form-control" value="GCash" readonly>
            <div class="form-text">Seller payouts are currently released to GCash only.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">GCash Account Name</label>
            <input class="form-control" name="payout_account_name" value="{{ $shop->payout_account_name }}" placeholder="Exact registered name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">GCash Mobile Number</label>
            <input class="form-control" name="payout_account_number" value="{{ $shop->payout_account_number }}" placeholder="09XXXXXXXXX" required>
          </div>
          <button class="btn btn-primary w-100" type="submit" data-loading-text="Saving..."><i class="bi bi-save me-1"></i>Save Details</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card mb-4">
      <div class="card-header"><i class="bi bi-receipt me-2" style="color:var(--primary)"></i>Recent Payout Requests</div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>ID</th><th>Status</th><th class="text-end">Net</th><th>Reference</th><th>Date</th></tr></thead>
          <tbody>
          @forelse($payouts as $p)
            <tr>
              <td>#{{ $p->id }}</td>
              <td><span class="badge text-bg-{{ $p->status === 'paid' ? 'success' : 'warning' }}">{{ ucfirst(str_replace('_',' ', $p->status)) }}</span></td>
              <td class="text-end fw-semibold">₱{{ number_format($p->net_amount, 2) }}</td>
              <td>{{ $p->reference_number ?: 'Pending admin transfer' }}</td>
              <td class="small text-muted">{{ $p->created_at }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-4">No payout requests yet.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-list-check me-2" style="color:var(--primary)"></i>Earnings Ledger</div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Order</th><th>Status</th><th class="text-end">Gross</th><th class="text-end">Commission</th><th class="text-end">Net</th><th>Release</th></tr></thead>
          <tbody>
          @forelse($ledgers as $l)
            <tr>
              <td>#{{ $l->order_id }}</td>
              <td><span class="badge text-bg-light">{{ ucfirst(str_replace('_',' ', $l->status)) }}</span></td>
              <td class="text-end">₱{{ number_format($l->gross_amount, 2) }}</td>
              <td class="text-end">₱{{ number_format($l->commission_amount, 2) }}</td>
              <td class="text-end fw-semibold">₱{{ number_format($l->seller_net_amount, 2) }}</td>
              <td class="small text-muted">{{ $l->release_at ?: 'Pending' }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-4">No cleared earnings yet. Delivered paid orders will appear here.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
