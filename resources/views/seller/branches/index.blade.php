@php
    $totalBranches = $branches->count();
    $openBranches = $branches->where('status', 'active')->count();
    $closedBranches = $branches->where('status', 'inactive')->count();
    $suspendedBranches = $branches->where('status', 'suspended')->count();
    $unpinned = $branches->filter(fn ($branch): bool => $branch->latitude === null || $branch->longitude === null)->values();
    $deliveryFeeFormatted = number_format((float) $tenant->delivery_fee, 2);
    $freeAboveFormatted = $tenant->free_delivery_minimum !== null ? number_format((float) $tenant->free_delivery_minimum, 2) : null;
    $now = now();
    $manilaToday = now(\App\Models\Store::TIMEZONE)->toDateString();
    $filterKey = ['active' => 'open', 'inactive' => 'closed', 'suspended' => 'suspended'];
    $rows = $branches->map(fn ($branch): array => [
        'status' => $filterKey[$branch->status] ?? $branch->status,
        'pinned' => $branch->latitude !== null && $branch->longitude !== null,
        'search' => Str::lower(implode(' ', [$branch->name, $branch->address_line, $branch->city, $branch->phone, preg_replace('/\D+/', '', (string) $branch->phone)])),
    ])->all();
    $filters = array_filter([
        'all' => ['All', $totalBranches],
        'open' => ['Open', $openBranches],
        'closed' => $closedBranches > 0 ? ['Closed', $closedBranches] : null,
        'suspended' => $suspendedBranches > 0 ? ['Suspended', $suspendedBranches] : null,
        'unpinned' => $unpinned->isNotEmpty() ? ['Needs a pin', $unpinned->count()] : null,
    ]);
    $icons = [
        'store' => '<path d="M3 9.5 5 4h14l2 5.5M4 10h16v10H4zM9 20v-6h6v6M3 9.5a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/>',
        'pin' => '<path d="M12 21s-7-5.7-7-11a7 7 0 1 1 14 0c0 5.3-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
        'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z"/>',
        'stock' => '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'staff' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5"/>',
        'power' => '<path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 1 1-12.77.04"/>',
        'lock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'warning' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'chevron' => '<path d="m9 18 6-6-6-6"/>',
        'more' => '<circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'close' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
    ];
    $icon = fn (string $name, string $class = ''): string => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$icons[$name].'</svg>';
@endphp

