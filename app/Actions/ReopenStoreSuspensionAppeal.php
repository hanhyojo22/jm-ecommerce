<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Store;
use App\Models\StoreSuspensionAppeal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class ReopenStoreSuspensionAppeal
{
    public function __construct(private SuspendStore $suspendStore) {}

    public function handle(User $administrator, Store $store, StoreSuspensionAppeal $appeal, string $reason, ?string $ipAddress = null): StoreSuspensionAppeal
    {
        return DB::transaction(function () use ($administrator, $store, $appeal, $reason, $ipAddress): StoreSuspensionAppeal {
            $store = Store::query()->lockForUpdate()->findOrFail($store->id);
            $appeal = StoreSuspensionAppeal::query()
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->findOrFail($appeal->id);

            if (! in_array($appeal->status, ['approved', 'rejected'], true)) {
                throw new LogicException('Only completed appeals can be reopened.');
            }

            if ($store->appeals()->where('status', 'pending')->exists()) {
                throw new LogicException('Another appeal is already awaiting review.');
            }

            if ($appeal->status === 'approved') {
                if ($store->status !== 'active') {
                    throw new LogicException('Only an active store with an approved appeal can be reopened.');
                }

                $this->suspendStore->handle($administrator, $store, 'Appeal reopened: '.$reason, $ipAddress);
            } elseif ($store->status !== 'suspended') {
                throw new LogicException('Only a suspended store with a rejected appeal can be reopened.');
            }

            $oldValues = $appeal->only(['status', 'review_note', 'reviewed_by', 'reviewed_at']);
            $appeal->update([
                'status' => 'pending',
                'review_note' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $administrator->id,
                'action' => 'store.suspension_appeal.reopened',
                'entity_type' => StoreSuspensionAppeal::class,
                'entity_id' => $appeal->id,
                'old_values' => $oldValues,
                'new_values' => ['store_id' => $store->id, 'status' => 'pending', 'reason' => $reason],
                'ip_address' => $ipAddress,
            ]);

            return $appeal;
        });
    }
}
