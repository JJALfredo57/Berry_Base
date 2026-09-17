@extends('layouts.app')
@section('content')
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
        <thead><tr><th>Customer</th><th>ID Type</th><th>Status</th><th>Files</th><th>Submitted</th><th>Action</th></tr></thead>
        <tbody>
          @forelse($rows as $row)
            <tr>
              <td><div class="fw-semibold">{{ $row->fullname }}</div><div class="text-muted small">{{ $row->email }} &bull; {{ $row->phone }}</div></td>
              <td>{{ $row->id_type }}</td>
              <td><span class="badge {{ $row->status === 'approved' ? 'text-bg-success' : ($row->status === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ ucfirst($row->status) }}</span></td>
              <td class="small">
                <a href="{{ $row->id_front_path }}" target="_blank">Front</a>
                @if($row->id_back_path) &bull; <a href="{{ $row->id_back_path }}" target="_blank">Back</a>@endif
                @if($row->selfie_path) &bull; <a href="{{ $row->selfie_path }}" target="_blank">Selfie</a>@endif
              </td>
              <td class="small text-muted">{{ \Carbon\Carbon::parse($row->created_at)->format('M d, Y g:i A') }}</td>
              <td>
                @if($row->status === 'pending')
                  <div class="d-flex flex-wrap gap-2">
                    <form action="{{ route(Route::currentRouteName() && str_starts_with(Route::currentRouteName(), 'superadmin.') ? 'superadmin.customer_verifications.approve' : 'admin.customer_verifications.approve', $row->id) }}" method="POST">@csrf<button class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i></button></form>
                    <form action="{{ route(Route::currentRouteName() && str_starts_with(Route::currentRouteName(), 'superadmin.') ? 'superadmin.customer_verifications.reject' : 'admin.customer_verifications.reject', $row->id) }}" method="POST" class="d-flex gap-1">
                      @csrf
                      <input class="form-control form-control-sm" name="reason" placeholder="Reject reason" required>
                      <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-lg"></i></button>
                    </form>
                  </div>
                @else
                  <span class="text-muted small">{{ $row->reviewed_at ? \Carbon\Carbon::parse($row->reviewed_at)->format('M d, Y') : 'Reviewed' }}</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-5">No verification submissions yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-3">{{ $rows->links() }}</div>
</div>
@endsection
