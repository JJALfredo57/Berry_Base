@extends('layouts.app')
@section('content')
@php
  use Illuminate\Support\Facades\DB;
  $recentOrders   = DB::table('orders as o')
    ->leftJoin('users as u','u.id','=','o.user_id')
    ->join('products as p','p.id','=','o.product_id')
    ->select('o.*',
      DB::raw('COALESCE(o.guest_name, u.fullname, "Guest") as fullname'),
      'p.name as product_name')
    ->orderByDesc('o.id')->limit(8)->get();
  $ordersByStatus = DB::table('orders')->select('status', DB::raw('count(*) as count'))->groupBy('status')->get()->keyBy('status');
@endphp

{{-- Page Header --}}
<div class="cs-page-header">
  <div>
    <h4 class="cs-page-title"><i class="bi bi-speedometer2 me-2" style="color:var(--primary)"></i>Dashboard</h4>
    <p class="cs-page-sub">Welcome back, <strong>{{ session('user')['fullname'] ?? session('user')['username'] }}</strong> — {{ now()->format('l, F d, Y') }}</p>
  </div>
  <div class="cs-page-actions">
    <a href="{{ route('admin.orders.index') }}" class="btn btn-primary btn-sm"><i class="bi bi-bag-check me-1"></i>View Orders</a>
  </div>
</div>

{{-- Alerts --}}
@if($unreadMessages > 0)
<div class="d-flex align-items-center gap-3 p-3 mb-4 rounded-3 cs-fade-up" style="background:var(--primary-bg);border:1.5px solid color-mix(in srgb,var(--primary) 20%,transparent)">
  <div style="width:40px;height:40px;border-radius:10px;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0">
    <i class="bi bi-chat-dots-fill text-white"></i>
  </div>
  <div class="flex-grow-1 small"><strong>{{ $unreadMessages }} unread message{{ $unreadMessages > 1 ? 's' : '' }}</strong> from customers awaiting your reply.</div>
  <a href="{{ route('admin.messages.index') }}" class="btn btn-primary btn-sm flex-shrink-0">Reply</a>
</div>
@endif
@if($pendingOrders > 0)
<div class="d-flex align-items-center gap-3 p-3 mb-4 rounded-3 cs-fade-up" style="background:#fffbeb;border:1.5px solid #fde68a;animation-delay:.05s">
  <div style="width:40px;height:40px;border-radius:10px;background:#f59e0b;display:flex;align-items:center;justify-content:center;flex-shrink:0">
    <i class="bi bi-clock-fill text-white"></i>
  </div>
  <div class="flex-grow-1 small"><strong>{{ $pendingOrders }} pending order{{ $pendingOrders > 1 ? 's' : '' }}</strong> waiting for your confirmation.</div>
  <a href="{{ route('admin.orders.index') }}" class="btn btn-sm flex-shrink-0" style="background:#f59e0b;color:#fff;border:none">Confirm</a>
</div>
@endif

{{-- Main Stat Cards --}}
<div class="row g-3 mb-4 cs-stagger">
  {{-- Total Orders --}}
  <div class="col-12 col-md-4">
    <div class="cs-stat-card h-100">
      <div class="cs-stat-icon" style="background:var(--primary-bg)">
        <i class="bi bi-bag-check" style="color:var(--primary)"></i>
      </div>
      <div class="cs-stat-body">
        <div class="cs-stat-num">{{ number_format($totalOrders) }}</div>
        <div class="cs-stat-label">Total Orders</div>
        <a href="{{ route('admin.orders.index') }}" class="cs-stat-trend flat" style="text-decoration:none">
          View all <i class="bi bi-arrow-right ms-1"></i>
        </a>
      </div>
    </div>
  </div>

  {{-- Pending --}}
  <div class="col-12 col-md-4">
    <div class="cs-stat-card h-100">
      <div class="cs-stat-icon" style="background:#fffbeb">
        <i class="bi bi-clock" style="color:#d97706"></i>
      </div>
      <div class="cs-stat-body">
        <div class="cs-stat-num" style="color:#d97706">{{ number_format($pendingOrders) }}</div>
        <div class="cs-stat-label">Pending Orders</div>
        <div class="cs-stat-trend {{ $pendingOrders > 0 ? 'down' : 'flat' }}">
          <i class="bi bi-{{ $pendingOrders > 0 ? 'exclamation-circle' : 'check-circle' }}"></i>
          {{ $pendingOrders > 0 ? 'Need attention' : 'All clear' }}
        </div>
      </div>
    </div>
  </div>

  {{-- Revenue --}}
  <div class="col-12 col-md-4">
    <div class="cs-stat-card h-100">
      <div class="cs-stat-icon" style="background:#ecfdf5">
        <i class="bi bi-cash-stack" style="color:#059669"></i>
      </div>
      <div class="cs-stat-body">
        <div class="cs-stat-num" style="color:#059669">₱{{ number_format($totalRevenue,2) }}</div>
        <div class="cs-stat-label">Total Revenue</div>
        <div class="cs-stat-trend up" style="flex-wrap:wrap;gap:8px">
          <span><i class="bi bi-sun me-1"></i>Today: <strong>₱{{ number_format($revenueToday,2) }}</strong></span>
          <span style="color:var(--gray-400)">·</span>
          <span><i class="bi bi-calendar me-1"></i>Month: <strong>₱{{ number_format($revenueMonth,2) }}</strong></span>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Order Status Grid --}}
