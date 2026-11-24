<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveStaffMember
{
    /**
     * Removes an employee from the pharmacy. Their account stays and becomes a customer account,
     * so past orders and messages keep their author.
     */
    public function handle(User $actor, TenantMember $member, ?string $ipAddress = null): void
    {
        DB::transaction(function () use ($actor, $member, $ipAddress): void {
            $member = TenantMember::query()->lockForUpdate()->findOrFail($member->id);

            if ($member->role === 'owner' || $member->user_id === $actor->id) {
                throw ValidationException::withMessages(['member' => 'The owner and your own account cannot be removed.']);
            }

            $member->delete();
            User::query()->whereKey($member->user_id)->update(['account_type' => 'buyer', 'preferred_workspace' => 'customer']);

            AuditLog::query()->create([
                'actor_user_id' => $actor->id,
                'action' => 'tenant_member.removed',
                'entity_type' => TenantMember::class,
                'entity_id' => $member->id,
                // tenant_id lets this company add the person back later (AddStaffMember::isFormerEmployee).
                'old_values' => $member->only(['tenant_id', 'user_id', 'role', 'store_id', 'is_pharmacist']),
                'ip_address' => $ipAddress,
            ]);
        });
    }
}
