<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Validation\Validator;

trait ValidatesProductVariants
{
    /**
     * Names variant fields by their row, so errors read "SKU for variant 2" instead of "variants.1.sku".
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'variants.*.color' => 'color for variant :position',
            'variants.*.color_hex' => 'swatch for variant :position',
            'variants.*.size' => 'size for variant :position',
            'variants.*.sku' => 'SKU for variant :position',
            'variants.*.stock_quantity' => 'stock for variant :position',
            'variants.*.price' => 'price for variant :position',
            'variants.*.images' => 'photos for variant :position',
            'variants.*.images.*' => 'photo for variant :position',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'variants.*.color.required_without' => 'Variant :position needs a color or a size.',
            'variants.*.size.required_without' => 'Variant :position needs a color or a size.',
        ];
    }

    protected function validateVariants(Validator $validator): void
    {
        $variants = $this->input('variants', []);
        if (! is_array($variants)) {
            return;
        }

        $combinations = [];
        $skus = [];
        $totalStock = 0;

        foreach ($variants as $index => $variant) {
            if (! is_array($variant) || $validator->errors()->has("variants.$index.color") || $validator->errors()->has("variants.$index.size") || $validator->errors()->has("variants.$index.sku") || $validator->errors()->has("variants.$index.stock_quantity")) {
                continue;
            }

            $combination = mb_strtolower(trim((string) ($variant['color'] ?? ''))).'|'.mb_strtolower(trim((string) ($variant['size'] ?? '')));
            $sku = mb_strtolower(trim($variant['sku']));
            if (isset($combinations[$combination])) {
                $validator->errors()->add("variants.$index.color", 'Each variant needs a different combination of options.');
            }
            if (isset($skus[$sku]) || $sku === mb_strtolower(trim((string) $this->input('sku')))) {
                $validator->errors()->add("variants.$index.sku", 'Each variant needs a unique SKU.');
            }
            $combinations[$combination] = true;
            $skus[$sku] = true;
            $totalStock += (int) $variant['stock_quantity'];

            if (isset($variant['id'])) {
                $product = $this->route('product');
                if ($product instanceof Product && $product->variants()->whereKey($variant['id'])->exists()
                    && $product->images()->where('product_variant_id', $variant['id'])->whereNotIn('id', $this->removedImageIds())->count() + count((array) $this->file("variants.$index.images", [])) > 4) {
                    $validator->errors()->add("variants.$index.images", 'Each variant can have no more than 4 photos.');
                }
            }
        }

        if ($totalStock > 2147483647) {
            $validator->errors()->add('variants', 'Total variant stock is too large.');
        }
    }

    /**
     * Photo IDs the seller marked for removal in this save.
     *
     * @return list<string>
     */
    protected function removedImageIds(): array
    {
        return array_values(array_filter((array) $this->input('remove_images', []), 'is_string'));
    }
}
