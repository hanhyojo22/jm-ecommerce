<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    use ValidatesProductVariants;

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
            'stock_quantity' => ['required_without:variants', 'integer', 'min:0', 'max:2147483647'],
            'status' => ['required', 'in:draft,active,inactive,changes_required,rejected'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_images' => ['nullable', 'array', 'max:40'],
            'remove_images.*' => ['string', 'ulid'],
            'image_order' => ['nullable', 'array', 'max:8'],
            'image_order.*' => ['string', 'ulid'],
            'variants' => ['nullable', 'array', 'max:30'],
            'variants.*' => ['array:id,color,color_hex,size,sku,stock_quantity,price,images'],
            'variants.*.price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'variants.*.images' => ['nullable', 'array'],
            'variants.*.images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'variants.*.id' => ['nullable', 'ulid'],
            'variants.*.color' => ['nullable', 'required_without:variants.*.size', 'string', 'max:60'],
            'variants.*.color_hex' => ['nullable', 'hex_color', 'size:7'],
            'variants.*.size' => ['nullable', 'required_without:variants.*.color', 'string', 'max:60'],
            'variants.*.sku' => ['required', 'string', 'max:100'],
            'variants.*.stock_quantity' => ['required', 'integer', 'min:0', 'max:2147483647'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $product = $this->route('product');

            $this->validateVariants($validator);
            if ($product instanceof Product) {
                $productImageIds = $product->images()->pluck('id')->all();
                if (array_diff($this->removedImageIds(), $productImageIds) !== []) {
                    $validator->errors()->add('remove_images', 'Only this product’s photos can be removed.');
                }
                $sharedImageIds = $product->images()->whereNull('color')->pluck('id')->all();
                if (array_diff(array_filter((array) $this->input('image_order', []), 'is_string'), $sharedImageIds) !== []) {
                    $validator->errors()->add('image_order', 'Only this product’s photos can be reordered.');
                }
                $keptSharedPhotos = count(array_diff($sharedImageIds, $this->removedImageIds()));
                if ($keptSharedPhotos + count((array) $this->file('images', [])) + ($this->hasFile('image') ? 1 : 0) > 8) {
                    $validator->errors()->add('images', 'A product can have no more than 8 photos. Remove a photo to add another.');
                }
            }
            $variants = $this->input('variants', []);
            if ($product instanceof Product && $product->variants()->exists() && (! is_array($variants) || $variants === [])) {
                $validator->errors()->add('variants', 'Keep at least one variant for this product.');
            }
            if (is_array($variants) && $product instanceof Product) {
                $existingIds = $product->variants()->pluck('id')->all();
                $submittedIds = [];
                foreach ($variants as $index => $variant) {
                    $id = is_array($variant) ? ($variant['id'] ?? null) : null;
                    if ($id !== null && (! in_array($id, $existingIds, true) || in_array($id, $submittedIds, true))) {
                        $validator->errors()->add("variants.$index.id", 'This variant does not belong to this product.');
                    }
                    if ($id !== null) {
                        $submittedIds[] = $id;
                    }
                }
                if (array_diff($existingIds, $submittedIds) !== []) {
                    $validator->errors()->add('variants', 'Existing variants cannot be removed. Set their stock to 0 instead.');
                }
            }

            if ($product instanceof Product && $product->status !== 'active' && $this->input('status') === 'active') {
                $validator->errors()->add('status', 'Only an administrator can activate a listing.');
            }

            if ($product instanceof Product && (in_array($product->status, ['pending', 'inactive'], true) || ! in_array($this->input('status'), [$product->status, 'draft', ...($product->status === 'active' ? ['inactive'] : [])], true))) {
                $validator->errors()->add('status', 'This listing cannot be edited in its current review state.');
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
