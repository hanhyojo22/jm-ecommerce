@props(['active' => 'details', 'branch' => null])

<div class="sc-settings-subnav">
    <nav class="sc-settings-tabs" aria-label="Store settings">
        <a href="{{ route('seller.store-settings.edit', ['branch' => $branch]) }}" class="sc-settings-tab" @if ($active === 'details') aria-current="page" @endif>
            <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
            <span>Store details</span>
        </a>
        <a href="{{ route('seller.opening-hours.edit', ['branch' => $branch]) }}" class="sc-settings-tab" @if ($active === 'hours') aria-current="page" @endif>
            <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            <span>Opening hours</span>
        </a>
    </nav>
    @if (trim($slot) !== '')
        <div class="sc-settings-jumps" aria-label="Jump to section">
            {{ $slot }}
        </div>
    @endif
</div>
