<x-app-layout>
    <x-marketplace.account-shell active="addresses">
        <a href="{{ route('delivery-addresses.index') }}" class="-ml-1.5 inline-flex min-h-11 items-center gap-1 rounded-md px-1.5 text-meta font-semibold text-brand hover:text-brand-hover lg:min-h-10">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/></svg>
            Delivery addresses
        </a>
        <header class="mt-2 min-w-0">
            <h1 class="text-3xl font-semibold tracking-tight text-ink">Edit delivery address</h1>
            <p class="mt-1 text-meta text-muted">Changes apply to your next checkout. Orders already placed keep the address they were sent to.</p>
        </header>
        <section class="account-card mt-6 px-4 py-5 sm:px-6" aria-label="Address details">
            @include('marketplace.delivery-addresses.form', ['action' => route('delivery-addresses.update', $address), 'method' => 'PATCH', 'address' => $address, 'submitLabel' => 'Save changes', 'cancelUrl' => route('delivery-addresses.index')])
        </section>
    </x-marketplace.account-shell>
</x-app-layout>
