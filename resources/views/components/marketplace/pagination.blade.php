@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        <div class="flex flex-wrap items-center justify-center gap-2">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}" class="inline-flex size-10 items-center justify-center rounded-[10px] border border-line bg-white text-sm font-medium text-muted">‹</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}" class="inline-flex size-10 items-center justify-center rounded-[10px] border border-line bg-white text-sm font-medium text-ink transition hover:border-brand hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">‹</a>
            @endif

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

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}" class="inline-flex size-10 items-center justify-center rounded-[10px] border border-line bg-white text-sm font-medium text-ink transition hover:border-brand hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">›</a>
            @else
                <span aria-disabled="true" aria-label="{{ __('pagination.next') }}" class="inline-flex size-10 items-center justify-center rounded-[10px] border border-line bg-white text-sm font-medium text-muted">›</span>
            @endif
        </div>
    </nav>
@endif
