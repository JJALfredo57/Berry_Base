@extends('layouts.app')
@section('content')
<style>
.bb-verification-list{display:grid;gap:.75rem}
.bb-verification-row{display:grid;grid-template-columns:minmax(170px,1.2fr) minmax(140px,.95fr) minmax(110px,.65fr) minmax(180px,1fr) minmax(132px,.7fr) auto;gap:1rem;align-items:center;padding:1rem;border:1px solid #e5e7eb;border-radius:8px;background:#fff;box-shadow:0 8px 22px rgba(15,23,42,.045)}
.bb-verification-row.is-rejected{background:#fff7f7;border-color:#f3d4d4}
.bb-verification-label{font-size:.68rem;text-transform:uppercase;letter-spacing:.04em;color:#94a3b8;font-weight:800;margin-bottom:.18rem}
.bb-verification-value{font-size:.86rem;color:#334155}
.bb-thumb-strip{display:flex;gap:.45rem;align-items:center;flex-wrap:wrap}
.bb-thumb{display:grid;gap:.2rem;text-decoration:none;color:#475569;font-size:.68rem;font-weight:700;text-align:center}
.bb-thumb img{width:54px;height:54px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb;background:#f8fafc}
.bb-review-modal .modal-dialog{display:flex;align-items:center;min-height:calc(100% - 1rem)}
.bb-review-modal .modal-content{border:0;border-radius:10px;box-shadow:0 24px 70px rgba(15,23,42,.26);max-height:min(92vh,860px)}
.bb-review-modal .modal-body{overflow-y:auto;overscroll-behavior:contain}
.bb-review-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}
.bb-review-image{border:1px solid #e5e7eb;border-radius:8px;background:#f8fafc;padding:.65rem;height:100%}
.bb-review-image img{width:100%;max-height:360px;object-fit:contain;background:#fff;border-radius:8px;border:1px solid #eef2f7}
.bb-review-facts{border:1px solid #e5e7eb;border-radius:8px;background:#f8fafc;padding:.85rem}
.bb-modal-actions{display:grid;gap:.75rem;grid-template-columns:minmax(180px,auto) minmax(260px,1fr);align-items:start}
@media (max-width:1199.98px){.bb-verification-row{grid-template-columns:1fr 1fr}.bb-verification-row>div:last-child{grid-column:1/-1}.bb-review-grid{grid-template-columns:1fr}.bb-modal-actions{grid-template-columns:1fr}}
@media (max-width:575.98px){.bb-verification-row{grid-template-columns:1fr;gap:.7rem}.bb-thumb img{width:48px;height:48px}.bb-review-modal .modal-dialog{min-height:100%;margin:.5rem}.bb-review-image img{max-height:280px}}
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

  <div class="bb-verification-list">
    @forelse($rows as $row)
      @php
        $reviewFlags = json_decode($row->review_flags ?? '', true) ?: [];
        $faceMatchStatus = $reviewFlags['face_match_status'] ?? 'manual_review';
        $isFaceMismatch = $faceMatchStatus === 'mismatch';
        $isMismatch = ($row->id_type_match_status ?? null) === 'mismatch';
        $detectedType = $row->id_type_scan_detected ?: null;
      @endphp
      <div class="bb-verification-row {{ $row->status === 'rejected' ? 'is-rejected' : '' }}">
        <div>
          <div class="bb-verification-label">Customer</div>
          <div class="fw-bold">{{ $row->fullname }}</div>
          <div class="text-muted small">{{ $row->email }}</div>
          <div class="text-muted small">{{ $row->phone }}</div>
        </div>
        <div>
          <div class="bb-verification-label">ID Type</div>
          <div class="bb-verification-value"><strong>Selected:</strong> {{ $row->id_type }}</div>
          <div class="bb-verification-value"><strong>Detected:</strong> {{ $detectedType ?: 'Not detected' }}</div>
        </div>
        <div>
          <div class="bb-verification-label">Status</div>
          <span class="badge {{ $row->status === 'approved' ? 'text-bg-success' : ($row->status === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ ucfirst($row->status) }}</span>
          @if(($row->status ?? '') === 'pending' && (($row->id_type_match_status ?? '') === 'needs_review' || $faceMatchStatus === 'needs_review' || $faceMatchStatus === 'manual_review'))
            <div class="small text-warning fw-semibold mt-1">Needs admin review</div>
          @endif
        </div>
        <div>
          <div class="bb-verification-label">Files</div>
          <div class="bb-thumb-strip">
            <a class="bb-thumb" href="{{ $row->id_front_path }}" target="_blank" rel="noopener"><img src="{{ $row->id_front_path }}" alt="Front ID"><span>Front</span></a>
            @if($row->id_back_path)<a class="bb-thumb" href="{{ $row->id_back_path }}" target="_blank" rel="noopener"><img src="{{ $row->id_back_path }}" alt="Back ID"><span>Back</span></a>@endif
            @if($row->selfie_path)<a class="bb-thumb" href="{{ $row->selfie_path }}" target="_blank" rel="noopener"><img src="{{ $row->selfie_path }}" alt="Selfie"><span>Selfie</span></a>@endif
          </div>
        </div>
        <div>
          <div class="bb-verification-label">Submitted</div>
          <div class="bb-verification-value">{{ \Carbon\Carbon::parse($row->created_at)->format('M d, Y') }}</div>
          <div class="text-muted small">{{ \Carbon\Carbon::parse($row->created_at)->format('g:i A') }}</div>
        </div>
        <div class="text-end text-md-start">
          <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#verificationReviewModal{{ $row->id }}">
            <i class="bi bi-search me-1"></i>Review
          </button>
        </div>
      </div>
    @empty
      <div class="card"><div class="card-body text-center text-muted py-5">No verification submissions yet.</div></div>
    @endforelse
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
    <div class="modal fade bb-review-modal" id="verificationReviewModal{{ $row->id }}" tabindex="-1" aria-hidden="true">
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
          <div class="modal-footer d-block">
            @if($row->status === 'pending')
              <div class="bb-modal-actions">
                <div class="d-flex flex-wrap gap-2">
                  @if($modalIdMismatch || $modalFaceMismatch)
                    <button class="btn btn-secondary" disabled><i class="bi bi-lock me-1"></i>Approval locked</button>
                  @else
                    <form action="{{ route($routePrefix . 'customer_verifications.approve', $row->id) }}" method="POST">@csrf<button class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Approve</button></form>
                  @endif
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                </div>
                <form action="{{ route($routePrefix . 'customer_verifications.reject', $row->id) }}" method="POST" class="d-flex flex-wrap gap-2 justify-content-end">
                  @csrf
                  <input class="form-control" name="reason" placeholder="Reject comment / reason" required minlength="5" style="min-width:260px;flex:1 1 260px">
                  <button class="btn btn-outline-danger"><i class="bi bi-x-lg me-1"></i>Reject</button>
                </form>
              </div>
            @else
              <div class="d-flex justify-content-between align-items-center gap-2">
                <div class="text-muted small">{{ $row->reviewed_at ? 'Reviewed ' . \Carbon\Carbon::parse($row->reviewed_at)->format('M d, Y g:i A') : 'Reviewed' }}</div>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
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