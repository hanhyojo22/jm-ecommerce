@php
    $isSignUp = $mode === 'sign-up';
    $redirectParams = array_filter(['redirect' => request('redirect')]);
    $isAddingToCart = filled(request('add_to_cart'));
    $heading = $isAddingToCart
        ? ($isSignUp ? 'Create an account to add this item' : 'Sign in to add this to your cart')
        : ($isSignUp ? 'Create your account' : 'Welcome back');
    $subheading = $isAddingToCart
        ? 'We’ll add it for you and take you straight back to the item.'
        : (filled(request('redirect'))
            ? 'You’ll return to the page you were viewing.'
            : ($isSignUp ? 'Email and password — that’s all it takes.' : 'Sign in to check out, track orders, and message sellers.'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta name="clerk-publishable-key" content="{{ config('services.clerk.publishable_key') }}">
    <meta name="clerk-sign-in-url" content="{{ config('services.clerk.sign_in_url') }}">
    <meta name="clerk-sign-up-url" content="{{ config('services.clerk.sign_up_url') }}">
    <title>{{ $isSignUp ? 'Create account' : 'Sign in' }} · JMMart</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400..700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-screen bg-surface font-sans text-ink antialiased">
    <div class="flex items-center gap-3 bg-[linear-gradient(120deg,#1c1917,#2b241d)] px-[18px] py-4 lg:hidden">
        <a href="{{ route('catalog.index') }}" class="flex shrink-0 items-center gap-2.5 font-display font-semibold tracking-[-0.02em] text-white" aria-label="JMMart marketplace home">
            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-white text-sm font-bold text-[#8a5a12]" aria-hidden="true">JM</span>
            <span class="sr-only">JMMart</span>
        </a>
        <p class="min-w-0 text-[13px] leading-snug text-[#d8d0c3]"><strong class="font-semibold text-white">JMMart</strong> — protected checkout, order tracking, and direct seller chat.</p>
    </div>

    <div class="lg:grid lg:min-h-screen lg:grid-cols-2">
        <aside class="relative hidden flex-col overflow-hidden bg-[linear-gradient(158deg,#1c1917_0%,#241f1a_58%,#12100e_100%)] px-12 py-10 text-white lg:flex">
            <span class="pointer-events-none absolute -right-36 top-10 size-[460px] rounded-full bg-[radial-gradient(circle_at_35%_35%,rgba(201,164,106,.28),transparent_62%)]" aria-hidden="true"></span>
            <a href="{{ route('catalog.index') }}" class="relative z-10 inline-flex items-center gap-2.5 self-start font-display text-lg font-semibold tracking-[-0.02em] text-white" aria-label="JMMart marketplace home">
                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-white text-sm font-bold text-[#8a5a12]" aria-hidden="true">JM</span>
                JMMart
            </a>

            <div class="relative z-10 my-auto max-w-[27rem] py-10">
                <h2 class="font-display text-balance text-[32px] font-semibold leading-[1.18] tracking-[-0.035em]">Everything you need, from sellers you can check.</h2>
                <p class="mt-2.5 text-[15px] leading-relaxed text-[#cfc7ba]">Your account keeps the cart, the orders, and the conversation in one place.</p>

                <dl class="mt-7">
                    @foreach ([
                        ['Cart that waits for you', 'Add now, decide later — it’s there on your next visit.', 'M3.5 4.5h2l2.2 10.2h9.1l2.2-7.4H7'],
                        ['Track every order', 'From confirmed to delivered, per seller, in one list.', 'M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z'],
                        ['Message the seller', 'Ask about stock or delivery before you commit.', 'M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l-1 2v-6.5a7.5 7.5 0 1 1 16-3Z'],
                    ] as [$title, $copy, $path])
                        <div @class(['flex gap-3.5 py-3.5', 'border-t border-white/10' => ! $loop->first])>
                            <span class="grid size-[38px] shrink-0 place-items-center rounded-[11px] bg-white/10 text-[#c9a46a]" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-[19px]"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
                            </span>
                            <div class="min-w-0">
                                <dt class="text-sm font-semibold">{{ $title }}</dt>
                                <dd class="mt-0.5 text-[13px] leading-relaxed text-[#cfc7ba]">{{ $copy }}</dd>
                            </div>
                        </div>
                    @endforeach
                </dl>
            </div>

            <p class="relative z-10 flex items-center gap-2.5 text-[12.5px] font-medium text-[#a9a195]">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" class="size-[15px] shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4.5 6v5.5c0 4.6 3.1 8.3 7.5 9.5 4.4-1.2 7.5-4.9 7.5-9.5V6L12 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="m9.2 12.2 2 2 3.6-3.8"/></svg>
                Payments are processed on verified stores only
            </p>
        </aside>

        <main class="flex items-start justify-center px-[18px] py-7 sm:px-8 lg:items-center lg:py-12">
            <section class="w-full max-w-[392px]" aria-labelledby="auth-heading" data-od-id="auth-card">
                <div class="flex gap-1 rounded-[10px] bg-canvas p-1" role="tablist" aria-label="Sign in or create account">
                    <a href="{{ route('login', $redirectParams) }}" role="tab" @if (! $isSignUp) aria-selected="true" @endif @class(['flex min-h-11 flex-1 items-center justify-center rounded-lg text-sm font-semibold transition', 'bg-white text-ink shadow-sm' => ! $isSignUp, 'text-muted hover:text-ink' => $isSignUp])>Sign in</a>
                    <a href="{{ route('register', $redirectParams) }}" role="tab" @if ($isSignUp) aria-selected="true" @endif @class(['flex min-h-11 flex-1 items-center justify-center rounded-lg text-sm font-semibold transition', 'bg-white text-ink shadow-sm' => $isSignUp, 'text-muted hover:text-ink' => ! $isSignUp])>Create account</a>
                </div>

                <h1 id="auth-heading" class="mt-5 text-balance font-display text-[30px] font-semibold leading-tight tracking-[-0.03em]">{{ $heading }}</h1>
                <p class="mt-1.5 text-sm leading-relaxed text-muted">{{ $subheading }}</p>

                <div class="mt-5">
                    <div data-clerk-component="{{ $mode }}" data-after-auth-url="{{ $afterAuthUrl }}" class="min-h-[28rem] w-full min-w-0 max-w-full"></div>
                    <noscript><p class="px-3 py-8 text-center text-sm text-muted">JavaScript is required to sign in with Clerk.</p></noscript>
                </div>

                <ul class="mt-6 grid grid-cols-3 gap-2.5 lg:hidden">
                    @foreach ([
                        ['Protected checkout', 'M12 3 4.5 6v5.5c0 4.6 3.1 8.3 7.5 9.5 4.4-1.2 7.5-4.9 7.5-9.5V6L12 3Z'],
                        ['Order tracking', 'M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z'],
                        ['Message sellers', 'M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l-1 2v-6.5a7.5 7.5 0 1 1 16-3Z'],
                    ] as [$label, $path])
                        <li class="flex flex-col items-center gap-1.5 text-center text-xs font-semibold leading-tight text-muted">
                            <span class="grid size-9 place-items-center rounded-full bg-blue-50 text-brand" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" class="size-[17px]"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
                            </span>
                            {{ $label }}
                        </li>
                    @endforeach
                </ul>

                <a href="{{ route('catalog.index') }}" class="mt-6 block text-center text-sm font-medium text-muted transition hover:text-ink">Browse without an account →</a>
            </section>
        </main>
    </div>
    <x-marketplace.footer />
    <x-cookie-consent />
</body>
</html>
