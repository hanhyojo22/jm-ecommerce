<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\SellerApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class RejectSellerApplication
{
    public function handle(User $administrator, SellerApplication $application, string $reason, ?string $ipAddress = null): SellerApplication
    {
        return DB::transaction(function () use ($administrator, $application, $reason, $ipAddress): SellerApplication {
            $application = SellerApplication::query()->lockForUpdate()->findOrFail($application->id);

            if (! in_array($application->status, ['pending', 'submitted', 'under_review'], true)) {
                throw new LogicException('Only pending seller applications can be rejected.');
            }

            $oldStatus = $application->status;
            $application->update([
                'status' => 'rejected',
                'reviewed_at' => now(),
                'reviewed_by' => $administrator->id,
                'review_note' => $reason,
            ]);
            $application->statusHistory()->create(['actor_user_id' => $administrator->id, 'from_status' => $oldStatus, 'to_status' => 'rejected', 'reason' => $reason, 'created_at' => now()]);
            $application->reviews()->create(['reviewer_id' => $administrator->id, 'action' => 'rejected', 'note' => $reason]);

            AuditLog::query()->create([
                'actor_user_id' => $administrator->id,
                'action' => 'seller_application.rejected',
                'entity_type' => SellerApplication::class,
                'entity_id' => $application->id,
                'old_values' => ['status' => 'pending'],
                'new_values' => ['status' => 'rejected', 'reason' => $reason],
                'ip_address' => $ipAddress,
            ]);

            return $application;
        });
    }
}
