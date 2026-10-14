<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\SellerApplication;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class SuspendStore
{
    public function handle(User $administrator, Store $store, string $reason, ?string $ipAddress = null): Store
    {
        return DB::transaction(function () use ($administrator, $store, $reason, $ipAddress): Store {
            $store = Store::query()->lockForUpdate()->findOrFail($store->id);
            $tenant = $store->tenant()->lockForUpdate()->firstOrFail();

            if ($store->status === 'suspended') {
                throw new LogicException('This store is already suspended.');
            }

            $oldValues = ['store_status' => $store->status, 'tenant_status' => $tenant->status];

            $store->update(['status' => 'suspended']);
            $tenant->update(['status' => 'suspended']);
            $application = SellerApplication::query()->where('tenant_id', $tenant->id)->lockForUpdate()->first();
            if ($application && $application->status === 'approved') {
                $application->update(['status' => 'suspended', 'review_note' => $reason, 'reviewed_by' => $administrator->id, 'reviewed_at' => now()]);
                $application->statusHistory()->create(['actor_user_id' => $administrator->id, 'from_status' => 'approved', 'to_status' => 'suspended', 'reason' => $reason, 'created_at' => now()]);
                $application->reviews()->create(['reviewer_id' => $administrator->id, 'action' => 'suspended', 'note' => $reason]);
            }

            AuditLog::query()->create([
                'actor_user_id' => $administrator->id,
                'action' => 'store.suspended',
                'entity_type' => Store::class,
                'entity_id' => $store->id,
                'old_values' => $oldValues,
                'new_values' => ['store_status' => 'suspended', 'tenant_status' => 'suspended', 'reason' => $reason],
                'ip_address' => $ipAddress,
            ]);

            return $store;
        });
    }
}
