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
                                @php($counts = $product->branchStocks->pluck('quantity', 'variant_key'))
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
