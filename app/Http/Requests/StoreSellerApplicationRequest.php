<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSellerApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasVerifiedClerkEmail() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return collect(['seller_agreement', 'terms', 'privacy', 'prohibited_products', 'returns_refunds', 'fees'])
            ->mapWithKeys(fn (string $policy): array => ['agreements.'.$policy => ['required', 'accepted']])
            ->all();
    }
}
