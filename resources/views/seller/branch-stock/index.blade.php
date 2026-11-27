@php
    $productVariantsMap = $products->getCollection()->mapWithKeys(fn ($product): array => [
        $product->id => $product->variants->map(fn ($variant): array => [
            'id' => $variant->id,
            'label' => $variant->label(),
        ])->values()->all(),
    ])->all();

    $initialCounts = [];
    foreach ($products as $product) {
        $counts = $product->sellable_branch_counts;
        foreach ($product->variants->isEmpty() ? [null] : $product->variants as $variant) {
            $key = $variant?->id ?? 'default';
            $rowKey = $product->id.'|'.$key;
            $initialCounts[$rowKey] = (int) old("stock.{$product->id}.{$key}", $counts[$key] ?? 0);
        }
    }

    $savedCounts = [];
    foreach ($products as $product) {
        $counts = $product->sellable_branch_counts;
        foreach ($product->variants->isEmpty() ? [null] : $product->variants as $variant) {
            $key = $variant?->id ?? 'default';
            $savedCounts[$product->id.'|'.$key] = (int) ($counts[$key] ?? 0);
        }
    }

    $watchCards = [
        ['key' => 'expiring', 'label' => 'Expires within 90 days', 'unit' => 'units', 'tone' => 'warning', 'action' => 'batch', 'filter' => 'expiring'],
        ['key' => 'expired', 'label' => 'Expired', 'unit' => 'units', 'tone' => 'danger', 'action' => 'batch', 'filter' => 'expired'],
        ['key' => 'quarantined', 'label' => 'Quarantined', 'unit' => 'units', 'tone' => 'warning', 'action' => 'batch', 'filter' => 'quarantined'],
        ['key' => 'low_stock', 'label' => 'Low stock', 'unit' => 'product options', 'tone' => 'warning', 'action' => 'count', 'filter' => 'low'],
        ['key' => 'discrepancies', 'label' => 'Stock discrepancies', 'unit' => 'counts', 'tone' => 'danger', 'action' => 'count', 'filter' => 'all'],
    ];

    $hasSupplierOld = filled(old('supplier')) || filled(old('reference')) || $errors->hasAny(['supplier', 'reference']);
    $batchPageCounts = [
        'all' => $batches->count(),
        'expiring' => 0,
        'expired' => 0,
        'quarantined' => 0,
    ];
    foreach ($batches as $batch) {
        $isExpired = $batch->expires_at && $batch->expires_at->lt(today());
        $isNear = ! $isExpired && $batch->expires_at && $batch->expires_at->lte(today()->addDays(90));
        if ($batch->status === 'quarantined') {
            $batchPageCounts['quarantined']++;
        } elseif ($isExpired) {
            $batchPageCounts['expired']++;
        } elseif ($isNear) {
            $batchPageCounts['expiring']++;
        }
    }
@endphp

