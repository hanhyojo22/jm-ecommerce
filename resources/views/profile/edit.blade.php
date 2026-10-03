<x-app-layout>
    @if ($user->account_type === 'buyer')
        <section class="bg-slate-50 py-5 sm:py-7 lg:py-8">
            <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <nav aria-label="Breadcrumb" class="flex min-w-0 items-center gap-2 text-sm">
                    <a href="{{ route('catalog.index') }}" class="shrink-0 font-medium text-slate-500 transition hover:text-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700">Home</a>
                    <svg class="size-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                    <span aria-current="page" class="min-w-0 truncate font-semibold text-slate-800">My account</span>
                </nav>

                <div class="jmmart-account-shell mt-5 min-w-0 sm:mt-6">
                    <div data-clerk-component="user-profile" class="clerk-profile-shell min-w-0" aria-busy="true" aria-live="polite">
                        <p class="py-6 text-sm text-slate-500">Loading account settings…</p>
                    </div>
                </div>
            </div>
        </section>
    @else
        <section class="min-h-screen bg-[#f7f7f2] py-6 sm:py-10" aria-labelledby="account-heading">
            <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 sm:gap-8 sm:px-6 lg:px-8">
                <div class="max-w-2xl">
                    <h1 id="account-heading" class="text-balance text-2xl font-semibold tracking-[-0.025em] text-slate-950 sm:text-3xl">My account</h1>
                    <p class="mt-2 text-pretty text-sm leading-6 text-slate-600 sm:text-base">Manage your JMMart identity, sign-in details, and security in one secure place.</p>
                </div>

                <div data-clerk-component="user-profile" class="clerk-profile-shell w-full min-w-0" aria-busy="true" aria-live="polite">
                    <p class="py-6 text-sm text-slate-500">Loading secure account settings…</p>
                </div>

                <p class="text-sm leading-6 text-slate-500">JMMart uses Clerk to manage your sign-in and security settings.</p>
            </div>
        </section>
    @endif
</x-app-layout>
