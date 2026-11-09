@php($isBuyerMessages = $conversation->buyer_id === auth()->id())
<div data-conversation-thread>
    <x-marketplace.account-shell active="messages">
        <div class="grid h-[calc(100dvh-17.5rem)] min-h-0 min-w-0 overflow-hidden rounded-lg border border-line bg-surface sm:h-[calc(100dvh-14.5rem)] lg:h-[calc(100dvh-7.5rem)] lg:min-h-[32rem] lg:grid-cols-[clamp(380px,30%,420px)_minmax(0,1fr)]">
            <div class="hidden min-h-0 border-r border-line lg:block"><livewire:chat.inbox :embedded="true" :active-conversation-id="$conversation->id" :context="$isBuyerMessages ? 'buying' : 'selling'" /></div>
            @include('livewire.chat.thread-content')
        </div>
    </x-marketplace.account-shell>

    {{-- Photo viewer. wire:ignore keeps Livewire refreshes of the thread from resetting it mid-zoom. --}}
    <div wire:ignore x-data="imageViewer()" x-on:chat-image-open.window="open($event.detail)">
        <dialog x-ref="dialog" x-on:close="closed()" x-on:keydown="keydown($event)" aria-label="Photo viewer" data-image-viewer class="m-0 h-dvh max-h-none w-screen max-w-none overflow-hidden bg-slate-950 p-0 text-white backdrop:bg-slate-950">
            <div x-ref="stage" data-image-viewer-stage class="relative flex size-full touch-none select-none items-center justify-center overflow-hidden" x-on:wheel.prevent="wheel($event)" x-on:dblclick="doubleClick($event)" x-on:pointerdown="pointerDown($event)" x-on:pointermove="pointerMove($event)" x-on:pointerup="pointerUp($event)" x-on:pointercancel="pointerUp($event)" x-on:click="stageClick()">
                <img x-ref="image" x-bind:src="src" x-bind:alt="name" x-on:load="loaded = true" x-on:error="failed = true" draggable="false" data-image-viewer-image
                    class="max-h-full max-w-full object-contain will-change-transform"
                    x-bind:class="{ 'transition-transform duration-200 ease-out motion-reduce:transition-none': animate, 'cursor-grab active:cursor-grabbing': scale > 1, 'cursor-zoom-in': scale === 1, 'opacity-0': ! loaded }"
                    x-bind:style="{ transform }">
                <div x-show="! loaded && ! failed" class="pointer-events-none absolute inset-0 grid place-items-center" role="status"><span class="size-8 animate-spin rounded-full border-2 border-white/25 border-t-white motion-reduce:animate-none" aria-hidden="true"></span><span class="sr-only">Loading photo</span></div>
                <div x-show="failed" x-cloak class="pointer-events-none absolute inset-0 grid place-items-center text-center text-sm text-white/80" role="alert">Could not load this photo.</div>
            </div>

            {{-- Controls ride on soft scrims so they stay legible over bright photos. --}}
            <div class="pointer-events-none absolute inset-x-0 top-0 flex items-center gap-2 bg-gradient-to-b from-slate-950/70 to-transparent px-3 pb-8 pt-3 sm:px-4">
                <p class="min-w-0 flex-1 truncate text-sm font-medium text-white/90" x-text="name"></p>
                <a x-bind:href="src" x-bind:download="name" class="pointer-events-auto grid size-11 place-items-center rounded-full text-white transition-colors hover:bg-white/15 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white" aria-label="Save image">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v11m0 0-4-4m4 4 4-4M5 19h14"/></svg>
                </a>
                <button type="button" x-ref="close" x-on:click="$refs.dialog.close()" class="pointer-events-auto grid size-11 place-items-center rounded-full text-white transition-colors hover:bg-white/15 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white" aria-label="Close photo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <div class="pointer-events-none absolute inset-x-0 bottom-0 flex justify-center bg-gradient-to-t from-slate-950/70 to-transparent px-3 pb-[max(1rem,env(safe-area-inset-bottom))] pt-8">
                <div class="pointer-events-auto flex items-center gap-1 rounded-full bg-slate-900/80 p-1 ring-1 ring-white/10">
                    <button type="button" x-on:click="step(-1)" x-bind:disabled="scale <= 1" class="grid size-10 place-items-center rounded-full text-white transition-colors hover:bg-white/15 disabled:opacity-40 disabled:hover:bg-transparent focus-visible:outline focus-visible:outline-2 focus-visible:outline-white" aria-label="Zoom out">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true"><path stroke-linecap="round" d="M6 12h12"/></svg>
                    </button>
                    <button type="button" x-on:click="reset()" class="min-w-16 rounded-full px-2 text-center text-sm font-semibold tabular-nums text-white transition-colors hover:bg-white/15 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white" aria-label="Reset zoom" x-text="percent" data-image-viewer-zoom>100%</button>
                    <button type="button" x-on:click="step(1)" x-bind:disabled="scale >= 5" class="grid size-10 place-items-center rounded-full text-white transition-colors hover:bg-white/15 disabled:opacity-40 disabled:hover:bg-transparent focus-visible:outline focus-visible:outline-2 focus-visible:outline-white" aria-label="Zoom in">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true"><path stroke-linecap="round" d="M12 6v12M6 12h12"/></svg>
                    </button>
                </div>
            </div>
        </dialog>
    </div>
</div>
