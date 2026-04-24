@extends('layouts.app')
@section('content')
<div style="display:flex;align-items:center;justify-content:center;min-height:60vh">
  <div style="text-align:center;max-width:460px">
    <div style="width:80px;height:80px;border-radius:50%;background:#FFEBEE;display:inline-flex;align-items:center;justify-content:center;margin-bottom:1.5rem">
      <i class="bi bi-x-circle" style="font-size:2.2rem;color:#C62828"></i>
    </div>
    <h1 style="font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;color:var(--gray-900);margin:0 0 .75rem">Application Not Approved</h1>
    <p style="font-size:.9rem;color:var(--gray-600);line-height:1.7;margin:0 0 1rem">
      Unfortunately, your seller application for <strong>{{ $shop->shop_name }}</strong> was not approved.
    </p>
    @if($shop->rejected_reason)
    <div style="background:#FFEBEE;border-left:3px solid #C62828;border-radius:var(--radius-md);padding:.875rem 1rem;text-align:left;margin-bottom:1.5rem">
      <div style="font-size:.75rem;font-weight:700;color:#C62828;margin-bottom:.25rem">Reason</div>
      <div style="font-size:.875rem;color:#B71C1C">{{ $shop->rejected_reason }}</div>
    </div>
    @endif
    <a href="{{ route('seller.apply') }}" style="display:inline-flex;align-items:center;gap:.5rem;background:var(--primary);color:#fff;padding:.7rem 1.5rem;border-radius:var(--radius-md);font-size:.9rem;font-weight:600">
      <i class="bi bi-arrow-repeat"></i> Re-apply
    </a>
    <div style="margin-top:1rem">
      <form method="POST" action="{{ route('logout') }}" style="display:inline">@csrf<button type="submit" style="font-size:.82rem;color:var(--gray-500);background:none;border:none;cursor:pointer;padding:0">Sign out</button></form>
    </div>
  </div>
</div>
@endsection
