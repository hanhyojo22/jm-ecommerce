<div x-cloak x-show="open" class="absolute left-0 right-0 top-full z-50 mt-2 max-h-[min(30rem,72vh)] overflow-y-auto rounded-lg border border-line bg-white p-2 shadow-[0_20px_44px_rgba(24,44,72,0.16)]">
    <template x-if="[...query.trim()].length < 2">
        <div>
            <div class="flex items-center justify-between px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">
                <span x-text="recentSearches.length ? 'Recent searches' : 'Recently viewed'"></span>
                <button type="button" @click="clearHistory()" class="font-semibold normal-case tracking-normal text-brand transition hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Clear history</button>
            </div>
            <div x-show="recentSearches.length" class="flex flex-wrap gap-1.5 px-3 pb-2.5 pt-1" role="list" aria-label="Recent searches">
                <template x-for="term in recentSearches" :key="term">
                    <a :href="resultsUrl + '?q=' + encodeURIComponent(term)" role="listitem" class="inline-flex min-h-8 items-center gap-1.5 rounded-full border border-line bg-canvas px-3 py-1 text-xs font-medium text-ink transition hover:border-brand hover:bg-[#eef3ff] hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                        <svg class="size-3.5 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
                        <span x-text="term"></span>
                    </a>
                </template>
            </div>
            <div x-show="recentSearches.length && recentlyViewed.length" class="border-t border-line px-3 pb-1 pt-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">
                <span>Recently viewed</span>
            </div>
        </div>
    </template>
    <div x-show="[...query.trim()].length >= 2 && !loading && items.length" class="flex items-center justify-between px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">
        <span>Matching products</span>
        <span class="hidden font-medium normal-case tracking-normal sm:inline">Use ↑↓ and Enter to open</span>
    </div>
    <p x-show="loading" class="px-3 py-3 text-sm text-muted" role="status">Searching products…</p>
    <p x-show="[...query.trim()].length >= 2 && !loading && !items.length" class="px-3 py-3 text-sm text-muted" role="status">No matching products</p>
    <div id="{{ $listId }}" role="listbox" aria-label="Product suggestions" class="space-y-0.5"><template x-for="(item, index) in currentItems()" :key="item.url">
        <a :id="'{{ $optionPrefix }}' + index" :href="item.url" role="option" :aria-selected="(active === index).toString()" @mouseenter="active = index" class="flex min-h-16 items-center gap-3 rounded-md p-2 text-ink transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand" :class="active === index ? 'bg-[#eef3ff]' : 'hover:bg-canvas'">
            <span class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-md border border-line bg-white text-xs text-muted">
                <template x-if="item.image"><img :src="item.image" :alt="item.name" class="size-full object-cover"></template>
                <template x-if="!item.image"><span aria-hidden="true">No image</span></template>
            </span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold text-ink" x-text="item.name"></span>
                <span class="block truncate text-xs text-muted" x-text="item.store"></span>
            </span>
            <span class="shrink-0 text-right">
                <span class="block text-sm font-bold tabular-nums text-ink" x-text="item.price"></span>
                <span x-show="item.sold_out" class="block text-xs font-medium text-danger">Sold out</span>
            </span>
        </a>
    </template></div>
    <a x-show="[...query.trim()].length >= 2" :href="resultsUrl + '?q=' + encodeURIComponent(query)" class="mt-1.5 flex min-h-11 items-center justify-center gap-1.5 rounded-md border-t border-line px-3 text-sm font-semibold text-brand transition hover:bg-[#eef3ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
        <span>View all results</span>
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
    </a>
</div>
