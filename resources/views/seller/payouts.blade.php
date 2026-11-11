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
  $availableBalance = (float)($summary['available'] ?? 0);
  $payoutProgress = $minimumPayout > 0 ? min(100, round(($availableBalance / $minimumPayout) * 100)) : 100;
  $payoutState = empty($shop->payout_account_name) || empty($shop->payout_account_number) ? 'details' : (empty($shop->payout_details_verified) ? 'verification' : ($availableBalance < $minimumPayout ? 'minimum' : ($requestBlockReason ? 'clearing' : 'ready')));
  $payoutStateCopy = [
    'details' => ['Set up your GCash details', 'Add the exact registered account name and mobile number to begin.', 'bi-bank'],
    'verification' => ['Waiting for admin verification', 'Your details are saved. An admin needs to verify them before payout requests.', 'bi-patch-check'],
    'minimum' => ['Keep building your available balance', 'You are '.number_format($payoutProgress).'% of the way to the minimum payout.', 'bi-graph-up-arrow'],
    'clearing' => ['Earnings are still clearing', 'Paid and delivered orders become available after the platform hold period.', 'bi-hourglass-split'],
    'ready' => ['Your payout is ready to request', 'Your account is verified and your available balance meets the minimum.', 'bi-check2-circle'],
  ][$payoutState];
@endphp
<div class="cs-page-header">
  <div>
    <h4 class="cs-page-title"><i class="bi bi-wallet2 me-2" style="color:var(--primary)"></i>Payouts</h4>
    <p class="cs-page-sub">Track your earnings and keep your payout details accurate.</p>
  </div>
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

<section class="payout-readiness-card payout-state-{{ $payoutState }}" aria-labelledby="payoutReadinessTitle">
  <div class="payout-readiness-main">
    <div class="payout-live-label"><span></span>Live payout status</div>
    <div class="payout-readiness-title-row"><div class="payout-readiness-icon"><i class="bi {{ $payoutStateCopy[2] }}"></i></div><div><h2 id="payoutReadinessTitle">{{ $payoutStateCopy[0] }}</h2><p>{{ $payoutStateCopy[1] }}</p></div></div>
    <div class="payout-live-time"><i class="bi bi-clock me-1"></i>Local time: <time data-live-clock aria-live="polite">Loading...</time></div>
  </div>
  <div class="payout-readiness-action">
    <small>Available now</small><div class="payout-available-value">&#8369;{{ number_format($availableBalance, 2) }}</div>
    <div class="payout-progress-track" role="progressbar" aria-label="Progress toward minimum payout" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $payoutProgress }}"><span style="width:{{ $payoutProgress }}%"></span></div>
    <small>{{ $minimumPayout > 0 ? number_format($payoutProgress).'% of minimum reached' : 'No minimum payout amount' }}</small>
    @if($payoutState === 'details' || $payoutState === 'verification')
      <a href="#payout-details" class="btn btn-primary payout-hero-btn"><i class="bi bi-bank me-1"></i>{{ $payoutState === 'details' ? 'Add GCash Details' : 'Review GCash Details' }}</a>
    @elseif($payoutState === 'ready')
      <form action="{{ route('seller.payouts.request') }}" method="POST" data-prevent-double-submit>@csrf<button class="btn btn-primary payout-hero-btn" type="submit" data-loading-text="Submitting..."><i class="bi bi-cash-coin me-1"></i>Request Payout</button></form>
    @else
      <button class="btn btn-outline-secondary payout-hero-btn" type="button" onclick="alert(@js($requestBlockReason))"><i class="bi bi-info-circle me-1"></i>Why can’t I request?</button>
    @endif
  </div>
</section>

<div class="alert alert-info d-flex gap-3 align-items-start payout-rule-note">
  <i class="bi bi-info-circle-fill fs-5"></i>
  <div>
    <strong>Reminder:</strong> earnings become available only after an order is paid, delivered, and cleared by the platform hold period.
    Eligible refunds may be handled before payout release. Keep your account name and number exact because incorrect payout details can delay or fail transfers.
  </div>
</div>

