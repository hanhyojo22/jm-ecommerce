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
                @if (session('status'))<div class="mt-4 rounded-lg border border-success/20 bg-success/10 px-4 py-3 text-sm font-medium text-success" role="status">{{ session('status') }}</div>@endif
                <x-marketplace.account-profile />
                <section class="content-card mt-4 p-5 sm:p-6" aria-labelledby="notification-settings">
                    <h2 id="notification-settings" class="text-lg font-semibold text-ink">Notifications</h2>
                    <form method="POST" action="{{ route('profile.notifications.update') }}" class="mt-3">@csrf @method('PATCH')
                        <label class="flex min-h-11 items-start gap-3">
                            <input type="checkbox" name="cart_reminders" value="1" @checked(auth()->user()->cart_reminders) onchange="this.form.requestSubmit()" class="mt-1 rounded text-brand focus:ring-brand">
                            <span><span class="block text-sm font-medium text-ink">Cart reminders</span><span class="block text-sm text-muted">Remind me when items sit in my cart for a day.</span></span>
                        </label>
                        <noscript><x-primary-button class="mt-3">{{ __('Save') }}</x-primary-button></noscript>
                    </form>
                </section>
            </div>
        </x-marketplace.account-shell>
    @endif
</x-app-layout>
