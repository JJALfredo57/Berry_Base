@extends('layouts.app')
@section('content')
<div style="min-height:100vh;background:linear-gradient(135deg,var(--primary-bg) 0%,var(--primary-light) 100%);display:flex;align-items:center;justify-content:center;padding:2rem 1rem">
<div style="max-width:480px;width:100%;text-align:center">

  <div style="width:96px;height:96px;border-radius:50%;background:var(--success-bg);display:inline-flex;align-items:center;justify-content:center;margin-bottom:1.5rem;box-shadow:0 8px 32px rgba(46,125,50,.15)">
    <i class="bi bi-check-lg" style="font-size:2.8rem;color:var(--success)"></i>
  </div>

  <h1 style="font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:700;color:var(--gray-900);margin:0 0 .75rem">
    Application Submitted!
  </h1>
  <p style="color:var(--gray-600);font-size:.95rem;line-height:1.7;margin:0 0 2rem;max-width:380px;margin-left:auto;margin-right:auto">
    Your seller application is now under review. Our team will verify your documents and notify you via SMS within 1-3 business days.
  </p>

  <div style="background:#fff;border-radius:var(--radius-lg);padding:1.5rem;margin-bottom:1.75rem;text-align:left;box-shadow:var(--shadow-sm);border:1.5px solid var(--gray-100)">
    <div style="font-size:.82rem;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:.05em;margin-bottom:1rem">What happens next</div>
    @foreach([
      ['bi-search', 'Document Review', 'Our team reviews your uploaded ID and DTI certificate.'],
      ['bi-shield-check', 'Verification', 'We verify your details against official records.'],
      ['bi-bell', 'Notification', 'You\'ll receive an SMS once your application is approved or if we need more info.'],
      ['bi-shop', 'Go Live', 'Set up your products and start receiving orders!'],
    ] as [$icon, $title, $desc])
    <div style="display:flex;gap:.875rem;margin-bottom:.875rem;align-items:flex-start">
      <div style="width:36px;height:36px;border-radius:10px;background:var(--primary-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:.1rem">
        <i class="bi {{ $icon }}" style="color:var(--primary);font-size:.9rem"></i>
      </div>
      <div>
        <div style="font-size:.875rem;font-weight:600;color:var(--gray-900)">{{ $title }}</div>
        <div style="font-size:.8rem;color:var(--gray-500);margin-top:.15rem">{{ $desc }}</div>
      </div>
    </div>
    @endforeach
  </div>

  <a href="{{ route('catalog') }}" class="btn btn-primary" style="padding:.75rem 2rem;font-size:.95rem;font-weight:600;border-radius:var(--radius-md)">
    Browse the Platform
  </a>
  <div style="margin-top:1rem">
    <a href="{{ route('login') }}" style="font-size:.875rem;color:var(--gray-500)">Already approved? Sign in</a>
  </div>
</div>
</div>
@endsection
