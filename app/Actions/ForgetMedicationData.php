<?php

namespace App\Actions;

use App\Models\CaregiverLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deletes everything medicine reminders stored about a person, for a withdrawn consent or a
 * deleted account. Accounts are anonymised rather than removed, so foreign-key cascades never run.
 */
class ForgetMedicationData
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->medicationPlans()->delete();
            $user->careProfiles()->delete();
            CaregiverLink::query()->where('patient_user_id', $user->id)->orWhere('caregiver_user_id', $user->id)->delete();
            $user->forceFill(['medication_reminders_consent_at' => null])->save();
        });
    }
}
