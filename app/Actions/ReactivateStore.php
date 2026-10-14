<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\SellerApplication;
use App\Models\Store;
use App\Models\StoreSuspensionAppeal;
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
            $application = SellerApplication::query()->where('tenant_id', $tenant->id)->lockForUpdate()->first();
            if ($application && $application->status === 'suspended') {
                $application->update(['status' => 'approved', 'reviewed_by' => $administrator->id, 'reviewed_at' => now()]);
                $application->statusHistory()->create(['actor_user_id' => $administrator->id, 'from_status' => 'suspended', 'to_status' => 'approved', 'created_at' => now()]);
                $application->reviews()->create(['reviewer_id' => $administrator->id, 'action' => 'reactivated']);
            }

            $store->appeals()->where('status', 'pending')->lockForUpdate()->get()->each(
                function (StoreSuspensionAppeal $appeal) use ($administrator, $store, $ipAddress): void {
                    $reviewNote = 'This store was reactivated directly by an administrator.';
                    $appeal->update([
                        'status' => 'approved',
                        'review_note' => $reviewNote,
                        'reviewed_by' => $administrator->id,
                        'reviewed_at' => now(),
                    ]);

                    AuditLog::query()->create([
                        'actor_user_id' => $administrator->id,
                        'action' => 'store.suspension_appeal.approved',
                        'entity_type' => StoreSuspensionAppeal::class,
                        'entity_id' => $appeal->id,
                        'new_values' => ['store_id' => $store->id, 'status' => 'approved', 'review_note' => $reviewNote],
                        'ip_address' => $ipAddress,
                    ]);
                },
            );

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
