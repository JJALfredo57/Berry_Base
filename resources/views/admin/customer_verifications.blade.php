@extends('layouts.app')
@section('content')
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
        <thead><tr><th>Customer</th><th>ID Type</th><th>Status</th><th>OCR / Review Check</th><th>Files</th><th>Submitted</th><th>Action</th></tr></thead>
        <tbody>
          @forelse($rows as $row)
            @php
              $matchStatus = $row->id_type_match_status ?? 'not_scanned';
              $scanStatus = $row->scan_status ?? 'manual_review';
              $scanResult = json_decode($row->scan_result ?? '', true) ?: [];
              $ocrPreview = $scanResult['text_preview'] ?? null;
            @endphp
            <tr>
              <td><div class="fw-semibold">{{ $row->fullname }}</div><div class="text-muted small">{{ $row->email }} &bull; {{ $row->phone }}</div></td>
              <td>
                <div class="fw-semibold small">Selected: {{ $row->id_type }}</div>
                @if(!empty($row->id_type_scan_detected))
                  <div class="text-muted small">Detected: {{ $row->id_type_scan_detected }}</div>
                @endif
              </td>
              <td><span class="badge {{ $row->status === 'approved' ? 'text-bg-success' : ($row->status === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ ucfirst($row->status) }}</span></td>
              <td class="small" style="min-width:260px;max-width:360px">
                @if($matchStatus === 'mismatch')
                  <span class="badge text-bg-danger mb-1"><i class="bi bi-exclamation-triangle me-1"></i>ID type mismatch</span>
                  <div class="text-danger">{{ $row->id_type_match_warning ?? 'Selected ID type does not match the uploaded ID.' }}</div>
                @elseif($matchStatus === 'match')
                  <span class="badge text-bg-success mb-1"><i class="bi bi-check-circle me-1"></i>ID type matched by OCR</span>
                  <div class="text-muted">OCR found keywords for the selected ID type. Manual identity review is still required.</div>
                @else
                  <span class="badge text-bg-light mb-1"><i class="bi bi-person-check me-1"></i>{{ ucfirst(str_replace('_', ' ', $scanStatus)) }}</span>
                  <div class="text-muted">{{ $row->id_type_match_warning ?: 'Check the uploaded ID manually before approving.' }}</div>
                @endif
                @if($ocrPreview)
                  <details class="mt-2">
                    <summary class="text-muted" style="cursor:pointer">OCR text preview</summary>
                    <div class="mt-1 p-2 rounded" style="background:#f8fafc;border:1px solid #e5e7eb;max-height:120px;overflow:auto;white-space:normal">{{ $ocrPreview }}</div>
                  </details>
                @endif
              </td>
              <td class="small">
                <a href="{{ $row->id_front_path }}" target="_blank">Front</a>
                @if($row->id_back_path) &bull; <a href="{{ $row->id_back_path }}" target="_blank">Back</a>@endif
                @if($row->selfie_path) &bull; <a href="{{ $row->selfie_path }}" target="_blank">Selfie</a>@endif
              </td>
              <td class="small text-muted">{{ \Carbon\Carbon::parse($row->created_at)->format('M d, Y g:i A') }}</td>
              <td style="min-width:320px">
                @if($row->status === 'pending')
                  <div class="d-flex flex-wrap gap-2 mb-2">
                    <form action="{{ route($routePrefix . 'customer_verifications.approve', $row->id) }}" method="POST">@csrf<button class="btn btn-success btn-sm" title="Approve"><i class="bi bi-check-lg"></i></button></form>
                    <form action="{{ route($routePrefix . 'customer_verifications.reject', $row->id) }}" method="POST" class="d-flex gap-1">
                      @csrf
                      <input class="form-control form-control-sm" name="reason" placeholder="Reject reason" required>
                      <button class="btn btn-outline-danger btn-sm" title="Reject"><i class="bi bi-x-lg"></i></button>
                    </form>
                  </div>
                  <form action="{{ route($routePrefix . 'customer_verifications.flag_id_type', $row->id) }}" method="POST" class="d-flex flex-wrap gap-1">
                    @csrf
                    <input class="form-control form-control-sm" name="detected_id_type" placeholder="Actual ID type seen" style="max-width:150px">
                    <input class="form-control form-control-sm" name="warning" placeholder="Mismatch warning" value="Selected ID type does not match the uploaded ID." required style="max-width:260px">
                    <button class="btn btn-outline-warning btn-sm" title="Flag mismatch"><i class="bi bi-flag"></i></button>
                  </form>
                @else
                  <span class="text-muted small">{{ $row->reviewed_at ? \Carbon\Carbon::parse($row->reviewed_at)->format('M d, Y') : 'Reviewed' }}</span>
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
  <div class="mt-3">{{ $rows->links() }}</div>
</div>
@endsection