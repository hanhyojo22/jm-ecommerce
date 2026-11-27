<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dose the buyer marked taken or skipped. scheduled_for is the slot it answers (null for an
 * as-needed dose); client_uuid lets the app resend offline logs without duplicates.
 */
class MedicationDoseLog extends Model
{
    use HasUlids;

    protected $fillable = ['scheduled_for', 'status', 'quantity', 'logged_at', 'client_uuid'];

    protected function casts(): array
    {
        return ['scheduled_for' => 'datetime', 'logged_at' => 'datetime', 'quantity' => 'decimal:2'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MedicationPlan::class, 'medication_plan_id');
    }
}
