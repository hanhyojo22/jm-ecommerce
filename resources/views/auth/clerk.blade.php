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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-screen bg-surface font-sans text-ink antialiased">
    <x-auth.mobile-bar />

    <div class="lg:grid lg:min-h-screen lg:grid-cols-2">
        <x-auth.brand-aside />

        <main class="flex items-start justify-center px-[18px] py-7 sm:px-8 lg:items-center lg:py-12">
            <section class="w-full max-w-[392px]" aria-labelledby="auth-heading" data-od-id="auth-card">
                <div class="flex gap-1 rounded-md bg-canvas p-1" role="tablist" aria-label="Sign in or create account">
                    <a href="{{ route('login', $redirectParams) }}" role="tab" @if (! $isSignUp) aria-selected="true" @endif @class(['flex min-h-11 flex-1 items-center justify-center rounded-lg text-sm font-semibold transition', 'bg-white text-ink shadow-sm' => ! $isSignUp, 'text-muted hover:text-ink' => $isSignUp])>Sign in</a>
                    <a href="{{ route('register', $redirectParams) }}" role="tab" @if ($isSignUp) aria-selected="true" @endif @class(['flex min-h-11 flex-1 items-center justify-center rounded-lg text-sm font-semibold transition', 'bg-white text-ink shadow-sm' => $isSignUp, 'text-muted hover:text-ink' => ! $isSignUp])>Create account</a>
                </div>

                <h1 id="auth-heading" class="mt-5 text-balance font-display text-3xl font-semibold leading-tight tracking-[-0.03em]">{{ $heading }}</h1>
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
