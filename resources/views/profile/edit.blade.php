<x-app-layout>
    @if (auth()->user()->isPlatformAdministrator())
        <div class="page-shell min-h-[calc(100dvh-4.5rem)]">
            <div class="page-container">
                <div class="max-w-[880px]">
                    <h1 class="customer-page-title text-2xl font-semibold tracking-tight text-ink">Account settings</h1>
                    <x-marketplace.account-profile />
                </div>
            </div>
        </div>
    @else
        <x-marketplace.account-shell active="settings">
            <div>
                <h1 class="text-3xl font-semibold tracking-[-0.03em] text-ink">Account settings</h1>
                <p class="mt-1 text-meta text-muted">Your sign-in details, profile, and which notifications JMMart sends you.</p>
                @if (session('status'))<p role="status" class="mt-4 rounded-md border border-[var(--color-success-line)] bg-[var(--color-success-wash)] px-3.5 py-2.5 text-meta font-medium text-success">{{ session('status') }}</p>@endif
                <x-marketplace.account-profile />
                <section class="account-card mt-4" aria-labelledby="notification-settings">
                    <div class="border-b border-line px-4 py-3.5 sm:px-6">
                        <h2 id="notification-settings" class="text-ink">Notifications</h2>
                        <p class="mt-0.5 text-xs text-muted">Order, delivery, and message updates always reach you. These are optional.</p>
                    </div>
                    <form method="POST" action="{{ route('profile.notifications.update') }}">@csrf @method('PATCH')
                        <label class="flex min-h-11 cursor-pointer items-center justify-between gap-4 px-4 py-4 sm:px-6">
                            <span class="min-w-0"><span class="block text-meta font-semibold text-ink">Cart reminders</span><span class="mt-0.5 block text-xs leading-5 text-muted">A nudge when items sit in your cart for a day without changes.</span></span>
                            <span class="relative inline-flex shrink-0">
                                <input type="checkbox" role="switch" name="cart_reminders" value="1" @checked(auth()->user()->cart_reminders) onchange="this.form.requestSubmit()" class="peer absolute inset-0 size-full cursor-pointer appearance-none rounded-full opacity-0">
                                <span class="pointer-events-none h-6 w-11 rounded-full bg-[var(--color-border-strong)] transition-colors peer-checked:bg-brand peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand motion-reduce:transition-none" aria-hidden="true"></span>
                                <span class="pointer-events-none absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow-[0_1px_2px_rgb(24_44_72/25%)] transition-transform peer-checked:translate-x-5 motion-reduce:transition-none" aria-hidden="true"></span>
                            </span>
                        </label>
                        <noscript><div class="border-t border-line-soft px-4 py-3 sm:px-6"><x-primary-button>{{ __('Save') }}</x-primary-button></div></noscript>
                    </form>
                </section>
            </div>
        </x-marketplace.account-shell>
    @endif
</x-app-layout>
