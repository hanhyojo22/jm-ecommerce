<x-guest-layout>
    <div data-clerk-component="{{ $mode }}" data-after-auth-url="{{ $afterAuthUrl }}" class="min-h-[28rem] w-full min-w-0 max-w-full"></div>
    <noscript><p class="px-3 py-8 text-center text-sm text-slate-600">JavaScript is required to sign in with Clerk.</p></noscript>
</x-guest-layout>
