@extends('layouts.app')
@section('content')
@php
  $currentTier = $overview['current_tier'] ?? 'Bronze';
  $nextTier = $overview['next_tier'] ?? null;
  $isVerified = ($verificationStatus ?? 'not_submitted') === 'approved';
  $loyaltySettings = $loyaltySettings ?? ($overview['settings'] ?? ['earn_enabled' => true, 'redeem_enabled' => true, 'points_base_amount' => 50, 'point_value' => 1, 'max_redemption_percent' => 50, 'redemption_requires_verified' => true]);
  $pointValue = (float) ($loyaltySettings['point_value'] ?? 1);
  $baseAmount = (float) ($loyaltySettings['points_base_amount'] ?? 50);
  $maxRedeemPercent = (float) ($loyaltySettings['max_redemption_percent'] ?? 50);
  $requiresVerifiedToRedeem = (bool) ($loyaltySettings['redemption_requires_verified'] ?? true);
@endphp
<div class="container-fluid py-4">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
      <h4 class="fw-bold mb-1"><i class="bi bi-award me-2" style="color:var(--primary)"></i>My Rewards</h4>
      <div class="text-muted small">View points, available vouchers, and rewards activity.</div>
    </div>
    <a href="{{ route('customer.verification') }}" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-shield-check me-1"></i>{{ $isVerified ? 'Verified' : 'Verify to Redeem' }}
    </a>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-sm-4">
      <div class="card h-100"><div class="card-body">
        <div class="small text-muted">Membership</div>
        <div class="h5 fw-bold mb-0">{{ $currentTier }}</div>
      </div></div>
    </div>
    <div class="col-sm-4">
      <div class="card h-100"><div class="card-body">
        <div class="small text-muted">Available Points</div>
        <div class="h5 fw-bold mb-0" style="color:var(--primary)">{{ (int)($overview['balance'] ?? 0) }}</div>
      </div></div>
    </div>
    <div class="col-sm-4">
      <div class="card h-100"><div class="card-body">
        <div class="small text-muted">Next Level</div>
        <div class="h6 fw-bold mb-0">
          @if($nextTier)
            {{ $overview['points_to_next'] ?? 0 }} pts to {{ $nextTier->name }}
          @else
            Top tier unlocked
          @endif
        </div>
      </div></div>
    </div>
  </div>

  @if(!$isVerified)
    <div class="alert alert-warning border-0"><i class="bi bi-lock me-1"></i>You can earn points now, but redemption and verified-only vouchers unlock after valid ID approval.</div>
  @endif

  <div class="card mb-4">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
        <div>
          <h6 class="fw-bold mb-1"><i class="bi bi-info-circle me-2" style="color:var(--primary)"></i>How Rewards Work</h6>
          <div class="text-muted small">Rules shown here follow the current Superadmin rewards settings.</div>
        </div>
        <span class="badge {{ !empty($loyaltySettings['redeem_enabled']) ? 'text-bg-success' : 'text-bg-secondary' }}">{{ !empty($loyaltySettings['redeem_enabled']) ? 'Redemption active' : 'Redemption paused' }}</span>
      </div>
      <div class="row g-3 mt-1">
        <div class="col-md-4">
          <div class="p-3 rounded-3 h-100" style="background:#f8fafc;border:1px solid #e5e7eb">
            <div class="fw-semibold small mb-1">Earn</div>
            <div class="small text-muted">{{ !empty($loyaltySettings['earn_enabled']) ? 'Every PHP '.number_format($baseAmount, 2).' product subtotal earns 1 base point after the order is paid and completed.' : 'Point earning is currently paused.' }}</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="p-3 rounded-3 h-100" style="background:#f8fafc;border:1px solid #e5e7eb">
            <div class="fw-semibold small mb-1">Redeem</div>
            <div class="small text-muted">1 point is worth PHP {{ number_format($pointValue, 2) }}. Points can cover up to {{ rtrim(rtrim(number_format($maxRedeemPercent, 2), '0'), '.') }}% of product subtotal after vouchers.</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="p-3 rounded-3 h-100" style="background:#f8fafc;border:1px solid #e5e7eb">
            <div class="fw-semibold small mb-1">Verification</div>
            <div class="small text-muted">{{ $requiresVerifiedToRedeem ? 'Without valid ID approval, you can earn points but cannot redeem points or verified-only vouchers.' : 'Valid ID approval improves trust and unlocks verified-only vouchers.' }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-body">
          <h6 class="fw-bold mb-3"><i class="bi bi-ticket-perforated me-2" style="color:var(--primary)"></i>Available Vouchers</h6>
          <div class="row g-3">
            @forelse($walletVouchers as $voucher)
              <div class="col-md-6">
                <div class="h-100 p-3 rounded-3" style="border:1px solid #e5e7eb;background:#fff">
                  <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                      <div class="fw-bold">{{ $voucher->name }}</div>
                      <div class="small text-muted">{{ $voucher->shop_name ?: 'All BerryBase shops' }}</div>
                    </div>
                    <span class="badge {{ $voucher->audience === 'assigned' ? 'text-bg-success' : 'text-bg-light' }}">{{ $voucher->audience === 'assigned' ? 'For you' : 'Public' }}</span>
                  </div>
                  <div class="my-3 p-2 rounded text-center fw-bold" style="background:var(--primary-bg);color:var(--primary);letter-spacing:.08em">{{ $voucher->code }}</div>
                  <div class="small text-muted">
                    Min spend PHP {{ number_format((float)$voucher->minimum_order_amount, 2) }}
                    @if($voucher->requires_verified_customer) • Verified only @endif
                    @if($voucher->ends_at) • Until {{ \Carbon\Carbon::parse($voucher->ends_at)->format('M d, Y') }} @endif
                  </div>
                  <button class="btn btn-outline-primary btn-sm w-100 mt-3" type="button" onclick="navigator.clipboard?.writeText('{{ $voucher->code }}');this.innerText='Copied';">
                    <i class="bi bi-clipboard me-1"></i>Copy Code
                  </button>
                </div>
              </div>
            @empty
              <div class="col-12 text-center text-muted py-4">No active vouchers yet.</div>
            @endforelse
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card">
        <div class="card-body">
          <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2" style="color:var(--primary)"></i>Points History</h6>
          @forelse($transactions as $txn)
            <div class="d-flex justify-content-between gap-2 py-2 border-bottom">
              <div>
                <div class="fw-semibold small">{{ $txn->description ?: ucfirst($txn->type) }}</div>
                <div class="text-muted" style="font-size:.75rem">{{ \Carbon\Carbon::parse($txn->created_at)->format('M d, Y g:i A') }}</div>
              </div>
              <div class="fw-bold {{ (int)$txn->points >= 0 ? 'text-success' : 'text-danger' }}">{{ (int)$txn->points >= 0 ? '+' : '' }}{{ (int)$txn->points }}</div>
            </div>
          @empty
            <div class="text-center text-muted py-4">No points activity yet.</div>
          @endforelse
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
