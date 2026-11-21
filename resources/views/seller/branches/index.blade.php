@php
    $totalBranches = $branches->count();
    $openBranches = $branches->where('status', 'active')->count();
    $closedBranches = $branches->where('status', 'inactive')->count();
    $suspendedBranches = $branches->where('status', 'suspended')->count();
    $pinnedBranches = $branches->filter(fn ($branch): bool => $branch->latitude !== null && $branch->longitude !== null)->count();
    $unpinnedBranches = $totalBranches - $pinnedBranches;
    $totalStockedItems = (int) $branches->sum('branch_stocks_count');
    $deliveryFeeFormatted = number_format((float) $tenant->delivery_fee, 2);
    $freeAboveFormatted = $tenant->free_delivery_minimum !== null ? number_format((float) $tenant->free_delivery_minimum, 2) : null;
    $statusParts = array_filter([
        $openBranches > 0 ? $openBranches.' open' : null,
        $closedBranches > 0 ? $closedBranches.' closed' : null,
        $suspendedBranches > 0 ? $suspendedBranches.' suspended' : null,
    ]);
@endphp

<x-app-layout :error-summary="false">
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <h1 class="sc-title">Branches</h1>
                <p class="sc-subtitle">{{ $totalBranches }} {{ Str::plural('location', $totalBranches) }} across {{ $tenant->name }}. Manage map pins, inventory counts, hours, and delivery fees.</p>
            </div>
            <div class="sc-page-actions">
                <a href="{{ route('seller.branches.create') }}" class="seller-primary-button">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    Add branch
                </a>
            </div>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container seller-workspace-page sc-stack" x-data="{ filter: 'all' }">
            @if (session('status'))<p class="sc-notice" data-tone="success" role="status">{{ session('status') }}</p>@endif

            @if ($totalBranches > 0)
                <dl class="sc-metrics" data-columns="4" aria-label="Branch network summary">
                    <div class="sc-metric">
                        <dt>Locations</dt>
                        <dd>
                            <span class="sc-metric-value">{{ number_format($totalBranches) }}</span>
                            <span class="sc-metric-context">{{ $statusParts ? implode(' · ', $statusParts) : 'No active branches yet' }}</span>
                        </dd>
                    </div>
                    <div class="sc-metric">
                        <dt>Map coverage</dt>
                        <dd>
                            <span class="sc-metric-value {{ $unpinnedBranches === 0 ? 'is-accent' : '' }}">{{ $pinnedBranches }} / {{ $totalBranches }}</span>
                            <span class="sc-metric-context">{{ $unpinnedBranches > 0 ? $unpinnedBranches.' '.Str::plural('branch', $unpinnedBranches).' still missing a map pin' : 'All branches pinned for nearby search' }}</span>
                        </dd>
                    </div>
                    <div class="sc-metric">
                        <dt>Stocked items</dt>
                        <dd>
                            <span class="sc-metric-value">{{ number_format($totalStockedItems) }}</span>
                            <span class="sc-metric-context">Tracked inventory {{ Str::plural('line', $totalStockedItems) }} across branches</span>
                        </dd>
                    </div>
                    <div class="sc-metric">
                        <dt>Delivery fee</dt>
                        <dd>
                            <span class="sc-metric-value">₱{{ $deliveryFeeFormatted }}</span>
                            <span class="sc-metric-context">{{ $freeAboveFormatted ? 'Free above ₱'.$freeAboveFormatted.' · Pickup free' : 'Same at every branch · Pickup free' }}</span>
                        </dd>
                    </div>
                </dl>
            @endif

            @if ($totalBranches > 1 && ($closedBranches > 0 || $unpinnedBranches > 0))
                <div class="sc-branch-filters" role="group" aria-label="Filter branches">
                    <button type="button" class="sc-branch-filter" :aria-pressed="(filter === 'all').toString()" :class="filter === 'all' && 'is-active'" @click="filter = 'all'">All <span>{{ $totalBranches }}</span></button>
                    <button type="button" class="sc-branch-filter" :aria-pressed="(filter === 'open').toString()" :class="filter === 'open' && 'is-active'" @click="filter = 'open'">Open <span>{{ $openBranches }}</span></button>
                    @if ($closedBranches > 0)
                        <button type="button" class="sc-branch-filter" :aria-pressed="(filter === 'closed').toString()" :class="filter === 'closed' && 'is-active'" @click="filter = 'closed'">Closed <span>{{ $closedBranches }}</span></button>
                    @endif
                    @if ($unpinnedBranches > 0)
                        <button type="button" class="sc-branch-filter is-warning" :aria-pressed="(filter === 'unpinned').toString()" :class="filter === 'unpinned' && 'is-active'" @click="filter = 'unpinned'">Needs map pin <span>{{ $unpinnedBranches }}</span></button>
                    @endif
                </div>
            @endif

            <section class="sc-branch-grid" aria-label="Branches">
                @forelse ($branches as $branch)
                    @php
                        $tone = $branch->status === 'active' ? 'success' : ($branch->status === 'suspended' ? 'danger' : 'neutral');
                        $statusLabel = ['active' => 'Open', 'inactive' => 'Closed', 'suspended' => 'Suspended by Everyday Pharmacy'][$branch->status] ?? $branch->status;
                        $hasPin = $branch->latitude !== null && $branch->longitude !== null;
                        $hoursLabel = $branch->opening_time === null
                            ? 'Open 24 hours'
                            : \Carbon\Carbon::parse($branch->opening_time)->format('g:i A').' – '.\Carbon\Carbon::parse($branch->closing_time)->format('g:i A');
                    @endphp
                    <article class="sc-card sc-branch-card"
                        x-show="filter === 'all' || (filter === 'open' && @js($branch->status === 'active')) || (filter === 'closed' && @js($branch->status === 'inactive')) || (filter === 'unpinned' && @js(! $hasPin))">
                        <div class="flex items-start justify-between gap-3">
                            <span class="sc-branch-icon" data-tone="{{ $tone }}" aria-hidden="true">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M3 9.5 5 4h14l2 5.5M4 10h16v10H4zM9 20v-6h6v6M3 9.5a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/></svg>
                            </span>
                            <span class="sc-badge" data-tone="{{ $tone }}">{{ $statusLabel }}</span>
                        </div>

                        <div class="min-w-0">
                            <h2 class="truncate" title="{{ $branch->name }}">{{ $branch->name }}</h2>
                            <p class="sc-branch-address">
                                <svg class="mt-0.5 size-3.5 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 21s-7-5.7-7-11a7 7 0 1 1 14 0c0 5.3-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                                <span>{{ $branch->address_line }}, {{ $branch->city }}</span>
                            </p>
                            @if ($branch->phone)
                                <p class="sc-branch-phone">
                                    <svg class="size-3.5 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z"/></svg>
                                    <span>{{ $branch->phone }}</span>
                                </p>
                            @endif
                        </div>

                        <dl class="sc-branch-facts">
                            <div>
                                <dt>Inventory</dt>
                                <dd class="tabular-nums">{{ number_format($branch->branch_stocks_count) }} stocked {{ Str::plural('item', $branch->branch_stocks_count) }}</dd>
                            </div>
                            <div>
                                <dt>Map location</dt>
                                <dd>
                                    @if ($hasPin)
                                        <span class="sc-branch-pin is-pinned">On the map</span>@if ($branch->delivery_radius_km)<span class="text-xs text-muted"> · {{ $branch->delivery_radius_km }} km</span>@endif
                                    @else
                                        <a href="{{ route('seller.store-settings.edit', ['branch' => $branch->id]) }}#map-location-heading" class="sc-branch-pin is-missing">No map pin yet</a>
                                    @endif
                                </dd>
                            </div>
                            <div class="col-span-2 sc-branch-hours">
                                <dt>Opening hours</dt>
                                <dd>{{ $hoursLabel }}</dd>
                            </div>
                        </dl>

                        <div class="sc-branch-actions">
                            <a href="{{ route('seller.store-settings.edit', ['branch' => $branch->id]) }}" class="seller-outline-button flex-1" aria-label="Manage {{ $branch->name }}">Manage branch</a>
                            <a href="{{ route('seller.branch-stock.index', ['branch' => $branch->id]) }}" class="seller-outline-button" aria-label="Stock at {{ $branch->name }}">
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/></svg>
                                Stock
                            </a>
                            <a href="{{ route('seller.opening-hours.edit', ['branch' => $branch->id]) }}" class="seller-outline-button" aria-label="Opening hours for {{ $branch->name }}">
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                Hours
                            </a>
                        </div>
                    </article>
                @empty
                    <div class="sc-card sc-card-pad col-span-full text-center">
                        <span class="sc-branch-icon mx-auto" data-tone="neutral" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M3 9.5 5 4h14l2 5.5M4 10h16v10H4zM9 20v-6h6v6"/></svg>
                        </span>
                        <h2 class="mt-4 text-base font-semibold text-ink">No branches yet</h2>
                        <p class="mx-auto mt-1 max-w-md text-sm text-muted">Add your first pharmacy branch to pin it on the map, set opening hours, and track branch stock.</p>
                        <div class="mt-5">
                            <a href="{{ route('seller.branches.create') }}" class="seller-primary-button">Add branch</a>
                        </div>
                    </div>
                @endforelse
            </section>

            <details class="sc-card sc-settings-disclosure" @if ($errors->hasAny(['delivery_fee', 'free_delivery_minimum'])) open @endif>
                <summary>
                    <span class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="inline-flex items-center gap-2.5">
                            <svg class="size-4 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M10 17h4V5H2v12h3m15 0h2v-3.34a4 4 0 0 0-1.17-2.83L19 9h-5v8h1"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
                            <span>Delivery fee</span>
                        </span>
                        <span class="text-xs font-normal tabular-nums text-muted">₱{{ $deliveryFeeFormatted }}{{ $freeAboveFormatted ? ' · Free above ₱'.$freeAboveFormatted : ' · Pickup always free' }}</span>
                    </span>
                    <svg class="shrink-0 transition-transform" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </summary>
                <div class="sc-disclosure-body">
                    <form method="POST" action="{{ route('seller.delivery-fee.update') }}"
                        x-data="{ saving: false }" @submit="saving = true"
                        class="flex flex-wrap items-end gap-4" aria-label="Delivery fee">
                        @csrf
                        @method('PATCH')
                        <p class="w-full text-sm text-muted">The same at every branch. Pickup is always free.</p>
                        <div class="min-w-0">
                            <label for="delivery_fee" class="block text-sm font-semibold text-ink">Fee per order (₱)</label>
                            <div class="pe-affix mt-2 w-40">
                                <span aria-hidden="true">₱</span>
                                <input id="delivery_fee" name="delivery_fee" type="number" inputmode="decimal" step="0.01" min="0" required value="{{ old('delivery_fee', $tenant->delivery_fee) }}" class="pe-input text-base lg:text-sm">
                            </div>
                            <x-input-error :messages="$errors->get('delivery_fee')" class="mt-2" />
                        </div>
                        <div class="min-w-0">
                            <label for="free_delivery_minimum" class="block text-sm font-semibold text-ink">Free above (₱, optional)</label>
                            <div class="pe-affix mt-2 w-44">
                                <span aria-hidden="true">₱</span>
                                <input id="free_delivery_minimum" name="free_delivery_minimum" type="number" inputmode="decimal" step="0.01" min="0.01" value="{{ old('free_delivery_minimum', $tenant->free_delivery_minimum) }}" placeholder="No minimum" class="pe-input text-base lg:text-sm">
                            </div>
                            <x-input-error :messages="$errors->get('free_delivery_minimum')" class="mt-2" />
                        </div>
                        <button type="submit" :disabled="saving" :aria-busy="saving" class="seller-outline-button min-h-11 disabled:opacity-60">
                            <span x-show="!saving">Save delivery fee</span>
                            <span x-cloak x-show="saving">Saving…</span>
                        </button>
                    </form>
                </div>
            </details>
        </div>
    </div>
</x-app-layout>
