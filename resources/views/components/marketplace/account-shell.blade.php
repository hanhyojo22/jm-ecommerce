@props(['active' => 'settings'])
{{-- Each account page reads at its own measure; the width itself lives in app.css next to the other workspace measures. --}}
@php($measure = match ($active) {
    'orders' => 'account-measure-orders',
    'messages' => 'account-measure-messages',
    'reviews' => 'account-measure-reviews',
    'addresses' => 'account-measure-addresses',
    'settings' => 'account-measure-settings',
    default => '',
})

<div class="page-shell min-h-[calc(100dvh-4.5rem)] {{ $active === 'messages' ? 'account-messages-shell' : '' }}">
    {{-- Settings and messages set their own top edge; the rest relied on the removed breadcrumb for it. --}}
    <div class="page-container {{ $measure }} {{ in_array($active, ['settings', 'messages'], true) ? '' : 'pt-8' }}">
        <div class="account-shell-content min-w-0">
            {{ $slot }}
        </div>
    </div>
</div>
