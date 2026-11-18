<x-app-layout :error-summary="false">
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <h1 class="sc-title">Staff</h1>
                <p class="sc-subtitle">Who works for the pharmacy. Head office sees every branch; branch staff see only their branch. Only pharmacists with a valid PRC license can approve prescriptions.</p>
            </div>
        </div>
    </x-slot>

    @php($roleLabels = ['owner' => 'Owner', 'manager' => 'Manager', 'staff' => 'Staff'])

    <div class="page-shell">
        <div class="page-container seller-workspace-page sc-stack">
            @if (session('status'))<p class="sc-notice" data-tone="success" role="status">{{ session('status') }}</p>@endif
            <x-input-error :messages="$errors->get('member')" />
            <x-input-error :messages="$errors->get('license_number')" />
            <x-input-error :messages="$errors->get('license_expires_on')" />
            @unless ($isOwner)
                <p class="sc-notice" data-tone="info">{{ $canAssignManagers ? 'Only the owner can add head-office people, change them, or make someone a pharmacist.' : 'You add and change staff at your own branch. Head office adds managers and pharmacists.' }}</p>
            @endunless

            <form method="POST" action="{{ route('seller.staff.store') }}" x-data="{ pharmacist: @js((bool) old('is_pharmacist')) }" class="sc-card flex flex-wrap items-end gap-4 p-4" aria-labelledby="add-staff-heading">
                @csrf
                <h2 id="add-staff-heading" class="w-full font-semibold text-ink">Add an employee <span class="font-normal text-muted">— they sign in with this email to start</span></h2>
                <div class="min-w-56 flex-1">
                    <label for="email" class="block text-sm font-semibold text-ink">Email</label>
                    <input id="email" name="email" type="email" required autocomplete="off" value="{{ old('email') }}" class="field-control mt-2 w-full text-base lg:text-sm">
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
                <div>
                    <label for="role" class="block text-sm font-semibold text-ink">Role</label>
                    <select id="role" name="role" class="field-control mt-2 !w-32 text-base lg:text-sm">
                        <option value="staff" @selected(old('role') === 'staff')>Staff</option>
                        @if ($canAssignManagers)<option value="manager" @selected(old('role') === 'manager')>Manager</option>@endif
                    </select>
                </div>
                <div>
                    <label for="store_id" class="block text-sm font-semibold text-ink">Works at</label>
                    <select id="store_id" name="store_id" class="field-control mt-2 !w-56 text-base lg:text-sm">
                        @if ($isOwner)<option value="">Head office (every branch)</option>@endif
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('store_id') === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('store_id')" class="mt-2" />
                </div>
                @if ($isOwner)
                    <label class="flex min-h-11 items-center gap-2 text-sm font-semibold text-ink">
                        <input type="checkbox" name="is_pharmacist" value="1" x-model="pharmacist" @checked(old('is_pharmacist')) class="size-4">
                        Pharmacist
                    </label>
                    <div x-show="pharmacist" x-cloak>
                        <label for="license_number" class="block text-sm font-semibold text-ink">PRC license no.</label>
                        <input id="license_number" name="license_number" inputmode="numeric" maxlength="20" value="{{ old('license_number') }}" class="field-control mt-2 !w-40 text-base lg:text-sm">
                    </div>
                    <div x-show="pharmacist" x-cloak>
                        <label for="license_expires_on" class="block text-sm font-semibold text-ink">License valid until</label>
                        <input id="license_expires_on" name="license_expires_on" type="date" value="{{ old('license_expires_on') }}" class="field-control mt-2 !w-44 text-base lg:text-sm">
                    </div>
                @endif
                <button type="submit" class="seller-primary-button min-h-11">Add employee</button>
            </form>

            <ul class="sc-card divide-y divide-line" aria-label="Employees">
                @foreach ($members as $member)
                    @php($isSelf = $member->user_id === auth()->id())
                    @php($licenseOk = $member->canReviewPrescriptions())
                    <li class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 p-4">
                        <div class="min-w-0">
                            <p class="font-semibold text-ink">{{ $member->user?->name }} <span class="font-normal text-muted">{{ $member->user?->email }}</span></p>
                            <p class="text-sm text-muted">
                                {{ $roleLabels[$member->role] ?? $member->role }}
                                &middot; {{ $member->store?->name ?? 'Head office' }}
                                @if ($member->is_pharmacist)
                                    &middot; Pharmacist
                                    @if ($licenseOk)
                                        &middot; PRC {{ $member->license_number }}, valid until {{ $member->license_expires_on->format('M j, Y') }}
                                    @else
                                        &middot; <span class="font-semibold text-danger">{{ $member->license_number ? 'License expired' : 'No PRC license on file' }} — cannot approve prescriptions</span>
                                    @endif
                                @endif
                                @if ($member->user?->clerk_user_id === null) &middot; Has not signed in yet @endif
                            </p>
                        </div>
                        @if ($isOwner && $isSelf)
                            <form method="POST" action="{{ route('seller.staff.update', $member) }}" x-data="{ pharmacist: @js($member->is_pharmacist) }" class="flex flex-wrap items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <label class="flex min-h-11 items-center gap-2 text-sm text-ink">
                                    <input type="checkbox" name="is_pharmacist" value="1" x-model="pharmacist" @checked($member->is_pharmacist) class="size-4">
                                    I am a pharmacist
                                </label>
                                <label class="sr-only" for="license-{{ $member->id }}">Your PRC license number</label>
                                <input x-show="pharmacist" x-cloak id="license-{{ $member->id }}" name="license_number" inputmode="numeric" maxlength="20" placeholder="PRC license no." value="{{ $member->license_number }}" class="field-control !w-40 text-base lg:text-sm">
                                <label class="sr-only" for="expires-{{ $member->id }}">Your license is valid until</label>
                                <input x-show="pharmacist" x-cloak id="expires-{{ $member->id }}" name="license_expires_on" type="date" value="{{ $member->license_expires_on?->toDateString() }}" class="field-control !w-44 text-base lg:text-sm">
                                <button type="submit" class="seller-outline-button min-h-11">Save</button>
                            </form>
                        @elseif ($member->role !== 'owner' && ! $isSelf && ($isOwner || ! $member->isHeadOffice()) && ($canAssignManagers || $member->role === 'staff'))
                            <div class="flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('seller.staff.update', $member) }}" x-data="{ pharmacist: @js($member->is_pharmacist) }" class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <label class="sr-only" for="role-{{ $member->id }}">Role for {{ $member->user?->email }}</label>
                                    <select id="role-{{ $member->id }}" name="role" class="field-control !w-32 text-base lg:text-sm">
                                        <option value="staff" @selected($member->role === 'staff')>Staff</option>
                                        @if ($canAssignManagers)<option value="manager" @selected($member->role === 'manager')>Manager</option>@endif
                                    </select>
                                    <label class="sr-only" for="branch-{{ $member->id }}">Branch for {{ $member->user?->email }}</label>
                                    <select id="branch-{{ $member->id }}" name="store_id" class="field-control !w-56 text-base lg:text-sm">
                                        @if ($isOwner)<option value="">Head office</option>@endif
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}" @selected($member->store_id === $branch->id)>{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                    @if ($isOwner)
                                        <label class="flex min-h-11 items-center gap-2 text-sm text-ink">
                                            <input type="checkbox" name="is_pharmacist" value="1" x-model="pharmacist" @checked($member->is_pharmacist) class="size-4">
                                            Pharmacist
                                        </label>
                                        <label class="sr-only" for="license-{{ $member->id }}">PRC license number for {{ $member->user?->email }}</label>
                                        <input x-show="pharmacist" x-cloak id="license-{{ $member->id }}" name="license_number" inputmode="numeric" maxlength="20" placeholder="PRC license no." value="{{ $member->license_number }}" class="field-control !w-40 text-base lg:text-sm">
                                        <label class="sr-only" for="expires-{{ $member->id }}">License valid until for {{ $member->user?->email }}</label>
                                        <input x-show="pharmacist" x-cloak id="expires-{{ $member->id }}" name="license_expires_on" type="date" value="{{ $member->license_expires_on?->toDateString() }}" class="field-control !w-44 text-base lg:text-sm">
                                    @endif
                                    <button type="submit" class="seller-outline-button min-h-11">Save</button>
                                </form>
                                <form method="POST" action="{{ route('seller.staff.destroy', $member) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="seller-outline-button min-h-11 text-danger">Remove</button>
                                </form>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($isOwner)
                @php($successors = $members->filter(fn ($member) => $member->user_id !== auth()->id() && $member->user?->clerk_user_id !== null))
                <section class="sc-card p-4" aria-labelledby="transfer-heading">
                    <h2 id="transfer-heading" class="font-semibold text-ink">Transfer ownership</h2>
                    <p class="mt-1 text-sm text-muted">Hand the pharmacy to another employee. They become the owner at head office and you stay on as a head-office manager. Only someone who has already signed in can take over.</p>
                    @if ($successors->isEmpty())
                        <p class="mt-3 text-sm text-muted">Nobody can take over yet. Add the person as an employee and ask them to sign in first.</p>
                    @else
                        <form method="POST" x-data="{ member: @js(old('member_id', $successors->first()->id)) }" :action="@js(route('seller.staff.transfer-ownership', '__member__')).replace('__member__', member)" action="{{ route('seller.staff.transfer-ownership', $successors->first()) }}" data-confirm="Hand over the pharmacy? You will no longer be the owner." data-confirm-tone="danger" class="mt-3 flex flex-wrap items-end gap-4">
                            @csrf
                            <div>
                                <label for="member_id" class="block text-sm font-semibold text-ink">New owner</label>
                                <select id="member_id" name="member_id" x-model="member" class="field-control mt-2 !w-72 text-base lg:text-sm">
                                    @foreach ($successors as $successor)
                                        <option value="{{ $successor->id }}">{{ $successor->user->email }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="min-w-56 flex-1">
                                <label for="confirm_email" class="block text-sm font-semibold text-ink">Type their email to confirm</label>
                                <input id="confirm_email" name="confirm_email" type="email" required autocomplete="off" class="field-control mt-2 w-full text-base lg:text-sm">
                                <x-input-error :messages="$errors->get('confirm_email')" class="mt-2" />
                            </div>
                            <button type="submit" class="seller-outline-button min-h-11 text-danger">Transfer ownership</button>
                        </form>
                    @endif
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
