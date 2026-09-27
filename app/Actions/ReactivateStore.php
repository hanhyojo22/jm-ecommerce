<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class ReactivateStore
{
    public function handle(User $administrator, Store $store, ?string $ipAddress = null): Store
    {
        return DB::transaction(function () use ($administrator, $store, $ipAddress): Store {
            $store = Store::query()->lockForUpdate()->findOrFail($store->id);
            $tenant = $store->tenant()->lockForUpdate()->firstOrFail();

            if ($store->status !== 'suspended') {
                throw new LogicException('Only a suspended store can be reactivated.');
            }

            $oldValues = ['store_status' => $store->status, 'tenant_status' => $tenant->status];

            $store->update(['status' => 'active']);
            $tenant->update(['status' => 'active']);

            AuditLog::query()->create([
                'actor_user_id' => $administrator->id,
                'action' => 'store.reactivated',
                'entity_type' => Store::class,
                'entity_id' => $store->id,
                'old_values' => $oldValues,
                'new_values' => ['store_status' => 'active', 'tenant_status' => 'active'],
                'ip_address' => $ipAddress,
            ]);

            return $store;
        });
    }
}
