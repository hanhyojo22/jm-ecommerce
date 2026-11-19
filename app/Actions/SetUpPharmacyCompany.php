<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;

class SetUpPharmacyCompany
{
    /**
     * Creates the pharmacy company and its head-office owner, run once on a new server. The owner
     * signs in through Clerk with this email; the verified email links the account on first sign-in.
     * An existing customer account is promoted, since an owner can still shop. Everyday Pharmacy itself is the actor.
     */
    public function handle(string $companyName, string $ownerEmail): Tenant
    {
        return DB::transaction(function () use ($companyName, $ownerEmail): Tenant {
            $ownerEmail = Str::lower(trim($ownerEmail));
            $owner = User::query()->whereRaw('LOWER(email) = ?', [$ownerEmail])->lockForUpdate()->first();

            if ($owner?->account_type === 'admin') {
                throw new LogicException('That email belongs to an administrator. Use a separate email for the pharmacy owner.');
            }
            if ($owner !== null && $owner->tenantMemberships()->exists()) {
                throw new LogicException('That email already works for a pharmacy company.');
            }

            $owner ??= User::query()->create([
                'name' => Str::before($ownerEmail, '@'),
                'email' => $ownerEmail,
                'password' => Hash::make(Str::random(64)),
                'is_active' => true,
            ]);
            $owner->forceFill(['account_type' => 'seller', 'preferred_workspace' => 'seller'])->save();

            $tenant = Tenant::query()->create(['name' => $companyName, 'slug' => $this->uniqueSlug($companyName), 'status' => 'active']);
            TenantMember::query()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner']);

            AuditLog::query()->create([
                'actor_user_id' => null,
                'action' => 'tenant.set_up',
                'entity_type' => Tenant::class,
                'entity_id' => $tenant->id,
                'new_values' => ['name' => $companyName, 'owner_email' => $ownerEmail],
            ]);

            return $tenant;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'pharmacy';
        $slug = $base;

        for ($suffix = 2; Tenant::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
