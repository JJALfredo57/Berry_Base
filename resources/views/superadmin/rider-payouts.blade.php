@extends('layouts.app')
@section('page_title', 'Rider Payouts')

@section('content')
<div class="cs-page-header"><div><h4 class="cs-page-title"><i class="bi bi-bicycle me-2" style="color:var(--primary)"></i>Rider Payouts</h4><p class="cs-page-sub">Delivery-fee transfers only. Seller cake payouts are listed separately.</p></div></div>
<nav class="rp-nav" aria-label="Finance and payout pages">
  <a href="{{ route('superadmin.payouts') }}">Finance overview</a>
  <a href="{{ route('superadmin.payouts.sellers') }}">Seller Payouts @if($payoutAttention['seller'])<span class="rp-dot" aria-label="{{ $payoutAttention['seller'] }} seller actions needed">{{ $payoutAttention['seller'] > 9 ? '9+' : $payoutAttention['seller'] }}</span>@endif</a>
  <a class="is-current" href="{{ route('superadmin.payouts.riders') }}">Rider Payouts @if($payoutAttention['rider'])<span class="rp-dot" aria-label="{{ $payoutAttention['rider'] }} rider actions needed">{{ $payoutAttention['rider'] > 9 ? '9+' : $payoutAttention['rider'] }}</span>@endif</a>
