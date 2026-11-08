@extends('layouts.app')
@section('content')
<style>.voucher-field-error{display:none;color:#b42318;font-size:.75rem;margin-top:.3rem}.is-invalid+.voucher-field-error{display:block}.voucher-help{border:0;background:var(--primary);color:#fff;border-radius:50%;width:18px;height:18px;font-size:.7rem;line-height:18px;padding:0;margin-left:.3rem}.voucher-help-text{display:none;background:#fff7fb;border:1px solid #fbcfe8;border-radius:8px;padding:.45rem .6rem;font-size:.75rem;margin-top:.35rem}.voucher-help-text.show{display:block}.voucher-modal.fade .modal-dialog{transform:translateY(16px) scale(.98);transition:transform .2s ease}.voucher-modal.show .modal-dialog{transform:none}@media(min-width:992px){#voucher-list-column{width:100%}.row:has(#voucher-list-column)>.col-lg-4{display:none}}@media(max-width:768px){.voucher-modal .modal-dialog{margin:.5rem}}</style>
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
          <form method="POST" action="{{ route($ctx['route_prefix'].'.vouchers.store') }}" id="voucher-form" novalidate>
            @csrf
            <div class="mb-2"><label class="form-label small fw-semibold">Code <span class="text-danger">*</span></label><div class="input-group"><input name="code" id="voucher-code" class="form-control text-uppercase" maxlength="40" required placeholder="BERRY100"><button class="btn btn-outline-secondary" type="button" id="voucher-generate">Generate</button></div><div class="voucher-field-error"></div></div>
            <div class="mb-2"><label class="form-label small fw-semibold">Name</label><input name="name" class="form-control" required placeholder="PHP 100 off"></div>
            <div class="mb-2"><label class="form-label small fw-semibold">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            <div class="row g-2">
              <div class="col-6"><label class="form-label small fw-semibold">Type</label><select name="discount_type" id="voucher-type" class="form-select"><option value="fixed">Fixed</option><option value="percent">Percent</option></select></div>
              <div class="col-6"><label class="form-label small fw-semibold">Value</label><input type="number" step="0.01" min="0.01" max="100000" name="discount_value" class="form-control" required></div>
              <div class="col-6" id="max-discount-wrap"><label class="form-label small fw-semibold">Max Discount</label><input type="number" step="0.01" min="0" name="max_discount" class="form-control"></div>
              <div class="col-6"><label class="form-label small fw-semibold">Min Order</label><input type="number" step="0.01" min="0" name="minimum_order_amount" class="form-control" value="0"></div>
              <div class="col-6"><label class="form-label small fw-semibold">Total Limit</label><input type="number" min="1" max="100000" name="usage_limit" class="form-control"></div>
              <div class="col-6"><label class="form-label small fw-semibold">Per Customer</label><input type="number" min="1" max="1000" name="per_customer_limit" class="form-control" value="1"></div>
            </div>
            <div class="mt-2"><label class="form-label small fw-semibold">Audience</label><select name="audience" id="voucher-audience" class="form-select"><option value="public">Public</option><option value="assigned">Assigned customer only</option></select></div>
            <div class="mt-2 d-none" id="customer-wrap"><label class="form-label small fw-semibold">Customer for Assigned Voucher</label><input id="voucher-customer" class="form-control" autocomplete="off" placeholder="Search name, email, or phone"><input type="hidden" name="customer_identifier" id="voucher-customer-id"><div id="voucher-customer-results" class="list-group mt-1"></div></div>
            <div class="row g-2 mt-1">
              <div class="col-6"><label class="form-label small fw-semibold">Starts</label><input type="datetime-local" name="starts_at" class="form-control voucher-date"></div>
              <div class="col-6"><label class="form-label small fw-semibold">Ends</label><input type="datetime-local" name="ends_at" class="form-control voucher-date"></div>
            </div>
            <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="requires_verified_customer" value="1" id="requiresVerified"><label class="form-check-label small" for="requiresVerified">Verified customers only</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="first_order_only" value="1" id="firstOrderOnly"><label class="form-check-label small" for="firstOrderOnly">First order only</label></div>
            <details class="mt-2"><summary class="small fw-semibold">Advanced discount rules</summary><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="stack_with_product_discount" value="1" id="stackDiscount"><label class="form-check-label small" for="stackDiscount">Allow stacking with product discounts</label><div class="form-text">May reduce your margin when the product is already on sale.</div></div></details>
            <div class="small rounded-3 p-2 mt-3" id="voucher-preview" style="background:var(--primary-bg)">Complete the required fields to preview this voucher.</div><button class="btn btn-primary w-100 mt-3" id="voucher-submit"><i class="bi bi-plus-circle me-1"></i>Create Voucher</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-8" id="voucher-list-column">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><h6 class="fw-bold mb-0">Voucher List</h6><button class="btn btn-primary btn-sm" type="button" id="open-voucher-modal"><i class="bi bi-plus-lg me-1"></i>Create Voucher</button></div>
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
<script>
(() => { const f=document.getElementById('voucher-form'); if(!f)return; const type=f.querySelector('#voucher-type'),aud=f.querySelector('#voucher-audience'),max=document.getElementById('max-discount-wrap'),cust=document.getElementById('customer-wrap'),code=document.getElementById('voucher-code'),gen=document.getElementById('voucher-generate'),preview=document.getElementById('voucher-preview'),submit=document.getElementById('voucher-submit'),dates=[...f.querySelectorAll('.voucher-date')]; const now=new Date(),limit=new Date(now);limit.setFullYear(limit.getFullYear()+1); const fmt=d=>d.toISOString().slice(0,16); dates.forEach(x=>{x.min=fmt(now);x.max=fmt(limit)}); const sync=()=>{const percent=type.value==='percent', assigned=aud.value==='assigned',value=f.discount_value.value||'0',min=f.minimum_order_amount.value||'0';max.classList.toggle('d-none',!percent); max.querySelector('input').required=percent; cust.classList.toggle('d-none',!assigned); f.customer_identifier.required=assigned; preview.textContent=`${percent?value+'% off':'₱'+value+' off'} · minimum order ₱${min} · ${assigned?'assigned customer':'public'}`}; gen.onclick=()=>{code.value='SHOP'+Math.random().toString(36).slice(2,8).toUpperCase();code.dispatchEvent(new Event('input'))}; f.addEventListener('input',sync); f.addEventListener('change',sync); f.addEventListener('submit',()=>{submit.disabled=true;submit.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Creating…'}); sync(); })();
</script>
<script>
(() => {const f=document.getElementById('voucher-form');if(!f)return;const btn=document.getElementById('voucher-submit'),fields=[...f.querySelectorAll('input,select,textarea')];fields.forEach(x=>{if(!x.closest('.form-check')&&!x.parentElement.querySelector('.voucher-field-error'))x.insertAdjacentHTML('afterend','<div class="voucher-field-error" role="alert"></div>')});const msg=x=>{if(x.name==='starts_at'||x.name==='ends_at')return 'Choose a date from now until one year ahead.';if(x.name==='discount_value')return 'Enter a valid discount amount (percent cannot exceed 100%).';if(x.name==='usage_limit'||x.name==='per_customer_limit')return 'Enter a whole number within the allowed limit.';return 'Complete this field with a valid value.'};const validate=()=>{let ok=true;fields.forEach(x=>{if(x.disabled||x.type==='checkbox')return;let bad=x.required&&!x.value||!x.checkValidity();if(x.name==='discount_value'&&f.discount_type.value==='percent'&&Number(x.value)>100)bad=true;if(x.name==='ends_at'&&f.starts_at.value&&x.value&&x.value<f.starts_at.value)bad=true;x.classList.toggle('is-invalid',bad);let e=x.parentElement.querySelector('.voucher-field-error');if(e)e.textContent=bad?msg(x):'';if(bad)ok=false});btn.disabled=!ok;return ok};f.addEventListener('input',validate);f.addEventListener('change',validate);f.addEventListener('submit',ev=>{if(!validate()){ev.preventDefault();f.querySelector('.is-invalid')?.focus();}});validate();})();
</script>
<script>(()=>{const help={"Total Limit":"Leave blank for unlimited uses. Enter at least 1 to cap total redemptions.","Per Customer":"Leave blank for no per-customer limit. Enter at least 1 to limit each customer.","Min Order":"Enter 0 for no minimum order amount.","Max Discount":"Required for percent vouchers to cap the peso discount."};document.querySelectorAll('#voucher-form label').forEach(l=>{const t=l.textContent.trim();if(!help[t])return;const b=document.createElement('button');b.type='button';b.className='voucher-help';b.textContent='?';b.setAttribute('aria-label','Help: '+t);const d=document.createElement('div');d.className='voucher-help-text';d.textContent=help[t];b.onclick=()=>d.classList.toggle('show');l.append(b);l.parentElement.append(d)});})();</script>
<script>(()=>{const form=document.getElementById('voucher-form'),btn=document.getElementById('open-voucher-modal');if(!form||!btn)return;const card=form.closest('.card');const modal=document.createElement('div');modal.className='modal fade voucher-modal';modal.tabIndex=-1;modal.innerHTML='<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Create Voucher</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"></div></div></div>';modal.querySelector('.modal-body').appendChild(card);document.body.appendChild(modal);btn.onclick=()=>bootstrap.Modal.getOrCreateInstance(modal).show();})();</script>
@endsection
