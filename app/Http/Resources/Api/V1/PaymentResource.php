<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'method' => $this->method,
            'label' => Payment::METHOD_LABELS[$this->method] ?? $this->method,
            'status' => $this->status,
            'amount' => (string) $this->amount,
            'fee_amount' => (string) $this->fee_amount,
        ];
    }
}
