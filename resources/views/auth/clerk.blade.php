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
            : ($isSignUp ? 'Email and password — that’s all it takes.' : (config('marketplace.web_store.enabled') ? 'Sign in to check out, track orders and chat with a pharmacist.' : 'Seller Center and admin workspace.')));
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
    <title>{{ $isSignUp ? 'Create account' : 'Sign in' }} · Everyday Pharmacy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        @keyframes auth-sun-rise { from { transform: translateY(56px) rotate(-12deg); opacity: 0; } }
        .auth-sun { animation: auth-sun-rise 1.2s cubic-bezier(.16, 1, .3, 1) both; }
        @media (prefers-reduced-motion: reduce) { .auth-sun { animation: none; } }
        @unless (config('marketplace.web_store.enabled'))
            {{-- Staff accounts are created by invitation while the store is closed, so Clerk's "Sign up" link leads nowhere. --}}
            .cl-footerAction { display: none !important; }
        @endunless
    </style>
</head>
{{-- The logo at page scale: the sign-in card is the cloud, and the sun rises behind it. --}}
<body class="flex min-h-screen flex-col bg-[linear-gradient(180deg,#dcebfa_0%,#eef5fd_62%,#f3f6fb_100%)] font-sans text-ink antialiased selection:bg-[#fff4d1] selection:text-[#0a1a6e]">
    <header class="px-[18px] py-4 sm:px-10 sm:py-6">
        <a href="{{ config('marketplace.web_store.enabled') ? route('catalog.index') : route('login') }}" class="inline-flex items-center gap-2.5 text-base font-bold tracking-[-0.01em] text-[#0a1a6e]">
            <img src="{{ asset('favicon.svg') }}" alt="" class="size-9">
            Everyday Pharmacy
        </a>
    </header>

    <main class="grid flex-1 place-items-start justify-center overflow-x-clip px-[18px] pb-10 pt-16 sm:place-items-center sm:px-6 sm:pb-16 sm:pt-14">
        <div class="relative w-full max-w-[440px]">
            <img src="{{ asset('images/brand/sun.png') }}" alt="" class="auth-sun pointer-events-none absolute -right-[26px] -top-[70px] w-[148px] sm:-right-[132px] sm:-top-[118px] sm:w-[264px]">

            <section class="relative rounded-[18px] bg-white px-5 pb-5 pt-7 shadow-[0_2px_4px_rgb(7_28_148/5%),0_30px_60px_-30px_rgb(7_28_148/35%)] sm:rounded-[22px] sm:px-9 sm:pb-7 sm:pt-9" aria-labelledby="auth-heading" data-od-id="auth-card">
                @if (config('marketplace.web_store.enabled'))
                    <div class="mb-6 flex gap-1 rounded-md bg-[#eef5fd] p-1" role="tablist" aria-label="Sign in or create account">
                        <a href="{{ route('login', $redirectParams) }}" role="tab" @if (! $isSignUp) aria-selected="true" @endif @class(['flex min-h-11 flex-1 items-center justify-center rounded-md text-sm font-semibold transition', 'bg-white text-[#0a1a6e] shadow-sm' => ! $isSignUp, 'text-muted hover:text-ink' => $isSignUp])>Sign in</a>
                        <a href="{{ route('register', $redirectParams) }}" role="tab" @if ($isSignUp) aria-selected="true" @endif @class(['flex min-h-11 flex-1 items-center justify-center rounded-md text-sm font-semibold transition', 'bg-white text-[#0a1a6e] shadow-sm' => $isSignUp, 'text-muted hover:text-ink' => ! $isSignUp])>Create account</a>
                    </div>
                @endif

                <h1 id="auth-heading" class="text-balance pr-16 text-2xl font-extrabold leading-tight tracking-[-0.03em] text-[#0a1a6e] sm:pr-0 sm:text-[28px]">{{ $heading }}</h1>
                <p class="mt-2 text-sm leading-relaxed text-muted">{{ $subheading }}</p>

                <div class="mt-6">
                    <div data-clerk-component="{{ $mode }}" data-after-auth-url="{{ $afterAuthUrl }}" class="min-h-[22rem] w-full min-w-0 max-w-full"></div>
                    <noscript><p class="px-3 py-8 text-center text-sm text-muted">JavaScript is required to sign in with Clerk.</p></noscript>
                </div>
            </section>

            @if (config('marketplace.web_store.enabled'))
                <a href="{{ route('catalog.index') }}" class="mt-5 block text-center text-sm font-medium text-muted transition hover:text-ink">Browse without an account →</a>
            @else
                <p class="mt-5 flex items-start gap-3 px-2 text-[13px] leading-normal text-muted">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="mt-px size-[18px] shrink-0 text-[#071c94]" aria-hidden="true"><rect x="6.5" y="2.5" width="11" height="19" rx="2.5"/><path stroke-linecap="round" d="M10.5 18.5h3"/></svg>
                    Customers shop in the Everyday Pharmacy mobile app.
                </p>
            @endif
        </div>
    </main>

    <footer class="flex flex-wrap items-center justify-center gap-x-4 px-[18px] pb-4 text-xs text-muted">
        <span>&copy; {{ date('Y') }} Everyday Pharmacy</span>
        <a href="{{ route('privacy-policy') }}" class="inline-flex min-h-11 items-center hover:text-ink">Privacy</a>
        <a href="{{ route('terms') }}" class="inline-flex min-h-11 items-center hover:text-ink">Terms</a>
        <button type="button" data-cookie-preferences-open class="min-h-11 hover:text-ink">Cookie preferences</button>
    </footer>
    <x-cookie-consent />
</body>
</html>
