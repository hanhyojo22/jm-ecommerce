<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Validation\Rule;

/**
 * The senior citizen / PWD discount on one order line. Checkout, both checkout previews, PayMongo
 * and refunds all read the discount from here so their totals agree.
 */
class SeniorPwdDiscount
{
    public const CARD_TYPES = ['senior' => 'Senior citizen', 'pwd' => 'PWD'];

    /**
     * Medicines are what the law discounts; the seller's drug class says whether a product is one.
     */
    public static function appliesTo(Product $product): bool
    {
        return $product->isMedicine();
    }

    /**
     * Pesos taken off a VAT-inclusive line total. VAT-registered: the line without its 12% VAT,
     * less 20% of that; otherwise 20% of the line.
     */
    public static function on(string $lineTotal): string
    {
        $config = config('marketplace.senior_pwd');

        if (! $config['vat_registered']) {
            return self::round(bcmul($lineTotal, $config['rate'], 4));
        }

        $withoutVat = self::round(bcdiv($lineTotal, bcadd('1', $config['vat_rate'], 4), 4));
        $payable = bcsub($withoutVat, self::round(bcmul($withoutVat, $config['rate'], 4)), 2);

        return bcsub($lineTotal, $payable, 2);
    }

    /**
     * Checkout fields for the card. Leaving the type empty means no discount.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'discount_card' => ['nullable', 'array'],
            'discount_card.type' => ['nullable', Rule::in(array_keys(self::CARD_TYPES))],
            'discount_card.id_number' => ['required_with:discount_card.type', 'nullable', 'string', 'max:40'],
            'discount_card.name' => ['required_with:discount_card.type', 'nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return ?array{type: string, id_number: string, name: string}
     */
    public static function cardFrom(array $validated): ?array
    {
        $card = $validated['discount_card'] ?? null;

        return blank($card['type'] ?? null) ? null : [
            'type' => $card['type'],
            'id_number' => trim($card['id_number']),
            'name' => trim($card['name']),
        ];
    }

    /**
     * What the discount would be on the given medicine lines, for the checkout preview.
     *
     * @param  iterable<array{product: Product, total: string}>  $lines
     */
    public static function preview(iterable $lines): string
    {
        $discount = '0.00';

        foreach ($lines as $line) {
            if (self::appliesTo($line['product'])) {
                $discount = bcadd($discount, self::on($line['total']), 2);
            }
        }

        return $discount;
    }

    /**
     * A keyed hash of the card, so orders can be matched on the same ID without storing the
     * number in the clear. Spaces, dashes and case are ignored: "sc-123" and "SC 123" match.
     */
    public static function fingerprint(string $type, string $idNumber): string
    {
        // shortcut: keyed on APP_KEY, so rotating the key stops new orders matching old ones; re-fingerprint orders if the key ever changes.
        $normalized = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($idNumber));

        return hash_hmac('sha256', $type.'|'.$normalized, (string) config('app.key'));
    }

    /**
     * Shows the last four characters only, for anywhere outside the branch's own order page.
     */
    public static function mask(?string $idNumber): ?string
    {
        return $idNumber === null ? null : str_repeat('•', max(0, mb_strlen($idNumber) - 4)).mb_substr($idNumber, -4);
    }

    /**
     * Half-up to the centavo; amounts here are never negative.
     */
    private static function round(string $amount): string
    {
        return bcadd($amount, '0.005', 2);
    }
}