<x-app-layout :error-summary="false">
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="sc-title">Stock at {{ $branch->name }}</h1>
                    @if ($branches && $branches->count() > 1)
                        <span class="sc-badge" data-tone="neutral">{{ $branches->count() }} branches</span>
                    @endif
                </div>
                <p class="sc-subtitle">Track expiry lots, receive supplier deliveries, and reconcile shelf counts for this branch</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="#physical-counts" class="seller-outline-button min-h-11">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="m9 14 2 2 4-4"/></svg>
                    Physical counts
                </a>
                <a href="#receive-stock" class="seller-primary-button min-h-11">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    Receive stock
                </a>
            </div>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container seller-workspace-page sc-stock-page"
            x-data="{
                batchFilter: 'all',
                countFilter: 'all',
                openBatchId: @js((string) old('batch_id', '')),
                selectedProduct: @js((string) old('product_id', '')),
                selectedVariant: @js((string) old('variant_id', '')),
                variantMap: @js($productVariantsMap),
                counts: @js($initialCounts),
                savedCounts: @js($savedCounts),
                get currentVariants() {
                    return this.variantMap[this.selectedProduct] ?? [];
                },
                onProductChange() {
                    const allowed = this.currentVariants.map(v => v.id);
                    if (! allowed.includes(this.selectedVariant)) {
                        this.selectedVariant = '';
                    }
                },
                toggleBatchAdjust(id) {
                    this.openBatchId = this.openBatchId === id ? '' : id;
                },
                matchesBatchFilter(bucket) {
                    return this.batchFilter === 'all' || this.batchFilter === bucket;
                },
                visibleBatchCount() {
                    if (this.batchFilter === 'all') return {{ $batchPageCounts['all'] }};
                    if (this.batchFilter === 'expiring') return {{ $batchPageCounts['expiring'] }};
                    if (this.batchFilter === 'expired') return {{ $batchPageCounts['expired'] }};
                    if (this.batchFilter === 'quarantined') return {{ $batchPageCounts['quarantined'] }};
                    return {{ $batchPageCounts['all'] }};
                },
                stepCount(rowKey, delta) {
                    const current = Number(this.counts[rowKey] ?? 0);
                    const next = Math.max(0, Math.min(1000000, (Number.isFinite(current) ? current : 0) + delta));
                    this.counts[rowKey] = next;
                },
                rowDelta(rowKey) {
                    const current = Number(this.counts[rowKey] ?? 0);
                    const saved = Number(this.savedCounts[rowKey] ?? 0);
                    if (! Number.isFinite(current)) return 0;
                    return current - saved;
                },
                get modifiedCount() {
                    return Object.keys(this.savedCounts).filter(k => this.rowDelta(k) !== 0).length;
                },
                matchesCountFilter(rowKey) {
                    const qty = Number(this.counts[rowKey] ?? 0);
                    if (this.countFilter === 'low') return qty <= 5;
                    if (this.countFilter === 'changed') return this.rowDelta(rowKey) !== 0;
                    return true;
                },
                resetCounts() {
                    for (const key of Object.keys(this.savedCounts)) {
                        this.counts[key] = this.savedCounts[key];
                    }
                },
                jumpWatch(action, filter) {
                    if (action === 'batch') {
                        this.batchFilter = this.batchFilter === filter ? 'all' : filter;
                        document.getElementById('stock-batches')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    } else {
                        this.countFilter = filter;
                        document.getElementById('physical-counts')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            }">

            @if (session('status'))
                <div class="sc-notice" data-tone="success" role="status">
                    <div class="flex items-center gap-2.5">
                        <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
                        <p class="font-medium">{{ session('status') }}</p>
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="sc-error-summary" role="alert" tabindex="-1" x-init="$nextTick(() => $el.focus())">
                    <div class="sc-error-summary-head">
                        <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                        <span>Stock could not be saved. Review the issues below and try again.</span>
                    </div>
                    <ul class="sc-error-summary-list">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Grounded Working Branch & Search Bar --}}
            <form method="GET" action="{{ route('seller.branch-stock.index') }}" class="sc-stock-toolbar-card">
                @if ($branches)
                    <div class="sc-field sc-stock-branch">
                        <label for="branch">Working branch</label>
                        <select id="branch" name="branch" class="field-control sc-input" data-submit-on-change>
                            @foreach ($branches as $option)
                                <option value="{{ $option->id }}" @selected($option->id === $branch->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="sc-field sc-stock-search">
                    <label for="search">Find medicine to receive or count</label>
                    <div class="sc-stock-search-wrap">
                        <svg class="sc-stock-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        <input id="search" name="search" type="search" value="{{ $search }}" maxlength="100" placeholder="Search brand or generic name" class="field-control sc-input">
                    </div>
                </div>
                <div class="sc-stock-toolbar-actions">
                    <button type="submit" class="seller-outline-button min-h-11">Search</button>
                    @if ($search)
                        <a href="{{ route('seller.branch-stock.index', ['branch' => $branch->id]) }}" class="sc-link min-h-11 px-2 text-xs">Clear search</a>
                    @endif
                </div>
            </form>

            {{-- Actionable Batch Watch KPI Strip --}}
            <section class="sc-stock-watch" aria-labelledby="stock-watch-title">
                <div class="sc-stock-section-heading">
                    <div>
                        <h2 id="stock-watch-title" class="sc-card-title">Batch watch</h2>
                        <p class="sc-card-note">Live inventory alerts across {{ $branch->name }} · Click a watch tile to filter below</p>
                    </div>
                </div>
                <div class="sc-stock-signals" role="list">
                    @foreach ($watchCards as $card)
                        @php
                            $count = $watch[$card['key']];
                        @endphp
                        <button type="button" role="listitem"
                            @click="jumpWatch('{{ $card['action'] }}', '{{ $card['filter'] }}')"
                            :aria-pressed="(('{{ $card['action'] }}' === 'batch' && batchFilter === '{{ $card['filter'] }}') || ('{{ $card['action'] }}' === 'count' && '{{ $card['filter'] }}' === 'low' && countFilter === 'low')).toString()"
                            class="sc-stock-signal"
                            @if ($count > 0) data-tone="{{ $card['tone'] }}" @endif>
                            <span class="sc-stock-signal-top">
                                <span class="sc-stock-signal-label">{{ $card['label'] }}</span>
                                <span class="sc-stock-signal-dot" aria-hidden="true"></span>
                            </span>
                            <span class="sc-stock-signal-value">
                                <strong>{{ number_format($count) }}</strong>
                                <span>{{ $count === 1 ? Str::singular($card['unit']) : $card['unit'] }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </section>

            {{-- 2-Column Operations Workspace: Batches Table + Receive Batch Panel --}}
            <div class="sc-stock-workspace">
                <section id="stock-batches" class="sc-card sc-stock-batches" aria-labelledby="batches-title">
                    <div class="sc-card-head">
                        <div class="flex items-start gap-3">
                            <span class="sc-settings-card-icon" aria-hidden="true">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/></svg>
                            </span>
                            <div>
                                <h2 id="batches-title" class="sc-card-title">Stock batches</h2>
                                <p class="sc-card-note">Ordered by soonest expiry · Adjust individual lots for counts, damage, expiry, or quarantine</p>
                            </div>
                        </div>
                        <span class="sc-stock-total">{{ number_format($batches->total()) }} {{ Str::plural('batch', $batches->total()) }}</span>
                    </div>

                    @if ($batches->isNotEmpty())
                        <div class="sc-stock-filterbar" role="group" aria-label="Filter visible batches">
                            <button type="button" @click="batchFilter = 'all'" :aria-pressed="(batchFilter === 'all').toString()" class="sc-stock-chip">
                                All <span class="sc-stock-chip-count">{{ $batchPageCounts['all'] }}</span>
                            </button>
                            <button type="button" @click="batchFilter = 'expiring'" :aria-pressed="(batchFilter === 'expiring').toString()" class="sc-stock-chip" data-tone="warning">
                                Expiring soon <span class="sc-stock-chip-count">{{ $batchPageCounts['expiring'] }}</span>
                            </button>
                            <button type="button" @click="batchFilter = 'expired'" :aria-pressed="(batchFilter === 'expired').toString()" class="sc-stock-chip" data-tone="danger">
                                Expired <span class="sc-stock-chip-count">{{ $batchPageCounts['expired'] }}</span>
                            </button>
                            <button type="button" @click="batchFilter = 'quarantined'" :aria-pressed="(batchFilter === 'quarantined').toString()" class="sc-stock-chip" data-tone="warning">
                                Quarantined <span class="sc-stock-chip-count">{{ $batchPageCounts['quarantined'] }}</span>
                            </button>
                        </div>
                    @endif

                    <div class="sc-table-wrap">
                        <table class="sc-table sc-stock-table">
                            <thead>
                                <tr>
                                    <th scope="col">Medicine &amp; lot</th>
                                    <th scope="col">Expiry</th>
                                    <th scope="col" class="is-num">On hand</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="is-action"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($batches as $batch)
                                    @php
                                        $expired = $batch->expires_at && $batch->expires_at->lt(today());
                                        $nearExpiry = ! $expired && $batch->expires_at && $batch->expires_at->lte(today()->addDays(90));
                                        $bucket = $batch->status === 'quarantined' ? 'quarantined' : ($expired ? 'expired' : ($nearExpiry ? 'expiring' : 'active'));
                                        $tone = $batch->status === 'quarantined' ? 'warning' : ($expired ? 'danger' : ($nearExpiry ? 'warning' : 'success'));
                                        $statusLabel = $batch->status === 'quarantined' ? 'Quarantined' : ($expired ? 'Expired' : ($batch->status === 'active' ? 'Active' : ucfirst($batch->status)));
                                        $daysDiff = $batch->expires_at ? (int) today()->diffInDays($batch->expires_at, false) : null;
                                    @endphp
                                    <tr x-show="matchesBatchFilter('{{ $bucket }}')" :class="openBatchId === @js($batch->id) && 'is-adjusting'">
                                        <th scope="row" class="sc-stock-medicine">
                                            <span class="sc-strong">{{ $batch->product->name }}</span>
                                            <span class="sc-stock-lot-row">
                                                <span class="sc-stock-lot-pill">Lot {{ $batch->lot_number }}</span>
                                                @if ($batch->supplier)
                                                    <span class="sc-stock-supplier">{{ $batch->supplier }}</span>
                                                @endif
                                            </span>
                                        </th>
                                        <td class="sc-stock-expiry">
                                            <span class="font-medium text-ink">{{ $batch->expires_at?->format('M j, Y') ?? 'Not recorded' }}</span>
                                            @if ($expired && $daysDiff !== null)
                                                <span class="sc-stock-expiry-note" data-tone="danger">Expired {{ abs($daysDiff) }} {{ Str::plural('day', abs($daysDiff)) }} ago</span>
                                            @elseif ($nearExpiry && $daysDiff !== null)
                                                <span class="sc-stock-expiry-note" data-tone="warning">{{ $daysDiff === 0 ? 'Expires today' : 'In '.$daysDiff.' '.Str::plural('day', $daysDiff) }}</span>
                                            @endif
                                        </td>
                                        <td class="is-num sc-stock-quantity">
                                            <strong>{{ number_format($batch->remaining_quantity) }}</strong>
                                            @if ($batch->received_quantity > 0 && $batch->received_quantity !== $batch->remaining_quantity)
                                                <span class="sc-stock-received-meta">of {{ number_format($batch->received_quantity) }}</span>
                                            @endif
                                            <span class="sc-stock-mobile-unit"> units on hand</span>
                                        </td>
                                        <td class="sc-stock-status">
                                            <span class="sc-badge" data-tone="{{ $tone }}">{{ $statusLabel }}</span>
                                        </td>
                                        <td class="sc-stock-action">
                                            <button type="button" @click="toggleBatchAdjust(@js($batch->id))"
                                                :aria-expanded="(openBatchId === @js($batch->id)).toString()"
                                                aria-controls="adjust-panel-{{ $batch->id }}"
                                                class="sc-stock-adjust-trigger">
                                                <span x-text="openBatchId === @js($batch->id) ? 'Close' : 'Adjust'">Adjust</span>
                                                <svg class="size-4 transition-transform" :class="openBatchId === @js($batch->id) && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr id="adjust-panel-{{ $batch->id }}" class="sc-stock-adjust-row"
                                        x-show="openBatchId === @js($batch->id) && matchesBatchFilter('{{ $bucket }}')"
                                        @if (old('batch_id') !== $batch->id) x-cloak @endif>
                                        <td colspan="5">
                                            <form method="POST" action="{{ route('seller.branch-stock.adjust', ['branch' => $branch->id]) }}" class="sc-stock-adjust-panel">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="batch_id" value="{{ $batch->id }}">
                                                <div class="sc-stock-adjust-context">
                                                    <span class="text-xs font-medium text-ink">Adjust Lot {{ $batch->lot_number }}</span>
                                                    <span class="text-xs text-muted">Currently {{ number_format($batch->remaining_quantity) }} {{ Str::plural('unit', $batch->remaining_quantity) }}</span>
                                                </div>
                                                <div class="sc-stock-adjust-fields">
                                                    <div class="sc-field">
                                                        <label for="batch-quantity-{{ $batch->id }}">New quantity</label>
                                                        <input id="batch-quantity-{{ $batch->id }}" name="quantity" type="number" inputmode="numeric" min="0" max="1000000"
                                                            value="{{ old('batch_id') === $batch->id ? old('quantity', $batch->remaining_quantity) : $batch->remaining_quantity }}"
                                                            required class="field-control sc-input">
                                                    </div>
                                                    <div class="sc-field">
                                                        <label for="batch-reason-{{ $batch->id }}">Reason</label>
                                                        <select id="batch-reason-{{ $batch->id }}" name="reason" required class="field-control sc-input">
                                                            @foreach (['count' => 'Physical count', 'damage' => 'Damaged stock', 'expiry' => 'Expired write-off', 'correction' => 'Entry correction', 'quarantine' => 'Move to quarantine'] as $value => $label)
                                                                <option value="{{ $value }}" @selected(old('batch_id') === $batch->id && old('reason') === $value)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="sc-stock-adjust-btns">
                                                        <button type="button" @click="openBatchId = ''" class="seller-outline-button min-h-11">Cancel</button>
                                                        <button type="submit" class="seller-primary-button min-h-11">Save adjustment</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5">
                                            <div class="sc-empty">
                                                <h3>No batches recorded yet</h3>
                                                <p>Receive your first delivery to start tracking lots and expiry dates at this branch.</p>
                                                <a href="#receive-stock" class="sc-link">Receive stock</a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse

                                @if ($batches->isNotEmpty())
                                    <tr x-cloak x-show="visibleBatchCount() === 0">
                                        <td colspan="5">
                                            <div class="sc-empty">
                                                <h3>No batches match this filter</h3>
                                                <p>There are no lots in this view on the current page.</p>
                                                <button type="button" @click="batchFilter = 'all'" class="sc-link">Show all batches</button>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    @if ($batches->hasPages())
                        <div class="sc-card-foot">{{ $batches->links() }}</div>
                    @endif
                </section>

                {{-- Right Sticky Panel: Receive a Batch --}}
                <aside id="receive-stock" class="sc-card sc-stock-receive" aria-labelledby="receive-stock-title" tabindex="-1">
                    <div class="sc-card-head">
                        <div class="flex items-start gap-3">
                            <span class="sc-settings-card-icon" aria-hidden="true">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 5v14M5 12h14"/></svg>
                            </span>
                            <div>
                                <h2 id="receive-stock-title" class="sc-card-title">Receive a batch</h2>
                                <p class="sc-card-note">Log an incoming delivery for {{ $branch->name }}</p>
                            </div>
                        </div>
                    </div>

                    @if ($products->isNotEmpty())
                        <form method="POST" action="{{ route('seller.branch-stock.receive', ['branch' => $branch->id]) }}" class="sc-stock-receive-form">
                            @csrf
                            <fieldset class="sc-stock-fields">
                                <legend>Medicine &amp; lot details</legend>
                                <div class="sc-field">
                                    <label for="receive-product">Medicine <span class="sc-stock-optional">(required)</span></label>
                                    <select id="receive-product" name="product_id" x-model="selectedProduct" @change="onProductChange()" required class="field-control sc-input">
                                        <option value="" disabled @selected(! old('product_id'))>Select a medicine</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}" @selected(old('product_id') === $product->id)>{{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="sc-field" x-show="selectedProduct === '' || currentVariants.length > 0">
                                    <label for="receive-variant">Product option <span class="sc-stock-optional">if applicable</span></label>
                                    <select id="receive-variant" name="variant_id" x-model="selectedVariant" class="field-control sc-input">
                                        <option value="">No option</option>
                                        <template x-for="variant in currentVariants" :key="variant.id">
                                            <option :value="variant.id" x-text="variant.label" :selected="selectedVariant === variant.id"></option>
                                        </template>
                                    </select>
                                </div>

                                <div class="sc-field">
                                    <label for="receive-lot">Lot number <span class="sc-stock-optional">(required)</span></label>
                                    <input id="receive-lot" name="lot_number" maxlength="100" value="{{ old('lot_number') }}" placeholder="e.g. LOT-2026-04A" required class="field-control sc-input sc-code text-sm">
                                </div>

                                <div class="sc-stock-field-pair">
                                    <div class="sc-field">
                                        <label for="receive-quantity">Quantity</label>
                                        <input id="receive-quantity" name="quantity" type="number" inputmode="numeric" min="1" max="1000000" value="{{ old('batch_id') ? '' : old('quantity') }}" placeholder="e.g. 50" required class="field-control sc-input">
                                    </div>
                                    <div class="sc-field">
                                        <label for="receive-expiry">Expiry date</label>
                                        <input id="receive-expiry" name="expires_at" type="date" min="{{ today()->addDay()->toDateString() }}" value="{{ old('expires_at') }}" required class="field-control sc-input">
                                    </div>
                                </div>
                            </fieldset>

                            <details class="sc-subtle-disclosure" @if ($hasSupplierOld) open @endif>
                                <summary>
                                    <svg class="size-4 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                    <span>Add supplier &amp; invoice reference <span class="sc-stock-optional">(optional)</span></span>
                                </summary>
                                <div class="mt-3 grid gap-3.5">
                                    <div class="sc-field">
                                        <label for="receive-supplier">Supplier</label>
                                        <input id="receive-supplier" name="supplier" maxlength="255" value="{{ old('supplier') }}" placeholder="e.g. Unilab / Zuellig Pharma" class="field-control sc-input">
                                    </div>
                                    <div class="sc-field">
                                        <label for="receive-reference">Supplier reference</label>
                                        <input id="receive-reference" name="reference" maxlength="255" value="{{ old('reference') }}" placeholder="e.g. DR-10492 or INV-2026" class="field-control sc-input">
                                    </div>
                                </div>
                            </details>

                            <button type="submit" class="seller-primary-button min-h-11 w-full">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                Receive stock
                            </button>
                        </form>
                    @else
                        <div class="sc-empty">
                            <h3>No medicines to receive</h3>
                            <p>{{ $search ? 'Clear your search to choose a medicine from your catalog.' : 'Head office adds medicines under Products before stock can be received.' }}</p>
                        </div>
                    @endif
                </aside>
            </div>

            {{-- Physical Stock Counts & Quick Reconciliation --}}
            <section id="physical-counts" class="sc-card sc-stock-reconcile" aria-labelledby="counts-title">
                <div class="sc-card-head">
                    <div class="flex items-start gap-3">
                        <span class="sc-settings-card-icon" aria-hidden="true">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6M9 16h4"/></svg>
                        </span>
                        <div>
                            <h2 id="counts-title" class="sc-card-title">Physical stock counts</h2>
                            <p class="sc-card-note">Update shelf quantities for {{ $branch->name }} · Low stock flags items with 5 or fewer units</p>
                        </div>
                    </div>
                    @if ($products->isNotEmpty())
                        <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Filter physical count rows">
                            <button type="button" @click="countFilter = 'all'" :aria-pressed="(countFilter === 'all').toString()" class="sc-stock-chip">All</button>
                            <button type="button" @click="countFilter = 'low'" :aria-pressed="(countFilter === 'low').toString()" class="sc-stock-chip" data-tone="warning">Low stock (≤ 5)</button>
                            <button type="button" @click="countFilter = 'changed'" :aria-pressed="(countFilter === 'changed').toString()" class="sc-stock-chip">
                                Modified <span class="sc-stock-chip-count" x-text="modifiedCount">0</span>
                            </button>
                        </div>
                    @endif
                </div>

                @if ($products->isEmpty())
                    <div class="sc-empty">
                        <h3>No medicines found</h3>
                        <p>{{ $search ? 'Try another name or clear your search to see your catalog.' : 'Head office adds medicines under Products; they appear here to stock.' }}</p>
                        @if ($search)
                            <a href="{{ route('seller.branch-stock.index', ['branch' => $branch->id]) }}" class="sc-link">Clear search</a>
                        @endif
                    </div>
                @else
                    <form method="POST" action="{{ route('seller.branch-stock.update', ['branch' => $branch->id]) }}" class="sc-stock-count-form">
                        @csrf
                        @method('PATCH')
                        <div class="sc-table-wrap">
                            <table class="sc-table sc-stock-count-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Medicine</th>
                                        <th scope="col">Option</th>
                                        <th scope="col">Stock level</th>
                                        <th scope="col" class="is-num">On hand</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($products as $product)
                                        @php
                                            $counts = $product->sellable_branch_counts;
                                        @endphp
                                        @foreach ($product->variants->isEmpty() ? [null] : $product->variants as $variant)
                                            @php
                                                $key = $variant?->id ?? 'default';
                                                $rowKey = $product->id.'|'.$key;
                                                $currentVal = old("stock.{$product->id}.{$key}", $counts[$key] ?? 0);
                                            @endphp
                                            <tr x-show="matchesCountFilter(@js($rowKey))" :class="rowDelta(@js($rowKey)) !== 0 && 'is-modified'">
                                                <td class="sc-count-med">
                                                    <span class="font-medium text-ink">{{ $product->name }}</span>
                                                    @if ($product->generic_name)
                                                        <span class="mt-0.5 block text-xs text-muted">{{ $product->generic_name }}{{ $product->strength ? ' · '.$product->strength : '' }}</span>
                                                    @endif
                                                </td>
                                                <td class="sc-count-opt">
                                                    @if ($variant)
                                                        <span class="sc-stock-opt-pill">{{ $variant->label() }}</span>
                                                    @else
                                                        <span class="text-xs text-muted">Standard</span>
                                                    @endif
                                                </td>
                                                <td class="sc-count-level">
                                                    <span class="sc-badge"
                                                        :data-tone="Number(counts[@js($rowKey)] ?? 0) === 0 ? 'danger' : Number(counts[@js($rowKey)] ?? 0) <= 5 ? 'warning' : 'success'"
                                                        x-text="Number(counts[@js($rowKey)] ?? 0) === 0 ? 'Out of stock' : Number(counts[@js($rowKey)] ?? 0) <= 5 ? 'Low stock' : 'In stock'">
                                                        {{ (int) $currentVal === 0 ? 'Out of stock' : ((int) $currentVal <= 5 ? 'Low stock' : 'In stock') }}
                                                    </span>
                                                    <span x-cloak x-show="rowDelta(@js($rowKey)) !== 0"
                                                        class="sc-delta-chip"
                                                        :data-dir="rowDelta(@js($rowKey)) > 0 ? 'up' : 'down'"
                                                        x-text="(rowDelta(@js($rowKey)) > 0 ? '+' : '') + rowDelta(@js($rowKey))"></span>
                                                </td>
                                                <td class="sc-count-input-cell">
                                                    <div class="sc-stepper">
                                                        <button type="button" @click="stepCount(@js($rowKey), -1)" class="sc-stepper-btn" aria-label="Decrease {{ $product->name }} {{ $variant?->label() }} quantity">
                                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14"/></svg>
                                                        </button>
                                                        <label for="stock-{{ $product->id }}-{{ $key }}" class="sr-only">Units of {{ $product->name }} {{ $variant?->label() }} on hand</label>
                                                        <input id="stock-{{ $product->id }}-{{ $key }}" name="stock[{{ $product->id }}][{{ $key }}]"
                                                            type="number" inputmode="numeric" min="0" max="1000000" required
                                                            value="{{ $currentVal }}"
                                                            x-model.number="counts[@js($rowKey)]"
                                                            class="field-control sc-stepper-input">
                                                        <button type="button" @click="stepCount(@js($rowKey), 1)" class="sc-stepper-btn" aria-label="Increase {{ $product->name }} {{ $variant?->label() }} quantity">
                                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="sc-stock-count-foot">
                            <div class="flex flex-wrap items-center gap-3">
                                <button type="submit" class="seller-primary-button min-h-11">Save stock</button>
                                <button type="button" x-cloak x-show="modifiedCount > 0" @click="resetCounts()" class="seller-outline-button min-h-11">Reset changes</button>
                                <span x-cloak x-show="modifiedCount > 0" class="sc-stock-modified-note" role="status" aria-live="polite">
                                    <span class="sc-dirty-pulse" aria-hidden="true"></span>
                                    <span x-text="modifiedCount + (modifiedCount === 1 ? ' count modified' : ' counts modified')"></span>
                                </span>
                            </div>
                            <div>{{ $products->links() }}</div>
                        </div>
                    </form>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
