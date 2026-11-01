<aside class="relative hidden flex-col overflow-hidden bg-[linear-gradient(158deg,#163a86_0%,#10234d_58%,#0b1a3c_100%)] px-12 py-10 text-white lg:flex">
    <span class="pointer-events-none absolute -right-36 top-10 size-[460px] rounded-full bg-[radial-gradient(circle_at_35%_35%,rgba(59,130,246,.4),transparent_62%)]" aria-hidden="true"></span>
    <a href="{{ route('catalog.index') }}" class="relative z-10 inline-flex items-center gap-2.5 self-start font-display text-lg font-semibold tracking-[-0.02em] text-white" aria-label="JMMart marketplace home">
        <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-white text-sm font-semibold text-[#10234d]" aria-hidden="true">JM</span>
        JMMart
    </a>

    <div class="relative z-10 my-auto max-w-[27rem] py-10">
        <h2 class="font-display text-balance text-3xl font-semibold leading-[1.18] tracking-[-0.035em]">Everything you need, from sellers you can check.</h2>
        <p class="mt-2.5 text-[15px] leading-relaxed text-[#b9cbe8]">Your account keeps the cart, the orders, and the conversation in one place.</p>

        <dl class="mt-7">
            @foreach ([
                ['Cart that waits for you', 'Add now, decide later — it’s there on your next visit.', 'M3.5 4.5h2l2.2 10.2h9.1l2.2-7.4H7'],
                ['Track every order', 'From confirmed to delivered, per seller, in one list.', 'M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z'],
                ['Message the seller', 'Ask about stock or delivery before you commit.', 'M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l-1 2v-6.5a7.5 7.5 0 1 1 16-3Z'],
            ] as [$title, $copy, $path])
                <div @class(['flex gap-3.5 py-3.5', 'border-t border-white/10' => ! $loop->first])>
                    <span class="grid size-[38px] shrink-0 place-items-center rounded-md bg-white/10 text-[#93b4f5]" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-[19px]"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
                    </span>
                    <div class="min-w-0">
                        <dt class="text-sm font-semibold">{{ $title }}</dt>
                        <dd class="mt-0.5 text-[13px] leading-relaxed text-[#9db2d4]">{{ $copy }}</dd>
                    </div>
                </div>
            @endforeach
        </dl>
    </div>

    <p class="relative z-10 flex items-center gap-2.5 text-xs font-medium text-[#8fa6cb]">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" class="size-[15px] shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4.5 6v5.5c0 4.6 3.1 8.3 7.5 9.5 4.4-1.2 7.5-4.9 7.5-9.5V6L12 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="m9.2 12.2 2 2 3.6-3.8"/></svg>
        Payments are processed on verified stores only
    </p>
</aside>
