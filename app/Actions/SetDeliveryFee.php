<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SetDeliveryFee
{
    /**
     * Sets the flat fee every branch charges for delivery, and the item total above which
     * delivery is free (null for never). Orders already placed keep the fee they were charged.
     */
    public function handle(User $actor, Tenant $tenant, string $fee, ?string $freeDeliveryMinimum, ?string $ipAddress = null): Tenant
    {
        return DB::transaction(function () use ($actor, $tenant, $fee, $freeDeliveryMinimum, $ipAddress): Tenant {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            $oldValues = ['delivery_fee' => (string) $tenant->delivery_fee, 'free_delivery_minimum' => $tenant->free_delivery_minimum === null ? null : (string) $tenant->free_delivery_minimum];
            $newValues = ['delivery_fee' => $fee, 'free_delivery_minimum' => $freeDeliveryMinimum];

            $tenant->update($newValues);

            AuditLog::query()->create([
                'actor_user_id' => $actor->id,
                'action' => 'tenant.delivery_fee_updated',
                'entity_type' => Tenant::class,
                'entity_id' => $tenant->id,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => $ipAddress,
            ]);

            return $tenant;
        });
    }
}
