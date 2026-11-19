<x-app-layout :error-summary="false">
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <h1 class="sc-title">Branches</h1>
                <p class="sc-subtitle">{{ $branches->count() }} {{ Str::plural('location', $branches->count()) }}</p>
            </div>
            <div class="sc-page-actions"><a href="{{ route('seller.branches.create') }}" class="seller-primary-button">Add branch</a></div>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container seller-workspace-page sc-stack">
            @if (session('status'))<p class="sc-notice" data-tone="success" role="status">{{ session('status') }}</p>@endif

            <section class="sc-branch-grid" aria-label="Branches">
                @foreach ($branches as $branch)
                    <article class="sc-card sc-branch-card">
                        <div class="flex items-center justify-between gap-3">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 9.5 5 4h14l2 5.5M4 10h16v10H4zM9 20v-6h6v6M3 9.5a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/></svg>
                            <span class="sc-badge" data-tone="{{ $branch->status === 'active' ? 'success' : ($branch->status === 'suspended' ? 'danger' : 'neutral') }}">{{ ['active' => 'Open', 'inactive' => 'Closed', 'suspended' => 'Suspended by Everyday Pharmacy'][$branch->status] ?? $branch->status }}</span>
                        </div>
                        <div><h2>{{ $branch->name }}</h2><p>{{ $branch->address_line }}, {{ $branch->city }}</p></div>
                        <dl class="sc-branch-facts">
                            <div><dt>Inventory</dt><dd>{{ number_format($branch->branch_stocks_count) }} stocked {{ Str::plural('item', $branch->branch_stocks_count) }}</dd></div>
                            <div><dt>Map location</dt><dd>{{ $branch->latitude === null ? 'No map pin yet' : 'On the map' }}</dd></div>
                            <div class="col-span-2"><dt>Opening hours</dt><dd>{{ $branch->opening_time === null ? 'Open 24 hours' : substr($branch->opening_time, 0, 5).' - '.substr($branch->closing_time, 0, 5) }}</dd></div>
                        </dl>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('seller.store-settings.edit', ['branch' => $branch->id]) }}" class="seller-outline-button flex-1">Manage branch</a>
                            <a href="{{ route('seller.branch-stock.index', ['branch' => $branch->id]) }}" class="seller-outline-button">Stock</a>
                            <a href="{{ route('seller.opening-hours.edit', ['branch' => $branch->id]) }}" class="seller-outline-button">Hours</a>
                        </div>
                    </article>
                @endforeach
            </section>
            <details class="sc-card sc-settings-disclosure" @if ($errors->hasAny(['delivery_fee', 'free_delivery_minimum'])) open @endif>
                <summary>Delivery fee<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
            <form method="POST" action="{{ route('seller.delivery-fee.update') }}" class="flex flex-wrap items-end gap-4 p-6" aria-label="Delivery fee">
                @csrf
                @method('PATCH')
                <p class="w-full text-sm text-muted">The same at every branch. Pickup is always free.</p>
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

            </details>
        </div>
    </div>
</x-app-layout>
