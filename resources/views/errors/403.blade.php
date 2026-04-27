@extends('errors::layout')
@section('title', 'Access Denied')
@section('code', '403')

@section('content')
<div class="error-icon-wrap danger mx-auto">
  <i class="bi bi-shield-lock-fill"></i>
</div>
<div class="error-code">403</div>
<h1 class="error-title">Access Denied</h1>
<p class="error-message">
  You don't have permission to access this page.<br>
  If you believe this is a mistake, please contact the administrator.
</p>
<a href="javascript:history.back()" class="btn-back">
  <i class="bi bi-arrow-left"></i> Go Back
</a>
<a href="/" class="btn-home">
  <i class="bi bi-house-fill"></i> Go to Home
</a>
@endsection
