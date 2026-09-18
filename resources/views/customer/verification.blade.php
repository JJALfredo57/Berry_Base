@extends('layouts.app')
@section('content')
@php
  $statusLabel = [
    'not_submitted' => 'Not Verified',
    'pending' => 'Pending Review',
    'approved' => 'Verified Customer',
    'rejected' => 'Needs Resubmission',
  ][$status] ?? ucfirst($status);
  $statusIcon = $status === 'approved' ? 'bi-patch-check-fill' : ($status === 'pending' ? 'bi-hourglass-split' : 'bi-shield-exclamation');
@endphp
<style>
.verify-hero{position:relative;overflow:hidden;border-radius:8px;background:linear-gradient(135deg,#fff7fb,#eef6ff);border:1px solid #f3d6e5}
.verify-hero:after{content:"";position:absolute;inset:auto -80px -120px auto;width:260px;height:260px;border-radius:50%;background:rgba(233,30,99,.12);filter:blur(8px)}
.verify-step{display:flex;gap:.75rem;align-items:flex-start;position:relative}
.verify-step .dot{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#fff;border:1px solid #e5e7eb;color:#64748b;transition:.25s}
.verify-step.active .dot{background:var(--primary);color:#fff;box-shadow:0 8px 22px rgba(233,30,99,.22)}
.benefit-card{height:100%;border:1px solid #e5e7eb;border-radius:8px;padding:1rem;background:#fff;transition:.2s transform,.2s box-shadow}
.benefit-card:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(15,23,42,.08)}
.benefit-card.locked{background:#f8fafc;color:#64748b}
.upload-panel{border:1px solid #e5e7eb;border-radius:8px;background:#fff}
.verify-upload-card{border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:.9rem;height:100%;transition:.18s border-color,.18s box-shadow,.18s transform}
.verify-upload-card:focus-within{border-color:var(--primary);box-shadow:0 0 0 .2rem rgba(var(--primary-rgb,233,30,99),.12)}
.verify-upload-icon{width:38px;height:38px;border-radius:50%;display:grid;place-items:center;background:rgba(var(--primary-rgb,233,30,99),.1);color:var(--primary);flex:0 0 auto}
.verify-upload-card .form-control{font-size:.86rem}
.verify-upload-hint{font-size:.76rem;color:#64748b}
.verify-file-input{position:absolute;inline-size:1px;block-size:1px;opacity:0;overflow:hidden;clip:rect(0,0,0,0)}
.verify-upload-status{font-size:.76rem;color:#64748b;min-height:1.1rem}
.verify-member-tier{border:1px solid #e5e7eb;border-radius:8px;padding:.8rem;background:#fff}
.verify-member-tier.is-current{border-color:var(--primary);box-shadow:0 10px 24px rgba(var(--primary-rgb,233,30,99),.1)}
.verify-member-tier.is-locked{background:#f8fafc;color:#64748b}
@media (max-width:575.98px){.verify-hero{border-radius:0;margin-left:-.75rem;margin-right:-.75rem}.benefit-card{padding:.85rem}}
</style>

<div class="container-fluid py-4">
  @if(session('msg'))<div class="alert alert-success border-0">{{ session('msg') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger border-0">{{ session('error') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger border-0">{{ $errors->first() }}</div>@endif

  <div class="verify-hero p-4 p-md-5 mb-4">
    <div class="row g-4 align-items-center position-relative" style="z-index:1">
      <div class="col-lg-7">
        <div class="d-inline-flex align-items-center gap-2 rounded-pill px-3 py-1 mb-3" style="background:#fff;border:1px solid #f3d6e5">
          <i class="bi {{ $statusIcon }}" style="color:var(--primary)"></i>
          <span class="small fw-semibold">{{ $statusLabel }}</span>
        </div>
        <h3 class="fw-bold mb-2">Verify your BerryBase account</h3>
        <p class="text-muted mb-0">Upload a valid ID once to unlock rewards redemption, verified-only vouchers, and higher trust for larger COD/COP orders.</p>
      </div>
      <div class="col-lg-5">
        <div class="bg-white rounded-3 p-3 border">
          <div class="verify-step {{ in_array($status, ['pending','approved','rejected'], true) ? 'active' : '' }} mb-3">
            <div class="dot"><i class="bi bi-cloud-arrow-up"></i></div>
            <div><div class="fw-semibold small">Upload ID</div><div class="text-muted small">Submit clear front/back images.</div></div>
          </div>
          <div class="verify-step {{ in_array($status, ['pending','approved','rejected'], true) ? 'active' : '' }} mb-3">
            <div class="dot"><i class="bi bi-search"></i></div>
            <div><div class="fw-semibold small">Admin review</div><div class="text-muted small">Only authorized admins can review.</div></div>
          </div>
          <div class="verify-step {{ $status === 'approved' ? 'active' : '' }}">
            <div class="dot"><i class="bi bi-stars"></i></div>
            <div><div class="fw-semibold small">Benefits unlocked</div><div class="text-muted small">Use rewards and verified promos.</div></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    @foreach($benefits as $benefit)
      <div class="col-sm-6 col-xl-3">
        <div class="benefit-card {{ $benefit['unlocked'] ? '' : 'locked' }}">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <i class="bi {{ $benefit['icon'] }}" style="font-size:1.4rem;color:{{ $benefit['unlocked'] ? 'var(--primary)' : '#94a3b8' }}"></i>
            <span class="badge {{ $benefit['unlocked'] ? 'text-bg-success' : 'text-bg-light' }}">{{ $benefit['unlocked'] ? 'Unlocked' : 'Locked' }}</span>
          </div>
          <div class="fw-bold small mb-1">{{ $benefit['title'] }}</div>
          <div class="text-muted small">{{ $benefit['copy'] }}</div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="upload-panel p-4">
        <h5 class="fw-bold mb-1">Submit valid ID</h5>
        <p class="text-muted small mb-3">Take a clear photo with your phone camera or upload an existing file. Accepted: JPG, PNG, WebP, or PDF up to 5MB.</p>
        @if($status === 'pending')
          <div class="alert alert-warning border-0 mb-0"><i class="bi bi-hourglass-split me-1"></i>Your latest submission is pending review. You can still order normally.</div>
        @elseif($status === 'approved')
          <div class="alert alert-success border-0 mb-0"><i class="bi bi-patch-check-fill me-1"></i>Your account is verified. Benefits are active.</div>
        @else
          @if($status === 'rejected' && $latest?->rejection_reason)
            <div class="alert alert-danger border-0"><strong>Reason:</strong> {{ $latest->rejection_reason }}</div>
          @endif
          <form action="{{ route('customer.verification.store') }}" method="POST" enctype="multipart/form-data" data-prevent-double-submit>
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold small">ID Type</label>
              <select class="form-select" name="id_type" required>
                <option value="">Select ID type</option>
                <option>National ID</option>
                <option>Driver's License</option>
                <option>Passport</option>
                <option>UMID</option>
                <option>Postal ID</option>
                <option>Student ID</option>
                <option>Other Government ID</option>
              </select>
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <div class="verify-upload-card">
                  <div class="d-flex gap-2 align-items-start mb-2">
                    <div class="verify-upload-icon"><i class="bi bi-camera"></i></div>
                    <div>
                      <label class="form-label fw-semibold small mb-1">Front of ID</label>
                      <div class="verify-upload-hint">Use the rear camera or choose a saved file.</div>
                    </div>
                  </div>
                  <input type="file" class="verify-file-input" id="idFrontInput" name="id_front" accept="image/*,.pdf" capture="environment" required data-size-preview-target="idUploadSummary">
                  <button type="button" class="btn btn-outline-primary w-100" data-upload-trigger="idFrontInput">
                    <i class="bi bi-camera me-1"></i>Get Front ID Picture
                  </button>
                  <div class="verify-upload-status mt-2" data-upload-status-for="idFrontInput">No file selected yet.</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="verify-upload-card">
                  <div class="d-flex gap-2 align-items-start mb-2">
                    <div class="verify-upload-icon"><i class="bi bi-camera"></i></div>
                    <div>
                      <label class="form-label fw-semibold small mb-1">Back of ID <span class="text-muted fw-normal">(optional)</span></label>
                      <div class="verify-upload-hint">Capture the back side if your ID has details there.</div>
                    </div>
                  </div>
                  <input type="file" class="verify-file-input" id="idBackInput" name="id_back" accept="image/*,.pdf" capture="environment" data-size-preview-target="idUploadSummary">
                  <button type="button" class="btn btn-outline-primary w-100" data-upload-trigger="idBackInput">
                    <i class="bi bi-camera me-1"></i>Get Back ID Picture
                  </button>
                  <div class="verify-upload-status mt-2" data-upload-status-for="idBackInput">Optional file not selected.</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="verify-upload-card">
                  <div class="d-flex gap-2 align-items-start mb-2">
                    <div class="verify-upload-icon"><i class="bi bi-person-bounding-box"></i></div>
                    <div>
                      <label class="form-label fw-semibold small mb-1">Selfie with ID <span class="text-muted fw-normal">(optional)</span></label>
                      <div class="verify-upload-hint">Front camera opens on most phones.</div>
                    </div>
                  </div>
                  <input type="file" class="verify-file-input" id="selfieInput" name="selfie" accept="image/*" capture="user" data-size-preview-target="idUploadSummary">
                  <button type="button" class="btn btn-outline-primary w-100" data-upload-trigger="selfieInput">
                    <i class="bi bi-person-bounding-box me-1"></i>Get Selfie Picture
                  </button>
                  <div class="verify-upload-status mt-2" data-upload-status-for="selfieInput">Optional selfie not selected.</div>
                </div>
              </div>
            </div>
            <div id="idUploadSummary" class="mt-2"></div>
            <div class="small text-muted mt-3"><i class="bi bi-lock me-1"></i>Your ID is used only for account verification and visible only to authorized admins.</div>
            <button class="btn btn-primary mt-3"><i class="bi bi-shield-check me-1"></i>Submit for Review</button>
          </form>
        @endif
      </div>
    </div>
    <div class="col-lg-5">
      @php
        $membership = $loyaltyOverview ?? [];
        $membershipTiers = $membership['tiers'] ?? [];
        $nextMembership = $membership['next_tier'] ?? null;
      @endphp
      <div class="card mb-3">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
            <h6 class="fw-bold mb-0"><i class="bi bi-award me-2" style="color:var(--primary)"></i>Membership Rewards</h6>
            <span class="badge text-bg-light">{{ $membership['current_tier'] ?? 'Bronze' }}</span>
          </div>
          <div class="text-muted small mb-3">
            {{ $status === 'approved' ? 'Verified redemption is active.' : 'Earn points now. Verify your account to redeem rewards and unlock verified-only vouchers.' }}
          </div>
          <div class="d-flex justify-content-between small mb-1">
            <span>{{ (int)($membership['lifetime_points'] ?? 0) }} lifetime points</span>
            <span>
              @if($nextMembership)
                {{ $membership['points_to_next'] ?? 0 }} to {{ $nextMembership->name }}
              @else
                Top tier
              @endif
            </span>
          </div>
          <div class="progress mb-3" style="height:8px">
            <div class="progress-bar" style="width:{{ (int)($membership['progress'] ?? 0) }}%;background:var(--primary)"></div>
          </div>
          <div class="vstack gap-2">
            @foreach($membershipTiers as $tier)
              <div class="verify-member-tier {{ $tier['is_current'] ? 'is-current' : '' }} {{ $tier['is_unlocked'] ? '' : 'is-locked' }}">
                <div class="d-flex align-items-center justify-content-between gap-2">
                  <div>
                    <div class="fw-semibold small">{{ $tier['name'] }} Member</div>
                    <div class="text-muted" style="font-size:.76rem">{{ number_format($tier['min_lifetime_points']) }} lifetime pts • {{ rtrim(rtrim(number_format($tier['points_multiplier'], 2), '0'), '.') }}x points</div>
                  </div>
                  <span class="badge {{ $tier['is_current'] ? 'text-white' : ($tier['is_unlocked'] ? 'text-bg-success' : 'text-bg-light') }}" @if($tier['is_current']) style="background:var(--primary)" @endif>
                    {{ $tier['is_current'] ? 'Current' : ($tier['is_unlocked'] ? 'Unlocked' : 'Locked') }}
                  </span>
                </div>
                <div class="text-muted mt-2" style="font-size:.78rem">{{ $tier['perk_summary'] ?: ($tier['benefits'][0] ?? 'Earn rewards on completed orders.') }}</div>
              </div>
            @endforeach
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-body">
          <h6 class="fw-bold mb-3">While not verified</h6>
          @if(count($limitations))
            @foreach($limitations as $item)
              <div class="d-flex gap-2 mb-2 small"><i class="bi bi-lock text-muted"></i><span>{{ $item }}</span></div>
            @endforeach
          @else
            <div class="alert alert-success py-2 mb-0 small">No locked benefits. Your account is fully verified.</div>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-upload-trigger]').forEach(function (button) {
    button.addEventListener('click', function () {
      var input = document.getElementById(button.dataset.uploadTrigger);
      if (input) input.click();
    });
  });

  document.querySelectorAll('.verify-file-input').forEach(function (input) {
    input.addEventListener('change', function () {
      var status = document.querySelector('[data-upload-status-for="' + input.id + '"]');
      if (!status) return;
      var file = input.files && input.files[0] ? input.files[0] : null;
      status.textContent = file ? ('Selected: ' + file.name) : (input.required ? 'No file selected yet.' : 'Optional file not selected.');
      status.classList.toggle('text-success', !!file);
    });
  });
});
</script>
@endsection
