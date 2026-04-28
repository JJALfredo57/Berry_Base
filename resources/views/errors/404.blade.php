@extends('errors.layout')
@section('title', 'Page Not Found')
@section('code', '404')

@section('content')
<div class="error-icon-wrap warning mx-auto">
  <i class="bi bi-search"></i>
</div>
<div class="error-code">404</div>
<h1 class="error-title">Page Not Found</h1>
<p class="error-message">
  The page you are looking for doesn't exist or may have been moved.<br>
  Please check the URL or navigate back to the homepage.
</p>
<a href="javascript:history.back()" class="btn-back">
  <i class="bi bi-arrow-left"></i> Go Back
</a>
<a href="/" class="btn-home">
  <i class="bi bi-house-fill"></i> Go to Home
</a>
@endsection
