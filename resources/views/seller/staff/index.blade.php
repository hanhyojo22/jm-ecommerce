<x-app-layout :error-summary="false">
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <h1 class="sc-title">Staff</h1>
                <p class="sc-subtitle">{{ $members->count() }} {{ Str::plural('team member', $members->count()) }}</p>
            </div>
            <button type="button" class="seller-primary-button" aria-haspopup="dialog" aria-controls="invite-member" x-data @click="document.getElementById('invite-member').showModal()">Invite member</button>
        </div>
    </x-slot>

    @php
        $roleLabels = ['owner' => 'Owner', 'manager' => 'Manager', 'staff' => 'Staff'];
    @endphp

    <div class="page-shell">
        <div class="page-container seller-workspace-page sc-stack">
            @if (session('status'))<p class="sc-notice" data-tone="success" role="status">{{ session('status') }}</p>@endif
            <x-input-error :messages="$errors->all()" />
            @unless ($isOwner)
                <p class="sc-notice" data-tone="info">{{ $canAssignManagers ? 'Only the owner can add head-office people, change them, or make someone a pharmacist.' : 'You add and change staff at your own branch. Head office adds managers and pharmacists.' }}</p>
            @endunless

            <dialog id="invite-member" class="sc-member-dialog" aria-labelledby="invite-member-heading" x-data x-init="@js($errors->any() && old('_staff_form') === 'invite') && $el.showModal()">
                <div class="sc-dialog-head">
                    <h2 id="invite-member-heading">Invite member</h2>
                    <button type="button" @click="$el.closest('dialog').close()" class="sc-dialog-close" aria-label="Close invite member"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6"/></svg></button>
                </div>
                <form method="POST" action="{{ route('seller.staff.store') }}">
                    @csrf
                    @include('seller.staff._fields', ['member' => null])
                    <div class="sc-dialog-actions">
                        <button type="button" @click="$el.closest('dialog').close()" class="seller-outline-button">Cancel</button>
                        <button type="submit" class="seller-primary-button">Add employee</button>
                    </div>
                </form>
            </dialog>

            <section class="sc-card" aria-label="Team members" x-data="{ search: '' }">
                <div class="sc-toolbar">
                    <label for="team-search" class="sr-only">Search team members</label>
                    <input id="team-search" type="search" x-model="search" placeholder="Search team members" class="field-control max-w-xs">
                </div>
                <div class="sc-table-wrap">
                    <table class="sc-table sc-staff-table" aria-label="Employees">
                        <thead><tr><th scope="col">Member</th><th scope="col">Role</th><th scope="col">Branch</th><th scope="col">PRC license</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                        <tbody x-ref="teamRows">
                            @foreach ($members as $member)
                                @php
                                    $isSelf = $member->user_id === auth()->id();
                                    $licenseOk = $member->canReviewPrescriptions();
                                    $canEdit = ($isOwner && $isSelf) || ($member->role !== 'owner' && ! $isSelf && ($isOwner || ! $member->isHeadOffice()) && ($canAssignManagers || $member->role === 'staff'));
                                @endphp
                                <tr data-member-search="{{ mb_strtolower($member->user?->name.' '.$member->user?->email) }}" x-show="!search.trim() || $el.dataset.memberSearch.includes(search.toLowerCase().trim())">
                                    <th scope="row">
                                        <span class="sc-member-identity">
                                            <span class="seller-studio-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($member->user?->name ?? '', 0, 1)) }}</span>
                                            <span><strong>{{ $member->user?->name }}</strong><small>{{ $member->user?->email }}</small></span>
                                        </span>
                                    </th>
                                    <td data-label="Role">{{ $roleLabels[$member->role] ?? $member->role }}@if ($member->is_pharmacist)<small class="sc-cell-note">Pharmacist</small>@endif</td>
                                    <td data-label="Branch">{{ $member->store?->name ?? 'Head office' }}</td>
                                    <td data-label="PRC license">
                                        @if ($member->is_pharmacist)
                                            <span class="sc-badge" data-tone="{{ $licenseOk ? 'success' : 'danger' }}">{{ $licenseOk ? 'Valid' : ($member->license_number ? 'Expired' : 'Missing license') }}</span>
                                            @if ($member->license_number)<small class="sc-cell-note">PRC {{ $member->license_number }}</small>@endif
                                            @if ($member->license_expires_on)<small class="sc-cell-note">Until {{ $member->license_expires_on->format('M j, Y') }}</small>@endif
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    </td>
                                    <td data-label="Status">
                                        @if ($member->user?->clerk_user_id)
                                            <span class="sc-badge" data-tone="success">Active</span>
                                        @elseif ($member->invitationExpired())
                                            <span class="sc-badge" data-tone="danger">Invitation expired</span>
                                            <small class="sc-cell-note">Remove and add them again to resend.</small>
                                        @else
                                            <span class="sc-badge" data-tone="neutral">Awaiting sign-in</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if ($canEdit)
                                            <button type="button" class="seller-outline-button" aria-label="Edit {{ $member->user?->name }}" aria-haspopup="dialog" aria-controls="member-{{ $member->id }}" x-data @click="document.getElementById(@js('member-'.$member->id)).showModal()">Edit</button>
                                            <dialog id="member-{{ $member->id }}" class="sc-member-dialog" aria-labelledby="member-heading-{{ $member->id }}" x-data x-init="@js($errors->any() && old('_staff_form') === $member->id) && $el.showModal()">
                                                <div class="sc-dialog-head">
                                                    <div><h2 id="member-heading-{{ $member->id }}">{{ $isSelf ? 'Your pharmacist license' : 'Edit member' }}</h2><p>{{ $member->user?->email }}</p></div>
                                                    <button type="button" @click="$el.closest('dialog').close()" class="sc-dialog-close" aria-label="Close edit member"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6"/></svg></button>
                                                </div>
                                                <form id="member-form-{{ $member->id }}" method="POST" action="{{ route('seller.staff.update', $member) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    @include('seller.staff._fields', ['member' => $member])
                                                </form>
                                                <div class="sc-dialog-actions">
                                                    @unless ($isSelf)
                                                        <form method="POST" action="{{ route('seller.staff.destroy', $member) }}" class="mr-auto" data-confirm="Remove this member from your team?" data-confirm-tone="danger">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="seller-outline-button text-danger">Remove</button>
                                                        </form>
                                                    @endunless
                                                    <button type="button" @click="$el.closest('dialog').close()" class="seller-outline-button">Cancel</button>
                                                    <button type="submit" form="member-form-{{ $member->id }}" class="seller-primary-button">Save changes</button>
                                                </div>
                                            </dialog>
                                        @elseif ($isSelf)
                                            <span class="text-muted">You</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="sc-staff-empty" x-cloak x-show="search.trim() && !Array.from($refs.teamRows.querySelectorAll('[data-member-search]')).some(row => row.dataset.memberSearch.includes(search.toLowerCase().trim()))">
                                <td colspan="6" class="text-center text-muted">No members found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($isOwner)
                @php
                    $successors = $members->filter(fn ($member) => $member->user_id !== auth()->id() && $member->user?->clerk_user_id !== null);
                @endphp
                <details class="sc-card sc-dashboard-support" @if ($errors->has('confirm_email')) open @endif>
                    <summary>Transfer ownership<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
                    <div class="border-t border-line p-6">
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
                    </div>
                </details>
            @endif
        </div>
    </div>
</x-app-layout>
