@extends('layouts.app')
@section('content')
<style>
.receipt-wrap { max-width:500px;margin:0 auto;padding:24px 16px 48px; }
.receipt-card { background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 4px 32px rgba(0,0,0,.10); }
.receipt-header { background:linear-gradient(135deg,var(--primary) 0%,#c2185b 100%);color:#fff;padding:32px 28px 24px;text-align:center; }
.receipt-header .check-circle { width:72px;height:72px;border-radius:50%;background:rgba(255,255,255,.2);display:inline-flex;align-items:center;justify-content:center;margin-bottom:14px; }
.receipt-header h5 { font-size:1.25rem;font-weight:700;margin:0 0 4px; }
.receipt-header p  { font-size:.85rem;margin:0;opacity:.85; }
.gcash-badge { display:inline-flex;align-items:center;gap:6px;background:#007AFF;color:#fff;border-radius:20px;padding:4px 14px;font-size:.78rem;font-weight:700;margin-top:10px; }
.receipt-body { padding:24px 28px; }
.receipt-divider { border:none;border-top:1.5px dashed #e5e7eb;margin:16px 0; }
.receipt-row { display:flex;justify-content:space-between;align-items:flex-start;font-size:.875rem;margin-bottom:9px; }
.receipt-row .lbl { color:#6b7280;flex-shrink:0; }
.receipt-row .val { font-weight:600;color:#111;text-align:right;max-width:65%; }
.receipt-product { display:flex;gap:12px;align-items:center;background:#fdf4f7;border-radius:12px;padding:12px;margin-bottom:14px; }
.receipt-product img { width:52px;height:52px;border-radius:8px;object-fit:cover;flex-shrink:0; }
.receipt-product .name { font-weight:700;font-size:.9rem;color:#111; }
.receipt-product .sub  { font-size:.78rem;color:#6b7280;margin-top:2px; }
.order-id-box { background:#f9fafb;border:1px dashed #d1d5db;border-radius:10px;padding:10px 16px;text-align:center;font-family:monospace;font-size:1rem;font-weight:700;color:#374151;letter-spacing:.05em;margin-bottom:12px; }
.order-id-box span { display:block;font-size:.72rem;font-weight:400;color:#9ca3af;margin-bottom:2px;font-family:sans-serif;letter-spacing:0; }
.total-row { display:flex;justify-content:space-between;align-items:center;padding:12px 0 0; }
.total-row .total-lbl  { font-size:.95rem;font-weight:700;color:#111; }
.total-row .total-amt  { font-size:1.35rem;font-weight:800;color:var(--primary); }
.vat-box { background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px 14px;margin-top:10px;font-size:.82rem; }
.ref-box { background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:10px 14px;margin-top:10px;font-size:.82rem;word-break:break-all; }
.track-badge { display:inline-block;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:6px;padding:2px 8px;font-family:monospace;font-size:.85rem;font-weight:700;color:#374151; }
.receipt-footer { padding:0 28px 28px; }
@media print { .no-print{display:none!important} .receipt-wrap{padding:0} .receipt-card{box-shadow:none;border:1px solid #eee} body{background:#fff!important} }
</style>

<div class="receipt-wrap">
@php
  $s        = $vatSettings ?? null;
  $vatOn    = $s && $s->vat_enabled;
  $vatRate  = $vatOn ? ($s->vat_rate ?? 12) : 0;
  $shopName = $s->site_title ?? config('app.name','Cake Shop');
  $subtotal = (float) $receipt->total_price;
  $vatAmt   = $vatOn ? round($subtotal - ($subtotal / (1 + $vatRate/100)), 2) : 0;
  $vatExcl  = $vatOn ? round($subtotal - $vatAmt, 2) : 0;
@endphp

<div class="receipt-card">
  <div class="receipt-header">
    <div class="check-circle"><i class="bi bi-check-lg" style="font-size:2.2rem"></i></div>
    <h5>Payment Confirmed! 🎉</h5>
    <p>Your GCash payment was received successfully.</p>
    <div class="gcash-badge"><i class="bi bi-phone-fill"></i>&nbsp;GCash &nbsp;✓ Paid</div>
  </div>

  <div class="receipt-body">

    <div class="text-center mb-4">
      <div style="font-size:.75rem;color:#9ca3af;text-transform:uppercase;letter-spacing:.1em;font-weight:600">Official Receipt</div>
      <div style="font-size:1.1rem;font-weight:800;color:var(--primary);margin-top:2px">{{ $shopName }}</div>
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

    @if($receipt->guest_name || ($receipt->fulfillment_type === 'Delivery' && $receipt->address))
    <div class="mb-3">
      @if($receipt->guest_name)
      <div class="receipt-row">
        <span class="lbl"><i class="bi bi-person me-1"></i>Customer</span>
        <span class="val">{{ $receipt->guest_name }}</span>
      </div>
      @endif
      @if($receipt->fulfillment_type === 'Delivery' && $receipt->address)
      <div class="receipt-row">
        <span class="lbl"><i class="bi bi-geo-alt me-1"></i>Address</span>
        <span class="val" style="font-weight:400;color:#374151">{{ $receipt->address }}</span>
      </div>
      @endif
    </div>
    @endif

    <hr class="receipt-divider">

    <div class="receipt-product">
      <img src="{{ $receipt->product_image }}" onerror="this.src='https://placehold.co/52x52/fce4ec/e91e63?text=🎂'" alt="">
      <div>
        <div class="name">{{ $receipt->product_name }}</div>
        <div class="sub">
          Qty: {{ $receipt->quantity }}
          @if($receipt->selected_size) &bull; {{ $receipt->selected_size }} @endif
          @if($receipt->custom_note) &bull; {{ $receipt->custom_note }} @endif
        </div>
      </div>
    </div>

    @foreach($receiptAddons as $addon)
    <div class="receipt-row" style="margin-bottom:6px">
      <span class="lbl"><i class="bi bi-plus-circle me-1" style="color:var(--primary)"></i>{{ $addon->addon_name }}</span>
      <span class="val">+ ₱{{ number_format($addon->addon_price,2) }}</span>
    </div>
    @endforeach

    <hr class="receipt-divider">

    <div class="receipt-row">
      <span class="lbl">Date Paid</span>
      <span class="val">{{ \Carbon\Carbon::parse($receipt->paid_at)->format('M d, Y h:i A') }}</span>
    </div>
    <div class="receipt-row">
      <span class="lbl">Fulfillment</span>
      <span class="val">{{ $receipt->fulfillment_type }}</span>
    </div>
    @if($receipt->fulfillment_type === 'Delivery' && $receipt->delivery_zone)
    <div class="receipt-row">
      <span class="lbl">Delivery Zone</span>
      <span class="val">{{ $receipt->delivery_zone }}</span>
    </div>
    @endif
    @if($receipt->schedule_date)
    <div class="receipt-row">
      <span class="lbl">Scheduled</span>
      <span class="val">
        {{ \Carbon\Carbon::parse($receipt->schedule_date)->format('M d, Y') }}
        @if($receipt->schedule_time) &bull; {{ \Carbon\Carbon::parse($receipt->schedule_time)->format('h:i A') }} @endif
      </span>
    </div>
    @endif
    <div class="receipt-row">
      <span class="lbl">Payment</span>
      <span class="val" style="color:#007AFF"><i class="bi bi-phone-fill me-1"></i>GCash — Paid ✓</span>
    </div>

    <hr class="receipt-divider">

    @if($receipt->delivery_fee > 0)
    <div class="receipt-row">
      <span class="lbl">Delivery Fee</span>
      <span class="val">₱{{ number_format($receipt->delivery_fee,2) }}</span>
    </div>
    @endif
    @if($receipt->service_charge > 0)
    <div class="receipt-row">
      <span class="lbl">Service Charge</span>
      <span class="val">₱{{ number_format($receipt->service_charge,2) }}</span>
    </div>
    @endif

    @if($vatOn)
    <div class="vat-box mt-2">
      <div class="d-flex justify-content-between mb-1">
        <span style="color:#166534">VAT-Exclusive Amount</span>
        <span style="color:#166534;font-weight:600">₱{{ number_format($vatExcl,2) }}</span>
      </div>
      <div class="d-flex justify-content-between">
        <span style="color:#166534">VAT ({{ number_format($vatRate,0) }}%)</span>
        <span style="color:#166534;font-weight:600">₱{{ number_format($vatAmt,2) }}</span>
      </div>
    </div>
    @else
    <div style="font-size:.75rem;color:#9ca3af;text-align:right;margin-top:4px">Non-VAT Registered</div>
    @endif

    <div class="total-row border-top mt-2 pt-3">
      <span class="total-lbl">Total Paid</span>
      <span class="total-amt">₱{{ number_format($receipt->total_price,2) }}</span>
    </div>

    @if(!empty($pmReference))
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:10px 14px;margin-top:10px;font-size:.82rem;word-break:break-all">
      <div style="color:#1e40af;font-weight:600;margin-bottom:3px"><i class="bi bi-shield-check me-1"></i>PayMongo Reference</div>
      <div style="color:#1e3a8a;font-family:monospace">{{ $pmReference }}</div>
    </div>
    @endif

  </div>

  <div class="receipt-footer">
    <div class="text-center mb-3" style="font-size:.75rem;color:#9ca3af">
      <i class="bi bi-shield-check me-1" style="color:#22c55e"></i>
      Transaction verified via PayMongo
    </div>
    <div class="d-grid gap-2 no-print">
      <a href="{{ route('track.order', $trackCode) }}" class="btn btn-primary fw-semibold">
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
