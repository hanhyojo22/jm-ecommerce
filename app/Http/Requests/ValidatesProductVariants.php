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
        $colorPhotoCounts = [];
        $product = $this->route('product');

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

            // Photos belong to the color, so every size of it counts toward the same four.
            $newPhotos = count((array) $this->file("variants.$index.images", []));
            if ($newPhotos > 0) {
                $color = trim((string) ($variant['color'] ?? ''));
                if ($color === '') {
                    $validator->errors()->add("variants.$index.images", 'Only a variant with a color can have its own photos. Use the product photos instead.');

                    continue;
                }
                $colorPhotoCounts[$color] ??= $product instanceof Product
                    ? $product->images()->where('color', $color)->whereNotIn('id', $this->removedImageIds())->count()
                    : 0;
                $colorPhotoCounts[$color] += $newPhotos;
                if ($colorPhotoCounts[$color] > 4) {
                    $validator->errors()->add("variants.$index.images", "Each color can have no more than 4 photos. Remove a {$color} photo to add another.");
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
