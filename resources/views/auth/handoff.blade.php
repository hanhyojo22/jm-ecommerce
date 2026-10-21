<x-guest-layout>
    <div
        data-clerk-session-handoff
        data-session-endpoint="{{ route('clerk.handoff.complete') }}"
        data-redirect-url="{{ $afterAuthUrl }}"
        data-sign-in-url="{{ $signInUrl ?? route('login') }}"
        role="status"
        aria-live="polite"
        class="space-y-3 rounded-2xl border border-line bg-surface p-6 text-center"
    >
        <span class="mx-auto block size-8 animate-spin rounded-full border-2 border-line border-t-brand" aria-hidden="true"></span>
        <p class="font-semibold text-ink">Finishing your sign-in</p>
        <p class="text-sm text-muted">We're securely connecting your account to the marketplace.</p>
    </div>

    <div
        data-clerk-handoff-error
        hidden
        role="alert"
        class="space-y-3 rounded-2xl border border-danger/20 bg-danger/10 p-6 text-center"
    >
        <p data-clerk-handoff-error-title class="font-semibold text-ink">We could not finish signing you in.</p>
        <p class="text-sm text-danger">The hand-off did not complete. Check your connection, then try again — nothing has been changed yet.</p>
        <div class="flex flex-col items-center justify-center gap-3 sm:flex-row">
            <a href="{{ $signInUrl ?? route('login') }}" class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-transparent bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Return to sign in</a>
            <button type="button" data-clerk-handoff-retry class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition hover:border-ink/30 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Try again</button>
        </div>
    </div>

    <noscript><p class="mt-4 text-center text-sm text-muted">JavaScript is required to finish signing in. Please enable JavaScript and try again.</p></noscript>

    <script>
        /*
         * Finishing a hand-off needs the page bundle, Clerk and the server to answer in turn.
         * Any of them stalling would leave the spinner above turning with nothing to explain
         * it, so this deadline — which needs no bundle of its own — shows the way out instead.
         * The bundle extends it as each step is reached, so only a hand-off that is genuinely
         * stuck is called stuck; one that finishes later simply navigates away.
         */
        (() => {
            const handoff = document.querySelector('[data-clerk-session-handoff]');
            const error = document.querySelector('[data-clerk-handoff-error]');
            const title = error?.querySelector('[data-clerk-handoff-error-title]');

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
                fail,
            };

            error?.querySelector('[data-clerk-handoff-retry]')?.addEventListener('click', () => window.location.reload());
        })();
    </script>
</x-guest-layout>
