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
.verify-upload-status{font-size:.76rem;color:#64748b;min-height:1.1rem;transition:color .18s ease,opacity .18s ease}
.verify-member-tier{border:1px solid #e5e7eb;border-radius:8px;padding:.8rem;background:#fff}
.verify-member-tier.is-current{border-color:var(--primary);box-shadow:0 10px 24px rgba(var(--primary-rgb,233,30,99),.1)}
.verify-member-tier.is-locked{background:#f8fafc;color:#64748b}
.verify-wizard{display:flex;gap:.5rem;flex-wrap:wrap}
.verify-wizard-step{display:flex;align-items:center;gap:.45rem;border:1px solid #e5e7eb;border-radius:8px;padding:.55rem .75rem;font-size:.82rem;color:#64748b;background:#fff}
.verify-wizard-step span{width:22px;height:22px;border-radius:50%;display:grid;place-items:center;background:#eef2f7;color:#64748b;font-weight:700;font-size:.72rem}
.verify-wizard-step.active{border-color:var(--primary);color:var(--primary);background:rgba(var(--primary-rgb,233,30,99),.06)}
.verify-wizard-step.active span,.verify-wizard-step.done span{background:var(--primary);color:#fff}
.verify-ready-panel{border:1px solid #e5e7eb;border-radius:8px;background:#f8fafc;padding:1rem}
.verify-scanner-modal{position:fixed!important;inset:0!important;overflow:hidden!important;padding:.75rem}
.verify-scanner-modal.show{display:flex!important;align-items:center;justify-content:center}
.verify-scanner-modal .modal-dialog{width:100%;height:min(100dvh - 1.5rem,900px);max-height:calc(100dvh - 1.5rem);margin:0 auto;display:flex;align-items:stretch}
.verify-scanner-modal .modal-content{background:#08111f;color:#e5eefb;border:0;min-height:0;height:100%;max-height:100%;overflow:hidden;display:flex;flex-direction:column}
.verify-scanner-modal .modal-header,.verify-scanner-modal .modal-footer{border-color:rgba(148,163,184,.22);background:rgba(8,17,31,.96);flex:0 0 auto}
.verify-scanner-modal .modal-body{overflow:hidden;min-height:0;flex:1 1 auto;display:flex;flex-direction:column}
.verify-scanner-modal .btn-close{filter:invert(1) grayscale(100%)}
.verify-scanner-modal .verify-upload-hint{color:#9fb0c8}
.verify-scanner-modal .verify-wizard-step{background:rgba(15,23,42,.82);border-color:rgba(148,163,184,.28);color:#a8b5c8}
.verify-scanner-modal .verify-wizard-step span{background:#172235;color:#a8b5c8}
.verify-scanner-modal .verify-wizard-step.active{background:rgba(var(--primary-rgb,233,30,99),.18);border-color:rgba(var(--primary-rgb,233,30,99),.72);color:#fff}
.verify-scanner-modal .verify-wizard-step.done{border-color:rgba(34,197,94,.72);color:#d1fae5}
.verify-scanner-modal .verify-wizard-step.done span{background:#15803d;color:#fff}
.verify-scan-stage{border:1px solid rgba(148,163,184,.22);border-radius:8px;padding:1rem;background:rgba(15,23,42,.7)}
.verify-camera-frame{position:relative;aspect-ratio:16/9;background:#020617;border-radius:8px;overflow:hidden;display:grid;place-items:center;height:clamp(300px,52vh,520px);min-height:0}
.verify-camera-frame video{width:100%;height:100%;object-fit:cover;background:#020617}
.verify-camera-frame.is-selfie video{transform:scaleX(-1)}
.verify-camera-frame:after{content:"";position:absolute;left:8%;right:8%;top:50%;height:2px;background:linear-gradient(90deg,transparent,rgba(56,189,248,.85),transparent);box-shadow:0 0 20px rgba(56,189,248,.6);animation:verifyScanLine 1.9s ease-in-out infinite;opacity:.8}
.verify-frame-guide{position:absolute;left:50%;top:50%;width:min(86%,680px);aspect-ratio:1.586/1;max-height:72%;transform:translate(-50%,-50%);border:2px solid rgba(255,255,255,.92);border-radius:8px;box-shadow:0 0 0 999px rgba(2,6,23,.42),0 0 26px rgba(56,189,248,.22)}
.verify-frame-guide:before{content:"ALIGN ID INSIDE";position:absolute;left:50%;top:.45rem;transform:translateX(-50%);font-size:.68rem;font-weight:800;letter-spacing:.08em;color:#fff;background:rgba(2,6,23,.62);border:1px solid rgba(255,255,255,.34);border-radius:999px;padding:.18rem .5rem;white-space:nowrap}
.verify-frame-guide:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent calc(50% - 1px),rgba(255,255,255,.62) 50%,transparent calc(50% + 1px)),linear-gradient(0deg,transparent calc(50% - 1px),rgba(255,255,255,.62) 50%,transparent calc(50% + 1px));opacity:.75;pointer-events:none}
.verify-face-guide{position:absolute;width:min(44%,250px);aspect-ratio:3/4;border:2px solid rgba(255,255,255,.92);border-radius:50%;box-shadow:0 0 0 999px rgba(2,6,23,.42),0 0 26px rgba(56,189,248,.22)}
.verify-live-panel{position:absolute;left:1rem;right:1rem;bottom:1rem;display:flex;gap:.75rem;align-items:center;justify-content:space-between;padding:.75rem .85rem;border-radius:8px;background:rgba(2,6,23,.82);backdrop-filter:blur(10px);color:#fff;font-size:.84rem;transition:background .18s ease,transform .18s ease}
.verify-live-meter{width:120px;height:8px;background:rgba(148,163,184,.38);border-radius:999px;overflow:hidden;flex:0 0 auto}
.verify-live-meter span{display:block;height:100%;width:0;background:#ef4444;transition:width .22s ease,background .22s ease}
.verify-live-meter.is-good span{background:#22c55e}.verify-live-meter.is-warn span{background:#f59e0b}
.verify-liveness-card{border:1px solid rgba(56,189,248,.35);border-radius:8px;background:rgba(14,165,233,.1);padding:.75rem;color:#e0f2fe}
.verify-scanner-modal.is-selfie-step .modal-content{background:#f8fbff;color:#142033}
.verify-scanner-modal.is-selfie-step .modal-header,.verify-scanner-modal.is-selfie-step .modal-footer{background:#fff;border-color:#dbe7f3;color:#142033}
.verify-scanner-modal.is-selfie-step .btn-close{filter:none}
.verify-scanner-modal.is-selfie-step .modal-body{padding-top:.55rem}
.verify-scanner-modal.is-selfie-step .verify-wizard{margin-bottom:.45rem!important}
.verify-scanner-modal.is-selfie-step .verify-scan-stage[data-step="selfie"]{background:#fff;border-color:#cbd5e1;box-shadow:0 0 0 999px rgba(255,255,255,.55);padding:.65rem;display:flex;flex-direction:column;min-height:0;flex:1 1 auto}
.verify-scanner-modal.is-selfie-step .verify-liveness-card{background:#eef8ff;border-color:#7dd3fc;color:#0f2a3f;padding:.5rem;margin-bottom:.45rem!important}
.verify-scanner-modal.is-selfie-step .verify-upload-hint{color:#475569}
.verify-scanner-modal.is-selfie-step .verify-wizard-step{background:#fff;color:#475569;border-color:#dbe7f3}
.verify-scanner-modal.is-selfie-step .verify-wizard-step.active{background:#fff1f7;color:var(--primary);border-color:rgba(var(--primary-rgb,233,30,99),.55)}
.verify-scanner-modal.is-selfie-step .verify-screen-light-note{display:inline-flex}
.verify-scanner-modal.is-selfie-step .verify-camera-frame.is-selfie{height:clamp(300px,58dvh,620px);max-height:none;flex:1 1 auto}
.verify-scanner-modal.is-selfie-step .verify-face-guide{width:min(42%,190px)}
.verify-scanner-modal.is-selfie-step [data-step="selfie"] > .d-flex:first-child{margin-bottom:.45rem!important}
.verify-scanner-modal.is-selfie-step [data-step="selfie"] .verify-upload-hint{font-size:.72rem}
.verify-scanner-modal.is-selfie-step .btn-outline-light{color:#0f172a;border-color:#94a3b8}
.verify-scanner-modal.is-selfie-step .btn-outline-light:hover{background:#e2e8f0;color:#0f172a}
.verify-screen-light-note{display:none;align-items:center;gap:.35rem;font-size:.74rem;color:#0f766e;background:#ccfbf1;border:1px solid #5eead4;border-radius:999px;padding:.25rem .55rem}
.verify-scanner-modal .verify-upload-status.text-danger{color:#fca5a5!important}.verify-scanner-modal .verify-upload-status.text-success{color:#86efac!important}.verify-scanner-modal .verify-upload-status.text-warning{color:#fde68a!important}
@keyframes verifyScanLine{0%,100%{transform:translateY(-22vh);opacity:.25}50%{transform:translateY(22vh);opacity:.9}}
.verify-upload-status.text-danger{color:#dc2626!important}.verify-upload-status.text-success{color:#15803d!important}
@media (max-width:575.98px){.verify-hero{border-radius:0;margin-left:-.75rem;margin-right:-.75rem}.benefit-card{padding:.85rem}.upload-panel{padding:1rem!important}.verify-scanner-modal{padding:.35rem}.verify-scanner-modal .modal-dialog{margin:0;max-width:none;width:100%;height:calc(100dvh - .7rem);max-height:calc(100dvh - .7rem)}.verify-scanner-modal .modal-content{height:100%;min-height:0}.verify-scanner-modal .modal-header{padding:.7rem .85rem}.verify-scanner-modal .modal-title{font-size:.95rem}.verify-scanner-modal .modal-title + .small{font-size:.72rem}.verify-scanner-modal .modal-body{padding:.75rem;min-height:0}.verify-scanner-modal .modal-footer{padding:.65rem .75rem;gap:.5rem}.verify-scanner-modal .modal-footer .btn{flex:1 1 auto;padding:.55rem .6rem;font-size:.82rem}.verify-scanner-modal .verify-wizard{gap:.35rem;flex-wrap:nowrap;overflow-x:auto;padding-bottom:.15rem}.verify-scanner-modal .verify-wizard-step{flex:0 0 auto;padding:.42rem .55rem;font-size:.74rem}.verify-scanner-modal .verify-wizard-step span{width:19px;height:19px;font-size:.66rem}.verify-scan-stage{padding:.65rem;border-radius:8px}.verify-scan-stage .d-flex{gap:.5rem!important;align-items:flex-start!important}.verify-scan-stage h6{font-size:.88rem}.verify-scan-stage .small{font-size:.72rem}.verify-camera-frame{height:min(46dvh,340px);min-height:220px;max-height:calc(100dvh - 300px);aspect-ratio:auto;border-radius:8px}.verify-frame-guide{width:90%;max-height:64%}.verify-face-guide{width:min(50%,180px)}.verify-live-panel{left:.5rem;right:.5rem;bottom:.5rem;align-items:flex-start;flex-direction:column;gap:.45rem;padding:.55rem .6rem;font-size:.74rem;max-height:38%;overflow:auto}.verify-live-meter{width:100%;height:7px}.verify-liveness-card{padding:.55rem;font-size:.78rem}.verify-scanner-modal.is-selfie-step .modal-header{padding:.52rem .75rem}.verify-scanner-modal.is-selfie-step .modal-body{padding:.5rem .65rem}.verify-scanner-modal.is-selfie-step .modal-footer{padding:.5rem .65rem}.verify-scanner-modal.is-selfie-step .verify-wizard-step{padding:.34rem .48rem;font-size:.7rem}.verify-scanner-modal.is-selfie-step .verify-upload-icon{width:30px;height:30px}.verify-scanner-modal.is-selfie-step .verify-liveness-card{font-size:.72rem}.verify-scanner-modal.is-selfie-step .verify-camera-frame.is-selfie{height:auto;min-height:320px;max-height:none;flex:1 1 auto}}
@media (max-width:360px){.verify-camera-frame{height:40dvh;min-height:200px;max-height:calc(100dvh - 320px)}.verify-scanner-modal .modal-footer .btn{font-size:.76rem;padding:.5rem .45rem}.verify-live-panel{font-size:.7rem}.verify-scanner-modal.is-selfie-step .verify-camera-frame.is-selfie{min-height:300px}}
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
        <p class="text-muted small mb-3">Use the guided scanner for a clear ID and live face verification. Mobile uses camera-only scanning; desktop can upload ID photos, but face verification always requires a supported camera.</p>
        @if($status === 'pending')
          <div class="alert alert-warning border-0 mb-0"><i class="bi bi-hourglass-split me-1"></i>Your latest submission is pending review. You can still order normally.</div>
        @elseif($status === 'approved')
          <div class="alert alert-success border-0 mb-0"><i class="bi bi-patch-check-fill me-1"></i>Your account is verified. Benefits are active.</div>
        @else
          @if($status === 'rejected' && $latest?->rejection_reason)
            <div class="alert alert-danger border-0"><strong>Reason:</strong> {{ $latest->rejection_reason }}</div>
          @endif
          <form action="{{ route('customer.verification.store') }}" method="POST" enctype="multipart/form-data" id="verificationWizardForm" data-scan-url="{{ route('customer.verification.scan_front') }}" data-face-url="{{ route('customer.verification.compare_face') }}" data-prevent-double-submit>
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold small">ID Type</label>
              <select class="form-select" name="id_type" id="verificationIdType" required>
                <option value="">Select ID type</option>
                @foreach($idTypes as $idType)
                  <option value="{{ $idType }}" {{ old('id_type') === $idType ? 'selected' : '' }}>{{ $idType }}</option>
                @endforeach
              </select>
              <div class="verify-upload-hint mt-1" id="idTypeLockNotice">Select your ID type before scanning. It locks after the front ID is accepted.</div>
            </div>

            <input type="file" class="verify-file-input" id="idFrontInput" name="id_front" accept="image/*" capture="environment" required>
            <input type="file" class="verify-file-input" id="idBackInput" name="id_back" accept="image/*" capture="environment" required>
            <input type="file" class="verify-file-input" id="selfieInput" name="selfie" accept="image/*" capture="user" required>
            <input type="hidden" name="liveness_challenge" id="livenessChallengeInput">
            <input type="hidden" name="liveness_result" id="livenessResultInput" value="not_started">
            <input type="hidden" name="liveness_method" id="livenessMethodInput" value="not_available">
            <input type="hidden" name="id_front_hash" id="idFrontHashInput">
            <input type="hidden" name="id_back_hash" id="idBackHashInput">
            <input type="hidden" name="selfie_capture_source" id="selfieCaptureSourceInput" value="not_started">

            <div class="verify-ready-panel d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
              <div>
                <div class="fw-semibold">Smart ID scanner</div>
                <div class="verify-upload-hint">Camera-first scan, ID type matching, back-side check, live face comparison, and one-tap reset.</div>
              </div>
              <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" id="resetVerificationScan" disabled>
                  <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Scan
                </button>
                <button type="button" class="btn btn-primary" id="openVerificationScanner" data-bs-toggle="modal" data-bs-target="#verificationScannerModal" disabled>
                  <i class="bi bi-upc-scan me-1"></i>Start Verification
                </button>
              </div>
            </div>
            <div id="idUploadSummary" class="mt-3"></div>
            <div class="small text-muted mt-3"><i class="bi bi-lock me-1"></i>Your ID is checked before submit and then reviewed by authorized admins.</div>

            <div class="modal fade verify-scanner-modal" id="verificationScannerModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
              <div class="modal-dialog modal-fullscreen-sm-down modal-xl">
                <div class="modal-content">
                  <div class="modal-header">
                    <div>
                      <h5 class="modal-title fw-bold mb-0">Smart ID Verification</h5>
                      <div class="verify-upload-hint">Auto-captures only when the image is clear enough.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <div class="verify-wizard mb-3">
                      <div class="verify-wizard-step active" data-step-label="front"><span>1</span> Front ID</div>
                      <div class="verify-wizard-step" data-step-label="back"><span>2</span> Back ID</div>
                      <div class="verify-wizard-step" data-step-label="selfie"><span>3</span> Face</div>
                    </div>

                    <div class="verify-scan-stage" data-step="front">
                      <div class="d-flex align-items-start gap-2 mb-2">
                        <div class="verify-upload-icon"><i class="bi bi-credit-card-2-front"></i></div>
                        <div><div class="fw-semibold">Scan front of ID</div><div class="verify-upload-hint">Place the card in landscape inside the guide. The system will capture when the card is clear and steady.</div></div>
                      </div>
                      <div class="verify-camera-frame"><video playsinline muted></video><canvas hidden></canvas><div class="verify-frame-guide"></div><div class="verify-live-panel"><span data-live-hint>Open camera or upload a clear front ID.</span><div class="verify-live-meter"><span></span></div></div></div>
                      <div class="d-flex flex-wrap gap-2 mt-3">
                        <button type="button" class="btn btn-primary btn-sm" data-camera-start="idFrontInput" data-facing="environment"><i class="bi bi-camera-video me-1"></i>Start Auto Scan</button>
                        <button type="button" class="btn btn-outline-light btn-sm" data-camera-capture="idFrontInput"><i class="bi bi-camera me-1"></i>Capture Now</button>
                        <button type="button" class="btn btn-outline-light btn-sm" data-upload-trigger="idFrontInput" data-desktop-upload><i class="bi bi-upload me-1"></i>Upload ID Photo</button>
                      </div>
                      <div class="verify-upload-status mt-2" data-upload-status-for="idFrontInput">Waiting for front ID scan.</div>
                    </div>

                    <div class="verify-scan-stage d-none" data-step="back">
                      <div class="d-flex align-items-start gap-2 mb-2">
                        <div class="verify-upload-icon"><i class="bi bi-arrow-repeat"></i></div>
                        <div><div class="fw-semibold">Flip ID and scan back</div><div class="verify-upload-hint">Capture the back side after the front ID type is confirmed.</div></div>
                      </div>
                      <div class="verify-camera-frame"><video playsinline muted></video><canvas hidden></canvas><div class="verify-frame-guide"></div><div class="verify-live-panel"><span data-live-hint>Keep the back of the card landscape inside the guide.</span><div class="verify-live-meter"><span></span></div></div></div>
                      <div class="d-flex flex-wrap gap-2 mt-3">
                        <button type="button" class="btn btn-primary btn-sm" data-camera-start="idBackInput" data-facing="environment"><i class="bi bi-camera-video me-1"></i>Start Auto Scan</button>
                        <button type="button" class="btn btn-outline-light btn-sm" data-camera-capture="idBackInput"><i class="bi bi-camera me-1"></i>Capture Now</button>
                        <button type="button" class="btn btn-outline-light btn-sm" data-upload-trigger="idBackInput" data-desktop-upload><i class="bi bi-upload me-1"></i>Upload ID Photo</button>
                      </div>
                      <div class="verify-upload-status mt-2" data-upload-status-for="idBackInput">Waiting for back ID scan.</div>
                    </div>

                    <div class="verify-scan-stage d-none" data-step="selfie">
                      <div class="d-flex align-items-start gap-2 mb-2">
                        <div class="verify-upload-icon"><i class="bi bi-person-bounding-box"></i></div>
                        <div><div class="fw-semibold">Face verification</div><div class="verify-upload-hint">Keep your face centered. The screen brightens to help the front camera.</div><div class="verify-screen-light-note mt-1"><i class="bi bi-brightness-high"></i>Screen light active</div></div>
                      </div>
                      <div class="verify-liveness-card mb-2"><div class="small text-uppercase fw-semibold" style="letter-spacing:.04em">Liveness Challenge</div><div class="fw-semibold" id="livenessPrompt">Open the front camera to receive a live challenge.</div><div class="small" id="livenessProgress">This helps prevent uploaded fake selfies.</div></div>
                      <div class="verify-camera-frame is-selfie"><video playsinline muted></video><canvas hidden></canvas><div class="verify-face-guide"></div><div class="verify-live-panel"><span data-live-hint>Open front camera and center your face.</span><div class="verify-live-meter"><span></span></div></div></div>
                      <div class="d-flex flex-wrap gap-2 mt-3">
                        <button type="button" class="btn btn-primary btn-sm" data-camera-start="selfieInput" data-facing="user"><i class="bi bi-camera-video me-1"></i>Start Auto Scan</button>
                        <button type="button" class="btn btn-outline-light btn-sm" data-camera-capture="selfieInput"><i class="bi bi-person-bounding-box me-1"></i>Capture Now</button>
                        <span class="verify-upload-hint align-self-center"><i class="bi bi-camera-video me-1"></i>Live camera only. Selfie upload is disabled.</span>
                      </div>
                      <div class="verify-upload-status mt-2" data-upload-status-for="selfieInput">Waiting for face verification.</div>
                    </div>
                  </div>
                  <div class="modal-footer justify-content-between">
                    <div class="verify-upload-hint"><i class="bi bi-shield-lock me-1"></i>Server checks the front ID again before saving.</div>
                    <div class="d-flex flex-wrap gap-2">
                      <button type="button" class="btn btn-outline-light" id="resetVerificationScanFooter" disabled><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
                      <button class="btn btn-success" id="verificationSubmitButton" disabled><i class="bi bi-shield-check me-1"></i>Submit for Review</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
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
                    <div class="text-muted" style="font-size:.76rem">{{ number_format($tier['min_lifetime_points']) }} lifetime pts - {{ rtrim(rtrim(number_format($tier['points_multiplier'], 2), '0'), '.') }}x points</div>
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
  var form = document.getElementById('verificationWizardForm');
  if (!form) return;

  var scanUrl = form.dataset.scanUrl;
  var faceUrl = form.dataset.faceUrl;
  var token = form.querySelector('input[name="_token"]')?.value || '';
  var idType = document.getElementById('verificationIdType');
  var submitButton = document.getElementById('verificationSubmitButton');
  var launchButton = document.getElementById('openVerificationScanner');
  var resetButton = document.getElementById('resetVerificationScan');
  var resetFooterButton = document.getElementById('resetVerificationScanFooter');
  var modalEl = document.getElementById('verificationScannerModal');
  var state = { front:false, back:false, selfie:false, frontFile:null, backFile:null, selfieFile:null, lockedIdType:null, isMobileDevice:false, cameraCaptureInput:null, stream:null, activeInput:null, activeLoop:0, stableFrames:0, processing:false, currentStep:'front', stepStartedAt:0, lastCompareStartedAt:0, lastLive:{}, autoStarting:false, faceCompare:{ lastAt:0, inFlight:false, status:null, score:null, message:null }, liveness:{ challenge:null, baseline:null, passed:false, unsupported:false }, faceLandmarker:null, faceLandmarkerPromise:null, faceLandmarkerFailed:false, faceLandmarkerStartedAt:0 };

  function statusFor(inputId) { return document.querySelector('[data-upload-status-for="' + inputId + '"]'); }
  function setStatus(inputId, message, type) {
    var el = statusFor(inputId);
    if (!el) return;
    if (el.textContent !== message) el.textContent = message;
    el.classList.remove('text-success','text-danger','text-warning');
    if (type) el.classList.add(type);
  }
  function activeStage() { return document.querySelector('[data-step="' + state.currentStep + '"]'); }
  function detectMobileDevice() {
    return window.matchMedia('(pointer: coarse)').matches || /Android|iPhone|iPad|iPod|IEMobile|Mobile/i.test(navigator.userAgent || '');
  }
  function applyDeviceRules() {
    state.isMobileDevice = detectMobileDevice();
    if (modalEl) modalEl.classList.toggle('is-mobile-scan-only', state.isMobileDevice);
    document.querySelectorAll('[data-desktop-upload]').forEach(function (button) {
      button.classList.toggle('d-none', state.isMobileDevice);
      button.disabled = state.isMobileDevice;
    });
    if (state.isMobileDevice) {
      setStatus('idFrontInput', 'Mobile mode: use live camera scan for front ID.');
      setStatus('idBackInput', 'Mobile mode: use live camera scan for back ID.');
    }
  }
  function hasAnyScan() { return !!(state.front || state.back || state.selfie || state.frontFile || state.backFile || state.selfieFile); }
  function setIdTypeLocked(locked) {
    if (!idType) return;
    state.lockedIdType = locked ? idType.value : null;
    idType.disabled = !!locked;
    idType.classList.toggle('is-id-locked', !!locked);
    var notice = document.getElementById('idTypeLockNotice');
    if (notice) notice.textContent = locked ? 'ID type locked after accepted front scan. Use Reset Scan to choose a different ID type.' : 'Select your ID type before scanning. It locks after the front ID is accepted.';
  }
  function setLiveness(message, progress) {
    var prompt = document.getElementById('livenessPrompt');
    var progressEl = document.getElementById('livenessProgress');
    if (prompt && message) prompt.textContent = message;
    if (progressEl && progress) progressEl.textContent = progress;
  }
  function resetLiveness() {
    var challenges = [
      { key:'move_closer', prompt:'Slowly move your face closer to the camera.', pass:function (base, box) { return base && box && box.width >= base.width * 1.06; } }
    ];
    state.liveness = { challenge: challenges[Math.floor(Math.random() * challenges.length)], baseline:null, passed:false, unsupported:false };
    document.getElementById('livenessChallengeInput').value = state.liveness.challenge.key;
    document.getElementById('livenessResultInput').value = 'pending';
    document.getElementById('livenessMethodInput').value = 'mediapipe_face_landmarker';
    setLiveness(state.liveness.challenge.prompt, 'Center your face first, then follow the challenge.');
  }
  async function loadFaceLandmarker() {
    if (state.faceLandmarker) return state.faceLandmarker;
    if (state.faceLandmarkerFailed) return null;
    if (!state.faceLandmarkerPromise) {
      state.faceLandmarkerStartedAt = Date.now();
      state.faceLandmarkerPromise = import('https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision/vision_bundle.mjs')
        .then(async function (vision) {
          var fileset = await vision.FilesetResolver.forVisionTasks('https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision/wasm');
          state.faceLandmarker = await vision.FaceLandmarker.createFromOptions(fileset, {
            baseOptions: { modelAssetPath:'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/latest/face_landmarker.task' },
            runningMode:'VIDEO',
            numFaces:1
          });
          return state.faceLandmarker;
        })
        .catch(function (error) { state.faceLandmarkerFailed = true; state.faceLandmarkerError = error && error.message ? error.message : 'load_failed'; return null; });
    }
    return await state.faceLandmarkerPromise;
  }
  function boxFromLandmarks(landmarks, video) {
    if (!landmarks || !landmarks.length || !video || !video.videoWidth) return null;
    var minX = 1, minY = 1, maxX = 0, maxY = 0;
    landmarks.forEach(function (point) {
      minX = Math.min(minX, point.x); minY = Math.min(minY, point.y);
      maxX = Math.max(maxX, point.x); maxY = Math.max(maxY, point.y);
    });
    return { x:minX * video.videoWidth, y:minY * video.videoHeight, width:(maxX - minX) * video.videoWidth, height:(maxY - minY) * video.videoHeight };
  }
  async function detectLiveFaceBox(video) {
    if (state.faceLandmarker) {
      var result = state.faceLandmarker.detectForVideo(video, performance.now());
      return result && result.faceLandmarks && result.faceLandmarks.length ? boxFromLandmarks(result.faceLandmarks[0], video) : null;
    }
    if (!state.faceLandmarkerPromise && !state.faceLandmarkerFailed) loadFaceLandmarker();
    if ('FaceDetector' in window) {
      var detector = new FaceDetector({ fastMode:true, maxDetectedFaces:1 });
      var faces = await detector.detect(video);
      return faces.length ? faces[0].boundingBox : null;
    }
    return null;
  }
  async function checkLiveness(stage) {
    if (state.currentStep !== 'selfie' || state.liveness.passed) return true;
    var video = stage ? stage.querySelector('video') : null;
    if (!video || !video.videoWidth) return false;
    try {
      setLiveness(state.liveness.challenge.prompt, state.faceLandmarker ? 'Face detector ready. Follow the movement.' : 'Loading live face detector...');
      var box = await detectLiveFaceBox(video);
      if (!box) {
        var waitingMs = Date.now() - (state.faceLandmarkerStartedAt || state.stepStartedAt || Date.now());
        if ((state.faceLandmarkerFailed && !('FaceDetector' in window)) || waitingMs > 1500) {
          state.liveness.unsupported = true;
          state.liveness.passed = true;
          document.getElementById('livenessResultInput').value = state.faceLandmarkerFailed ? 'detector_load_failed' : 'detector_loading_timeout';
          document.getElementById('livenessMethodInput').value = 'manual_admin_review';
          setLiveness('Live detector is slow on this device.', 'Continuing with live camera selfie and server face comparison.');
          return true;
        }
        setLiveness(state.liveness.challenge.prompt, 'Loading face detector. Keep your face centered.');
        return false;
      }
      if (!state.liveness.baseline) {
        state.liveness.baseline = { x:box.x, y:box.y, width:box.width, height:box.height };
        setLiveness(state.liveness.challenge.prompt, 'Baseline captured. Now complete the movement.');
        return false;
      }
      var challengeWaitMs = Date.now() - (state.stepStartedAt || Date.now());
      if (state.liveness.challenge.pass(state.liveness.baseline, box) || challengeWaitMs > 1200) {
        state.liveness.passed = true;
        document.getElementById('livenessResultInput').value = state.liveness.challenge.pass(state.liveness.baseline, box) ? 'passed' : 'fast_live_camera_review';
        document.getElementById('livenessMethodInput').value = state.faceLandmarker ? 'mediapipe_face_landmarker' : 'browser_face_detector';
        setLiveness('Live camera check accepted.', 'Capturing your selfie now.');
        return true;
      }
      setLiveness(state.liveness.challenge.prompt, 'Small movement detected. Hold for a moment.');
      return false;
    } catch (e) {
      state.liveness.unsupported = true;
      document.getElementById('livenessResultInput').value = 'detector_error';
      document.getElementById('livenessMethodInput').value = 'manual_admin_review';
      setLiveness('Live movement challenge needs review.', 'Keep your face clear. The server will still compare your selfie with your ID.');
      return true;
    }
  }
  function setLive(stage, message, score, force) {
    if (!stage) return;
    var hint = stage.querySelector('[data-live-hint]');
    var meter = stage.querySelector('.verify-live-meter');
    var fill = meter ? meter.querySelector('span') : null;
    var clamped = Math.max(0, Math.min(100, score || 0));
    var key = stage.dataset.step || 'active';
    var now = Date.now();
    var last = state.lastLive[key] || { message:'', at:0, score:0 };
    var shouldUpdateText = force || (message !== last.message && (now - last.at > 650 || clamped >= 86 || clamped < last.score - 18));
    if (hint && shouldUpdateText) {
      hint.textContent = message;
      state.lastLive[key] = { message:message, at:now, score:clamped };
    }
    if (fill) fill.style.width = clamped + '%';
    if (meter) {
      meter.classList.toggle('is-good', clamped >= 86);
      meter.classList.toggle('is-warn', clamped >= 60 && clamped < 86);
    }
  }
  function showStep(step) {
    state.currentStep = step;
    state.stepStartedAt = Date.now();
    if (step === 'selfie') state.lastCompareStartedAt = Date.now();
    document.querySelectorAll('[data-step]').forEach(function (el) { el.classList.toggle('d-none', el.dataset.step !== step); });
    document.querySelectorAll('[data-step-label]').forEach(function (el) {
      el.classList.toggle('active', el.dataset.stepLabel === step);
      el.classList.toggle('done', !!state[el.dataset.stepLabel]);
    });
    if (modalEl) modalEl.classList.toggle('is-selfie-step', step === 'selfie');
    stopCamera();
    if (modalEl && modalEl.classList.contains('show')) {
      setTimeout(function () { startCameraForStep(step); }, 220);
    }
  }
  function updateSubmit() {
    if (submitButton) submitButton.disabled = !(state.front && state.back && state.selfie && state.lockedIdType && state.lockedIdType === idType.value);
    if (launchButton) launchButton.disabled = !idType.value;
    if (resetButton) resetButton.disabled = !hasAnyScan();
    if (resetFooterButton) resetFooterButton.disabled = !hasAnyScan();
  }
  function stopCamera() {
    state.activeLoop += 1;
    state.stableFrames = 0;
    state.processing = false;
    if (state.stream) state.stream.getTracks().forEach(function (track) { track.stop(); });
    state.stream = null;
    document.querySelectorAll('video').forEach(function (video) { video.srcObject = null; });
    if (modalEl && state.currentStep !== 'selfie') modalEl.classList.remove('is-selfie-step');
  }
  function inputStep(input) {
    if (input.id === 'idFrontInput') return 'front';
    if (input.id === 'idBackInput') return 'back';
    return 'selfie';
  }
  function inputForStep(step) {
    return document.getElementById(step === 'front' ? 'idFrontInput' : (step === 'back' ? 'idBackInput' : 'selfieInput'));
  }
  function nextStep(step) { return step === 'front' ? 'back' : (step === 'back' ? 'selfie' : 'selfie'); }
  function setInputFile(input, blob, filename) {
    var file = new File([blob], filename, { type: blob.type || 'image/jpeg' });
    state.cameraCaptureInput = input ? input.id : null;
    var transfer = new DataTransfer();
    transfer.items.add(file);
    input.files = transfer.files;
    input.dispatchEvent(new Event('change', { bubbles:true }));
    return file;
  }
  function imageMetrics(file) {
    return new Promise(function (resolve, reject) {
      var img = new Image();
      var objectUrl = URL.createObjectURL(file);
      img.onload = function () {
        var canvas = document.createElement('canvas');
        var w = Math.min(360, img.width);
        var h = Math.max(1, Math.round(img.height * (w / img.width)));
        canvas.width = w; canvas.height = h;
        var ctx = canvas.getContext('2d', { willReadFrequently:true });
        ctx.drawImage(img, 0, 0, w, h);
        var data = ctx.getImageData(0, 0, w, h).data;
        var total = 0;
        var totalSq = 0;
        var sharp = 0;
        var samples = 0;
        for (var y = 1; y < h - 1; y += 3) {
          for (var x = 1; x < w - 1; x += 3) {
            var idx = (y * w + x) * 4;
            var gray = (data[idx] + data[idx + 1] + data[idx + 2]) / 3;
            total += gray; totalSq += gray * gray; samples++;
            var right = ((y * w + (x + 1)) * 4);
            var down = (((y + 1) * w + x) * 4);
            var gRight = (data[right] + data[right + 1] + data[right + 2]) / 3;
            var gDown = (data[down] + data[down + 1] + data[down + 2]) / 3;
            sharp += Math.abs(gray - gRight) + Math.abs(gray - gDown);
          }
        }
        var brightness = total / Math.max(1, samples);
        var variance = (totalSq / Math.max(1, samples)) - (brightness * brightness);
        URL.revokeObjectURL(objectUrl);
        resolve({ width: img.width, height: img.height, brightness: brightness, contrast: Math.sqrt(Math.max(0, variance)), sharpness: sharp / Math.max(1, samples), hash: averageHash(img), image: img });
      };
      img.onerror = function () { URL.revokeObjectURL(objectUrl); reject(); };
      img.src = objectUrl;
    });
  }
  function averageHash(img) {
    var size = 8;
    var canvas = document.createElement('canvas');
    canvas.width = size; canvas.height = size;
    var ctx = canvas.getContext('2d', { willReadFrequently:true });
    ctx.drawImage(img, 0, 0, size, size);
    var data = ctx.getImageData(0, 0, size, size).data;
    var grays = [];
    var total = 0;
    for (var i = 0; i < data.length; i += 4) {
      var gray = (data[i] + data[i + 1] + data[i + 2]) / 3;
      grays.push(gray); total += gray;
    }
    var avg = total / Math.max(1, grays.length);
    return grays.map(function (gray) { return gray >= avg ? '1' : '0'; }).join('');
  }
  function hashDistance(a, b) {
    if (!a || !b || a.length !== b.length) return 64;
    var distance = 0;
    for (var i = 0; i < a.length; i++) if (a[i] !== b[i]) distance++;
    return distance;
  }
  function hashInputForStep(step) {
    return document.getElementById(step === 'front' ? 'idFrontHashInput' : 'idBackHashInput');
  }
  function setIdHash(step, hash) {
    var input = hashInputForStep(step);
    if (input) input.value = hash || '';
  }
  function scoreMetrics(metrics, kind) {
    if (kind !== 'selfie') {
      if (metrics.width < 260 || metrics.height < 160) return { ok:false, score:62, message:'Move the ID a little closer inside the guide.' };
      if (metrics.brightness < 6) return { ok:false, score:68, message:'Add a little light so the ID can be read.' };
      return { ok:true, score:96, message:'ID image is readable.' };
    }
    if (metrics.width < 220 || metrics.height < 220) return { ok:false, score:58, message:'Move a bit closer and keep your face inside the oval.' };
    if (metrics.brightness < 8) return { ok:false, score:64, message:'Too dark. Add a little light.' };
    if (metrics.contrast < 2 || metrics.sharpness < 0.8) return { ok:false, score:78, message:'Hold steady for a moment.' };
    return { ok:true, score:96, message:'Face photo looks clear.' };
  }
  async function validateQuality(file, kind) {
    var metrics = await imageMetrics(file);
    var quality = scoreMetrics(metrics, kind);
    if (!quality.ok) return quality.message;
    if (kind === 'selfie') {
      try {
        var landmarker = await loadFaceLandmarker();
        if (landmarker) {
          var result = landmarker.detect(metrics.image);
          if (!result.faceLandmarks.length) return 'No face detected. Center your face and retake the selfie.';
          if (result.faceLandmarks.length > 1) return 'More than one face detected. Take the selfie alone.';
        } else if ('FaceDetector' in window) {
          var detector = new FaceDetector({ fastMode:true, maxDetectedFaces:2 });
          var faces = await detector.detect(metrics.image);
          if (!faces.length) return 'No face detected. Center your face and retake the selfie.';
          if (faces.length > 1) return 'More than one face detected. Take the selfie alone.';
        }
      } catch (e) {}
    }
    return null;
  }
  function resetFaceCompare() {
    state.faceCompare = { lastAt:0, inFlight:false, status:null, score:null, message:null };
  }
  function faceCompareMessage(result) {
    if (!result || !result.status) return 'Comparing your selfie with the ID...';
    var scoreText = typeof result.score === 'number' ? ' (' + Math.round(result.score * 100) + '%)' : '';
    if (result.status === 'match') return 'Face matched with ID' + scoreText + '. Hold steady.';
    if (result.status === 'mismatch') return result.message || 'Face does not match the ID. Keep scanning with the correct person.';
    return result.message || 'Face must match the ID before submit. Keep your face centered and try again.';
  }
  async function compareFaceFrame(selfieFile, force) {
    var frontInput = inputForStep('front');
    var frontFile = state.frontFile || (frontInput && frontInput.files && frontInput.files[0] ? frontInput.files[0] : null);
    if (!faceUrl || !frontFile || !selfieFile) {
      return { status:'needs_review', score:null, message:'Front ID is not ready for live face comparison yet.' };
    }
    var now = Date.now();
    if (!force && state.faceCompare.inFlight) return state.faceCompare;
    if (!force && state.faceCompare.status && now - state.faceCompare.lastAt < 1800) return state.faceCompare;

    state.faceCompare.inFlight = true;
    var body = new FormData();
    body.append('_token', token);
    body.append('id_front', frontFile);
    body.append('selfie', selfieFile, 'live-selfie.jpg');
    try {
      var response = await fetch(faceUrl, { method:'POST', body:body, headers:{ 'Accept':'application/json' } });
      var data = response.ok ? await response.json() : { status:'needs_review', message:'Face check is not ready. Keep your face centered and try again.' };
      state.faceCompare = {
        lastAt:Date.now(),
        inFlight:false,
        status:data.status || 'needs_review',
        score:typeof data.score === 'number' ? data.score : null,
        message:data.message || null,
        can_continue:data.can_continue !== false
      };
      return state.faceCompare;
    } catch (e) {
      state.faceCompare = { lastAt:Date.now(), inFlight:false, status:'needs_review', score:null, message:'Face check is not ready. Keep your face centered and try again.', can_continue:true };
      return state.faceCompare;
    }
  }
  async function captureFromStage(input, stage, auto) {
    var video = stage ? stage.querySelector('video') : null;
    var canvas = stage ? stage.querySelector('canvas') : null;
    if (!input || !video || !canvas || !video.videoWidth) {
      if (input) setStatus(input.id, 'Open the camera first or upload a picture.', 'text-danger');
      return;
    }
    if (state.processing) return;
    if (input.id === 'selfieInput' && !state.liveness.passed) {
      var livenessOk = await checkLiveness(stage);
      if (!livenessOk) {
        setStatus(input.id, 'Complete the live face challenge before capturing.', 'text-warning');
        setLive(stage, 'Complete the liveness challenge first.', 65);
        return;
      }
    }
    state.processing = true;
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    canvas.toBlob(async function (blob) {
      if (!blob) { state.processing = false; return; }
      if (input.id === 'selfieInput') {
        var faceResult = await compareFaceFrame(blob, true);
        if (faceResult.status !== 'match') {
          state.processing = false;
          state.stableFrames = 0;
          setStatus(input.id, faceResult.message || 'Face must match the ID before submit. Keep scanning with the correct person.', 'text-danger');
          setLive(stage, faceCompareMessage(faceResult), faceResult.status === 'mismatch' ? 38 : 60, true);
          return;
        }
      }
      setLive(stage, auto ? 'Clear image captured automatically.' : 'Captured. Checking image...', 96, true);
      setInputFile(input, blob, input.id + '.jpg');
    }, 'image/jpeg', 0.94);
  }
  async function scanFront(file) {
    var selected = idType.value;
    if (!selected) return { ok:false, message:'Select an ID type first.' };
    var body = new FormData();
    body.append('_token', token);
    body.append('id_type', selected);
    body.append('id_front', file);
    var response = await fetch(scanUrl, { method:'POST', body:body, headers:{ 'Accept':'application/json' } });
    if (!response.ok) {
      var errorData = null;
      try { errorData = await response.json(); } catch (e) {}
      var firstError = errorData && errorData.errors ? Object.values(errorData.errors).flat()[0] : null;
      return { ok:false, message:firstError || (errorData && errorData.message) || ('ID scan could not start. Server returned HTTP ' + response.status + '. Please try again.') };
    }
    return await response.json();
  }
  async function handleFile(input) {
    var file = input.files && input.files[0] ? input.files[0] : null;
    var step = inputStep(input);
    var fromCamera = state.cameraCaptureInput === input.id;
    state.cameraCaptureInput = null;
    if (step === 'selfie' && file && !fromCamera) {
      input.value = '';
      state.selfie = false;
      state.selfieFile = null;
      document.getElementById('selfieCaptureSourceInput').value = 'blocked_upload';
      setStatus(input.id, 'Face verification requires the live camera. Selfie upload is disabled.', 'text-danger');
      updateSubmit();
      return;
    }
    if (state.isMobileDevice && step !== 'selfie' && file && !fromCamera) {
      input.value = '';
      state[step] = false;
      setStatus(input.id, 'Mobile verification uses live camera scan only. Use Start Auto Scan.', 'text-danger');
      updateSubmit();
      return;
    }
    state[step] = false;
    if (step === 'front') { state.frontFile = null; resetFaceCompare(); }
    if (step === 'back') state.backFile = null;
    if (step === 'selfie') state.selfieFile = null;
    updateSubmit();
    if (!file) return;

    setStatus(input.id, 'Checking image...', 'text-warning');
    try {
      var metrics = await imageMetrics(file);
      var quality = scoreMetrics(metrics, step);
      var qualityError = quality.ok ? null : quality.message;
      if (qualityError) {
        input.value = '';
        state.processing = false;
        setStatus(input.id, qualityError, 'text-danger');
        setLive(document.querySelector('[data-step="' + step + '"]'), qualityError, 35);
        return;
      }
      if (step === 'front') {
        setStatus(input.id, 'Scanning front ID and checking selected ID type...', 'text-warning');
        var result = await scanFront(file);
        if (!result.ok) {
          input.value = '';
          setIdHash('front', '');
          state.processing = false;
          setStatus(input.id, result.message || 'Selected ID type does not match the scanned ID.', 'text-danger');
          setLive(document.querySelector('[data-step="front"]'), result.message || 'Wrong ID type detected.', 40, true);
          return;
        }
        state.front = true;
        state.frontFile = file;
        setIdTypeLocked(true);
        setIdHash('front', metrics.hash);
        setStatus(input.id, result.message || 'Front ID matched.', 'text-success');
        setLive(document.querySelector('[data-step="front"]'), 'Front ID verified. Flip to the back side.', 100, true);
        showStep('back');
      } else if (step === 'back') {
        var frontHash = document.getElementById('idFrontHashInput')?.value || '';
        if (frontHash && metrics.hash && hashDistance(frontHash, metrics.hash) <= 16) {
          input.value = '';
          setIdHash('back', '');
          state.processing = false;
          setStatus(input.id, 'This looks like the front side again. Flip the ID and scan the back side.', 'text-danger');
          setLive(document.querySelector('[data-step="back"]'), 'Please flip the ID. The back side must be different from the front.', 38, true);
          return;
        }
        state.back = true;
        state.backFile = file;
        setIdHash('back', metrics.hash);
        setStatus(input.id, 'Back ID captured. Continue to face verification.', 'text-success');
        setLive(document.querySelector('[data-step="back"]'), 'Back ID accepted.', 100, true);
        showStep('selfie');
      } else {
        var uploadedFaceResult = await compareFaceFrame(file, true);
        if (uploadedFaceResult.status !== 'match') {
          input.value = '';
          state.processing = false;
          setStatus(input.id, uploadedFaceResult.message || 'Face must match the ID before submit. Please retake with the correct person.', 'text-danger');
          setLive(document.querySelector('[data-step="selfie"]'), faceCompareMessage(uploadedFaceResult), uploadedFaceResult.status === 'mismatch' ? 38 : 60, true);
          return;
        }
        if (document.getElementById('livenessResultInput').value === 'not_started') {
          document.getElementById('livenessResultInput').value = 'uploaded_manual_review';
          document.getElementById('livenessMethodInput').value = 'upload_manual_review';
          document.getElementById('livenessChallengeInput').value = 'upload_fallback';
        }
        state.selfie = true;
        state.selfieFile = file;
        document.getElementById('selfieCaptureSourceInput').value = fromCamera ? 'live_camera' : 'blocked_upload';
        setStatus(input.id, 'Face matched the ID. You may submit for review.', 'text-success');
        setLive(document.querySelector('[data-step="selfie"]'), faceCompareMessage(uploadedFaceResult), 100, true);
        stopCamera();
      }
      state.processing = false;
      state.stableFrames = 0;
      updateSubmit();
    } catch (e) {
      input.value = '';
      state.processing = false;
      setStatus(input.id, 'Could not check this image. Retake or upload a clearer file.', 'text-danger');
    }
  }
  async function monitorCamera(input, stage, loopId) {
    var video = stage.querySelector('video');
    var canvas = stage.querySelector('canvas');
    var step = inputStep(input);
    if (!video || !canvas || loopId !== state.activeLoop) return;
    if (!video.videoWidth || state.processing) {
      requestAnimationFrame(function () { monitorCamera(input, stage, loopId); });
      return;
    }
    var scale = Math.min(1, 360 / video.videoWidth);
    canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
    canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
    var ctx = canvas.getContext('2d', { willReadFrequently:true });
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    canvas.toBlob(async function (blob) {
      if (!blob || loopId !== state.activeLoop) return;
      try {
        var metrics = await imageMetrics(blob);
        metrics.width = video.videoWidth;
        metrics.height = video.videoHeight;
        var quality = scoreMetrics(metrics, step);
        var livenessOk = step === 'selfie' && quality.ok ? await checkLiveness(stage) : true;
        var faceOk = true;
        var liveMessage = quality.ok ? (livenessOk ? 'Hold steady. Capturing automatically...' : 'Complete the liveness challenge.') : quality.message;
        var liveScore = livenessOk ? quality.score : 65;
        if (step === 'selfie' && quality.ok && livenessOk) {
          var faceResult = await compareFaceFrame(blob, false);
          faceOk = faceResult.status === 'match';
          liveMessage = faceCompareMessage(faceResult);
          liveScore = faceResult.status === 'match' ? 96 : (faceResult.status === 'mismatch' ? 38 : 60);
        }
        setLive(stage, liveMessage, liveScore);
        state.stableFrames = quality.ok && livenessOk && faceOk ? state.stableFrames + 1 : 0;
        var requiredFrames = 1;
        if (state.stableFrames >= requiredFrames && !state.processing) {
          captureFromStage(input, stage, true);
          return;
        }
      } catch (e) {}
      setTimeout(function () { monitorCamera(input, stage, loopId); }, 140);
    }, 'image/jpeg', 0.82);
  }

  document.querySelectorAll('[data-upload-trigger]').forEach(function (button) {
    button.addEventListener('click', function () {
      var input = document.getElementById(button.dataset.uploadTrigger);
      if (input) input.click();
    });
  });
  document.querySelectorAll('.verify-file-input').forEach(function (input) {
    input.addEventListener('change', function () { handleFile(input); });
  });
  async function startCameraForStep(step) {
    var targetStep = step || state.currentStep;
    var input = inputForStep(targetStep);
    var stage = document.querySelector('[data-step="' + targetStep + '"]');
    var button = stage ? stage.querySelector('[data-camera-start]') : null;
    var video = stage ? stage.querySelector('video') : null;
    if (!input || !stage || !video || state.autoStarting) return;
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      setStatus(input.id, 'Camera is not supported here. Upload a clear picture instead.', 'text-danger');
      setLive(stage, 'Camera is not supported here. Upload a clear picture instead.', 35, true);
      return;
    }
    if (!idType.value) {
      setStatus(input.id, 'Select an ID type before scanning.', 'text-danger');
      return;
    }
    state.autoStarting = true;
    stopCamera();
    state.activeInput = input;
    state.activeLoop += 1;
    var loopId = state.activeLoop;
    try {
      if (input.id === 'selfieInput') {
        resetLiveness();
        loadFaceLandmarker();
      }
      setStatus(input.id, 'Opening camera...', 'text-warning');
      setLive(stage, 'Opening camera...', 30, true);
      state.stream = await navigator.mediaDevices.getUserMedia({ video:{ facingMode: button?.dataset.facing || (input.id === 'selfieInput' ? 'user' : 'environment'), width:{ ideal:1280 }, height:{ ideal:720 } }, audio:false });
      video.srcObject = state.stream;
      await video.play();
      setStatus(input.id, 'Camera ready. Align inside the guide; capture is automatic.', 'text-warning');
      setLive(stage, input.id === 'selfieInput' ? 'Center your face and follow the challenge.' : 'Align the landscape card inside the guide.', 58, true);
      monitorCamera(input, stage, loopId);
    } catch (e) {
      setStatus(input.id, 'Camera unavailable. Upload a clear picture instead.', 'text-danger');
      setLive(stage, 'Camera unavailable. Upload a clear picture instead.', 35, true);
    } finally {
      state.autoStarting = false;
    }
  }
  document.querySelectorAll('[data-camera-start]').forEach(function (button) {
    button.addEventListener('click', function () {
      var input = document.getElementById(button.dataset.cameraStart);
      startCameraForStep(input ? inputStep(input) : state.currentStep);
    });
  });
  document.querySelectorAll('[data-camera-capture]').forEach(function (button) {
    button.addEventListener('click', function () {
      var input = document.getElementById(button.dataset.cameraCapture);
      var stage = button.closest('[data-step]');
      captureFromStage(input, stage, false);
    });
  });
  if (idType) {
    idType.addEventListener('change', function () {
      if (state.lockedIdType || hasAnyScan()) {
        idType.value = state.lockedIdType || idType.value;
        setStatus('idFrontInput', 'Use Reset Scan before changing the ID type.', 'text-warning');
        return;
      }
      state.front = false; state.back = false; state.selfie = false; state.frontFile = null; state.backFile = null; state.selfieFile = null; resetLiveness(); resetFaceCompare();
      ['idFrontInput','idBackInput','selfieInput'].forEach(function (id) {
        var input = document.getElementById(id);
        if (input) input.value = '';
      });
      setStatus('idFrontInput', 'Waiting for front ID scan.');
      setStatus('idBackInput', 'Waiting for back ID scan.');
      setIdHash('front', '');
      setIdHash('back', '');
      setStatus('selfieInput', 'Waiting for face verification.');
      document.getElementById('idUploadSummary').innerHTML = '';
      resetLiveness(); resetFaceCompare(); showStep('front'); updateSubmit(); stopCamera();
    });
  }
  function resetScanFlow(confirmFirst) {
    if (confirmFirst && hasAnyScan() && !window.confirm('Reset all scanned ID and face verification data?')) return;
    stopCamera();
    state.front = false; state.back = false; state.selfie = false;
    state.frontFile = null; state.backFile = null; state.selfieFile = null;
    state.stableFrames = 0; state.processing = false; state.lastLive = {};
    setIdTypeLocked(false);
    ['idFrontInput','idBackInput','selfieInput'].forEach(function (id) {
      var input = document.getElementById(id);
      if (input) input.value = '';
    });
    setIdHash('front', '');
    setIdHash('back', '');
    setStatus('idFrontInput', 'Waiting for front ID scan.');
    setStatus('idBackInput', 'Waiting for back ID scan.');
    setStatus('selfieInput', 'Waiting for face verification.');
    document.getElementById('selfieCaptureSourceInput').value = 'not_started';
    document.getElementById('idUploadSummary').innerHTML = '';
    resetLiveness(); resetFaceCompare(); showStep('front'); applyDeviceRules(); updateSubmit();
  }
  if (resetButton) resetButton.addEventListener('click', function () { resetScanFlow(true); });
  if (resetFooterButton) resetFooterButton.addEventListener('click', function () { resetScanFlow(true); });
  if (modalEl) {
    modalEl.addEventListener('hidden.bs.modal', function () {
      stopCamera();
      modalEl.classList.remove('is-selfie-step');
    });
    modalEl.addEventListener('shown.bs.modal', function () {
      var input = inputForStep(state.currentStep);
      var stage = activeStage();
      if (input && input.files.length) {
        setLive(stage, 'Step already has an accepted image.', 100, true);
      } else {
        setLive(stage, 'Opening camera automatically...', 45, true);
        setTimeout(function () { startCameraForStep(state.currentStep); }, 260);
      }
    });
  }
  form.addEventListener('submit', function (event) {
    if (idType && idType.disabled) idType.disabled = false;
    if (document.getElementById('selfieCaptureSourceInput').value !== 'live_camera') {
      event.preventDefault();
      document.getElementById('idUploadSummary').innerHTML = '<div class="alert alert-danger mb-0">Face verification must be completed with the live camera before submit.</div>';
      if (idType && state.lockedIdType) idType.disabled = true;
      return;
    }
    if (!(state.front && state.back && state.selfie && state.lockedIdType && state.lockedIdType === idType.value)) {
      event.preventDefault();
      document.getElementById('idUploadSummary').innerHTML = '<div class="alert alert-danger mb-0">Complete front ID scan, back ID scan, and face verification first.</div>';
      if (idType && state.lockedIdType) idType.disabled = true;
    }
  });
  applyDeviceRules(); resetLiveness(); showStep('front'); updateSubmit();
});
</script>
@endsection


