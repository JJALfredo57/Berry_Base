@extends('errors::layout')
@section('title', 'Server Error')
@section('code', '500')

@section('content')
<div class="error-icon-wrap danger mx-auto">
  <i class="bi bi-exclamation-triangle-fill"></i>
</div>
<div class="error-code">500</div>
<h1 class="error-title">Something Went Wrong</h1>
<p class="error-message">
  We encountered an unexpected error while processing your request.<br>
  Our team has been notified and is working to fix this as quickly as possible.
</p>
<a href="javascript:history.back()" class="btn-back">
  <i class="bi bi-arrow-left"></i> Go Back
</a>
<a href="/" class="btn-home">
  <i class="bi bi-house-fill"></i> Go to Home
</a>
@endsection
