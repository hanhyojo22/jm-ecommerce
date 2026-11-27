<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ForgetMedicationData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MedicationConsentController extends Controller
{
    public function store(Request $request): Response
    {
        $request->user()->forceFill(['medication_reminders_consent_at' => $request->user()->medication_reminders_consent_at ?? now()])->save();

        return response()->noContent();
    }

    /**
     * Withdrawing consent deletes every plan, log, profile and caregiver link.
     */
    public function destroy(Request $request, ForgetMedicationData $forget): Response
    {
        $forget->handle($request->user());

        return response()->noContent();
    }
}
