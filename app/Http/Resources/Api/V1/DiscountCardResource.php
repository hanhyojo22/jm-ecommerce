<?php

namespace App\Http\Resources\Api\V1;

use App\Models\DiscountCard;
use App\Support\SeniorPwdDiscount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DiscountCard
 */
class DiscountCardResource extends JsonResource
{
    /**
     * The full number never leaves the server; the masked form is enough to tell
     * two saved IDs apart.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'id_number_masked' => SeniorPwdDiscount::mask($this->id_number),
            // Without a photo on file, checkout needs one with this ID.
            'has_document' => $this->document_path !== null,
        ];
    }
}
