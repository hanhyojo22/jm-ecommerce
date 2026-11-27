@props(['mode' => 'desktop', 'sellerTenant' => null])

@php
    $user = auth()->user();
    $member = $user?->sellerMembership();
    $webStoreEnabled = config('marketplace.web_store.enabled');
    $nameParts = collect(preg_split('/\s+/', trim((string) $user?->name)) ?: [])->filter();
    $initials = $nameParts->count() >= 2
        ? Str::upper(Str::substr((string) $nameParts->first(), 0, 1).Str::substr((string) $nameParts->last(), 0, 1))
        : Str::upper(Str::substr(trim((string) $user?->name), 0, 2));
    $initials = $initials !== '' ? $initials : 'S';

    if ($member === null) {
        $roleBadges = [['label' => 'Seller', 'tone' => 'staff']];
    } elseif ($member->role === 'staff' && $member->is_pharmacist) {
        $roleBadges = [['label' => 'Pharmacist', 'tone' => 'pharmacist']];
    } else {
        $roleBadges = [[
            'label' => $member->roleLabel(),
            'tone' => match ($member->role) {
                'owner' => 'owner',
                'manager' => 'manager',
                default => 'staff',
            },
        ]];

        if ($member->is_pharmacist) {
            $roleBadges[] = ['label' => 'Pharmacist', 'tone' => 'pharmacist'];
        }
    }

    $avatarTone = $roleBadges[0]['tone'];
    $isAccountPage = request()->routeIs('profile.edit');
@endphp

<div class="seller-studio-footer">
    <div class="seller-theme-toggle" role="group" aria-label="Appearance"
        x-data="{
            theme: document.documentElement.dataset.sellerTheme || 'dark',
            setTheme(next) {
                this.theme = next;
                document.documentElement.dataset.sellerTheme = next;
                try { localStorage.setItem('seller_center_theme', next); } catch (e) {}
                window.dispatchEvent(new CustomEvent('seller-theme-changed', { detail: next }));
            }
        }"
        @seller-theme-changed.window="theme = $event.detail">
        <button type="button" class="seller-theme-btn" @click="setTheme('dark')" :aria-pressed="(theme === 'dark').toString()">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" /></svg>
            <span>Dark</span>
        </button>
        <button type="button" class="seller-theme-btn" @click="setTheme('light')" :aria-pressed="(theme === 'light').toString()">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" /></svg>
            <span>Light</span>
        </button>
    </div>

    @if ($user)
        <div class="seller-studio-user-card" data-seller-user-card>
            <span class="seller-studio-avatar" data-role-tone="{{ $avatarTone }}" aria-hidden="true">{{ $initials }}</span>
            <div class="min-w-0 flex-1">
                <p class="seller-studio-user-name" title="{{ $user->name }}">{{ $user->name }}</p>
                <div class="seller-studio-role-list" data-seller-user-role>
                    @foreach ($roleBadges as $badge)
                        <span class="seller-studio-role-badge" data-role-tone="{{ $badge['tone'] }}">{{ $badge['label'] }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="seller-studio-footer-actions">
        @if ($mode === 'mobile' && $sellerTenant && $webStoreEnabled)
            <a href="{{ route('home') }}" @click="closeDrawer" class="workspace-nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5 12 4l9 6.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z" /></svg>
                <span>View website</span>
            </a>
        @endif

        <a href="{{ route('profile.edit') }}" @if ($mode === 'mobile') @click="closeDrawer" @endif class="workspace-nav-link" {{ $isAccountPage ? 'aria-current=page' : '' }}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M5 21a7 7 0 0 1 14 0" /></svg>
            <span>Account</span>
        </a>

        @if ($mode === 'mobile')
            <div class="seller-studio-footer-sep" aria-hidden="true"></div>
        @endif

        <button type="button" data-clerk-sign-out @if ($mode === 'mobile') @click="closeDrawer" @endif class="workspace-nav-link seller-studio-signout w-full text-left">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3" /><path d="m10 17 5-5-5-5M15 12H3" /></svg>
            <span>Sign out</span>
        </button>
    </div>
</div>
