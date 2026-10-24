<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
{
    use ValidatesProductVariants;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

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
            'stock_quantity' => ['required_without:variants', 'integer', 'min:0', 'max:2147483647'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'variants' => ['nullable', 'array', 'max:30'],
            'variants.*' => ['array:color,size,sku,stock_quantity,price,images'],
            'variants.*.price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'variants.*.images' => ['nullable', 'array', 'max:4'],
            'variants.*.images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'variants.*.color' => ['nullable', 'required_without:variants.*.size', 'string', 'max:60'],
            'variants.*.size' => ['nullable', 'required_without:variants.*.color', 'string', 'max:60'],
            'variants.*.sku' => ['required', 'string', 'max:100'],
            'variants.*.stock_quantity' => ['required', 'integer', 'min:0', 'max:2147483647'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->validateVariants($validator);
            if (count((array) $this->file('images', [])) + ($this->hasFile('image') ? 1 : 0) > 8) {
                $validator->errors()->add('images', 'Upload no more than 8 product photos.');
            }

            if ($validator->errors()->has('sku')) {
                return;
            }

            $tenant = $this->user()?->sellerTenant();

            if ($tenant !== null && Product::query()->where('tenant_id', $tenant->id)->where('sku', (string) $this->string('sku'))->exists()) {
                $validator->errors()->add('sku', 'This SKU is already used by this store.');
            }
        }];
    }
}
