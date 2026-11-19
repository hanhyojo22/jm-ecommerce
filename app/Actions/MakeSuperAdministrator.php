<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;

class MakeSuperAdministrator
{
    /**
     * Creates or promotes the platform owner, run from the server console. The person signs in through
     * Clerk with this email; admin_invited_at marks the row as safe to link by verified email.
     * Pharmacy staff cannot also be administrators, so their accounts are refused. Everyday Pharmacy itself is the actor.
     */
    public function handle(string $email): User
    {
        return DB::transaction(function () use ($email): User {
            $email = Str::lower(trim($email));
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();

            if ($user !== null && $user->tenantMemberships()->exists()) {
                throw new LogicException('That email works for a pharmacy company. Use a separate email for the super admin.');
            }

            $oldValues = $user?->only(['account_type', 'platform_role', 'is_active']);
            $user ??= new User(['name' => Str::before($email, '@'), 'email' => $email, 'password' => Hash::make(Str::random(64))]);
            $user->forceFill([
                'account_type' => 'admin',
                'platform_role' => 'super_admin',
                'admin_invited_at' => $user->admin_invited_at ?? now(),
                'is_active' => true,
            ])->save();

            AuditLog::query()->create([
                'actor_user_id' => null,
                'action' => 'admin_account.made_super_admin',
                'entity_type' => User::class,
                'entity_id' => $user->id,
                'old_values' => $oldValues,
                'new_values' => ['email' => $email, 'account_type' => 'admin', 'platform_role' => 'super_admin'],
            ]);

            return $user;
        });
    }
}
