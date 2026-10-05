<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Store;
use App\Models\StoreSuspensionAppeal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SubmitStoreSuspensionAppeal
{
    public function handle(User $seller, string $statement, ?string $ipAddress = null): StoreSuspensionAppeal
    {
        $membership = $seller->tenantMemberships()
            ->where('role', 'owner')
            ->whereHas('tenant', fn (Builder $tenant): Builder => $tenant
                ->where(function (Builder $query): void {
                    $query->where('status', 'suspended')
                        ->orWhereHas('store', fn (Builder $store): Builder => $store->where('status', 'suspended'));
                }))
            ->with('tenant.store')
            ->oldest('created_at')
            ->first();

        if ($membership?->tenant?->store === null) {
            throw new AccessDeniedHttpException;
        }

        $storeId = $membership->tenant->store->id;

        return DB::transaction(function () use ($seller, $statement, $ipAddress, $storeId): StoreSuspensionAppeal {
            $store = Store::query()->lockForUpdate()->findOrFail($storeId);
            $tenant = $store->tenant()->lockForUpdate()->firstOrFail();

            if ($store->status !== 'suspended' && $tenant->status !== 'suspended') {
                throw new LogicException('This store is not currently suspended.');
            }

            if ($store->appeals()->where('status', 'pending')->exists()) {
                throw new LogicException('Your appeal is already awaiting review.');
            }

            $appeal = $store->appeals()->create([
                'seller_id' => $seller->id,
                'statement' => $statement,
                'status' => 'pending',
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $seller->id,
                'action' => 'store.suspension_appeal.submitted',
                'entity_type' => StoreSuspensionAppeal::class,
                'entity_id' => $appeal->id,
                'new_values' => ['store_id' => $store->id, 'status' => 'pending'],
                'ip_address' => $ipAddress,
            ]);

            return $appeal;
        });
    }
}
