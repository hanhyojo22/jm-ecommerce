@props(['type', 'id', 'label', 'placeholder', 'onPick'])

{{-- A server-backed combobox for one kind of link target; jmmartLinkSearch lives on the notifications page. --}}
<div x-data="jmmartLinkSearch(@js($type), @js($onPick), @js($id))" class="relative" @click.outside="open = false">
    <label for="{{ $id }}" class="mb-1.5 block text-xs font-semibold text-ink">{{ $label }}</label>
    <div class="relative">
        <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
        <input
            id="{{ $id }}"
            type="search"
            x-model="query"
            @focus="search()"
            @input.debounce.250ms="search()"
            @keydown.arrow-down.prevent="move(1)"
            @keydown.arrow-up.prevent="move(-1)"
            @keydown.enter="chooseActive($event)"
            @keydown.escape="open = false"
            role="combobox"
            autocomplete="off"
            aria-autocomplete="list"
            aria-controls="{{ $id }}-list"
            :aria-expanded="open.toString()"
            :aria-activedescendant="open && active >= 0 ? @js($id.'-option-') + active : null"
            placeholder="{{ $placeholder }}"
            class="field-control pl-9"
        >

        <div
            x-cloak
            x-show="open"
            class="absolute inset-x-0 top-full z-30 mt-1.5 overflow-hidden rounded-lg border border-line bg-surface shadow-[0_12px_32px_rgb(24_44_72/14%)]"
        >
            <ul id="{{ $id }}-list" role="listbox" aria-label="{{ $label }}" class="max-h-80 overflow-y-auto py-1.5" x-show="results.length > 0">
                <template x-for="(option, index) in results" :key="option.url">
                    <li
                        :id="@js($id.'-option-') + index"
                        role="option"
                        :aria-selected="(active === index).toString()"
                        @click="choose(option)"
                        @mouseenter="active = index"
                        :class="active === index ? 'bg-[var(--color-info-wash)]' : ''"
                        class="flex min-h-11 cursor-pointer items-center gap-3 px-3.5 py-2"
                    >
                        <template x-if="option.image">
                            <img :src="option.image" alt="" class="size-10 shrink-0 rounded-md border border-line bg-white object-contain" referrerpolicy="no-referrer">
                        </template>
                        <template x-if="! option.image">
                            <span class="grid size-10 shrink-0 place-items-center rounded-md bg-[var(--color-sunken)] text-xs font-bold text-brand ring-1 ring-inset ring-[var(--color-line-soft)]" aria-hidden="true" x-text="initials(option.name)"></span>
                        </template>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-ink" x-text="option.name"></span>
                            <span class="block truncate text-xs text-muted" x-text="option.kind === 'product' ? option.store : (option.city || 'Store page')"></span>
                        </span>
                        <span x-show="option.price" class="shrink-0 text-sm font-semibold tabular-nums text-ink" x-text="option.price"></span>
                    </li>
                </template>
            </ul>

            <p x-show="loading" class="border-t border-line-soft px-3.5 py-2.5 text-xs text-muted" role="status">Searching…</p>
            <div x-show="! loading && ! failed && results.length === 0" class="px-3.5 py-3" role="status">
                <p class="text-sm text-ink" x-text="emptyText()"></p>
                <button x-show="type === 'products' && storeFilter" type="button" @click="clearStoreFilter(); search()" class="mt-2 inline-flex min-h-8 items-center rounded-md bg-[var(--color-info-wash)] px-2.5 text-xs font-semibold text-brand">Search every store</button>
            </div>
            <div x-show="failed" class="flex items-center justify-between gap-3 bg-[var(--color-danger-wash)] px-3.5 py-3 text-sm text-danger" role="alert">
                <span>Search is unavailable right now.</span>
                <button type="button" @click="search()" class="inline-flex min-h-8 items-center rounded-md bg-surface px-2.5 text-xs font-semibold text-brand">Try again</button>
            </div>
        </div>
    </div>
</div>
