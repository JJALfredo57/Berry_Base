@extends('layouts.app')
@section('content')
@php
  $currentTier = $overview['current_tier'] ?? 'Bronze';
  $nextTier = $overview['next_tier'] ?? null;
  $progress = (int) ($overview['progress'] ?? 0);
  $pointsToNext = (int) ($overview['points_to_next'] ?? 0);
  $isVerified = ($verificationStatus ?? 'not_submitted') === 'approved';
  $loyaltySettings = $loyaltySettings ?? ($overview['settings'] ?? ['earn_enabled' => true, 'redeem_enabled' => true, 'points_base_amount' => 100, 'point_value' => 0.10, 'max_redemption_percent' => 20, 'redemption_requires_verified' => true]);
  $pointValue = (float) ($loyaltySettings['point_value'] ?? 0.10);
  $baseAmount = (float) ($loyaltySettings['points_base_amount'] ?? 100);
  $maxRedeemPercent = (float) ($loyaltySettings['max_redemption_percent'] ?? 20);
  $requiresVerifiedToRedeem = (bool) ($loyaltySettings['redemption_requires_verified'] ?? true);
  $redeemEnabled = !empty($loyaltySettings['redeem_enabled']);

  if (!$redeemEnabled) {
    $redemptionBadgeClass = 'text-bg-secondary';
    $redemptionBadgeIcon = 'bi-pause-circle';
    $redemptionBadgeText = 'Redemption paused';
  } elseif ($requiresVerifiedToRedeem && !$isVerified) {
    $redemptionBadgeClass = 'text-bg-warning text-dark';
    $redemptionBadgeIcon = 'bi-shield-lock';
    $redemptionBadgeText = 'Redemption active (verification required)';
  } else {
    $redemptionBadgeClass = 'text-bg-success';
    $redemptionBadgeIcon = 'bi-check-circle';
    $redemptionBadgeText = 'Redemption active';
  }

  $tierKey = strtolower((string) $currentTier);
  $tierIcon = match ($tierKey) {
      'silver' => 'bi-stars',
      'gold' => 'bi-gem',
      default => 'bi-award',
  };
