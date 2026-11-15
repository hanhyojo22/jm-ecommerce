<?php

namespace App\Http\Controllers\Seller;

use App\Actions\ReviewPrescription;
use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Models\SellerOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pharmacists at the filling branch (or at head office) read and decide an order's prescription.
 */
class PrescriptionController extends Controller
{
    public function show(Request $request, SellerOrder $sellerOrder): StreamedResponse
    {
        $prescription = $this->prescription($request, $sellerOrder);

        return Storage::disk('chat_attachments')->download($prescription->path, $prescription->original_name);
    }

    public function update(Request $request, SellerOrder $sellerOrder, ReviewPrescription $review): RedirectResponse
    {
        $prescription = $this->prescription($request, $sellerOrder);
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:500'],
        ]);

        $review->handle($request->user(), $prescription, $data['decision'] === 'approve', $data['reason'] ?? null, $request->ip());

        return back()->with('status', $data['decision'] === 'approve' ? 'Prescription approved. The order can now be confirmed.' : 'Prescription rejected and the order cancelled.');
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
