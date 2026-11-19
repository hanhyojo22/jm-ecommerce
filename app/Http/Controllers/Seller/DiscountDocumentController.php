<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DiscountCard;
use App\Models\Order;
use App\Models\SellerOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiscountDocumentController extends Controller
{
    public function show(Request $request, SellerOrder $sellerOrder): StreamedResponse
    {
        $order = $this->scopedOrder($request, $sellerOrder)->order;
        abort_unless($order->discount_document_path, 404);

        return Storage::disk('chat_attachments')->response($order->discount_document_path, $order->discount_document_name, [
            'Content-Type' => $order->discount_document_mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function update(Request $request, SellerOrder $sellerOrder): RedirectResponse
    {
        $sellerOrder = $this->scopedOrder($request, $sellerOrder);
        abort_unless($request->user()->sellerMembership()->canWorkOrders(), 403);
        DB::transaction(function () use ($request, $sellerOrder): void {
            Order::query()->lockForUpdate()->findOrFail($sellerOrder->order_id);
            $sellerOrder = SellerOrder::query()->lockForUpdate()->findOrFail($sellerOrder->id);
            if (! $sellerOrder->order->discount_document_path || bccomp((string) $sellerOrder->discount_amount, '0', 2) <= 0 || in_array($sellerOrder->status, ['delivered', 'cancelled'], true)) {
                throw ValidationException::withMessages(['discount' => 'Only an active discounted order with a submitted ID can be reviewed.']);
            }
            if ($sellerOrder->discount_reviewed_at !== null) {
                return;
            }
            $sellerOrder->forceFill(['discount_reviewed_by' => $request->user()->id, 'discount_reviewed_at' => now()])->save();
            // A saved ID holding this same photo carries the review to the customer's later orders.
            $photo = basename($sellerOrder->order->discount_document_path);
            DiscountCard::query()->where('user_id', $sellerOrder->order->user_id)->where('document_path', 'like', '%/'.$photo)->get()
                ->each(fn (DiscountCard $card) => $card->update([
                    'document_reviewed_at' => $sellerOrder->discount_reviewed_at,
                    'document_reviewed_by' => $request->user()->id,
                    'document_reviewed_tenant_id' => $sellerOrder->tenant_id,
                ]));
            AuditLog::query()->create([
                'actor_user_id' => $request->user()->id,
                'action' => 'order.discount_id_reviewed',
                'entity_type' => SellerOrder::class,
                'entity_id' => $sellerOrder->id,
                'new_values' => ['order_id' => $sellerOrder->order_id],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Discount ID reviewed. Check the original ID at handover. Prescription approval is separate.');
    }

    /**
     * Records that staff saw the original card when the customer received the order: the
     * check the law relies on, kept as proof alongside the photo review.
     */
    public function confirmCard(Request $request, SellerOrder $sellerOrder): RedirectResponse
    {
        $sellerOrder = $this->scopedOrder($request, $sellerOrder);
        abort_unless($request->user()->sellerMembership()->canWorkOrders(), 403);
        DB::transaction(function () use ($request, $sellerOrder): void {
            Order::query()->lockForUpdate()->findOrFail($sellerOrder->order_id);
            $sellerOrder = SellerOrder::query()->lockForUpdate()->findOrFail($sellerOrder->id);
            if (bccomp((string) $sellerOrder->discount_amount, '0', 2) <= 0 || $sellerOrder->status === 'cancelled') {
                throw ValidationException::withMessages(['discount' => 'Only a discounted order that is not cancelled can have its card checked.']);
            }
            if ($sellerOrder->discount_card_checked_at !== null) {
                return;
            }
            $sellerOrder->forceFill(['discount_card_checked_by' => $request->user()->id, 'discount_card_checked_at' => now()])->save();
            AuditLog::query()->create([
                'actor_user_id' => $request->user()->id,
                'action' => 'order.discount_card_checked',
                'entity_type' => SellerOrder::class,
                'entity_id' => $sellerOrder->id,
                'new_values' => ['order_id' => $sellerOrder->order_id],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Original ID checked at handover.');
    }

    private function scopedOrder(Request $request, SellerOrder $sellerOrder): SellerOrder
    {
        $member = $request->user()->sellerMembership() ?? abort(403);

        return $member->sellerOrders()->whereKey($sellerOrder)->with('order')->firstOrFail();
    }
}
