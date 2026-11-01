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
<body class="min-h-screen bg-white font-sans text-ink antialiased">
    {{-- The mark is the only indicator: no card, no backdrop, no progress bar. --}}
    <main class="flex min-h-screen items-center justify-center px-6 py-10">
        <div class="w-full max-w-[26rem] text-center">
            <div
                data-clerk-session-handoff
                data-session-endpoint="{{ route('clerk.handoff.complete') }}"
                data-redirect-url="{{ $afterAuthUrl }}"
                data-sign-in-url="{{ $signInUrl ?? route('login') }}"
            >
                <span class="handoff-mark" aria-hidden="true">
                    <span class="handoff-mark-tile">JM</span>
                </span>

                <h1 class="mt-6 text-balance font-display text-xl font-semibold leading-[1.3] tracking-[-0.02em] sm:text-[1.375rem]">Finishing your sign-in</h1>
                <p class="mt-1.5 text-sm leading-relaxed text-muted">
                    <span data-handoff-status role="status" aria-live="polite">Checking your sign-in…</span>
                </p>

                <a
                    href="{{ $signInUrl ?? route('login') }}"
                    class="handoff-escape mt-2.5 inline-flex min-h-11 items-center text-[13px] font-medium text-muted underline decoration-ink/25 underline-offset-4 transition hover:text-ink hover:decoration-ink/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
                >Taking longer than expected? Sign in again</a>
            </div>

            <div
                data-clerk-handoff-error
                hidden
                role="alert"
            >
                <span class="handoff-mark" data-stopped aria-hidden="true">
                    <span class="handoff-mark-tile">JM</span>
                </span>

                <h1 data-clerk-handoff-error-title tabindex="-1" class="mt-6 text-balance font-display text-xl font-semibold leading-[1.3] tracking-[-0.02em] outline-none sm:text-[1.375rem]">We could not finish signing you in.</h1>
                <p class="mt-1.5 text-sm leading-relaxed text-muted">The hand-off did not complete. Check your connection, then try again.</p>
                <p class="mt-1 text-xs leading-relaxed text-muted">Nothing has been changed yet.</p>

                <div class="mt-6 flex flex-col items-center gap-1.5">
                    <button type="button" data-clerk-handoff-retry class="inline-flex min-h-11 items-center justify-center rounded-md border border-transparent bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">Try again</button>
                    <a href="{{ $signInUrl ?? route('login') }}" class="inline-flex min-h-11 items-center px-2 text-[13px] font-medium text-muted underline decoration-ink/25 underline-offset-4 transition hover:text-ink hover:decoration-ink/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">Return to sign in</a>
                </div>
            </div>

            <noscript><p class="mt-6 text-sm leading-relaxed text-muted">JavaScript is required to finish signing in. Please enable JavaScript and try again.</p></noscript>
        </div>
    </main>

    <x-cookie-consent />

    <script>
        /*
         * Finishing a hand-off needs the page bundle, Clerk and the server to answer in turn.
         * Any of them stalling would leave the mark turning with nothing to explain it, so this
         * deadline — which needs no bundle of its own — swaps in the failure instead. The bundle
         * extends it as each step is reached, so only a hand-off that is genuinely stuck is
         * called stuck; one that finishes later simply navigates away.
         *
         * The status line follows the steps the bundle actually takes. Until it reports one, the
         * page claims only that it is checking the sign-in it arrived with.
         */
        (() => {
            const handoff = document.querySelector('[data-clerk-session-handoff]');
            const error = document.querySelector('[data-clerk-handoff-error]');
            const title = error?.querySelector('[data-clerk-handoff-error-title]');
            const status = handoff?.querySelector('[data-handoff-status]');

            const labels = {
                load: 'Checking your sign-in…',
                session: 'Confirming your account with Clerk…',
                server: 'Securing your JMMart session…',
                done: 'Taking you back…',
            };

            const stage = (name) => {
                if (!labels[name] || !status) {
                    return;
                }

                status.textContent = labels[name];
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
                title?.focus({ preventScroll: true });
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