<div class="d-flex align-items-center justify-content-between mb-3">
  <h6 class="fw-bold mb-0" style="color:var(--gray-700)"><i class="bi bi-pie-chart me-2" style="color:var(--primary)"></i>Order Status Breakdown</h6>
</div>
<div class="row g-2 mb-4 cs-stagger">
  @foreach([
    'Pending'          => ['#f59e0b','#fffbeb','bi-clock-fill'],
    'Confirmed'        => ['#0ea5e9','#eff6ff','bi-check-circle-fill'],
    'Preparing'        => ['#8b5cf6','#f5f3ff','bi-fire'],
    'Out for Delivery' => ['#10b981','#ecfdf5','bi-bicycle'],
    'Delivered'        => ['#059669','#d1fae5','bi-house-check-fill'],
    'Cancelled'        => ['#ef4444','#fef2f2','bi-x-circle-fill'],
  ] as $status => [$color, $bg, $icon])
  <div class="col-6 col-md-2">
    <div class="card text-center p-3 h-100" style="border-top:3px solid {{ $color }}">
      <div style="width:36px;height:36px;border-radius:9px;background:{{ $bg }};display:flex;align-items:center;justify-content:center;margin:0 auto .6rem">
        <i class="bi {{ $icon }}" style="color:{{ $color }};font-size:.9rem"></i>
      </div>
      <div class="cs-stat-num fw-bold mb-0" style="font-size:1.35rem;color:{{ $color }}">{{ $ordersByStatus[$status]->count ?? 0 }}</div>
      <div class="text-muted mt-1" style="font-size:.68rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em">{{ $status }}</div>
    </div>
  </div>
  @endforeach
</div>

{{-- Recent Orders --}}
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <span><i class="bi bi-receipt me-2" style="color:var(--primary)"></i>Recent Orders</span>
    <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-primary btn-sm">View All</a>
  </div>
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead>
        <tr>
          <th class="ps-4">Order</th>
          <th>Customer</th>
          <th>Product</th>
          <th>Total</th>
          <th>Payment</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($recentOrders as $o)
        <tr>
          <td class="ps-4">
            <span class="fw-semibold" style="font-size:.8rem;color:var(--gray-500)">#{{ $o->id }}</span>
          </td>
          <td>
            <div class="fw-semibold" style="font-size:.85rem">{{ $o->fullname }}</div>
            <div class="text-muted" style="font-size:.73rem">{{ $o->fulfillment_type ?? 'Pickup' }}</div>
          </td>
          <td style="font-size:.85rem;max-width:160px">{{ Str::limit($o->product_name, 28) }}</td>
          <td class="fw-bold" style="font-size:.88rem">₱{{ number_format($o->total_price,2) }}</td>
          <td><span class="status-badge status-{{ $o->payment_status }}">{{ $o->payment_status }}</span></td>
          <td><span class="status-badge status-{{ str_replace(' ','-',$o->status) }}">{{ $o->status }}</span></td>
          <td>
            <a href="{{ route('admin.messages.thread',$o->id) }}" class="btn btn-sm" style="background:var(--gray-100);color:var(--gray-600);border:none;width:32px;height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:8px" title="Message">
              <i class="bi bi-chat"></i>
            </a>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7">
            <div class="cs-empty">
              <i class="bi bi-bag cs-empty-icon"></i>
              <div class="cs-empty-title">No orders yet</div>
              <div class="cs-empty-sub">Orders will appear here once customers start placing them.</div>
            </div>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
