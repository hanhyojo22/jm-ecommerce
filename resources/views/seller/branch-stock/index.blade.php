<x-app-layout :error-summary="false">
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <h1 class="sc-title">Stock at {{ $branch->name }}</h1>
                <p class="sc-subtitle">Review batches, receive deliveries, and keep branch stock up to date.</p>
            </div>
            <a href="#receive-stock" class="seller-primary-button">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                Receive stock
            </a>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container seller-workspace-page sc-stock-page">
            @if (session('status'))<p class="sc-notice" data-tone="success" role="status">{{ session('status') }}</p>@endif
            @if ($errors->any())
                <div class="sc-notice" data-tone="danger" role="alert">
                    <p class="font-semibold">Stock could not be saved</p>
                    <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <form method="GET" action="{{ route('seller.branch-stock.index') }}" class="sc-stock-toolbar">
                @if ($branches)
                    <div class="sc-field sc-stock-branch">
                        <label for="branch">Working branch</label>
                        <select id="branch" name="branch" class="field-control sc-input" data-submit-on-change>
                            @foreach ($branches as $option)<option value="{{ $option->id }}" @selected($option->id === $branch->id)>{{ $option->name }}</option>@endforeach
                        </select>
                    </div>
                @endif
                <div class="sc-field sc-stock-search">
                    <label for="search">Find medicine to receive or count</label>
                    <input id="search" name="search" type="search" value="{{ $search }}" maxlength="100" placeholder="Name or generic name" class="field-control sc-input">
                </div>
                <button type="submit" class="seller-outline-button">Search</button>
                @if ($search)<a href="{{ route('seller.branch-stock.index', ['branch' => $branch->id]) }}" class="sc-link">Clear search</a>@endif
            </form>

            <section class="sc-stock-watch" aria-labelledby="stock-watch-title">
                <div class="sc-stock-section-heading">
                    <h2 id="stock-watch-title" class="sc-card-title">Batch watch</h2>
                    <p class="sc-card-note">Across {{ $branch->name }}</p>
                </div>
                <dl class="sc-stock-signals">
                    @foreach ([['expiring', 'Expires within 90 days', 'units', 'warning'], ['expired', 'Expired', 'units', 'danger'], ['quarantined', 'Quarantined', 'units', 'warning'], ['low_stock', 'Low stock', 'product options', 'warning'], ['discrepancies', 'Stock discrepancies', 'counts', 'danger']] as [$key, $label, $unit, $tone])
                        <div class="sc-stock-signal" @if ($watch[$key] > 0) data-tone="{{ $tone }}" @endif>
                            <dt>{{ $label }}</dt>
                            <dd><strong>{{ number_format($watch[$key]) }}</strong> <span>{{ $watch[$key] === 1 ? Str::singular($unit) : $unit }}</span></dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <div class="sc-stock-workspace">
                <section class="sc-card sc-stock-batches" aria-labelledby="batches-title">
                    <div class="sc-card-head">
                        <div><h2 id="batches-title" class="sc-card-title">Stock batches</h2><p class="sc-card-note">Review expiry dates and record changes to each lot.</p></div>
                        <span class="sc-stock-total">{{ number_format($batches->total()) }} {{ Str::plural('batch', $batches->total()) }}</span>
                    </div>
                    <table class="sc-table sc-stock-table">
                        <thead><tr><th scope="col">Medicine / lot</th><th scope="col">Expiry</th><th scope="col" class="is-num">On hand</th><th scope="col">Status</th></tr></thead>
                        <tbody>
                            @forelse ($batches as $batch)
                                @php
                                    $expired = $batch->expires_at && $batch->expires_at->lt(today());
                                    $nearExpiry = !$expired && $batch->expires_at && $batch->expires_at->lte(today()->addDays(90));
                                    $tone = $batch->status === 'quarantined' ? 'warning' : ($expired ? 'danger' : 'neutral');
                                    $statusLabel = $batch->status === 'quarantined' ? 'Quarantined' : ($expired ? 'Expired' : ucfirst($batch->status));
                                @endphp
                                <tr>
                                    <th scope="row" class="sc-stock-medicine"><span class="sc-strong">{{ $batch->product->name }}</span><span class="sc-stock-lot">Lot {{ $batch->lot_number }}</span></th>
                                    <td class="sc-stock-expiry"><span>{{ $batch->expires_at?->format('M j, Y') ?? 'Not recorded' }}</span>@if ($nearExpiry)<span class="sc-stock-expiry-note">Expires soon</span>@endif</td>
                                    <td class="is-num sc-stock-quantity"><strong>{{ number_format($batch->remaining_quantity) }}</strong><span class="sc-stock-mobile-unit"> units on hand</span></td>
                                    <td class="sc-stock-status">
                                        <span class="sc-badge" data-tone="{{ $tone }}">{{ $statusLabel }}</span>
                                        <details class="sc-stock-adjust" @if (old('batch_id') === $batch->id) open @endif>
                                            <summary>Adjust batch <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></summary>
                                            <form method="POST" action="{{ route('seller.branch-stock.adjust', ['branch' => $branch->id]) }}" class="sc-stock-adjust-form">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="batch_id" value="{{ $batch->id }}">
                                                <div class="sc-field"><label for="batch-quantity-{{ $batch->id }}">New quantity</label><input id="batch-quantity-{{ $batch->id }}" name="quantity" type="number" inputmode="numeric" min="0" max="1000000" value="{{ old('batch_id') === $batch->id ? old('quantity', $batch->remaining_quantity) : $batch->remaining_quantity }}" required class="field-control sc-input"></div>
                                                <div class="sc-field"><label for="batch-reason-{{ $batch->id }}">Reason</label><select id="batch-reason-{{ $batch->id }}" name="reason" required class="field-control sc-input">@foreach (['count' => 'Count', 'damage' => 'Damage', 'expiry' => 'Expiry', 'correction' => 'Correction', 'quarantine' => 'Quarantine'] as $value => $label)<option value="{{ $value }}" @selected(old('batch_id') === $batch->id && old('reason') === $value)>{{ $label }}</option>@endforeach</select></div>
                                                <button class="seller-outline-button">Save adjustment</button>
                                            </form>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><div class="sc-empty"><h3>No batches recorded yet</h3><p>Receive your first delivery to start tracking lots and expiry dates at this branch.</p><a href="#receive-stock" class="sc-link">Receive stock</a></div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    @if ($batches->hasPages())<div class="sc-card-foot">{{ $batches->links() }}</div>@endif
                </section>

                <aside id="receive-stock" class="sc-card sc-stock-receive" aria-labelledby="receive-stock-title" tabindex="-1">
                    <div class="sc-card-head"><div><h2 id="receive-stock-title" class="sc-card-title">Receive a batch</h2><p class="sc-card-note">Add a delivery to {{ $branch->name }}.</p></div></div>
                    @if ($products->isNotEmpty())
                        <form method="POST" action="{{ route('seller.branch-stock.receive', ['branch' => $branch->id]) }}" class="sc-stock-receive-form">
                            @csrf
                            <fieldset class="sc-stock-fields"><legend>Medicine &amp; batch</legend>
                                <div class="sc-field"><label for="receive-product">Medicine</label><select id="receive-product" name="product_id" required class="field-control sc-input"><option value="" disabled @selected(!old('product_id'))>Select a medicine</option>@foreach ($products as $product)<option value="{{ $product->id }}" @selected(old('product_id') === $product->id)>{{ $product->name }}</option>@endforeach</select></div>
                                <div class="sc-field"><label for="receive-variant">Product option <span class="sc-stock-optional">if applicable</span></label><select id="receive-variant" name="variant_id" class="field-control sc-input"><option value="">No option</option>@foreach ($products as $product)@foreach ($product->variants as $variant)<option value="{{ $variant->id }}" @selected(old('variant_id') === $variant->id)>{{ $product->name }} ? {{ $variant->label() }}</option>@endforeach @endforeach</select></div>
                                <div class="sc-field"><label for="receive-lot">Lot number</label><input id="receive-lot" name="lot_number" maxlength="100" value="{{ old('lot_number') }}" required class="field-control sc-input"></div>
                                <div class="sc-stock-field-pair">
                                    <div class="sc-field"><label for="receive-quantity">Quantity</label><input id="receive-quantity" name="quantity" type="number" inputmode="numeric" min="1" max="1000000" value="{{ old('batch_id') ? '' : old('quantity') }}" required class="field-control sc-input"></div>
                                    <div class="sc-field"><label for="receive-expiry">Expiry date</label><input id="receive-expiry" name="expires_at" type="date" min="{{ today()->addDay()->toDateString() }}" value="{{ old('expires_at') }}" required class="field-control sc-input"></div>
                                </div>
                            </fieldset>
                            <fieldset class="sc-stock-fields"><legend>Supplier details <span class="sc-stock-optional">optional</span></legend>
                                <div class="sc-field"><label for="receive-supplier">Supplier</label><input id="receive-supplier" name="supplier" maxlength="255" value="{{ old('supplier') }}" class="field-control sc-input"></div>
                                <div class="sc-field"><label for="receive-reference">Supplier reference</label><input id="receive-reference" name="reference" maxlength="255" value="{{ old('reference') }}" class="field-control sc-input"></div>
                            </fieldset>
                            <button class="seller-primary-button w-full">Receive stock</button>
                        </form>
                    @else
                        <div class="sc-empty"><h3>No medicines to receive</h3><p>{{ $search ? 'Clear your search to choose a medicine from your catalog.' : 'Head office adds medicines under Products before stock can be received.' }}</p></div>
                    @endif
                </aside>
            </div>

            <section class="sc-card sc-stock-reconcile" aria-labelledby="counts-title">
                <div class="sc-card-head"><div><h2 id="counts-title" class="sc-card-title">Physical stock counts</h2><p class="sc-card-note">Compare shelf quantities with recorded stock and save a count for this branch.</p></div><span class="sc-stock-total">Low stock: 5 or fewer units</span></div>
            @if ($products->isEmpty())
                <div class="sc-empty"><h3>No medicines found</h3><p>{{ $search ? 'Try another name or clear your search to see your catalog.' : 'Head office adds medicines under Products; they appear here to stock.' }}</p>@if ($search)<a href="{{ route('seller.branch-stock.index', ['branch' => $branch->id]) }}" class="sc-link">Clear search</a>@endif</div>
            @else
                <form method="POST" action="{{ route('seller.branch-stock.update', ['branch' => $branch->id]) }}" class="sc-stock-count-form">
                    @csrf
                    @method('PATCH')
                    <div class="sc-table-wrap"><table class="sc-table">
                        <thead><tr class="border-b border-line text-left text-muted"><th class="p-3 font-semibold">Medicine</th><th class="p-3 font-semibold">Option</th><th class="w-36 p-3 font-semibold">On hand</th></tr></thead>
                        <tbody>
                            @foreach ($products as $product)
                                @php($counts = $product->sellable_branch_counts)
                                @foreach ($product->variants->isEmpty() ? [null] : $product->variants as $variant)
                                    @php($key = $variant?->id ?? 'default')
                                    <tr class="border-b border-line-soft">
                                        <td class="p-3">
                                            @if ($loop->first)
                                                <span class="font-semibold text-ink">{{ $product->name }}</span>
                                                @if ($product->generic_name)<span class="block text-muted">{{ $product->generic_name }}{{ $product->strength ? ' · '.$product->strength : '' }}</span>@endif
                                            @endif
                                        </td>
                                        <td class="p-3 text-muted">{{ $variant?->label() ?? '—' }}</td>
                                        <td class="p-3">
                                            <label for="stock-{{ $product->id }}-{{ $key }}" class="sr-only">Units of {{ $product->name }} {{ $variant?->label() }} on hand</label>
                                            <input id="stock-{{ $product->id }}-{{ $key }}" name="stock[{{ $product->id }}][{{ $key }}]" type="number" inputmode="numeric" min="0" max="1000000" required
                                                value="{{ old("stock.{$product->id}.{$key}", $counts[$key] ?? 0) }}" class="field-control w-28 text-base lg:text-sm">
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table></div>
                    <div class="sc-card-foot">
                        <button type="submit" class="seller-primary-button min-h-11">Save stock</button>
                        {{ $products->links() }}
                    </div>
                </form>
            @endif
            </section>
        </div>
    </div>
</x-app-layout>
