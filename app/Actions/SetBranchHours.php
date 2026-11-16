<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SetBranchHours
{
    /**
     * @param  array{opening_time: ?string, closing_time: ?string, prep_minutes: ?int}  $hours  null times mean the branch never closes
     */
    public function handle(User $actor, Store $branch, array $hours, ?string $ipAddress = null): Store
    {
        return DB::transaction(function () use ($actor, $branch, $hours, $ipAddress): Store {
            $branch = Store::query()->lockForUpdate()->findOrFail($branch->id);
            $oldValues = $branch->only(Store::HOURS);

            $branch->update($hours);

            AuditLog::query()->create([
                'actor_user_id' => $actor->id,
                'action' => 'store.hours_updated',
                'entity_type' => Store::class,
                'entity_id' => $branch->id,
                'old_values' => $oldValues,
                'new_values' => $branch->only(Store::HOURS),
                'ip_address' => $ipAddress,
            ]);

            return $branch;
        });
    }
}
