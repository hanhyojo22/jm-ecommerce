<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidatesMedicineDirections;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A licensed pharmacist sets the directions for one purchased medicine while reviewing its
 * prescription. They replace the product label when the buyer sets up reminders.
 */
class OrderItemDirectionsController extends Controller
{
    use ValidatesMedicineDirections;

    public function __invoke(Request $request, SellerOrder $sellerOrder, OrderItem $orderItem): RedirectResponse
    {
        $member = $request->user()->sellerMembership() ?? abort(403);
        // Another branch's order, or an item from another order, is a 404.
        $sellerOrder = $member->sellerOrders()->whereKey($sellerOrder)->firstOrFail();
        $item = $sellerOrder->items()->whereKey($orderItem)->firstOrFail();
        abort_unless($member->canReviewPrescriptions(), 403);

        $directions = self::validatedMedicineDirections([...$request->all(), 'medicine_kind' => $request->input('medicine_kind', 'maintenance')], withPackUnits: false);
        // Stored with the same types the product columns cast to, so the app reads both alike.
        $counts = ['doses_per_day', 'course_days', 'min_hours_between', 'max_doses_per_day'];
        $item->update(['medication_directions' => collect($directions)
            ->map(fn ($value, string $field) => in_array($field, $counts, true) && $value !== null ? (int) $value : $value)
            ->all()]);

        return back()->with('status', "Reminder directions saved for {$item->product_name}.");
    }
}
