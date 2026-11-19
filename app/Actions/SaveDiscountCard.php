<?php

namespace App\Actions;

use App\Models\DiscountCard;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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
    public function handle(User $user, array $card, UploadedFile $document): ?DiscountCard
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
            ...$this->store($user, $document),
        ]);
    }

    /**
     * A photo sent with a saved ID becomes that ID's photo: the first one for an ID saved
     * before photos were kept, or a newer one when the old card was replaced.
     */
    public function replaceDocument(DiscountCard $card, UploadedFile $document): void
    {
        $oldPath = $card->document_path;

        $card->update($this->store($card->user, $document));

        if ($oldPath !== null) {
            Storage::disk('chat_attachments')->delete($oldPath);
        }
    }

    /**
     * @return array{document_path: string, document_name: string, document_mime: ?string}
     */
    private function store(User $user, UploadedFile $document): array
    {
        $path = $document->store('discount-cards/'.$user->id, 'chat_attachments');

        if ($path === false) {
            throw new RuntimeException('The discount ID photo could not be saved.');
        }

        return [
            'document_path' => $path,
            'document_name' => $document->getClientOriginalName(),
            'document_mime' => $document->getMimeType(),
        ];
    }
}
