{{-- Who is signed in to Seller Center: the pharmacy and where they work, then the person, their role and pharmacist license. --}}
@php
    $member = auth()->user()?->sellerMembership();
    $workplace = $member?->store ?? $member?->tenant?->store;
    $licenseStatus = $member?->licenseStatus();
    $expiresOn = $member?->license_expires_on?->format('M j, Y');
@endphp

@if ($member)
    <div {{ $attributes->class('workspace-identity') }} data-seller-identity>
        <div class="workspace-identity-row">
            <span class="workspace-sidebar-avatar" aria-hidden="true">
                @if ($member->tenant->store?->logo_path)
                    <img src="{{ Storage::disk('public')->url($member->tenant->store->logo_path) }}" alt="" class="size-full object-cover">
                @else
                    {{ Str::upper(Str::substr($member->tenant->name, 0, 1)) }}
                @endif
            </span>
            <div class="min-w-0">
                <p class="text-sm font-semibold leading-snug text-ink line-clamp-2 [overflow-wrap:anywhere]">{{ $member->tenant->name }}</p>
                <p class="mt-0.5 flex min-w-0 items-center gap-1.5 text-xs text-muted">
                    <span @class(['size-1.5 shrink-0 rounded-full', 'bg-success' => $workplace?->status === 'active', 'bg-danger' => $workplace?->status === 'suspended', 'bg-muted' => ! in_array($workplace?->status, ['active', 'suspended'], true)]) aria-hidden="true"></span>
                    <span class="truncate">{{ $member->placeLabel() }}@if ($workplace?->status === 'suspended') · suspended @endif</span>
                </p>
            </div>
        </div>
        <div class="workspace-identity-row workspace-identity-person">
            <span class="workspace-identity-initials" aria-hidden="true">{{ Str::upper(collect(explode(' ', trim(auth()->user()->name)))->filter()->take(2)->map(fn (string $part): string => Str::substr($part, 0, 1))->implode('')) }}</span>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-ink">{{ auth()->user()->name }}</p>
                <p class="workspace-identity-role">{{ $member->roleLabel() }}</p>
            </div>
        </div>
        @if ($licenseStatus)
            <p class="workspace-license" data-status="{{ $licenseStatus }}">
                @if (in_array($licenseStatus, ['valid', 'expiring'], true))
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 5 6v6c0 4.4 3 7.6 7 9 4-1.4 7-4.6 7-9V6z" /><path d="m9 12 2 2 4-4" /></svg>
                @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 2 20h20z" /><path d="M12 10v4M12 17h.01" /></svg>
                @endif
                <span><strong>Pharmacist</strong> · {{ match ($licenseStatus) {
                    'valid' => "PRC {$member->license_number}",
                    'expiring' => "license expires {$expiresOn}",
                    'expired' => "license expired {$expiresOn}",
                    default => 'no PRC license on file',
                } }}</span>
            </p>
        @endif
    </div>
@endif
