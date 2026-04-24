@extends('layouts.app')
@section('page_title', 'Reviews')
@section('content')
<div style="padding:0">
  <div style="margin-bottom:1.5rem">
    <h1 style="font-size:1.4rem;font-weight:700;margin:0 0 .25rem">Customer Reviews</h1>
    <p style="font-size:.875rem;color:var(--gray-500);margin:0">Reviews for {{ $shop->shop_name }}</p>
  </div>

  @if(session('msg'))<div class="alert alert-success border-0 py-2"><i class="bi bi-check-circle-fill me-2"></i>{{ session('msg') }}</div>@endif

  {{-- Rating Summary --}}
  <div style="background:#fff;border-radius:16px;padding:1.5rem;border:1.5px solid var(--gray-100);margin-bottom:1.5rem;display:flex;align-items:center;gap:2rem;flex-wrap:wrap">
    <div style="text-align:center">
      <div style="font-size:3rem;font-weight:700;color:var(--primary);line-height:1">{{ $avgRating > 0 ? number_format($avgRating,1) : '—' }}</div>
      <div style="color:#FFC107;font-size:1.1rem;margin:.25rem 0">
        @for($i=1;$i<=5;$i++)<i class="bi bi-star{{ $i<=round($avgRating) ? '-fill' : '' }}"></i>@endfor
      </div>
      <div style="font-size:.8rem;color:var(--gray-500)">{{ $totalReviews }} review{{ $totalReviews!=1?'s':'' }}</div>
    </div>
    <div style="flex:1;min-width:160px">
      @foreach([5,4,3,2,1] as $star)
        @php $count = $starCounts[$star] ?? 0; $pct = $totalReviews > 0 ? ($count/$totalReviews)*100 : 0; @endphp
        <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.35rem">
          <span style="font-size:.75rem;color:var(--gray-600);width:12px">{{ $star }}</span>
          <i class="bi bi-star-fill" style="color:#FFC107;font-size:.7rem"></i>
          <div style="flex:1;height:8px;background:var(--gray-100);border-radius:99px;overflow:hidden">
            <div style="height:100%;width:{{ $pct }}%;background:#FFC107;border-radius:99px"></div>
          </div>
          <span style="font-size:.75rem;color:var(--gray-500);width:24px">{{ $count }}</span>
        </div>
      @endforeach
    </div>
  </div>

  {{-- Review List --}}
  @forelse($reviews as $rev)
  <div style="background:#fff;border-radius:12px;padding:1.1rem;border:1.5px solid var(--gray-100);margin-bottom:.75rem">
    <div style="display:flex;align-items:flex-start;gap:.875rem">
      <div style="width:42px;height:42px;border-radius:50%;background:var(--primary-bg);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--primary);flex-shrink:0">
        {{ strtoupper(substr($rev->reviewer_name,0,1)) }}
      </div>
      <div style="flex:1">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem">
          <span style="font-weight:700;font-size:.9rem">{{ $rev->reviewer_name }}</span>
          <span style="font-size:.75rem;color:var(--gray-400)">{{ \Carbon\Carbon::parse($rev->created_at)->diffForHumans() }}</span>
        </div>
        <div style="color:#FFC107;font-size:.8rem;margin:.2rem 0">
          @for($i=1;$i<=5;$i++)<i class="bi bi-star{{ $i<=$rev->rating ? '-fill' : '' }}"></i>@endfor
          <span style="font-size:.75rem;color:var(--gray-500);margin-left:.3rem">Order #{{ $rev->order_id }}</span>
        </div>
        @if($rev->review)
          <p style="font-size:.875rem;color:var(--gray-700);margin:.4rem 0 0;line-height:1.6">{{ $rev->review }}</p>
        @endif
      </div>
    </div>
  </div>
  @empty
  <div style="text-align:center;padding:4rem 1rem;background:#fff;border-radius:16px;border:1.5px solid var(--gray-100)">
    <i class="bi bi-star" style="font-size:2.5rem;color:var(--gray-300);display:block;margin-bottom:1rem"></i>
    <h3 style="font-size:1rem;font-weight:700;margin:0 0 .4rem">No reviews yet</h3>
    <p style="font-size:.875rem;color:var(--gray-500);margin:0">Reviews will appear here once customers start ordering.</p>
  </div>
  @endforelse
</div>
@endsection
