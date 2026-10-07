<x-guest-layout>
    <div
        data-clerk-session-handoff
        data-session-endpoint="{{ route('clerk.handoff.complete') }}"
        data-redirect-url="{{ $afterAuthUrl }}"
        role="status"
        aria-live="polite"
        class="space-y-3 rounded-[14px] border border-line bg-surface p-6 text-center"
    >
        <span class="mx-auto block size-8 animate-spin rounded-full border-2 border-line border-t-brand" aria-hidden="true"></span>
        <p class="font-semibold text-ink">Finishing your sign-in</p>
        <p class="text-sm text-muted">We're securely connecting your account to the marketplace.</p>
    </div>
    <noscript><p class="mt-4 text-center text-sm text-muted">JavaScript is required to finish signing in. Please enable JavaScript and try again.</p></noscript>
</x-guest-layout>
