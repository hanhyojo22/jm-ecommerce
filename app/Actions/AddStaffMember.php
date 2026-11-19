<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AddStaffMember
{
    /**
     * Adds an employee to the pharmacy. The email must be new: it gets an account they claim by signing
     * in through Clerk with it.
     *
     * @param  array{email: string, role: string, store_id: ?string, is_pharmacist: bool, license_number: ?string, license_expires_on: ?string}  $details
     */
    public function handle(User $actor, Tenant $tenant, array $details, ?string $ipAddress = null): TenantMember
    {
        return DB::transaction(function () use ($actor, $tenant, $details, $ipAddress): TenantMember {
            $email = Str::lower(trim($details['email']));
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();

            // Existing accounts are never taken over: becoming staff changes what that person can do
            // (staff cannot buy from their own company), so it needs an email nobody uses yet.
            if ($user !== null) {
                throw ValidationException::withMessages(['email' => 'That email already has a Everyday Pharmacy account. Use a work email that has not signed up yet.']);
            }

            $user = User::query()->create(['name' => Str::before($email, '@'), 'email' => $email, 'password' => Hash::make(Str::random(64)), 'is_active' => true]);
            $user->forceFill(['account_type' => 'seller', 'preferred_workspace' => 'seller'])->save();

            $member = TenantMember::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role' => $details['role'],
                'store_id' => $details['store_id'],
                'is_pharmacist' => $details['is_pharmacist'],
                'license_number' => $details['license_number'],
                'license_expires_on' => $details['license_expires_on'],
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $actor->id,
                'action' => 'tenant_member.added',
                'entity_type' => TenantMember::class,
                'entity_id' => $member->id,
                'new_values' => ['email' => $email, 'role' => $details['role'], 'store_id' => $details['store_id'], 'is_pharmacist' => $details['is_pharmacist'], 'license_number' => $details['license_number'], 'license_expires_on' => $details['license_expires_on']],
                'ip_address' => $ipAddress,
            ]);

            return $member;
        });
    }

    /**
     * The branch id must be one of this company's branches; null means head office.
     */
    public static function assertOwnBranch(Tenant $tenant, ?string $storeId): void
    {
        if ($storeId !== null && ! Store::query()->where('tenant_id', $tenant->id)->whereKey($storeId)->exists()) {
            throw ValidationException::withMessages(['store_id' => 'Choose one of your branches.']);
        }
    }
}
