@extends('layouts.app')
@section('content')
<div class="page-header">
  <h4 class="page-title"><i class="bi bi-palette me-2" style="color:var(--primary)"></i>Custom Orders</h4>
</div>
<div class="alert alert-danger d-flex align-items-start gap-3 p-4">
  <i class="bi bi-exclamation-octagon-fill fs-4 flex-shrink-0 mt-1"></i>
  <div>
    <div class="fw-bold mb-1">Page failed to load</div>
    <div class="small font-monospace">{{ $msg }}</div>
    <div class="mt-2 small text-muted">This error has been logged. Please contact support or try again later.</div>
  </div>
</div>
@endsection
