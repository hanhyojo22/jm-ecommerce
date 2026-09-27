<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\SellerApplication;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class ApproveSellerApplication
{
    public function handle(User $administrator, SellerApplication $application, ?string $ipAddress = null): Tenant
    {
        return DB::transaction(function () use ($administrator, $application, $ipAddress): Tenant {
            $application = SellerApplication::query()->lockForUpdate()->findOrFail($application->id);

            if ($application->status !== 'pending') {
                throw new LogicException('Only pending seller applications can be approved.');
            }

            $tenant = Tenant::query()->create([
                'name' => $application->store_name,
                'slug' => $this->uniqueSlug($application->store_name, Tenant::class),
                'status' => 'active',
            ]);

            TenantMember::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $application->user_id,
                'role' => 'owner',
            ]);

            Store::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $application->store_name,
                'slug' => $this->uniqueSlug($application->store_name, Store::class),
                'description' => $application->store_description,
                'logo_path' => $application->logo_path,
                'phone' => $application->phone,
                'address_line' => $application->address_line,
                'city' => $application->city,
                'status' => 'active',
            ]);

            $application->update([
                'tenant_id' => $tenant->id,
                'status' => 'approved',
                'reviewed_at' => now(),
                'reviewed_by' => $administrator->id,
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $administrator->id,
                'action' => 'seller_application.approved',
                'entity_type' => SellerApplication::class,
                'entity_id' => $application->id,
                'new_values' => ['tenant_id' => $tenant->id, 'status' => 'approved'],
                'ip_address' => $ipAddress,
            ]);

            return $tenant;
        });
    }

    private function uniqueSlug(string $name, string $model): string
    {
        $base = Str::slug($name) ?: 'store';
        $slug = $base;

        while ($model::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::ulid());
        }

        return $slug;
    }
}
