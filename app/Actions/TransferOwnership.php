<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferOwnership
{
    /**
     * Hands the pharmacy to another employee who has already signed in. They become the owner at head
     * office; the old owner stays on as a head-office manager, so the company always has exactly one owner.
     */
    public function handle(User $owner, TenantMember $newOwner, ?string $ipAddress = null): TenantMember
    {
        return DB::transaction(function () use ($owner, $newOwner, $ipAddress): TenantMember {
            $members = TenantMember::query()
                ->where('tenant_id', $newOwner->tenant_id)
                ->where(fn ($query) => $query->whereKey($newOwner->id)->orWhere('user_id', $owner->id))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $oldOwner = $members->firstWhere('user_id', $owner->id);
            $newOwner = $members->firstWhere('id', $newOwner->id);

            if ($oldOwner?->role !== 'owner') {
                throw ValidationException::withMessages(['member' => 'Only the owner can hand over the pharmacy.']);
            }

            if ($newOwner === null || $newOwner->is($oldOwner)) {
                throw ValidationException::withMessages(['member' => 'Choose another employee to become the owner.']);
            }

            if ($newOwner->user()->value('clerk_user_id') === null) {
                throw ValidationException::withMessages(['member' => 'That employee has not signed in yet. Ownership can only go to someone who already uses their account.']);
            }

            $previous = $newOwner->only(['role', 'store_id']);
            $oldOwner->update(['role' => 'manager', 'store_id' => null]);
            $newOwner->update(['role' => 'owner', 'store_id' => null]);

            AuditLog::query()->create([
                'actor_user_id' => $owner->id,
                'action' => 'tenant.ownership_transferred',
                'entity_type' => TenantMember::class,
                'entity_id' => $newOwner->id,
                'old_values' => ['owner_user_id' => $owner->id, ...$previous],
                'new_values' => ['owner_user_id' => $newOwner->user_id, 'role' => 'owner', 'store_id' => null],
                'ip_address' => $ipAddress,
            ]);

            return $newOwner;
        });
    }
}
