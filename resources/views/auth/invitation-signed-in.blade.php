<x-guest-layout>
    <h1 class="text-lg font-semibold text-ink">Sign out to accept this invitation</h1>
    <p class="mt-2 text-sm text-muted">This browser is signed in as <strong class="text-ink">{{ $email }}</strong>. The invitation is for a new team member, so sign out first, then create their login with the invited email.</p>
    <form method="POST" action="{{ route('logout') }}" class="mt-5">
        @csrf
        <input type="hidden" name="redirect" value="{{ $invitationUrl }}">
        <button type="submit" class="min-h-11 w-full rounded-lg bg-brand px-4 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Sign out and accept the invitation</button>
    </form>
    <a href="{{ route(auth()->user()->landingRouteName()) }}" class="mt-3 inline-flex min-h-11 items-center text-sm font-semibold text-brand">Stay signed in as {{ $email }}</a>
</x-guest-layout>
