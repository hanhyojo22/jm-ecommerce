<x-app-layout>
    <x-marketplace.account-shell active="addresses" title="Delivery Addresses">
        <div class="space-y-5">
            <div><h1 class="text-2xl font-semibold tracking-[-0.02em] text-ink">Delivery Addresses</h1><p class="mt-1 text-sm text-muted">Save addresses for faster checkout. Past orders keep their original delivery details.</p></div>
            @if (session('status'))<p class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</p>@endif
            <section class="rounded-2xl border border-line bg-white p-5 sm:p-[26px]" aria-labelledby="saved-addresses-heading">
                <h2 id="saved-addresses-heading" class="text-lg font-semibold text-ink">Saved addresses</h2>
                <div class="mt-4 divide-y divide-line">
                    @forelse ($addresses as $address)
                        <div class="flex flex-wrap items-start justify-between gap-3 py-4 first:pt-0">
                            <div><p class="font-semibold text-ink">{{ $address->recipient_name }}</p><p class="mt-1 text-sm text-muted">{{ $address->address_line }}, {{ $address->city }}@if ($address->province), {{ $address->province }}@endif @if ($address->postal_code){{ $address->postal_code }}@endif</p><p class="mt-1 text-sm text-muted">{{ $address->phone }}</p></div>
                            <div class="flex items-center gap-3"><a href="{{ route('delivery-addresses.edit', $address) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:text-brand-hover">Edit</a><form method="POST" action="{{ route('delivery-addresses.destroy', $address) }}" onsubmit="return confirm('Remove this saved address?')">@csrf @method('DELETE')<button type="submit" class="min-h-11 text-sm font-semibold text-danger hover:underline">Remove</button></form></div>
                        </div>
                    @empty
                        <p class="py-5 text-sm text-muted">No saved addresses yet.</p>
                    @endforelse
                </div>
            </section>
            <section class="rounded-2xl border border-line bg-white p-5 sm:p-[26px]" aria-labelledby="add-address-heading">
                <h2 id="add-address-heading" class="mb-5 text-lg font-semibold text-ink">Add an address</h2>
                @include('marketplace.delivery-addresses.form', ['action' => route('delivery-addresses.store'), 'method' => 'POST', 'address' => null, 'submitLabel' => 'Save address'])
            </section>
        </div>
    </x-marketplace.account-shell>
</x-app-layout>
