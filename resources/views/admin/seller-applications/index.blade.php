<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="mt-1 text-[28px] font-bold tracking-[-0.02em] text-ink leading-[1.55]">Seller applications</h1>
        </div>
    </x-slot>

    @php($statusStyles = ['pending' => 'bg-warning/10 text-warning-text ring-warning/20', 'approved' => 'bg-success/10 text-success ring-success/20', 'rejected' => 'bg-danger/10 text-danger ring-danger/20'])
    @php($hasFilters = filled($filters['search'] ?? null) || filled($filters['status'] ?? null))

    <div class="bg-canvas py-8 sm:py-10">
        <div class="mx-auto max-w-[1320px] px-[18px] sm:px-8">
            @if (session('status'))
                <div class="mb-5 rounded-[14px] border border-success/20 bg-success/10 px-4 py-3 text-sm font-medium text-success" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->has('application'))
                <div class="mb-5 rounded-[14px] border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">{{ $errors->first('application') }}</div>
            @endif

            <section class="grid gap-3 sm:grid-cols-3" aria-label="Seller application summaries">
                @foreach ([
                    ['label' => 'Pending', 'count' => $statusCounts['pending'] ?? 0, 'valueClass' => 'text-warning-text'],
                    ['label' => 'Approved', 'count' => $statusCounts['approved'] ?? 0, 'valueClass' => 'text-success'],
                    ['label' => 'Rejected', 'count' => $statusCounts['rejected'] ?? 0, 'valueClass' => 'text-danger'],
                ] as $summary)
                    <div class="rounded-[14px] border border-line bg-surface px-5 py-4 ">
                        <p class="text-2xl font-semibold tabular-nums tracking-tight {{ $summary['valueClass'] }}">{{ number_format($summary['count']) }}</p>
                        <p class="mt-1 text-sm font-medium text-muted">{{ $summary['label'] }}</p>
                    </div>
                @endforeach
            </section>

            <section class="mt-9" aria-labelledby="applications-heading">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 id="applications-heading" class="text-lg font-semibold tracking-tight text-ink">Applications to review</h2>
                        <p class="mt-1 text-sm text-muted">Pending applications are shown first.</p>
                    </div>
                    <form method="GET" action="{{ route('admin.seller-applications.index') }}" class="flex w-full flex-col gap-2 sm:flex-row lg:w-auto" role="search" aria-label="Search seller applications">
                        @if (filled($filters['status'] ?? null))
                            <input type="hidden" name="status" value="{{ $filters['status'] }}">
                        @endif
                        <label class="sr-only" for="search">Search applications</label>
                        <input id="search" name="search" value="{{ $filters['search'] ?? '' }}" type="search" placeholder="Search applicant or store" class="block h-11 w-full rounded-lg border-line bg-surface text-sm text-ink placeholder:text-muted focus:border-brand focus:ring-brand sm:w-72">
                        @if ($hasFilters)
                            <a href="{{ route('admin.seller-applications.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg px-3 text-sm font-semibold text-muted transition hover:bg-surface hover:text-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Reset</a>
                        @endif
                    </form>
                </div>

                <nav class="mt-4 flex gap-1 overflow-x-auto border-b border-line" aria-label="Application status filters">
                    @foreach (['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                        @php($selected = ($filters['status'] ?? '') === $value)
                        <a href="{{ route('admin.seller-applications.index', [...request()->except('status', 'page'), ...($value ? ['status' => $value] : [])]) }}" @class([
                            'inline-flex min-h-11 shrink-0 items-center gap-2 border-b-2 px-3 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand',
                            'border-brand text-brand' => $selected,
                            'border-transparent text-muted hover:border-line hover:text-ink' => ! $selected,
                        ]) @if ($selected) aria-current="page" @endif>
                            {{ $label }} <span @class(['rounded-full px-2 py-0.5 text-xs tabular-nums', 'bg-brand/10 text-brand' => $selected, 'bg-canvas text-muted' => ! $selected])>{{ number_format($value ? ($statusCounts[$value] ?? 0) : $statusCounts->sum()) }}</span>
                        </a>
                    @endforeach
                </nav>

                <div class="mt-5 overflow-hidden rounded-[14px] border border-line bg-surface" aria-label="Seller applications">
                    <div class="hidden grid-cols-[minmax(0,1.4fr)_minmax(0,1.2fr)_minmax(8rem,.8fr)_auto] gap-4 border-b border-line bg-canvas px-5 py-3 text-[0.65rem] font-semibold uppercase tracking-[0.1em] text-muted md:grid">
                        <span>Applicant</span><span>Proposed store</span><span>Status / submitted</span><span>Next step</span>
                    </div>
                    @forelse ($applications as $application)
                        <article class="grid gap-3 border-b border-line px-4 py-4 last:border-0 sm:px-5 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1.2fr)_minmax(8rem,.8fr)_auto] md:items-center md:gap-4">
                            <div class="min-w-0">
                                <p class="mb-1 text-[0.65rem] font-semibold uppercase tracking-[0.08em] text-muted md:hidden">Applicant</p>
                                <p title="{{ $application->user->name }}" class="truncate text-sm font-semibold text-ink">{{ $application->user->name }}</p>
                                <p title="{{ $application->user->email }}" class="mt-0.5 truncate text-xs text-muted">{{ $application->user->email }}</p>
                            </div>
                            <div class="min-w-0">
                                <p class="mb-1 text-[0.65rem] font-semibold uppercase tracking-[0.08em] text-muted md:hidden">Proposed store</p>
                                <p title="{{ $application->store_name ?: 'Not provided' }}" class="truncate text-sm text-ink">{{ $application->store_name ?: 'Not provided' }}</p>
                            </div>
                            <div>
                                <p class="mb-1 text-[0.65rem] font-semibold uppercase tracking-[0.08em] text-muted md:hidden">Status / submitted</p>
                                <div class="flex flex-wrap items-center justify-between gap-2 md:block">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$application->status] }}">{{ ucfirst($application->status) }}</span>
                                    <time @if ($application->submitted_at) datetime="{{ $application->submitted_at->toIso8601String() }}" @endif class="text-xs text-muted md:mt-1 md:block">{{ $application->submitted_at?->format('M j, Y') ?? 'Not submitted' }}</time>
                                </div>
                            </div>
                            <a href="{{ route('admin.seller-applications.show', $application) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-3 text-sm font-semibold text-white transition hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 md:justify-self-start">Review application</a>
                        </article>
                    @empty
                        <div class="px-6 py-14 text-center">
                            <p class="font-semibold text-ink">{{ $hasFilters ? 'No applications match these filters.' : 'No seller applications yet.' }}</p>
                            <p class="mt-2 text-sm leading-6 text-muted">{{ $hasFilters ? 'Try widening the search or clearing a filter.' : 'New seller applications will appear here for review.' }}</p>
                            @if ($hasFilters)<a href="{{ route('admin.seller-applications.index') }}" class="mt-5 inline-flex min-h-11 items-center rounded-lg bg-brand px-4 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Clear filters</a>@endif
                        </div>
                    @endforelse
                </div>

                @if ($applications->hasPages())
                    <div class="mt-6 rounded-[14px] border border-line bg-surface px-4 py-4 ">{{ $applications->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
