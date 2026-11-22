@extends('layouts.app')
@section('page_title', $page === 'seller' ? 'Seller Payouts' : 'Finance & Payouts')

@section('content')
<div class="cs-page-header">
  <div>
    <h4 class="cs-page-title"><i class="bi bi-cash-stack me-2" style="color:var(--primary)"></i>{{ $page === 'seller' ? 'Seller Payouts' : 'Finance & Payouts' }}</h4>
    <p class="cs-page-sub">{{ $page === 'seller' ? 'Review seller balances, GCash details, requests, and transfer history.' : 'Review customer payments, reconciliation, and platform commission.' }}</p>
  </div>
</div>

<nav class="admin-payout-nav" aria-label="Finance and payout pages">
  <a class="{{ $page === 'hub' ? 'is-current' : '' }}" href="{{ route('superadmin.payouts') }}">Finance overview</a>
  <a class="{{ $page === 'seller' ? 'is-current' : '' }}" href="{{ route('superadmin.payouts.sellers') }}">Seller Payouts @if($payoutAttention['seller'])<span class="admin-payout-dot" aria-label="{{ $payoutAttention['seller'] }} seller actions needed">{{ $payoutAttention['seller'] > 9 ? '9+' : $payoutAttention['seller'] }}</span>@endif</a>
  <a href="{{ route('superadmin.payouts.riders') }}">Rider Payouts @if($payoutAttention['rider'])<span class="admin-payout-dot" aria-label="{{ $payoutAttention['rider'] }} rider actions needed">{{ $payoutAttention['rider'] > 9 ? '9+' : $payoutAttention['rider'] }}</span>@endif</a>
</nav>
<style>.admin-payout-nav{display:flex;flex-wrap:wrap;gap:.6rem;margin-bottom:1rem}.admin-payout-nav a{min-width:0;display:inline-flex;align-items:center;gap:.5rem;padding:.65rem .85rem;border:1px solid #e5e7eb;border-radius:12px;background:#fff;color:#55301d;font-weight:750;text-decoration:none;overflow-wrap:anywhere}.admin-payout-nav a:hover,.admin-payout-nav a.is-current{border-color:var(--primary);background:#fff7ed;box-shadow:0 0 0 2px rgba(135,63,25,.12)}.admin-payout-dot{display:inline-grid;place-items:center;min-width:1.2rem;height:1.2rem;padding:0 .24rem;border-radius:999px;background:#dc2626;color:#fff;font-size:.68rem;box-shadow:0 0 0 3px #fee2e2}@media(max-width:425px){.admin-payout-nav a{flex:1 1 100%}}</style>

@if(session('msg'))
  <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>{{ session('msg') }}</div>
@endif

@push('scripts')<script>document.addEventListener('change',async e=>{const i=e.target.closest('.rider-payout-receipt');if(!i)return;const f=i.closest('.rider-payout-pay'),m=f.querySelector('.rider-payout-scan-message'),r=f.querySelector('.rider-payout-reference'),b=f.querySelector('button[type="submit"]');r.value='';b.disabled=true;if(!i.files[0])return;m.textContent='Scanning receipt…';const fd=new FormData();fd.append('receipt',i.files[0]);try{const x=await fetch(i.dataset.scanUrl,{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||'','Accept':'application/json'},body:fd}),d=await x.json();if(!x.ok||!d.ok){m.textContent=(d.errors||['Receipt verification failed.']).join(' ');m.className='small rider-payout-scan-message text-danger';return}r.value=d.reference_number;b.disabled=false;m.textContent='Receipt verified; reference scanned automatically.';m.className='small rider-payout-scan-message text-success'}catch(_){m.textContent='Receipt scan failed. Try again.';m.className='small rider-payout-scan-message text-danger'}});</script>@endpush
@if(session('err'))
  <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i>{{ session('err') }}</div>
@endif
@foreach($errors->all() as $e)
  <div class="alert alert-danger">{{ $e }}</div>
@endforeach

@if($page === 'hub')
<section class="finance-date-filter" aria-label="Finance reporting period">
  <div><strong>Reporting period</strong><small>Date filters affect finance activity lists. Seller and rider payout filters are on their separate pages.</small></div>
  <form method="GET" class="finance-date-form">
    <input type="hidden" name="period" value="">
    <label>From<input class="form-control" type="date" name="from" value="{{ $from }}"></label>
    <label>To<input class="form-control" type="date" name="to" value="{{ $to }}"></label>
    <button class="btn btn-primary" type="submit">Apply Filters</button><a class="btn btn-outline-secondary" href="{{ route('superadmin.payouts') }}">Clear</a>
  </form>
  <div class="finance-periods" aria-label="Quick date filters">
    @foreach(['all'=>'All Time','today'=>'Today','week'=>'This Week','month'=>'This Month','last_month'=>'Last Month','year'=>'This Year','last30'=>'Last 30 days'] as $key=>$label)
      <a class="btn btn-sm {{ $period === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('superadmin.payouts', array_merge(request()->except(['from','to','shops_page','requests_page','payouts_page']), ['period'=>$key])) }}">{{ $label }}</a>
    @endforeach
  </div>
</section>

