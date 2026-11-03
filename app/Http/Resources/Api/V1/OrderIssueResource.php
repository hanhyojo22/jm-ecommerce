<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OrderIssue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderIssue
 */
class OrderIssueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seller_order_id' => $this->seller_order_id,
            'type' => $this->type,
            'status' => $this->status,
            'details' => $this->details,
            'resolution_note' => $this->resolution_note,
            'counts_as_refusal' => (bool) $this->counts_as_refusal,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
