@extends('errors::layout')
@section('title', 'Session Expired')
@section('code', '419')

@section('content')
<div class="error-icon-wrap warning mx-auto">
  <i class="bi bi-clock-history"></i>
</div>
<div class="error-code">419</div>
<h1 class="error-title">Session Expired</h1>
<p class="error-message">
  Your session has expired due to inactivity.<br>
  Please go back and try again — your progress may have been saved.
</p>
<a href="javascript:history.back()" class="btn-back">
  <i class="bi bi-arrow-counterclockwise"></i> Try Again
</a>
<a href="/" class="btn-home">
  <i class="bi bi-house-fill"></i> Go to Home
</a>
@endsection
