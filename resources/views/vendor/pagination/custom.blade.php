@if ($paginator->hasPages())
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-top:1.25rem;padding-top:1rem;border-top:1.5px solid var(--gray-100)">
  <div style="font-size:.78rem;color:var(--gray-500)">
    Showing
    <strong style="color:var(--gray-700)">{{ $paginator->firstItem() }}</strong>
    &ndash;
    <strong style="color:var(--gray-700)">{{ $paginator->lastItem() }}</strong>
    of
    <strong style="color:var(--gray-700)">{{ number_format($paginator->total()) }}</strong>
    results
  </div>
  <div style="display:flex;gap:.3rem;flex-wrap:wrap;align-items:center">

    {{-- First --}}
    @if ($paginator->onFirstPage())
      <button disabled style="width:32px;height:32px;border:1.5px solid var(--gray-200);border-radius:7px;background:#fff;color:var(--gray-300);cursor:not-allowed;display:flex;align-items:center;justify-content:center;font-size:.8rem"><i class="bi bi-chevron-double-left"></i></button>
    @else
      <a href="{{ $paginator->url(1) }}" style="width:32px;height:32px;border:1.5px solid var(--gray-200);border-radius:7px;background:#fff;color:var(--gray-600);display:flex;align-items:center;justify-content:center;font-size:.8rem;text-decoration:none;transition:border-color .15s,color .15s" onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'" onmouseout="this.style.borderColor='var(--gray-200)';this.style.color='var(--gray-600)'"><i class="bi bi-chevron-double-left"></i></a>
    @endif

    {{-- Prev --}}
    @if ($paginator->onFirstPage())
      <button disabled style="width:32px;height:32px;border:1.5px solid var(--gray-200);border-radius:7px;background:#fff;color:var(--gray-300);cursor:not-allowed;display:flex;align-items:center;justify-content:center;font-size:.8rem"><i class="bi bi-chevron-left"></i></button>
    @else
      <a href="{{ $paginator->previousPageUrl() }}" style="width:32px;height:32px;border:1.5px solid var(--gray-200);border-radius:7px;background:#fff;color:var(--gray-600);display:flex;align-items:center;justify-content:center;font-size:.8rem;text-decoration:none;transition:border-color .15s,color .15s" onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'" onmouseout="this.style.borderColor='var(--gray-200)';this.style.color='var(--gray-600)'"><i class="bi bi-chevron-left"></i></a>
    @endif

    {{-- Page numbers --}}
    @foreach ($elements as $element)
      @if (is_string($element))
        <span style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;color:var(--gray-400);font-size:.82rem">…</span>
      @endif
      @if (is_array($element))
        @foreach ($element as $page => $url)
          @if ($page == $paginator->currentPage())
            <button disabled style="min-width:32px;height:32px;padding:0 6px;border:1.5px solid var(--primary);border-radius:7px;background:var(--primary);color:#fff;font-size:.82rem;font-weight:700;cursor:default;display:flex;align-items:center;justify-content:center">{{ $page }}</button>
          @else
            <a href="{{ $url }}" style="min-width:32px;height:32px;padding:0 6px;border:1.5px solid var(--gray-200);border-radius:7px;background:#fff;color:var(--gray-600);font-size:.82rem;display:flex;align-items:center;justify-content:center;text-decoration:none;transition:border-color .15s,color .15s" onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'" onmouseout="this.style.borderColor='var(--gray-200)';this.style.color='var(--gray-600)'">{{ $page }}</a>
          @endif
        @endforeach
      @endif
    @endforeach

    {{-- Next --}}
    @if ($paginator->hasMorePages())
      <a href="{{ $paginator->nextPageUrl() }}" style="width:32px;height:32px;border:1.5px solid var(--gray-200);border-radius:7px;background:#fff;color:var(--gray-600);display:flex;align-items:center;justify-content:center;font-size:.8rem;text-decoration:none;transition:border-color .15s,color .15s" onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'" onmouseout="this.style.borderColor='var(--gray-200)';this.style.color='var(--gray-600)'"><i class="bi bi-chevron-right"></i></a>
    @else
      <button disabled style="width:32px;height:32px;border:1.5px solid var(--gray-200);border-radius:7px;background:#fff;color:var(--gray-300);cursor:not-allowed;display:flex;align-items:center;justify-content:center;font-size:.8rem"><i class="bi bi-chevron-right"></i></button>
    @endif

    {{-- Last --}}
    @if ($paginator->hasMorePages())
      <a href="{{ $paginator->url($paginator->lastPage()) }}" style="width:32px;height:32px;border:1.5px solid var(--gray-200);border-radius:7px;background:#fff;color:var(--gray-600);display:flex;align-items:center;justify-content:center;font-size:.8rem;text-decoration:none;transition:border-color .15s,color .15s" onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'" onmouseout="this.style.borderColor='var(--gray-200)';this.style.color='var(--gray-600)'"><i class="bi bi-chevron-double-right"></i></a>
    @else
      <button disabled style="width:32px;height:32px;border:1.5px solid var(--gray-200);border-radius:7px;background:#fff;color:var(--gray-300);cursor:not-allowed;display:flex;align-items:center;justify-content:center;font-size:.8rem"><i class="bi bi-chevron-double-right"></i></button>
    @endif

  </div>
</div>
@endif
