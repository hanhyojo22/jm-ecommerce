<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CaregiverLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Caregivers are other app users told when the patient hasn't marked a dose. They accept in their
 * own app and never see the patient's plans.
 */
class CaregiverController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $invitations = CaregiverLink::query()
            ->where(fn ($query) => $query->where('caregiver_user_id', $user->id)
                ->orWhere(fn ($pending) => $pending->whereNull('caregiver_user_id')->where('invited_email', Str::lower($user->email))))
            ->with('patient:id,name')
            ->latest()
            ->get();

        return response()->json(['data' => [
            'caregivers' => $user->caregiverLinks()->with('caregiver:id,name')->latest()->get()->map(fn (CaregiverLink $link): array => $this->present($link)),
            'invitations' => $invitations->map(fn (CaregiverLink $link): array => $this->present($link)),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasMedicationConsent(), 403, 'Turn on medicine reminders first.');
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'notify_after_minutes' => ['nullable', 'integer', 'in:30,60,120'],
            'share_medicine_names' => ['boolean'],
        ]);
        $email = Str::lower($data['email']);
        abort_if($email === Str::lower($request->user()->email), 422, 'Invite someone other than yourself.');

        $link = $request->user()->caregiverLinks()->updateOrCreate(['invited_email' => $email], [
            'notify_after_minutes' => $data['notify_after_minutes'] ?? 60,
            'share_medicine_names' => $data['share_medicine_names'] ?? false,
        ]);

        $created = $link->wasRecentlyCreated;

        return response()->json(['data' => $this->present($link->refresh()->load('caregiver:id,name'))], $created ? 201 : 200);
    }

    public function accept(Request $request, string $link): JsonResponse
    {
        $link = CaregiverLink::query()
            ->whereKey($link)
            ->whereNull('caregiver_user_id')
            ->where('invited_email', Str::lower($request->user()->email))
            ->firstOrFail();
        $link->update(['caregiver_user_id' => $request->user()->id, 'status' => 'accepted']);

        return response()->json(['data' => $this->present($link->load('patient:id,name'))]);
    }

    /**
     * Either side can end the link.
     */
    public function destroy(Request $request, string $link): Response
    {
        CaregiverLink::query()
            ->whereKey($link)
            ->where(fn ($query) => $query->where('patient_user_id', $request->user()->id)->orWhere('caregiver_user_id', $request->user()->id))
            ->firstOrFail()
            ->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CaregiverLink $link): array
    {
        return [
            'id' => $link->id,
            'status' => $link->status,
            'invited_email' => $link->invited_email,
            'caregiver_name' => $link->relationLoaded('caregiver') ? $link->caregiver?->name : null,
            'patient_name' => $link->relationLoaded('patient') ? $link->patient?->name : null,
            'notify_after_minutes' => $link->notify_after_minutes,
            'share_medicine_names' => $link->share_medicine_names,
        ];
    }
}
