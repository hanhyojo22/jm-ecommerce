<x-app-layout>
    <x-marketplace.account-shell active="addresses">
        <div class="rounded-2xl border border-line bg-white p-5 sm:p-[26px]">
            <a href="{{ route('delivery-addresses.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:text-brand-hover"><span aria-hidden="true">←</span> Delivery Addresses</a>
            <h1 class="mb-5 mt-3 text-2xl font-semibold tracking-[-0.02em] text-ink">Edit Delivery Address</h1>
            @include('marketplace.delivery-addresses.form', ['action' => route('delivery-addresses.update', $address), 'method' => 'PATCH', 'address' => $address, 'submitLabel' => 'Save changes'])
        </div>
    </x-marketplace.account-shell>
</x-app-layout>
