<x-guest-layout>
    <p class="text-sm text-muted">Signing you out…</p>
    <script>
        window.addEventListener('clerk:loaded', () => {
            const secure = window.location.protocol === 'https:' ? '; Secure' : '';
            document.cookie = `__session=; Max-Age=0; path=/${secure}; SameSite=Lax`;
            window.clerk.signOut({ redirectUrl: '/' });
        }, { once: true });
    </script>
</x-guest-layout>
