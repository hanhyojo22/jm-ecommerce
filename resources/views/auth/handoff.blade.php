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
    <title>Finishing your sign-in · JMMart</title>
    @include('partials.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#10234d] font-sans text-white antialiased">
    {{-- The held breath between Clerk's form and the marketplace: same navy as the sign-in panel. --}}
    <div class="relative isolate flex min-h-screen flex-col overflow-hidden bg-[linear-gradient(158deg,#163a86_0%,#10234d_58%,#0b1a3c_100%)]">
        <span class="handoff-glow" aria-hidden="true"></span>

        <header class="relative z-10 px-[18px] pt-6 sm:px-8 lg:px-12 lg:pt-9">
            <a href="{{ route('catalog.index') }}" class="inline-flex items-center gap-2.5 font-display text-lg font-semibold tracking-[-0.02em] text-white" aria-label="JMMart marketplace home">
                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-white text-sm font-semibold text-[#10234d]" aria-hidden="true">JM</span>
                JMMart
            </a>
        </header>

        <main class="relative z-10 flex flex-1 items-center px-[18px] py-12 sm:px-8 lg:px-12">
            <div class="w-full max-w-[34rem]">
                <section
                    data-clerk-session-handoff
                    data-handoff-stage="load"
                    data-session-endpoint="{{ route('clerk.handoff.complete') }}"
                    data-redirect-url="{{ $afterAuthUrl }}"
                    data-sign-in-url="{{ $signInUrl ?? route('login') }}"
                    role="status"
                    aria-live="polite"
                    class="handoff-wait relative"
                >
                    <div class="handoff-rail" aria-hidden="true">
                        <span class="handoff-seg" data-handoff-seg data-state="active"><span class="handoff-fill"></span></span>
                        <span class="handoff-seg" data-handoff-seg data-state="idle"><span class="handoff-fill"></span></span>
                        <span class="handoff-seg" data-handoff-seg data-state="idle"><span class="handoff-fill"></span></span>
                    </div>

                    <h1 class="mt-7 text-balance font-display text-[2.25rem] font-semibold leading-[1.04] tracking-[-0.035em] text-white sm:text-[2.6rem] lg:text-[3.2rem]">Finishing your sign-in</h1>
                    <p data-handoff-status class="mt-3.5 text-[15px] leading-relaxed text-[#d5e2f5] sm:text-base">Checking your sign-in…</p>
                    <p class="mt-5 max-w-[38ch] text-[13px] leading-relaxed text-[#a6bad9]">You’ll be back in the marketplace in a moment. Nothing is charged or changed while you wait.</p>

                    <a
                        href="{{ $signInUrl ?? route('login') }}"
                        class="handoff-restart absolute left-0 top-full mt-5 inline-flex min-h-11 items-center text-sm font-medium text-[#b3c6e2] underline decoration-white/25 underline-offset-4 transition hover:text-white hover:decoration-white/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70 focus-visible:ring-offset-4 focus-visible:ring-offset-[#132a56]"
                    >Taking longer than expected? Sign in again</a>
                </section>

                <div
                    data-clerk-handoff-error
                    hidden
                    role="alert"
                    class="max-w-[27rem] rounded-lg bg-white p-6 text-left text-ink shadow-[0_32px_72px_-36px_rgba(3,10,26,.85)]"
                >
                    <span class="grid size-9 place-items-center rounded-full bg-danger/10 text-danger" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" class="size-[18px]"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v5"/><circle cx="12" cy="16.5" r=".9" fill="currentColor" stroke="none"/><path stroke-linecap="round" stroke-linejoin="round" d="M10.3 4.2 3.2 16.4A2 2 0 0 0 4.9 19.4h14.2a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0Z"/></svg>
                    </span>
                    <h1 data-clerk-handoff-error-title class="mt-4 text-xl font-semibold leading-snug text-ink">We could not finish signing you in.</h1>
                    <p class="mt-1.5 text-sm leading-relaxed text-muted">The hand-off did not complete. Check your connection, then try again — nothing has been changed yet.</p>
                    <div class="mt-5 flex flex-col gap-2.5 sm:flex-row">
                        <a href="{{ $signInUrl ?? route('login') }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-transparent bg-brand px-4 text-sm font-semibold text-white transition hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">Return to sign in</a>
                        <button type="button" data-clerk-handoff-retry class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-white px-4 text-sm font-semibold text-ink transition hover:bg-canvas focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">Try again</button>
                    </div>
                </div>

                <noscript><p class="mt-6 text-sm leading-relaxed text-[#93a9cd]">JavaScript is required to finish signing in. Please enable JavaScript and try again.</p></noscript>
            </div>
        </main>
    </div>

    <x-cookie-consent />

    <script>
        /*
         * Finishing a hand-off needs the page bundle, Clerk and the server to answer in turn.
         * Any of them stalling would leave the progress above turning with nothing to explain
         * it, so this deadline — which needs no bundle of its own — shows the way out instead.
         * The bundle extends it as each step is reached, so only a hand-off that is genuinely
         * stuck is called stuck; one that finishes later simply navigates away.
         *
         * The three segments and the line under the heading follow the same steps the bundle
         * actually takes. Until it reports one, the page claims only that it is loading.
         */
        (() => {
            const handoff = document.querySelector('[data-clerk-session-handoff]');
            const error = document.querySelector('[data-clerk-handoff-error]');
            const title = error?.querySelector('[data-clerk-handoff-error-title]');
            const status = handoff?.querySelector('[data-handoff-status]');
            const segments = [...(handoff?.querySelectorAll('[data-handoff-seg]') ?? [])];

            const steps = ['load', 'session', 'server'];
            const labels = {
                load: 'Checking your sign-in…',
                session: 'Confirming your account with Clerk…',
                server: 'Securing your JMMart session…',
                done: 'Taking you back…',
            };

            const stage = (name) => {
                if (!handoff || !labels[name]) {
                    return;
                }

                const reached = name === 'done' ? steps.length : steps.indexOf(name);

                handoff.setAttribute('data-handoff-stage', name);
                segments.forEach((segment, position) => {
                    segment.dataset.state = position < reached ? 'done' : position === reached ? 'active' : 'idle';
                });

                if (status) {
                    status.textContent = labels[name];
                }
            };

            const fail = (message) => {
                window.clearTimeout(timer);

                if (!handoff || !error) {
                    return;
                }

                if (typeof message === 'string' && title) {
                    title.textContent = message;
                }

                handoff.setAttribute('hidden', '');
                error.removeAttribute('hidden');
            };

            let timer = window.setTimeout(fail, 15_000);

            window.clerkHandoffState = {
                /* The bundle reached a step of its own, and that step is allowed this long. */
                extend(milliseconds) {
                    window.clearTimeout(timer);
                    timer = window.setTimeout(fail, milliseconds);
                },
                finish() {
                    window.clearTimeout(timer);
                },
                stage,
                fail,
            };

            error?.querySelector('[data-clerk-handoff-retry]')?.addEventListener('click', () => window.location.reload());
        })();
    </script>
</body>
</html>
