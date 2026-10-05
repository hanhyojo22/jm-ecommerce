<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Store;
use App\Models\StoreSuspensionAppeal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class ReviewStoreSuspensionAppeal
{
    public function __construct(private ReactivateStore $reactivateStore) {}

    public function handle(User $administrator, Store $store, StoreSuspensionAppeal $appeal, string $decision, string $reviewNote, ?string $ipAddress = null): StoreSuspensionAppeal
    {
        return DB::transaction(function () use ($administrator, $store, $appeal, $decision, $reviewNote, $ipAddress): StoreSuspensionAppeal {
            $store = Store::query()->lockForUpdate()->findOrFail($store->id);
            $appeal = StoreSuspensionAppeal::query()
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->findOrFail($appeal->id);

            if ($appeal->status !== 'pending') {
                throw new LogicException('Only pending appeals can be reviewed.');
            }

            if ($decision === 'approved' && $store->status !== 'suspended') {
                throw new LogicException('Only appeals for suspended stores can be approved.');
            }

            $appeal->update([
                'status' => $decision,
                'review_note' => $reviewNote,
                'reviewed_by' => $administrator->id,
                'reviewed_at' => now(),
            ]);

            if ($decision === 'approved') {
                $this->reactivateStore->handle($administrator, $store, $ipAddress);
            }

            AuditLog::query()->create([
                'actor_user_id' => $administrator->id,
                'action' => 'store.suspension_appeal.'.$decision,
                'entity_type' => StoreSuspensionAppeal::class,
                'entity_id' => $appeal->id,
                'new_values' => ['store_id' => $store->id, 'status' => $decision, 'review_note' => $reviewNote],
                'ip_address' => $ipAddress,
            ]);

            return $appeal;
        });
    }
}