@endif
<style>.finance-date-filter{display:flex;align-items:end;gap:1rem;flex-wrap:wrap;padding:1rem;margin-bottom:1rem;border:1px solid var(--gray-100);border-radius:16px;background:#fff}.finance-date-filter>div:first-child{display:grid;gap:.15rem;margin-right:auto}.finance-date-filter small{color:var(--gray-500);font-size:.78rem}.finance-date-form{display:flex;gap:.7rem;flex-wrap:wrap}.finance-date-form label{display:grid;gap:.25rem;font-size:.76rem;font-weight:700;color:var(--gray-600)}.finance-date-form input{min-width:148px;min-height:42px}.finance-periods{display:flex;gap:.45rem;flex-wrap:wrap;width:100%}.finance-periods .btn{min-height:38px;white-space:nowrap}@media(max-width:575px){.finance-date-filter{align-items:stretch}.finance-date-filter>div:first-child{margin-right:0}.finance-date-form{width:100%}.finance-date-form label{flex:1 1 140px}.finance-date-form input{width:100%;min-width:0}.finance-periods{flex-wrap:nowrap;overflow:auto;padding-bottom:2px}.finance-periods .btn{flex:0 0 auto}}</style>

@if($page === 'hub')
<div class="alert alert-info d-flex gap-3 align-items-start">
  <i class="bi bi-info-circle-fill fs-5"></i>
  <div>
    <strong>How money moves:</strong> Verified online payments appear in the seller balance immediately, less the assigned seller commission on the full cake amount and any online-funded rider fee. Only paid, Delivered/Picked Up orders without active issues are selectable. COD cash and rider-retained delivery fees are excluded. There are no waiting days or minimum payout. A request becomes paid only with a verified transfer receipt. Re-check eligibility before sending money; cancel blocked, untransferred requests to reconcile their funds.
  </div>
</div>

<style>.finance-groups{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;margin-bottom:1.5rem}.finance-group{border:1px solid var(--gray-100,#e5e7eb);border-radius:16px;background:#fff;overflow:hidden}.finance-group summary{list-style:none;cursor:pointer;padding:1rem;display:flex;justify-content:space-between;gap:.75rem;align-items:center}.finance-group summary::-webkit-details-marker{display:none}.finance-group summary:hover{background:#f8fafc}.finance-amount{font-size:1.35rem;font-weight:800}.finance-details{display:grid;gap:.5rem;padding:0 1rem 1rem}.finance-line{display:flex;justify-content:space-between;gap:.75rem;font-size:.85rem;border-top:1px solid #f1f5f9;padding-top:.5rem}.finance-line strong{white-space:nowrap}.finance-review-alert{border-left:4px solid #b45309}.finance-audit-table{min-width:1040px}.finance-state{font-size:.75rem;font-weight:700}@media(max-width:900px){.finance-groups{grid-template-columns:1fr}}@media(prefers-reduced-motion:no-preference){.finance-group[open] .finance-details{animation:financeIn .2s ease-out}@keyframes financeIn{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:none}}}</style>
<div class="finance-groups">
<details class="finance-group"><summary><div><div class="small text-muted">Customer funds</div><div class="finance-amount text-primary">₱{{ number_format($paymentSummary['online_amount'],2) }}</div><small>GCash order payments</small></div><i class="bi bi-chevron-down"></i></summary><div class="finance-details"><div class="finance-line"><span>Customer-paid service fee</span><strong>₱{{ number_format($paymentSummary['service_fees'],2) }}</strong></div><div class="finance-line"><span>Customer checkout total</span><strong>₱{{ number_format($paymentSummary['customer_charged'],2) }}</strong></div><div class="finance-line"><span>Refunds sent</span><strong>−₱{{ number_format($paymentSummary['refunds_sent'],2) }}</strong></div></div></details>
<details class="finance-group"><summary><div><div class="small text-muted">Seller funds in process</div><div class="finance-amount text-success">₱{{ number_format($summary['awaiting']+$summary['available'],2) }}</div><small>Awaiting completion + ready payout</small></div><i class="bi bi-chevron-down"></i></summary><div class="finance-details"><div class="finance-line"><span>Awaiting Delivery/Pickup</span><strong>₱{{ number_format($summary['awaiting'],2) }}</strong></div><div class="finance-line"><span>Ready for seller payout</span><strong>₱{{ number_format($summary['available'],2) }}</strong></div><div class="finance-line"><span>Refund reserved</span><strong>₱{{ number_format($summary['reserved'],2) }}</strong></div><div class="finance-line"><span>Transfer pending</span><strong>₱{{ number_format($summary['processing'],2) }}</strong></div><div class="finance-line"><span>Paid to sellers</span><strong>₱{{ number_format($summary['paid'],2) }}</strong></div></div></details>
<details class="finance-group" open><summary><div><div class="small text-muted">Verified platform commission</div><div class="finance-amount" style="color:#7c3aed">₱{{ number_format($paymentSummary['verified_platform_commission'],2) }}</div><small>Only fully matched orders</small></div><i class="bi bi-chevron-down"></i></summary><div class="finance-details"><div class="finance-line"><span>Seller funds awaiting release</span><strong>₱{{ number_format($paymentSummary['seller_payable'],2) }}</strong></div><div class="finance-line"><span>Rider funds awaiting release</span><strong>₱{{ number_format($paymentSummary['rider_payable'],2) }}</strong></div><div class="finance-line"><span>Paid to sellers</span><strong>₱{{ number_format($paymentSummary['seller_paid'],2) }}</strong></div><div class="finance-line"><span>Rider fees paid</span><strong>₱{{ number_format($paymentSummary['rider_paid'],2) }}</strong></div><div class="finance-line"><span>Recorded commission</span><strong>₱{{ number_format($paymentSummary['commission_recorded'],2) }}</strong></div></div></details>
</div>
@if($paymentSummary['review_count'] > 0)
  <div class="alert alert-warning finance-review-alert d-flex gap-2 align-items-start" role="alert"><i class="bi bi-shield-exclamation fs-5"></i><div><strong>Funds are blocked from platform use.</strong> {{ $paymentSummary['review_count'] }} order{{ $paymentSummary['review_count'] === 1 ? '' : 's' }} have a payment difference or missing finance evidence. This amount is not commission, cannot be paid out, and appears in the audit below.</div></div>
@endif

<div class="card mb-4">
  <button class="card-header finance-table-toggle w-100 border-0 text-start" type="button" data-finance-panel="audit" aria-expanded="false" aria-controls="finance-panel-audit"><span><i class="bi bi-clipboard2-data me-2" style="color:var(--primary)"></i>Order Reconciliation Audit</span><span class="small text-muted">Every net GCash settlement must be fully allocated before it is usable.</span><i class="bi bi-chevron-down finance-table-chevron" aria-hidden="true"></i></button>
  <div class="finance-table-panel" id="finance-panel-audit" data-finance-content="audit" hidden><div class="table-responsive"><table class="table align-middle mb-0 finance-audit-table"><thead><tr><th>Order</th><th>Order state</th><th class="text-end">Net GCash</th><th class="text-end">Platform-funded credit</th><th class="text-end">Refund</th><th class="text-end">Seller</th><th class="text-end">Rider</th><th class="text-end">Commission</th><th class="text-end">Unallocated</th><th class="text-end">Difference</th><th>Result</th></tr></thead><tbody>
  @forelse($financeReconciliation['rows']->take(100) as $audit)
    @php $needsReview = $audit->state === 'needs_review'; @endphp
    <tr class="{{ $needsReview ? 'table-warning' : '' }}"><td class="fw-semibold">#{{ $audit->order_id }}</td><td>{{ $audit->order_status }}</td><td class="text-end">₱{{ number_format($audit->online_settled,2) }}</td><td class="text-end">₱{{ number_format($audit->platform_funding,2) }}</td><td class="text-end">₱{{ number_format($audit->refunds,2) }}</td><td class="text-end">₱{{ number_format($audit->seller_amount,2) }}</td><td class="text-end">₱{{ number_format($audit->rider_amount,2) }}</td><td class="text-end">₱{{ number_format($audit->commission,2) }}</td><td class="text-end">₱{{ number_format($audit->held,2) }}</td><td class="text-end fw-semibold {{ abs($audit->difference) >= .01 ? 'text-danger' : 'text-success' }}">{{ $audit->difference > 0 ? '+' : '' }}₱{{ number_format($audit->difference,2) }}</td><td><span class="finance-state badge text-bg-{{ $audit->state === 'reconciled' ? 'success' : ($audit->state === 'held' ? 'secondary' : 'warning') }}">{{ $audit->state === 'reconciled' ? 'Matched' : ($audit->state === 'held' ? 'Awaiting completion' : 'Needs review') }}</span>@if($audit->rider_review_amount > 0)<div class="small text-danger mt-1">Rider ₱{{ number_format($audit->rider_review_amount,2) }} needs review</div>@endif</td></tr>
  @empty
    <tr><td colspan="11" class="text-center text-muted py-4">No online payment records need reconciliation yet.</td></tr>
  @endforelse
  </tbody></table></div></div>
</div>
<div class="row g-3 mb-4 payout-summary-grid d-none">
  @foreach([
    ['All Online Payments', $paymentSummary['online_amount'], 'bi-credit-card-2-front', '#0369a1', '#f0f9ff'],
    ['Unallocated Customer Funds', $heldCustomerFunds ?? 0, 'bi-shield-lock', '#92400e', '#fffbeb'],
    ['Platform Commission Recorded', $commissionSummary['recorded'], 'bi-percent', '#7c3aed', '#f5f3ff'],
    ['Awaiting Delivery/Pickup', $summary['awaiting'], 'bi-hourglass-split', '#92400e', '#fffbeb'],
    ['Ready for Seller Payout', $summary['available'], 'bi-cash-stack', '#166534', '#ecfdf5'],
    ['Transfer Pending', $summary['processing'], 'bi-arrow-repeat', '#1d4ed8', '#eff6ff'],
    ['Paid to Sellers', $summary['paid'], 'bi-check2-circle', '#6d28d9', '#f5f3ff'],
    ['Customer Refunds Sent', $paymentSummary['refunds_sent'], 'bi-arrow-return-left', '#b45309', '#fff7ed'],
    ['Seller Recovery Pending', $summary['seller_recovery_pending'] ?? 0, 'bi-arrow-counterclockwise', '#b91c1c', '#fef2f2'],
    ['COD Remittance Pending', $riderFundSummary['pending'] ?? 0, 'bi-cash-coin', '#9a3412', '#fff7ed'],
  ] as [$label, $value, $icon, $color, $bg])
  <div class="col-6 col-xl-4 col-xxl-3">
    <div class="cs-stat-card h-100">
      <div class="cs-stat-icon" style="background:{{ $bg }}"><i class="bi {{ $icon }}" style="color:{{ $color }}"></i></div>
      <div class="cs-stat-body">
        <div class="cs-stat-num" style="color:{{ $color }}">₱{{ number_format($value, 2) }}</div>
        <div class="cs-stat-label">{{ $label }}</div>
      </div>
    </div>
  </div>
  @endforeach
</div>

<div class="card mb-4">
  <button class="card-header finance-table-toggle w-100 border-0 text-start" type="button" data-finance-panel="cash-history" aria-expanded="false" aria-controls="finance-panel-cash-history"><span><i class="bi bi-cash-coin me-2" style="color:#166534"></i>Cash Settlement History</span><span class="small text-muted">Cash on Pickup and Cash on Delivery; recorded for audit only and excluded from online payout.</span><i class="bi bi-chevron-down finance-table-chevron" aria-hidden="true"></i></button>
  <div class="finance-table-panel" id="finance-panel-cash-history" data-finance-content="cash-history" hidden><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Settled</th><th>Order</th><th>Seller</th><th>Cash transaction</th><th>Order Status</th><th class="text-end">Amount</th></tr></thead><tbody>
    @forelse($cashPayments as $payment)
      <tr><td class="small">{{ $payment->paid_at ? \Carbon\Carbon::parse($payment->paid_at)->format('M d, Y h:i A') : 'Recorded' }}</td><td class="fw-semibold">#{{ $payment->order_id }}</td><td>{{ $payment->shop_name ?? 'Shop unavailable' }}</td><td>{{ \App\Helpers\PaymentTransactionHelper::typeLabel($payment->type) }}</td><td><span class="badge text-bg-light">{{ $payment->order_status ?? 'Order unavailable' }}</span></td><td class="text-end fw-semibold">₱{{ number_format((float) $payment->amount, 2) }}</td></tr>
    @empty
      <tr><td colspan="6" class="text-center text-muted py-4">No completed cash pickup or delivery payments are recorded for this period.</td></tr>
    @endforelse
  </tbody></table></div></div>
</div>

<div class="card mb-4">
  <button class="card-header finance-table-toggle w-100 border-0 text-start" type="button" data-finance-panel="payments" aria-expanded="false" aria-controls="finance-panel-payments"><span><i class="bi bi-receipt-cutoff me-2" style="color:var(--primary)"></i>Customer Online Payments</span><span class="small text-muted">{{ number_format($paymentSummary['count']) }} confirmed GCash payment{{ $paymentSummary['count'] === 1 ? '' : 's' }} · processor fee: ₱{{ number_format($paymentSummary['service_fees'], 2) }}</span><i class="bi bi-chevron-down finance-table-chevron" aria-hidden="true"></i></button>
  <div class="finance-table-panel" id="finance-panel-payments" data-finance-content="payments" hidden><div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Paid</th><th>Order</th><th>Seller</th><th>Payment</th><th>Order Status</th><th class="text-end">Order Amount</th><th>PayMongo Reference</th></tr></thead>
      <tbody>
        @forelse($onlinePayments as $payment)
          <tr>
            <td class="small">{{ $payment->paid_at ? \Carbon\Carbon::parse($payment->paid_at)->format('M d, Y h:i A') : 'Recorded' }}</td>
            <td class="fw-semibold">#{{ $payment->order_id }}</td>
            <td>{{ $payment->shop_name ?? 'Shop unavailable' }}</td>
            <td>{{ \App\Helpers\PaymentTransactionHelper::typeLabel($payment->type) }}</td>
            <td><span class="badge text-bg-light">{{ $payment->order_status ?? 'Order unavailable' }}</span></td>
            <td class="text-end fw-semibold">₱{{ number_format((float) $payment->amount, 2) }}</td>
            <td class="small text-muted">{{ $payment->provider_reference ?: 'Reference unavailable' }}</td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No confirmed GCash payments are recorded yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div></div>
</div>

<div class="card mb-4">
  <button class="card-header finance-table-toggle w-100 border-0 text-start" type="button" data-finance-panel="commission" aria-expanded="false" aria-controls="finance-panel-commission"><span><i class="bi bi-percent me-2" style="color:var(--primary)"></i>Platform Commission Ledger</span><span class="small text-muted">Assigned seller rate × full cake/product value · direct-cash commission due: ₱{{ number_format($commissionSummary['cash_due'], 2) }}</span><i class="bi bi-chevron-down finance-table-chevron" aria-hidden="true"></i></button>
  <div class="finance-table-panel" id="finance-panel-commission" data-finance-content="commission" hidden><div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Order</th><th>Seller</th><th>Settlement State</th><th class="text-end">GCash Paid</th><th class="text-end">Cash Paid</th><th class="text-end">Cake Amount</th><th class="text-end">Commission</th><th class="text-end">Seller Earnings</th></tr></thead>
      <tbody>
        @forelse($commissionRows as $ledger)
          <tr>
            <td class="fw-semibold">#{{ $ledger->order_id }}</td>
            <td>{{ $ledger->shop_name ?? 'Shop unavailable' }}</td>
            <td><span class="badge text-bg-{{ $ledger->status === 'paid' ? 'success' : ($ledger->status === 'cash_settled' ? 'warning' : 'light') }}">{{ $ledger->status === 'cash_settled' ? 'Seller cash; commission due' : ucfirst(str_replace('_', ' ', $ledger->status)) }}</span></td>
            <td class="text-end">₱{{ number_format((float) ($ledger->online_paid ?? 0), 2) }}</td>
            <td class="text-end">₱{{ number_format((float) ($ledger->cash_paid ?? 0), 2) }}</td>
            <td class="text-end">₱{{ number_format((float) ($ledger->cake_amount ?? $ledger->commission_base), 2) }}</td>
            <td class="text-end fw-semibold">₱{{ number_format((float) $ledger->commission_amount, 2) }}</td>
            <td class="text-end">₱{{ number_format((float) $ledger->seller_net_amount, 2) }}</td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted py-4">Commission allocation is recorded when online payment is verified; the seller rate is preserved.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div></div>
</div>

@endif

@if($page === 'seller')
<section class="finance-date-filter" aria-label="Seller payout filters">
  <div><strong>Filter seller payouts</strong><small>Search and filters update the list and totals. Tap a total to filter by status. Attention dots remain until each action is resolved.</small></div>
  <form method="GET" action="{{ route('superadmin.payouts.sellers') }}" class="finance-date-form">
    <input type="hidden" name="period" value="">
    <label>From<input class="form-control" type="date" name="from" value="{{ $from }}"></label>
    <label>To<input class="form-control" type="date" name="to" value="{{ $to }}"></label>
    <label>Seller / order / reference<input class="form-control" name="search" value="{{ $search }}" maxlength="120"></label>
    <label>Seller / shop<select class="form-select" name="shop"><option value="">All sellers / shops</option>@foreach($shopOptions as $option)<option value="{{ $option->id }}" @selected((string)$shopFilter===(string)$option->id)>{{ $option->seller_name ?: 'Seller' }} · {{ $option->shop_name }}</option>@endforeach</select></label>
    <label>Status<select class="form-select" name="payout_status"><option value="">All statuses</option>@foreach(['requested','processing','paid','cancelled'] as $value)<option value="{{ $value }}" @selected($payoutStatus===$value)>{{ ucfirst($value) }}</option>@endforeach</select></label>
    <label>Sort<select class="form-select" name="sort"><option value="newest" @selected($sort==='desc')>Newest</option><option value="oldest" @selected($sort==='asc')>Oldest</option></select></label>
    <label>Rows<select class="form-select" name="per_page">@foreach([10,25,50] as $size)<option value="{{ $size }}" @selected($perPage===$size)>{{ $size }} per page</option>@endforeach</select></label>
    <button class="btn btn-primary" type="submit">Apply Filters</button><a class="btn btn-outline-secondary" href="{{ route('superadmin.payouts.sellers') }}">Clear</a>
  </form>
  <div class="finance-periods" aria-label="Quick date filters">@foreach(['all'=>'All Time','today'=>'Today','week'=>'This Week','month'=>'This Month','last_month'=>'Last Month','year'=>'This Year','last30'=>'Last 30 days'] as $key=>$label)<a class="btn btn-sm {{ $period === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('superadmin.payouts.sellers', array_merge(request()->except(['from','to','shops_page','requests_page','payouts_page']), ['period'=>$key])) }}">{{ $label }}</a>@endforeach</div>
</section>
<section class="admin-payout-totals" aria-label="Filtered seller payout totals">
  @foreach(['requested'=>'Requested','processing'=>'Processing','paid'=>'Paid','cancelled'=>'Cancelled'] as $key=>$label)
    <a class="{{ $payoutStatus===$key ? 'is-current' : '' }}" href="{{ route('superadmin.payouts.sellers', array_merge(request()->except(['requests_page','payouts_page']), ['payout_status'=>$payoutStatus===$key ? '' : $key])) }}"><strong>₱{{ number_format((float)($sellerTotals->get($key)->amount ?? 0),2) }}</strong><span>{{ $label }} · {{ (int)($sellerTotals->get($key)->count ?? 0) }}</span></a>
  @endforeach
</section>
<style>.admin-payout-totals{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,155px),1fr));gap:.65rem;margin:1rem 0}.admin-payout-totals a{min-width:0;padding:.9rem;border:1px solid #e5e7eb;border-radius:14px;background:#fff;text-decoration:none;color:#44291a;overflow-wrap:anywhere}.admin-payout-totals a:hover,.admin-payout-totals a.is-current{border-color:var(--primary);background:#fff7ed;box-shadow:0 0 0 3px rgba(135,63,25,.16)}.admin-payout-totals strong,.admin-payout-totals span{display:block}.admin-payout-totals strong{font-size:1.15rem;font-variant-numeric:tabular-nums}.admin-payout-totals span{font-size:.76rem;color:#6b7280}.finance-date-form>*{min-width:0}.finance-date-form select{min-width:130px}.finance-date-filter,.card,.payout-table-scroll{min-width:0;max-width:100%}@media(max-width:575px){.finance-date-form .btn{flex:1 1 100%}.finance-date-form select{width:100%;min-width:0}}</style>
@endif

<div class="row g-4">
  @if($page === 'hub')
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-sliders me-2" style="color:var(--primary)"></i>Payout Settings</div>
      <div class="card-body">
        <form action="{{ route('superadmin.payouts.settings') }}" method="POST" data-prevent-double-submit>
          @csrf
          <div class="mb-3">
            <label class="form-label fw-semibold">Payout Mode</label>
            <div class="form-control bg-light d-flex align-items-center gap-2" aria-label="Payout mode: Manual, locked"><i class="bi bi-lock-fill text-muted"></i><span>Manual — admin reviews and marks paid</span></div>
            <div class="form-text">Payout mode is locked to Manual. Admin review is required before marking payout as paid.</div>
          </div><div class="alert alert-light mb-0">No waiting days or minimum payout. Verified payments are visible immediately; completed, issue-free orders can be requested.</div>
          <button class="btn btn-primary w-100 mt-4" type="submit" data-loading-text="Saving...">
            <i class="bi bi-save me-1"></i>Save Settings
          </button>
        </form>
      </div>
    </div>
  </div>
  @endif

  @if($page === 'seller')
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-shop me-2" style="color:var(--primary)"></i>Seller Balances</span>
        <span class="badge text-bg-light">Mode: Manual / Active</span>
      </div>
      <div class="table-responsive payout-table-scroll">
        <table class="table align-middle mb-0 payout-balances-table">
          <thead>
            <tr>
              <th>Seller</th>
              <th>Payout Details</th>
              <th>Awaiting Completion / Issues</th>
              <th class="text-end">Available</th>
              <th class="text-end">Processing</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse($shops as $shop)
              @php
                $minimumPayout = (float)($payoutSettings->payout_minimum_amount ?? 0);
                $payoutBlockReason = null;
                $missingPayoutDetails = empty($shop->payout_method) || empty($shop->payout_account_name) || empty($shop->payout_account_number);
                if (!empty($shop->payout_paused)) {
                  $payoutBlockReason = 'Payout is paused for this seller. Enable payouts for this shop first.';
                } elseif ($missingPayoutDetails) {
                  $payoutBlockReason = 'Seller GCash payout details are incomplete. Ask the seller to add GCash details first.';
                } elseif (empty($shop->payout_details_verified)) {
                  $payoutBlockReason = 'Seller payout details need admin verification before creating a payout.';
                } elseif ($shop->available_balance <= 0) {
                  $payoutBlockReason = 'No available balance yet. Orders must be paid, completed, and free of active issues.';
                } elseif ($shop->available_balance < $minimumPayout) {
                  $payoutBlockReason = 'Available balance is below the minimum payout amount of ₱'.number_format($minimumPayout, 2).'.';
                }
              @endphp
              <tr>
                <td>
                  <div class="fw-semibold">{{ $shop->shop_name }}</div>
                  <div class="small text-muted">{{ $shop->seller_name ?? 'Seller' }}</div>
                </td>
                <td class="small">
                  @if($shop->payout_method)
                    <div>GCash</div>
                    <div class="text-muted">{{ $shop->payout_account_name }} / {{ $shop->payout_account_number }}</div>
                    <span class="badge {{ $shop->payout_details_verified ? 'text-bg-success' : 'text-bg-warning' }}">{{ $shop->payout_details_verified ? 'Verified' : 'Needs verification' }}</span>
                  @else
                    <span class="text-muted">No payout details yet</span>
                  @endif
                </td>
                <td class="small">
                  <div class="fw-semibold">₱{{ number_format($shop->awaiting_balance ?? 0, 2) }}</div>
                  <div class="text-muted">Completes eligibility at delivery/pickup, without extra waiting days.</div>
                  @if(($shop->pending_refunds ?? 0) > 0)
                    <div class="mt-1"><span class="badge text-bg-danger">{{ $shop->pending_refunds }} pending refund{{ $shop->pending_refunds > 1 ? 's' : '' }}</span></div>
                  @endif
                  @if(($shop->refund_deductions ?? 0) > 0)
                    <div class="small text-danger mt-1">Refund deductions: ₱{{ number_format($shop->refund_deductions, 2) }}</div>
                  @endif
                </td>
                <td class="text-end fw-semibold">₱{{ number_format($shop->available_balance, 2) }}</td>
                <td class="text-end">₱{{ number_format($shop->processing_balance, 2) }}</td>
                <td class="text-end payout-row-action">
                  <div class="d-flex gap-2 justify-content-end payout-action-group">
                    @if($missingPayoutDetails)
                      <form action="{{ route('superadmin.payouts.request_seller_details', $shop->id) }}" method="POST" data-prevent-double-submit>
                        @csrf
                        <button class="btn btn-outline-primary btn-sm" type="submit" data-loading-text="Sending...">
                          <i class="bi bi-bell me-1"></i>Request Details
                        </button>
                      </form>
                    @elseif($shop->payout_method && !$shop->payout_details_verified)
                      <form action="{{ route('superadmin.payouts.verify_seller', $shop->id) }}" method="POST" data-prevent-double-submit>
                        @csrf
                        <button class="btn btn-outline-success btn-sm" type="submit">
                          <i class="bi bi-check2-circle me-1"></i>Verify
                        </button>
                      </form>
                    @elseif($payoutBlockReason)
                      <button class="btn btn-outline-secondary btn-sm" type="button" onclick="alert(@js($payoutBlockReason))">
                        Create Payout
                      </button>
                    @else
                      <form action="{{ route('superadmin.payouts.create_manual', $shop->id) }}" method="POST" data-prevent-double-submit>
                        @csrf
                        <button class="btn btn-primary btn-sm" type="submit" data-loading-text="Creating...">Create Payout</button>
                      </form>
                    @endif
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-4">No seller balances yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="p-3">{{ $shops->links() }}</div>
    </div>
  </div>
  @endif
</div>

@if(false) {{-- Legacy refund queue: refund completion belongs to Return & Refund Cases. --}}
@if(($riderPayoutRequests ?? collect())->count())<div class="card mt-4"><div class="card-header payout-section-head"><span><i class="bi bi-bicycle me-2"></i>Rider Delivery-Fee Payouts</span></div><div class="card-body">@foreach($riderPayoutRequests as $rp)<div class="border-bottom py-3"><strong>{{ $rp->rider_name }}</strong> · ₱{{ number_format($rp->amount,2) }}<div class="small text-muted">{{ $rp->gcash_name }} · {{ $rp->gcash_number }} · {{ ucfirst($rp->status) }}</div>@if($rp->status==='requested')<form method="POST" enctype="multipart/form-data" action="{{ route('superadmin.rider_payouts.mark_paid',$rp->id) }}" class="mt-2 rider-payout-pay" data-prevent-double-submit>@csrf<input class="rider-payout-receipt" data-scan-url="{{ route('superadmin.rider_payouts.scan_receipt',$rp->id) }}" name="receipt" type="file" accept="image/jpeg,image/png,image/webp" required><div class="small rider-payout-scan-message">Upload a receipt to scan the reference.</div><input class="rider-payout-reference" name="reference_number" required readonly placeholder="Scanned reference"><button class="btn btn-success btn-sm" type="submit" disabled>Mark paid</button></form>@endif</div>@endforeach</div></div>@endif
<div class="card mt-4">
  @php $refundActionCount = $refunds->whereIn('status', ['approved_for_admin_refund', 'processing'])->count(); @endphp
  <div class="card-header payout-section-head"><span><i class="bi bi-arrow-counterclockwise me-2" style="color:#b91c1c"></i>Refund Queue</span>@if($refundActionCount)<span class="payout-attention"><i class="payout-attention-dot" aria-hidden="true"></i>{{ $refundActionCount }} needs action</span>@endif</div>
  <div class="table-responsive payout-table-scroll">
    <table class="table align-middle mb-0 payout-refunds-table">
      <thead>
        <tr>
          <th>Order</th>
          <th>Seller</th>
          <th>Paid</th>
          <th>GCash</th>
          <th class="text-end">Amount</th>
          <th>Reference / Action</th>
        </tr>
      </thead>
      <tbody>
        @foreach($refunds as $r)
        <tr class="payout-detail-trigger {{ in_array($r->status, ['approved_for_admin_refund', 'processing']) ? 'is-action-required' : '' }}" tabindex="0" role="button" aria-label="Open refund #{{ $r->order_id }} details">
          <td>#{{ $r->order_id }}</td>
          <td>{{ $r->shop_name ?? 'Shop' }}</td>
          <td><span class="badge text-bg-{{ $r->status === 'refunded' ? 'success' : ($r->status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst(str_replace('_', ' ', $r->status)) }}</span></td>
          <td class="small">{{ $r->refund_gcash_name }}<br><span class="text-muted">{{ $r->refund_gcash_number }}</span></td>
          <td class="text-end fw-semibold">₱{{ number_format((float)$r->refund_amount, 2) }}</td>
          <td class="small">
            @if(in_array($r->status, ['approved_for_admin_refund', 'processing']))
              <form action="{{ route('superadmin.refunds.mark_sent', $r->id) }}" method="POST" enctype="multipart/form-data" class="refund-mark-sent-form" data-prevent-double-submit>
                @csrf
                <div class="payout-receipt-scan" data-scan-url="{{ route('superadmin.refunds.scan_receipt', $r->id) }}">
                  <input type="file" name="refund_receipt" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp" required>
                  <div class="small mt-1 payout-scan-message" aria-live="polite">Upload a GCash receipt to verify recipient, amount, and reference.</div>
                  <input name="reference_number" class="form-control form-control-sm mt-1 payout-reference" placeholder="Scanned reference number" readonly required>
                  <input name="admin_note" class="form-control form-control-sm mt-1" maxlength="500" placeholder="Internal note (optional)">
                </div>
                <button class="btn btn-success btn-sm mt-2" type="submit" disabled>Mark Refund Sent</button>
              </form>
            @else
              <span class="text-muted">{{ $r->reference_number ?: 'Pending' }}</span>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endif

@if($page === 'seller')
@if($payoutStatus === '' || in_array($payoutStatus, ['requested','processing'], true))
<div class="card mt-4">
  @php $payoutActionCount = $payoutRequests->total(); @endphp
  <button class="card-header payout-section-head finance-table-toggle w-100 border-0 text-start" type="button" data-finance-panel="requests" aria-expanded="true" aria-controls="finance-panel-requests"><span><i class="bi bi-cash-coin me-2" style="color:var(--primary)"></i>Payout Requests</span><span>@if($payoutActionCount)<span class="payout-attention"><i class="payout-attention-dot" aria-hidden="true"></i>{{ $payoutActionCount }} needs action</span>@endif <i class="bi bi-chevron-up ms-2 finance-table-chevron" aria-hidden="true"></i></span></button>
  <div class="table-responsive payout-table-scroll finance-table-panel" id="finance-panel-requests" data-finance-content="requests">
    <table class="table align-middle mb-0 payout-history-admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Recipient</th>
          <th>Type</th>
          <th>Status</th>
          <th class="text-end">Net</th>
          <th>Receipt Proof</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($payoutRequests as $p)
          <tr class="payout-detail-trigger is-action-required" tabindex="0" role="button" aria-label="Open payout #{{ $p->id }} details"
              data-shop-name="{{ $p->shop_name }}"
              data-seller-name="{{ $p->seller_name ?? 'Seller' }}"
              data-payout-method="{{ $p->payout_method ?? 'GCash' }}"
              data-payout-account-name="{{ $p->payout_account_name ?? '' }}"
              data-payout-account-number="{{ $p->payout_account_number ?? '' }}">
            <td>#{{ $p->id }}</td>
            <td><div class="fw-semibold">{{ $p->shop_name }}</div><div class="small text-muted">{{ $p->seller_name }}</div></td>
            <td>{{ ucfirst($p->mode) }}</td>
            <td><span class="badge text-bg-{{ $p->status === 'paid' ? 'success' : ($p->status === 'failed' ? 'danger' : 'warning') }}">{{ ucfirst(str_replace('_',' ', $p->status)) }}</span>@if(!empty($p->blocked_reason))<div class="small text-danger mt-1">Blocked: {{ $p->blocked_reason }}</div>@endif<details class="small mt-2"><summary>Order allocations</summary>@foreach($requestItems->get($p->id,collect()) as $item)<div>#{{ $item->order_id }} · ₱{{ number_format($item->net_amount,2) }}</div>@endforeach</details></td>
            <td class="text-end fw-semibold">₱{{ number_format($p->net_amount, 2) }}</td>
            <td class="small">
              @if(!empty($p->payout_receipt_path ?? null))
                <button class="btn btn-outline-primary btn-sm payout-receipt-viewer-btn" type="button" data-receipt-url="{{ $p->payout_receipt_path }}" data-receipt-download-url="{{ route('superadmin.payouts.receipt_download', $p->id) }}" data-receipt-title="Payout Receipt #{{ $p->id }}">
                  <i class="bi bi-image me-1"></i>View Receipt
                </button>
              @else
                <span class="text-muted">Pending receipt</span>
              @endif
            </td>
            <td class="text-end payout-row-action">
              @if(in_array($p->status,['requested','processing']) && empty($p->blocked_reason))
                <form action="{{ route('superadmin.payouts.mark_paid', $p->id) }}" method="POST" enctype="multipart/form-data" class="d-flex gap-2 justify-content-end payout-mark-paid-form" data-prevent-double-submit>
                  @csrf
                  <div class="payout-receipt-scan flex-grow-1" data-scan-url="{{ route('superadmin.payouts.scan_receipt', $p->id) }}">
                    <input type="file" name="payout_receipt" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp" required>
                    <div class="small mt-1 payout-scan-message" aria-live="polite">Upload a GCash receipt to scan its reference, amount, and recipient.</div>
                    <input name="reference_number" class="form-control form-control-sm mt-1 payout-reference" placeholder="Scanned reference number" readonly required>
                  </div>
                  <button class="btn btn-success btn-sm" type="submit" disabled>Mark Paid</button>
                </form>
              @else
                <div class="text-muted small">{{ $p->paid_at }}</div>
                @if($p->reference_number)
                  <div class="small text-muted">Ref: {{ $p->reference_number }}</div>
                @endif
              @endif
              @if(in_array($p->status,['requested','processing']))<form class="mt-2" method="POST" action="{{ route('superadmin.payouts.cancel',$p->id) }}" data-prevent-double-submit>@csrf<label class="small d-block"><input type="checkbox" name="confirmed_untransferred" value="1" required> I confirm no money was transferred.</label><button class="btn btn-sm btn-outline-danger mt-1">Cancel & Reconcile</button></form>@endif
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No payout requests need action.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="p-3">{{ $payoutRequests->links() }}</div>
</div>
@endif

@if($payoutStatus === '' || in_array($payoutStatus, ['paid','cancelled','failed'], true))
<div class="card mt-4">
  <button class="card-header payout-section-head finance-table-toggle w-100 border-0 text-start" type="button" data-finance-panel="recent" aria-expanded="{{ $payoutStatus === '' ? 'false' : 'true' }}" aria-controls="finance-panel-recent"><span><i class="bi bi-clock-history me-2" style="color:var(--primary)"></i>Payout History</span><i class="bi bi-chevron-down finance-table-chevron" aria-hidden="true"></i></button>
  <div class="table-responsive payout-table-scroll finance-table-panel" id="finance-panel-recent" data-finance-content="recent" @if($payoutStatus === '') hidden @endif>
    <table class="table align-middle mb-0 payout-history-admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Seller</th>
          <th>Mode</th>
          <th>Paid</th>
          <th class="text-end">Net</th>
          <th>Receipt Proof</th>
          <th>Reference</th>
        </tr>
      </thead>
      <tbody>
        @foreach($recentPayouts as $p)
          <tr class="payout-detail-trigger" tabindex="0" role="button" aria-label="Open paid payout #{{ $p->id }} details"
              data-shop-name="{{ $p->shop_name }}"
              data-seller-name="{{ $p->seller_name ?? 'Seller' }}"
              data-payout-method="{{ $p->payout_method ?? 'GCash' }}"
              data-payout-account-name="{{ $p->payout_account_name ?? '' }}"
              data-payout-account-number="{{ $p->payout_account_number ?? '' }}">
            <td>#{{ $p->id }}</td>
            <td><div class="fw-semibold">{{ $p->shop_name }}</div><div class="small text-muted">{{ $p->seller_name }}</div></td>
            <td>{{ ucfirst($p->mode) }}</td>
            <td class="small">{{ ucfirst($p->status) }}<br>{{ $p->paid_at ? \Carbon\Carbon::parse($p->paid_at)->format('M d, Y h:i A') : $p->created_at }}</td>
            <td class="text-end fw-semibold">₱{{ number_format($p->net_amount, 2) }}</td>
            <td class="small">
              @if(!empty($p->payout_receipt_path ?? null))
                <button class="btn btn-outline-primary btn-sm payout-receipt-viewer-btn" type="button" data-receipt-url="{{ $p->payout_receipt_path }}" data-receipt-download-url="{{ route('superadmin.payouts.receipt_download', $p->id) }}" data-receipt-title="Payout Receipt #{{ $p->id }}">
                  <i class="bi bi-image me-1"></i>View Receipt
                </button>
              @else
                <span class="text-muted">No receipt</span>
              @endif
            </td>
            <td class="small text-muted">{{ $p->reference_number ?: '—' }}<details class="mt-1"><summary>Order allocations</summary>@foreach($requestItems->get($p->id,collect()) as $item)<div>#{{ $item->order_id }} · ₱{{ number_format($item->net_amount,2) }}</div>@endforeach</details></td>
          </tr>
        @endforeach
        @if($recentPayouts->isEmpty())
          <tr><td colspan="7" class="text-center text-muted py-4">No payouts match these filters.</td></tr>
        @endif
      </tbody>
    </table>
  </div>
  <div class="p-3">{{ $recentPayouts->links() }}</div>
</div>
@endif
@endif

<style>
  .payout-summary-grid > [class*="col-"] { display:flex; min-width:0; }
  .payout-summary-grid .cs-stat-card { width:100%; min-width:0; }
  .payout-summary-grid .cs-stat-num { white-space:nowrap; overflow-wrap:normal; font-variant-numeric:tabular-nums; font-size:clamp(1.05rem,1.55vw,1.45rem); }
  .finance-table-toggle{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,auto) auto;align-items:center;gap:.75rem;background:transparent;cursor:pointer;transition:background-color .18s ease}.finance-table-toggle:hover{background:#fafafa}.finance-table-toggle:focus-visible{outline:3px solid var(--primary);outline-offset:-3px}.finance-table-chevron{transition:transform .22s ease}.finance-table-toggle[aria-expanded="true"] .finance-table-chevron{transform:rotate(180deg)}.finance-table-panel{overflow:hidden}.finance-table-panel[hidden]{display:none!important}@media(max-width:700px){.finance-table-toggle{grid-template-columns:minmax(0,1fr) auto}.finance-table-toggle .small{grid-column:1/-1;grid-row:2;font-size:.78rem}.finance-table-chevron{grid-column:2;grid-row:1}}
  .payout-rider-requests{padding:1rem 1.25rem;border-bottom:1px solid var(--gray-100)}.payout-subhead{display:flex;justify-content:space-between;gap:.75rem;align-items:center;flex-wrap:wrap;font-weight:800}.payout-rider-grid{display:grid;gap:.75rem;margin-top:.8rem}.payout-rider-card{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.75rem;padding:1rem;border:1px solid #bfdbfe;border-radius:14px;background:linear-gradient(135deg,#eff6ff,#fff)}.payout-role-pill{display:inline-block;margin-right:.45rem;padding:.16rem .45rem;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:.7rem;font-weight:800}.payout-rider-account{margin-top:.3rem;color:var(--gray-500);font-size:.82rem}.payout-rider-amount{font-size:1.12rem;font-weight:900;color:#166534}.payout-rider-form{grid-column:1/-1;display:grid;gap:.45rem}.payout-rider-form .rider-payout-reference{min-width:0}@media(max-width:575px){.payout-rider-card{grid-template-columns:1fr}.payout-rider-amount{order:2}.payout-rider-form .d-flex{flex-direction:column}.payout-rider-form .btn{width:100%;min-height:44px}}
  .payout-section-head { display:flex; align-items:center; justify-content:space-between; gap:.75rem; flex-wrap:wrap; }
  .payout-attention { display:inline-flex; align-items:center; gap:.42rem; border-radius:999px; padding:.28rem .58rem; background:#fff7ed; color:#9a3412; font-size:.72rem; font-weight:800; }
  .payout-attention-dot { width:.55rem; height:.55rem; border-radius:50%; background:#f97316; box-shadow:0 0 0 0 rgba(249,115,22,.5); animation:payoutAttentionPulse 1.8s infinite; }
  @keyframes payoutAttentionPulse { 70% { box-shadow:0 0 0 .42rem rgba(249,115,22,0); } 100% { box-shadow:0 0 0 0 rgba(249,115,22,0); } }
  .payout-detail-trigger { cursor:pointer; }
  .payout-detail-trigger.is-action-required > td { background:#fffaf0; }
  .payout-detail-trigger.is-action-required > td:first-child { box-shadow:inset 3px 0 #f97316; }
  .payout-detail-trigger:focus-visible { outline:3px solid color-mix(in srgb,var(--primary) 35%,transparent); outline-offset:-3px; }
  .payout-balances-table { min-width:940px !important; }
  .payout-refunds-table { min-width:720px !important; }
  .payout-history-admin-table { min-width:980px !important; }
  .payout-row-action { width:1%; min-width:154px; white-space:nowrap; }
  .payout-action-group { flex-wrap:nowrap; }
  .payout-row-action form { flex:0 0 auto; }
  .payout-row-action .btn,
  .payout-action-group .btn {
    width:max-content;
    max-width:none;
    min-height:36px;
    white-space:nowrap;
    overflow-wrap:normal !important;
    word-break:normal;
  }
  .payout-mark-paid-form { flex-wrap:nowrap; min-width:440px; }
  .payout-mark-paid-form .form-control { min-width:150px; }
  .payout-mark-paid-form .btn { flex:0 0 auto; white-space:nowrap; }
  .payout-mobile-list { display:none; gap:0; padding:0; }
  .payout-mobile-card { width:100%; min-height:64px; border:0; border-bottom:1px solid var(--gray-100); border-radius:0; padding:.9rem 1rem; background:transparent; color:inherit; text-align:left; box-shadow:none; transition:background .16s ease, transform .16s ease; }
  .payout-mobile-card:last-child { border-bottom:0; }
  .payout-mobile-card:hover { background:color-mix(in srgb,var(--primary) 6%,#fff); transform:translateY(-2px); box-shadow:0 8px 18px rgba(15,23,42,.08); }
  .payout-mobile-card:hover .bi-chevron-right { color:var(--primary); transform:translateX(3px); }
  .payout-mobile-card:focus-visible { outline:3px solid color-mix(in srgb,var(--primary) 32%,transparent); outline-offset:-3px; }
  .payout-mobile-card-head { display:flex; align-items:center; justify-content:space-between; gap:.75rem; }
  .payout-mobile-card-title { font-weight:800; color:var(--gray-900); min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .payout-mobile-card-head .bi-chevron-right { color:var(--gray-400); font-size:.85rem; flex:0 0 auto; transition:transform .16s ease,color .16s ease; }
  .payout-mobile-card-meta { display:flex; align-items:center; gap:.55rem; width:100%; max-width:100%; overflow:hidden; margin-top:.26rem; min-width:0; color:var(--gray-600); font-size:.79rem; }
  .payout-mobile-card-meta span { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .payout-mobile-card-meta span:first-child { flex:1 1 auto; }
  .payout-mobile-card.has-status .payout-mobile-card-meta span:last-child { flex:0 1 auto; border-radius:999px; padding:.16rem .45rem; background:#f1f5f9; color:#475569; font-size:.68rem; font-weight:800; }
  .payout-detail-modal { position:fixed; inset:0; z-index:1090; display:none; padding:12px; background:rgba(15,23,42,.58); }
  .payout-detail-modal.is-open { display:flex; align-items:center; justify-content:center; }
  .payout-detail-dialog { width:min(560px,100%); max-height:calc(100dvh - 24px); display:flex; flex-direction:column; overflow:hidden; border-radius:18px; background:#fff; box-shadow:0 24px 70px rgba(15,23,42,.3); }
  .payout-detail-header,.payout-detail-footer { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:.9rem 1rem; border-bottom:1px solid var(--gray-100); }
  .payout-detail-footer { border-top:1px solid var(--gray-100); border-bottom:0; justify-content:flex-end; }
  .payout-detail-body { overflow-y:auto; overflow-x:hidden; min-width:0; padding:1rem; -webkit-overflow-scrolling:touch; }
  .payout-detail-grid { display:grid; gap:.65rem; min-width:0; }.payout-detail-field { min-width:0; padding:.7rem; border-radius:10px; background:var(--gray-50); overflow-wrap:anywhere; }.payout-detail-field small { display:block; margin-bottom:.22rem; color:var(--gray-500); font-weight:700; text-transform:uppercase; font-size:.65rem; letter-spacing:.06em; }.payout-detail-field .form-control { margin-top:.45rem; min-width:0; }
  .payout-detail-body .payout-mark-paid-form--modal { display:grid!important; grid-template-columns:minmax(0,1fr); gap:.65rem!important; min-width:0!important; width:100%; justify-content:stretch!important; }
  .payout-detail-body .payout-receipt-scan { min-width:0; width:100%; }
  .payout-detail-body .payout-mark-paid-form .form-control { min-width:0; width:100%; }
  .payout-detail-body .payout-mark-paid-form .btn { width:100%; min-height:42px; white-space:normal; }
  .payout-mobile-card.is-action-required { background:#fffaf0; box-shadow:inset 3px 0 #f97316; }
  .payout-mobile-card.is-action-required .payout-mobile-card-title::before { content:''; display:inline-block; width:.55rem; height:.55rem; margin-right:.45rem; border-radius:50%; background:#f97316; vertical-align:middle; }
  @media(max-width:575px) {
    .payout-summary-grid { --bs-gutter-x:.75rem; --bs-gutter-y:.75rem; }
    .payout-summary-grid .cs-stat-card { gap:.45rem; padding:.65rem!important; }
    .payout-summary-grid .cs-stat-icon { width:36px; height:36px; border-radius:9px; font-size:.95rem; }
    .payout-summary-grid .cs-stat-num { font-size:clamp(.82rem,4.15vw,1.1rem); letter-spacing:-.045em; }
    .payout-summary-grid .cs-stat-label { margin-top:.18rem; font-size:.6rem; letter-spacing:.025em; line-height:1.1; }
  }
  @media (hover:none) { .payout-mobile-card:hover { transform:none; box-shadow:none; } }
  /* The admin sidebar narrows the usable content area before the viewport reaches phone width. */
  @media(max-width:575px) {
    .payout-table-scroll > .payout-balances-table,
    .payout-table-scroll > .payout-refunds-table,
    .payout-table-scroll > .payout-history-admin-table { display:none; }
    .payout-mobile-list { display:grid; }
    .payout-balances-table { min-width:900px !important; }
    .payout-history-admin-table { min-width:920px !important; }
    .payout-row-action { min-width:150px; }
    .payout-row-action .btn { min-height:40px; }
  }
  #payoutReceiptViewerModal.payout-receipt-modal .modal-dialog {
    width:min(920px, calc(100vw - 24px));
    max-width:min(920px, calc(100vw - 24px)) !important;
    margin:0 !important;
    transform:scale(.96);
  }
  #payoutReceiptViewerModal.payout-receipt-modal.show {
    display:flex !important;
    align-items:center;
    justify-content:center;
    padding:12px !important;
    overflow:hidden;
  }
  #payoutReceiptViewerModal.payout-receipt-modal.show .modal-dialog {
    transform:scale(1);
  }
  #payoutReceiptViewerModal.payout-receipt-modal .modal-content {
    max-height:calc(100dvh - 24px);
    display:flex;
    flex-direction:column;
  }
  #payoutReceiptViewerModal.payout-receipt-modal .modal-body {
    flex:1 1 auto;
    min-height:0;
    max-height:none;
    overflow:auto;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:12px;
    -webkit-overflow-scrolling:touch;
  }
  #payoutReceiptViewerModal.payout-receipt-modal .modal-body img {
    max-height:calc(100dvh - 160px) !important;
    width:auto;
  }
  #payoutReceiptViewerModal.payout-receipt-modal .modal-footer {
    flex-shrink:0;
    position:relative;
    z-index:2;
    background:#fff;
  }
  #payoutReceiptViewerModal.payout-receipt-modal .modal-footer .btn {
    min-height:44px;
    padding-inline:16px;
  }
  @media(max-width:575px) {
    #payoutReceiptViewerModal.payout-receipt-modal .modal-footer {
      justify-content:stretch;
      gap:8px;
    }
    #payoutReceiptViewerModal.payout-receipt-modal .modal-footer .btn {
      flex:1 1 0;
    }
  }
</style>

<div class="modal fade payout-receipt-modal" id="payoutReceiptViewerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold" id="payoutReceiptViewerTitle"><i class="bi bi-receipt me-1" style="color:var(--primary)"></i>Payout Receipt</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center" style="background:#0f172a">
        <img id="payoutReceiptViewerImage" src="" alt="Payout receipt" style="max-width:100%;max-height:75vh;object-fit:contain;border-radius:8px">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-primary btn-sm" id="payoutReceiptViewerDownload">
          <i class="bi bi-download me-1"></i>Download
        </button>
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const mobileDetailModal = document.createElement('div');
  mobileDetailModal.className = 'payout-detail-modal';
  mobileDetailModal.setAttribute('role', 'dialog');
  mobileDetailModal.setAttribute('aria-modal', 'true');
  mobileDetailModal.innerHTML = '<div class="payout-detail-dialog"><div class="payout-detail-header"><strong class="payout-detail-title">Payout details</strong><button type="button" class="btn-close" aria-label="Close details"></button></div><div class="payout-detail-body"><div class="payout-detail-grid"></div></div><div class="payout-detail-footer"><button type="button" class="btn btn-secondary btn-sm">Close</button></div></div>';
  document.body.appendChild(mobileDetailModal);
  const closeMobileDetail = () => { mobileDetailModal.classList.remove('is-open'); document.body.style.overflow = ''; };
  mobileDetailModal.addEventListener('click', event => { if (event.target === mobileDetailModal || event.target.closest('.btn-close,.payout-detail-footer button')) closeMobileDetail(); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && mobileDetailModal.classList.contains('is-open')) closeMobileDetail(); });
  const openPayoutDetails = (cells, headers, values, payoutDestination = {}) => {
    const grid = mobileDetailModal.querySelector('.payout-detail-grid');
    grid.replaceChildren();
    cells.forEach((cell, index) => {
      const field = document.createElement('div'); field.className = 'payout-detail-field';
      const label = document.createElement('small'); label.textContent = headers[index] || 'Details';
      const content = document.createElement('div'); content.innerHTML = cell.innerHTML;
      content.querySelectorAll('.payout-mark-paid-form').forEach(form => {
        form.classList.remove('d-flex', 'gap-2', 'justify-content-end');
        form.classList.add('payout-mark-paid-form--modal');
      });
      field.append(label, content); grid.appendChild(field);
      if (index === 1 && Object.prototype.hasOwnProperty.call(payoutDestination, 'payoutMethod')) {
        const destination = document.createElement('div'); destination.className = 'payout-detail-field';
        const destinationLabel = document.createElement('small'); destinationLabel.textContent = 'Send payout to';
        const destinationContent = document.createElement('div');
        const method = payoutDestination.payoutMethod || 'GCash';
        const accountName = payoutDestination.payoutAccountName || '';
        const accountNumber = payoutDestination.payoutAccountNumber || '';
        if (accountName || accountNumber) {
          const methodLine = document.createElement('div'); methodLine.className = 'fw-semibold'; methodLine.textContent = method;
          const nameLine = document.createElement('div'); nameLine.textContent = accountName || 'Account name not provided';
          const numberLine = document.createElement('div'); numberLine.className = 'small text-muted'; numberLine.textContent = accountNumber || 'Account number not provided';
          destinationContent.append(methodLine, nameLine, numberLine);
        } else {
          destinationContent.className = 'text-muted'; destinationContent.textContent = 'Payout destination details were not saved for this record.';
        }
        destination.append(destinationLabel, destinationContent); grid.appendChild(destination);
      }
    });
    mobileDetailModal.querySelector('.payout-detail-title').textContent = values[0] || 'Payout details';
    mobileDetailModal.classList.add('is-open'); document.body.style.overflow = 'hidden';
  };
  document.querySelectorAll('.payout-table-scroll').forEach(wrapper => {
    const table = wrapper.querySelector('table');
    const headers = [...table.querySelectorAll('thead th')].map(header => header.textContent.trim() || 'Details');
    const isSellerBalances = headers.includes('Payout Details');
    const isPayoutList = headers.includes('Mode') && headers.includes('Net');
    const list = document.createElement('div');
    list.className = 'payout-mobile-list';
    const cards = [];
    [...table.querySelectorAll('tbody tr')].forEach(row => {
      const cells = [...row.querySelectorAll('td')];
      if (!cells.length || cells.length === 1) return;
      const values = cells.map(cell => cell.textContent.trim().replace(/\s+/g, ' '));
      const card = document.createElement('button');
      card.type = 'button'; card.className = 'payout-mobile-card';
      if (headers.includes('Status')) card.classList.add('has-status');
      const needsAction = row.classList.contains('is-action-required') || /Request Details|Create Payout|Mark Refund Sent|Mark Paid/.test(values.join(' '));
      if (needsAction) card.classList.add('is-action-required');
      card.replaceChildren();
      const cardHead = document.createElement('div');
      cardHead.className = 'payout-mobile-card-head';
      const cardTitle = document.createElement('span');
      cardTitle.className = 'payout-mobile-card-title';
      cardTitle.textContent = isPayoutList ? (row.dataset.shopName || 'Cake shop') : (values[0] || 'Payout record');
      const cardIcon = document.createElement('i');
      cardIcon.className = 'bi bi-chevron-right';
      cardHead.append(cardTitle, cardIcon);
      const cardMeta = document.createElement('div');
      cardMeta.className = 'payout-mobile-card-meta';
      const firstMeta = document.createElement('span');
      firstMeta.textContent = isPayoutList
        ? (row.dataset.sellerName || 'Seller')
        : (isSellerBalances
        ? (String(values[1] || '').includes('No payout details') ? 'Payout details needed' : 'Payout details verified')
        : (values[1] || ''));
      const secondMeta = document.createElement('span');
      const statusIndex = headers.indexOf('Status');
      const paidIndex = headers.indexOf('Paid');
      const netIndex = headers.indexOf('Net');
      secondMeta.textContent = isPayoutList
        ? [values[statusIndex >= 0 ? statusIndex : paidIndex] || 'Paid', values[netIndex] || ''].filter(Boolean).join(' · ')
        : (isSellerBalances ? (values[3] ? 'Available ' + values[3] : '') : (values[2] || ''));
      cardMeta.append(firstMeta, secondMeta);
      card.append(cardHead, cardMeta);
      card.addEventListener('click', () => openPayoutDetails(cells, headers, values, row.dataset));
      cards.push({ card, needsAction });
      if (row.classList.contains('payout-detail-trigger')) {
        const openRow = event => {
          if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') return;
          if (event.target.closest('button,input,label,select,textarea,a,form')) return;
          event.preventDefault(); openPayoutDetails(cells, headers, values, row.dataset);
        };
        row.addEventListener('click', openRow);
        row.addEventListener('keydown', openRow);
      }
    });
    cards.sort((a, b) => Number(b.needsAction) - Number(a.needsAction)).forEach(item => list.appendChild(item.card));
    wrapper.appendChild(list);
  });
  document.addEventListener('change', async event => {
    const input = event.target.closest('input[name="payout_receipt"], input[name="refund_receipt"]');
    if (!input) return;
    const form = input.closest('form');
    const box = input.closest('.payout-receipt-scan');
    const message = box.querySelector('.payout-scan-message');
    const reference = box.querySelector('.payout-reference');
    const submit = form.querySelector('button[type="submit"]');
    reference.value = ''; submit.disabled = true;
    if (!input.files[0]) { message.textContent = 'Select a receipt image to scan.'; return; }
    if (input.files[0].size > 5 * 1024 * 1024) { message.textContent = 'Receipt image must be 5 MB or smaller.'; message.className = 'small mt-1 payout-scan-message text-danger'; return; }
    message.textContent = 'Scanning receipt…'; message.className = 'small mt-1 payout-scan-message text-muted';
    const body = new FormData(); body.append(input.name, input.files[0]);
    try {
      const response = await fetch(box.dataset.scanUrl, { method:'POST', headers:{'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', 'Accept':'application/json'}, body });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error((data.errors || ['Receipt could not be verified.']).join(' '));
      reference.value = data.reference_number;
      message.textContent = 'Receipt verified. Reference ' + data.reference_number + ' detected.';
      message.className = 'small mt-1 payout-scan-message text-success'; submit.disabled = false;
    } catch (error) { message.textContent = error.message; message.className = 'small mt-1 payout-scan-message text-danger'; }
  });
  const receiptModal = document.getElementById('payoutReceiptViewerModal');
  if (receiptModal && receiptModal.parentElement !== document.body) {
    document.body.appendChild(receiptModal);
  }

  function formatCountdown(ms) {
    if (ms <= 0) return null;
    const totalSeconds = Math.floor(ms / 1000);
    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    return (days ? days + 'd ' : '') +
      String(hours).padStart(2, '0') + 'h ' +
      String(minutes).padStart(2, '0') + 'm ' +
      String(seconds).padStart(2, '0') + 's';
  }

  let countdownTimer = null;
  function updateCountdowns() {
    let active = 0;
    document.querySelectorAll('[data-countdown-until]').forEach(el => {
      if (el.dataset.countdownDone === '1') return;
      const target = Date.parse(el.dataset.countdownUntil || '');
      if (!target) return;
      const text = formatCountdown(target - Date.now());
      if (!text) {
        el.textContent = 'Ready after refresh';
        el.dataset.countdownDone = '1';
        return;
      }
      el.textContent = text;
      active++;
    });
    if (active === 0 && countdownTimer) {
      clearInterval(countdownTimer);
      countdownTimer = null;
    }
  }

  updateCountdowns();
  if (document.querySelector('[data-countdown-until]:not([data-countdown-done="1"])')) {
    countdownTimer = setInterval(updateCountdowns, 1000);
  }

  document.querySelectorAll('.payout-receipt-viewer-btn').forEach(button => {
    button.addEventListener('click', function() {
      const url = button.dataset.receiptUrl;
      if (!url) return;
      document.getElementById('payoutReceiptViewerTitle').textContent = button.dataset.receiptTitle || 'Payout Receipt';
      document.getElementById('payoutReceiptViewerImage').src = url;
      const downloadButton = document.getElementById('payoutReceiptViewerDownload');
      downloadButton.dataset.downloadUrl = button.dataset.receiptDownloadUrl || url;
      downloadButton.dataset.downloadName = 'payout-receipt-' + (button.dataset.receiptTitle || 'receipt').replace(/[^0-9A-Za-z_-]+/g, '-').replace(/^-+|-+$/g, '') + '.jpg';
      if (window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(receiptModal).show();
      } else {
        window.open(url, '_blank', 'noopener');
      }
    });
  });

  document.getElementById('payoutReceiptViewerDownload')?.addEventListener('click', function() {
    const url = this.dataset.downloadUrl;
    if (!url) return;
    const originalHtml = this.innerHTML;
    this.disabled = true;
    const link = document.createElement('a');
    link.href = url;
    link.download = this.dataset.downloadName || 'payout-receipt.jpg';
    document.body.appendChild(link);
    link.click();
    link.remove();
    if (window.BerryBaseDownloads && typeof window.BerryBaseDownloads.notify === 'function') {
      window.BerryBaseDownloads.notify('Download started.');
    }
    setTimeout(() => {
      this.disabled = false;
      this.innerHTML = originalHtml;
    }, 900);
  });
});
</script>
<script>document.addEventListener('DOMContentLoaded',()=>{const buttons=[...document.querySelectorAll('.finance-table-toggle')],reduced=matchMedia('(prefers-reduced-motion: reduce)').matches;let active=buttons.find(button=>button.getAttribute('aria-expanded')==='true')||null,token=0;const panel=button=>document.querySelector('[data-finance-content="'+button.dataset.financePanel+'"]');const setState=(button,expanded)=>{button.setAttribute('aria-expanded',expanded?'true':'false');const icon=button.querySelector('.finance-table-chevron');if(icon){icon.classList.toggle('bi-chevron-up',expanded);icon.classList.toggle('bi-chevron-down',!expanded);}};const stop=element=>element?.getAnimations().forEach(animation=>animation.cancel());const close=async button=>{const content=panel(button);if(!content||content.hidden)return;stop(content);setState(button,false);if(reduced){content.hidden=true;return;}const height=content.getBoundingClientRect().height;const animation=content.animate([{height:height+'px',opacity:1},{height:'0px',opacity:0}],{duration:180,easing:'cubic-bezier(.4,0,.2,1)'});try{await animation.finished}catch(_){}content.hidden=true;};const open=async button=>{const content=panel(button);if(!content)return;stop(content);content.hidden=false;setState(button,true);if(reduced)return;const height=content.scrollHeight;const animation=content.animate([{height:'0px',opacity:0},{height:height+'px',opacity:1}],{duration:220,easing:'cubic-bezier(.2,.8,.2,1)'});try{await animation.finished}catch(_){};};buttons.forEach(button=>{const content=panel(button);if(content)content.hidden=button!==active;button.addEventListener('click',async()=>{const current=++token;if(button===active){await close(button);if(current===token)active=null;return;}const previous=active;if(previous){await close(previous);if(current!==token)return;}await open(button);if(current===token)active=button;});});});</script>
@endsection
