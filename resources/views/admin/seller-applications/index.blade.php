<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-emerald-800">Marketplace administration</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Seller applications</h1>
        </div>
    </x-slot>

    @php($statusStyles = ['pending' => 'bg-amber-50 text-amber-800 ring-amber-200', 'approved' => 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'rejected' => 'bg-rose-50 text-rose-800 ring-rose-200'])
    @php($hasFilters = filled($filters['search'] ?? null) || filled($filters['status'] ?? null))

    <div class="bg-[#faf8f5] py-8 sm:py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->has('application'))
                <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">{{ $errors->first('application') }}</div>
            @endif

            <section class="grid gap-3 sm:grid-cols-3" aria-label="Seller application summaries">
                @foreach ([
                    ['label' => 'Pending', 'count' => $statusCounts['pending'] ?? 0, 'valueClass' => 'text-amber-700'],
                    ['label' => 'Approved', 'count' => $statusCounts['approved'] ?? 0, 'valueClass' => 'text-emerald-800'],
                    ['label' => 'Rejected', 'count' => $statusCounts['rejected'] ?? 0, 'valueClass' => 'text-rose-700'],
                ] as $summary)
                    <div class="rounded-xl border border-stone-200 bg-white px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
                        <p class="text-2xl font-semibold tabular-nums tracking-tight {{ $summary['valueClass'] }}">{{ number_format($summary['count']) }}</p>
                        <p class="mt-1 text-sm font-medium text-slate-600">{{ $summary['label'] }}</p>
                    </div>
                @endforeach
            </section>

            <section class="mt-9" aria-labelledby="applications-heading">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 id="applications-heading" class="text-lg font-semibold tracking-tight text-slate-950">Applications to review</h2>
                        <p class="mt-1 text-sm text-slate-500">Pending applications are shown first.</p>
                    </div>
                    <form method="GET" action="{{ route('admin.seller-applications.index') }}" class="flex w-full flex-col gap-2 sm:flex-row lg:w-auto" role="search" aria-label="Search seller applications">
                        @if (filled($filters['status'] ?? null))
                            <input type="hidden" name="status" value="{{ $filters['status'] }}">
                        @endif
                        <label class="sr-only" for="search">Search applications</label>
                        <input id="search" name="search" value="{{ $filters['search'] ?? '' }}" type="search" placeholder="Search applicant or store" class="block h-10 w-full rounded-lg border-stone-200 bg-white text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-emerald-700 focus:ring-emerald-700 sm:w-72">
                        @if ($hasFilters)
                            <a href="{{ route('admin.seller-applications.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg px-3 text-sm font-semibold text-slate-600 transition hover:bg-white hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Reset</a>
                        @endif
                    </form>
                </div>

                <nav class="mt-4 flex gap-2 overflow-x-auto border-y border-stone-200 py-3" aria-label="Application status filters">
                    @foreach (['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                        @php($selected = ($filters['status'] ?? '') === $value)
                        <a href="{{ route('admin.seller-applications.index', [...request()->except('status', 'page'), ...($value ? ['status' => $value] : [])]) }}" aria-current="{{ $selected ? 'page' : 'false' }}" class="inline-flex min-h-9 shrink-0 items-center rounded-full px-3 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 {{ $selected ? 'bg-emerald-800 text-white' : 'text-slate-600 hover:bg-white hover:text-emerald-800' }}">
                            {{ $label }} <span class="ml-1.5 tabular-nums {{ $selected ? 'text-emerald-100' : 'text-slate-400' }}">{{ number_format($value ? ($statusCounts[$value] ?? 0) : $statusCounts->sum()) }}</span>
                        </a>
                    @endforeach
                </nav>

                <div class="mt-6 grid grid-cols-[repeat(auto-fill,minmax(17rem,20rem))] gap-5" aria-label="Seller applications">
                    @forelse ($applications as $application)
                        <article class="min-h-[18rem] overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.08)] transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md focus-within:border-emerald-300 focus-within:ring-2 focus-within:ring-emerald-700">
                            <a href="{{ route('admin.seller-applications.show', $application) }}" class="group flex h-full flex-col p-5 focus:outline-none" aria-labelledby="application-{{ $application->id }}-name">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 id="application-{{ $application->id }}-name" title="{{ $application->user->name }}" class="truncate text-lg font-semibold tracking-tight text-slate-950">{{ $application->user->name }}</h3>
                                        <p title="{{ $application->user->email }}" class="mt-1 truncate text-sm text-slate-500">{{ $application->user->email }}</p>
                                    </div>
                                    <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$application->status] }}">{{ ucfirst($application->status) }}</span>
                                </div>
                                @if (filled($application->store_name))
                                    <div class="mt-5 border-t border-stone-100 pt-4">
                                        <p class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Proposed store</p>
                                        <p title="{{ $application->store_name }}" class="mt-1 truncate font-semibold text-slate-800">{{ $application->store_name }}</p>
                                    </div>
                                @endif
                                <div class="mt-auto flex items-end justify-between gap-3 pt-5">
                                    <div>
                                        <p class="text-xs font-medium text-slate-500">Submitted</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-800">{{ $application->submitted_at?->format('M j, Y') ?? 'Not submitted' }}</p>
                                    </div>
                                    <span class="inline-flex min-h-10 items-center text-sm font-semibold text-emerald-800 transition group-hover:text-emerald-950">Review application <span class="ml-1.5" aria-hidden="true">→</span></span>
                                </div>
                            </a>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-stone-200 bg-white px-6 py-14 text-center shadow-sm">
                            <p class="font-semibold text-slate-900">{{ $hasFilters ? 'No applications match these filters.' : 'No seller applications yet.' }}</p>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $hasFilters ? 'Try widening the search or clearing a filter.' : 'New seller applications will appear here for review.' }}</p>
                            @if ($hasFilters)<a href="{{ route('admin.seller-applications.index') }}" class="mt-5 inline-flex min-h-10 items-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Clear filters</a>@endif
                        </div>
                    @endforelse
                </div>

                @if ($applications->hasPages())
                    <div class="mt-6 rounded-xl border border-stone-200 bg-white px-4 py-4 shadow-sm">{{ $applications->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
