<?php

namespace App\Actions;

use App\Models\DiscountCard;
use App\Models\User;

class SaveDiscountCard
{
    /**
     * Keeps a typed ID for next time. Opting in never blocks the order: a full
     * list or an ID already saved just means there is nothing new to store.
     *
     * @param  array{type: string, id_number: string, name: string}  $card
     */
    public function handle(User $user, array $card): ?DiscountCard
    {
        $saved = $user->discountCards()->get();

        if ($saved->count() >= DiscountCard::LIMIT
            || $saved->contains(fn (DiscountCard $existing): bool => $existing->matches($card['type'], $card['id_number']))) {
            return null;
        }

        $number = trim($card['id_number']);

        return $user->discountCards()->create([
            'type' => $card['type'],
            'id_number' => $number,
            'name' => trim($card['name']),
            'last4' => mb_substr($number, -4),
        ]);
    }
}
