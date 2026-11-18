<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeStaffMember
{
    /**
     * Changes an employee's role, branch or pharmacist details. The owner and the person making the change
     * are left alone, so head office can never lock itself out; the one exception is the owner updating
     * their own pharmacist license, which never touches their role or branch.
     *
     * @param  array{role: string, store_id: ?string, is_pharmacist: bool, license_number: ?string, license_expires_on: ?string}  $details
     */
    public function handle(User $actor, TenantMember $member, array $details, ?string $ipAddress = null): TenantMember
    {
        return DB::transaction(function () use ($actor, $member, $details, $ipAddress): TenantMember {
            $member = TenantMember::query()->lockForUpdate()->findOrFail($member->id);

            $ownLicenseOnly = $member->role === 'owner' && $member->user_id === $actor->id;

            if ($ownLicenseOnly) {
                $details = [...$details, 'role' => $member->role, 'store_id' => $member->store_id];
            } elseif ($member->role === 'owner' || $member->user_id === $actor->id) {
                throw ValidationException::withMessages(['member' => 'The owner and your own account cannot be changed here.']);
            }

            $oldValues = [...$member->only(['role', 'store_id', 'is_pharmacist', 'license_number']), 'license_expires_on' => $member->license_expires_on?->toDateString()];
            $member->update($details);

            AuditLog::query()->create([
                'actor_user_id' => $actor->id,
                'action' => 'tenant_member.changed',
                'entity_type' => TenantMember::class,
                'entity_id' => $member->id,
                'old_values' => $oldValues,
                'new_values' => $details,
                'ip_address' => $ipAddress,
            ]);

            return $member;
        });
    }
}
