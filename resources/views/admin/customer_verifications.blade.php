@extends('layouts.app')
@section('content')
<style>
.bb-verify-files{display:grid;grid-template-columns:repeat(3,minmax(72px,1fr));gap:.5rem;min-width:240px}
.bb-verify-file{display:flex;flex-direction:column;gap:.25rem;text-decoration:none;color:var(--gray-800);font-size:.72rem;font-weight:700}
.bb-verify-file img{width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb;background:#f8fafc;box-shadow:0 6px 16px rgba(15,23,42,.06)}
.bb-verify-file:hover img{border-color:var(--primary);box-shadow:0 8px 22px rgba(15,23,42,.12)}
.bb-review-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}
.bb-review-image{border:1px solid #e5e7eb;border-radius:8px;background:#f8fafc;padding:.65rem;height:100%}
.bb-review-image img{width:100%;max-height:360px;object-fit:contain;background:#fff;border-radius:8px;border:1px solid #eef2f7}
.bb-review-facts{border:1px solid #e5e7eb;border-radius:8px;background:#f8fafc;padding:.85rem}
@media (max-width:991.98px){.bb-review-grid{grid-template-columns:1fr}.bb-verify-files{grid-template-columns:repeat(3,82px);min-width:0}.bb-verify-file img{height:64px;aspect-ratio:auto}}
@media (max-width:575.98px){.bb-verify-files{grid-template-columns:repeat(3,minmax(66px,1fr));gap:.35rem}.bb-verify-file{font-size:.68rem}.bb-review-image img{max-height:280px}}
</style>
@php
  $routePrefix = Route::currentRouteName() && str_starts_with(Route::currentRouteName(), 'superadmin.') ? 'superadmin.' : 'admin.';
@endphp
<div class="container-fluid py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="fw-bold mb-0"><i class="bi bi-shield-check me-2" style="color:var(--primary)"></i>Customer Verifications</h4>
  </div>
  @if(session('msg'))<div class="alert alert-success border-0">{{ session('msg') }}</div>@endif
  @if(session('err'))<div class="alert alert-danger border-0">{{ session('err') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger border-0">{{ $errors->first() }}</div>@endif

  <div class="card">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Customer</th><th>ID Type</th><th>Status</th><th>OCR / Face Review</th><th>Files</th><th>Submitted</th><th>Action</th></tr></thead>
        <tbody>
          @forelse($rows as $row)
            @php
              $matchStatus = $row->id_type_match_status ?? 'not_scanned';
              $scanStatus = $row->scan_status ?? 'manual_review';
              $scanResult = json_decode($row->scan_result ?? '', true) ?: [];
              $reviewFlags = json_decode($row->review_flags ?? '', true) ?: [];
              $ocrPreview = $scanResult['text_preview'] ?? null;
              $expectedType = $row->id_type_scan_expected ?: $row->id_type;
              $detectedType = $row->id_type_scan_detected ?: null;
              $engine = $scanResult['engine'] ?? ($scanStatus === 'scanned' ? 'ocr' : 'manual');
              $textLength = $scanResult['text_length'] ?? null;
              $scores = is_array($scanResult['scores'] ?? null) ? $scanResult['scores'] : [];
              $matchedKeywords = is_array($scanResult['matched_keywords'] ?? null) ? $scanResult['matched_keywords'] : [];
              $livenessResult = $reviewFlags['liveness_result'] ?? 'not_recorded';
              $livenessMethod = $reviewFlags['liveness_method'] ?? 'not_available';
              $livenessChallenge = $reviewFlags['liveness_challenge'] ?? null;
              $faceMatchStatus = $reviewFlags['face_match_status'] ?? 'manual_review';
              $faceMatchScore = isset($reviewFlags['face_match_score']) && is_numeric($reviewFlags['face_match_score']) ? (float) $reviewFlags['face_match_score'] : null;
              $faceMatchMessage = $reviewFlags['face_match_message'] ?? null;
              $faceMatchEngine = $reviewFlags['face_match_engine'] ?? null;
              $isFaceMismatch = $faceMatchStatus === 'mismatch';
              $isMismatch = $matchStatus === 'mismatch';
              $isMatched = $matchStatus === 'match';
              $livenessPassed = $livenessResult === 'passed';
            @endphp
            <tr>
              <td><div class="fw-semibold">{{ $row->fullname }}</div><div class="text-muted small">{{ $row->email }} &bull; {{ $row->phone }}</div></td>
              <td>
                <div class="fw-semibold small">Selected: {{ $row->id_type }}</div>
                <div class="text-muted small">Expected: {{ $expectedType }}</div>
                <div class="small {{ $detectedType ? '' : 'text-muted' }}">Detected: {{ $detectedType ?: 'Not detected' }}</div>
              </td>
              <td><span class="badge {{ $row->status === 'approved' ? 'text-bg-success' : ($row->status === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ ucfirst($row->status) }}</span></td>
              <td class="small" style="min-width:340px;max-width:460px">
                @if($isMismatch)
                  <span class="badge text-bg-danger mb-2"><i class="bi bi-exclamation-triangle me-1"></i>ID type mismatch</span>
                  <div class="text-danger fw-semibold">{{ $row->id_type_match_warning ?? 'Selected ID type does not match the uploaded ID.' }}</div>
                @elseif($isMatched)
                  <span class="badge text-bg-success mb-2"><i class="bi bi-check-circle me-1"></i>ID type matched by OCR</span>
                  <div class="text-muted">OCR found keywords for the selected ID type. Manual identity review is still required.</div>
                @else
                  <span class="badge text-bg-light mb-2"><i class="bi bi-person-check me-1"></i>{{ ucfirst(str_replace('_', ' ', $scanStatus)) }}</span>
                  <div class="text-muted">{{ $row->id_type_match_warning ?: 'Check the uploaded ID manually before approving.' }}</div>
                @endif

                <div class="mt-2 p-2 rounded" style="background:#f8fafc;border:1px solid #e5e7eb">
                  <div class="d-flex flex-wrap gap-2 mb-1">
                    <span class="badge text-bg-light">Engine: {{ strtoupper($engine) }}</span>
                    <span class="badge {{ $scanStatus === 'scanned' ? 'text-bg-primary' : 'text-bg-light' }}">Scan: {{ ucfirst(str_replace('_', ' ', $scanStatus)) }}</span>
                    <span class="badge {{ $isMatched ? 'text-bg-success' : ($isMismatch ? 'text-bg-danger' : 'text-bg-warning') }}">ID match: {{ ucfirst(str_replace('_', ' ', $matchStatus)) }}</span>
                    <span class="badge {{ $livenessPassed ? 'text-bg-success' : 'text-bg-warning' }}">Liveness: {{ ucfirst(str_replace('_', ' ', $livenessResult)) }}</span>
                    <span class="badge {{ $faceMatchStatus === 'match' ? 'text-bg-success' : ($isFaceMismatch ? 'text-bg-danger' : 'text-bg-warning') }}">Face match: {{ ucfirst(str_replace('_', ' ', $faceMatchStatus)) }}</span>
                  </div>
                  <div class="row g-1 text-muted">
                    <div class="col-sm-6">Expected: <strong class="text-body">{{ $expectedType ?: 'N/A' }}</strong></div>
                    <div class="col-sm-6">Detected: <strong class="text-body">{{ $detectedType ?: 'N/A' }}</strong></div>
                    @if($textLength !== null)
                      <div class="col-sm-6">OCR text length: <strong class="text-body">{{ number_format((int) $textLength) }}</strong></div>
                    @endif
                    <div class="col-sm-6">Liveness method: <strong class="text-body">{{ ucfirst(str_replace('_', ' ', $livenessMethod)) }}</strong></div>
                    @if($faceMatchScore !== null)
                      <div class="col-sm-6">Face score: <strong class="text-body">{{ number_format($faceMatchScore * 100, 1) }}%</strong></div>
                    @endif
                    @if($faceMatchEngine)
                      <div class="col-sm-6">Face engine: <strong class="text-body">{{ ucfirst(str_replace('_', ' ', $faceMatchEngine)) }}</strong></div>
                    @endif
                    @if($faceMatchMessage)
                      <div class="col-12 {{ $isFaceMismatch ? 'text-danger fw-semibold' : '' }}">Face note: <strong class="text-body">{{ $faceMatchMessage }}</strong></div>
                    @endif
                    @if($livenessChallenge)
                      <div class="col-12">Challenge: <strong class="text-body">{{ ucfirst(str_replace('_', ' ', $livenessChallenge)) }}</strong></div>
                    @endif
                  </div>
                </div>

                @if(count($scores) || count($matchedKeywords) || $ocrPreview)
                  <details class="mt-2">
                    <summary class="text-muted" style="cursor:pointer">OCR evidence</summary>
                    <div class="mt-2 p-2 rounded" style="background:#fff;border:1px solid #e5e7eb;max-height:220px;overflow:auto">
                      @if(count($scores))
                        <div class="fw-semibold mb-1">Detected scores</div>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                          @foreach($scores as $type => $score)
                            <span class="badge text-bg-light">{{ $type }}: {{ $score }}</span>
                          @endforeach
                        </div>
                      @endif
                      @if(count($matchedKeywords))
                        <div class="fw-semibold mb-1">Matched keywords</div>
                        @foreach($matchedKeywords as $type => $keywords)
                          <div class="mb-1"><span class="text-muted">{{ $type }}:</span> {{ implode(', ', array_unique($keywords)) }}</div>
                        @endforeach
                      @endif
                      @if($ocrPreview)
                        <div class="fw-semibold mt-2 mb-1">OCR text preview</div>
                        <div style="white-space:normal">{{ $ocrPreview }}</div>
                      @endif
                    </div>
                  </details>
                @endif
              </td>
              <td class="small">
                <div class="bb-verify-files">
                  <a class="bb-verify-file" href="{{ $row->id_front_path }}" target="_blank" rel="noopener">
                    <img src="{{ $row->id_front_path }}" alt="Front ID uploaded by {{ $row->fullname }}" loading="lazy">
                    <span>Front ID</span>
                  </a>
                  @if($row->id_back_path)
                    <a class="bb-verify-file" href="{{ $row->id_back_path }}" target="_blank" rel="noopener">
                      <img src="{{ $row->id_back_path }}" alt="Back ID uploaded by {{ $row->fullname }}" loading="lazy">
                      <span>Back ID</span>
                    </a>
                  @endif
                  @if($row->selfie_path)
                    <a class="bb-verify-file" href="{{ $row->selfie_path }}" target="_blank" rel="noopener">
                      <img src="{{ $row->selfie_path }}" alt="Selfie uploaded by {{ $row->fullname }}" loading="lazy">
                      <span>Selfie</span>
                    </a>
                  @endif
                </div>
              </td>
              <td class="small text-muted">{{ \Carbon\Carbon::parse($row->created_at)->format('M d, Y g:i A') }}</td>
              <td style="min-width:320px">
                @if($row->status === 'pending')
                  <div class="d-flex flex-wrap gap-2 mb-2">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#verificationReviewModal{{ $row->id }}"><i class="bi bi-search me-1"></i>Review</button>
                    @if($isMismatch || $isFaceMismatch)
                      <button class="btn btn-secondary btn-sm" disabled title="Cannot approve while an ID or face mismatch is flagged"><i class="bi bi-lock"></i></button>
                    @else
                      <form action="{{ route($routePrefix . 'customer_verifications.approve', $row->id) }}" method="POST">@csrf<button class="btn btn-success btn-sm" title="Approve"><i class="bi bi-check-lg"></i></button></form>
                    @endif
                    <form action="{{ route($routePrefix . 'customer_verifications.reject', $row->id) }}" method="POST" class="d-flex gap-1">
                      @csrf
                      <input class="form-control form-control-sm" name="reason" placeholder="Reject reason" required>
                      <button class="btn btn-outline-danger btn-sm" title="Reject"><i class="bi bi-x-lg"></i></button>
                    </form>
                  </div>
                  <form action="{{ route($routePrefix . 'customer_verifications.flag_id_type', $row->id) }}" method="POST" class="d-flex flex-wrap gap-1">
                    @csrf
                    <input class="form-control form-control-sm" name="detected_id_type" placeholder="Actual ID type seen" value="{{ $detectedType }}" style="max-width:150px">
                    <input class="form-control form-control-sm" name="warning" placeholder="Mismatch warning" value="{{ $row->id_type_match_warning ?: 'Selected ID type does not match the uploaded ID.' }}" required style="max-width:260px">
                    <button class="btn btn-outline-warning btn-sm" title="Flag mismatch"><i class="bi bi-flag"></i></button>
                  </form>
                @else
                  <div class="d-flex flex-wrap align-items-center gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#verificationReviewModal{{ $row->id }}"><i class="bi bi-search me-1"></i>View</button>
                    <span class="text-muted small">{{ $row->reviewed_at ? \Carbon\Carbon::parse($row->reviewed_at)->format('M d, Y') : 'Reviewed' }}</span>
                  </div>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-5">No verification submissions yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @foreach($rows as $row)
    @php
      $modalReviewFlags = json_decode($row->review_flags ?? '', true) ?: [];
      $modalScanResult = json_decode($row->scan_result ?? '', true) ?: [];
      $modalFaceStatus = $modalReviewFlags['face_match_status'] ?? 'manual_review';
      $modalFaceMismatch = $modalFaceStatus === 'mismatch';
      $modalIdMismatch = ($row->id_type_match_status ?? null) === 'mismatch';
      $modalDetectedType = $row->id_type_scan_detected ?: null;
      $modalExpectedType = $row->id_type_scan_expected ?: $row->id_type;
    @endphp
    <div class="modal fade" id="verificationReviewModal{{ $row->id }}" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <h5 class="modal-title fw-bold mb-0">Review Verification: {{ $row->fullname }}</h5>
              <div class="text-muted small">{{ $row->email }} &bull; {{ $row->phone }} &bull; Submitted {{ \Carbon\Carbon::parse($row->created_at)->format('M d, Y g:i A') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="bb-review-grid mb-3">
              <div class="bb-review-image">
                <div class="fw-semibold small mb-2">Front ID</div>
                <a href="{{ $row->id_front_path }}" target="_blank" rel="noopener"><img src="{{ $row->id_front_path }}" alt="Front ID uploaded by {{ $row->fullname }}"></a>
              </div>
              @if($row->id_back_path)
                <div class="bb-review-image">
                  <div class="fw-semibold small mb-2">Back ID</div>
                  <a href="{{ $row->id_back_path }}" target="_blank" rel="noopener"><img src="{{ $row->id_back_path }}" alt="Back ID uploaded by {{ $row->fullname }}"></a>
                </div>
              @endif
              @if($row->selfie_path)
                <div class="bb-review-image">
                  <div class="fw-semibold small mb-2">Selfie</div>
                  <a href="{{ $row->selfie_path }}" target="_blank" rel="noopener"><img src="{{ $row->selfie_path }}" alt="Selfie uploaded by {{ $row->fullname }}"></a>
                </div>
              @endif
            </div>
            <div class="bb-review-facts small">
              <div class="d-flex flex-wrap gap-2 mb-2">
                <span class="badge {{ $row->status === 'approved' ? 'text-bg-success' : ($row->status === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ ucfirst($row->status) }}</span>
                <span class="badge {{ $modalIdMismatch ? 'text-bg-danger' : (($row->id_type_match_status ?? '') === 'match' ? 'text-bg-success' : 'text-bg-warning') }}">ID: {{ ucfirst(str_replace('_', ' ', $row->id_type_match_status ?? 'not_scanned')) }}</span>
                <span class="badge {{ $modalFaceStatus === 'match' ? 'text-bg-success' : ($modalFaceMismatch ? 'text-bg-danger' : 'text-bg-warning') }}">Face: {{ ucfirst(str_replace('_', ' ', $modalFaceStatus)) }}</span>
              </div>
              <div class="row g-2">
                <div class="col-md-4">Selected ID: <strong>{{ $row->id_type }}</strong></div>
                <div class="col-md-4">Expected: <strong>{{ $modalExpectedType ?: 'N/A' }}</strong></div>
                <div class="col-md-4">Detected: <strong>{{ $modalDetectedType ?: 'Not detected' }}</strong></div>
                @if(!empty($row->id_type_match_warning))
                  <div class="col-12 text-danger fw-semibold">ID note: {{ $row->id_type_match_warning }}</div>
                @endif
                @if(!empty($modalReviewFlags['face_match_message']))
                  <div class="col-12 {{ $modalFaceMismatch ? 'text-danger fw-semibold' : '' }}">Face note: {{ $modalReviewFlags['face_match_message'] }}</div>
                @endif
                @if(!empty($modalScanResult['text_preview']))
                  <div class="col-12"><details><summary class="text-muted" style="cursor:pointer">OCR text preview</summary><div class="mt-2 p-2 bg-white rounded border">{{ $modalScanResult['text_preview'] }}</div></details></div>
                @endif
              </div>
            </div>
          </div>
          <div class="modal-footer justify-content-between">
            <div class="text-muted small">Open any image to inspect it full size.</div>
            @if($row->status === 'pending')
              <div class="d-flex flex-wrap gap-2">
                @if($modalIdMismatch || $modalFaceMismatch)
                  <button class="btn btn-secondary" disabled><i class="bi bi-lock me-1"></i>Approval locked</button>
                @else
                  <form action="{{ route($routePrefix . 'customer_verifications.approve', $row->id) }}" method="POST">@csrf<button class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Approve</button></form>
                @endif
                <form action="{{ route($routePrefix . 'customer_verifications.reject', $row->id) }}" method="POST" class="d-flex flex-wrap gap-2">
                  @csrf
                  <input class="form-control" name="reason" placeholder="Reject reason" required style="min-width:220px">
                  <button class="btn btn-outline-danger"><i class="bi bi-x-lg me-1"></i>Reject</button>
                </form>
                <form action="{{ route($routePrefix . 'customer_verifications.flag_id_type', $row->id) }}" method="POST" class="d-flex flex-wrap gap-2">
                  @csrf
                  <input class="form-control" name="detected_id_type" placeholder="Actual ID type" value="{{ $modalDetectedType }}" style="max-width:170px">
                  <input class="form-control" name="warning" placeholder="Mismatch warning" value="{{ $row->id_type_match_warning ?: 'Selected ID type does not match the uploaded ID.' }}" required style="min-width:240px">
                  <button class="btn btn-outline-warning"><i class="bi bi-flag me-1"></i>Flag</button>
                </form>
              </div>
            @endif
          </div>
        </div>
      </div>
    </div>
  @endforeach
  <div class="mt-3">{{ $rows->links() }}</div>
</div>
@endsection