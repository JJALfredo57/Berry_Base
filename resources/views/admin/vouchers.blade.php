@extends('layouts.app')
@section('content')
<div class="container-fluid py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h4 class="fw-bold mb-1"><i class="bi bi-ticket-perforated me-2" style="color:var(--primary)"></i>Vouchers</h4>
      <div class="text-muted small">{{ $ctx['role'] === 'seller' ? 'Create shop vouchers for your customers.' : 'Create platform and customer-specific vouchers.' }}</div>
    </div>
  </div>

  @if(session('msg'))<div class="alert alert-success border-0">{{ session('msg') }}</div>@endif
  @if(session('err'))<div class="alert alert-danger border-0">{{ session('err') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger border-0">{{ $errors->first() }}</div>@endif

  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-body">
          <h6 class="fw-bold mb-3">Create Voucher</h6>
          <form method="POST" action="{{ route($ctx['route_prefix'].'.vouchers.store') }}">
            @csrf
            <div class="mb-2"><label class="form-label small fw-semibold">Code</label><input name="code" class="form-control text-uppercase" maxlength="40" required placeholder="BERRY100"></div>
            <div class="mb-2"><label class="form-label small fw-semibold">Name</label><input name="name" class="form-control" required placeholder="PHP 100 off"></div>
            <div class="mb-2"><label class="form-label small fw-semibold">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            <div class="row g-2">
              <div class="col-6"><label class="form-label small fw-semibold">Type</label><select name="discount_type" class="form-select"><option value="fixed">Fixed</option><option value="percent">Percent</option></select></div>
              <div class="col-6"><label class="form-label small fw-semibold">Value</label><input type="number" step="0.01" min="0.01" name="discount_value" class="form-control" required></div>
              <div class="col-6"><label class="form-label small fw-semibold">Max Discount</label><input type="number" step="0.01" min="0" name="max_discount" class="form-control"></div>
              <div class="col-6"><label class="form-label small fw-semibold">Min Order</label><input type="number" step="0.01" min="0" name="minimum_order_amount" class="form-control" value="0"></div>
              <div class="col-6"><label class="form-label small fw-semibold">Total Limit</label><input type="number" min="1" name="usage_limit" class="form-control"></div>
              <div class="col-6"><label class="form-label small fw-semibold">Per Customer</label><input type="number" min="1" name="per_customer_limit" class="form-control" value="1"></div>
            </div>
            <div class="mt-2"><label class="form-label small fw-semibold">Audience</label><select name="audience" class="form-select"><option value="public">Public</option><option value="assigned">Assigned customer only</option></select></div>
            <div class="mt-2"><label class="form-label small fw-semibold">Customer for Assigned Voucher</label><input name="customer_identifier" class="form-control" placeholder="Customer email, phone, or ID"></div>
            <div class="row g-2 mt-1">
              <div class="col-6"><label class="form-label small fw-semibold">Starts</label><input type="datetime-local" name="starts_at" class="form-control"></div>
              <div class="col-6"><label class="form-label small fw-semibold">Ends</label><input type="datetime-local" name="ends_at" class="form-control"></div>
            </div>
            <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="requires_verified_customer" value="1" id="requiresVerified"><label class="form-check-label small" for="requiresVerified">Verified customers only</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="first_order_only" value="1" id="firstOrderOnly"><label class="form-check-label small" for="firstOrderOnly">First order only</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="stack_with_product_discount" value="1" id="stackDiscount" checked><label class="form-check-label small" for="stackDiscount">Can stack with product discounts</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="activeVoucher" checked><label class="form-check-label small" for="activeVoucher">Active</label></div>
            <button class="btn btn-primary w-100 mt-3"><i class="bi bi-plus-circle me-1"></i>Create Voucher</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card">
        <div class="card-body">
          <h6 class="fw-bold mb-3">Voucher List</h6>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead><tr><th>Code</th><th>Discount</th><th>Audience</th><th>Limits</th><th>Status</th><th></th></tr></thead>
              <tbody>
                @forelse($vouchers as $voucher)
                  <tr>
                    <td><div class="fw-bold">{{ $voucher->code }}</div><div class="small text-muted">{{ $voucher->name }}</div></td>
                    <td>{{ $voucher->discount_type === 'percent' ? rtrim(rtrim(number_format($voucher->discount_value,2),'0'),'.').'%' : 'PHP '.number_format($voucher->discount_value,2) }}</td>
                    @php $audience = $voucher->audience ?? 'public'; @endphp
                    <td><span class="badge {{ $audience === 'assigned' ? 'text-bg-success' : 'text-bg-light' }}">{{ $audience === 'assigned' ? 'Assigned' : 'Public' }}</span><div class="small text-muted">{{ (int)($assignmentCounts[$voucher->id] ?? 0) }} assigned</div></td>
                    <td><div class="small">Min PHP {{ number_format($voucher->minimum_order_amount,2) }}</div><div class="small text-muted">Per customer: {{ $voucher->per_customer_limit ?: 'No limit' }}</div></td>
                    <td><span class="badge {{ $voucher->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $voucher->is_active ? 'Active' : 'Paused' }}</span></td>
                    <td class="text-end">
                      <form method="POST" action="{{ route($ctx['route_prefix'].'.vouchers.toggle', $voucher->id) }}">@csrf<button class="btn btn-outline-secondary btn-sm">{{ $voucher->is_active ? 'Pause' : 'Activate' }}</button></form>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="6" class="text-center text-muted py-4">No vouchers yet.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
          {{ $vouchers->links() }}
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
