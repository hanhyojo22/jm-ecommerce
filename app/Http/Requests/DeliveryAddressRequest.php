<?php

namespace App\Http\Requests;

use App\Models\DeliveryAddress;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DeliveryAddressRequest extends FormRequest
{
    /**
     * Changing an address is authorized before its fields are validated, so a
     * foreign address is reported missing instead of leaking validation details.
     */
    public function authorize(): bool
    {
        $address = $this->route('deliveryAddress');

        if ($address instanceof DeliveryAddress) {
            Gate::authorize('update', $address);
        }

        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^(?:\+63|0)9\d{9}$/'],
            'address_line' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'regex:/^\d{4}$/'],
        ];
    }
}
