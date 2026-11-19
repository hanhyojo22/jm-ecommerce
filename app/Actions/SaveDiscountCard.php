<?php

namespace App\Actions;

use App\Models\DiscountCard;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SaveDiscountCard
{
    /**
     * Keeps a typed ID and its photo for next time. Opting in never blocks the order: a full
     * list or an ID already saved just means there is nothing new to store.
     *
     * @param  array{type: string, id_number: string, name: string}  $card
     */
    public function handle(User $user, array $card, Order $order): ?DiscountCard
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
            ...$this->copyPhoto($user, $order),
        ]);
    }

    /**
     * A photo sent with a saved ID becomes that ID's photo: the first one for an ID saved
     * before photos were kept, or a newer one when the old card was replaced. A new photo
     * needs a fresh review.
     */
    public function replaceDocument(DiscountCard $card, Order $order): void
    {
        $oldPath = $card->document_path;

        // A retried checkout replays the same order; its photo is already this card's.
        if ($oldPath !== null && basename($oldPath) === basename($order->discount_document_path)) {
            return;
        }

        $card->update([
            ...$this->copyPhoto($card->user, $order),
            'document_reviewed_at' => null,
            'document_reviewed_by' => null,
            'document_reviewed_tenant_id' => null,
        ]);

        if ($oldPath !== null) {
            Storage::disk('chat_attachments')->delete($oldPath);
        }
    }

    /**
     * Copies the order's photo under the same file name: a matching name is how a review of
     * an order's photo finds the saved ID it came from.
     *
     * @return array{document_path: string, document_name: ?string, document_mime: ?string}
     */
    private function copyPhoto(User $user, Order $order): array
    {
        $path = 'discount-cards/'.$user->id.'/'.basename($order->discount_document_path);

        if (! Storage::disk('chat_attachments')->copy($order->discount_document_path, $path)) {
            throw new RuntimeException('The discount ID photo could not be saved.');
        }

        return [
            'document_path' => $path,
            'document_name' => $order->discount_document_name,
            'document_mime' => $order->discount_document_mime,
        ];
    }
}