@endphp
<style>
  .rewards-page .rewards-progress-wrap{
    border:1px solid #f5d0e2;
    border-radius:16px;
    background:linear-gradient(135deg,#fff,#fff6fb);
    padding:1rem;
  }
  .rewards-page .level-journey-track{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:.5rem;
    margin-bottom:.75rem;
  }
  .rewards-page .level-pill{
    border:1px solid #e5e7eb;
    border-radius:999px;
    background:#fff;
    padding:.45rem .5rem;
    text-align:center;
    font-size:.78rem;
    color:#475569;
    white-space:nowrap;
  }
  .rewards-page .level-pill i{margin-right:.25rem}
  .rewards-page .level-pill.is-current{
    border-color:var(--primary);
    color:var(--primary);
    box-shadow:0 8px 20px rgba(var(--primary-rgb,233,30,99),.18);
    font-weight:700;
  }
  .rewards-page .level-pill.is-unlocked{
    border-color:#c7eed8;
    background:#f0fdf4;
    color:#166534;
  }
  .rewards-page .cake-progress{
    height:12px;
    border-radius:999px;
    overflow:hidden;
    background:#fee2ee;
    border:1px solid #fbcfe8;
  }
  .rewards-page .cake-progress .progress-bar{
    position:relative;
    background:linear-gradient(90deg,#ec4899,#f97316);
  }
  .rewards-page .cake-progress .progress-bar::after{
    content:'';
    position:absolute;
    inset:0;
    background:linear-gradient(120deg,transparent 30%,rgba(255,255,255,.55) 50%,transparent 70%);
    animation:rewardsShimmer 2.6s linear infinite;
  }
  .rewards-page .how-works-flow{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:.5rem;
    margin-top:.75rem;
    margin-bottom:.4rem;
  }
  .rewards-page .how-step{
    border:1px solid #e5e7eb;
    border-radius:12px;
    background:#fff;
    padding:.7rem;
    text-align:center;
    font-size:.76rem;
    color:#475569;
  }
  .rewards-page .how-step i{
    display:block;
    color:var(--primary);
    font-size:1rem;
    margin-bottom:.2rem;
  }
  .rewards-page .reward-headline{
    font-size:.78rem;
    font-weight:600;
    color:#334155;
  }
  @keyframes rewardsShimmer {
    0% { transform:translateX(-100%); }
    100% { transform:translateX(100%); }
  }
  @media (max-width: 767.98px) {
    .rewards-page .level-journey-track,
    .rewards-page .how-works-flow{
      grid-template-columns:1fr;
    }
    .rewards-page .level-pill,
    .rewards-page .how-step{
      text-align:left;
      white-space:normal;
    }
    .rewards-page .stats-col{
      width:100%;
    }
  }
</style>
<div class="container-fluid py-4">
  <div class="rewards-page">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-award me-2" style="color:var(--primary)"></i>My Rewards</h4>
        <div class="text-muted small">View earned points, available vouchers, and rewards activity.</div>
      </div>
      <a href="{{ route('customer.verification') }}" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-shield-check me-1"></i>{{ $isVerified ? 'Verified' : 'Verify to Redeem' }}
      </a>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-12 col-md-4 stats-col">
        <div class="card h-100"><div class="card-body">
          <div class="small text-muted">Membership</div>
          <div class="h5 fw-bold mb-0"><i class="bi {{ $tierIcon }} me-1"></i>{{ $currentTier }}</div>
        </div></div>
      </div>
      <div class="col-12 col-md-4 stats-col">
        <div class="card h-100"><div class="card-body">
          <div class="small text-muted">Available Points</div>
          <div class="h5 fw-bold mb-0" style="color:var(--primary)">{{ (int)($overview['balance'] ?? 0) }}</div>
        </div></div>
      </div>
      <div class="col-12 col-md-4 stats-col">
        <div class="card h-100"><div class="card-body">
          <div class="small text-muted">Next Level</div>
          <div class="h6 fw-bold mb-0">
            @if($nextTier)
              {{ $pointsToNext }} pts to {{ $nextTier->name }}
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
            <div class="text-muted small">Rewards are a capped customer discount. Platform income remains handled through seller commission and payout rules.</div>
          </div>
          <span class="badge {{ $redemptionBadgeClass }}">
            <i class="bi {{ $redemptionBadgeIcon }} me-1"></i>{{ $redemptionBadgeText }}
          </span>
        </div>

        <div class="rewards-progress-wrap mt-3">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <div class="reward-headline">Cake Level Journey</div>
            <div class="small text-muted">
              @if($nextTier)
                {{ $pointsToNext }} points more to {{ $nextTier->name }}
              @else
                Top tier unlocked
              @endif
            </div>
          </div>
          <div class="level-journey-track">
            <div class="level-pill {{ in_array(strtolower((string)$currentTier), ['bronze','silver','gold']) ? 'is-unlocked' : '' }} {{ strtolower((string)$currentTier)==='bronze' ? 'is-current' : '' }}"><i class="bi bi-award"></i>Bronze</div>
            <div class="level-pill {{ in_array(strtolower((string)$currentTier), ['silver','gold']) ? 'is-unlocked' : '' }} {{ strtolower((string)$currentTier)==='silver' ? 'is-current' : '' }}"><i class="bi bi-stars"></i>Silver</div>
            <div class="level-pill {{ strtolower((string)$currentTier)==='gold' ? 'is-unlocked is-current' : '' }}"><i class="bi bi-gem"></i>Gold</div>
          </div>
          <div class="cake-progress progress" role="progressbar" aria-label="Membership progress" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" style="width:{{ $progress }}%"></div>
          </div>
        </div>

        <div class="how-works-flow">
          <div class="how-step"><i class="bi bi-bag-check"></i>Complete order<br><span class="text-muted">Paid + completed</span></div>
          <div class="how-step"><i class="bi bi-stars"></i>Earn points<br><span class="text-muted">Based on subtotal</span></div>
          <div class="how-step"><i class="bi bi-ticket-perforated"></i>Redeem discount<br><span class="text-muted">If rules are met</span></div>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-md-4">
            <div class="p-3 rounded-3 h-100" style="background:#f8fafc;border:1px solid #e5e7eb">
              <div class="fw-semibold small mb-1">Earn</div>
              <div class="small text-muted">{{ !empty($loyaltySettings['earn_enabled']) ? 'Every PHP '.number_format($baseAmount, 2).' product item subtotal earns 1 base point after the order is paid and completed.' : 'Point earning is currently paused.' }}</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 rounded-3 h-100" style="background:#f8fafc;border:1px solid #e5e7eb">
              <div class="fw-semibold small mb-1">Redeem</div>
              <div class="small text-muted">1 point gives a small PHP {{ number_format($pointValue, 2) }} checkout discount. Points can cover up to {{ rtrim(rtrim(number_format($maxRedeemPercent, 2), '0'), '.') }}% of product subtotal after vouchers.</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 rounded-3 h-100" style="background:#f8fafc;border:1px solid #e5e7eb">
              <div class="fw-semibold small mb-1">Verification</div>
              <div class="small text-muted">{{ $requiresVerifiedToRedeem ? 'Without valid ID approval, you can earn points but cannot redeem points or verified-only vouchers.' : 'Valid ID approval supports Customer Loyalty Trust and unlocks verified-only vouchers.' }}</div>
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
                      @if($voucher->requires_verified_customer) | Verified only @endif
                      @if($voucher->ends_at) | Until {{ \Carbon\Carbon::parse($voucher->ends_at)->format('M d, Y') }} @endif
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
</div>
@endsection
