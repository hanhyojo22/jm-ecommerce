<x-app-layout>
    <x-marketplace.account-shell active="addresses">
        <header class="min-w-0">
            <h1 class="text-3xl font-semibold tracking-tight text-ink">Delivery addresses</h1>
            <p class="mt-1 text-meta text-muted">@if ($addresses->isNotEmpty()){{ $addresses->count() }} saved<span aria-hidden="true" class="px-1.5">·</span>@endif Pick one at checkout. Past orders keep the address they were sent to.</p>
        </header>

        @if (session('status'))
            <p role="status" class="mt-4 rounded-md border border-[var(--color-success-line)] bg-[var(--color-success-wash)] px-3.5 py-2.5 text-meta font-medium text-success">{{ session('status') }}</p>
        @endif

        <section class="mt-6" aria-labelledby="saved-addresses-heading">
            <h2 id="saved-addresses-heading" class="sr-only">Saved addresses</h2>
            @if ($addresses->isEmpty())
                <div class="account-card px-6 py-10 text-center">
                    <span class="mx-auto grid size-12 place-items-center rounded-full bg-[var(--color-sunken)] text-muted ring-1 ring-inset ring-[var(--color-line-soft)]" aria-hidden="true"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6 7-12a7 7 0 1 0-14 0c0 6 7 12 7 12Z" /><circle cx="12" cy="9" r="2" /></svg></span>
                    <p class="mt-4 text-lg font-semibold text-ink">No saved addresses yet</p>
                    <p class="mx-auto mt-2 max-w-sm text-meta text-muted">Add one below and it will be ready to pick the next time you check out.</p>
                </div>
            @else
                <ul class="grid gap-4 sm:grid-cols-2">
                    @foreach ($addresses as $address)
                        <li class="account-card flex min-w-0 flex-col">
                            <div class="flex-1 px-4 py-4">
                                <p class="truncate text-sm font-semibold text-ink">{{ $address->recipient_name }}</p>
                                <p class="mt-1.5 flex items-start gap-2 text-meta text-ink">
                                    <svg class="mt-0.5 size-4 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-6 7-12a7 7 0 1 0-14 0c0 6 7 12 7 12Z" /><circle cx="12" cy="9" r="2" /></svg>
                                    <span class="min-w-0 break-words">{{ $address->address_line }}, {{ $address->city }}@if ($address->province), {{ $address->province }}@endif @if ($address->postal_code){{ $address->postal_code }}@endif</span>
                                </p>
                                <p class="mt-1.5 flex items-center gap-2 text-meta text-muted">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="7" y="2.5" width="10" height="19" rx="2" /><path d="M11 18h2" /></svg>
                                    <span class="tabular-nums">{{ $address->phone }}</span>
                                </p>
                            </div>
                            <div class="flex items-center justify-end gap-1 border-t border-line-soft bg-[#fbfcfe] px-2.5 py-1">
                                <a href="{{ route('delivery-addresses.edit', $address) }}" class="inline-flex min-h-11 items-center rounded-md px-2.5 text-xs font-semibold text-brand transition-colors hover:bg-[var(--color-info-wash)] hover:text-brand-hover lg:min-h-10" aria-label="Edit address for {{ $address->recipient_name }}">Edit</a>
                                <form method="POST" action="{{ route('delivery-addresses.destroy', $address) }}" onsubmit="return confirm('Remove this saved address? Past orders keep their delivery details.')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inline-flex min-h-11 items-center rounded-md px-2.5 text-xs font-semibold text-danger transition-colors hover:bg-[var(--color-danger-wash)] lg:min-h-10" aria-label="Remove address for {{ $address->recipient_name }}">Remove</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="account-card mt-6 px-4 py-5 sm:px-6" aria-labelledby="add-address-heading">
            <h2 id="add-address-heading" class="text-ink">Add an address</h2>
            <p class="mt-1 text-meta text-muted">Use the details the rider will need at the door.</p>
            <div class="mt-5">
                @include('marketplace.delivery-addresses.form', ['action' => route('delivery-addresses.store'), 'method' => 'POST', 'address' => null, 'submitLabel' => 'Save address'])
            </div>
        </section>
    </x-marketplace.account-shell>
</x-app-layout>
