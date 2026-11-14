<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateStoreSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $tenant = $this->user()?->sellerTenant();

        return $tenant !== null && $this->user()->tenantMemberships()
            ->where('tenant_id', $tenant->id)
            ->whereIn('role', ['owner', 'manager'])
            ->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:2000'],
            'phone' => ['required', 'string', 'max:30'],
            'address_line' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'digits:4'],
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'return_address' => ['nullable', 'string', 'max:255'],
            'processing_days' => ['nullable', 'integer', 'between:1,30'],
            'fulfillment_methods' => ['nullable', 'string', 'max:120'],
            'delivery_areas' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'delivery_radius_km' => ['nullable', 'integer', 'between:1,500'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get additional validation callables for the request.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->hasFile('logo') && $this->boolean('remove_logo')) {
                $validator->errors()->add('remove_logo', 'Choose a replacement logo or remove the current logo, not both.');
            }
        }];
    }
}
