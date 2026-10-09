<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('product')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:100'],
            'condition' => ['nullable', Rule::in(['new', 'refurbished'])],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'compare_at_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99', 'gt:price'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'status' => ['required', 'in:draft,active,inactive'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $product = $this->route('product');

            if ($product instanceof Product && $product->status !== 'active' && $this->input('status') === 'active') {
                $validator->errors()->add('status', 'Only an administrator can activate a listing.');
            }

            if ($validator->errors()->has('sku')) {
                return;
            }

            if ($product !== null && Product::query()
                ->where('tenant_id', $product->tenant_id)
                ->where('sku', (string) $this->string('sku'))
                ->whereKeyNot($product?->id)
                ->exists()) {
                $validator->errors()->add('sku', 'This SKU is already used by this store.');
            }
        }];
    }
}
