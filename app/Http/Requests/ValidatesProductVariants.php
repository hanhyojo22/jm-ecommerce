<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

trait ValidatesProductVariants
{
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

            $combination = mb_strtolower(trim($variant['color'])).'|'.mb_strtolower(trim($variant['size']));
            $sku = mb_strtolower(trim($variant['sku']));
            if (isset($combinations[$combination])) {
                $validator->errors()->add("variants.$index.color", 'Each color and size combination must be unique.');
            }
            if (isset($skus[$sku]) || $sku === mb_strtolower(trim((string) $this->input('sku')))) {
                $validator->errors()->add("variants.$index.sku", 'Each variant needs a unique SKU.');
            }
            $combinations[$combination] = true;
            $skus[$sku] = true;
            $totalStock += (int) $variant['stock_quantity'];
        }

        if ($totalStock > 2147483647) {
            $validator->errors()->add('variants', 'Total variant stock is too large.');
        }
    }
}
