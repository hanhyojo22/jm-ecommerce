@props(['item', 'href', 'selected' => false, 'clearHref' => null])

<li>
    <a href="{{ $selected && $clearHref ? $clearHref : $href }}" class="flex min-h-8 items-center gap-3 py-[5px] text-sm leading-[1.55] hover:text-brand" @if ($selected) aria-current="page" @endif>
        <span @class(['grid size-4 shrink-0 place-items-center rounded-[3px] border', 'border-brand bg-brand text-white' => $selected, 'border-slate-500 text-transparent' => ! $selected]) aria-hidden="true">
            <svg class="size-3" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 8 3 3 7-7" /></svg>
        </span>
        <span class="min-w-0 truncate">{{ $item->name }}</span>
    </a>
</li>
