@extends('layouts.app')
@section('page_title', 'Customer Payments & Collections')

@section('content')
<style>
  .finance-detail-page{max-width:1240px;margin:auto}.finance-detail-panel{background:#fff;border:1px solid var(--gray-100,#e5e7eb);border-radius:16px;padding:1rem;box-shadow:0 1px 2px rgba(15,23,42,.04)}.finance-detail-filter{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem;align-items:end}.finance-total-row{background:#fff1f6;font-weight:800}.finance-total-row td{border-top:2px solid var(--primary,#eb2d5d)}@media(max-width:760px){.finance-detail-filter{grid-template-columns:1fr 1fr}.finance-detail-table{min-width:880px}}
</style>

<div class="finance-detail-page">
  <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
    <div>
      <h4 class="cs-page-title mb-1">Customer Payments & Collections</h4>
      <p class="cs-page-sub mb-0">Online receipts and completed cash collections are separate, so cash already received is never requested again as a seller payout.</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('seller.finance',['from'=>$from,'to'=>$to]) }}"><i class="bi bi-arrow-left me-1"></i>Back to Finance</a>
  </div>

  <section class="finance-detail-panel mb-3">
    <form method="GET" class="finance-detail-filter">
      <div><label class="form-label small fw-semibold">From</label><input class="form-control" type="date" name="from" value="{{ $from }}"></div>
      <div><label class="form-label small fw-semibold">To</label><input class="form-control" type="date" name="to" value="{{ $to }}"></div>
      <div><label class="form-label small fw-semibold">Method</label><select class="form-select" name="method"><option value="">All methods</option><option value="gcash" @selected($method==='gcash')>GCash / online</option><option value="cash" @selected($method==='cash')>Cash</option></select></div>
      <div><label class="form-label small fw-semibold">Fulfillment</label><select class="form-select" name="fulfillment"><option value="">All orders</option><option value="delivery" @selected($fulfillment==='delivery')>Delivery</option><option value="pickup" @selected($fulfillment==='pickup')>Pickup</option></select></div>
      <div><label class="form-label small fw-semibold">Payment kind</label><select class="form-select" name="payment_type"><option value="">All receipt kinds</option><option value="downpayment" @selected($paymentType==='downpayment')>Downpayment</option><option value="full" @selected($paymentType==='full')>Full payment</option><option value="remaining" @selected($paymentType==='remaining')>Remaining balance</option></select></div>
      <div><button class="btn btn-primary w-100" type="submit">Apply filters</button></div>
    </form>
  </section>

  <section class="finance-detail-panel mb-3">
    <div class="d-flex justify-content-between align-items-end gap-2 flex-wrap mb-2">
      <div><h6 class="fw-bold mb-0">Completed-order collection breakdown</h6><small class="text-muted">Cash collected includes the rider's retained delivery fee. “Cash to seller” is only the amount already handed to the seller.</small></div>
      <strong>₱{{ number_format($collectionTotal,2) }} collected</strong>
    </div>
    <div class="table-responsive"><table class="table align-middle mb-0 finance-detail-table"><thead><tr><th>Date completed</th><th>Order</th><th>Payment / fulfillment</th><th class="text-end">Online paid</th><th class="text-end">Cash collected</th><th class="text-end">Rider fee</th><th class="text-end">Cash to seller</th><th class="text-end">Expected online payout</th></tr></thead><tbody>
      @forelse($settlements as $settlement)
        <tr><td>{{ \Carbon\Carbon::parse($settlement->created_at)->format('M j, Y') }}</td><td class="fw-semibold">#{{ $settlement->order_id }}</td><td>{{ strtoupper($settlement->payment_method) }} · {{ ucfirst(strtolower($settlement->fulfillment_type)) }}</td><td class="text-end text-primary">₱{{ number_format($settlement->online_collected_amount,2) }}</td><td class="text-end text-warning-emphasis">₱{{ number_format($settlement->cash_collected_amount,2) }}</td><td class="text-end">₱{{ number_format((float)$settlement->rider_cash_fee_amount + (float)$settlement->rider_online_fee_amount,2) }}</td><td class="text-end text-warning-emphasis">₱{{ number_format($settlement->cash_to_seller_amount,2) }}</td><td class="text-end finance-positive">₱{{ number_format($settlement->seller_net_amount,2) }}</td></tr>
      @empty
        <tr><td colspan="8" class="text-center text-muted py-4">No completed collections match these filters.</td></tr>
      @endforelse
    </tbody></table></div>
  </section>

  <section class="finance-detail-panel">
    <div class="d-flex justify-content-between align-items-end gap-2 flex-wrap mb-2"><div><h6 class="fw-bold mb-0">Payment receipt history</h6><small class="text-muted">Each GCash or cash receipt is retained as payment proof.</small></div><strong>₱{{ number_format($total,2) }} receipts</strong></div>
    <div class="table-responsive"><table class="table align-middle mb-0 finance-detail-table"><thead><tr><th>Date paid</th><th>Order</th><th>Payment</th><th>Method</th><th>Fulfillment</th><th class="text-end">Remaining balance</th><th class="text-end">Amount</th></tr></thead><tbody>
      @forelse($payments as $payment)
        <tr><td>{{ \Carbon\Carbon::parse($payment->paid_at ?? $payment->created_at)->format('M j, Y g:i A') }}</td><td class="fw-semibold">#{{ $payment->order_id }}</td><td>{{ $payment->label }}</td><td>{{ $payment->method }}</td><td><span class="badge text-bg-{{ strtolower($payment->fulfillment_type)==='delivery'?'info':'secondary' }}">{{ ucfirst(strtolower($payment->fulfillment_type)) }}</span></td><td class="text-end">{{ (float)$payment->remaining_balance > 0 ? '₱'.number_format($payment->remaining_balance,2) : '—' }}</td><td class="text-end fw-bold">₱{{ number_format($payment->amount,2) }}</td></tr>
      @empty
        <tr><td colspan="7" class="text-center text-muted py-4">No payment receipts match these filters.</td></tr>
      @endforelse
    </tbody><tfoot><tr class="finance-total-row"><td colspan="5">Filtered total · {{ $payments->count() }} receipt{{ $payments->count()===1?'':'s' }}</td><td class="text-end">Total received</td><td class="text-end">₱{{ number_format($total,2) }}</td></tr></tfoot></table></div>
  </section>
</div>
@endsection
