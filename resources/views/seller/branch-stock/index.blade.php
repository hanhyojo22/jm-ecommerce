<x-app-layout :error-summary="false">
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <h1 class="sc-title">Stock at {{ $branch->name }}</h1>
                <p class="sc-subtitle">Units on hand at this branch. Customers near it see only what is in stock here.</p>
            </div>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container seller-workspace-page sc-stack">
            @if (session('status'))<p class="sc-notice" data-tone="success" role="status">{{ session('status') }}</p>@endif
            @if ($errors->any())<p class="sc-notice" data-tone="danger" role="alert">{{ $errors->first() }}</p>@endif

            <form method="GET" action="{{ route('seller.branch-stock.index') }}" class="flex flex-wrap items-end gap-3">
                @if ($branches)
                    <div>
                        <label for="branch" class="block text-sm font-semibold text-ink">Branch</label>
                        <select id="branch" name="branch" class="field-control mt-2 text-base lg:text-sm" data-submit-on-change>
                            @foreach ($branches as $option)
                                <option value="{{ $option->id }}" @selected($option->id === $branch->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label for="search" class="block text-sm font-semibold text-ink">Find a medicine</label>
                    <input id="search" name="search" type="search" value="{{ $search }}" maxlength="100" class="field-control mt-2 text-base lg:text-sm">
                </div>
                <button type="submit" class="seller-outline-button min-h-11">Search</button>
            </form>

            <section class="grid gap-4 lg:grid-cols-2">
                <form method="POST" action="{{ route('seller.branch-stock.receive', ['branch' => $branch->id]) }}" class="sc-card grid gap-3 p-4">
                    @csrf
                    <h2 class="text-lg font-semibold text-ink">Receive a batch</h2>
                    <label class="text-sm font-semibold text-ink">Medicine
                        <select name="product_id" required class="field-control mt-1 w-full">
                            @foreach ($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="text-sm font-semibold text-ink">Product option <span class="font-normal text-muted">(leave blank for products without options)</span>
                        <select name="variant_id" class="field-control mt-1 w-full">
                            <option value="">No option</option>
                            @foreach ($products as $product)
                                @foreach ($product->variants as $variant)
                                    <option value="{{ $variant->id }}">{{ $product->name }} — {{ $variant->label() }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-sm font-semibold text-ink">Lot number<input name="lot_number" maxlength="100" required class="field-control mt-1 w-full"></label>
                        <label class="text-sm font-semibold text-ink">Quantity<input name="quantity" type="number" min="1" max="1000000" required class="field-control mt-1 w-full"></label>
                        <label class="text-sm font-semibold text-ink">Expiry date<input name="expires_at" type="date" min="{{ today()->addDay()->toDateString() }}" required class="field-control mt-1 w-full"></label>
                        <label class="text-sm font-semibold text-ink">Supplier<input name="supplier" maxlength="255" class="field-control mt-1 w-full"></label>
                    </div>
                    <label class="text-sm font-semibold text-ink">Supplier reference<input name="reference" maxlength="255" class="field-control mt-1 w-full"></label>
                    <button class="seller-primary-button min-h-11 justify-self-start">Receive stock</button>
                </form>
                <div class="sc-card p-4">
                    <h2 class="text-lg font-semibold text-ink">Batch watch</h2>
                    <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-muted">Expires within 90 days</dt><dd class="font-semibold text-ink">{{ $watch['expiring'] }} units</dd></div>
                        <div><dt class="text-muted">Expired</dt><dd class="font-semibold text-ink">{{ $watch['expired'] }} units</dd></div>
                        <div><dt class="text-muted">Quarantined</dt><dd class="font-semibold text-ink">{{ $watch['quarantined'] }} units</dd></div>
                        <div><dt class="text-muted">Low stock (5 or fewer)</dt><dd class="font-semibold text-ink">{{ $watch['low_stock'] }} product options</dd></div>
                        <div><dt class="text-muted">Stock discrepancies</dt><dd class="font-semibold text-ink">{{ $watch['discrepancies'] }} counts</dd></div>
                    </dl>
                    <p class="mt-2 text-sm text-muted">Use the stock count table below to reconcile physical quantities.</p>
                </div>
            </section>

            <section class="sc-card overflow-x-auto">
                <h2 class="p-4 text-lg font-semibold text-ink">Batches at {{ $branch->name }}</h2>
                <table class="w-full text-sm">
                    <thead><tr class="border-y border-line text-left text-muted"><th class="p-3">Medicine / lot</th><th class="p-3">Expiry</th><th class="p-3">On hand</th><th class="p-3">Status / adjustment</th></tr></thead>
                    <tbody>
                        @forelse ($batches as $batch)
                            <tr class="border-b border-line-soft">
                                <td class="p-3"><span class="font-semibold text-ink">{{ $batch->product->name }}</span><span class="block text-muted">{{ $batch->lot_number }}</span></td>
                                <td class="p-3">{{ $batch->expires_at?->format('M j, Y') ?? 'No expiry recorded' }}</td>
                                <td class="p-3">{{ $batch->remaining_quantity }}</td>
                                <td class="p-3">
                                    <form method="POST" action="{{ route('seller.branch-stock.adjust', ['branch' => $branch->id]) }}" class="flex flex-wrap items-center gap-2">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="batch_id" value="{{ $batch->id }}">
                                        <span class="text-muted">{{ ucfirst($batch->status) }}</span>
                                        <input aria-label="New batch quantity" name="quantity" type="number" min="0" max="1000000" value="{{ $batch->remaining_quantity }}" required class="field-control w-24">
                                        <select aria-label="Adjustment reason" name="reason" required class="field-control"><option value="count">Count</option><option value="damage">Damage</option><option value="expiry">Expiry</option><option value="correction">Correction</option><option value="quarantine">Quarantine</option></select>
                                        <button class="seller-outline-button min-h-10">Adjust</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-4 text-muted">No batches recorded for this branch yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-3">{{ $batches->links() }}</div>
            </section>

            @if ($products->isEmpty())
                <div class="sc-empty"><h3>No medicines found</h3><p>Head office adds medicines under Products; they appear here to stock.</p></div>
            @else
                <form method="POST" action="{{ route('seller.branch-stock.update', ['branch' => $branch->id]) }}" class="sc-card">
                    @csrf
                    @method('PATCH')
                    <table class="w-full text-sm">
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
                    </table>
                    <div class="flex items-center justify-between gap-3 p-3">
                        <button type="submit" class="seller-primary-button min-h-11">Save stock</button>
                        {{ $products->links() }}
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