<div class="row g-3 mb-4 payout-summary-grid">
  @foreach([
    ['Clearing', $summary['pending'], 'bi-hourglass-split', '#92400e', '#fffbeb'],
    ['Available', $summary['available'], 'bi-cash-stack', '#166534', '#ecfdf5'],
    ['Requested/Processing', $summary['processing'], 'bi-arrow-repeat', '#1d4ed8', '#eff6ff'],
    ['Paid Out', $summary['paid'], 'bi-check2-circle', '#6d28d9', '#f5f3ff'],
    ['Refund Deductions', $summary['refund_deductions'] ?? 0, 'bi-arrow-counterclockwise', '#b91c1c', '#fef2f2'],
  ] as [$label, $value, $icon, $color, $bg])
  <div class="col-6 col-lg {{ $label === 'Refund Deductions' ? 'payout-secondary-stat' : '' }}">
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

@if(($refunds ?? collect())->count() > 0)
<div class="card mb-4">
  <div class="card-header"><i class="bi bi-arrow-counterclockwise me-2" style="color:#b91c1c"></i>Recent Refunds</div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Order</th><th>Status</th><th>GCash</th><th class="text-end">Amount</th><th>Reference</th></tr></thead>
      <tbody>
      @foreach($refunds as $r)
        <tr>
          <td>#{{ $r->order_id }}</td>
          <td><span class="badge text-bg-{{ $r->status === 'refunded' ? 'success' : ($r->status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($r->status) }}</span></td>
          <td class="small">{{ $r->refund_gcash_name }}<br><span class="text-muted">{{ $r->refund_gcash_number }}</span></td>
          <td class="text-end fw-semibold">₱{{ number_format((float)$r->refund_amount, 2) }}</td>
          <td class="small text-muted">{{ $r->reference_number ?: 'Pending' }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
</div>
@endif

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

<section class="card mb-4 payout-steps-card" aria-labelledby="payoutStepsTitle">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3"><div><div class="text-uppercase small fw-bold" style="color:var(--primary);letter-spacing:.08em">Simple process</div><h5 id="payoutStepsTitle" class="mb-0">How your payout moves</h5></div><span class="small text-muted">Manual admin review</span></div>
    <div class="payout-steps">
      <div class="payout-step"><span>1</span><div><strong>Earn</strong><small>Order is paid and delivered.</small></div></div>
      <div class="payout-step"><span>2</span><div><strong>Clear</strong><small>Hold period finishes.</small></div></div>
      <div class="payout-step"><span>3</span><div><strong>Request</strong><small>Submit when eligible.</small></div></div>
      <div class="payout-step"><span>4</span><div><strong>Receive</strong><small>Admin sends to verified GCash.</small></div></div>
    </div>
  </div>
</section>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card" id="payout-details">
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
      <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <span><i class="bi bi-receipt me-2" style="color:var(--primary)"></i>Recent Payout Requests</span>
        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#payoutHistoryModal">
          <i class="bi bi-clock-history me-1"></i>Transaction History
          <span class="badge text-bg-primary ms-1">{{ (int)($payoutHistoryCount ?? 0) }}</span>
        </button>
      </div>
      <div class="card-body py-3 small text-muted"><i class="bi bi-info-circle me-1"></i>Open Transaction History to review payout status, transfer references, and receipt proofs.</div>
      <div class="table-responsive d-none" aria-hidden="true">
        <table class="table align-middle mb-0">
          <thead><tr><th>ID</th><th>Status</th><th class="text-end">Net</th><th>Receipt Proof</th><th>Date</th></tr></thead>
          <tbody>
          @forelse($payouts as $p)
            <tr>
              <td>#{{ $p->id }}</td>
              <td><span class="badge text-bg-{{ $p->status === 'paid' ? 'success' : 'warning' }}">{{ ucfirst(str_replace('_',' ', $p->status)) }}</span><div class="small text-muted mt-1">{{ ($p->request_source ?? null) === 'seller' ? 'Requested by you' : (($p->request_source ?? null) === 'super_admin' ? 'Created by Super Admin' : (!empty($p->admin_note) && str_contains(strtolower($p->admin_note), 'requested by seller') ? 'Requested by you' : 'Source not recorded (legacy)')) }}</div></td>
              <td class="text-end fw-semibold">₱{{ number_format($p->net_amount, 2) }}</td>
              <td>
                @if(!empty($p->payout_receipt_path ?? null))
                  <button class="btn btn-outline-primary btn-sm payout-receipt-viewer-btn" type="button" data-receipt-url="{{ $p->payout_receipt_path }}" data-receipt-download-url="{{ route('seller.payouts.receipt_download', $p->id) }}" data-receipt-title="Payout Receipt #{{ $p->id }}">
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

<style>
  .payout-readiness-card{display:flex;justify-content:space-between;gap:1.5rem;padding:clamp(1rem,3vw,1.6rem);margin:0 0 1rem;border:1px solid color-mix(in srgb,var(--primary) 22%,#fff);border-radius:18px;background:linear-gradient(135deg,var(--primary-bg),#fff 66%);box-shadow:0 12px 30px rgba(15,23,42,.06);animation:payout-enter .38s ease both}
  .payout-readiness-main{min-width:0}.payout-live-label{display:flex;align-items:center;gap:.4rem;font-size:.72rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--primary)}.payout-live-label span{width:8px;height:8px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 0 rgba(34,197,94,.55);animation:payout-pulse 1.9s infinite}.payout-readiness-title-row{display:flex;align-items:flex-start;gap:.75rem;margin:.55rem 0}.payout-readiness-icon{display:grid;place-items:center;width:42px;height:42px;flex:0 0 auto;border-radius:13px;background:var(--primary);color:#fff;font-size:1.25rem}.payout-readiness-title-row h2{font-size:clamp(1.05rem,2vw,1.3rem);margin:0;color:var(--gray-900)}.payout-readiness-title-row p{margin:.18rem 0 0;color:var(--gray-600);max-width:620px}.payout-live-time{font-size:.8rem;color:var(--gray-600)}
  .payout-readiness-action{min-width:min(100%,260px);display:flex;flex-direction:column;align-items:stretch;justify-content:center;gap:.35rem}.payout-readiness-action small{color:var(--gray-600);font-size:.75rem}.payout-available-value{font-weight:800;font-size:1.45rem;color:#166534}.payout-progress-track{height:9px;overflow:hidden;background:#d1fae5;border-radius:99px}.payout-progress-track span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#16a34a,#22c55e);transition:width .5s ease}.payout-hero-btn{margin-top:.45rem;white-space:nowrap;overflow-wrap:normal}.payout-rule-note{font-size:.86rem}.payout-steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.6rem}.payout-step{display:flex;gap:.55rem;align-items:flex-start;padding:.7rem;border:1px solid var(--gray-100);border-radius:12px;background:#fff}.payout-step>span{display:grid;place-items:center;width:25px;height:25px;flex:0 0 auto;border-radius:50%;background:var(--primary-bg);color:var(--primary);font-size:.75rem;font-weight:800}.payout-step strong,.payout-step small{display:block}.payout-step small{font-size:.72rem;color:var(--gray-500);margin-top:.12rem}
  .payout-data-table .table{min-width:760px}.payout-data-table .btn{white-space:nowrap;overflow-wrap:normal}@keyframes payout-enter{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}@keyframes payout-pulse{70%{box-shadow:0 0 0 7px rgba(34,197,94,0)}}
  @media(max-width:767px){.payout-readiness-card{flex-direction:column;gap:1rem}.payout-readiness-action{width:100%;min-width:0}.payout-hero-btn{width:100%;min-height:44px}.payout-summary-grid .payout-secondary-stat{display:none}.payout-steps{grid-template-columns:1fr 1fr}.payout-step{min-height:66px}.payout-rule-note{align-items:flex-start!important}}
  @media(max-width:399px){.payout-steps{grid-template-columns:1fr}.payout-readiness-card{padding:.9rem}.payout-readiness-title-row{gap:.55rem}}
  @media(prefers-reduced-motion:reduce){.payout-readiness-card,.payout-live-label span{animation:none}.payout-progress-track span{transition:none}}
  #payoutHistoryModal.payout-history-modal .modal-dialog {
    width:min(980px, calc(100vw - 24px));
    max-width:min(980px, calc(100vw - 24px)) !important;
    margin:0 !important;
    transform:translateY(14px) scale(.97);
    transition:transform .22s ease;
  }
  #payoutHistoryModal.payout-history-modal.show { display:flex !important; align-items:center; justify-content:center; padding:12px !important; overflow:hidden; }
  #payoutHistoryModal.payout-history-modal.show .modal-dialog { transform:translateY(0) scale(1); }
  #payoutHistoryModal.payout-history-modal .modal-content { max-height:calc(100dvh - 24px); border-radius:1rem; overflow:hidden; }
  #payoutHistoryModal.payout-history-modal .modal-body { overflow:auto; -webkit-overflow-scrolling:touch; }
  #payoutHistoryModal .payout-history-table th { white-space:nowrap; font-size:.76rem; color:#64748b; text-transform:uppercase; }
  @media(max-width:575px) { #payoutHistoryModal.payout-history-modal .modal-footer .btn { min-height:44px; } }
  #payoutReceiptViewerModal.payout-receipt-modal .modal-dialog {
    width:min(920px, calc(100vw - 24px));
    max-width:min(920px, calc(100vw - 24px)) !important;
    margin:0 !important;
    transform:scale(.96);
  }
  #payoutReceiptViewerModal.payout-receipt-modal.show {
    display:flex !important;
    align-items:center;
    justify-content:center;
    padding:12px !important;
    overflow:hidden;
  }
  #payoutReceiptViewerModal.payout-receipt-modal.show .modal-dialog {
    transform:scale(1);
  }
  #payoutReceiptViewerModal.payout-receipt-modal .modal-content {
    max-height:calc(100dvh - 24px);
    display:flex;
    flex-direction:column;
  }
  #payoutReceiptViewerModal.payout-receipt-modal .modal-body {
    flex:1 1 auto;
    min-height:0;
    max-height:none;
    overflow:auto;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:12px;
    -webkit-overflow-scrolling:touch;
  }
  #payoutReceiptViewerModal.payout-receipt-modal .modal-body img {
    max-height:calc(100dvh - 160px) !important;
    width:auto;
  }
  #payoutReceiptViewerModal.payout-receipt-modal .modal-footer {
    flex-shrink:0;
    position:relative;
    z-index:2;
    background:#fff;
  }
  #payoutReceiptViewerModal.payout-receipt-modal .modal-footer .btn {
    min-height:44px;
    padding-inline:16px;
  }
  @media(max-width:575px) {
    #payoutReceiptViewerModal.payout-receipt-modal .modal-footer {
      justify-content:stretch;
      gap:8px;
    }
    #payoutReceiptViewerModal.payout-receipt-modal .modal-footer .btn {
      flex:1 1 0;
    }
  }
