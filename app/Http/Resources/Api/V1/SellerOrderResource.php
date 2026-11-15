<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
use App\Models\OrderIssue;
use App\Models\ReturnCase;
use App\Models\SellerOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin SellerOrder
 */
class SellerOrderResource extends JsonResource
{
    /**
     * One store's part of a purchase: what the buyer sees as a card on the order list.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'subtotal' => (string) $this->subtotal,
            'shipping_amount' => (string) $this->shipping_amount,
            'total' => (string) $this->total,
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'cod_collected_at' => $this->cod_collected_at?->toIso8601String(),
            'can_confirm_receipt' => $this->status === 'shipped',
            'store' => $this->whenLoaded('store', fn (): array => [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'slug' => $this->store->slug,
                'logo_url' => $this->store->logo_path === null
                    ? null
                    : Storage::disk('public')->url($this->store->logo_path),
            ]),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'latest_issue' => $this->whenLoaded('issues', fn (): ?array => $this->latestIssue()),
            'latest_return' => $this->whenLoaded('returnCases', fn (): ?array => $this->latestReturn()),
            'order' => $this->whenLoaded('order', fn (): array => [
                'id' => $this->order->id,
                'number' => $this->order->number,
                'short_number' => Order::shortNumber($this->order->number),
                'total' => (string) $this->order->total,
                'created_at' => $this->order->created_at?->toIso8601String(),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestIssue(): ?array
    {
        $issue = $this->issues->first();

        return $issue instanceof OrderIssue ? [
            'id' => $issue->id,
            'type' => $issue->type,
            'status' => $issue->status,
            'details' => $issue->details,
            'created_at' => $issue->created_at?->toIso8601String(),
        ] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestReturn(): ?array
    {
        $case = $this->returnCases->first();

        return $case instanceof ReturnCase ? [
            'id' => $case->id,
            'status' => $case->status,
            'reason' => $case->reason,
            'requested_remedy' => $case->requested_remedy,
            'remedy' => $case->remedy,
            'details' => $case->details,
            'created_at' => $case->created_at?->toIso8601String(),
            'respond_by' => $case->respond_by?->toIso8601String(),
            'buyer_respond_by' => $case->buyer_respond_by?->toIso8601String(),
            'return_by' => $case->return_by?->toIso8601String(),
        ] : null;
    }
}
