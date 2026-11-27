<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Another app user who is told when the patient hasn't marked a dose. They get alerts only and
 * never read the patient's plans.
 */
class CaregiverLink extends Model
{
    use HasUlids;

    protected $fillable = ['caregiver_user_id', 'invited_email', 'status', 'notify_after_minutes', 'share_medicine_names'];

    protected function casts(): array
    {
        return ['share_medicine_names' => 'boolean', 'notify_after_minutes' => 'integer'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_user_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }
}
