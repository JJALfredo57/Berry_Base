@extends('layouts.app')
@section('page_title','Seller Payouts')

@section('content')
@php
  $currentPayoutMode = strtolower(trim((string)($payoutSettings->payout_mode ?? 'manual')));
  if (!in_array($currentPayoutMode, ['manual', 'automatic'], true)) $currentPayoutMode = 'manual';
@endphp
<div class="cs-page-header">
  <div>
    <h4 class="cs-page-title"><i class="bi bi-wallet2 me-2" style="color:var(--primary)"></i>Seller Payouts</h4>
    <p class="cs-page-sub">Control how seller earnings are released after orders are delivered and cleared.</p>
  </div>
  <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#automaticPayoutNoticeModal">
    <i class="bi bi-lock me-1"></i>Automatic Coming Soon
  </button>
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
    <strong>Important payout rule:</strong> seller money is only payable after payment is collected, the order is delivered, and the hold period has passed.
    Automatic mode prepares eligible payout batches; final PayMongo disbursement should be enabled only after Wallet/Disbursements setup is confirmed.
  </div>
</div>

<div class="row g-3 mb-4">
  @foreach([
    ['Clearing', $summary['clearing'], 'bi-hourglass-split', '#92400e', '#fffbeb'],
    ['Available', $summary['available'], 'bi-cash-stack', '#166534', '#ecfdf5'],
    ['Processing', $summary['processing'], 'bi-arrow-repeat', '#1d4ed8', '#eff6ff'],
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
      <div class="card-header"><i class="bi bi-sliders me-2" style="color:var(--primary)"></i>Payout Settings</div>
      <div class="card-body">
        <form action="{{ route('superadmin.payouts.settings') }}" method="POST" data-prevent-double-submit>
          @csrf
          <div class="mb-3">
            <label class="form-label fw-semibold">Payout Mode</label>
            <select name="payout_mode" class="form-select" required id="payoutModeSelect">
              <option value="manual" @selected($currentPayoutMode === 'manual')>Manual - admin reviews and marks paid</option>
              <option value="automatic" @selected($currentPayoutMode === 'automatic')>Automatic - coming soon</option>
            </select>
            <div class="form-text">Manual mode is active while automatic payouts are being prepared for a future update.</div>
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">Hold Days</label>
              <input type="number" min="0" max="30" class="form-control" name="payout_hold_days" value="{{ (int)($payoutSettings->payout_hold_days ?? 3) }}" required>
            </div>
            <div class="col-6">
              <label class="form-label">Minimum</label>
              <input type="number" min="0" step="0.01" class="form-control" name="payout_minimum_amount" value="{{ number_format((float)($payoutSettings->payout_minimum_amount ?? 500), 2, '.', '') }}" required>
            </div>
          </div>
          <div class="mt-3 payout-auto-setting">
            <label class="form-label">Schedule</label>
            <select name="payout_schedule" class="form-select" required>
              @foreach(['daily'=>'Daily','weekly'=>'Weekly','twice_monthly'=>'Twice a month','monthly'=>'Monthly'] as $key => $label)
                <option value="{{ $key }}" @selected(($payoutSettings->payout_schedule ?? 'weekly') === $key)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-check form-switch mt-3 payout-auto-setting">
            <input class="form-check-input" type="checkbox" name="payout_first_approval_required" value="1" @checked(!empty($payoutSettings->payout_first_approval_required))>
            <label class="form-check-label">Require admin approval for first seller payout</label>
          </div>
          <div class="form-check form-switch mt-2 payout-auto-setting">
            <input class="form-check-input" type="checkbox" name="payout_auto_paused" value="1" @checked(!empty($payoutSettings->payout_auto_paused))>
            <label class="form-check-label">Pause automatic payouts globally</label>
          </div>
          <button class="btn btn-primary w-100 mt-4" type="submit" data-loading-text="Saving...">
            <i class="bi bi-save me-1"></i>Save Settings
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-shop me-2" style="color:var(--primary)"></i>Seller Balances</span>
        <span class="badge text-bg-light">Mode: {{ ucfirst($currentPayoutMode) }} / {{ !empty($payoutSettings->payout_auto_paused) ? 'Paused' : 'Active' }}</span>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Seller</th>
              <th>Payout Details</th>
              <th>Clearing / Next Release</th>
              <th class="text-end">Available</th>
              <th class="text-end">Processing</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse($shops as $shop)
              @php
                $minimumPayout = (float)($payoutSettings->payout_minimum_amount ?? 0);
                $payoutBlockReason = null;
                $missingPayoutDetails = empty($shop->payout_method) || empty($shop->payout_account_name) || empty($shop->payout_account_number);
                if (!empty($shop->payout_paused)) {
                  $payoutBlockReason = 'Payout is paused for this seller. Enable payouts for this shop first.';
                } elseif ($missingPayoutDetails) {
                  $payoutBlockReason = 'Seller GCash payout details are incomplete. Ask the seller to add GCash details first.';
                } elseif (empty($shop->payout_details_verified)) {
                  $payoutBlockReason = 'Seller payout details need admin verification before creating a payout.';
                } elseif ($shop->available_balance <= 0) {
                  $payoutBlockReason = 'No available balance yet. Orders must be paid, delivered, and past the hold period.';
                } elseif ($shop->available_balance < $minimumPayout) {
                  $payoutBlockReason = 'Available balance is below the minimum payout amount of ₱'.number_format($minimumPayout, 2).'.';
                }
              @endphp
              <tr>
                <td>
                  <div class="fw-semibold">{{ $shop->shop_name }}</div>
                  <div class="small text-muted">{{ $shop->seller_name ?? 'Seller' }}</div>
                </td>
                <td class="small">
                  @if($shop->payout_method)
                    <div>GCash</div>
                    <div class="text-muted">{{ $shop->payout_account_name }} / {{ $shop->payout_account_number }}</div>
                    <span class="badge {{ $shop->payout_details_verified ? 'text-bg-success' : 'text-bg-warning' }}">{{ $shop->payout_details_verified ? 'Verified' : 'Needs verification' }}</span>
                  @else
                    <span class="text-muted">No payout details yet</span>
                  @endif
                </td>
                <td class="small">
                  <div class="fw-semibold">₱{{ number_format($shop->clearing_balance ?? 0, 2) }}</div>
                  @if(!empty($shop->next_release_at))
                    @php $shopNextRelease = \Carbon\Carbon::parse($shop->next_release_at); @endphp
                    <div class="text-muted">Next: {{ $shopNextRelease->format('M d, Y h:i A') }}</div>
                    <div class="fw-semibold" style="color:var(--primary)">
                      <span data-countdown-until="{{ $shopNextRelease->toIso8601String() }}">Calculating...</span>
                    </div>
                  @else
                    <span class="text-muted">No clearing balance</span>
                  @endif
                </td>
                <td class="text-end fw-semibold">₱{{ number_format($shop->available_balance, 2) }}</td>
                <td class="text-end">₱{{ number_format($shop->processing_balance, 2) }}</td>
                <td class="text-end">
                  <div class="d-flex gap-2 justify-content-end flex-wrap">
                    @if($missingPayoutDetails)
                      <form action="{{ route('superadmin.payouts.request_seller_details', $shop->id) }}" method="POST" data-prevent-double-submit>
                        @csrf
                        <button class="btn btn-outline-primary btn-sm" type="submit" data-loading-text="Sending...">
                          <i class="bi bi-bell me-1"></i>Request Details
                        </button>
                      </form>
                    @elseif($shop->payout_method && !$shop->payout_details_verified)
                      <form action="{{ route('superadmin.payouts.verify_seller', $shop->id) }}" method="POST" data-prevent-double-submit>
                        @csrf
                        <button class="btn btn-outline-success btn-sm" type="submit">
                          <i class="bi bi-check2-circle me-1"></i>Verify
                        </button>
                      </form>
                    @elseif($payoutBlockReason)
                      <button class="btn btn-outline-secondary btn-sm" type="button" onclick="alert(@js($payoutBlockReason))">
                        Create Payout
                      </button>
                    @else
                      <form action="{{ route('superadmin.payouts.create_manual', $shop->id) }}" method="POST" data-prevent-double-submit>
                        @csrf
                        <button class="btn btn-primary btn-sm" type="submit" data-loading-text="Creating...">Create Payout</button>
                      </form>
                    @endif
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-4">No seller balances yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="card mt-4">
  <div class="card-header"><i class="bi bi-receipt me-2" style="color:var(--primary)"></i>Recent Payouts</div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>ID</th>
          <th>Seller</th>
          <th>Mode</th>
          <th>Status</th>
          <th class="text-end">Net</th>
          <th>Receipt Proof</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($payouts as $p)
          <tr>
            <td>#{{ $p->id }}</td>
            <td><div class="fw-semibold">{{ $p->shop_name }}</div><div class="small text-muted">{{ $p->seller_name }}</div></td>
            <td>{{ ucfirst($p->mode) }}</td>
            <td><span class="badge text-bg-{{ $p->status === 'paid' ? 'success' : ($p->status === 'failed' ? 'danger' : 'warning') }}">{{ ucfirst(str_replace('_',' ', $p->status)) }}</span></td>
            <td class="text-end fw-semibold">₱{{ number_format($p->net_amount, 2) }}</td>
            <td class="small">
              @if(!empty($p->payout_receipt_path ?? null))
                <button class="btn btn-outline-primary btn-sm payout-receipt-viewer-btn" type="button" data-receipt-url="{{ $p->payout_receipt_path }}" data-receipt-title="Payout Receipt #{{ $p->id }}">
                  <i class="bi bi-image me-1"></i>View Receipt
                </button>
              @else
                <span class="text-muted">Pending receipt</span>
              @endif
            </td>
            <td class="text-end">
              @if($p->status !== 'paid')
                <form action="{{ route('superadmin.payouts.mark_paid', $p->id) }}" method="POST" enctype="multipart/form-data" class="d-flex gap-2 justify-content-end flex-wrap" data-prevent-double-submit>
                  @csrf
                  <input type="file" name="payout_receipt" class="form-control form-control-sm" accept="image/*" required style="max-width:220px">
                  <input name="reference_number" class="form-control form-control-sm" placeholder="Reference no. (optional)" style="max-width:180px">
                  <button class="btn btn-success btn-sm" type="submit">Mark Paid</button>
                </form>
              @else
                <div class="text-muted small">{{ $p->paid_at }}</div>
                @if($p->reference_number)
                  <div class="small text-muted">Ref: {{ $p->reference_number }}</div>
                @endif
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No payouts yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="automaticPayoutNoticeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold"><i class="bi bi-shield-exclamation me-1" style="color:var(--primary)"></i>Automatic payouts are not available yet</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">Automatic seller payouts require additional business verification and payout/disbursement capability before money can be transferred safely.</p>
        <p class="mb-0 text-muted small">For now, manual payout remains the active method. The system is prepared to adopt automatic payouts in a future website update once the required business documents and provider access are ready.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Use Manual Mode</button>
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
  const mode = document.getElementById('payoutModeSelect');
  const automaticSettings = document.querySelectorAll('.payout-auto-setting');
  const automaticNotice = document.getElementById('automaticPayoutNoticeModal');

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

  function syncPayoutModeFields() {
    const isAutomatic = mode && mode.value === 'automatic';
    automaticSettings.forEach(section => {
      section.style.display = isAutomatic ? '' : 'none';
      section.querySelectorAll('input, select, textarea').forEach(field => {
        field.disabled = !isAutomatic;
      });
    });
  }

  if (mode) {
    mode.addEventListener('change', function() {
      if (mode.value === 'automatic') {
        mode.value = 'manual';
        syncPayoutModeFields();
        if (automaticNotice && window.bootstrap) {
          bootstrap.Modal.getOrCreateInstance(automaticNotice).show();
        } else {
          alert('Automatic payouts are not available yet. Manual payout remains the active method while the required business verification and payout provider access are prepared.');
        }
        return;
      }
      syncPayoutModeFields();
    });
    syncPayoutModeFields();
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
