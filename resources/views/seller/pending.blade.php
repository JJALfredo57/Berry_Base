@extends('layouts.app')
@section('content')
<div style="display:flex;align-items:center;justify-content:center;min-height:60vh">
  <div style="text-align:center;max-width:420px">
    <div style="width:80px;height:80px;border-radius:50%;background:#FFF3E0;display:inline-flex;align-items:center;justify-content:center;margin-bottom:1.5rem">
      <i class="bi bi-clock" style="font-size:2.2rem;color:#E65100"></i>
    </div>
    <h1 style="font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;color:var(--gray-900);margin:0 0 .75rem">Application Under Review</h1>
    <p style="font-size:.9rem;color:var(--gray-600);line-height:1.7;margin:0 0 1.5rem">
      Your shop <strong>{{ $shop->shop_name }}</strong> is being reviewed by our admin team. You will receive an SMS once approved. This usually takes 1-3 business days.
    </p>
    <a href="{{ route('platform.home') }}" style="display:inline-flex;align-items:center;gap:.5rem;background:var(--primary);color:#fff;padding:.7rem 1.5rem;border-radius:var(--radius-md);font-size:.9rem;font-weight:600">
      <i class="bi bi-house"></i> Go to Homepage
    </a>
    <div style="margin-top:1rem">
      <form method="POST" action="{{ route('logout') }}" style="display:inline">@csrf<button type="submit" style="font-size:.82rem;color:var(--gray-500);background:none;border:none;cursor:pointer;padding:0">Sign out</button></form>
    </div>
  </div>
</div>
@endsection
