@props(['user'])

<header class="sticky top-0 z-40 border-b border-slate-200 bg-white">
    <div class="mx-auto flex h-[72px] max-w-[88rem] items-center gap-4 px-4 sm:px-6 lg:px-8">
        <a href="{{ route('catalog.index') }}" class="flex shrink-0 items-center gap-2.5 rounded-lg focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2" aria-label="JMMart marketplace home">
            <x-application-logo class="size-9 fill-current text-emerald-700" />
            <span class="text-lg font-bold tracking-[-0.035em] text-slate-950">JMMart</span>
        </a>

        <span class="hidden h-8 w-px bg-slate-200 sm:block" aria-hidden="true"></span>
        <nav class="flex min-w-0 items-center gap-2 text-sm" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}" class="hidden shrink-0 rounded text-slate-600 hover:text-emerald-800 focus-visible:ring-2 focus-visible:ring-emerald-700 sm:inline">Admin workspace</a>
            <svg class="hidden size-4 shrink-0 text-slate-400 sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
            <span class="truncate font-semibold text-slate-900" aria-current="page">Moderation</span>
        </nav>

        <details class="group relative ml-auto shrink-0">
            <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-xl px-2 text-sm font-semibold text-slate-700 hover:bg-emerald-50 focus-visible:ring-2 focus-visible:ring-emerald-700 [&::-webkit-details-marker]:hidden">
                <span class="grid size-8 place-items-center rounded-full bg-slate-100 text-xs font-bold text-slate-800">{{ Str::upper(Str::substr($user->name, 0, 1)) }}</span>
                <span class="hidden sm:inline">{{ $user->name }}</span>
                <svg class="size-4 text-slate-500 transition group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
            </summary>
            <div class="absolute right-0 z-50 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-lg shadow-slate-900/10">
                <a href="{{ route('profile.edit') }}" class="flex min-h-10 items-center rounded-lg px-3 text-sm font-medium text-slate-700 hover:bg-emerald-50 hover:text-emerald-900 focus-visible:ring-2 focus-visible:ring-emerald-700">Account settings</a>
                <button type="button" data-clerk-sign-out class="flex min-h-10 w-full items-center rounded-lg px-3 text-left text-sm font-medium text-slate-600 hover:bg-rose-50 hover:text-rose-800 focus-visible:ring-2 focus-visible:ring-emerald-700">Sign out</button>
            </div>
        </details>
    </div>
</header>
