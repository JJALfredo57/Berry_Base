@extends('layouts.app')
@section('content')
<div style="display:flex;align-items:center;justify-content:center;min-height:60vh">
  <div style="text-align:center;max-width:420px">
    <div style="width:80px;height:80px;border-radius:50%;background:#F3E5F5;display:inline-flex;align-items:center;justify-content:center;margin-bottom:1.5rem">
      <i class="bi bi-pause-circle" style="font-size:2.2rem;color:#6A1B9A"></i>
    </div>
    <h1 style="font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;color:var(--gray-900);margin:0 0 .75rem">Shop Suspended</h1>
    <p style="font-size:.9rem;color:var(--gray-600);line-height:1.7;margin:0 0 1.5rem">
      Your shop <strong>{{ $shop->shop_name }}</strong> has been temporarily suspended. Please contact platform support for assistance.
    </p>
    <form method="POST" action="{{ route('logout') }}" style="display:inline">@csrf<button type="submit" style="display:inline-flex;align-items:center;gap:.5rem;background:var(--gray-200);color:var(--gray-700);padding:.7rem 1.5rem;border-radius:var(--radius-md);font-size:.9rem;font-weight:600;border:none;cursor:pointer">
      <i class="bi bi-box-arrow-right"></i> Sign Out
    </button></form>
  </div>
</div>
@endsection
