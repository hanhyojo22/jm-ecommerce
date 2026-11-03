<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
use App\Models\SellerOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'short_number' => Order::shortNumber($this->number),
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'subtotal' => (string) $this->subtotal,
            'shipping_amount' => (string) $this->shipping_amount,
            'total' => (string) $this->total,
            'delivery' => [
                'recipient_name' => $this->recipient_name,
                'phone' => $this->phone,
                'address_line' => $this->address_line,
                'city' => $this->city,
                'province' => $this->province,
                'postal_code' => $this->postal_code,
                'note' => $this->delivery_note,
            ],
            'payment' => $this->relationLoaded('payment') && $this->payment !== null
                ? PaymentResource::make($this->payment)
                : null,
            'seller_orders' => SellerOrderResource::collection($this->whenLoaded('sellerOrders')),
            'can_pay' => $this->canPay(),
            'can_confirm_receipt' => $this->relationLoaded('sellerOrders')
                && $this->sellerOrders->contains(fn (SellerOrder $sellerOrder): bool => $sellerOrder->status === 'shipped'),
        ];
    }

    /**
     * A pending online payment is the only state where opening PayMongo again is useful.
     */
    private function canPay(): bool
    {
        if (! $this->relationLoaded('payment') || ! $this->relationLoaded('sellerOrders')) {
            return false;
        }

        return $this->payment !== null
            && $this->payment->isOnline()
            && $this->payment->status === 'pending'
            && $this->sellerOrders->contains(fn (SellerOrder $sellerOrder): bool => $sellerOrder->status === 'pending');
    }
}
