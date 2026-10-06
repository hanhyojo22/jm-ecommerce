<x-app-layout>
    <section class="bg-slate-50 py-5 sm:py-7 lg:py-8">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto w-full max-w-6xl">
                <nav aria-label="Breadcrumb" class="flex min-w-0 items-center gap-2 text-sm">
                    <a href="{{ route('catalog.index') }}" class="shrink-0 font-medium text-slate-500 transition hover:text-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700">Home</a>
                    <svg class="size-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                    <span aria-current="page" class="min-w-0 truncate font-semibold text-slate-800">My account</span>
                </nav>

                <div class="mt-5 min-w-0 sm:mt-6">
                    <div data-clerk-component="user-profile" class="min-w-0" aria-busy="true" aria-live="polite">
                        <p class="py-6 text-sm text-slate-500">Loading account settings…</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-app-layout>