</style>

<div class="modal fade payout-receipt-modal" id="payoutReceiptViewerModal" tabindex="-1" aria-hidden="true">
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

<div class="modal fade payout-history-modal" id="payoutHistoryModal" tabindex="-1" aria-labelledby="payoutHistoryTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <div>
          <h5 class="modal-title fw-bold" id="payoutHistoryTitle"><i class="bi bi-clock-history me-2" style="color:var(--primary)"></i>Payout Transaction History</h5>
          <div class="small text-muted">Latest {{ $payouts->count() }} of {{ (int)($payoutHistoryCount ?? 0) }} payout request{{ (int)($payoutHistoryCount ?? 0) === 1 ? '' : 's' }}.</div>
        </div>
      </div>
      <div class="modal-body pt-3">
        @if($payouts->isEmpty())
          <div class="text-center py-5 text-muted"><i class="bi bi-receipt-cutoff d-block mb-2" style="font-size:2rem;color:var(--primary)"></i>No payout requests yet.</div>
        @else
          <div class="table-responsive">
            <table class="table align-middle mb-0 payout-history-table">
              <thead><tr><th>ID</th><th>Status</th><th class="text-end">Net</th><th>Receipt / Reference</th><th>Date</th></tr></thead>
              <tbody>
              @foreach($payouts as $p)
                <tr>
                  <td class="fw-semibold">#{{ $p->id }}</td>
                  <td><span class="badge text-bg-{{ ($p->status ?? '') === 'paid' ? 'success' : 'warning' }}">{{ ucfirst(str_replace('_', ' ', $p->status ?? 'pending')) }}</span><div class="small text-muted mt-1">{{ ($p->request_source ?? null) === 'seller' ? 'Requested by you' : (($p->request_source ?? null) === 'super_admin' ? 'Created by Super Admin' : (!empty($p->admin_note) && str_contains(strtolower($p->admin_note), 'requested by seller') ? 'Requested by you' : 'Source not recorded (legacy)')) }}</div></td>
                  <td class="text-end fw-semibold">₱{{ number_format((float)($p->net_amount ?? 0), 2) }}</td>
                  <td>
                    @if(!empty($p->payout_receipt_path ?? null))
                      <button class="btn btn-outline-primary btn-sm payout-receipt-viewer-btn" type="button" data-receipt-url="{{ $p->payout_receipt_path }}" data-receipt-download-url="{{ route('seller.payouts.receipt_download', $p->id) }}" data-receipt-title="Payout Receipt #{{ $p->id }}"><i class="bi bi-image me-1"></i>View Receipt</button>
                      @if($p->reference_number)<div class="small text-muted mt-1">Ref: {{ $p->reference_number }}</div>@endif
                    @else
                      <span class="small text-muted">Pending admin transfer</span>
                    @endif
                  </td>
                  <td class="small text-muted">{{ $p->created_at ? \Carbon\Carbon::parse($p->created_at)->format('M d, Y g:i A') : '—' }}</td>
                </tr>
              @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
      <div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-outline-secondary w-100" data-bs-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const receiptModal = document.getElementById('payoutReceiptViewerModal');
  const historyModal = document.getElementById('payoutHistoryModal');
  const liveClock = document.querySelector('[data-live-clock]');
  function updateLiveClock() {
    if (!liveClock) return;
    liveClock.dateTime = new Date().toISOString();
    liveClock.textContent = new Intl.DateTimeFormat(undefined, { weekday:'short', month:'short', day:'numeric', hour:'numeric', minute:'2-digit', second:'2-digit' }).format(new Date());
  }
  updateLiveClock();
  if (liveClock) setInterval(updateLiveClock, 1000);
  if (receiptModal && receiptModal.parentElement !== document.body) {
    document.body.appendChild(receiptModal);
  }
  if (historyModal && historyModal.parentElement !== document.body) {
    document.body.appendChild(historyModal);
  }

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
      if (historyModal && historyModal.classList.contains('show') && window.bootstrap) {
        bootstrap.Modal.getInstance(historyModal)?.hide();
      }
      document.getElementById('payoutReceiptViewerTitle').textContent = button.dataset.receiptTitle || 'Payout Receipt';
      document.getElementById('payoutReceiptViewerImage').src = url;
      const downloadButton = document.getElementById('payoutReceiptViewerDownload');
      downloadButton.dataset.downloadUrl = button.dataset.receiptDownloadUrl || url;
      downloadButton.dataset.downloadName = 'payout-receipt-' + (button.dataset.receiptTitle || 'receipt').replace(/[^0-9A-Za-z_-]+/g, '-').replace(/^-+|-+$/g, '') + '.jpg';
      if (window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(receiptModal).show();
      } else {
        window.open(url, '_blank', 'noopener');
      }
    });
  });

  document.getElementById('payoutReceiptViewerDownload')?.addEventListener('click', function() {
    const url = this.dataset.downloadUrl;
    if (!url) return;
    const originalHtml = this.innerHTML;
    this.disabled = true;
    const link = document.createElement('a');
    link.href = url;
    link.download = this.dataset.downloadName || 'payout-receipt.jpg';
    document.body.appendChild(link);
    link.click();
    link.remove();
    if (window.BerryBaseDownloads && typeof window.BerryBaseDownloads.notify === 'function') {
      window.BerryBaseDownloads.notify('Download started.');
    }
    setTimeout(() => {
      this.disabled = false;
      this.innerHTML = originalHtml;
    }, 900);
  });
});
</script>
@endsection
