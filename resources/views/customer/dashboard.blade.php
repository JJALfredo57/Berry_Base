@extends('layouts.app')
@section('content')
<div class="container-fluid py-4">
  @php
    use Illuminate\Support\Facades\DB;
    $uid = session('user')['id'];
    $totalOrders    = DB::table('orders')->where('user_id',$uid)->count();
    $pendingOrders  = DB::table('orders')->where('user_id',$uid)->where('status','Pending')->count();
    $deliveredOrders = DB::table('orders')->where('user_id',$uid)->where('status','Delivered')->count();
    $openFeedback = 0;
    try {
      $openFeedback = DB::table('customer_feedback')->where('user_id',$uid)->where('status','open')->count();
    } catch (\Exception $e) {}
    $recentOrders   = DB::table('orders as o')
      ->join('products as p','p.id','=','o.product_id')
      ->where('o.user_id',$uid)
      ->select('o.*','p.name as product_name','p.image_path')
      ->orderByDesc('o.id')->limit(5)->get();
  @endphp

  <div class="mb-4">
    <h4 class="fw-bold mb-1">👋 Hello, {{ session('user')['fullname'] ?? session('user')['username'] }}!</h4>
    <p class="text-muted small">Here's a summary of your orders.</p>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-4">
      <div class="card text-center p-3 h-100">
        <div class="fw-bold" style="font-size:1.8rem;color:var(--primary)">{{ $totalOrders }}</div>
        <div class="text-muted small">Total Orders</div>
      </div>
    </div>
    <div class="col-4">
      <div class="card text-center p-3 h-100">
        <div class="fw-bold text-warning" style="font-size:1.8rem">{{ $pendingOrders }}</div>
        <div class="text-muted small">Pending</div>
      </div>
    </div>
    <div class="col-4">
      <div class="card text-center p-3 h-100">
        <div class="fw-bold text-success" style="font-size:1.8rem">{{ $deliveredOrders }}</div>
        <div class="text-muted small">Delivered</div>
      </div>
    </div>
  </div>

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h6 class="fw-bold mb-0">Recent Orders</h6>
    <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
      <a href="{{ route('customer.feedback') }}" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-chat-square-heart me-1"></i>Feedback{{ $openFeedback > 0 ? ' ('.$openFeedback.')' : '' }}
      </a>
      <a href="{{ route('customer.orders') }}" class="small" style="color:var(--primary)">View all →</a>
    </div>
  </div>

  @forelse($recentOrders as $o)
  <div class="card mb-2">
    <div class="card-body p-3 d-flex align-items-center gap-3">
      <img src="{{ $o->image_path }}" style="width:48px;height:48px;object-fit:cover;border-radius:.6rem"
           onerror="this.src='https://placehold.co/48x48/f3f3f3/bbb?text=🎂'">
      <div class="flex-grow-1">
        <div class="fw-semibold small">{{ $o->product_name }}</div>
        <div class="text-muted" style="font-size:.75rem">Order #{{ $o->id }} · {{ \Carbon\Carbon::parse($o->created_at)->format('M d') }}</div>
      </div>
      <span class="status-badge status-{{ str_replace(' ','-',$o->status) }}">{{ $o->status }}</span>
    </div>
  </div>
  @empty
  <div class="card text-center py-4">
    <i class="bi bi-bag" style="font-size:2.5rem;color:#ddd"></i>
    <p class="text-muted mt-2 mb-3 small">No orders yet.</p>
    <a href="{{ route('customer.catalog') }}" class="btn btn-primary btn-sm mx-auto" style="width:fit-content">🎂 Browse Cakes</a>
  </div>
  @endforelse
</div>
@endsection
