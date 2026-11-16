<x-app-layout :error-summary="false">
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <h1 class="sc-title">Branches</h1>
                <p class="sc-subtitle">Every branch customers can buy from. A branch shows in the app once it is open and has a map pin.</p>
            </div>
            <div class="sc-page-actions"><a href="{{ route('seller.branches.create') }}" class="seller-primary-button">Add branch</a></div>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container seller-workspace-page sc-stack">
            @if (session('status'))<p class="sc-notice" data-tone="success" role="status">{{ session('status') }}</p>@endif

            <form method="POST" action="{{ route('seller.delivery-fee.update') }}" class="sc-card flex flex-wrap items-end gap-4 p-4" aria-labelledby="delivery-fee-heading">
                @csrf
                @method('PATCH')
                <h2 id="delivery-fee-heading" class="w-full font-semibold text-ink">Delivery fee <span class="font-normal text-muted">— the same at every branch; pickup is always free</span></h2>
                <div>
                    <label for="delivery_fee" class="block text-sm font-semibold text-ink">Fee per order (₱)</label>
                    <input id="delivery_fee" name="delivery_fee" type="number" inputmode="decimal" step="0.01" min="0" required value="{{ old('delivery_fee', $tenant->delivery_fee) }}" class="field-control mt-2 w-36 text-base lg:text-sm">
                    <x-input-error :messages="$errors->get('delivery_fee')" class="mt-2" />
                </div>
                <div>
                    <label for="free_delivery_minimum" class="block text-sm font-semibold text-ink">Free above (₱, optional)</label>
                    <input id="free_delivery_minimum" name="free_delivery_minimum" type="number" inputmode="decimal" step="0.01" min="0.01" value="{{ old('free_delivery_minimum', $tenant->free_delivery_minimum) }}" class="field-control mt-2 w-36 text-base lg:text-sm">
                    <x-input-error :messages="$errors->get('free_delivery_minimum')" class="mt-2" />
                </div>
                <button type="submit" class="seller-outline-button min-h-11">Save delivery fee</button>
            </form>

            <ul class="sc-card divide-y divide-line" aria-label="Branches">
                @foreach ($branches as $branch)
                    <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <p class="font-semibold text-ink">{{ $branch->name }}</p>
                            <p class="text-sm text-muted">
                                {{ $branch->address_line }}, {{ $branch->city }}
                                &middot; {{ ['active' => 'Open', 'inactive' => 'Closed', 'suspended' => 'Suspended by JMMart'][$branch->status] ?? $branch->status }}
                                &middot; {{ $branch->latitude === null ? 'No map pin yet' : 'On the map' }}
                                &middot; {{ number_format($branch->branch_stocks_count) }} stocked {{ Str::plural('item', $branch->branch_stocks_count) }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('seller.branch-stock.index', ['branch' => $branch->id]) }}" class="seller-outline-button min-h-11">Stock</a>
                            <a href="{{ route('seller.opening-hours.edit', ['branch' => $branch->id]) }}" class="seller-outline-button min-h-11">Hours</a>
                            <a href="{{ route('seller.store-settings.edit', ['branch' => $branch->id]) }}" class="seller-outline-button min-h-11">Edit details</a>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-app-layout>
