<x-app-layout>
    @if (auth()->user()->isPlatformAdministrator())
        <div class="page-shell min-h-[calc(100dvh-4.5rem)]">
            <div class="page-container">
                <div class="max-w-[880px]">
                    <h1 class="customer-page-title text-2xl font-bold tracking-tight text-ink">Account settings</h1>
                    <x-marketplace.account-profile />
                </div>
            </div>
        </div>
    @else
        <x-marketplace.account-shell active="settings" title="Account settings">
            <div>
                <h1 class="text-3xl font-semibold tracking-[-0.03em] text-ink">Account settings</h1>
                <x-marketplace.account-profile />
            </div>
        </x-marketplace.account-shell>
    @endif
</x-app-layout>
