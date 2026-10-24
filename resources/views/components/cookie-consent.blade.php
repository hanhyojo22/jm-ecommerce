@php($cookiePreferences = \App\Support\CookiePreferences::fromRequest(request()))

<div data-cookie-consent data-functional="{{ $cookiePreferences['functional'] ?? false ? 'true' : 'false' }}" data-save-url="{{ route('cookie-preferences.store') }}" data-csrf="{{ csrf_token() }}">
    @if ($cookiePreferences === null)
        <section data-cookie-banner aria-labelledby="cookie-banner-title" class="fixed inset-x-3 {{ request()->routeIs('home') ? 'bottom-20' : 'bottom-3' }} z-[90] mx-auto max-h-[calc(100dvh-6rem)] max-w-[480px] overflow-y-auto rounded-xl border border-line bg-white p-3 shadow-lg sm:bottom-3">
            <div class="flex items-center gap-2">
                <span class="grid size-7 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand-hover" aria-hidden="true">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 3a3 3 0 0 0 3 3 3 3 0 0 0 3 3 3 3 0 0 0 3 3M8 11h.01M12 16h.01M16 13h.01M10 7h.01" stroke-linecap="round"/></svg>
                </span>
                <h2 id="cookie-banner-title" class="text-base font-semibold text-ink">Your privacy matters</h2>
            </div>
            <p class="mt-1 text-sm leading-5 text-muted">Sign-in, cart and checkout need cookies. Choose whether to save browsing preferences. <a href="{{ route('cookie-policy') }}" class="font-semibold text-brand underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Cookie Policy</a> · <a href="{{ route('privacy-policy') }}" class="font-semibold text-brand underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Privacy Policy</a></p>
            <div class="mt-2 grid grid-cols-3 gap-2">
                <button type="button" data-cookie-action="reject" class="relative min-h-9 rounded-md border border-line px-1 py-1 text-xs font-semibold leading-4 text-ink before:absolute before:-inset-y-1 before:inset-x-0 before:content-[''] hover:bg-canvas focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Reject Optional</button>
                <button type="button" data-cookie-action="customize" class="relative min-h-9 rounded-md border border-line px-1 py-1 text-xs font-semibold leading-4 text-ink before:absolute before:-inset-y-1 before:inset-x-0 before:content-[''] hover:bg-canvas focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Customize</button>
                <button type="button" data-cookie-action="accept" class="relative min-h-9 rounded-md bg-brand px-1 py-1 text-xs font-semibold leading-4 text-white before:absolute before:-inset-y-1 before:inset-x-0 before:content-[''] hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand focus-visible:outline-offset-2">Accept All</button>
            </div>
            <p data-cookie-error role="alert" class="mt-2 hidden text-sm text-red-700"></p>
        </section>
    @endif

    <dialog data-cookie-dialog aria-labelledby="cookie-dialog-title" class="w-[calc(100%-2rem)] max-w-md max-h-[85dvh] overflow-y-auto rounded-xl border border-line bg-white p-0 text-ink shadow-2xl backdrop:bg-slate-950/50">
        <div class="border-b border-line px-4 py-3">
            <div class="flex items-center justify-between gap-3">
                <h2 id="cookie-dialog-title" class="text-lg font-semibold">Cookie preferences</h2>
                <button type="button" data-cookie-action="close" aria-label="Close cookie preferences" class="grid size-11 shrink-0 place-items-center rounded-lg text-xl hover:bg-canvas focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">×</button>
            </div>
            <p class="text-sm leading-5 text-muted">Choose what JMMart may remember. Essential services stay on.</p>
        </div>
        <div class="divide-y divide-line px-4">
            <section class="py-3">
                <div class="flex items-center justify-between gap-3"><h3 class="text-sm font-semibold">Strictly Necessary</h3><span class="text-xs font-semibold text-brand">Always active</span></div>
                <p class="mt-1 text-sm leading-5 text-muted">Sign-in, security, cart, checkout, and temporary chat drafts.</p>
            </section>
            <label class="flex cursor-pointer items-start justify-between gap-3 py-3">
                <span><span class="block text-sm font-semibold">Functional</span><span class="mt-1 block text-sm leading-5 text-muted">Recently viewed products and Seller Center settings.</span></span>
                <input type="checkbox" name="functional" class="mt-1 shrink-0 rounded border-line text-brand focus:ring-brand" @checked($cookiePreferences['functional'] ?? false)>
            </label>
            <section class="py-3">
                <div class="flex items-center justify-between gap-3"><h3 class="text-sm font-semibold">Analytics</h3><input type="checkbox" name="analytics" disabled aria-label="Analytics unavailable" class="shrink-0 rounded border-line"></div>
                <p class="mt-1 text-sm leading-5 text-muted">Not in use. No analytics tracker is installed.</p>
            </section>
            <section class="py-3">
                <div class="flex items-center justify-between gap-3"><h3 class="text-sm font-semibold">Marketing</h3><input type="checkbox" name="marketing" disabled aria-label="Marketing unavailable" class="shrink-0 rounded border-line"></div>
                <p class="mt-1 text-sm leading-5 text-muted">Not in use. No advertising tracker is installed.</p>
            </section>
            <p class="py-3 text-xs leading-5 text-muted">Saved for up to 180 days. Read our <a href="{{ route('cookie-policy') }}" class="font-semibold text-brand underline">Cookie Policy</a> and <a href="{{ route('privacy-policy') }}" class="font-semibold text-brand underline">Privacy Policy</a>.</p>
            <p data-cookie-error role="alert" class="hidden pb-3 text-sm text-red-700"></p>
        </div>
        <div class="flex justify-end gap-2 border-t border-line px-4 py-3">
            <button type="button" data-cookie-action="reject" class="min-h-11 rounded-lg border border-line px-3 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Reject Optional</button>
            <button type="button" data-cookie-action="save" class="min-h-11 rounded-lg bg-brand px-3 py-2 text-sm font-semibold text-white hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand focus-visible:outline-offset-2">Save Preferences</button>
        </div>
    </dialog>
</div>
