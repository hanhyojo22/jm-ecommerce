<x-app-layout>
    <div class="page-shell min-h-[calc(100dvh-4.5rem)]">
        <div class="page-container max-w-[1320px]">
            <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-sm text-muted">
                <a href="{{ route('home') }}" class="font-medium transition hover:text-brand-hover">Home</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page" class="font-semibold text-ink">My account</span>
            </nav>

            <p class="mt-4 text-sm text-muted">Manage your JMMart identity, sign-in details, and security in one secure place.</p>

            <div class="mt-6 flex justify-center">
                <div data-clerk-component="user-profile" class="w-full max-w-[880px] min-w-0" aria-busy="true" aria-live="polite">
                    <p class="py-6 text-sm text-muted">Loading account settings...</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
