@php
  $risk = $risk ?? null;
  $compact = $compact ?? false;
@endphp

@if($risk && ($risk['level'] ?? 'low') !== 'low')
  <span class="badge d-inline-flex align-items-center gap-1 ms-1"
        title="{{ implode(' | ', $risk['reasons'] ?? []) }}"
        style="background:{{ $risk['bg'] }};color:{{ $risk['color'] }};font-size:{{ $compact ? '.66rem' : 'clamp(.62rem,1.2vw,.68rem)' }};white-space:normal;text-align:left">
    <i class="bi {{ ($risk['level'] ?? '') === 'blocked' ? 'bi-slash-circle-fill' : (($risk['level'] ?? '') === 'suspicious' ? 'bi-exclamation-triangle-fill' : 'bi-eye-fill') }}"></i>
    {{ $risk['label'] }}
    @if(!$compact && !empty($risk['score']))
      <span style="opacity:.75">({{ $risk['score'] }})</span>
    @endif
  </span>
@endif
