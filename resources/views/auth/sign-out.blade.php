<x-guest-layout>
    <p class="text-sm text-muted">Signing you out…</p>
    <script>
        window.addEventListener('clerk:loaded', () => window.clerk.signOut({ redirectUrl: '/' }), { once: true });
    </script>
</x-guest-layout>
