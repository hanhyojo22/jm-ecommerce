<x-app-layout :error-summary="false">
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <h1 class="sc-title">Staff</h1>
                <p class="sc-subtitle">Who works for the pharmacy. Head office sees every branch; branch staff see only their branch. Only pharmacists can approve prescriptions.</p>
            </div>
        </div>
    </x-slot>

    @php($roleLabels = ['owner' => 'Owner', 'manager' => 'Manager', 'staff' => 'Staff'])

    <div class="page-shell">
        <div class="page-container seller-workspace-page sc-stack">
            @if (session('status'))<p class="sc-notice" data-tone="success" role="status">{{ session('status') }}</p>@endif
            <x-input-error :messages="$errors->get('member')" />

            <form method="POST" action="{{ route('seller.staff.store') }}" class="sc-card flex flex-wrap items-end gap-4 p-4" aria-labelledby="add-staff-heading">
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
                        <option value="manager" @selected(old('role') === 'manager')>Manager</option>
                    </select>
                </div>
                <div>
                    <label for="store_id" class="block text-sm font-semibold text-ink">Works at</label>
                    <select id="store_id" name="store_id" class="field-control mt-2 !w-56 text-base lg:text-sm">
                        <option value="">Head office (every branch)</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('store_id') === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('store_id')" class="mt-2" />
                </div>
                <label class="flex min-h-11 items-center gap-2 text-sm font-semibold text-ink">
                    <input type="checkbox" name="is_pharmacist" value="1" @checked(old('is_pharmacist')) class="size-4">
                    Pharmacist
                </label>
                <button type="submit" class="seller-primary-button min-h-11">Add employee</button>
            </form>

            <ul class="sc-card divide-y divide-line" aria-label="Employees">
                @foreach ($members as $member)
                    <li class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 p-4">
                        <div class="min-w-0">
                            <p class="font-semibold text-ink">{{ $member->user?->name }} <span class="font-normal text-muted">{{ $member->user?->email }}</span></p>
                            <p class="text-sm text-muted">
                                {{ $roleLabels[$member->role] ?? $member->role }}
                                &middot; {{ $member->store?->name ?? 'Head office' }}
                                @if ($member->is_pharmacist) &middot; Pharmacist @endif
                                @if ($member->user?->clerk_user_id === null) &middot; Has not signed in yet @endif
                            </p>
                        </div>
                        @if ($member->role !== 'owner' && $member->user_id !== auth()->id())
                            <div class="flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('seller.staff.update', $member) }}" class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <label class="sr-only" for="role-{{ $member->id }}">Role for {{ $member->user?->email }}</label>
                                    <select id="role-{{ $member->id }}" name="role" class="field-control !w-32 text-base lg:text-sm">
                                        <option value="staff" @selected($member->role === 'staff')>Staff</option>
                                        <option value="manager" @selected($member->role === 'manager')>Manager</option>
                                    </select>
                                    <label class="sr-only" for="branch-{{ $member->id }}">Branch for {{ $member->user?->email }}</label>
                                    <select id="branch-{{ $member->id }}" name="store_id" class="field-control !w-56 text-base lg:text-sm">
                                        <option value="">Head office</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}" @selected($member->store_id === $branch->id)>{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                    <label class="flex min-h-11 items-center gap-2 text-sm text-ink">
                                        <input type="checkbox" name="is_pharmacist" value="1" @checked($member->is_pharmacist) class="size-4">
                                        Pharmacist
                                    </label>
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
        </div>
    </div>
</x-app-layout>
