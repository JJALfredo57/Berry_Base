@extends('layouts.app')
@section('content')
<div class="container-fluid py-4">
  @php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;
    $uid = session('user')['id'];
    $totalOrders    = DB::table('orders')->where('user_id',$uid)->count();
    $pendingOrders  = DB::table('orders')->where('user_id',$uid)->where('status','Pending')->count();
    $deliveredOrders = DB::table('orders')->where('user_id',$uid)->where('status','Delivered')->count();
    $openFeedback = 0;
    $activeRequests = 0;
    $offerRequests = 0;
    try {
      $openFeedback = DB::table('customer_feedback')->where('user_id',$uid)->where('status','open')->count();
    } catch (\Exception $e) {}
    try {
      if (Schema::hasTable('order_requests')) {
        $activeRequests = DB::table('order_requests')->where('user_id',$uid)->whereIn('status',['pending','accepted','customer_accepted','schedule_suggested','alternative_offered','needs_more_details'])->count();
        $offerRequests = DB::table('order_requests')->where('user_id',$uid)->whereIn('status',['accepted','customer_accepted'])->count();
      }
    } catch (\Exception $e) {}
    $recentOrders   = DB::table('orders as o')
      ->leftJoin('products as p','p.id','=','o.product_id')
      ->leftJoin('custom_orders as co','co.order_id','=','o.id')
      ->where('o.user_id',$uid)
      ->select('o.*', DB::raw("COALESCE(co.cake_name, p.name, 'Custom Cake') as product_name"), 'p.image_path', 'co.reference_images', 'co.cake_name as custom_cake_name')
      ->orderByDesc('o.id')->limit(5)->get();
  @endphp

  <div class="mb-4">
    <h4 class="fw-bold mb-1">👋 Hello, {{ session('user')['fullname'] ?? session('user')['username'] }}!</h4>
    <p class="text-muted small">Here's a summary of your orders.</p>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card text-center p-3 h-100">
        <div class="fw-bold" style="font-size:1.8rem;color:var(--primary)">{{ $totalOrders }}</div>
        <div class="text-muted small">Total Orders</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center p-3 h-100">
        <div class="fw-bold text-warning" style="font-size:1.8rem">{{ $pendingOrders }}</div>
        <div class="text-muted small">Pending</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center p-3 h-100">
        <div class="fw-bold text-success" style="font-size:1.8rem">{{ $deliveredOrders }}</div>
        <div class="text-muted small">Delivered</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <a href="{{ route('customer.order_requests.index', ['filter' => $offerRequests > 0 ? 'offers' : 'active']) }}" class="card text-center p-3 h-100 text-decoration-none" style="border-color:rgba(236,37,99,.18)">
        <div class="fw-bold" style="font-size:1.8rem;color:var(--primary)">{{ $activeRequests }}</div>
        <div class="text-muted small">Requests{{ $offerRequests > 0 ? ' - '.$offerRequests.' offer'.($offerRequests > 1 ? 's' : '') : '' }}</div>
      </a>
    </div>
  </div>

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h6 class="fw-bold mb-0">Recent Orders</h6>
    <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
      <a href="{{ route('customer.feedback') }}" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-chat-square-heart me-1"></i>Feedback{{ $openFeedback > 0 ? ' ('.$openFeedback.')' : '' }}
      </a>
      <a href="{{ route('customer.order_requests.index') }}" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-send me-1"></i>Requests{{ $activeRequests > 0 ? ' ('.$activeRequests.')' : '' }}
      </a>
      <a href="{{ route('customer.orders') }}" class="small" style="color:var(--primary)">View all →</a>
    </div>
  </div>

  @forelse($recentOrders as $o)
  @php
    $referenceImages = json_decode($o->reference_images ?? '[]', true);
    $referenceImages = is_array($referenceImages) ? array_values(array_filter($referenceImages)) : [];
    $isCustomOrder = ($o->order_type ?? '') === 'custom' || !empty($o->custom_cake_name);
    $recentOrderImage = $isCustomOrder ? ($referenceImages[0] ?? null) : ($o->image_path ?? null);
    $recentOrderDetails = [
      'id' => $o->id, 'name' => $o->product_name, 'status' => $o->status,
      'date' => \Carbon\Carbon::parse($o->created_at)->format('M d, Y h:i A'),
      'image' => $recentOrderImage ?: $o->image_path, 'quantity' => $o->quantity ?? 1,
      'total' => $o->total_price ?? $o->price ?? null, 'payment' => $o->payment_method ?? null,
      'paymentStatus' => $o->payment_status ?? null, 'fulfillment' => $o->delivery_method ?? $o->order_type ?? null,
      'address' => $o->delivery_address ?? null, 'notes' => $o->notes ?? $o->special_instructions ?? null,
    ];
  @endphp
  <button type="button" class="card mb-2 w-100 border-0 text-start recent-order-trigger" data-order="{{ json_encode($recentOrderDetails, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}">
    <div class="card-body p-3 d-flex align-items-center gap-3">
      <img src="{{ $recentOrderImage ?: $o->image_path }}" style="width:48px;height:48px;object-fit:cover;border-radius:.6rem"
           onerror="this.src='https://placehold.co/48x48/f3f3f3/bbb?text=🎂'">
      <div class="flex-grow-1">
        <div class="fw-semibold small">{{ $o->product_name }} @if($isCustomOrder)<span class="badge ms-1" style="background:var(--primary-bg,#fff7fb);color:var(--primary);font-size:.6rem">Custom</span>@endif</div>
        <div class="text-muted" style="font-size:.75rem">Order #{{ $o->id }} · {{ \Carbon\Carbon::parse($o->created_at)->format('M d') }}</div>
      </div>
      <span class="status-badge status-{{ str_replace(' ','-',$o->status) }}">{{ $o->status }}</span>
    </div>
  </button>
  @empty
  <div class="card text-center py-4">
    <i class="bi bi-bag" style="font-size:2.5rem;color:#ddd"></i>
    <p class="text-muted mt-2 mb-3 small">No orders yet.</p>
    <a href="{{ route('customer.catalog') }}" class="btn btn-primary btn-sm mx-auto" style="width:fit-content">🎂 Browse Cakes</a>
  </div>
  @endforelse
