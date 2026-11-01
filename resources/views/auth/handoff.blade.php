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
<body class="min-h-screen overflow-hidden bg-canvas font-sans text-ink antialiased">
    {{-- The marketplace is already behind the dialog; it settles and blurs while the hand-off runs. --}}
    <div class="handoff-scene" aria-hidden="true">
        <header class="flex items-center gap-4 border-b border-line bg-white px-6 py-4">
            <span class="flex shrink-0 items-center gap-2.5 text-[15px] font-semibold">
                <span class="grid size-8 place-items-center rounded-lg bg-brand text-[11px] font-semibold text-white">JM</span>
                JMMart
            </span>
            <span class="h-10 flex-1 rounded-md border border-line bg-canvas"></span>
            <span class="h-10 w-28 rounded-md border border-line bg-white"></span>
            <span class="size-9 shrink-0 rounded-full bg-[#dfe6f0]"></span>
        </header>

        <div class="mx-auto w-full max-w-[1320px] px-6 py-6">
            <div class="flex gap-2">
                <span class="h-7 w-24 rounded-full bg-white"></span>
                <span class="h-7 w-28 rounded-full bg-white"></span>
                <span class="h-7 w-20 rounded-full bg-white"></span>
                <span class="h-7 w-24 rounded-full bg-white"></span>
            </div>

            <div class="mt-5 h-52 rounded-lg border border-line bg-[linear-gradient(140deg,#eaf1fa,#dce8f7)]"></div>

            <div class="mt-7 grid grid-cols-2 gap-4 sm:grid-cols-4">
                @for ($card = 0; $card < 4; $card++)
                    <div class="overflow-hidden rounded-lg border border-line bg-white">
                        <div class="h-36 bg-[linear-gradient(150deg,#eef3fa,#e2eaf5)]"></div>
                        <div class="space-y-2 p-3">
                            <span class="block h-3.5 w-3/4 rounded bg-[#e9eef6]"></span>
                            <span class="block h-3 w-2/5 rounded bg-[#eef1f5]"></span>
                        </div>
                    </div>
                @endfor
            </div>
        </div>
    </div>

    <div class="handoff-scrim" aria-hidden="true"></div>

    <main class="handoff-stage">
        <div class="handoff-modal" role="dialog" aria-labelledby="handoff-heading" tabindex="-1" data-handoff-modal>
            <div
                data-clerk-session-handoff
                data-handoff-stage="load"
                data-session-endpoint="{{ route('clerk.handoff.complete') }}"
                data-redirect-url="{{ $afterAuthUrl }}"
                data-sign-in-url="{{ $signInUrl ?? route('login') }}"
            >
                <span class="handoff-spinner" aria-hidden="true"></span>

                <h1 id="handoff-heading" class="mt-5 font-display text-[1.3rem] font-semibold leading-tight tracking-[-0.02em]">Finishing your sign-in</h1>
                <p class="mt-1.5 text-sm leading-relaxed text-muted">
                    <span data-handoff-status role="status" aria-live="polite">Checking your sign-in…</span>
                </p>

                <div class="handoff-rail" aria-hidden="true">
                    <span class="handoff-seg" data-handoff-seg data-state="active"></span>
                    <span class="handoff-seg" data-handoff-seg data-state="pending"></span>
                    <span class="handoff-seg" data-handoff-seg data-state="pending"></span>
                </div>

                <p class="mt-5 text-[13px] leading-relaxed text-muted">You’ll be back in the marketplace in a moment. Nothing is charged or changed while you wait.</p>

                <a
                    href="{{ $signInUrl ?? route('login') }}"
                    class="mt-1 inline-flex min-h-11 items-center text-[13px] font-medium text-muted underline decoration-line underline-offset-4 transition hover:text-ink hover:decoration-ink/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
                >Taking longer than expected? Sign in again</a>
            </div>

            <div
                data-clerk-handoff-error
                hidden
                role="alert"
            >
                <span class="mx-auto grid size-10 place-items-center rounded-full bg-danger/10 text-danger" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" class="size-[18px]"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v5"/><circle cx="12" cy="16.5" r=".9" fill="currentColor" stroke="none"/><path stroke-linecap="round" stroke-linejoin="round" d="M10.3 4.2 3.2 16.4A2 2 0 0 0 4.9 19.4h14.2a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0Z"/></svg>
                </span>
                <h1 data-clerk-handoff-error-title id="handoff-error-heading" tabindex="-1" class="mt-4 text-lg font-semibold leading-snug text-ink outline-none">We could not finish signing you in.</h1>
                <p class="mt-1.5 text-[13px] leading-relaxed text-muted">The hand-off did not complete. Check your connection, then try again — nothing has been changed yet.</p>
                <div class="mt-5 flex flex-col gap-2.5">
                    <a href="{{ $signInUrl ?? route('login') }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-transparent bg-brand px-4 text-sm font-semibold text-white transition hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">Return to sign in</a>
                    <button type="button" data-clerk-handoff-retry class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-white px-4 text-sm font-semibold text-ink transition hover:bg-canvas focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">Try again</button>
                </div>
            </div>

            <noscript><p class="mt-5 text-[13px] leading-relaxed text-muted">JavaScript is required to finish signing in. Please enable JavaScript and try again.</p></noscript>
        </div>
    </main>

    <x-cookie-consent />

    <script>
        /*
         * Finishing a hand-off needs the page bundle, Clerk and the server to answer in turn.
         * Any of them stalling would leave the dialog spinning with nothing to explain it, so
         * this deadline — which needs no bundle of its own — shows the way out instead. The
         * bundle extends it as each step is reached, so only a hand-off that is genuinely stuck
         * is called stuck; one that finishes later simply navigates away.
         *
         * The rail and the line above it follow the steps the bundle actually takes. Until it
         * reports one, the dialog claims only that it is checking the sign-in it arrived with.
         */
        (() => {
            const handoff = document.querySelector('[data-clerk-session-handoff]');
            const error = document.querySelector('[data-clerk-handoff-error]');
            const title = error?.querySelector('[data-clerk-handoff-error-title]');
            const status = handoff?.querySelector('[data-handoff-status]');
            const segments = [...(handoff?.querySelectorAll('[data-handoff-seg]') ?? [])];
            const modal = document.querySelector('[data-handoff-modal]');

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
                    segment.dataset.state = position < reached ? 'done' : position === reached ? 'active' : 'pending';
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
                modal?.setAttribute('aria-labelledby', 'handoff-error-heading');
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

            modal?.focus({ preventScroll: true });
        })();
    </script>
</body>
</html>
