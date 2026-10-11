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
    <title>{{ $mode === 'sign-up' ? 'Create account' : 'Sign in' }} · JMMart</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-screen bg-canvas font-sans text-ink antialiased">
    <header class="border-b border-line bg-white">
        <div class="mx-auto flex h-[60px] max-w-[1120px] items-center gap-3 px-[18px] sm:px-8">
            <a href="{{ route('catalog.index') }}" class="flex items-center gap-2.5 font-bold tracking-[-0.02em]" aria-label="JMMart marketplace home">
                <x-application-logo class="size-9 fill-current text-brand" />
                <span class="text-lg">JMMart</span>
            </a>
            <span class="ml-auto hidden text-sm text-muted sm:block">Browse freely · sign in to manage your cart</span>
        </div>
    </header>

    <main class="mx-auto grid max-w-[1120px] items-start gap-10 px-[18px] py-12 sm:px-8 lg:grid-cols-[minmax(0,1fr)_420px]">
        <div>
            <p class="font-mono text-xs uppercase tracking-[0.08em] text-brand">Guest → Customer</p>
            <h1 class="mt-3 max-w-[22ch] text-balance text-4xl font-bold leading-tight tracking-[-0.035em]">Browse everything. Sign in only when you need to.</h1>
            <p class="mt-4 max-w-[52ch] text-base leading-7 text-muted">Browse products and visit stores without an account. Sign in to add items to your cart, check out, track orders, or message sellers.</p>

            <div class="mt-8 rounded-[16px] border border-line bg-white p-5 sm:p-6">
                <h2 class="font-semibold">{{ request('redirect') ? 'Sign in to continue' : 'Your account unlocks more' }}</h2>
                <p class="mt-1 text-sm leading-6 text-muted">{{ request('add_to_cart') ? 'After signing in, this item will be added to your cart.' : (request('redirect') ? 'After signing in, you’ll return to the page you were viewing.' : 'Keep a cart, check out, track orders, and contact sellers in one place.') }}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('login', array_filter(['redirect' => request('redirect')])) }}" class="inline-flex min-h-11 items-center rounded-[10px] bg-brand px-4 text-sm font-semibold text-white transition hover:bg-brand-hover">Sign in</a>
                    <a href="{{ route('register', array_filter(['redirect' => request('redirect')])) }}" class="inline-flex min-h-11 items-center rounded-[10px] border border-line bg-white px-4 text-sm font-semibold transition hover:border-brand">Create account</a>
                    <a href="{{ route('catalog.index') }}" class="inline-flex min-h-11 items-center rounded-[10px] px-3 text-sm font-semibold text-muted transition hover:text-ink">Continue browsing</a>
                </div>
            </div>
        </div>
        <section class="rounded-[16px] border border-line bg-white p-5 sm:p-7" aria-labelledby="auth-heading" data-od-id="auth-card">
            <div class="flex gap-1 rounded-[10px] bg-canvas p-1" role="tablist" aria-label="Sign in or create account">
                <a href="{{ route('login', array_filter(['redirect' => request('redirect')])) }}" role="tab" @if ($mode === 'sign-in') aria-selected="true" @endif @class(['flex min-h-11 flex-1 items-center justify-center rounded-lg text-sm font-semibold transition', 'bg-white text-ink shadow-sm' => $mode === 'sign-in', 'text-muted hover:text-ink' => $mode !== 'sign-in'])>Sign in</a>
                <a href="{{ route('register', array_filter(['redirect' => request('redirect')])) }}" role="tab" @if ($mode === 'sign-up') aria-selected="true" @endif @class(['flex min-h-11 flex-1 items-center justify-center rounded-lg text-sm font-semibold transition', 'bg-white text-ink shadow-sm' => $mode === 'sign-up', 'text-muted hover:text-ink' => $mode !== 'sign-up'])>Create account</a>
            </div>
            <h2 id="auth-heading" class="mt-5 text-xl font-semibold">{{ $mode === 'sign-up' ? 'Create your account' : 'Welcome back' }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $mode === 'sign-up' ? 'Name, email, and password — that’s all it takes.' : 'Sign in to check out, track orders, and message sellers.' }}</p>
            <div class="mt-5">
                <div data-clerk-component="{{ $mode }}" data-after-auth-url="{{ $afterAuthUrl }}" class="min-h-[28rem] w-full min-w-0 max-w-full"></div>
                <noscript><p class="px-3 py-8 text-center text-sm text-muted">JavaScript is required to sign in with Clerk.</p></noscript>
            </div>
            <a href="{{ route('catalog.index') }}" class="mt-5 block text-center text-sm font-medium text-muted transition hover:text-ink">Back to marketplace</a>
        </section>
    </main>
    <x-cookie-consent />
</body>
</html>