</div>
<style>
.recent-order-trigger{transition:transform .18s ease,box-shadow .18s ease;cursor:pointer}.recent-order-trigger:hover{transform:translateY(-2px);box-shadow:0 10px 22px rgba(15,23,42,.1)}.recent-order-trigger:focus-visible{outline:3px solid color-mix(in srgb,var(--primary) 35%,transparent);outline-offset:3px}.recent-order-modal{position:fixed;inset:0;z-index:1090;display:none;padding:12px;background:rgba(15,23,42,.58)}.recent-order-modal.is-open{display:flex;align-items:center;justify-content:center}.recent-order-dialog{width:min(560px,100%);max-height:calc(100dvh - 24px);display:flex;flex-direction:column;overflow:hidden;border-radius:18px;background:#fff;box-shadow:0 24px 70px rgba(15,23,42,.3)}.recent-order-header,.recent-order-footer{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:1rem;border-bottom:1px solid #eee}.recent-order-footer{border-top:1px solid #eee;border-bottom:0}.recent-order-body{overflow:auto;padding:1rem}.recent-order-hero{display:flex;gap:.9rem;align-items:center;margin-bottom:1rem}.recent-order-hero img{width:72px;height:72px;object-fit:cover;border-radius:12px;background:#f3f4f6}.recent-order-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem}.recent-order-field{padding:.72rem;border-radius:10px;background:#f8fafc}.recent-order-field small{display:block;color:#6b7280;font-weight:700;text-transform:uppercase;font-size:.65rem;letter-spacing:.06em;margin-bottom:.18rem}@media(max-width:575px){.recent-order-grid{grid-template-columns:1fr}.recent-order-dialog{border-radius:14px}}
</style>
<div class="recent-order-modal" id="recentOrderModal" role="dialog" aria-modal="true" aria-labelledby="recentOrderModalTitle" aria-hidden="true"><div class="recent-order-dialog"><div class="recent-order-header"><strong id="recentOrderModalTitle">Order details</strong><button type="button" class="btn-close" aria-label="Close order details"></button></div><div class="recent-order-body"><div class="recent-order-hero"><img id="recentOrderImage" alt="Order item"><div><div class="fw-bold" id="recentOrderName"></div><div class="text-muted small" id="recentOrderMeta"></div></div></div><div class="recent-order-grid" id="recentOrderFields"></div></div><div class="recent-order-footer"><a class="btn btn-outline-primary btn-sm" href="{{ route('customer.orders') }}">View all orders</a><button type="button" class="btn btn-secondary btn-sm">Close</button></div></div></div>
<script>
document.addEventListener('DOMContentLoaded',()=>{const modal=document.getElementById('recentOrderModal'),fields=document.getElementById('recentOrderFields'),close=()=>{modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');document.body.style.overflow=''};document.querySelectorAll('.recent-order-trigger').forEach(trigger=>trigger.addEventListener('click',()=>{const o=JSON.parse(trigger.dataset.order);document.getElementById('recentOrderName').textContent=o.name;document.getElementById('recentOrderMeta').textContent=`Order #${o.id} · ${o.date} · ${o.status}`;const image=document.getElementById('recentOrderImage');image.src=o.image||'https://placehold.co/72x72/f3f3f3/bbb?text=Cake';fields.replaceChildren();[['Status',o.status],['Quantity',o.quantity],['Total',o.total!==null?'₱'+Number(o.total).toLocaleString('en-PH',{minimumFractionDigits:2}):'—'],['Payment',o.paymentStatus||o.payment||'—'],['Fulfillment',o.fulfillment||'—'],['Delivery address',o.address||'—'],['Notes',o.notes||'—']].forEach(([label,value])=>{const f=document.createElement('div');f.className='recent-order-field';const l=document.createElement('small');l.textContent=label;const v=document.createElement('div');v.textContent=value;f.append(l,v);fields.append(f)});modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';modal.querySelector('.btn-close').focus()}));modal.addEventListener('click',e=>{if(e.target===modal||e.target.closest('.btn-close,.recent-order-footer button'))close()});document.addEventListener('keydown',e=>{if(e.key==='Escape'&&modal.classList.contains('is-open'))close()})});
</script>
@endsection
