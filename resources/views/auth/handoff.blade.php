<x-guest-layout>
    <div
        data-clerk-session-handoff
        data-session-endpoint="{{ route('clerk.handoff.complete') }}"
        data-redirect-url="{{ $afterAuthUrl }}"
        role="status"
        aria-live="polite"
        class="space-y-3 rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm"
    >
        <span class="mx-auto block size-8 animate-spin rounded-full border-2 border-slate-200 border-t-emerald-700" aria-hidden="true"></span>
        <p class="font-semibold text-slate-900">Finishing your sign-in</p>
        <p class="text-sm text-slate-600">We're securely connecting your account to the marketplace.</p>
    </div>
    <noscript><p class="mt-4 text-center text-sm text-slate-600">JavaScript is required to finish signing in. Please enable JavaScript and try again.</p></noscript>
</x-guest-layout>
