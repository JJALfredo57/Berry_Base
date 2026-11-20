@extends('layouts.app')
@section('page_title','Order History Cleanup')
@section('content')
<div class="admin-page">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
    <div><h4 class="cs-page-title mb-1">Order History Cleanup</h4><p class="cs-page-sub mb-0">Preview and permanently remove customer orders and their linked transaction history only.</p></div>
    <a class="btn btn-outline-secondary" href="{{ route('superadmin.dashboard') }}">Back to dashboard</a>
  </div>
  @if(session('msg'))<div class="alert alert-success">{{ session('msg') }}</div>@endif
  @if(session('err'))<div class="alert alert-danger">{{ session('err') }}</div>@endif
  @if($preview['financial_blockers'])<div class="alert alert-danger"><strong>Deletion blocked.</strong><ul class="mb-0">@foreach($preview['financial_blockers'] as $name=>$count)<li>{{ str_replace('_',' ',$name) }}: {{ $count }}</li>@endforeach</ul></div>@endif
  <div class="card mb-3"><div class="card-header fw-bold">Dry-run records to delete</div><div class="card-body"><div class="row g-2">@foreach($preview['counts'] as $name=>$count)<div class="col-6 col-md-3"><div class="border rounded p-2 h-100"><div class="small text-muted">{{ str_replace('_',' ',$name) }}</div><strong>{{ number_format($count) }}</strong></div></div>@endforeach</div></div></div>
  <div class="alert alert-warning"><strong>Protected:</strong> accounts, products/cakes, sellers, riders, settings, delivery zones, and catalog data are not included. Only order-linked history is targeted.</div>
  <form method="POST" action="{{ route('superadmin.order_history_cleanup.delete') }}" class="card" data-prevent-double-submit>@csrf
    <input type="hidden" name="snapshot" value="{{ $preview['snapshot'] }}">
    <div class="card-body"><h5>Permanent deletion</h5><p class="text-muted">Type <code>DELETE ORDER HISTORY</code> to permanently delete the reviewed records. The counts will be checked again before deletion.</p>
      <input class="form-control mb-3" name="confirmation" autocomplete="off" placeholder="DELETE ORDER HISTORY" {{ $preview['financial_blockers'] ? 'disabled' : '' }}>
      <button class="btn btn-danger" type="submit" {{ $preview['financial_blockers'] ? 'disabled' : '' }}>Delete reviewed order history</button>
    </div>
  </form>
</div>
@endsection
