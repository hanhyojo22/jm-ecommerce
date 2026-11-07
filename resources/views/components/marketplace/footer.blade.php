<footer class="border-t border-line bg-white text-[13px] leading-6 text-muted">
    <div class="marketplace-container flex flex-col gap-4 py-8 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <a href="{{ route('home') }}" class="inline-flex items-center text-xl font-bold tracking-[-0.05em] text-ink" aria-label="JMMart marketplace home">
                <span class="mr-2 grid size-[27px] place-items-center rounded-lg bg-brand text-sm font-bold text-white" aria-hidden="true">jm</span>JM<span class="text-brand">Mart.</span>
            </a>
            <span>Good finds. Great local stores.</span>
        </div>
        <nav aria-label="Footer" class="flex flex-wrap items-center gap-x-6 gap-y-0">
            <a href="{{ route('terms') }}" class="inline-flex min-h-11 items-center hover:text-brand">Terms</a>
            <a href="{{ route('privacy-policy') }}" class="inline-flex min-h-11 items-center hover:text-brand">Privacy</a>
            <a href="{{ route('cookie-policy') }}" class="inline-flex min-h-11 items-center hover:text-brand">Cookie Policy</a>
            <button type="button" data-cookie-preferences-open class="min-h-11 hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Cookie Preferences</button>
            <span class="inline-flex min-h-11 items-center">&copy; {{ date('Y') }} JMMart</span>
        </nav>
    </div>
</footer>

@if (request()->routeIs('home'))
    <nav class="sticky bottom-0 z-40 flex border-t border-line bg-white pb-1 sm:hidden" aria-label="Mobile marketplace navigation">
        <a href="{{ route('home') }}" aria-current="page" class="flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 text-xs font-semibold text-brand"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1V10Z"/></svg>Home</a>
        <a href="{{ route('catalog.index') }}" class="flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 text-xs text-muted"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>Shop</a>
        @if (! auth()->check() || in_array(auth()->user()->account_type, ['buyer', 'seller'], true))
            <a href="{{ auth()->check() ? route('cart.index') : route('login', ['redirect' => '/cart']) }}" class="flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 text-xs text-muted"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 3h2l2.4 12.1a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.6L21 7H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>Cart</a>
        @endif
        @if ($mobileNavCanUseMessages)
            <a href="{{ $mobileNavMessagesUrl }}" class="flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 text-xs text-muted" aria-label="Messages{{ $mobileNavUnreadMessageCount ? ', '.$mobileNavUnreadMessageCount.' unread' : '' }}">
                <span class="relative">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M21 12a8 8 0 0 1-8 8H4l2-3a8 8 0 1 1 15-5z"/></svg>
                    @if ($mobileNavUnreadMessageCount > 0)
                        <span aria-hidden="true" class="absolute -right-2 -top-1.5 grid h-[15px] min-w-[15px] place-items-center whitespace-nowrap rounded-full border-2 border-white bg-brand px-[3px] text-[9px] font-semibold leading-none tabular-nums text-white">{{ $mobileNavUnreadMessageCount > 99 ? '99+' : $mobileNavUnreadMessageCount }}</span>
                    @endif
                </span>
                Messages
            </a>
        @endif
        <a href="{{ auth()->check() ? route('profile.edit') : route('login') }}" class="flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 text-xs text-muted"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/></svg>Account</a>
    </nav>
@endif
