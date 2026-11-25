@extends('layouts.app')
@section('page_title', 'My Payouts')
@section('content')
@php
  $accountReady = !empty($shop->payout_method) && !empty($shop->payout_details_verified) && !empty($shop->payout_account_name) && !empty($shop->payout_account_number) && empty($shop->payout_paused);
  $stateLabels = ['unrequested'=>'Net Online Balance','transfer_pending'=>'Requested / Processing','awaiting_completion'=>'Awaiting Completion','available'=>'Ready to Request','refund_reserved'=>'Refund Reserved','refunded'=>'Refunded','requested'=>'Requested','processing'=>'Processing','cancelled'=>'Cancelled','cash_settled'=>'Direct cash settled','finance_review'=>'Needs Finance Verification'];
@endphp
<style>
.payout-shell{max-width:1400px;margin:auto}.payout-summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.8rem;margin:1rem 0}.payout-tile{display:block;border:1px solid #e2e8f0;border-radius:14px;padding:1rem;background:#fff;text-decoration:none;color:inherit;min-width:0}.payout-tile strong{display:block;color:#166534;font-size:1.3rem;font-variant-numeric:tabular-nums;overflow-wrap:anywhere}.payout-tile span{font-weight:700;font-size:.82rem}.payout-tile small{display:block;color:#64748b;margin-top:.25rem}.payout-panel{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:1rem;margin-bottom:1rem;min-width:0}.payout-filters{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem;align-items:end}.payout-filters label{font-size:.78rem;font-weight:700}.payout-order{border:1px solid #e2e8f0;border-radius:12px;padding:1rem;margin-bottom:.8rem;min-width:0}.payout-order-head{display:flex;gap:.75rem;align-items:flex-start;flex-wrap:wrap}.payout-order-head input{width:22px;height:22px;flex:none;margin-top:.2rem}.payout-order-title{flex:1;min-width:120px}.payout-breakdown{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem;margin-top:.85rem}.payout-breakdown dt{font-size:.72rem;color:#64748b;font-weight:500}.payout-breakdown dd{font-size:.9rem;margin:0;font-variant-numeric:tabular-nums;overflow-wrap:anywhere}.payout-selection-total{position:static;margin-top:1rem;border:1px solid #bbf7d0;border-radius:14px;background:#f0fdf4;padding:1.25rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap}.payout-selection-total strong{font-size:1.45rem;color:#166534;display:block}.payout-history-row{border-bottom:1px solid #e2e8f0;padding:1rem 0}.payout-history-row:last-child{border:0}.payout-history-head{display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap}.payout-shell .btn,.payout-shell select,.payout-shell input:not([type=checkbox]){min-height:44px}.payout-shell a:focus-visible,.payout-shell button:focus-visible{outline:3px solid var(--primary);outline-offset:3px}.payout-shell nav{overflow:auto}.payout-gcash{display:grid;grid-template-columns:1fr 1fr auto;gap:.75rem;align-items:end}.payout-empty{text-align:center;padding:2rem;color:#64748b}@media(max-width:767px){.payout-filters,.payout-breakdown{grid-template-columns:1fr 1fr}.payout-gcash{grid-template-columns:1fr}.payout-selection-total{align-items:stretch;flex-direction:column}.payout-selection-total .btn{width:100%}.payout-panel{padding:.85rem}}@media(max-width:374px){.payout-filters,.payout-summary-grid{grid-template-columns:1fr}.payout-order{padding:.75rem}}
</style>
<style>.payout-filters>div,.payout-breakdown>div{min-width:0}.payout-filters input,.payout-filters select{width:100%;min-width:0;max-width:100%}@media(max-width:767px){.payout-filters,.payout-breakdown{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:425px){.payout-filters{grid-template-columns:minmax(0,1fr)}}</style>
<div class="payout-shell">
<h4 class="cs-page-title">My Payouts</h4>
<p class="text-muted">Verified online payments appear immediately. Select completed, issue-free orders for a GCash transfer. No waiting days or minimum payout.</p>
@if(session('msg'))<div class="alert alert-success" role="status">{{ session('msg') }}</div>@endif
@if(session('err'))<div class="alert alert-danger" role="alert">{{ session('err') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<section class="payout-summary-grid" aria-label="Account-wide balances">
@foreach([
 ['Net Online Balance',$summary['net_online'],'Unrequested funds; includes incomplete orders.','unrequested'],
 ['Awaiting Completion',$summary['awaiting'],'Visible, but cannot yet be selected.','awaiting_completion'],
 ['Ready to Request',$summary['available'],'Completed and issue-free, less recovery.','available'],
 ['Requested / Processing',$summary['processing'],'Reserved for transfer.','transfer_pending'],
 ['Paid Out',$summary['paid'],'Transferred funds with receipt proof.','paid'],
 ['Refund Reserved',$summary['reserved'],'Unavailable while the issue is resolved.','refund_reserved'],
 ...($summary['review']>0 ? [['Needs Finance Verification',$summary['review'],'Historical funds need verified evidence.','finance_review']] : []),
] as [$label,$amount,$help,$targetState])
<a class="payout-tile" href="{{ $targetState === 'paid' ? route('seller.payouts',['history_status'=>'paid','period'=>'all']).'#payout-history' : route('seller.payouts',['state'=>$targetState,'period'=>'all']).'#payout-orders' }}"><strong>₱{{ number_format($amount,2) }}</strong><span>{{ $label }}</span><small>{{ $help }}</small></a>
@endforeach
</section>
<p class="small text-muted">Cards are account-wide; filters below affect list results only. Awaiting Completion and Ready to Request are parts of Net Online Balance, not extra earnings. Direct cash is never requestable.</p>

<section class="payout-panel"><h5>Filter transactions</h5>
<p class="small text-muted">Order dates use allocation recording dates. Payout, refund, and activity lists use their own recorded dates. Payment, fulfillment, order status, and balance state filter the order list; payout status and activity type filter their respective histories.</p>
<form method="GET" class="payout-filters">
<div><label for="pf-from">From</label><input id="pf-from" type="date" class="form-control" name="from" value="{{ $from }}"></div>
<div><label for="pf-to">To</label><input id="pf-to" type="date" class="form-control" name="to" value="{{ $to }}"></div>
<div><label for="pf-search">Order / cake / reference</label><input id="pf-search" class="form-control" name="search" value="{{ $search }}" maxlength="120"></div>
<div><label for="pf-method">Payment</label><select id="pf-method" class="form-select" name="method"><option value="">All payments</option>@foreach(['GCash','COD','COP'] as $value)<option @selected($method===$value)>{{ $value }}</option>@endforeach</select></div>
<div><label for="pf-fulfillment">Fulfillment</label><select id="pf-fulfillment" class="form-select" name="fulfillment"><option value="">All fulfillment</option>@foreach(['Delivery','Pickup'] as $value)<option @selected($fulfillment===$value)>{{ $value }}</option>@endforeach</select></div>
<div><label for="pf-state">Balance state</label><select id="pf-state" class="form-select" name="state"><option value="">All balance states</option>@foreach($stateLabels as $value=>$label)<option value="{{ $value }}" @selected($state===$value)>{{ $label }}</option>@endforeach</select></div>
<div><label for="pf-order">Order status</label><select id="pf-order" class="form-select" name="order_status"><option value="">All order statuses</option>@foreach(['Pending','Confirmed','Preparing','Pickup','Out for Delivery','Delivered','Picked Up','Cancelled','No Show'] as $value)<option @selected($orderStatus===$value)>{{ $value }}</option>@endforeach</select></div>
<div><label for="pf-history">Payout history status</label><select id="pf-history" class="form-select" name="history_status"><option value="">All payout statuses</option>@foreach(['requested','processing','paid','cancelled'] as $value)<option value="{{ $value }}" @selected($historyStatus===$value)>{{ ucfirst($value) }}</option>@endforeach</select></div>
<div><label for="pf-sort">Sort</label><select id="pf-sort" class="form-select" name="sort"><option value="newest" @selected($sort==='newest')>Newest first</option><option value="oldest" @selected($sort==='oldest')>Oldest first</option></select></div>
<div><label for="pf-activity">Balance activity type</label><select id="pf-activity" class="form-select" name="activity_type"><option value="">All activity</option>@foreach(['allocation','transfer_blocked','payout_requested','payout_cancelled','payout_paid','refund_completed','return_refunded','refund_recovery'] as $value)<option value="{{ $value }}" @selected($activityType===$value)>{{ ucfirst(str_replace('_',' ',$value)) }}</option>@endforeach</select></div>
<div class="d-flex gap-2 flex-wrap"><button class="btn btn-primary" type="submit">Apply Filters</button><a class="btn btn-outline-secondary" href="{{ route('seller.payouts') }}">Clear Filters</a></div>
</form>
<div class="d-flex gap-2 flex-wrap mt-3">@foreach(['all'=>'All Time','today'=>'Today','week'=>'This Week','month'=>'This Month','last30'=>'Last 30 Days'] as $value=>$label)<a class="btn btn-sm btn-outline-primary" href="{{ route('seller.payouts',array_merge(request()->except(['from','to','orders_page','history_page','refund_page','return_page','activity_page']),['period'=>$value])) }}">{{ $label }}</a>@endforeach</div>
</section>
@unless($accountReady)<div class="alert alert-warning">{{ !empty($shop->payout_paused) ? 'Payouts are paused. Contact Super Admin.' : 'Complete your verified GCash details before requesting a payout.' }} <a href="#payout-details">View details</a></div>@endunless

<section class="payout-panel" id="payout-orders"><h5>Select orders for payout</h5>
<p class="small text-muted">{{ $ledgers->total() }} matching allocations · filtered net amount: ₱{{ number_format($filteredTotal,2) }}. Paid transfers are in Payout Transaction History below.</p>
<form method="POST" action="{{ route('seller.payouts.request') }}" id="selectedPayoutForm" data-prevent-double-submit>@csrf
<div class="d-flex gap-3 align-items-center flex-wrap mb-3"><label class="d-flex gap-2 align-items-center"><input type="checkbox" id="payoutSelectAll" style="width:22px;height:22px"> Select all eligible orders on this page</label><button type="button" class="btn btn-sm btn-outline-secondary" id="clearPayoutSelection">Clear Selection</button></div>
@forelse($ledgers as $ledger)
@php
  $eligible=$ledger->status==='available' && !$ledger->payout_id && (float)$ledger->seller_net_amount>0 && $accountReady;
  $awaitingLabel=\App\Services\SellerBalanceService::awaitingPayoutLabel($ledger->fulfillment_type, $ledger->order_status, $ledger->blocked_reason);
  $displayStateLabel=$ledger->status==='awaiting_completion' ? $awaitingLabel : ($stateLabels[$ledger->status] ?? ucfirst(str_replace('_',' ',$ledger->status)));
  $blockedReason=$ledger->blocked_reason==='Awaiting Delivery/Pickup' ? $awaitingLabel : $ledger->blocked_reason;
@endphp
<article class="payout-order">
<div class="payout-order-head"><input aria-label="Select order {{ $ledger->order_id }}" type="checkbox" name="ledger_ids[]" value="{{ $ledger->id }}" data-net-cents="{{ (int)round($ledger->seller_net_amount*100) }}" @disabled(!$eligible)>
<div class="payout-order-title"><strong>{{ (float)$ledger->seller_net_amount<0 ? 'Refund recovery · ' : '' }}Order #{{ $ledger->display_order_id }}</strong><div>{{ $ledger->product_name ?: ((float)$ledger->seller_net_amount<0 ? 'Refund recovery adjustment' : 'Cake order') }}</div><small class="text-muted">{{ $ledger->payment_method }} · {{ $ledger->fulfillment_type }} · {{ $ledger->order_status }} · {{ \Carbon\Carbon::parse($ledger->created_at)->format('M j, Y g:i A') }}</small></div><span class="badge {{ $eligible ? 'text-bg-success' : 'text-bg-light' }}">{{ $displayStateLabel }}</span></div>
@if($blockedReason)<p class="small text-warning-emphasis mt-2 mb-0">{{ $blockedReason }}@if($ledger->payout_id) — cancel the untransferred request to reconcile its funds.@endif</p>@endif
@if($ledger->items->isNotEmpty())<details class="mt-2"><summary>View cake items ({{ $ledger->items->count() }})</summary><p class="small text-muted">Proportional per-item breakdown, with centavos balanced to the order total. Selection and refund protection apply to the entire order.</p>@foreach($ledger->items as $item)<div class="small border-top py-2"><strong>{{ $item->product_name }} × {{ $item->quantity }}</strong><div>Cake amount: ₱{{ number_format($item->payout_cake_amount,2) }} · Commission: ₱{{ number_format($item->payout_commission_amount,2) }}</div><div>Net online: <strong>₱{{ number_format($item->payout_seller_net_amount,2) }}</strong> · Direct cash: ₱{{ number_format($item->payout_cash_to_seller_amount,2) }}</div></div>@endforeach</details>@endif
<dl class="payout-breakdown">
<div><dt>Cake / product amount</dt><dd>₱{{ number_format($ledger->cake_amount ?? $ledger->commission_base,2) }}</dd></div>
<div><dt>Verified online payment</dt><dd>₱{{ number_format($ledger->online_collected_amount,2) }}</dd></div>
@if(($ledger->platform_funded_discount ?? 0)>0)<div><dt>Platform-funded discount credit</dt><dd>+₱{{ number_format($ledger->platform_funded_discount,2) }}</dd></div>@endif
<div><dt>Commission ({{ number_format($ledger->commission_rate,2) }}%){{ ($ledger->commission_refund_amount ?? 0)>0 ? ' · after refund reversal' : '' }}</dt><dd>−₱{{ number_format($ledger->commission_amount,2) }}</dd>@if(($ledger->commission_refund_amount ?? 0)>0)<small>Original ₱{{ number_format($ledger->commission_amount+$ledger->commission_refund_amount,2) }} · reversed ₱{{ number_format($ledger->commission_refund_amount,2) }}</small>@endif</div>
@if(($ledger->online_funds_refunded ?? 0)>0)<div><dt>Online funds refunded</dt><dd>₱{{ number_format($ledger->online_funds_refunded,2) }}</dd><small>Seller share reversed: ₱{{ number_format($ledger->seller_refund_amount,2) }}. Not requestable again.</small></div>@endif
<div><dt>Rider allocation from online</dt><dd>₱{{ number_format($ledger->rider_online_fee_amount,2) }}</dd></div>
<div><dt>Cash to seller · not requestable</dt><dd>₱{{ number_format($ledger->cash_to_seller_amount,2) }}</dd></div>
<div><dt>Rider fee retained in cash</dt><dd>₱{{ number_format($ledger->rider_cash_fee_amount,2) }}</dd></div>
<div><dt>Net online seller amount</dt><dd class="fw-bold text-success">₱{{ number_format($ledger->seller_net_amount,2) }}</dd></div>
<div><dt>Payout reference</dt><dd>{{ $ledger->payout_id ? '#'.$ledger->payout_id : 'Not requested' }}</dd></div>
</dl></article>
@empty<div class="payout-empty">No allocations match these filters. Verified payments appear here.</div>@endforelse
<div class="mt-3">{{ $ledgers->links() }}</div>
<div class="payout-selection-total" aria-live="polite" data-recovery-cents="{{ (int)round($summary['refund_deductions']*100) }}">
<div><span><span id="selectedOrderCount">0</span> selected orders</span><strong id="selectedPayoutAmount">₱0.00</strong><small>Net transfer after deductions, including ₱{{ number_format($summary['refund_deductions'],2) }} outstanding recovery.</small></div>
<button type="submit" class="btn btn-primary" id="requestSelectedPayout" disabled data-loading-text="Submitting…">Request Payout</button>
</div></form></section>

<section class="payout-panel" id="payout-history"><h5>Payout Transaction History</h5><p class="small text-muted">Original per-order amounts and receipt references are preserved. Cancel an untransferred request to reconcile affected funds.</p>
@forelse($payouts as $payout)
<article class="payout-history-row"><div class="payout-history-head"><div><strong>Payout #{{ $payout->id }} · ₱{{ number_format($payout->net_amount,2) }}</strong><div class="small">{{ ucfirst($payout->status) }} · {{ \Carbon\Carbon::parse($payout->created_at)->format('M j, Y g:i A') }}</div></div>
@if($payout->status==='requested')<form method="POST" action="{{ route('seller.payouts.cancel',$payout->id) }}" data-prevent-double-submit>@csrf<button class="btn btn-sm btn-outline-danger">Cancel Untransferred Request</button></form>@endif</div>
@if($payout->status==='processing')<p class="small text-warning-emphasis">Transfer processing. Contact Super Admin to stop and reconcile; do not request these funds again.</p>@endif
<details class="mt-2"><summary>View order breakdown and transfer details</summary><ul class="mt-2">@foreach($payoutItems->get($payout->id,collect()) as $item)<li>Order #{{ $item->order_id }}: ₱{{ number_format($item->net_amount,2) }}</li>@endforeach</ul><div class="small">GCash: {{ $payout->payout_account_name }} · {{ substr($payout->payout_account_number,0,4) }}••••{{ substr($payout->payout_account_number,-3) }}</div><div class="small">Reference: {{ $payout->reference_number ?: 'Awaiting transfer' }}</div>@if($payout->paid_at)<div class="small">Transferred: {{ $payout->paid_at }}</div>@endif</details>
@if(!empty($payout->payout_receipt_path))<a class="btn btn-sm btn-outline-primary mt-2" href="{{ route('seller.payouts.receipt_download',$payout->id) }}">Download Transfer Receipt</a>@endif</article>
@empty<div class="payout-empty">No payout history matches these filters.</div>@endforelse
{{ $payouts->links() }}</section>

<section class="payout-panel" id="refund-history"><h5>Refund & Adjustment History</h5><p class="small text-muted">Filtered by date and order search. Active cases reserve linked funds. View the complete case and receipt in Return Requests.</p>
@forelse($refunds as $refund)<article class="payout-history-row"><strong>Order #{{ $refund->order_id }} · ₱{{ number_format($refund->refund_amount,2) }}</strong><div>{{ ucfirst(str_replace('_',' ',$refund->status)) }} · {{ $refund->reference_number ?: 'Awaiting refund transfer' }}</div><a href="{{ url('/seller/return-cases') }}">View refund case</a>@if(!empty($refund->receipt_path)) <a class="btn btn-sm btn-outline-primary" href="{{ route('seller.payouts.refund_receipt',['kind'=>'cancellation','refundId'=>$refund->id]) }}">Download Refund Receipt</a>@endif</article>@empty<p class="text-muted">No cancellation refunds in this period.</p>@endforelse
@if(method_exists($refunds,'links')){{ $refunds->links() }}@endif
@forelse($returnRefunds as $return)<article class="payout-history-row"><strong>Return · Order #{{ $return->order_id }} · ₱{{ number_format($return->approved_amount ?? $return->requested_amount ?? 0,2) }}</strong><div>{{ ucfirst(str_replace('_',' ',$return->status)) }} · {{ $return->refund_reference ?: 'Awaiting refund transfer' }}</div><a href="{{ url('/seller/return-cases') }}">View return case</a>@if(!empty($return->refund_receipt_path)) <a class="btn btn-sm btn-outline-primary" href="{{ route('seller.payouts.refund_receipt',['kind'=>'return','refundId'=>$return->id]) }}">Download Refund Receipt</a>@endif</article>@empty<p class="text-muted">No return cases in this period.</p>@endforelse
@if(method_exists($returnRefunds,'links')){{ $returnRefunds->links() }}@endif</section>

<section class="payout-panel" id="balance-activity"><h5>Balance Activity</h5><p class="small text-muted">An audit of allocations and adjustments, not additional sales. Filter by date, order search, or activity type.</p>
@forelse($events as $event)<article class="payout-history-row"><div class="payout-history-head"><strong>{{ ucfirst(str_replace('_',' ',$event->type)) }} · ₱{{ number_format($event->amount,2) }}</strong><small>{{ \Carbon\Carbon::parse($event->created_at)->format('M j, Y g:i A') }}</small></div><div class="small">{{ $event->order_id ? 'Order #'.$event->order_id : 'Payout #'.$event->payout_id }} · {{ ucfirst(str_replace('_',' ',$event->status)) }}</div><div class="small text-muted">{{ $event->note === 'Awaiting Delivery/Pickup' ? \App\Services\SellerBalanceService::awaitingCompletionLabel($event->order_fulfillment_type) : $event->note }}</div></article>@empty<p class="text-muted">No activity matches these filters.</p>@endforelse
@if(method_exists($events,'links')){{ $events->links() }}@endif</section>

<section class="payout-panel" id="payout-details"><h5>GCash Payout Details</h5><p class="small text-muted">{{ $shop->payout_details_verified ? 'Verified by Super Admin' : 'Needs Super Admin verification' }}. Changing details resets verification.</p>
<form method="POST" action="{{ route('seller.payouts.details') }}" class="payout-gcash" data-prevent-double-submit>@csrf
<div><label for="gcashName" class="form-label">GCash Account Name</label><input id="gcashName" class="form-control" name="payout_account_name" value="{{ $shop->payout_account_name }}" required maxlength="150"></div>
<div><label for="gcashNumber" class="form-label">GCash Mobile Number</label><input id="gcashNumber" class="form-control" name="payout_account_number" value="{{ $shop->payout_account_number }}" required pattern="^(09[0-9]{9}|\+639[0-9]{9})$" inputmode="tel"></div>
<button class="btn btn-primary">Save Details</button></form></section>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
 const form=document.getElementById('selectedPayoutForm'), boxes=[...form.querySelectorAll('input[name="ledger_ids[]"]:not(:disabled)')], all=document.getElementById('payoutSelectAll');
 const recovery=Number(form.querySelector('[data-recovery-cents]').dataset.recoveryCents||0);
 const update=()=>{
  const selected=boxes.filter(box=>box.checked), cents=selected.length?Math.max(0,selected.reduce((total,box)=>total+Number(box.dataset.netCents),0)-recovery):0;
  document.getElementById('selectedOrderCount').textContent=selected.length;
  document.getElementById('selectedPayoutAmount').textContent='₱'+(cents/100).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
  document.getElementById('requestSelectedPayout').disabled=!selected.length||cents<=0;
  all.checked=boxes.length>0&&selected.length===boxes.length; all.indeterminate=selected.length>0&&selected.length<boxes.length;
 };
 all.disabled=!boxes.length;
 all.addEventListener('change',()=>{boxes.forEach(box=>box.checked=all.checked);update()});
 boxes.forEach(box=>box.addEventListener('change',update));
 document.getElementById('clearPayoutSelection').addEventListener('click',()=>{boxes.forEach(box=>box.checked=false);update()});
 update();
});
</script>
@endpush
@endsection
