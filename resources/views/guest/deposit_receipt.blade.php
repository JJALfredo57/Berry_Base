@extends('layouts.app')
@section('content')
<style>
.receipt-wrap { max-width:500px;margin:0 auto;padding:24px 16px 48px; }
.receipt-card { background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 4px 32px rgba(0,0,0,.10); }
.receipt-header { background:linear-gradient(135deg,#16a34a 0%,#15803d 100%);color:#fff;padding:32px 28px 24px;text-align:center; }
.check-circle { width:72px;height:72px;border-radius:50%;background:rgba(255,255,255,.2);display:inline-flex;align-items:center;justify-content:center;margin-bottom:14px; }
.gcash-badge { display:inline-flex;align-items:center;gap:6px;background:#007AFF;color:#fff;border-radius:20px;padding:4px 14px;font-size:.78rem;font-weight:700;margin-top:10px; }
.receipt-body { padding:24px 28px; }
.receipt-divider { border:none;border-top:1.5px dashed #e5e7eb;margin:16px 0; }
.receipt-row { display:flex;justify-content:space-between;align-items:flex-start;font-size:.875rem;margin-bottom:9px; }
.receipt-row .lbl { color:#6b7280;flex-shrink:0; }
.receipt-row .val { font-weight:600;color:#111;text-align:right;max-width:65%; }
.order-id-box { background:#f9fafb;border:1px dashed #d1d5db;border-radius:10px;padding:10px 16px;text-align:center;font-family:monospace;font-size:1rem;font-weight:700;color:#374151;letter-spacing:.05em;margin-bottom:12px; }
.order-id-box span { display:block;font-size:.72rem;font-weight:400;color:#9ca3af;margin-bottom:2px;font-family:sans-serif;letter-spacing:0; }
.balance-box { background:#fff7ed;border:1.5px solid #fed7aa;border-radius:12px;padding:14px 16px;margin-top:14px; }
.track-badge { display:inline-block;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:6px;padding:2px 8px;font-family:monospace;font-size:.85rem;font-weight:700;color:#374151; }
.receipt-footer { padding:0 28px 28px; }
@media print { .no-print{display:none!important} .receipt-wrap{padding:0} .receipt-card{box-shadow:none;border:1px solid #eee} }
</style>

<div class="receipt-wrap">
@php
  $s        = $vatSettings ?? null;
  $shopName = $s->site_title ?? config('app.name','Cake Shop');
  $deposit  = (float) $receipt->deposit_amount;
  $total    = (float) $receipt->total_price;
  $balance  = $total - $deposit;
@endphp

<div class="receipt-card">
  <div class="receipt-header">
    <div class="check-circle"><i class="bi bi-check-lg" style="font-size:2.2rem"></i></div>
    <h5>Deposit Confirmed! 💰</h5>
    <p>Your deposit has been received. We'll confirm your order soon.</p>
    <div class="gcash-badge"><i class="bi bi-phone-fill"></i>&nbsp;GCash &nbsp;✓ Paid</div>
  </div>

  <div class="receipt-body">

    <div class="text-center mb-4">
      <div style="font-size:.75rem;color:#9ca3af;text-transform:uppercase;letter-spacing:.1em;font-weight:600">Deposit Receipt</div>
      <div style="font-size:1.1rem;font-weight:800;color:#16a34a;margin-top:2px">{{ $shopName }}</div>
      @if($s && $s->tin_number)
        <div style="font-size:.75rem;color:#6b7280;margin-top:2px">TIN: {{ $s->tin_number }}</div>
      @endif
    </div>

    <div class="order-id-box">
      <span>Order Number</span>
      #{{ $receipt->id }}
    </div>

    @if($receipt->track_code)
    <div class="text-center mb-4" style="font-size:.82rem;color:#6b7280">
      Track Code: <span class="track-badge">{{ $receipt->track_code }}</span>
    </div>
    @endif

    @if($receipt->guest_name)
    <div class="receipt-row">
      <span class="lbl"><i class="bi bi-person me-1"></i>Customer</span>
      <span class="val">{{ $receipt->guest_name }}</span>
    </div>
    @endif

    <hr class="receipt-divider">

    <div class="receipt-row">
      <span class="lbl">Product</span>
      <span class="val">{{ $receipt->product_name }}</span>
    </div>
    <div class="receipt-row">
      <span class="lbl">Date Paid</span>
      <span class="val">{{ \Carbon\Carbon::parse($receipt->deposit_paid_at)->format('M d, Y h:i A') }}</span>
    </div>
    <div class="receipt-row">
      <span class="lbl">Payment</span>
      <span class="val" style="color:#007AFF"><i class="bi bi-phone-fill me-1"></i>GCash — Paid ✓</span>
    </div>

    @if($receipt->deposit_message)
    <div class="receipt-row">
      <span class="lbl">Admin Note</span>
      <span class="val" style="font-weight:400;color:#374151">{{ $receipt->deposit_message }}</span>
    </div>
    @endif

    @if(!empty($pmReference))
    <div class="receipt-row">
      <span class="lbl">Ref #</span>
      <span class="val" style="font-family:monospace;font-size:.82rem">{{ $pmReference }}</span>
    </div>
    @endif

    <hr class="receipt-divider">

    <div class="receipt-row">
      <span class="lbl">Order Total</span>
      <span class="val">₱{{ number_format($total,2) }}</span>
    </div>
    <div class="receipt-row border-top pt-2 mt-1">
      <span class="lbl fw-bold" style="color:#16a34a">Deposit Paid</span>
      <span class="val" style="font-size:1.15rem;color:#16a34a">₱{{ number_format($deposit,2) }}</span>
    </div>

    {{-- Remaining Balance --}}
    @if($balance > 0)
    <div class="balance-box">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div style="font-weight:700;color:#9a3412;font-size:.9rem"><i class="bi bi-clock me-1"></i>Remaining Balance</div>
          <div style="font-size:.75rem;color:#c2410c;margin-top:2px">To be paid upon pickup/delivery</div>
        </div>
        <div style="font-size:1.2rem;font-weight:800;color:#9a3412">₱{{ number_format($balance,2) }}</div>
      </div>
    </div>
    @endif

    <div class="mt-4 p-3 rounded-3 text-center" style="background:#f0fdf4;border:1px solid #bbf7d0">
      <i class="bi bi-hourglass-split me-1" style="color:#16a34a"></i>
      <span style="font-size:.82rem;color:#166534">
        Your deposit is received. The admin will now review and confirm your order.
        You'll receive an SMS once confirmed.
      </span>
    </div>

  </div>

  <div class="receipt-footer">
    <div class="d-grid gap-2 no-print">
      <a href="{{ route('track.order', $trackCode) }}" class="btn fw-semibold" style="background:#16a34a;color:#fff">
        <i class="bi bi-pin-map me-1"></i>Track My Order
      </a>
      <button onclick="window.print()" class="btn btn-outline-secondary">
        <i class="bi bi-printer me-1"></i>Print Receipt
      </button>
    </div>
  </div>
</div>
</div>
@endsection
