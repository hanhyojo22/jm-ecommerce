<form wire:submit="submit" wire:key="review-composer-{{ $replyToId ?? 'root' }}" class="mt-2 min-w-0">
    <div class="flex min-w-0 gap-2.5">
        @if ($actingAsStore && $store->logo_path)
            <img src="{{ Storage::disk('public')->url($store->logo_path) }}" alt="" class="size-8 shrink-0 rounded-full object-cover">
        @else
            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-bold text-muted" aria-hidden="true">{{ Str::upper(Str::substr($actingAsStore ? $store->name : auth()->user()?->name ?? '?', 0, 1)) }}</span>
        @endif
        <div class="min-w-0 flex-1">
            <label for="reply-body-{{ $review->id }}" class="sr-only">{{ $replyToId ? 'Write a reply' : 'Seller response' }}</label>
            <textarea id="reply-body-{{ $review->id }}" wire:model="body" rows="2" maxlength="2000" required placeholder="{{ $replyToId ? 'Write a reply...' : 'Write a response as '.$store->name.'...' }}" class="field-control w-full resize-y text-sm"></textarea>
            @error('body')<p class="mt-1 text-xs text-danger" role="alert">{{ $message }}</p>@enderror
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <button type="submit" wire:loading.attr="disabled" wire:target="submit" class="min-h-11 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-hover disabled:opacity-60">{{ $replyToId ? 'Post reply' : ($rootResponse ? 'Update response' : 'Post response') }}</button>
                @if ($replyToId)<button type="button" wire:click="cancelReply" class="min-h-11 px-3 text-sm font-semibold text-muted hover:text-ink">Cancel</button>@endif
                <span wire:loading wire:target="submit" class="text-xs text-muted" role="status">Posting...</span>
            </div>
        </div>
    </div>
</form>
