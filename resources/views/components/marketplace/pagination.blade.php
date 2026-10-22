{{-- simplePaginate() renders through this same view, and Illuminate\Pagination\Paginator has
     neither lastPage() nor $elements, so the total-aware parts stay behind this guard. --}}
@php($hasTotals = $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        {{-- Phones get one compact row: previous, position, next. Pagers do not wrap. --}}
        <div class="flex items-center justify-center gap-3 sm:hidden">
            @if ($paginator->onFirstPage())
                <span role="link" aria-disabled="true" aria-label="{{ __('pagination.previous') }}" class="inline-flex size-11 items-center justify-center rounded-[10px] border border-line bg-white text-base text-muted">‹</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}" class="inline-flex size-11 items-center justify-center rounded-[10px] border border-line bg-white text-base text-ink transition hover:border-brand hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">‹</a>
            @endif
            <span class="text-sm font-medium text-muted">Page {{ $paginator->currentPage() }}@if ($hasTotals) of {{ $paginator->lastPage() }}@endif</span>
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}" class="inline-flex size-11 items-center justify-center rounded-[10px] border border-line bg-white text-base text-ink transition hover:border-brand hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">›</a>
            @else
                <span role="link" aria-disabled="true" aria-label="{{ __('pagination.next') }}" class="inline-flex size-11 items-center justify-center rounded-[10px] border border-line bg-white text-base text-muted">›</span>
            @endif
        </div>

        <div class="hidden flex-wrap items-center justify-center gap-2 sm:flex">
            @if ($paginator->onFirstPage())
                <span role="link" aria-disabled="true" aria-label="{{ __('pagination.previous') }}" class="inline-flex size-10 items-center justify-center rounded-[10px] border border-line bg-white text-sm font-medium text-muted">‹</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}" class="inline-flex size-10 items-center justify-center rounded-[10px] border border-line bg-white text-sm font-medium text-ink transition hover:border-brand hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">‹</a>
            @endif

            @if ($hasTotals)
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span aria-hidden="true" class="inline-flex size-10 items-center justify-center text-sm text-muted">{{ $element }}</span>
                    @elseif (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="inline-flex size-10 items-center justify-center rounded-[10px] border border-ink bg-ink text-sm font-semibold text-white">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}" class="inline-flex size-10 items-center justify-center rounded-[10px] border border-line bg-white text-sm font-medium text-ink transition hover:border-brand hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            @else
                <span class="inline-flex h-10 items-center justify-center px-3 text-sm font-medium text-muted">Page {{ $paginator->currentPage() }}</span>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}" class="inline-flex size-10 items-center justify-center rounded-[10px] border border-line bg-white text-sm font-medium text-ink transition hover:border-brand hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">›</a>
            @else
                <span role="link" aria-disabled="true" aria-label="{{ __('pagination.next') }}" class="inline-flex size-10 items-center justify-center rounded-[10px] border border-line bg-white text-sm font-medium text-muted">›</span>
            @endif
        </div>
    </nav>
@endif
