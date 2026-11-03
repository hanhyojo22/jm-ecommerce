<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ReturnCase;
use App\Models\ReturnCaseEvent;
use App\Models\ReturnCaseEvidence;
use App\Models\ReturnCaseItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * @mixin ReturnCase
 */
class ReturnCaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'reason' => $this->reason,
            'requested_remedy' => $this->requested_remedy,
            'remedy' => $this->remedy,
            'details' => $this->details,
            'response_note' => $this->response_note,
            'return_waived' => (bool) $this->return_waived,
            'return_instructions' => $this->return_instructions,
            'return_method' => $this->return_method,
            'created_at' => $this->created_at?->toIso8601String(),
            'deadline' => $this->deadline()?->toIso8601String(),
            'respond_by' => $this->respond_by?->toIso8601String(),
            'buyer_respond_by' => $this->buyer_respond_by?->toIso8601String(),
            'return_by' => $this->return_by?->toIso8601String(),
            'fulfill_by' => $this->fulfill_by?->toIso8601String(),
            'handed_off_at' => $this->handed_off_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'can_respond' => $request->user()?->can('respond', $this->resource) ?? false,
            'seller_order' => $this->whenLoaded('sellerOrder', fn (): array => [
                'id' => $this->sellerOrder->id,
                'order_id' => $this->sellerOrder->order_id,
                'status' => $this->sellerOrder->status,
                'store' => $this->sellerOrder->store === null ? null : [
                    'id' => $this->sellerOrder->store->id,
                    'name' => $this->sellerOrder->store->name,
                    'slug' => $this->sellerOrder->store->slug,
                    'logo_url' => $this->sellerOrder->store->logo_path === null
                        ? null
                        : Storage::disk('public')->url($this->sellerOrder->store->logo_path),
                ],
            ]),
            'items' => $this->whenLoaded('items', fn (): array => $this->items
                ->map(fn (ReturnCaseItem $item): array => [
                    'id' => $item->id,
                    'order_item_id' => $item->order_item_id,
                    'product_name' => $item->orderItem?->product_name,
                    'variant_label' => $item->orderItem?->variant_label,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'line_total' => bcmul((string) $item->unit_price, (string) $item->quantity, 2),
                ])
                ->all()),
            'events' => $this->whenLoaded('events', fn (): array => $this->events
                ->map(fn (ReturnCaseEvent $event): array => [
                    'type' => $event->type,
                    'note' => $event->note,
                    'actor' => $event->actor?->name,
                    'created_at' => $event->created_at?->toIso8601String(),
                ])
                ->all()),
            'evidence' => $this->whenLoaded('evidence', fn (): array => $this->evidence
                ->map(fn (ReturnCaseEvidence $evidence): array => [
                    'id' => $evidence->id,
                    'kind' => $evidence->kind,
                    'name' => $evidence->original_name,
                    'url' => URL::temporarySignedRoute('api.v1.media.return-evidence.show', now()->addHour(), ['evidence' => $evidence->id]),
                ])
                ->all()),
            'outcome' => $this->whenLoaded('outcome', fn (): ?array => $this->outcome === null ? null : [
                'kind' => $this->outcome->kind,
                'amount' => $this->outcome->amount === null ? null : (string) $this->outcome->amount,
                'paid_at' => $this->outcome->paid_at?->toIso8601String(),
                'shipment_reference' => $this->outcome->shipment_reference,
                'shipped_at' => $this->outcome->shipped_at?->toIso8601String(),
            ]),
        ];
    }
}
