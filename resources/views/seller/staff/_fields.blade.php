@php
    $formKey = $member?->id ?? 'invite';
    $restore = $errors->any() && old('_staff_form') === $formKey;
    $suffix = $member ? '-'.$member->id : '';
    $self = $member && $member->user_id === auth()->id();
    $pharmacist = $restore ? (bool) old('is_pharmacist') : (bool) $member?->is_pharmacist;
@endphp

<div class="sc-member-fields" x-data="{ pharmacist: @js($pharmacist) }">
    <input type="hidden" name="_staff_form" value="{{ $formKey }}">
    @if ($restore)
        <x-input-error :messages="$errors->all()" />
    @endif
    @unless ($member)
        <div>
            <label for="email" class="sc-field-label">Email</label>
            <input id="email" name="email" type="email" required autocomplete="off" value="{{ $restore ? old('email') : '' }}" class="field-control">
            <p class="mt-2 text-xs text-muted">They sign in with this email to join your team.</p>
        </div>
    @endunless
    @unless ($isOwner && $self)
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="role{{ $suffix }}" class="sc-field-label">Role</label>
                <select id="role{{ $suffix }}" name="role" class="field-control">
                    <option value="staff" @selected(($restore ? old('role') : $member?->role) === 'staff')>Staff</option>
                    @if ($canAssignManagers)<option value="manager" @selected(($restore ? old('role') : $member?->role) === 'manager')>Manager</option>@endif
                </select>
            </div>
            <div>
                <label for="store_id{{ $suffix }}" class="sc-field-label">Works at</label>
                <select id="store_id{{ $suffix }}" name="store_id" class="field-control">
                    @if ($isOwner)<option value="">Head office (every branch)</option>@endif
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(($restore ? old('store_id') : $member?->store_id) === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    @endunless
    @if ($isOwner)
        <div class="border-t border-line pt-4">
            <label class="flex min-h-11 items-center gap-2 text-sm font-medium text-ink">
                <input type="checkbox" name="is_pharmacist" value="1" x-model="pharmacist" @checked($pharmacist) class="size-4">
                {{ $self ? 'I am a pharmacist' : 'Pharmacist' }}
            </label>
            <div x-show="pharmacist" x-cloak class="mt-3 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="license_number{{ $suffix }}" class="sc-field-label">PRC license no.</label>
                    <input id="license_number{{ $suffix }}" name="license_number" inputmode="numeric" maxlength="20" value="{{ $restore ? old('license_number') : $member?->license_number }}" class="field-control">
                </div>
                <div>
                    <label for="license_expires_on{{ $suffix }}" class="sc-field-label">License valid until</label>
                    <input id="license_expires_on{{ $suffix }}" name="license_expires_on" type="date" value="{{ $restore ? old('license_expires_on') : $member?->license_expires_on?->toDateString() }}" class="field-control">
                </div>
            </div>
        </div>
    @endif
</div>
