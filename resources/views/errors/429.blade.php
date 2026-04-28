@extends('errors.layout')
@section('title', 'Too Many Requests')
@section('code', '429')

@section('content')
<div class="error-icon-wrap warning mx-auto">
  <i class="bi bi-stopwatch-fill"></i>
</div>
<div class="error-code">429</div>
<h1 class="error-title">Too Many Requests</h1>
<p class="error-message">
  You have made too many requests in a short period of time.<br>
  Please wait a moment before trying again.
</p>
<a href="javascript:history.back()" class="btn-back">
  <i class="bi bi-arrow-left"></i> Go Back
</a>
<a href="/" class="btn-home">
  <i class="bi bi-house-fill"></i> Go to Home
</a>
@endsection