<x-app-layout :error-summary="false">
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <div class="sc-branches-title-row">
                    <h1 class="sc-title">Branches</h1>
                    <span class="sc-branches-count">{{ $totalBranches }} {{ Str::plural('location', $totalBranches) }}</span>
                </div>
                <p class="sc-subtitle">Every branch in {{ $tenant->name }}, with today’s status, stock, and map coverage in one place.</p>
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
        <div class="page-container seller-workspace-page sc-stack"
            x-data="{
                query: '',
                filter: 'all',
                rows: @js($rows),
                shows(index) {
                    const row = this.rows[index];
                    const query = this.query.trim().toLowerCase();
                    const byFilter = this.filter === 'all'
                        || (this.filter === 'unpinned' ? ! row.pinned : row.status === this.filter);

                    return byFilter && (query === '' || row.search.includes(query));
                },
                get matches() { return this.rows.filter((row, index) => this.shows(index)).length; },
                reset() { this.query = ''; this.filter = 'all'; },
            }">
            @if (session('status'))<p class="sc-notice" data-tone="success" role="status">{{ session('status') }}</p>@endif

            @if ($totalBranches > 0)
                <section class="sc-network" aria-label="Branch network summary"
                    x-data="{ feeOpen: @js($errors->hasAny(['delivery_fee', 'free_delivery_minimum'])) }">
                    <div class="sc-network-grid">
                        <div class="sc-network-zone">
                            <h2 class="sc-network-label">Network today</h2>
                            <div class="sc-network-bar" aria-hidden="true">
                                @foreach ($branches as $branch)
                                    <span class="sc-network-seg" data-status="{{ $filterKey[$branch->status] ?? 'closed' }}"></span>
                                @endforeach
                            </div>
                            <ul class="sc-network-legend">
                                <li><span class="sc-legend-dot" data-status="open"></span>{{ $openBranches }} open</li>
                                <li><span class="sc-legend-dot" data-status="closed"></span>{{ $closedBranches }} closed</li>
                                @if ($suspendedBranches > 0)
                                    <li><span class="sc-legend-dot" data-status="suspended"></span>{{ $suspendedBranches }} suspended</li>
                                @endif
                            </ul>
                        </div>

                        <div class="sc-network-zone">
                            <h2 class="sc-network-label">Needs attention</h2>
                            @if ($unpinned->isEmpty())
                                <p class="sc-network-attention is-clear">{!! $icon('check', 'sc-attention-icon') !!}<span><strong>Every branch has a map pin.</strong> <span>Nearby customers can find all of them.</span></span></p>
                            @elseif ($unpinned->count() === 1)
                                <p class="sc-network-attention">{!! $icon('warning', 'sc-attention-icon') !!}<span><strong>{{ $unpinned->first()->name }}</strong> <span>has no map pin, so nearby customers cannot find it.</span></span></p>
                                <a href="{{ route('seller.store-settings.edit', ['branch' => $unpinned->first()->id]) }}#map-location-heading" class="sc-network-action">Set its map pin {!! $icon('chevron') !!}</a>
                            @else
                                <p class="sc-network-attention">{!! $icon('warning', 'sc-attention-icon') !!}<span><strong>{{ $unpinned->count() }} branches have no map pin,</strong> <span>so nearby customers cannot find them.</span></span></p>
                                <button type="button" class="sc-network-action" @click="filter = 'unpinned'; query = ''; $nextTick(() => $refs.directory.scrollIntoView({ block: 'start' }))">Show them {!! $icon('chevron') !!}</button>
                            @endif
                        </div>

                        <div class="sc-network-zone">
                            <div class="sc-network-label">
                                <h2>Delivery fee</h2>
                                <button type="button" class="sc-network-edit" @click="feeOpen = ! feeOpen" :aria-expanded="feeOpen.toString()" aria-controls="delivery-fee-form">
                                    <span x-text="feeOpen ? 'Close' : 'Edit'">Edit</span> {!! $icon('edit') !!}
                                </button>
                            </div>
                            <p class="sc-network-value"><strong class="tabular-nums">₱{{ $deliveryFeeFormatted }}</strong> <span>per order</span></p>
                            <p class="sc-network-fine">{{ $freeAboveFormatted ? 'Free above ₱'.$freeAboveFormatted.' · ' : 'Same at every branch · ' }}pickup always free</p>
                        </div>
                    </div>

                    <div id="delivery-fee-form" class="sc-network-fee" x-cloak x-show="feeOpen">
                        <form method="POST" action="{{ route('seller.delivery-fee.update') }}"
                            x-data="{ saving: false }" @submit="saving = true" aria-label="Delivery fee">
                            @csrf
                            @method('PATCH')
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
                            <p class="sc-network-fee-note">The same at every branch. New orders use the saved fee.</p>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="seller-outline-button" @click="feeOpen = false">Cancel</button>
                                <button type="submit" :disabled="saving" :aria-busy="saving" class="seller-primary-button disabled:opacity-60">
                                    <span x-show="!saving">Save delivery fee</span>
                                    <span x-cloak x-show="saving">Saving…</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="sc-directory" aria-labelledby="branch-directory-title" x-ref="directory">
                    <h2 class="sr-only" id="branch-directory-title">Branch directory</h2>

                    @if ($totalBranches > 1)
                        <div class="sc-directory-toolbar">
                            <div class="sc-directory-search">
                                {!! $icon('search') !!}
                                <label for="branch-search" class="sr-only">Search branches</label>
                                <input id="branch-search" type="search" x-model="query" placeholder="Search name, city, or phone" autocomplete="off">
                                <button type="button" class="sc-directory-clear" x-cloak x-show="query !== ''" @click="query = ''; $el.previousElementSibling.focus()" aria-label="Clear search">{!! $icon('close') !!}</button>
                            </div>
                            <div class="sc-directory-filters" role="group" aria-label="Filter branches">
                                @foreach ($filters as $key => [$label, $count])
                                    <button type="button" class="sc-directory-filter" @click="filter = @js($key)" :aria-pressed="(filter === @js($key)).toString()" aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}">{{ $label }} <span>{{ $count }}</span></button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="sc-directory-head" aria-hidden="true" x-show="matches > 0">
                        <span>Branch</span><span>Today</span><span>Stock</span><span>Map</span><span>Actions</span>
                    </div>

                    <ul class="sc-directory-list">
                        @foreach ($branches as $branch)
                            @php
                                $hasPin = $branch->latitude !== null && $branch->longitude !== null;
                                $tone = ['active' => 'success', 'suspended' => 'danger'][$branch->status] ?? 'neutral';
                                $settingsUrl = route('seller.store-settings.edit', ['branch' => $branch->id]);
                                if ($branch->status === 'active' && $branch->isOpenAt($now)) {
                                    [$todayLabel, $todayTone] = ['Open now', 'success'];
                                    $todayNote = $branch->opening_time === null || $branch->closing_time === null ? 'Open 24 hours' : 'Closes '.\Carbon\Carbon::parse($branch->closing_time)->format('g:i A');
                                } elseif ($branch->status === 'active') {
                                    $nextOpening = $branch->nextOpeningAt($now);
                                    [$todayLabel, $todayTone] = ['Closed now', 'neutral'];
                                    $todayNote = 'Opens '.($nextOpening->toDateString() === $manilaToday ? '' : 'tomorrow, ').$nextOpening->format('g:i A');
                                } elseif ($branch->status === 'suspended') {
                                    [$todayLabel, $todayTone, $todayNote] = ['Suspended', 'danger', 'Suspended by the platform'];
                                } else {
                                    [$todayLabel, $todayTone, $todayNote] = ['Closed', 'neutral', 'Not taking orders'];
                                }
                            @endphp
                            <li class="sc-directory-row" x-show="shows({{ $loop->index }})">
                                <div class="sc-directory-branch">
                                    <span class="sc-directory-tile" data-tone="{{ $tone }}">{!! $icon('store') !!}</span>
                                    <div class="min-w-0">
                                        <h3 class="sc-directory-name" title="{{ $branch->name }}">{{ $branch->name }}</h3>
                                        <p class="sc-directory-meta">
                                            <span class="sc-directory-address">{!! $icon('pin') !!}<span>{{ $branch->address_line }}, {{ $branch->city }}</span></span>
                                            @if ($branch->phone)
                                                <span class="tabular-nums">{!! $icon('phone') !!}{{ $branch->phone }}</span>
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                <div class="sc-directory-cell">
                                    <span class="sc-directory-label">Today</span>
                                    <span class="sc-badge" data-tone="{{ $todayTone }}">{{ $todayLabel }}</span>
                                    <span class="sc-directory-note">{{ $todayNote }}</span>
                                </div>

                                <div class="sc-directory-cell">
                                    <span class="sc-directory-label">Stock</span>
                                    <span class="sc-directory-value tabular-nums">{{ number_format($branch->branch_stocks_count) }}</span>
                                    <span class="sc-directory-note">stocked {{ Str::plural('item', $branch->branch_stocks_count) }}</span>
                                </div>

                                <div class="sc-directory-cell">
                                    <span class="sc-directory-label">Map</span>
                                    @if ($hasPin)
                                        <span class="sc-branch-pin is-pinned">Pinned{{ $branch->delivery_radius_km ? ' · '.$branch->delivery_radius_km.' km' : '' }}</span>
                                        <span class="sc-directory-note">In nearby search</span>
                                    @else
                                        <a href="{{ $settingsUrl }}#map-location-heading" class="sc-directory-missing">{!! $icon('warning') !!}No map pin yet</a>
                                        <span class="sc-directory-note">Hidden from nearby search</span>
                                    @endif
                                </div>

                                <div class="sc-directory-actions" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                                    <a href="{{ $settingsUrl }}" class="seller-outline-button" aria-label="Manage {{ $branch->name }}">Manage branch {!! $icon('chevron', 'size-4') !!}</a>
                                    <button type="button" class="sc-directory-more" @click="open = ! open" :aria-expanded="open.toString()" aria-expanded="false" aria-haspopup="menu" aria-controls="branch-menu-{{ $branch->id }}" aria-label="More for {{ $branch->name }}">{!! $icon('more') !!}</button>
                                    <div id="branch-menu-{{ $branch->id }}" class="sc-directory-menu" role="menu" x-cloak x-show="open" x-transition.opacity.duration.100ms>
                                        <div class="sc-directory-menu-head"><strong>{{ $branch->name }}</strong><span>Branch tools</span></div>
                                        <a role="menuitem" href="{{ route('seller.branch-stock.index', ['branch' => $branch->id]) }}" class="sc-directory-menu-item">{!! $icon('stock') !!}Branch stock<span>{{ number_format($branch->branch_stocks_count) }} {{ Str::plural('item', $branch->branch_stocks_count) }}</span></a>
                                        <a role="menuitem" href="{{ route('seller.opening-hours.edit', ['branch' => $branch->id]) }}" class="sc-directory-menu-item">{!! $icon('clock') !!}Opening hours</a>
                                        <a role="menuitem" href="{{ route('seller.staff.index') }}" class="sc-directory-menu-item">{!! $icon('staff') !!}Staff</a>
                                        @if ($branch->status === 'active')
                                            <a role="menuitem" href="{{ route('catalog.store', $branch->slug) }}" target="_blank" rel="noopener noreferrer" class="sc-directory-menu-item">{!! $icon('external') !!}View storefront<span>New tab</span></a>
                                        @endif
                                        <div class="sc-directory-menu-sep" role="separator"></div>
                                        @if ($branch->status === 'suspended')
                                            <span role="menuitem" aria-disabled="true" class="sc-directory-menu-item is-disabled">{!! $icon('lock') !!}Suspended by the platform</span>
                                            <p class="sc-directory-menu-note">Only the platform can lift a suspension. Stock and details stay editable.</p>
                                        @elseif ($branch->status === 'active')
                                            <a role="menuitem" href="{{ $settingsUrl }}#status" class="sc-directory-menu-item is-danger">{!! $icon('power') !!}Close branch<span>In settings</span></a>
                                        @else
                                            <a role="menuitem" href="{{ $settingsUrl }}#status" class="sc-directory-menu-item is-accent">{!! $icon('power') !!}Open branch<span>In settings</span></a>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <div class="sc-directory-empty" x-cloak x-show="matches === 0" role="status">
                        <span class="sc-directory-tile" data-tone="neutral">{!! $icon('search') !!}</span>
                        <h3>No branches match</h3>
                        <p>Try another name, city, or phone number, or show every branch.</p>
                        <button type="button" class="seller-outline-button" @click="reset()">Show all branches</button>
                    </div>
                </section>

                <p class="sc-directory-footnote">{!! $icon('info') !!}Branches with a map pin appear in nearby search and checkout. The delivery fee is the same at every branch.</p>
            @else
                <div class="sc-card sc-directory-empty">
                    <span class="sc-directory-tile" data-tone="neutral">{!! $icon('store') !!}</span>
                    <h2>No branches yet</h2>
                    <p>Add your first pharmacy branch to pin it on the map, set opening hours, and track branch stock.</p>
                    <a href="{{ route('seller.branches.create') }}" class="seller-primary-button">Add branch</a>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
