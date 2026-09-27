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

            if ($application->status !== 'pending') {
                throw new LogicException('Only pending seller applications can be rejected.');
            }

            $application->update([
                'status' => 'rejected',
                'reviewed_at' => now(),
                'reviewed_by' => $administrator->id,
                'review_note' => $reason,
            ]);

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