</nav>
@if(session('msg'))<div class="alert alert-success">{{ session('msg') }}</div>@endif
@if(session('err'))<div class="alert alert-danger">{{ session('err') }}</div>@endif
@foreach($errors->all() as $error)<div class="alert alert-danger">{{ $error }}</div>@endforeach
<section class="rp-filter" aria-label="Rider payout filters">
  <div><strong>Filter rider payouts</strong><small>Search and filters update these totals and the list. Tap a total to filter by status. Attention dots remain until requests are paid.</small></div>
  <form method="GET" action="{{ route('superadmin.payouts.riders') }}">
    <label>Search<input class="form-control" name="search" value="{{ $search }}" maxlength="120" placeholder="Rider, phone, GCash, reference or ID"></label>
    <label>Rider<select class="form-select" name="rider"><option value="">All riders</option>@foreach($riders as $rider)<option value="{{ $rider->id }}" @selected($riderId===$rider->id)>{{ $rider->name }} · {{ $rider->shop_name ?: 'No shop' }} (#{{ $rider->id }})</option>@endforeach</select></label>
    <label>Status<select class="form-select" name="status"><option value="">All statuses</option><option value="requested" @selected($status==='requested')>Requested</option><option value="paid" @selected($status==='paid')>Paid</option></select></label>
    <label>From<input class="form-control" type="date" name="from" value="{{ $from }}"></label>
    <label>To<input class="form-control" type="date" name="to" value="{{ $to }}"></label>
    <label>Sort<select class="form-select" name="sort"><option value="newest" @selected($sort==='newest')>Newest</option><option value="oldest" @selected($sort==='oldest')>Oldest</option><option value="amount_high" @selected($sort==='amount_high')>Amount high</option><option value="amount_low" @selected($sort==='amount_low')>Amount low</option></select></label>
    <label>Rows<select class="form-select" name="per_page">@foreach([10,25,50] as $size)<option value="{{ $size }}" @selected($perPage===$size)>{{ $size }} per page</option>@endforeach</select></label>
    <button class="btn btn-primary" type="submit">Apply Filters</button><a class="btn btn-outline-secondary" href="{{ route('superadmin.payouts.riders') }}">Clear</a>
  </form>
  <div class="rp-periods">@foreach(['all'=>'All Time','today'=>'Today','week'=>'This Week','month'=>'This Month'] as $key=>$label)<a class="btn btn-sm {{ $period===$key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('superadmin.payouts.riders',array_merge(request()->except(['from','to','page']),['period'=>$key])) }}">{{ $label }}</a>@endforeach</div>
</section>
<section class="rp-totals" aria-label="Filtered rider payout totals">
  @foreach(['requested'=>'Requested','paid'=>'Paid'] as $key=>$label)
    <a class="{{ $status===$key ? 'is-current' : '' }}" href="{{ route('superadmin.payouts.riders',array_merge(request()->except('page'),['status'=>$status===$key ? '' : $key])) }}"><strong>₱{{ number_format((float)($totals->get($key)->amount ?? 0),2) }}</strong><span>{{ $label }} · {{ (int)($totals->get($key)->count ?? 0) }}</span></a>
  @endforeach
</section>
<section class="rp-list" aria-label="Rider payout records"><h5>Rider payout requests <small>{{ $records->total() }} matching</small></h5>
  @forelse($records as $record)
  <article class="apd-item {{ $record->status==='requested' ? 'is-action-required' : '' }}">
    <div class="apd-main"><strong>{{ $record->rider_name }}</strong><small>#R{{ $record->id }} · Delivery-fee payout</small></div>
    <div class="apd-side"><span class="badge text-bg-{{ $record->status==='paid' ? 'success' : 'warning' }}">{{ ucfirst($record->status) }}</span><span class="apd-amount">₱{{ number_format($record->amount,2) }}</span><button type="button" class="btn btn-outline-primary btn-sm" data-payout-detail="rider-payout-{{ $record->id }}" data-payout-title="Rider payout #R{{ $record->id }}">View details</button></div>
  </article>
  <template id="rider-payout-{{ $record->id }}">
    <div class="apd-details">
      <div class="apd-field"><small>Rider</small><strong>{{ $record->rider_name }}</strong></div>
      <div class="apd-field"><small>Request / status</small>#R{{ $record->id }} · {{ ucfirst($record->status) }}<div class="apd-help">{{ \Carbon\Carbon::parse($record->created_at)->format('M d, Y h:i A') }}</div></div>
      <div class="apd-field"><small>Delivery fee to transfer</small><strong>₱{{ number_format($record->amount, 2) }}</strong></div>
      <div class="apd-field"><small>Send payout to</small>GCash<div>{{ $record->gcash_name ?: 'Account name missing' }}</div><div>{{ $record->gcash_number ?: 'Account number missing' }}</div></div>
      <div class="apd-field"><small>Transfer reference</small>{{ $record->reference_number ?: 'Not yet recorded' }}</div>
      <div class="apd-field"><small>Receipt proof</small>@if($record->receipt_path)<a href="{{ route('superadmin.rider_payouts.receipt_download', $record->id) }}">Download receipt</a>@else<span class="apd-help">Pending receipt</span>@endif</div>
    </div>
    <div class="apd-actions">
      @if($record->status==='requested')
        <form method="POST" enctype="multipart/form-data" action="{{ route('superadmin.rider_payouts.mark_paid', $record->id) }}" class="rider-payout-pay" data-prevent-double-submit>
          @csrf<strong>Transfer receipt</strong><small class="apd-help">Verify the recipient and amount before uploading the GCash transfer receipt.</small>
          <input class="rider-payout-receipt form-control" data-scan-url="{{ route('superadmin.rider_payouts.scan_receipt', $record->id) }}" name="receipt" type="file" accept="image/jpeg,image/png,image/webp" required>
          <small class="rider-payout-scan-message" aria-live="polite">Upload a GCash receipt to verify the transfer.</small>
          <input class="rider-payout-reference form-control" name="reference_number" placeholder="Scanned reference" readonly required>
          <button class="btn btn-success" type="submit" disabled>Mark Paid</button>
        </form>
      @endif
    </div>
  </template>
  @empty<div class="rp-empty">No rider payout records match these filters.</div>@endforelse
  <div class="rp-pages">{{ $records->links() }}</div>
</section>
<style>
.rp-nav{display:flex;flex-wrap:wrap;gap:.6rem;margin-bottom:1rem}.rp-nav a{min-width:0;display:inline-flex;align-items:center;gap:.5rem;padding:.65rem .85rem;border:1px solid #e5e7eb;border-radius:12px;background:#fff;color:#55301d;font-weight:750;text-decoration:none;overflow-wrap:anywhere}.rp-nav a:hover,.rp-nav a.is-current,.rp-totals a:hover,.rp-totals a.is-current{border-color:var(--primary);background:#fff7ed;box-shadow:0 0 0 3px rgba(135,63,25,.16)}.rp-dot{display:inline-grid;place-items:center;min-width:1.2rem;height:1.2rem;padding:0 .24rem;border-radius:999px;background:#dc2626;color:#fff;font-size:.68rem;box-shadow:0 0 0 3px #fee2e2}.rp-filter,.rp-list,.rp-recovery{min-width:0;max-width:100%;padding:1rem;border:1px solid #e5e7eb;border-radius:16px;background:#fff;margin-bottom:1rem}.rp-filter>div:first-child{display:grid;gap:.15rem;margin-bottom:.8rem}.rp-filter small,.rp-list h5 small{color:#64748b;font-size:.78rem}.rp-filter form{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,175px),1fr));gap:.7rem;align-items:end}.rp-filter label{display:grid;gap:.25rem;min-width:0;font-size:.76rem;font-weight:700;color:#475569}.rp-filter input,.rp-filter select{width:100%;min-width:0;min-height:42px}.rp-periods{display:flex;gap:.45rem;flex-wrap:wrap;margin-top:.8rem}.rp-totals{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,175px),1fr));gap:.7rem;margin-bottom:1rem}.rp-totals a{min-width:0;display:grid;gap:.15rem;padding:1rem;border:1px solid #e5e7eb;border-radius:14px;background:#fff;text-decoration:none;color:#44291a;overflow-wrap:anywhere}.rp-totals strong{font-size:1.2rem}.rp-totals span{color:#64748b;font-size:.78rem}.rp-list h5,.rp-recovery h5{font-weight:800}.rp-record{display:grid;grid-template-columns:repeat(4,minmax(0,1fr)) minmax(185px,1.4fr);gap:.75rem;align-items:start;padding:1rem 0;border-top:1px solid #e5e7eb;min-width:0}.rp-record>div{min-width:0;overflow-wrap:anywhere}.rp-label{display:block;font-size:.68rem;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.03em}.rp-sub{font-size:.78rem;color:#64748b;overflow-wrap:anywhere}.rp-actions form{display:grid;gap:.4rem;min-width:0}.rp-actions .form-control{min-width:0}.rp-actions button{justify-self:start}.rp-recovery{border-color:#fed7aa;background:#fffaf5}.rp-recovery article{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.8rem;padding:.8rem 0;border-top:1px solid #fed7aa;overflow-wrap:anywhere}.rp-pages{padding-top:1rem}.rp-empty{text-align:center;color:#64748b;padding:2rem}.rp-list,.rp-recovery{overflow-wrap:anywhere}@media(max-width:1024px){.rp-record{grid-template-columns:repeat(2,minmax(0,1fr))}.rp-actions{grid-column:1/-1}}@media(max-width:425px){.rp-nav a{flex:1 1 100%}.rp-record{grid-template-columns:1fr}.rp-actions{grid-column:auto}.rp-actions button,.rp-recovery button{width:100%;min-height:44px}.rp-periods{flex-wrap:nowrap;overflow-x:auto;padding-bottom:.2rem}.rp-periods a{flex:0 0 auto}}
</style>
@push('scripts')<script>document.addEventListener('change',async event=>{const input=event.target.closest('.rider-payout-receipt');if(!input)return;const form=input.closest('.rider-payout-pay'),message=form.querySelector('.rider-payout-scan-message'),reference=form.querySelector('.rider-payout-reference'),button=form.querySelector('button[type="submit"]');reference.value='';button.disabled=true;if(!input.files[0])return;if(input.files[0].size>5*1024*1024){message.textContent='Receipt must be 5 MB or smaller.';message.className='rider-payout-scan-message text-danger';return}message.textContent='Scanning receipt…';const data=new FormData();data.append('receipt',input.files[0]);try{const response=await fetch(input.dataset.scanUrl,{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||'','Accept':'application/json'},body:data}),result=await response.json();if(!response.ok||!result.ok){message.textContent=(result.errors||['Receipt verification failed.']).join(' ');message.className='rider-payout-scan-message text-danger';return}reference.value=result.reference_number;button.disabled=false;message.textContent='Receipt verified; reference scanned automatically.';message.className='rider-payout-scan-message text-success'}catch(_){message.textContent='Receipt scan failed. Try again.';message.className='rider-payout-scan-message text-danger'}});</script>@endpush
@include('superadmin.partials.payout-request-dialog')
@endsection
