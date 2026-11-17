<?php

namespace App\Http\Controllers\Seller;

use App\Actions\ReviewPrescription;
use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Models\PrescriptionImage;
use App\Models\SellerOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pharmacists at the filling branch (or at head office) read every page of an order's prescription
 * and decide it.
 */
class PrescriptionController extends Controller
{
    public function show(Request $request, SellerOrder $sellerOrder, PrescriptionImage $image): StreamedResponse
    {
        $prescription = $this->prescription($request, $sellerOrder);
        abort_unless($image->prescription_id === $prescription->id, 404);

        return Storage::disk('chat_attachments')->download($image->path, $image->original_name);
    }

    public function update(Request $request, SellerOrder $sellerOrder, ReviewPrescription $review): RedirectResponse
    {
        $prescription = $this->prescription($request, $sellerOrder);
        $data = $request->validate([
            'decision' => ['required', Rule::in(ReviewPrescription::DECISIONS)],
            'reason' => ['required_unless:decision,approve', 'nullable', 'string', 'max:500'],
        ]);

        $review->handle($request->user(), $prescription, $data['decision'], $data['reason'] ?? null, $request->ip());

        return back()->with('status', match ($data['decision']) {
            'approve' => 'Prescription approved. The order is accepted and ready to prepare.',
            'needs_photo' => 'The customer was asked for a clearer photo.',
            default => 'Prescription rejected and the order cancelled.',
        });
    }

    /**
     * Another branch's order is a 404; a non-pharmacist at the right branch is a 403.
     */
    private function prescription(Request $request, SellerOrder $sellerOrder): Prescription
    {
        $member = $request->user()->sellerMembership() ?? abort(403);
        $sellerOrder = $member->sellerOrders()->whereKey($sellerOrder)->firstOrFail();
        abort_unless($member->is_pharmacist, 403);

        return Prescription::query()->where('order_id', $sellerOrder->order_id)->firstOrFail();
    }
}
