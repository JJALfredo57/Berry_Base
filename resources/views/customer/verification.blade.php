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
.verify-wizard{display:flex;gap:.5rem;flex-wrap:wrap}
.verify-wizard-step{display:flex;align-items:center;gap:.45rem;border:1px solid #e5e7eb;border-radius:8px;padding:.55rem .75rem;font-size:.82rem;color:#64748b;background:#fff}
.verify-wizard-step span{width:22px;height:22px;border-radius:50%;display:grid;place-items:center;background:#eef2f7;color:#64748b;font-weight:700;font-size:.72rem}
.verify-wizard-step.active{border-color:var(--primary);color:var(--primary);background:rgba(var(--primary-rgb,233,30,99),.06)}
.verify-wizard-step.active span,.verify-wizard-step.done span{background:var(--primary);color:#fff}
.verify-scan-stage{border:1px solid #e5e7eb;border-radius:8px;padding:1rem;background:#fff}
.verify-camera-frame{position:relative;aspect-ratio:4/3;background:#0f172a;border-radius:8px;overflow:hidden;display:grid;place-items:center;max-height:360px}
.verify-camera-frame video{width:100%;height:100%;object-fit:cover;background:#0f172a}
.verify-frame-guide{position:absolute;inset:14%;border:2px solid rgba(255,255,255,.9);border-radius:8px;box-shadow:0 0 0 999px rgba(15,23,42,.26)}
.verify-face-guide{position:absolute;width:42%;aspect-ratio:3/4;border:2px solid rgba(255,255,255,.9);border-radius:50%;box-shadow:0 0 0 999px rgba(15,23,42,.26)}
.verify-upload-status.text-danger{color:#dc2626!important}.verify-upload-status.text-success{color:#15803d!important}
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
          <form action="{{ route('customer.verification.store') }}" method="POST" enctype="multipart/form-data" id="verificationWizardForm" data-scan-url="{{ route('customer.verification.scan_front') }}" data-prevent-double-submit>
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold small">ID Type</label>
              <select class="form-select" name="id_type" id="verificationIdType" required>
                <option value="">Select ID type</option>
                @foreach($idTypes as $idType)
                  <option value="{{ $idType }}" {{ old('id_type') === $idType ? 'selected' : '' }}>{{ $idType }}</option>
                @endforeach
              </select>
            </div>

            <div class="verify-wizard mb-3">
              <div class="verify-wizard-step active" data-step-label="front"><span>1</span> Front ID</div>
              <div class="verify-wizard-step" data-step-label="back"><span>2</span> Back ID</div>
              <div class="verify-wizard-step" data-step-label="selfie"><span>3</span> Face</div>
            </div>

            <input type="file" class="verify-file-input" id="idFrontInput" name="id_front" accept="image/*" capture="environment" required>
            <input type="file" class="verify-file-input" id="idBackInput" name="id_back" accept="image/*" capture="environment" required>
            <input type="file" class="verify-file-input" id="selfieInput" name="selfie" accept="image/*" capture="user" required>

            <div class="verify-scan-stage" data-step="front">
              <div class="d-flex align-items-start gap-2 mb-2">
                <div class="verify-upload-icon"><i class="bi bi-credit-card-2-front"></i></div>
                <div><div class="fw-semibold">Scan front of ID</div><div class="verify-upload-hint">Place the front side inside the frame. The selected ID type must match the OCR result.</div></div>
              </div>
              <div class="verify-camera-frame"><video playsinline muted></video><canvas hidden></canvas><div class="verify-frame-guide"></div></div>
              <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="button" class="btn btn-outline-primary btn-sm" data-camera-start="idFrontInput" data-facing="environment"><i class="bi bi-camera-video me-1"></i>Open Camera</button>
                <button type="button" class="btn btn-primary btn-sm" data-camera-capture="idFrontInput"><i class="bi bi-camera me-1"></i>Capture Front</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-upload-trigger="idFrontInput"><i class="bi bi-upload me-1"></i>Upload Picture</button>
              </div>
              <div class="verify-upload-status mt-2" data-upload-status-for="idFrontInput">Waiting for front ID scan.</div>
            </div>

            <div class="verify-scan-stage d-none" data-step="back">
              <div class="d-flex align-items-start gap-2 mb-2">
                <div class="verify-upload-icon"><i class="bi bi-arrow-repeat"></i></div>
                <div><div class="fw-semibold">Flip ID and scan back</div><div class="verify-upload-hint">Capture the back side after the front ID type is confirmed.</div></div>
              </div>
              <div class="verify-camera-frame"><video playsinline muted></video><canvas hidden></canvas><div class="verify-frame-guide"></div></div>
              <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="button" class="btn btn-outline-primary btn-sm" data-camera-start="idBackInput" data-facing="environment"><i class="bi bi-camera-video me-1"></i>Open Camera</button>
                <button type="button" class="btn btn-primary btn-sm" data-camera-capture="idBackInput"><i class="bi bi-camera me-1"></i>Capture Back</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-upload-trigger="idBackInput"><i class="bi bi-upload me-1"></i>Upload Picture</button>
              </div>
              <div class="verify-upload-status mt-2" data-upload-status-for="idBackInput">Waiting for back ID scan.</div>
            </div>

            <div class="verify-scan-stage d-none" data-step="selfie">
              <div class="d-flex align-items-start gap-2 mb-2">
                <div class="verify-upload-icon"><i class="bi bi-person-bounding-box"></i></div>
                <div><div class="fw-semibold">Face verification</div><div class="verify-upload-hint">Look at the camera clearly. Submit becomes available after this step.</div></div>
              </div>
              <div class="verify-camera-frame is-selfie"><video playsinline muted></video><canvas hidden></canvas><div class="verify-face-guide"></div></div>
              <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="button" class="btn btn-outline-primary btn-sm" data-camera-start="selfieInput" data-facing="user"><i class="bi bi-camera-video me-1"></i>Open Camera</button>
                <button type="button" class="btn btn-primary btn-sm" data-camera-capture="selfieInput"><i class="bi bi-person-bounding-box me-1"></i>Capture Selfie</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-upload-trigger="selfieInput"><i class="bi bi-upload me-1"></i>Upload Selfie</button>
              </div>
              <div class="verify-upload-status mt-2" data-upload-status-for="selfieInput">Waiting for face verification.</div>
            </div>

            <div id="idUploadSummary" class="mt-3"></div>
            <div class="small text-muted mt-3"><i class="bi bi-lock me-1"></i>Your ID is checked before submit and then reviewed by authorized admins.</div>
            <button class="btn btn-primary mt-3" id="verificationSubmitButton" disabled><i class="bi bi-shield-check me-1"></i>Submit for Review</button>
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
  var token = form.querySelector('input[name="_token"]')?.value || '';
  var idType = document.getElementById('verificationIdType');
  var submitButton = document.getElementById('verificationSubmitButton');
  var state = { front:false, back:false, selfie:false, stream:null, activeInput:null };

  function statusFor(inputId) { return document.querySelector('[data-upload-status-for="' + inputId + '"]'); }
  function setStatus(inputId, message, type) {
    var el = statusFor(inputId);
    if (!el) return;
    el.textContent = message;
    el.classList.remove('text-success','text-danger','text-warning');
    if (type) el.classList.add(type);
  }
  function showStep(step) {
    document.querySelectorAll('[data-step]').forEach(function (el) { el.classList.toggle('d-none', el.dataset.step !== step); });
    document.querySelectorAll('[data-step-label]').forEach(function (el) {
      el.classList.toggle('active', el.dataset.stepLabel === step);
      el.classList.toggle('done', !!state[el.dataset.stepLabel]);
    });
  }
  function updateSubmit() { submitButton.disabled = !(state.front && state.back && state.selfie); }
  function stopCamera() {
    if (state.stream) state.stream.getTracks().forEach(function (track) { track.stop(); });
    state.stream = null;
  }
  function inputStep(input) {
    if (input.id === 'idFrontInput') return 'front';
    if (input.id === 'idBackInput') return 'back';
    return 'selfie';
  }
  function setInputFile(input, blob, filename) {
    var file = new File([blob], filename, { type: blob.type || 'image/jpeg' });
    var transfer = new DataTransfer();
    transfer.items.add(file);
    input.files = transfer.files;
    input.dispatchEvent(new Event('change', { bubbles:true }));
  }
  function imageMetrics(file) {
    return new Promise(function (resolve, reject) {
      var img = new Image();
      img.onload = function () {
        var canvas = document.createElement('canvas');
        var w = Math.min(320, img.width);
        var h = Math.max(1, Math.round(img.height * (w / img.width)));
        canvas.width = w; canvas.height = h;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, w, h);
        var data = ctx.getImageData(0, 0, w, h).data;
        var total = 0;
        for (var i = 0; i < data.length; i += 4) total += (data[i] + data[i+1] + data[i+2]) / 3;
        resolve({ width: img.width, height: img.height, brightness: total / (data.length / 4), image: img });
      };
      img.onerror = reject;
      img.src = URL.createObjectURL(file);
    });
  }
  async function validateQuality(file, kind) {
    var metrics = await imageMetrics(file);
    if (metrics.width < 500 || metrics.height < 320) return 'Picture is too small. Move closer and retake it.';
    if (metrics.brightness < 35) return 'Picture is too dark. Add light and retake it.';
    if (metrics.brightness > 245) return 'Picture is too bright. Avoid glare and retake it.';
    if (kind === 'selfie' && 'FaceDetector' in window) {
      try {
        var detector = new FaceDetector({ fastMode:true, maxDetectedFaces:2 });
        var faces = await detector.detect(metrics.image);
        if (!faces.length) return 'No face detected. Center your face and retake the selfie.';
      } catch (e) {}
    }
    return null;
  }
  async function scanFront(file) {
    var selected = idType.value;
    if (!selected) return { ok:false, message:'Select an ID type first.' };
    var body = new FormData();
    body.append('_token', token);
    body.append('id_type', selected);
    body.append('id_front', file);
    var response = await fetch(scanUrl, { method:'POST', body:body, headers:{ 'Accept':'application/json' } });
    if (!response.ok) return { ok:false, message:'ID scan failed. Please retake the front ID photo.' };
    return await response.json();
  }
  async function handleFile(input) {
    var file = input.files && input.files[0] ? input.files[0] : null;
    var step = inputStep(input);
    state[step] = false;
    updateSubmit();
    if (!file) return;

    setStatus(input.id, 'Checking picture quality...', 'text-warning');
    try {
      var qualityError = await validateQuality(file, step);
      if (qualityError) {
        input.value = '';
        setStatus(input.id, qualityError, 'text-danger');
        return;
      }
      if (step === 'front') {
        setStatus(input.id, 'Scanning front ID and checking selected ID type...', 'text-warning');
        var result = await scanFront(file);
        if (!result.ok) {
          input.value = '';
          setStatus(input.id, result.message || 'Selected ID type does not match the scanned ID.', 'text-danger');
          return;
        }
        state.front = true;
        setStatus(input.id, result.message || 'Front ID matched.', 'text-success');
        showStep('back');
      } else if (step === 'back') {
        state.back = true;
        setStatus(input.id, 'Back ID captured. Continue to face verification.', 'text-success');
        showStep('selfie');
      } else {
        state.selfie = true;
        setStatus(input.id, 'Face image captured. You may submit for review.', 'text-success');
      }
      updateSubmit();
    } catch (e) {
      input.value = '';
      setStatus(input.id, 'Could not check this image. Please retake it.', 'text-danger');
    }
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
  document.querySelectorAll('[data-camera-start]').forEach(function (button) {
    button.addEventListener('click', async function () {
      var input = document.getElementById(button.dataset.cameraStart);
      var stage = button.closest('[data-step]');
      var video = stage ? stage.querySelector('video') : null;
      if (!input || !video || !navigator.mediaDevices?.getUserMedia) {
        if (input) input.click();
        return;
      }
      stopCamera();
      state.activeInput = input;
      try {
        state.stream = await navigator.mediaDevices.getUserMedia({ video:{ facingMode: button.dataset.facing || 'environment' }, audio:false });
        video.srcObject = state.stream;
        await video.play();
        setStatus(input.id, 'Camera ready. Align the subject inside the frame, then capture.', 'text-warning');
      } catch (e) {
        setStatus(input.id, 'Camera unavailable. Upload a clear picture instead.', 'text-danger');
        input.click();
      }
    });
  });
  document.querySelectorAll('[data-camera-capture]').forEach(function (button) {
    button.addEventListener('click', function () {
      var input = document.getElementById(button.dataset.cameraCapture);
      var stage = button.closest('[data-step]');
      var video = stage ? stage.querySelector('video') : null;
      var canvas = stage ? stage.querySelector('canvas') : null;
      if (!input || !video || !canvas || !video.videoWidth) {
        if (input) setStatus(input.id, 'Open the camera first or upload a picture.', 'text-danger');
        return;
      }
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
      canvas.toBlob(function (blob) {
        if (!blob) return;
        setInputFile(input, blob, input.id + '.jpg');
      }, 'image/jpeg', 0.9);
    });
  });
  if (idType) {
    idType.addEventListener('change', function () {
      state.front = false; state.back = false; state.selfie = false;
      ['idFrontInput','idBackInput','selfieInput'].forEach(function (id) {
        var input = document.getElementById(id);
        if (input) input.value = '';
      });
      setStatus('idFrontInput', 'Waiting for front ID scan.');
      setStatus('idBackInput', 'Waiting for back ID scan.');
      setStatus('selfieInput', 'Waiting for face verification.');
      showStep('front'); updateSubmit(); stopCamera();
    });
  }
  form.addEventListener('submit', function (event) {
    if (!(state.front && state.back && state.selfie)) {
      event.preventDefault();
      document.getElementById('idUploadSummary').innerHTML = '<div class="alert alert-danger mb-0">Complete front ID scan, back ID scan, and face verification first.</div>';
    }
  });
  showStep('front'); updateSubmit();
});
</script>
@endsection
