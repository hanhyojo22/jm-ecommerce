<x-app-layout>
    @if (auth()->user()->isPlatformAdministrator())
        <div class="page-shell min-h-[calc(100dvh-4.5rem)]">
            <div class="page-container">
                <div class="max-w-[800px]">
                    <h1 class="customer-page-title text-2xl font-bold tracking-tight text-ink">Account settings</h1>
                    <p class="mt-2 text-sm text-muted">Manage your JMMart identity, sign-in details, and security in one secure place.</p>
                    <div data-clerk-component="user-profile" class="mt-4 min-w-0" aria-busy="true" aria-live="polite">
                        <p class="py-3 text-sm text-muted">Loading account settings...</p>
                    </div>
                </div>
            </div>
        </div>
    @else
        <x-marketplace.account-shell active="settings" title="Account settings">
            <div>
                <h1 class="text-3xl font-semibold tracking-[-0.03em] text-ink">Account settings</h1>
                <p class="mt-2 text-sm leading-6 text-muted">Manage your JMMart identity, sign-in details, and security in one secure place.</p>
                <div data-clerk-component="user-profile" class="mt-4 min-w-0" aria-busy="true" aria-live="polite">
                    <p class="py-3 text-sm text-muted">Loading account settings...</p>
                </div>
            </div>
        </x-marketplace.account-shell>
    @endif
</x-app-layout>
