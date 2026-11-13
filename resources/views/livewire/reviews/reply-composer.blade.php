<form x-on:submit.prevent="submitRoot($event)" data-optimistic-submit class="mt-2 min-w-0">
    @if ($sellerView)
        <label for="reply-body-{{ $review->id }}" class="sr-only">Seller response</label>
        <div class="seller-reply-field">
            @if ($rootResponse)
                <textarea id="reply-body-{{ $review->id }}" wire:model="body" rows="1" maxlength="2000" required placeholder="Edit your response..." class="field-control"></textarea>
            @else
                <input id="reply-body-{{ $review->id }}" type="text" wire:model="body" maxlength="2000" required placeholder="Write a reply..." class="field-control">
            @endif
            <button type="submit" x-bind:disabled="rootSubmitting" class="seller-reply-send" aria-label="{{ $rootResponse ? 'Update response' : 'Send response' }}" title="{{ $rootResponse ? 'Update response' : 'Send response' }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z" /><path d="M22 2 11 13" /></svg></button>
        </div>
        @error('body')<p class="mt-1 text-xs text-danger" role="alert">{{ $message }}</p>@enderror
        <span x-show="rootSubmitting" x-cloak class="text-xs text-muted" role="status">Sending...</span>
        @if ($rootResponse)<button type="button" wire:click="cancelReply" class="sc-link mt-1 text-xs">Cancel editing</button>@endif
    @else
    <div class="flex min-w-0 gap-2.5">
        @if ($actingAsStore && $store->logo_path)
            <img src="{{ Storage::disk('public')->url($store->logo_path) }}" alt="" class="size-8 shrink-0 rounded-full object-cover">
        @else
            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-semibold text-muted" aria-hidden="true">{{ Str::upper(Str::substr($actingAsStore ? $store->name : auth()->user()?->name ?? '?', 0, 1)) }}</span>
        @endif
        <div class="min-w-0 flex-1">
            <label for="reply-body-{{ $review->id }}" class="sr-only">Seller response</label>
            <textarea id="reply-body-{{ $review->id }}" wire:model="body" rows="2" maxlength="2000" required placeholder="Write a response as {{ $store->name }}..." class="field-control w-full resize-y text-sm"></textarea>
            @error('body')<p class="mt-1 text-xs text-danger" role="alert">{{ $message }}</p>@enderror
            @if ($sellerView)<p class="mt-1 text-xs text-muted">Your reply appears publicly under this review.</p>@endif
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <button type="submit" x-bind:disabled="rootSubmitting" @class(['min-h-11 rounded-lg px-4 text-sm font-semibold text-white disabled:opacity-60', 'seller-primary-button' => $sellerView, 'bg-brand hover:bg-brand-hover' => ! $sellerView])>{{ $rootResponse ? 'Update response' : 'Post response' }}</button>
                @if ($sellerView)<button type="button" wire:click="cancelReply" @class(['min-h-11 px-3 text-sm font-semibold', 'seller-outline-button' => $sellerView, 'text-muted hover:text-ink' => ! $sellerView])>Cancel</button>@endif
                <span x-show="rootSubmitting" class="text-xs text-muted" role="status">Posting...</span>
            </div>
        </div>
    </div>
    @endif
</form>
