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

@php
  $nextReleaseAt = $nextClearingLedger?->release_at ? \Carbon\Carbon::parse($nextClearingLedger->release_at) : null;
  $remainingToMinimum = max(0, $minimumPayout - (float)($summary['available'] ?? 0));
@endphp
<div class="card mb-4">
  <div class="card-header"><i class="bi bi-calendar2-check me-2" style="color:var(--primary)"></i>Payout Availability</div>
  <div class="card-body">
    <div class="row g-3 align-items-stretch">
      <div class="col-md-4">
        <div class="h-100 p-3 rounded-3" style="background:var(--primary-bg)">
          <div class="text-muted small fw-semibold">Hold Period</div>
          <div class="fw-bold" style="color:var(--primary)">{{ (int)($payoutSettings->payout_hold_days ?? 0) }} day{{ (int)($payoutSettings->payout_hold_days ?? 0) === 1 ? '' : 's' }}</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="h-100 p-3 rounded-3" style="background:#ecfdf5">
          <div class="text-muted small fw-semibold">Minimum Request</div>
          <div class="fw-bold" style="color:#166534">₱{{ number_format($minimumPayout, 2) }}</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="h-100 p-3 rounded-3" style="background:#fffbeb">
          <div class="text-muted small fw-semibold">Still Needed</div>
          <div class="fw-bold" style="color:#92400e">₱{{ number_format($remainingToMinimum, 2) }}</div>
        </div>
      </div>
    </div>

    @if($nextReleaseAt)
      <div class="mt-3 p-3 rounded-3 d-flex align-items-start gap-3" style="background:#f8fafc;border:1px solid #e5e7eb">
        <i class="bi bi-hourglass-split fs-4" style="color:var(--primary)"></i>
        <div>
          <div class="fw-semibold">Next clearing release</div>
          <div class="small text-muted">
            ₱{{ number_format((float)$nextClearingLedger->seller_net_amount, 2) }} becomes available on
            <strong>{{ $nextReleaseAt->format('M d, Y h:i A') }}</strong>.
          </div>
          <div class="mt-1 fw-bold" style="color:var(--primary)">
            <span data-countdown-until="{{ $nextReleaseAt->toIso8601String() }}">Calculating...</span>
          </div>
        </div>
      </div>
    @elseif(($summary['available'] ?? 0) >= $minimumPayout && !$requestBlockReason)
      <div class="alert alert-success mt-3 mb-0"><i class="bi bi-check-circle me-1"></i>Your available balance is ready for payout request.</div>
    @else
      <div class="alert alert-light border mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>No clearing countdown right now. New paid and delivered orders will show their release time here.</div>
    @endif
  </div>
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
        <form action="{{ route('seller.payouts.details') }}" method="POST" data-prevent-double-submit onsubmit="return confirm('Please double-check your GCash details before saving. The account name must match the registered GCash name, and the mobile number must be correct. Wrong details can delay or misdirect payout transfer, so confirm only if everything is accurate.')">
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
            <input class="form-control" name="payout_account_number" value="{{ $shop->payout_account_number }}" placeholder="09XXXXXXXXX" pattern="^(09[0-9]{9}|\+639[0-9]{9})$" inputmode="tel" required>
            <div class="form-text">Use 09XXXXXXXXX or +639XXXXXXXXX. Check every digit before saving.</div>
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
          <thead><tr><th>ID</th><th>Status</th><th class="text-end">Net</th><th>Receipt Proof</th><th>Date</th></tr></thead>
          <tbody>
          @forelse($payouts as $p)
            <tr>
              <td>#{{ $p->id }}</td>
              <td><span class="badge text-bg-{{ $p->status === 'paid' ? 'success' : 'warning' }}">{{ ucfirst(str_replace('_',' ', $p->status)) }}</span></td>
              <td class="text-end fw-semibold">₱{{ number_format($p->net_amount, 2) }}</td>
              <td>
                @if(!empty($p->payout_receipt_path ?? null))
                  <button class="btn btn-outline-primary btn-sm payout-receipt-viewer-btn" type="button" data-receipt-url="{{ $p->payout_receipt_path }}" data-receipt-title="Payout Receipt #{{ $p->id }}">
                    <i class="bi bi-image me-1"></i>View Receipt
                  </button>
                  @if($p->reference_number)
                    <div class="small text-muted mt-1">Ref: {{ $p->reference_number }}</div>
                  @endif
                @else
                  <span class="text-muted">Pending admin transfer</span>
                @endif
              </td>
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

<div class="modal fade" id="payoutReceiptViewerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold" id="payoutReceiptViewerTitle"><i class="bi bi-receipt me-1" style="color:var(--primary)"></i>Payout Receipt</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center" style="background:#0f172a">
        <img id="payoutReceiptViewerImage" src="" alt="Payout receipt" style="max-width:100%;max-height:75vh;object-fit:contain;border-radius:8px">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-primary btn-sm" id="payoutReceiptViewerDownload">
          <i class="bi bi-download me-1"></i>Download
        </button>
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  function formatCountdown(ms) {
    if (ms <= 0) return null;
    const totalSeconds = Math.floor(ms / 1000);
    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    return (days ? days + 'd ' : '') +
      String(hours).padStart(2, '0') + 'h ' +
      String(minutes).padStart(2, '0') + 'm ' +
      String(seconds).padStart(2, '0') + 's';
  }

  let countdownTimer = null;
  function updateCountdowns() {
    let active = 0;
    document.querySelectorAll('[data-countdown-until]').forEach(el => {
      if (el.dataset.countdownDone === '1') return;
      const target = Date.parse(el.dataset.countdownUntil || '');
      if (!target) return;
      const text = formatCountdown(target - Date.now());
      if (!text) {
        el.textContent = 'Ready after refresh';
        el.dataset.countdownDone = '1';
        return;
      }
      el.textContent = text;
      active++;
    });
    if (active === 0 && countdownTimer) {
      clearInterval(countdownTimer);
      countdownTimer = null;
    }
  }

  updateCountdowns();
  if (document.querySelector('[data-countdown-until]:not([data-countdown-done="1"])')) {
    countdownTimer = setInterval(updateCountdowns, 1000);
  }

  document.querySelectorAll('.payout-receipt-viewer-btn').forEach(button => {
    button.addEventListener('click', function() {
      const url = button.dataset.receiptUrl;
      if (!url) return;
      document.getElementById('payoutReceiptViewerTitle').textContent = button.dataset.receiptTitle || 'Payout Receipt';
      document.getElementById('payoutReceiptViewerImage').src = url;
      const downloadButton = document.getElementById('payoutReceiptViewerDownload');
      downloadButton.dataset.downloadUrl = url;
      downloadButton.dataset.downloadName = 'payout-receipt-' + (button.dataset.receiptTitle || 'receipt').replace(/[^0-9A-Za-z_-]+/g, '-').replace(/^-+|-+$/g, '') + '.jpg';
      if (window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('payoutReceiptViewerModal')).show();
      } else {
        window.open(url, '_blank', 'noopener');
      }
    });
  });

  document.getElementById('payoutReceiptViewerDownload')?.addEventListener('click', function() {
    const url = this.dataset.downloadUrl;
    if (!url) return;
    if (window.BerryBaseDownloads && typeof window.BerryBaseDownloads.download === 'function') {
      window.BerryBaseDownloads.download(url, this.dataset.downloadName || 'payout-receipt.jpg', this);
      return;
    }
    const link = document.createElement('a');
    link.href = url;
    link.download = this.dataset.downloadName || 'payout-receipt.jpg';
    document.body.appendChild(link);
    link.click();
    link.remove();
  });
});
</script>
@endsection
