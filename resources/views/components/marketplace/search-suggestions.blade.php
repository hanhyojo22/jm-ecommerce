<div x-cloak x-show="open" class="absolute left-0 right-0 top-full z-50 mt-2 max-h-[min(28rem,70vh)] overflow-y-auto rounded-xl border border-line bg-white p-2 shadow-[0_18px_40px_rgba(15,23,42,.16)]">
    <p x-show="loading" class="px-3 py-3 text-sm text-muted" role="status">Searching products…</p>
    <p x-show="!loading && !items.length" class="px-3 py-3 text-sm text-muted" role="status">No matching products</p>
    <div id="{{ $listId }}" role="listbox" aria-label="Product suggestions"><template x-for="(item, index) in items" :key="item.url">
        <a :id="'{{ $optionPrefix }}' + index" :href="item.url" role="option" :aria-selected="(active === index).toString()" @mouseenter="active = index" class="flex min-h-16 items-center gap-3 rounded-lg p-2 text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand" :class="active === index ? 'bg-blue-50' : 'hover:bg-canvas'">
            <span class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-md bg-slate-100 text-xs text-muted">
                <template x-if="item.image"><img :src="item.image" :alt="item.name" class="size-full object-cover"></template>
                <template x-if="!item.image"><span aria-hidden="true">No image</span></template>
            </span>
            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold" x-text="item.name"></span><span class="block truncate text-xs text-muted" x-text="item.store"></span></span>
            <span class="shrink-0 text-right"><span class="block text-sm font-semibold tabular-nums" x-text="item.price"></span><span x-show="item.sold_out" class="block text-xs font-medium text-rose-700">Sold out</span></span>
        </a>
    </template></div>
    <a :href="resultsUrl + '?q=' + encodeURIComponent(query)" class="mt-1 flex min-h-10 items-center justify-center rounded-lg border-t border-line px-3 text-sm font-semibold text-brand hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">View all results</a>
</div>
