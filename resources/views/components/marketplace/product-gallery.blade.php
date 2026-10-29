@props(['product', 'initialImages'])
<section class="min-w-0" aria-label="Product images">
    <div>
        <div class="pd-gallery-main">
            @if (count($initialImages))
                <button type="button" x-show="selected === 0 && gallery.length" @click="zoomTrigger = $event.currentTarget; $refs.zoomDialog.showModal()" aria-label="Zoom image 1 of {{ count($initialImages) }}" :aria-label="'Zoom image 1 of ' + gallery.length" class="absolute inset-0 h-full w-full focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand">
                    <img src="{{ $initialImages[0]['url'] }}" :src="gallery[0]?.url" alt="{{ $product->name }}" :alt="gallery[0]?.alt" width="800" height="680" fetchpriority="high" class="h-full w-full object-contain">
                </button>
            @endif
            <template x-for="(image, index) in gallery.slice(1)" :key="index">
                <button type="button" x-show="selected === index + 1" @click="zoomTrigger = $event.currentTarget; $refs.zoomDialog.showModal()" :aria-label="'Zoom image ' + (index + 2) + ' of ' + gallery.length" class="absolute inset-0 h-full w-full focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand">
                    <img :src="image.url" :alt="image.alt" width="800" height="680" class="h-full w-full object-contain">
                </button>
            </template>
            <p x-cloak x-show="!gallery.length" class="grid h-full place-items-center px-8 text-center text-sm text-muted">Product image coming soon</p>
            @if ($product->stock_quantity < 1 || $product->variants->contains('stock_quantity', 0))
                <div x-show="max < 1" class="pd-sold-overlay pointer-events-none absolute inset-0"><x-marketplace.sold-out-overlay /></div>
            @endif
            <div x-show="gallery.length > 1" class="sr-only focus-within:not-sr-only">
                <button type="button" @click="selected = (selected - 1 + gallery.length) % gallery.length" class="absolute left-3 top-1/2 grid size-11 -translate-y-1/2 place-items-center rounded-full border border-line bg-white text-xl shadow-sm" aria-label="Previous product image">&#8249;</button>
                <button type="button" @click="selected = (selected + 1) % gallery.length" class="absolute right-3 top-1/2 grid size-11 -translate-y-1/2 place-items-center rounded-full border border-line bg-white text-xl shadow-sm" aria-label="Next product image">&#8250;</button>
            </div>
            <span x-show="gallery.length" class="pd-expand" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 3H3v5M16 3h5v5M21 16v5h-5M3 16v5h5"/></svg></span>
        </div>
        <div x-show="gallery.length" class="pd-thumbs" role="group" aria-label="Choose a product image">
            <template x-for="(image, index) in gallery" :key="index">
                <button type="button" @click="selected = index" :aria-label="'Show image ' + (index + 1)" :aria-pressed="selected === index" :class="{ active: selected === index }" class="pd-thumb"><img :src="image.url" alt="" width="72" height="72" class="h-full w-full object-cover"><i x-show="image.swatch" class="pd-thumb-swatch" :style="{ background: image.swatch }" aria-hidden="true"></i></button>
            </template>
        </div>
    </div>
    <dialog x-ref="zoomDialog" @close="zoomTrigger?.focus()" @keydown.left.prevent="selected = (selected - 1 + gallery.length) % gallery.length" @keydown.right.prevent="selected = (selected + 1) % gallery.length" aria-label="Enlarged product image" class="max-h-[95dvh] w-[min(95vw,72rem)] max-w-none rounded-lg border border-line bg-white p-4 shadow-xl backdrop:bg-slate-950/80 sm:p-6">
        <div class="flex justify-end"><button type="button" @click="$refs.zoomDialog.close()" class="min-h-11 rounded-lg border border-line px-4 text-sm font-semibold">Close</button></div>
        <template x-for="(image, index) in gallery" :key="index"><img x-show="selected === index" :src="image.url" :alt="'Enlarged ' + image.alt" width="1200" height="1020" class="mx-auto max-h-[75dvh] w-full object-contain"></template>
    </dialog>
</section>
